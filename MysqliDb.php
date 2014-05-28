<?php
/**
 * MysqliDb Class
 *
 * @category  Database Access
 * @package   MysqliDb
 * @author    Jeffery Way <jeffrey@jeffrey-way.com>
 * @author    Josh Campbell <jcampbell@ajillion.com>
 * @author    Alexander V. Butenko <a.butenka@gmail.com>
 * @copyright Copyright (c) 2010
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   2.0
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
     * Table prefix
     * 
     * @var string
     */
    protected static $_prefix;
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
     * The previously executed SQL query
     *
     * @var string
     */
    protected $_lastQuery;
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
     * Dynamic type list for order by condition value
     */
    protected $_orderBy = array(); 
    /**
     * Dynamic type list for group by condition value
     */
    protected $_groupBy = array(); 
    /**
     * Dynamic array that holds a combination of where condition/table data value types and parameter referances
     *
     * @var array
     */
    protected $_bindParams = array(''); // Create the empty 0 index
    /**
     * Variable which holds an amount of returned rows during get/getOne/select queries
     *
     * @var string
     */ 
    public $count = 0;
    /**
     * Variable which holds last statement error
     *
     * @var string
     */
    protected $_stmtError;

    /**
     * Database credentials
     *
     * @var string
     */
    protected $host;
    protected $username;
    protected $password;
    protected $db;
    protected $port;

    /**
     * Is Subquery object
     *
     */
    protected $isSubQuery = false;

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $db
     * @param int $port
     */
    public function __construct($host = NULL, $username = NULL, $password = NULL, $db = NULL, $port = NULL)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
        if($port == NULL)
            $this->port = ini_get ('mysqli.default_port');
        else
            $this->port = $port;

        if ($host == null && $username == null && $db == null) {
            $this->isSubQuery = true;
            return;
        }

        // for subqueries we do not need database connection and redefine root instance
        $this->connect();
        $this->setPrefix();
        self::$_instance = $this;
    }

    /**
     * A method to connect to the database
     *
     */
    public function connect()
    {
        if ($this->isSubQuery)
            return;

        $this->_mysqli = new mysqli ($this->host, $this->username, $this->password, $this->db, $this->port)
            or die('There was a problem connecting to the database');

        $this->_mysqli->set_charset ('utf8');
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
        $this->_groupBy = array(); 
        $this->_bindParams = array(''); // Create the empty 0 index
        $this->_query = null;
        $this->count = 0;
    }
    
    /**
     * Method to set a prefix
     * 
     * @param string $prefix     Contains a tableprefix
     */
    public function setPrefix($prefix = '')
    {
        self::$_prefix = $prefix;
        return $this;
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
        $this->_query = filter_var ($query, FILTER_SANITIZE_MAGIC_QUOTES,
                                    FILTER_FLAG_NO_ENCODE_QUOTES);
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
        $this->_stmtError = $stmt->error;
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
        $this->_stmtError = $stmt->error;
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
        $this->_query = "SELECT $column FROM " .self::$_prefix . $tableName;
        $stmt = $this->_buildQuery($numRows);

        if ($this->isSubQuery)
            return $this;

        $stmt->execute();
        $this->_stmtError = $stmt->error;
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

        if (is_object($res))
            return $res;

        if (isset($res[0]))
            return $res[0];

        return null;
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
        if ($this->isSubQuery)
            return;

        $this->_query = "INSERT into " .self::$_prefix . $tableName;
        $stmt = $this->_buildQuery(null, $insertData);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
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
        if ($this->isSubQuery)
            return;

        $this->_query = "UPDATE " . self::$_prefix . $tableName ." SET ";

        $stmt = $this->_buildQuery(null, $tableData);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
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
        if ($this->isSubQuery)
            return;

        $this->_query = "DELETE FROM " . self::$_prefix . $tableName;

        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->reset();

        return ($stmt->affected_rows > 0);
    }

    /**
     * This method allows you to specify multiple (method chaining optional) AND WHERE statements for SQL queries.
     *
     * @uses $MySqliDb->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     *
     * @return MysqliDb
     */
    public function where($whereProp, $whereValue = null, $operator = null)
    {
        if ($operator)
            $whereValue = Array ($operator => $whereValue);

        $this->_where[$whereProp] = Array ("AND", $whereValue);
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR WHERE statements for SQL queries.
     *
     * @uses $MySqliDb->orWhere('id', 7)->orWhere('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     *
     * @return MysqliDb
     */
    public function orWhere($whereProp, $whereValue = null, $operator = null)
    {
        if ($operator)
            $whereValue = Array ($operator => $whereValue);

        $this->_where[$whereProp] = Array ("OR", $whereValue);
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

        $this->_join[$joinType . " JOIN " . self::$_prefix . $joinTable] = $joinCondition;

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
        $orderByField = preg_replace ("/[^-a-z0-9\.\(\),_]+/i",'', $orderByField);

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
        $groupByField = preg_replace ("/[^-a-z0-9\.\(\),_]+/i",'', $groupByField);

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
     * Method to call mysqli->ping() to keep unused connections open on
     * long-running scripts, or to reconnect timed out connections (if php.ini has
     * global mysqli.reconnect set to true). Can't do this directly using object
     * since _mysqli is protected.
     *
     * @return bool True if connection is up
     */
    public function ping() {
        return $this->_mysqli->ping();
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

            case 'boolean':
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
     * Helper function to add variables into bind parameters array
     *
     * @param string Variable value
     */
    protected function _bindParam($value)
    {
        $this->_bindParams[0] .= $this->_determineType ($value);
        array_push ($this->_bindParams, $value);
    }

    protected function _buildPair ($operator, $value) {
        if (!is_object($value)) {
            $this->_bindParam ($value);
            $comparison = ' ' . $operator. ' ? ';
            return $comparison;
        }

        $subQuery = $value->getSubQuery();
        $comparison = " " . $operator . " (" . $subQuery['query'] . ")";
        foreach ($subQuery['params'] as $v)
            $this->_bindParam ($v);

        return $comparison;
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

        // Determine INSERT or UPDATE query
        if ($hasTableData) {
            $isInsert = strpos ($this->_query, 'INSERT');
            $isUpdate = strpos ($this->_query, 'UPDATE');

            if ($isInsert !== false) {
                //is insert statement
                $this->_query .= '(`' . implode(array_keys($tableData), '`, `') . '`)';
                $this->_query .= ' VALUES(';
            }

            foreach ($tableData as $column => $value) {
                if ($isUpdate !== false)
                    $this->_query .= "`" . $column . "` = ";

                if (is_object ($value)) {
                    $this->_query .= $this->_buildPair ("", $value) . ", ";
                } else if (!is_array ($value)) {
                    $this->_bindParam ($value);
                    $this->_query .= '?, ';
                } else {
                    $key = key ($value);
                    $val = $value[$key];
                    switch ($key) {
                        case '[I]':
                            $this->_query .= $column . $val . ", ";
                            break;
                        case '[F]':
                            $this->_query .= $val[0] . ", ";
                            if (!empty ($val[1])) {
                                foreach ($val[1] as $v)
                                    $this->_bindParam ($v);
                            }
                            break;
                        case '[N]':
                            if ($val == null)
                                $this->_query .= "!" . $column . ", ";
                            else
                                $this->_query .= "!" . $val . ", ";
                            break;
                        default:
                            die ("Wrong operation");
                    }
                }
            }
            $this->_query = rtrim($this->_query, ', ');
            if ($isInsert !== false)
                $this->_query .= ')';
        }

        // Did the user call the "where" method?
        if ($hasConditional) {
            //Prepair the where portion of the query
            $this->_query .= ' WHERE ';
            foreach ($this->_where as $column => $value) {
                //value[0] -- AND/OR, value[1] -- condition array
                // if its not a first condition insert its concatenator (AND or OR)
                if (array_search ($column, array_keys ($this->_where)) != 0)
                    $this->_query .= ' ' . $value[0]. ' ';

                if (is_array ($value[1])) {
                    //value[0] -- AND/OR, value[1] -- condition array
                    // if the value is an array, then this isn't a basic = comparison
                    $key = key($value[1]);
                    $val = $value[1][$key];
                    switch( strtolower($key) ) {
                        case '0':
                            $comparison = '';
                            foreach ($value[1] as $v)
                                $this->_bindParam ($v);
                            break;
                        case 'not in':
                        case 'in':
                            $comparison = ' ' . $key . ' (';
                            if (is_object ($val)) {
                                $comparison .= $this->_buildPair ("", $val);
                            } else {
                                foreach ($val as $v) {
                                    $comparison .= ' ?,';
                                    $this->_bindParam ($v);
                                }
                            }
                            $comparison = rtrim($comparison, ',').' ) ';
                            break;
                        case 'not between':
                        case 'between':
                            $comparison = ' ' . $key . ' ? AND ? ';
                            $this->_bindParam ($val[0]);
                            $this->_bindParam ($val[1]);
                            break;
                        default:
                            // We are using a comparison operator with only one parameter after it
                            $comparison = $this->_buildPair ($key, $val);
                    }
                } else if ($value[1] === null) {
                    $comparison = '';
                } else {
                    $comparison = $this->_buildPair ("=", $value[1]);
                }
                $this->_query .= $column.$comparison;
            }
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

        // Did the user set a limit
        if (isset($numRows)) {
            if (is_array ($numRows))
                $this->_query .= ' LIMIT ' . (int)$numRows[0] . ', ' . (int)$numRows[1];
            else
                $this->_query .= ' LIMIT ' . (int)$numRows;
        }

        $this->_lastQuery = $this->replacePlaceHolders($this->_query, $this->_bindParams);

        if ($this->isSubQuery)
            return;

        // Prepare query
        $stmt = $this->_prepareQuery();

        // Bind parameters to statement if any
        if (count ($this->_bindParams) > 1)
            call_user_func_array(array($stmt, 'bind_param'), $this->refValues($this->_bindParams));

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
            $this->count++;
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
        if (!$this->isSubQuery)
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
     * Function to replace ? with variables from bind variable
     * @param string $str
     * @param Array $vals
     *
     * @return string
     */
    protected function replacePlaceHolders ($str, $vals) {
        $i = 1;
        $newStr = "";

        while ($pos = strpos ($str, "?")) {
            $val = $vals[$i++];
            if (is_object ($val))
                $val = '[object]';
            $newStr .= substr ($str, 0, $pos) . $val;
            $str = substr ($str, $pos + 1);
        }
        return $newStr;
    }

    /**
     * Method returns last executed query
     *
     * @return string
     */
    public function getLastQuery () {
        return $this->_lastQuery;
    }

    /**
     * Method returns mysql error
     * 
     * @return string
     */
    public function getLastError () {
        return $this->_stmtError . " " . $this->_mysqli->error;
    }

    public function getSubQuery () {
        if (!$this->isSubQuery)
            return null;

        array_shift ($this->_bindParams);
        $val = Array ('query' => $this->_query,
                      'params' => $this->_bindParams
                );
        $this->reset();
        return $val;
    }

    /* Helper functions */
    /**
     * Method returns generated interval function as a string
     *
     * @param string interval in the formats:
     *        "1", "-1d" or "- 1 day" -- For interval - 1 day
     *        Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
     *        Default null;
     * @param string Initial date
     *
     * @return string
    */
    public function interval ($diff, $func = "NOW()") {
        $types = Array ("s" => "second", "m" => "minute", "h" => "hour", "d" => "day", "M" => "month", "Y" => "year");
        $incr = '+';
        $items = '';
        $type = 'd';

        if ($diff && preg_match('/([+-]?) ?([0-9]+) ?([a-zA-Z]?)/',$diff, $matches)) {
            if (!empty ($matches[1])) $incr = $matches[1];
            if (!empty ($matches[2])) $items = $matches[2];
            if (!empty ($matches[3])) $type = $matches[3];
            if (!in_array($type, array_keys($types)))
                trigger_error ("invalid interval type in '{$diff}'");
            $func .= " ".$incr ." interval ". $items ." ".$types[$type] . " ";
        }
        return $func;

    }
    /**
     * Method returns generated interval function as an insert/update function
     *
     * @param string interval in the formats:
     *        "1", "-1d" or "- 1 day" -- For interval - 1 day
     *        Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
     *        Default null;
     * @param string Initial date
     *
     * @return array
    */
    public function now ($diff = null, $func = "NOW()") {
        return Array ("[F]" => Array($this->interval($diff, $func)));
    }

    /**
     * Method generates incremental function call
     * @param int increment amount. 1 by default
     */
    public function inc($num = 1) {
        return Array ("[I]" => "+" . (int)$num);
    }

    /**
     * Method generates decrimental function call
     * @param int increment amount. 1 by default
     */
    public function dec ($num = 1) {
        return Array ("[I]" => "-" . (int)$num);
    }
    
    /**
     * Method generates change boolean function call
     * @param string column name. null by default
     */
    public function not ($col = null) {
        return Array ("[N]" => (string)$col);
    }

    /**
     * Method generates user defined function call
     * @param string user function body
     */
    public function func ($expr, $bindParams = null) {
        return Array ("[F]" => Array($expr, $bindParams));
    }

    /**
     * Method creates new mysqlidb object for a subquery generation
     */
    public static function subQuery()
    {
        return new MysqliDb();
    }

} // END class
