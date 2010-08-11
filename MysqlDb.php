<?php

class MysqlDB {

   protected $_mysql;
   protected $_where = array();
   protected $_query;
   protected $_paramTypeList;

   public function __construct($host, $username, $password, $db) {
      $this->_mysql = new mysqli($host, $username, $password, $db) or die('There was a problem connecting to the database');
   }

   /**
    *
    * @param string $query Contains a user-provided select query.
    * @param int $numRows The number of rows total to return.
    * @return array Contains the returned rows from the query.
    */
   public function query($query) {
      $this->_query = filter_var($query, FILTER_SANITIZE_STRING);

      $stmt = $this->_prepareQuery();
      $stmt->execute();
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
   public function get($tableName, $numRows = NULL) {

      $this->_query = "SELECT * FROM $tableName";
      $stmt = $this->_buildQuery($numRows);
      $stmt->execute();

      $results = $this->_dynamicBindResults($stmt);
      return $results;
   }

   /**
    *
    * @param <string $tableName The name of the table.
    * @param array $insertData Data containing information for inserting into the DB.
    * @return boolean Boolean indicating whether the insert query was completed succesfully.
    */
   public function insert($tableName, $insertData) {
      $this->_query = "INSERT into $tableName";
      $stmt = $this->_buildQuery(NULL, $insertData);
      $stmt->execute();

      if ($stmt->affected_rows)
         return true;
   }

   /**
    * Update query. Be sure to first call the "where" method.
    *
    * @param string $tableName The name of the database table to work with.
    * @param array $tableData Array of data to update the desired row.
    * @return boolean
    */
   public function update($tableName, $tableData) {
      $this->_query = "UPDATE $tableName SET ";

      $stmt = $this->_buildQuery(NULL, $tableData);
      $stmt->execute();

      if ($stmt->affected_rows)
         return true;
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

      if ($stmt->affected_rows)
         return true;
   }

   /**
    * This method allows you to specify a WHERE statement for SQL queries.
    *
    * @param string $whereProp A string for the name of the database field to update
    * @param mixed $whereValue The value for the field.
    */
   public function where($whereProp, $whereValue) {
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
   protected function _determineType($item) {
      switch (gettype($item)) {
         case 'string':
            $param_type = 's';
            break;

         case 'integer':
            $param_type = 'i';
            break;

         case 'blob':
            $param_type = 'b';
            break;

         case 'double':
            $param_type = 'd';
            break;
      }
      return $param_type;
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
   protected function _buildQuery($numRows = NULL, $tableData = false) {
      $hasTableData = null;
      if (gettype($tableData) === 'array') {
         $hasTableData = true;
      }

      // Did the user call the "where" method?
      if (!empty($this->_where)) {
         $keys = array_keys($this->_where);
         $where_prop = $keys[0];
         $where_value = $this->_where[$where_prop];

         // if update data was passed, filter through
         // and create the SQL query, accordingly.
         if ($hasTableData) {
            $i = 1;
            foreach ($tableData as $prop => $value) {
               // determines what data type the item is, for binding purposes.
               $this->_paramTypeList .= $this->_determineType($value);

               // prepares the reset of the SQL query.
               if ($i === count($tableData)) {
                  $this->_query .= $prop . " = ? WHERE " . $where_prop . "= " . $where_value;
               } else {
                  $this->_query .= $prop . ' = ?, ';
               }

               $i++;
            }
         } else {
            // no table data was passed. Might be SELECT statement.
            $this->_paramTypeList = $this->_determineType($where_value);
            $this->_query .= " WHERE " . $where_prop . "= ?";
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

      // Bind parameters
      if ($hasTableData) {
         $args = array();
         $args[] = $this->_paramTypeList;
         foreach ($tableData as $prop => $val) {
            $args[] = &$tableData[$prop];
         }
         call_user_func_array(array($stmt, 'bind_param'), $args);
      } else {
         if ($this->_where)
            $stmt->bind_param($this->_paramTypeList, $where_value);
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
   protected function _dynamicBindResults($stmt) {
      $parameters = array();
      $results = array();

      $meta = $stmt->result_metadata();

      while ($field = $meta->fetch_field()) {
         $parameters[] = &$row[$field->name];
      }

      call_user_func_array(array($stmt, 'bind_result'), $parameters);

      while ($stmt->fetch()) {
         $x = array();
         foreach ($row as $key => $val) {
            $x[$key] = $val;
         }
         $results[] = $x;
      }
      return $results;
   }

   protected function _prepareQuery() {
      if (!$stmt = $this->_mysql->prepare($this->_query)) {
         trigger_error("Connection issue", E_USER_ERROR);
      }
      return $stmt;
   }

}