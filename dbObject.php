<?php
/**
 * Mysqli Model wrapper
 *
 * @category  Database Access
 * @package   MysqliDb
 * @author    Alexander V. Butenko <a.butenka@gmail.com>
 * @copyright Copyright (c) 2015
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   2.1
 *
 * @method int count ()
 * @method mixed byId (string $id, mixed $fields)
 * @method mixed get (mixed $limit, mixed $fields)
 * @method mixed getOne (mixed $fields)
 * @method mixed paginate (int $page, array $fields)
 * @method dbObject query ($query, $numRows)
 * @method dbObject rawQuery ($query, $bindParams, $sanitize)
 * @method dbObject join (string $objectName, string $key, string $joinType)
 * @method dbObject with (string $objectName)
 * @method dbObject groupBy (string $groupByField)
 * @method dbObject orderBy ($orderByField, $orderbyDirection, $customFields)
 * @method dbObject where ($whereProp, $whereValue, $operator)
 * @method dbObject orWhere ($whereProp, $whereValue, $operator)
 * @method dbObject setQueryOption ($options)
 * @method dbObject setTrace ($enabled, $stripPrefix)
 * @method dbObject withTotalCount ()
 * @method dbObject startTransaction ()
 * @method dbObject commit ()
 * @method dbObject rollback ()
 * @method dbObject ping ()
 * @method string getLastError ()
 * @method string getLastQuery ()
 **/
class dbObject {
    /**
     * Working instance of MysqliDb created earlier
     *
     * @var MysqliDb
     */
    private $db;
    /**
     * Loaded related object storage
     *
     * @var array
     */
    private $_related = Array();
    /**
     * Models path
     *
     * @var modelPath
     */
    protected static $modelPath;
    /**
     * An array that holds object data
     *
     * @var array
     */
    public $data;
    /**
     * Flag to define is object is new or loaded from database
     *
     * @var boolean
     */
    public $isNew = true;
    /**
     * Return type: 'Array' to return results as array, 'Object' as object
     * 'Json' as json string
     *
     * @var string
     */
    public $returnType = 'Object';
    /**
     * An array that holds has* objects which should be loaded togeather with main
     * object togeather with main object
     *
     * @var string
     */
    private $_with = Array();
    /**
     * Per page limit for pagination
     *
     * @var int
     */
    public $pageLimit = 20;
    /**
     * Variable that holds total pages count of last paginate() query
     *
     * @var int
     */
    public $totalPages = 0;
    /**
     * An array that holds insert/update/select errors
     *
     * @var array
     */
    public $errors = null;
    /**
     * Primary key for an object. 'id' is a default value.
     *
     * @var stating
     */
    protected $primaryKey = 'id';
    /**
     * Table name for an object. Class name will be used by default
     *
     * @var stating
     */
    protected $dbTable;

    /**
     * @param array $data Data to preload on object creation
     */
    public function __construct ($data = null) {
        $this->db = MysqliDb::getInstance();
        if (empty ($this->dbTable))
            $this->dbTable = get_class ($this);

        if ($data)
            $this->data = $data;
    }

    /**
     * Magic setter function
     *
     * @return mixed
     */
    public function __set ($name, $value) {
        $this->data[$name] = $value;
    }

    /**
     * Magic getter function
     *
     * @param $name Variable name
     *
     * @return mixed
     */
    public function __get ($name) {

        if (isset ($this->data[$name])) {
            return $this->data[$name];
        }

        if (property_exists ($this->db, $name))
            return $this->db->$name;

        if(isset($this->_related[$name]))
        {
            // Return related model that has already been fetched
            return $this->_related[$name];
        }

        if (property_exists ($this, 'relations') && isset ($this->relations[$name])) {
            $relationType = strtolower ($this->relations[$name][0]);
            $modelName = $this->relations[$name][1];
            switch ($relationType) {
                case 'hasone':
                    $key = $this->relations[$name][2];
                    $obj = new $modelName;
                    $obj->returnType = $this->returnType;
                    return $this->_related[$name] = $obj->byId($this->data[$key]);
                    break;
                case 'hasmany':
                    $key = $this->relations[$name][2];
                    $obj = new $modelName;
                    $obj->returnType = $this->returnType;
                    return $this->_related[$name] = $obj->where($key, $this->data[$this->primaryKey]);
                    break;
                case 'hasmanythrough':
                    $pivotTable = $this->relations[$name][2];
                    $key = $this->relations[$name][3];
                    $farKey = $this->relations[$name][4];
                    $obj = new $modelName;
                    $obj->returnType = $this->returnType;
                    $joinStr = MysqliDb::$prefix . $obj->dbTable . ".{$obj->primaryKey} = " .
                               MysqliDb::$prefix . $pivotTable.'.'.$farKey;
                    $obj->db->join($pivotTable, $joinStr, 'LEFT');
                    return $this->_related[$name] = $obj->where($key, $this->data[$this->primaryKey]);
                    break;
                default:
                    break;
            }
        }
    }

    public function __isset ($name) {
        if (isset ($this->data[$name]))
            return isset ($this->data[$name]);

        if (property_exists ($this->db, $name))
            return isset ($this->db->$name);
    }

    public function __unset ($name) {
        unset ($this->data[$name]);
    }

    /**
     * Helper function to create dbObject with Json return type
     *
     * @return dbObject
     */
    public static function JsonBuilder () {
        $obj = new static;
        $obj->returnType = 'Json';
        return $obj;
    }

    /**
     * Helper function to create dbObject with Array return type
     *
     * @return dbObject
     */
    public static function ArrayBuilder () {
        $obj = new static;
        $obj->returnType = 'Array';
        return $obj;
    }

    /**
     * Helper function to create dbObject with Object return type.
     * Added for consistency. Works same way as new $objname ()
     *
     * @return dbObject
     */
    public static function ObjectBuilder () {
        return new static;
    }

    /**
     * Helper function to create a virtual table class
     *
     * @param string tableName Table name
     * @return dbObject
     */
    public static function table ($tableName) {
        $tableName = preg_replace ("/[^-a-z0-9_]+/i",'', $tableName);
        if (!class_exists ($tableName))
            eval ("class $tableName extends dbObject {}");
        return new $tableName ();
    }
    /**
     * @return mixed insert id or false in case of failure
     */
    public function insert () {
        if (!empty ($this->timestamps) && in_array ("createdAt", $this->timestamps))
            $this->createdAt = date("Y-m-d H:i:s");
        $sqlData = $this->prepareData ();
        if (!$this->validate ($sqlData))
            return false;

        $id = $this->db->insert ($this->dbTable, $sqlData);
        if (!empty ($this->primaryKey))
            $this->data[$this->primaryKey] = $id;
        $this->isNew = false;

        return $id;
    }

    /**
     * @param array $data Optional update data to apply to the object
     */
    public function update ($data = null) {
        if (empty ($this->dbFields))
            return false;

        if (empty ($this->data[$this->primaryKey]))
            return false;

        if ($data) {
            foreach ($data as $k => $v)
                $this->$k = $v;
        }

        if (!empty ($this->timestamps) && in_array ("updatedAt", $this->timestamps))
            $this->updatedAt = date("Y-m-d H:i:s");

        $sqlData = $this->prepareData ();
        if (!$this->validate ($sqlData))
            return false;

        $this->db->where ($this->primaryKey, $this->data[$this->primaryKey]);
        return $this->db->update ($this->dbTable, $sqlData);
    }

    /**
     * Save or Update object
     *
     * @return mixed insert id or false in case of failure
     */
    public function save ($data = null) {
        if ($this->isNew) {
            $id = $this->insert();
            if (isset ($this->primaryKey))
                $this->data[$this->primaryKey] = $id;
            return $id;
        }
        return $this->update($data);
    }

    /**
     * Delete method. Works only if object primaryKey is defined
     *
     * @return boolean Indicates success. 0 or 1.
     */
    public function delete () {
        if (empty ($this->data[$this->primaryKey]))
            return false;

        $this->db->where ($this->primaryKey, $this->data[$this->primaryKey]);
        return $this->db->delete ($this->dbTable);
    }

    /**
     * Get object by primary key.
     *
     * @access public
     * @param $id Primary Key
     * @param array|string $fields Array or coma separated list of fields to fetch
     *
     * @return dbObject|array
     */
    private function byId ($id, $fields = null) {
        $this->db->where (MysqliDb::$prefix . $this->dbTable . '.' . $this->primaryKey, $id);
        return $this->getOne ($fields);
    }

    /**
     * Convinient function to fetch one object. Mostly will be togeather with where()
     *
     * @access public
     * @param array|string $fields Array or coma separated list of fields to fetch
     *
     * @return dbObject
     */
    private function getOne ($fields = null) {
        $this->processHasOneWith ();
        $results = $this->db->ArrayBuilder()->getOne ($this->dbTable, $fields);
        $this->processArrays ($results);
        $this->data = $results;
        $this->processAllWith ($results);
        if ($this->returnType == 'Json')
            return json_encode ($results);
        if ($this->returnType == 'Array')
            return $results;

        $item = new static ($results);
        $item->isNew = false;

        return $item;
    }

    /**
     * Fetch all objects
     *
     * @access public
     * @param integer|array $limit Array to define SQL limit in format Array ($count, $offset)
                                   or only $count
     * @param array|string $fields Array or coma separated list of fields to fetch
     *
     * @return array Array of dbObjects
     */
    private function get ($limit = null, $fields = null) {
        $objects = Array ();
        $this->processHasOneWith ();
        $results = $this->db->ArrayBuilder()->get ($this->dbTable, $limit, $fields);
        foreach ($results as &$r) {
            $this->processArrays ($r);
            $this->data = $r;
            $this->processAllWith ($r, false);
            if ($this->returnType == 'Object') {
                $item = new static ($r);
                $item->isNew = false;
                $objects[] = $item;
            }
        }
        $this->_with = Array();
        if ($this->returnType == 'Object')
            return $objects;

        if ($this->returnType == 'Json')
            return json_encode ($results);

        return $results;
    }

    /**
     * Function to set witch hasOne or hasMany objects should be loaded togeather with a main object
     *
     * @access public
     * @param string $objectName Object Name
     *
     * @return dbObject
     */
    private function with ($objectName) {
        if (!property_exists ($this, 'relations') && !isset ($this->relations[$name]))
            die ("No relation with name $objectName found");

        $this->_with[$objectName] = $this->relations[$objectName];

        return $this;
    }

    /**
     * Function to join object with another object.
     *
     * @access public
     * @param string $objectName Object Name
     * @param string $key Key for a join from primary object
     * @param string $joinType SQL join type: LEFT, RIGHT,  INNER, OUTER
     *
     * @return dbObject
     */
    private function join ($objectName, $key = null, $joinType = 'LEFT') {
        $joinObj = new $objectName;
        if (!$key)
            $key = $objectName . "id";
        $joinStr = MysqliDb::$prefix . $this->dbTable . ".{$key} = " .
                    MysqliDb::$prefix . "{$joinObj->dbTable}.{$joinObj->primaryKey}";
        $this->db->join ($joinObj->dbTable, $joinStr, $joinType);
        return $this;
    }

    /**
     * Function to get a total records count
     *
     * @return int
     */
    private function count () {
        $res = $this->db->ArrayBuilder()->getValue ($this->dbTable, "count(*)");
        return $res['cnt'];
    }

    /**
     * Pagination wraper to get()
     *
     * @access public
     * @param int $page Page number
     * @param array|string $fields Array or coma separated list of fields to fetch
     * @return array
     */
    private function paginate ($page, $fields = null) {
        $offset = $this->pageLimit * ($page - 1);
        $this->db->withTotalCount();
        $results = $this->get (Array ($this->pageLimit, $offset), $fields);
        $this->totalPages = round ($this->db->totalCount / $this->pageLimit);

        return $results;
    }

    /**
     * Catches calls to undefined methods.
     *
     * Provides magic access to private functions of the class and native public mysqlidb functions
     *
     * @param string $method
     * @param mixed $arg
     *
     * @return mixed
     */
    public function __call ($method, $arg) {
        if (method_exists ($this, $method))
            return call_user_func_array (array ($this, $method), $arg);

        call_user_func_array (array ($this->db, $method), $arg);
        return $this;
    }

    /**
     * Catches calls to undefined static methods.
     *
     * Transparently creating dbObject class to provide smooth API like name::get() name::orderBy()->get()
     *
     * @param string $method
     * @param mixed $arg
     *
     * @return mixed
     */
    public static function __callStatic ($method, $arg) {
        $obj = new static;
        $result = call_user_func_array (array ($obj, $method), $arg);
        if (method_exists ($obj, $method))
            return $result;
        return $obj;
    }

    /**
     * Converts object data to an associative array.
     *
     * @return array Converted data
     */
    public function toArray () {
        $data = $this->data;
        $this->processAllWith ($data);
        foreach ($data as &$d) {
            if ($d instanceof dbObject)
                $d = $d->data;
        }
        return $data;
    }

    /**
     * Converts object data to a JSON string.
     *
     * @return string Converted data
     */
    public function toJson () {
        return json_encode ($this->toArray());
    }

    /**
     * Converts object data to a JSON string.
     *
     * @return string Converted data
     */
    public function __toString () {
        return $this->toJson ();
    }

    /**
     * Function queries hasMany relations if needed and also converts hasOne object names
     *
     * @param array $data
     */
    private function processAllWith (&$data, $shouldReset = true) {
        if (count ($this->_with) == 0)
            return;

        foreach ($this->_with as $name => $opts) {
            $relationType = strtolower ($opts[0]);
            $modelName = $opts[1];
            if ($relationType == 'hasone') {
                $obj = new $modelName;
                $table = $obj->dbTable;

                if (!isset ($data[$table])) {
                    $data[$name] = $this->$name;
                    continue;
                }
                if ($this->returnType == 'Object') {
                    $item = new $modelName ($data[$table]);
                    $item->returnType = $this->returnType;
                    $item->isNew = false;
                    $data[$name] = $item;
                } else {
                    $data[$name] = $data[$table];
                }
                unset ($data[$table]);
            }
            else
                $data[$name] = $this->$name;
        }
        if ($shouldReset)
            $this->_with = Array();
    }

    /*
     * Function building hasOne joins for get/getOne method
     */
    private function processHasOneWith () {
        if (count ($this->_with) == 0)
            return;
        foreach ($this->_with as $name => $opts) {
            $relationType = strtolower ($opts[0]);
            $modelName = $opts[1];
            $key = null;
            if (isset ($opts[2]))
                $key = $opts[2];
            if ($relationType == 'hasone') {
                $this->db->setQueryOption ("MYSQLI_NESTJOIN");
                $this->join ($modelName, $key);
            }
        }
    }

    /**
     * @param array $data
     */
    private function processArrays (&$data) {
            if (isset ($this->jsonFields) && is_array ($this->jsonFields)) {
                foreach ($this->jsonFields as $key)
                    $data[$key] = json_decode ($data[$key]);
            }

            if (isset ($this->arrayFields) && is_array($this->arrayFields)) {
                foreach ($this->arrayFields as $key)
                    $data[$key] = explode ("|", $data[$key]);
            }
    }

    /**
     * @param array $data
     */
    private function validate ($data) {
        if (!$this->dbFields)
            return true;

        foreach ($this->dbFields as $key => $desc) {
            $type = null;
            $required = false;
            if (isset ($data[$key]))
                $value = $data[$key];
            else
                $value = null;

            if (is_array ($value))
                continue;

            if (isset ($desc[0]))
                $type = $desc[0];
            if (isset ($desc[1]) && ($desc[1] == 'required'))
                $required = true;

            if ($required && strlen ($value) == 0) {
                $this->errors[] = Array ($this->dbTable . "." . $key => "is required");
                continue;
            }
            if ($value == null)
                continue;

            switch ($type) {
                case "text";
                    $regexp = null;
                    break;
                case "int":
                    $regexp = "/^[0-9]*$/";
                    break;
                case "bool":
                    $regexp = '/^[yes|no|0|1|true|false]$/i';
                    break;
                case "datetime":
                    $regexp = "/^[0-9a-zA-Z -:]*$/";
                    break;
                default:
                    $regexp = $type;
                    break;
            }
            if (!$regexp)
                continue;

            if (!preg_match ($regexp, $value)) {
                $this->errors[] = Array ($this->dbTable . "." . $key => "$type validation failed");
                continue;
            }
        }
        return !count ($this->errors) > 0;
    }

    private function prepareData () {
        $this->errors = Array ();
        $sqlData = Array();
        if (count ($this->data) == 0)
            return Array();

        if (method_exists ($this, "preLoad"))
            $this->preLoad ($data);

        if (!$this->dbFields)
            return $this->data;

        foreach ($this->data as $key => &$value) {
            if ($value instanceof dbObject && $value->isNew == true) {
                $id = $value->save();
                if ($id)
                    $value = $id;
                else
                    $this->errors = array_merge ($this->errors, $value->errors);
            }

            if (!in_array ($key, array_keys ($this->dbFields)))
                continue;

            if (!is_array($value)) {
                $sqlData[$key] = $value;
                continue;
            }

            if (isset ($this->jsonFields) && in_array ($key, $this->jsonFields))
                $sqlData[$key] = json_encode($value);
            else if (isset ($this->arrayFields) && in_array ($key, $this->arrayFields))
                $sqlData[$key] = implode ("|", $value);
            else
                $sqlData[$key] = $value;
        }
        return $sqlData;
    }

    private static function dbObjectAutoload ($classname) {
        $filename = static::$modelPath . $classname .".php";
        if (file_exists ($filename))
            include ($filename);
    }

    /*
     * Enable models autoload from a specified path
     *
     * Calling autoload() without path will set path to dbObjectPath/models/ directory
     *
     * @param string $path
     */
    public static function autoload ($path = null) {
        if ($path)
            static::$modelPath = $path . "/";
        else
            static::$modelPath = __DIR__ . "/models/";
        spl_autoload_register ("dbObject::dbObjectAutoload");
    }
}
?>
