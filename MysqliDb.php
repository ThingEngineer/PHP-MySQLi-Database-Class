<?php
/**
 * MysqliDb Class
 *
 * @category  Database Access
 * @package   MysqliDb
 * @author    Jeffery Way <jeffrey@jeffrey-way.com>
 * @author    Josh Campbell <jcampbell@ajillion.com>
 * @copyright Copyright (c) 2010
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   1.1
 **/
class MysqliDb
{
    /**
     * Static instance of self
     *
     * @var MysqliDb
     */
    protected static $_instance;
    /**
     * MySQLi instance
     *
     * @var mysqli
     */
    protected $_mysqli;
    /**
     * The SQL query to be prepared and executed
     *
     * @var string
     */
    protected $_query;
    /**
     * An array that holds where joins
     *
     * @var array
     */
    protected $_join = array(); 
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
     * Dynamic type list for order by condition value
     */
    protected $_orderBy = array(); 
    /**
     * Dynamic type list for group by condition value
     */
    protected $_groupBy = array(); 
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
    protected $_bindParams = array(''); // Create the empty 0 index

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $db
     * @param int $port
     */
    public function __construct($host, $username, $password, $db, $port = NULL)
    {
        if($port == NULL)
            $port = ini_get('mysqli.default_port');
        
        $this->_mysqli = new mysqli($host, $username, $password, $db, $port)
            or die('There was a problem connecting to the database');

        $this->_mysqli->set_charset('utf8');

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
        $this->_join = array();
        $this->_orderBy = array();
        $this->groupBy = array(); 
        $this->_bindParams = array(''); // Create the empty 0 index
        $this->_query = null;
        $this->_whereTypeList = null;
        $this->_paramTypeList = null;
    }

    /**
     * Pass in a raw query and an array containing the parameters to bind to the prepaird statement.
     *
     * @param string $query      Contains a user-provided query.
     * @param array  $bindParams All variables to bind to the SQL statment.
     *
     * @return array Contains the returned rows from the query.
     */
    public function rawQuery($query, $bindParams = null)
    {
        $this->_query = filter_var($query, FILTER_SANITIZE_STRING);
        $stmt = $this->_prepareQuery();

        if (is_array($bindParams) === true) {
            $params = array(''); // Create the empty 0 index
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                array_push($params, $bindParams[$prop]);
            }

            call_user_func_array(array($stmt, 'bind_param'), $this->refValues($params));

        }

        $stmt->execute();
        $this->reset();

        return $this->_dynamicBindResults($stmt);
    }

    /**
     *
     * @param string $query   Contains a user-provided select query.
     * @param int    $numRows The number of rows total to return.
     *
     * @return array Contains the returned rows from the query.
     */
    public function query($query, $numRows = null)
    {
        $this->_query = filter_var($query, FILTER_SANITIZE_STRING);
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->reset();

        return $this->_dynamicBindResults($stmt);
    }

    /**
     * A convenient SELECT * function.
     *
     * @param string  $tableName The name of the database table to work with.
     * @param integer $numRows   The number of rows total to return.
     *
     * @return array Contains the returned rows from the select query.
     */
    public function get($tableName, $numRows = null, $columns = '*')
    {
        if (empty ($columns))
            $columns = '*';

        $column = is_array($columns) ? implode(', ', $columns) : $columns; 
        $this->_query = "SELECT $column FROM $tableName";
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->reset();

        return $this->_dynamicBindResults($stmt);
    }

    /**
     * A convenient SELECT * function to get one record.
     *
     * @param string  $tableName The name of the database table to work with.
     *
     * @return array Contains the returned rows from the select query.
     */
     public function getOne($tableName, $columns = '*') 
     {
         $res = $this->get ($tableName, 1, $columns);
         return $res[0];
     }

    /**
     *
     * @param <string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     *
     * @return boolean Boolean indicating whether the insert query was completed succesfully.
     */
    public function insert($tableName, $insertData)
    {
        $this->_query = "INSERT into $tableName";
        $stmt = $this->_buildQuery(null, $insertData);
        $stmt->execute();
        $this->reset();

        return ($stmt->affected_rows > 0 ? $stmt->insert_id : false);
    }

    /**
     * Update query. Be sure to first call the "where" method.
     *
     * @param string $tableName The name of the database table to work with.
     * @param array  $tableData Array of data to update the desired row.
     *
     * @return boolean
     */
    public function update($tableName, $tableData)
    {
        $this->_query = "UPDATE $tableName SET ";

        $stmt = $this->_buildQuery(null, $tableData);
        $stmt->execute();
        $this->reset();

        return ($stmt->affected_rows > 0);
    }

    /**
     * Delete query. Call the "where" method first.
     *
     * @param string  $tableName The name of the database table to work with.
     * @param integer $numRows   The number of rows to delete.
     *
     * @return boolean Indicates success. 0 or 1.
     */
    public function delete($tableName, $numRows = null)
    {
        $this->_query = "DELETE FROM $tableName";

        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->reset();

        return ($stmt->affected_rows > 0);
    }

    /**
     * This method allows you to specify multipl (method chaining optional) WHERE statements for SQL queries.
     *
     * @uses $MySqliDb->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     *
     * @return MysqliDb
     */
    public function where($whereProp, $whereValue)
    {
        $this->_where[$whereProp] = $whereValue;
        return $this;
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * @uses $MySqliDb->join('table1', 'field1 <> field2', 'LEFT')
     *
     * @param string $joinTable The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType 'LEFT', 'INNER' etc.
     *
     * @return MysqliDb
     */
     public function join($joinTable, $joinCondition, $joinType = '')
     {
        $allowedTypes = array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER');
        $joinType = strtoupper (trim ($joinType));
        $joinTable = filter_var($joinTable, FILTER_SANITIZE_STRING);

        if ($joinType && !in_array ($joinType, $allowedTypes))
            die ('Wrong JOIN type: '.$joinType);

        $this->_join[$joinType . " JOIN " . $joinTable] = $joinCondition;

        return $this;
    }
    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * @uses $MySqliDb->orderBy('id', 'desc')->orderBy('name', 'desc');
     *
     * @param string $orderByField The name of the database field.
     * @param string $orderByDirection Order direction.
     *
     * @return MysqliDb
     */
    public function orderBy($orderByField, $orderbyDirection = "DESC")
    {
        $allowedDirection = Array ("ASC", "DESC");
        $orderbyDirection = strtoupper (trim ($orderbyDirection));
        $orderByField = filter_var($orderByField, FILTER_SANITIZE_STRING);

        if (empty($orderbyDirection) || !in_array ($orderbyDirection, $allowedDirection))
            die ('Wrong order direction: '.$orderbyDirection);

        $this->_orderBy[$orderByField] = $orderbyDirection;
        return $this;
    } 

    /**
     * This method allows you to specify multiple (method chaining optional) GROUP BY statements for SQL queries.
     *
     * @uses $MySqliDb->groupBy('name');
     *
     * @param string $groupByField The name of the database field.
     *
     * @return MysqliDb
     */
    public function groupBy($groupByField)
    {
        $groupByField = filter_var($groupByField, FILTER_SANITIZE_STRING);

        $this->_groupBy[] = $groupByField;
        return $this;
    } 

    /**
     * This methods returns the ID of the last inserted item
     *
     * @return integer The last inserted item ID.
     */
    public function getInsertId()
    {
        return $this->_mysqli->insert_id;
    }

    /**
     * Escape harmful characters which might affect a query.
     *
     * @param string $str The string to escape.
     *
     * @return string The escaped string.
     */
    public function escape($str)
    {
        return $this->_mysqli->real_escape_string($str);
    }

    /**
     * This method is needed for prepared statements. They require
     * the data type of the field to be bound with "i" s", etc.
     * This function takes the input, determines what type it is,
     * and then updates the param_type.
     *
     * @param mixed $item Input to determine the type.
     *
     * @return string The joined parameter types.
     */
    protected function _determineType($item)
    {
        switch (gettype($item)) {
            case 'NULL':
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
        return '';
    }

    /**
     * Abstraction method that will compile the WHERE statement,
     * any passed update data, and the desired rows.
     * It then builds the SQL query.
     *
     * @param int   $numRows   The number of rows total to return.
     * @param array $tableData Should contain an array of data for updating the database.
     *
     * @return mysqli_stmt Returns the $stmt object.
     */
    protected function _buildQuery($numRows = null, $tableData = null)
    {
        $hasTableData = is_array($tableData);
        $hasConditional = !empty($this->_where);

        // Did the user call the "join" method? 
        if (!empty($this->_join)) {
            foreach ($this->_join as $prop => $value) {
                $this->_query .= " " . $prop . " on " . $value;
            } 
        }

        // Did the user call the "where" method?
        if (!empty($this->_where)) {

            // if update data was passed, filter through and create the SQL query, accordingly.
            if ($hasTableData) {
                $pos = strpos($this->_query, 'UPDATE');
                if ($pos !== false) {
                    foreach ($tableData as $prop => $value) {
                        // determines what data type the item is, for binding purposes.
                        $this->_paramTypeList .= $this->_determineType($value);

                        // prepares the reset of the SQL query.
                        $this->_query .= ($prop . ' = ?, ');
                    }
                    $this->_query = rtrim($this->_query, ', ');
                }
            }

            //Prepair the where portion of the query
            $this->_query .= ' WHERE ';
            foreach ($this->_where as $column => $value) {
                $comparison = ' = ? ';
                if( is_array( $value ) ) {
                    // if the value is an array, then this isn't a basic = comparison
                    $key = key( $value );
                    $val = $value[$key];
                    switch( strtolower($key) ) {
                        case 'in':
                            $comparison = ' IN (';
                            foreach($val as $v){
                                $comparison .= ' ?,';
                                $this->_whereTypeList .= $this->_determineType( $v );
                            }
                            $comparison = rtrim($comparison, ',').' ) ';
                            break;
                        case 'between':
                            $comparison = ' BETWEEN ? AND ? ';
                            $this->_whereTypeList .= $this->_determineType( $val[0] );
                            $this->_whereTypeList .= $this->_determineType( $val[1] );
                            break;
                        default:
                            // We are using a comparison operator with only one parameter after it
                            $comparison = ' '.$key.' ? ';
                            // Determines what data type the where column is, for binding purposes.
                            $this->_whereTypeList .= $this->_determineType( $val );
                    }
                } else {
                    // Determines what data type the where column is, for binding purposes.
                    $this->_whereTypeList .= $this->_determineType($value);
                }
                // Prepares the reset of the SQL query.
                $this->_query .= ($column.$comparison.' AND ');
            }
            $this->_query = rtrim($this->_query, ' AND ');
        }

        // Did the user call the "groupBy" method?
        if (!empty($this->_groupBy)) {
            $this->_query .= " GROUP BY ";
            foreach ($this->_groupBy as $key => $value) {
                // prepares the reset of the SQL query.
                $this->_query .= $value . ", ";
            }
            $this->_query = rtrim($this->_query, ', ') . " ";
        }

        // Did the user call the "orderBy" method?
        if (!empty ($this->_orderBy)) {
            $this->_query .= " ORDER BY ";
            foreach ($this->_orderBy as $prop => $value) {
                // prepares the reset of the SQL query.
                $this->_query .= $prop . " " . $value . ", ";
            }
            $this->_query = rtrim ($this->_query, ', ') . " ";
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
                    $this->_query .= '?, ';
                    $num--;
                }
                $this->_query = rtrim($this->_query, ', ');
                $this->_query .= ')';
            }
        }

        // Did the user set a limit
        if (isset($numRows)) {
            $this->_query .= ' LIMIT ' . (int)$numRows;
        }

        // Prepare query
        $stmt = $this->_prepareQuery();

        // Prepare table data bind parameters
        if ($hasTableData) {
            $this->_bindParams[0] = $this->_paramTypeList;
            foreach ($tableData as $prop => $val) {
                array_push($this->_bindParams, $tableData[$prop]);
            }
        }
        // Prepare where condition bind parameters
        if ($hasConditional) {
            if ($this->_where) {
                $this->_bindParams[0] .= $this->_whereTypeList;
                foreach ($this->_where as $prop => $val) {
                    if (!is_array ($val)) {
                        array_push ($this->_bindParams, $this->_where[$prop]);
                        continue;
                    }
                    // if val is an array, this is not a basic = comparison operator
                    $key = key($val);
                    $vals = $val[$key];
                    if (is_array($vals)) {
                        // if vals is an array, this comparison operator takes more than one parameter
                        foreach ($vals as $k => $v) {
                            array_push($this->_bindParams, $this->_where[$prop][$key][$k]);
                        }
                    } else {
                        // otherwise this comparison operator takes only one parameter
                        array_push ($this->_bindParams, $this->_where[$prop][$key]);
                    }
                }
            }
        }
        // Bind parameters to statment
        if ($hasTableData || $hasConditional) {
            call_user_func_array(array($stmt, 'bind_param'), $this->refValues($this->_bindParams));
        }

        return $stmt;
    }

    /**
     * This helper method takes care of prepared statements' "bind_result method
     * , when the number of variables to pass is unknown.
     *
     * @param mysqli_stmt $stmt Equal to the prepared statement object.
     *
     * @return array The results of the SQL fetch.
     */
    protected function _dynamicBindResults(mysqli_stmt $stmt)
    {
        $parameters = array();
        $results = array();

        $meta = $stmt->result_metadata();

        // if $meta is false yet sqlstate is true, there's no sql error but the query is
        // most likely an update/insert/delete which doesn't produce any results
        if(!$meta && $stmt->sqlstate) { 
            return array();
        }

        $row = array();
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $parameters[] = & $row[$field->name];
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
     *
     * @return mysqli_stmt
     */
    protected function _prepareQuery()
    {
        if (!$stmt = $this->_mysqli->prepare($this->_query)) {
            trigger_error("Problem preparing query ($this->_query) " . $this->_mysqli->error, E_USER_ERROR);
        }
        return $stmt;
    }

    /**
     * Close connection
     */
    public function __destruct()
    {
        $this->_mysqli->close();
    }

    /**
     * @param array $arr
     *
     * @return array
     */
    protected function refValues($arr)
    {
        //Reference is required for PHP 5.3+
        if (strnatcmp(phpversion(), '5.3') >= 0) {
            $refs = array();
            foreach ($arr as $key => $value) {
                $refs[$key] = & $arr[$key];
            }
            return $refs;
        }
        return $arr;
    }

    /**
     * Method returns mysql error
     * 
     * @return string
     */
    public function getLastError () {
        return $this->_mysqli->error;
    }

} // END class
