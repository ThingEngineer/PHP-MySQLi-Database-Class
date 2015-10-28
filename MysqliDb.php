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
 * @link      http://github.com/joshcam/PHP-MySQLi-Database-Class 
 * @version   2.4
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
    public static $prefix = '';
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
     * The SQL query options required after SELECT, INSERT, UPDATE or DELETE
     *
     * @var string
     */
    protected $_queryOptions = array();
    /**
     * An array that holds where joins
     *
     * @var array
     */
    protected $_join = array();
    /**
     * An array that holds where conditions
     *
     * @var array
     */
    protected $_where = array();
    /**
     * An array that holds having conditions
     *
     * @var array
     */
    protected $_having = array();
    /**
     * Dynamic type list for order by condition value
     */
    protected $_orderBy = array();
    /**
     * Dynamic type list for group by condition value
     */
    protected $_groupBy = array();
    /**
     * Dynamic array that holds a combination of where condition/table data value types and parameter references
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
     * Variable which holds an amount of returned rows during get/getOne/select queries with withTotalCount()
     *
     * @var string
     */
    public $totalCount = 0;
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
    protected $charset;

    /**
     * Is Subquery object
     *
     */
    protected $isSubQuery = false;

    /**
     * Name of the auto increment column
     *
     */
    protected $_lastInsertId = null;

    /**
     * Column names for update when using onDuplicate method
     *
     */
    protected $_updateColumns = null;

    /**
     * Return type: 'Array' to return results as array, 'Object' as object
     * 'Json' as json string
     *
     * @var string
     */
    public $returnType = 'Array';

    /**
     * Should join() results be nested by table
     * @var boolean
     */
    protected $_nestJoin = false;
    private $_tableName = '';

    /**
     * FOR UPDATE flag
     * @var boolean
     */
    protected $_forUpdate = false;

    /**
     * LOCK IN SHARE MODE flag
     * @var boolean
     */
    protected $_lockInShareMode = false;

    /**
     * Key field for Map()'ed result array
     * @var string
     */
    protected $_mapKey = null;

    /**
     * Variables for query execution tracing
     *
     */
    protected $traceStartQ;
    protected $traceEnabled;
    protected $traceStripPrefix;
    public $trace = array();

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $db
     * @param int $port
     */
    public function __construct($host = NULL, $username = NULL, $password = NULL, $db = NULL, $port = NULL, $charset = 'utf8')
    {
        $isSubQuery = false;

        // if params were passed as array
        if (is_array ($host)) {
            foreach ($host as $key => $val)
                $$key = $val;
        }
        // if host were set as mysqli socket
        if (is_object ($host))
            $this->_mysqli = $host;
        else
            $this->host = $host;

        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
        $this->port = $port;
        $this->charset = $charset;

        if ($isSubQuery) {
            $this->isSubQuery = true;
            return;
        }
        if (isset ($prefix))
            $this->setPrefix ($prefix);
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

        if (empty ($this->host))
            die ('Mysql host is not set');

        $this->_mysqli = new mysqli ($this->host, $this->username, $this->password, $this->db, $this->port);
        if ($this->_mysqli->connect_error)
            throw new Exception ('Connect Error ' . $this->_mysqli->connect_errno . ': ' . $this->_mysqli->connect_error);

        if ($this->charset)
            $this->_mysqli->set_charset ($this->charset);
    }

    /**
     * A method to get mysqli object or create it in case needed
     */
    public function mysqli ()
    {
        if (!$this->_mysqli)
            $this->connect();
        return $this->_mysqli;
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
        if ($this->traceEnabled)
            $this->trace[] = array ($this->_lastQuery, (microtime(true) - $this->traceStartQ) , $this->_traceGetCaller());

        $this->_where = array();
        $this->_having = array();
        $this->_join = array();
        $this->_orderBy = array();
        $this->_groupBy = array();
        $this->_bindParams = array(''); // Create the empty 0 index
        $this->_query = null;
        $this->_queryOptions = array();
        $this->returnType = 'Array';
        $this->_nestJoin = false;
        $this->_forUpdate = false;
        $this->_lockInShareMode = false;
        $this->_tableName = '';
        $this->_lastInsertId = null;
        $this->_updateColumns = null;
        $this->_mapKey = null;
    }

    /**
     * Helper function to create dbObject with Json return type
     *
     * @return dbObject
     */
    public function JsonBuilder () {
        $this->returnType = 'Json';
        return $this;
    }

    /**
     * Helper function to create dbObject with Array return type
     * Added for consistency as thats default output type
     *
     * @return dbObject
     */
    public function ArrayBuilder () {
        $this->returnType = 'Array';
        return $this;
    }

    /**
     * Helper function to create dbObject with Object return type.
     *
     * @return dbObject
     */
    public function ObjectBuilder () {
        $this->returnType = 'Object';
        return $this;
    }

    /**
     * Method to set a prefix
     *
     * @param string $prefix     Contains a tableprefix
     */
    public function setPrefix($prefix = '')
    {
        self::$prefix = $prefix;
        return $this;
    }

    /**
     * Execute raw SQL query.
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     *
     * @return array Contains the returned rows from the query.
     */
    public function rawQuery ($query, $bindParams = null)
    {
        $params = array(''); // Create the empty 0 index
        $this->_query = $query;
        $stmt = $this->_prepareQuery();

        if (is_array ($bindParams) === true) {
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                array_push($params, $bindParams[$prop]);
            }

            call_user_func_array(array($stmt, 'bind_param'), $this->refValues($params));

        }

        $stmt->execute();
        $this->count = $stmt->affected_rows;
        $this->_stmtError = $stmt->error;
        $this->_lastQuery = $this->replacePlaceHolders ($this->_query, $params);
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();

        return $res;
    }

    /**
     * Helper function to execute raw SQL query and return only 1 row of results.
     * Note that function do not add 'limit 1' to the query by itself
     * Same idea as getOne()
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     *
     * @return array Contains the returned row from the query.
     */
    public function rawQueryOne ($query, $bindParams = null) {
        $res = $this->rawQuery ($query, $bindParams);
        if (is_array ($res) && isset ($res[0]))
            return $res[0];

        return null;
    }

    /**
     * Helper function to execute raw SQL query and return only 1 column of results.
     * If 'limit 1' will be found, then string will be returned instead of array
     * Same idea as getValue()
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     *
     * @return mixed Contains the returned rows from the query.
     */
    public function rawQueryValue ($query, $bindParams = null) {
        $res = $this->rawQuery ($query, $bindParams);
        if (!$res)
            return null;

        $limit = preg_match ('/limit\s+1;?$/i', $query);
        $key = key ($res[0]);
        if (isset($res[0][$key]) && $limit == true)
            return $res[0][$key];

        $newRes = Array ();
        for ($i = 0; $i < $this->count; $i++)
            $newRes[] = $res[$i][$key];
        return $newRes;
    }
    /**
     *
     * @param string $query   Contains a user-provided select query.
     * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
     *
     * @return array Contains the returned rows from the query.
     */
    public function query($query, $numRows = null)
    {
        $this->_query = $query;
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();

        return $res;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) options for SQL queries.
     *
     * @uses $MySqliDb->setQueryOption('name');
     *
     * @param string/array $options The optons name of the query.
     *
     * @return MysqliDb
     */
    public function setQueryOption ($options) {
        $allowedOptions = Array ('ALL','DISTINCT','DISTINCTROW','HIGH_PRIORITY','STRAIGHT_JOIN','SQL_SMALL_RESULT',
                          'SQL_BIG_RESULT','SQL_BUFFER_RESULT','SQL_CACHE','SQL_NO_CACHE', 'SQL_CALC_FOUND_ROWS',
                          'LOW_PRIORITY','IGNORE','QUICK', 'MYSQLI_NESTJOIN', 'FOR UPDATE', 'LOCK IN SHARE MODE');
        if (!is_array ($options))
            $options = Array ($options);

        foreach ($options as $option) {
            $option = strtoupper ($option);
            if (!in_array ($option, $allowedOptions))
                die ('Wrong query option: '.$option);

            if ($option == 'MYSQLI_NESTJOIN')
                $this->_nestJoin = true;
            else if ($option == 'FOR UPDATE')
                $this->_forUpdate = true;
            else if ($option == 'LOCK IN SHARE MODE')
                $this->_lockInShareMode = true;
            else
                $this->_queryOptions[] = $option;
        }

        return $this;
    }

    /**
     * Function to enable SQL_CALC_FOUND_ROWS in the get queries
     *
     * @return MysqliDb
     */
    public function withTotalCount () {
        $this->setQueryOption ('SQL_CALC_FOUND_ROWS');
        return $this;
    }

    /**
     * A convenient SELECT * function.
     *
     * @param string  $tableName The name of the database table to work with.
     * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
     *                               or only $count
     *
     * @return array Contains the returned rows from the select query.
     */
    public function get($tableName, $numRows = null, $columns = '*')
    {
        if (empty ($columns))
            $columns = '*';

        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        if (strpos ($tableName, '.') === false)
            $this->_tableName = self::$prefix . $tableName;
        else
            $this->_tableName = $tableName;

        $this->_query = 'SELECT ' . implode(' ', $this->_queryOptions) . ' ' .
                        $column . " FROM " . $this->_tableName;
        $stmt = $this->_buildQuery($numRows);

        if ($this->isSubQuery)
            return $this;

        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();

        return $res;
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

        if ($res instanceof MysqliDb)
            return $res;
        else if (is_array ($res) && isset ($res[0]))
            return $res[0];
        else if ($res)
            return $res;

        return null;
    }

    /**
     * A convenient SELECT COLUMN function to get a single column value from one row
     *
     * @param string  $tableName The name of the database table to work with.
     * @param int     $limit     Limit of rows to select. Use null for unlimited..1 by default
     *
     * @return mixed Contains the value of a returned column / array of values
     */
    public function getValue ($tableName, $column, $limit = 1)
    {
        $res = $this->ArrayBuilder()->get ($tableName, $limit, "{$column} AS retval");

        if (!$res)
            return null;

        if ($limit == 1) {
            if (isset ($res[0]["retval"]))
                return $res[0]["retval"];
            return null;
        }

        $newRes = Array ();
        for ($i = 0; $i < $this->count; $i++)
            $newRes[] = $res[$i]['retval'];
        return $newRes;
    }

    /**
     * Insert method to add new row
     *
     * @param <string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     *
     * @return boolean Boolean indicating whether the insert query was completed succesfully.
     */
    public function insert ($tableName, $insertData) {
        return $this->_buildInsert ($tableName, $insertData, 'INSERT');
    }

    /**
     * Replace method to add new row
     *
     * @param <string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     *
     * @return boolean Boolean indicating whether the insert query was completed succesfully.
     */
    public function replace ($tableName, $insertData) {
        return $this->_buildInsert ($tableName, $insertData, 'REPLACE');
    }

    /**
     * A convenient function that returns TRUE if exists at least an element that
     * satisfy the where condition specified calling the "where" method before this one.
     *
     * @param string  $tableName The name of the database table to work with.
     *
     * @return array Contains the returned rows from the select query.
     */
    public function has($tableName)
    {
        $this->getOne($tableName, '1');
        return $this->count >= 1;
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

        $this->_query = "UPDATE " . self::$prefix . $tableName;

        $stmt = $this->_buildQuery (null, $tableData);
        $status = $stmt->execute();
        $this->reset();
        $this->_stmtError = $stmt->error;
        $this->count = $stmt->affected_rows;

        return $status;
    }

    /**
     * Delete query. Call the "where" method first.
     *
     * @param string  $tableName The name of the database table to work with.
     * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
     *                               or only $count
     *
     * @return boolean Indicates success. 0 or 1.
     */
    public function delete($tableName, $numRows = null)
    {
        if ($this->isSubQuery)
            return;

        $table = self::$prefix . $tableName;
        if (count ($this->_join))
            $this->_query = "DELETE " . preg_replace ('/.* (.*)/', '$1', $table) . " FROM " . $table;
        else
            $this->_query = "DELETE FROM " . $table;

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
    public function where($whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        // forkaround for an old operation api
        if (is_array ($whereValue) && ($key = key ($whereValue)) != "0") {
            $operator = $key;
            $whereValue = $whereValue[$key];
        }
        if (count ($this->_where) == 0)
            $cond = '';
        $this->_where[] = Array ($cond, $whereProp, $operator, $whereValue);
        return $this;
    }

    /**
     * This function store update column's name and column name of the
     * autoincrement column
     *
     * @param Array Variable with values
     * @param String Variable value
     */
    public function onDuplicate($_updateColumns, $_lastInsertId = null)
    {
        $this->_lastInsertId = $_lastInsertId;
        $this->_updateColumns = $_updateColumns;
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
    public function orWhere($whereProp, $whereValue = 'DBNULL', $operator = '=')
    {
        return $this->where ($whereProp, $whereValue, $operator, 'OR');
    }

    /*
     * This method allows you to specify multiple (method chaining optional) AND HAVING statements for SQL queries.
     *
     * @uses $MySqliDb->having('SUM(tags) > 10')
     *
     * @param string $havingProp  The name of the database field.
     * @param mixed  $havingValue The value of the database field.
     *
     * @return MysqliDb
     */
    public function having($havingProp, $havingValue = null, $operator = null)
    {
        if ($operator)
            $havingValue = Array ($operator => $havingValue);

        $this->_having[] = Array ("AND", $havingValue, $havingProp);
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR HAVING statements for SQL queries.
     *
     * @uses $MySqliDb->orHaving('SUM(tags) > 10')
     *
     * @param string $havingProp  The name of the database field.
     * @param mixed  $havingValue The value of the database field.
     *
     * @return MysqliDb
     */
    public function orHaving($havingProp, $havingValue = null, $operator = null)
    {
        if ($operator)
            $havingValue = Array ($operator => $havingValue);

        $this->_having[] = Array ("OR", $havingValue, $havingProp);
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

        if ($joinType && !in_array ($joinType, $allowedTypes))
            die ('Wrong JOIN type: '.$joinType);

        if (!is_object ($joinTable))
            $joinTable = self::$prefix . $joinTable;

        $this->_join[] = Array ($joinType,  $joinTable, $joinCondition);

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
    public function orderBy($orderByField, $orderbyDirection = "DESC", $customFields = null)
    {
        $allowedDirection = Array ("ASC", "DESC");
        $orderbyDirection = strtoupper (trim ($orderbyDirection));
        $orderByField = preg_replace ("/[^-a-z0-9\.\(\),_`\*\'\"]+/i",'', $orderByField);

        // Add table prefix to orderByField if needed.
        //FIXME: We are adding prefix only if table is enclosed into `` to distinguish aliases
        // from table names
        $orderByField = preg_replace('/(\`)([`a-zA-Z0-9_]*\.)/', '\1' . self::$prefix.  '\2', $orderByField);


        if (empty($orderbyDirection) || !in_array ($orderbyDirection, $allowedDirection))
            die ('Wrong order direction: '.$orderbyDirection);

        if (is_array ($customFields)) {
            foreach ($customFields as $key => $value)
                $customFields[$key] = preg_replace ("/[^-a-z0-9\.\(\),_`]+/i",'', $value);

            $orderByField = 'FIELD (' . $orderByField . ', "' . implode('","', $customFields) . '")';
        }

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
        $groupByField = preg_replace ("/[^-a-z0-9\.\(\),_\*]+/i",'', $groupByField);

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
        return $this->mysqli()->insert_id;
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
        return $this->mysqli()->real_escape_string($str);
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
        return $this->mysqli()->ping();
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
    protected function _bindParam($value) {
        $this->_bindParams[0] .= $this->_determineType ($value);
        array_push ($this->_bindParams, $value);
    }

    /**
     * Helper function to add variables into bind parameters array in bulk
     *
     * @param Array Variable with values
     */
    protected function _bindParams ($values) {
        foreach ($values as $value)
            $this->_bindParam ($value);
    }

    /**
     * Helper function to add variables into bind parameters array and will return
     * its SQL part of the query according to operator in ' $operator ?' or
     * ' $operator ($subquery) ' formats
     *
     * @param Array Variable with values
     */
    protected function _buildPair ($operator, $value) {
        if (!is_object($value)) {
            $this->_bindParam ($value);
            return ' ' . $operator. ' ? ';
        }

        $subQuery = $value->getSubQuery ();
        $this->_bindParams ($subQuery['params']);

        return " " . $operator . " (" . $subQuery['query'] . ") " . $subQuery['alias'];
    }

    /**
     * Internal function to build and execute INSERT/REPLACE calls
     *
     * @param <string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     *
     * @return boolean Boolean indicating whether the insert query was completed succesfully.
     */
    private function _buildInsert ($tableName, $insertData, $operation)
    {
        if ($this->isSubQuery)
            return;

        $this->_query = $operation . " " . implode (' ', $this->_queryOptions) ." INTO " .self::$prefix . $tableName;
        $stmt = $this->_buildQuery (null, $insertData);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->reset();
        $this->count = $stmt->affected_rows;

        if ($stmt->affected_rows < 1)
            return false;

        if ($stmt->insert_id > 0)
            return $stmt->insert_id;

        return true;
    }

    /**
     * Abstraction method that will compile the WHERE statement,
     * any passed update data, and the desired rows.
     * It then builds the SQL query.
     *
     * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
     *                               or only $count
     * @param array $tableData Should contain an array of data for updating the database.
     *
     * @return mysqli_stmt Returns the $stmt object.
     */
    protected function _buildQuery($numRows = null, $tableData = null)
    {
        $this->_buildJoin();
        $this->_buildInsertQuery ($tableData);
        $this->_buildCondition('WHERE', $this->_where);
        $this->_buildGroupBy();
        $this->_buildCondition('HAVING', $this->_having);
        $this->_buildOrderBy();
        $this->_buildLimit ($numRows);
        $this->_buildOnDuplicate($tableData);
        if ($this->_forUpdate)
            $this->_query .= ' FOR UPDATE';
        if ($this->_lockInShareMode)
            $this->_query .= ' LOCK IN SHARE MODE';

        $this->_lastQuery = $this->replacePlaceHolders ($this->_query, $this->_bindParams);

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
        // See http://php.net/manual/en/mysqli-result.fetch-fields.php
        $mysqlLongType = 252;
        $shouldStoreResult = false;

        $meta = $stmt->result_metadata();

        // if $meta is false yet sqlstate is true, there's no sql error but the query is
        // most likely an update/insert/delete which doesn't produce any results
        if(!$meta && $stmt->sqlstate)
            return array();

        $row = array();
        while ($field = $meta->fetch_field()) {
            if ($field->type == $mysqlLongType)
                $shouldStoreResult = true;

            if ($this->_nestJoin && $field->table != $this->_tableName) {
                $field->table = substr ($field->table, strlen (self::$prefix));
                $row[$field->table][$field->name] = null;
                $parameters[] = & $row[$field->table][$field->name];
            } else {
                $row[$field->name] = null;
                $parameters[] = & $row[$field->name];
            }
        }

        // avoid out of memory bug in php 5.2 and 5.3. Mysqli allocates lot of memory for long*
        // and blob* types. So to avoid out of memory issues store_result is used
        // https://github.com/joshcam/PHP-MySQLi-Database-Class/pull/119
        if ($shouldStoreResult)
             $stmt->store_result();

        call_user_func_array(array($stmt, 'bind_result'), $parameters);

        $this->totalCount = 0;
        $this->count = 0;
        while ($stmt->fetch()) {
            if ($this->returnType == 'Object') {
                $x = new stdClass ();
                foreach ($row as $key => $val) {
                    if (is_array ($val)) {
                        $x->$key = new stdClass ();
                        foreach ($val as $k => $v)
                            $x->$key->$k = $v;
                    } else
                        $x->$key = $val;
                }
            } else {
                $x = array();
                foreach ($row as $key => $val) {
                    if (is_array($val)) {
                        foreach ($val as $k => $v)
                            $x[$key][$k] = $v;
                    } else
                        $x[$key] = $val;
                }
            }
            $this->count++;
            if ($this->_mapKey)
                $results[$row[$this->_mapKey]] = count ($row) > 2 ? $x : end ($x);
            else
                array_push ($results, $x);
        }
        if ($shouldStoreResult)
            $stmt->free_result();
        $stmt->close();
        // stored procedures sometimes can return more then 1 resultset
        if ($this->mysqli()->more_results())
            $this->mysqli()->next_result();

        if (in_array ('SQL_CALC_FOUND_ROWS', $this->_queryOptions)) {
            $stmt = $this->mysqli()->query ('SELECT FOUND_ROWS()');
            $totalCount = $stmt->fetch_row();
            $this->totalCount = $totalCount[0];
        }
        if ($this->returnType == 'Json')
            return json_encode ($results);

        return $results;
    }


    /**
     * Abstraction method that will build an JOIN part of the query
     */
    protected function _buildJoin () {
        if (empty ($this->_join))
            return;

        foreach ($this->_join as $data) {
            list ($joinType,  $joinTable, $joinCondition) = $data;

            if (is_object ($joinTable))
                $joinStr = $this->_buildPair ("", $joinTable);
            else
                $joinStr = $joinTable;

            $this->_query .= " " . $joinType. " JOIN " . $joinStr ." on " . $joinCondition;
        }
    }

    public function _buildDataPairs ($tableData, $tableColumns, $isInsert) {
        foreach ($tableColumns as $column) {
            $value = $tableData[$column];
            if (!$isInsert)
                $this->_query .= "`" . $column . "` = ";

            // Subquery value
            if ($value instanceof MysqliDb) {
                $this->_query .= $this->_buildPair ("", $value) . ", ";
                continue;
            }

            // Simple value
            if (!is_array ($value)) {
                $this->_bindParam($value);
                $this->_query .= '?, ';
                continue;
            }

            // Function value
            $key = key ($value);
            $val = $value[$key];
            switch ($key) {
            case '[I]':
                $this->_query .= $column . $val . ", ";
                break;
            case '[F]':
                $this->_query .= $val[0] . ", ";
                if (!empty ($val[1]))
                    $this->_bindParams ($val[1]);
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
        $this->_query = rtrim($this->_query, ', ');
    }

    /**
     * Helper function to add variables into the query statement
     *
     * @param Array Variable with values
     */
    protected function _buildOnDuplicate($tableData)
    {
        if (is_array($this->_updateColumns) && !empty($this->_updateColumns)) {
            $this->_query .= " on duplicate key update ";
            if ($this->_lastInsertId)
                $this->_query .= $this->_lastInsertId . "=LAST_INSERT_ID (".$this->_lastInsertId."), ";

            foreach ($this->_updateColumns as $key => $val) {
                // skip all params without a value
                if (is_numeric ($key)) {
                    $this->_updateColumns[$val] = '';
                    unset ($this->_updateColumns[$key]);
                } else
                    $tableData[$key] = $val;
            }
            $this->_buildDataPairs ($tableData, array_keys ($this->_updateColumns), false);
        }
    }

    /**
     * Abstraction method that will build an INSERT or UPDATE part of the query
     */
    protected function _buildInsertQuery ($tableData) {
        if (!is_array ($tableData))
            return;

        $isInsert = preg_match ('/^[INSERT|REPLACE]/', $this->_query);
        $dataColumns = array_keys ($tableData);
        if ($isInsert)
            $this->_query .= ' (`' . implode ($dataColumns, '`, `') . '`)  VALUES (';
        else
            $this->_query .= " SET ";

        $this->_buildDataPairs ($tableData, $dataColumns, $isInsert);

        if ($isInsert)
            $this->_query .= ')';
    }

    /**
     * Abstraction method that will build the part of the WHERE conditions
     */
    protected function _buildCondition ($operator, &$conditions) {
        if (empty ($conditions))
            return;

        //Prepare the where portion of the query
        $this->_query .= ' ' . $operator;

        foreach ($conditions as $cond) {
            list ($concat, $varName, $operator, $val) = $cond;
            $this->_query .= " " . $concat ." " . $varName;

            switch (strtolower ($operator)) {
                case 'not in':
                case 'in':
                    $comparison = ' ' . $operator. ' (';
                    if (is_object ($val)) {
                        $comparison .= $this->_buildPair ("", $val);
                    } else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            $this->_bindParam ($v);
                        }
                    }
                    $this->_query .= rtrim($comparison, ',').' ) ';
                    break;
                case 'not between':
                case 'between':
                    $this->_query .= " $operator ? AND ? ";
                    $this->_bindParams ($val);
                    break;
                case 'not exists':
                case 'exists':
                    $this->_query.= $operator . $this->_buildPair ("", $val);
                    break;
                default:
                    if (is_array ($val))
                        $this->_bindParams ($val);
                    else if ($val === null)
                        $this->_query .= $operator . " NULL";
                    else if ($val != 'DBNULL' || $val == '0')
                        $this->_query .= $this->_buildPair ($operator, $val);
            }
        }
    }

    /**
     * Abstraction method that will build the GROUP BY part of the WHERE statement
     *
     */
    protected function _buildGroupBy () {
        if (empty ($this->_groupBy))
            return;

        $this->_query .= " GROUP BY ";
        foreach ($this->_groupBy as $key => $value)
            $this->_query .= $value . ", ";

        $this->_query = rtrim($this->_query, ', ') . " ";
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     */
    protected function _buildOrderBy () {
        if (empty ($this->_orderBy))
            return;

        $this->_query .= " ORDER BY ";
        foreach ($this->_orderBy as $prop => $value) {
            if (strtolower (str_replace (" ", "", $prop)) == 'rand()')
                $this->_query .= "rand(), ";
            else
                $this->_query .= $prop . " " . $value . ", ";
        }

        $this->_query = rtrim ($this->_query, ', ') . " ";
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
     *                               or only $count
     */
    protected function _buildLimit ($numRows) {
        if (!isset ($numRows))
            return;

        if (is_array ($numRows))
            $this->_query .= ' LIMIT ' . (int)$numRows[0] . ', ' . (int)$numRows[1];
        else
            $this->_query .= ' LIMIT ' . (int)$numRows;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return mysqli_stmt
     */
    protected function _prepareQuery()
    {
        if (!$stmt = $this->mysqli()->prepare($this->_query))
            throw new Exception ("Problem preparing query ($this->_query) " . $this->mysqli()->error);
        if ($this->traceEnabled)
            $this->traceStartQ = microtime (true);

        return $stmt;
    }

    /**
     * Close connection
     */
    public function __destruct()
    {
        if ($this->isSubQuery)
            return;
        if ($this->_mysqli) {
            $this->_mysqli->close();
            $this->_mysqli = null;
        }
    }

    /**
     * @param array $arr
     *
     * @return array
     */
    protected function refValues(Array &$arr)
    {
        //Reference in the function arguments are required for HHVM to work
        //https://github.com/facebook/hhvm/issues/5155
        //Referenced data array is required by mysqli since PHP 5.3+
        if (strnatcmp (phpversion(), '5.3') >= 0) {
            $refs = array();
            foreach ($arr as $key => $value)
                $refs[$key] = & $arr[$key];
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
            if ($val === NULL)
                $val = 'NULL';
            $newStr .= substr ($str, 0, $pos) . "'". $val . "'";
            $str = substr ($str, $pos + 1);
        }
        $newStr .= $str;
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
        if (!$this->_mysqli)
            return "mysqli is null";
        return trim ($this->_stmtError . " " . $this->mysqli()->error);
    }

    /**
     * Mostly internal method to get query and its params out of subquery object
     * after get() and getAll()
     *
     * @return array
     */
    public function getSubQuery () {
        if (!$this->isSubQuery)
            return null;

        array_shift ($this->_bindParams);
        $val = Array ('query' => $this->_query,
                      'params' => $this->_bindParams,
                      'alias' => $this->host
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
                throw new Exception("invalid interval type in '{$diff}'");
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
     * @param int increment by int or float. 1 by default
     */
    public function inc($num = 1) {
        if(!is_numeric($num))
            throw new Exception ('Argument supplied to inc must be a number');
        return Array ("[I]" => "+" . $num);
    }

    /**
     * Method generates decrimental function call
     * @param int increment by int or float. 1 by default
     */
    public function dec ($num = 1) {
        if(!is_numeric($num))
            throw new Exception ('Argument supplied to dec must be a number');
        return Array ("[I]" => "-" . $num);
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
    public static function subQuery($subQueryAlias = "")
    {
        return new MysqliDb (Array('host' => $subQueryAlias, 'isSubQuery' => true));
    }

    /**
     * Method returns a copy of a mysqlidb subquery object
     *
     * @param object new mysqlidb object
     */
    public function copy ()
    {
        $copy = unserialize (serialize ($this));
        $copy->_mysqli = $this->_mysqli;
        return $copy;
    }

    /**
     * Begin a transaction
     *
     * @uses mysqli->autocommit(false)
     * @uses register_shutdown_function(array($this, "_transaction_shutdown_check"))
     */
    public function startTransaction () {
        $this->mysqli()->autocommit (false);
        $this->_transaction_in_progress = true;
        register_shutdown_function (array ($this, "_transaction_status_check"));
    }

    /**
     * Transaction commit
     *
     * @uses mysqli->commit();
     * @uses mysqli->autocommit(true);
     */
    public function commit () {
        $this->mysqli()->commit ();
        $this->_transaction_in_progress = false;
        $this->mysqli()->autocommit (true);
    }

    /**
     * Transaction rollback function
     *
     * @uses mysqli->rollback();
     * @uses mysqli->autocommit(true);
     */
    public function rollback () {
      $this->mysqli()->rollback ();
      $this->_transaction_in_progress = false;
      $this->mysqli()->autocommit (true);
    }

    /**
     * Shutdown handler to rollback uncommited operations in order to keep
     * atomic operations sane.
     *
     * @uses mysqli->rollback();
     */
    public function _transaction_status_check () {
        if (!$this->_transaction_in_progress)
            return;
        $this->rollback ();
    }

    /**
     * Query exection time tracking switch
     *
     * @param bool $enabled Enable execution time tracking
     * @param string $stripPrefix Prefix to strip from the path in exec log
     **/
    public function setTrace ($enabled, $stripPrefix = null) {
        $this->traceEnabled = $enabled;
        $this->traceStripPrefix = $stripPrefix;
        return $this;
    }
    /**
     * Get where and what function was called for query stored in MysqliDB->trace
     *
     * @return string with information
     */
    private function _traceGetCaller () {
        $dd = debug_backtrace ();
        $caller = next ($dd);
        while (isset ($caller) &&  $caller["file"] == __FILE__ )
            $caller = next($dd);

        return __CLASS__ . "->" . $caller["function"] . "() >>  file \"" .
                str_replace ($this->traceStripPrefix, '', $caller["file"] ) . "\" line #" . $caller["line"] . " " ;
    }

    /**
     * Method to check if needed table is created
     *
     * @param array $tables Table name or an Array of table names to check
     *
     * @returns boolean True if table exists
     */
    public function tableExists ($tables) {
        $tables = !is_array ($tables) ? Array ($tables) : $tables;
        $count = count ($tables);
        if ($count == 0)
            return false;

        array_walk ($tables, function (&$value, $key) { $value = self::$prefix . $value; });
        $this->where ('table_schema', $this->db);
        $this->where ('table_name', $tables, 'IN');
        $this->get ('information_schema.tables', $count);
        return $this->count == $count;
    }

    /**
     * Return result as an associative array with $idField field value used as a record key
     *
     * @param String $idField field name to use for a mapped element key
     *
     * @return Array Returns an array($k => $v) if get(.."param1, param2"), array ($k => array ($v, $v)) otherwise
     */
    public function map ($idField) {
        $this->_mapKey = $idField;
        return $this;
    }
} // END class
