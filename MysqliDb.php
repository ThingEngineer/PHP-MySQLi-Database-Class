<?php
/**
 * MySqliDb Class
 *
 * @category Database Access
 * @package MysqliDB
 * @author Jeffery Way <jeffrey@jeffrey-way.com>
 * @author Josh Campbell <jcampbell@ajillion.com>
 * @copyright Copyright (c) 2010 Jeffery Way
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version 1.1
 **/
class MysqliDB {

   /**
    * Static instance of self
    *
    * @var object
    */
   protected static $_instance;
   /**
    * MySQLi instance
    *
    * @var object
    */
   protected $_mysqli;
   /**
    * The SQL query to be prepared and executed
    *
    * @var object
    */
   protected $_query;
   /**
    * An array that holds where conditions 'fieldname' => 'value'
    *
    * @var array
    */
   protected $_where = array();
   /**
    * Dynamic type list for where condition values
    *
    * @var array
    */
   protected $_whereTypeList;
   /**
    * Dynamic type list for table data values
    *
    * @var array
    */
   protected $_paramTypeList;
   /**
    * Dynamic array that holds a combination of where condition/table data value types and parameter referances
    *
    * @var array
    */
   protected $_bindParams = array('');

   public function __construct($host, $username, $password, $db) {
      $this->_mysqli = new mysqli($host, $username, $password, $db) 
         or die('There was a problem connecting to the database');
      self::$_instance = $this;
   }

   /**
    * A method of returning the static instance to allow access to the 
    * instantiated object from within another class.
    * Inheriting this class would require reloading connection info.
    *
    * @uses $db = MySqliDb::getInstance();
    *
    * @return object Returns the current instance.
    */
   public static function getInstance()
   {
      return self::$_instance;
   }

   /**
    * Reset states after an execution
    *
    * @return object Returns the current instance.
    */
   protected function reset()
   {
      $this->_where = array();
      $this->_bindParams = array('');
      unset($this->_query);
      unset($this->_whereTypeList);
      unset($this->_paramTypeList);
   }
   
   /**
    * Pass in a raw query and an array containing the parameters to bind to the prepaird statement.
    *
    * @param string $query Contains a user-provided query.
    * @param array $bindData All variables to bind to the SQL statment.
    * @return array Contains the returned rows from the query.
    */
   public function rawQuery($query, $bindParams = NULL) 
   {
      $this->_query = filter_var($query, FILTER_SANITIZE_STRING);
      $stmt = $this->_prepareQuery();
      
      if (gettype($bindParams) === 'array') {
         $params = array('');
         foreach ($bindParams as $prop => $val) {
            $params[0] .= $this->_determineType($val);
            array_push($params, &$bindParams[$prop]);
         }
         
         call_user_func_array(array($stmt, 'bind_param'), $params);
      }
      
      $stmt->execute();
      $this->reset();

      $results = $this->_dynamicBindResults($stmt);
      return $results;
   }

   /**
    * 
    * @param string $query Contains a user-provided select query.
    * @param int $numRows The number of rows total to return.
    * @return array Contains the returned rows from the query.
    */
   public function query($query, $numRows = NULL) 
   {
      $this->_query = filter_var($query, FILTER_SANITIZE_STRING);
      $stmt = $this->_buildQuery($numRows);
      $stmt->execute();
      $this->reset();

      $results = $this->_dynamicBindResults($stmt);
      return $results;
   }

   /**
    * A convenient SELECT * function.
    *
    * @param string $tableName The name of the database table to work with.
    * @param int $numRows The number of rows total to return.
    * @return array Contains the returned rows from the select query.
    */
   public function get($tableName, $numRows = NULL) 
   {

      $this->_query = "SELECT * FROM $tableName";
      $stmt = $this->_buildQuery($numRows);
      $stmt->execute();
      $this->reset();

      $results = $this->_dynamicBindResults($stmt);
      return $results;
   }

   /**
    *
    * @param <string $tableName The name of the table.
    * @param array $insertData Data containing information for inserting into the DB.
    * @return boolean Boolean indicating whether the insert query was completed succesfully.
    */
   public function insert($tableName, $insertData) 
   {
      $this->_query = "INSERT into $tableName";
      $stmt = $this->_buildQuery(NULL, $insertData);
      $stmt->execute();
      $this->reset();

      ($stmt->affected_rows) ? $result = $stmt->insert_id : $result = false;
      return $result;
   }

   /**
    * Update query. Be sure to first call the "where" method.
    *
    * @param string $tableName The name of the database table to work with.
    * @param array $tableData Array of data to update the desired row.
    * @return boolean
    */
   public function update($tableName, $tableData) 
   {
      $this->_query = "UPDATE $tableName SET ";

      $stmt = $this->_buildQuery(NULL, $tableData);
      $stmt->execute();
      $this->reset();

      ($stmt->affected_rows) ? $result = true : $result = false;
      return $result;
   }

   /**
    * Delete query. Call the "where" method first.
    *
    * @param string $tableName The name of the database table to work with.
    * @return boolean Indicates success. 0 or 1.
    */
   public function delete($tableName) {
      $this->_query = "DELETE FROM $tableName";

      $stmt = $this->_buildQuery();
      $stmt->execute();
      $this->reset();

      ($stmt->affected_rows) ? $result = true : $result = false;
      return $result;
   }

   /**
    * This method allows you to specify multipl WHERE statements for SQL queries.
    *
    * @param string $whereProp The name of the database field.
    * @param mixed $whereValue The value of the database field.
    */
   public function where($whereProp, $whereValue) 
   {
      $this->_where[$whereProp] = $whereValue;
   }

   /**
    * This method is needed for prepared statements. They require
    * the data type of the field to be bound with "i" s", etc.
    * This function takes the input, determines what type it is,
    * and then updates the param_type.
    *
    * @param mixed $item Input to determine the type.
    * @return string The joined parameter types.
    */
   protected function _determineType($item) 
   {
      switch (gettype($item)) {
         case 'string':
            return 's';
            break;

         case 'integer':
            return 'i';
            break;

         case 'blob':
            return 'b';
            break;

         case 'double':
            return 'd';
            break;
      }
   }

   /**
    * Abstraction method that will compile the WHERE statement,
    * any passed update data, and the desired rows.
    * It then builds the SQL query.
    *
    * @param int $numRows The number of rows total to return.
    * @param array $tableData Should contain an array of data for updating the database.
    * @return object Returns the $stmt object.
    */
   protected function _buildQuery($numRows = NULL, $tableData = NULL) 
   {
      (gettype($tableData) === 'array') ? $hasTableData = true : $hasTableData = false;   
      (!empty($this->_where )) ? $hasConditional = true : $hasConditional = false;

      // Did the user call the "where" method?
      if (!empty($this->_where)) {

         // if update data was passed, filter through and create the SQL query, accordingly.
         if ($hasTableData) {
            $i = 1;
            $pos = strpos($this->_query, 'UPDATE');
            if ( $pos !== false) {
               foreach ($tableData as $prop => $value) {
                  // determines what data type the item is, for binding purposes.
                  $this->_paramTypeList .= $this->_determineType($value);

                  // prepares the reset of the SQL query.
                  ($i === count($tableData)) ?
                     $this->_query .= $prop . ' = ?':
                     $this->_query .= $prop . ' = ?, ';

                  $i++;
               }
            }
         }
         
         //Prepair the where portion of the query
         $this->_query .= ' WHERE ';   
         $i = 1;
         foreach ($this->_where as $column => $value) {
            // Determines what data type the where column is, for binding purposes.
            $this->_whereTypeList .= $this->_determineType($value);

            // Prepares the reset of the SQL query.
            ($i === count($this->_where)) ?
               $this->_query .= $column . ' = ?':
               $this->_query .= $column . ' = ? AND ';

            $i++;
         }
         
      }

      // Determine if is INSERT query
      if ($hasTableData) {
         $pos = strpos($this->_query, 'INSERT');

         if ($pos !== false) {
            //is insert statement
            $keys = array_keys($tableData);
            $values = array_values($tableData);
            $num = count($keys);

            // wrap values in quotes
            foreach ($values as $key => $val) {
               $values[$key] = "'{$val}'";
               $this->_paramTypeList .= $this->_determineType($val);
            }

            $this->_query .= '(' . implode($keys, ', ') . ')';
            $this->_query .= ' VALUES(';
            while ($num !== 0) {
               ($num !== 1) ? $this->_query .= '?, ' : $this->_query .= '?)';
               $num--;
            }
         }
      }

      // Did the user set a limit
      if (isset($numRows)) {
         $this->_query .= " LIMIT " . (int) $numRows;
      }

      // Prepare query
      $stmt = $this->_prepareQuery();

      // Prepare table data bind parameters
      if ($hasTableData) {
         $this->_bindParams[0] = $this->_paramTypeList;
         foreach ($tableData as $prop => $val) {
            array_push($this->_bindParams, &$tableData[$prop]);
         }
      }
      // Prepare where condition bind parameters
      if($hasConditional) {
         if ($this->_where) {
            $this->_bindParams[0] .= $this->_whereTypeList;
            foreach ($this->_where as $prop => $val) {
               array_push($this->_bindParams, &$this->_where[$prop]);
            }
         }  
      }
      // Bind parameters to statment
      if ($hasTableData || $hasConditional){
         call_user_func_array(array($stmt, 'bind_param'), $this->_bindParams);
      }

      return $stmt;
   }

   /**
    * This helper method takes care of prepared statements' "bind_result method
    * , when the number of variables to pass is unknown.
    *
    * @param object $stmt Equal to the prepared statement object.
    * @return array The results of the SQL fetch.
    */
   protected function _dynamicBindResults($stmt) 
   {
      $parameters = array();
      $results = array();

      $meta = $stmt->result_metadata();

      while ($field = $meta->fetch_field()) {
         array_push($parameters, &$row[$field->name]);
      }

      call_user_func_array(array($stmt, 'bind_result'), $parameters);

      while ($stmt->fetch()) {
         $x = array();
         foreach ($row as $key => $val) {
            $x[$key] = $val;
         }
         array_push($results, $x);
      }
      return $results;
   }


   /**
   * Method attempts to prepare the SQL query
   * and throws an error if there was a problem.
   */
   protected function _prepareQuery() 
   {
      if (!$stmt = $this->_mysqli->prepare($this->_query)) {
         trigger_error("Problem preparing query ($this->_query) ".$this->_mysqli->error, E_USER_ERROR);
      }
      return $stmt;
   }


   public function __destruct() 
   {
      $this->_mysqli->close();
   }

} // END class