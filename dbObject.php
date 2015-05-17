<?php
abstract class dbObject {
    private $db;
    public $data;
    public $isNew = true;
    public static $returnType = 'Object';

    public function __construct ($data = null) {
        $this->db = MysqliDb::getInstance();
        if ($data)
            $this->data = $data;
    }

    public function __set ($name, $value) {
        $this->data[$name] = $value;
    }

    public function __get ($name) {
        if (property_exists ($this, 'relations')) {
            if (isset ($this->relations[$name])) {
                $relationType = strtolower ($this->relations[$name][0]);
                $modelName = $this->relations[$name][1];
                switch ($relationType) {
                    case 'hasone':
                        return $modelName::ObjectBuilder()->byId($this->data[$name]);
                        break;
                    case 'hasmany':
                        $key = $this->relations[$name][2];
                        return $modelName::ObjectBuilder()->where($key, $this->data[$this->primaryKey])->get();
                        break;
                    default:
                        break;
                }
            }
        }


        if (isset ($this->data[$name]))
            return $this->data[$name];

        if (property_exists ($this->db, $name))
            return $this->db->$name;

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

    public static function ArrayBuilder () {
        $obj = new static;
        $obj->returnType = 'Array';
        return $obj;
    }

    public static function ObjectBuilder () {
        $obj = new static;
        return $obj;
    }

    public function insert () {
        $sqlData = $this->prepareData ();
        $id = $this->db->insert ($this->dbTable, $sqlData);
        if (!empty ($this->primaryKey))
            $this->data[$this->primaryKey] = $id;
        $this->isNew = false;

        return $id;
    }

    public function update ($data = null) {
        if (empty ($this->dbFields))
            return false;

        if (empty ($this->data[$this->primaryKey]))
            return false;

        if ($data) {
            foreach ($data as $k => $v)
                $this->$k = $v;
        }

        $sqlData = $this->prepareData ();
        $this->db->where ($this->primaryKey, $this->data[$this->primaryKey]);
        return $this->db->update ($this->dbTable, $sqlData);
    }

    public function save () {
        if ($this->isNew)
            return $this->insert();
        return $this->update();
    }

    public function remove () {
        if (empty ($this->data[$this->primaryKey]))
            return false;

        $this->db->where ($this->primaryKey, $this->data[$this->primaryKey]);
        return $this->db->delete ($this->dbTable);
    }


    private function byId ($id, $fields = null) {
        return $this->getOne ($fields, $id);
    }

    private function getOne ($fields = null, $primaryKey = null) {
        if ($primaryKey)
            $this->db->where ($this->primaryKey, $primaryKey);

        $results = $this->db->getOne ($this->dbTable, $fields);
        if (isset($this->jsonFields) && is_array($this->jsonFields)) {
            foreach ($this->jsonFields as $key)
                $results[$key] = json_decode ($results[$key]);
        }
        if (isset($this->arrayFields) && is_array($this->arrayFields)) {
            foreach ($this->arrayFields as $key)
                $results[$key] = explode ("|", $results[$key]);
        }
        if (static::$returnType == 'Array')
            return $results;

        $item = new static ($results);
        $item->isNew = false;

        return $item;
    }

    private function get ($limit = null, $fields = null) {
        $objects = Array ();
        $results = $this->db->get ($this->dbTable, $limit, $fields);
        foreach ($results as &$r) {
            if (isset ($this->jsonFields) && is_array($this->jsonFields)) {
                foreach ($this->jsonFields as $key)
                    $r[$key] = json_decode ($r[$key]);
            }
            if (isset ($this->arrayFields) && is_array($this->arrayFields)) {
                foreach ($this->arrayFields as $key)
                    $r[$key] = explode ("|", $r[$key]);
            }
            if (static::$returnType == 'Object') {
                $item = new static ($r);
                $item->isNew = false;
                $objects[] = $item;
            }
        }
        if (static::$returnType == 'Object')
            return $objects;
        return $results;
    }

    public function join ($objectName, $key = null, $joinType = 'LEFT') {
        $joinObj = new $objectName;
        if (!$key)
            $key = $objectName . "id";
        $joinStr = "{$this->dbTable}.{$key} = {$joinObj->dbTable}.{$joinObj->primaryKey}";
        $this->db->join ($joinObj->dbTable, $joinStr, $joinType);
        return $this;
    }

    public function count () {
        $res = $this->db->getValue ($this->dbTable, "count(*)");
        return $res['cnt'];
    }

    public function __call ($method, $arg) {
        if (method_exists ($this, $method))
            return call_user_func_array (array ($this, $method), $arg);

        call_user_func_array (array ($this->db, $method), $arg);
        return $this;
    }

    public static function __callStatic ($method, $arg) {
        $obj = new static;
        $result = call_user_func_array (array ($obj, $method), $arg);
        if (method_exists ($obj, $method))
            return $result;
        return $obj;
    }

    public function toJson () {
        return json_encode ($this->data);
    }

    public function __toString () {
        return $this->toJson ();
    }

    private function prepareData () {
        $sqlData = Array();
        if (method_exists ($this, "preLoad"))
            $this->preLoad ($data);

        if (count ($this->data) == 0)
            return Array();

        foreach ($this->data as $key => $value) {
            if (!in_array ($key, array_keys ($this->dbFields)))
                continue;

            if (!is_array($value)) {
                $sqlData[$key] = $value;
                continue;
            }

            if (in_array ($key, $this->jsonFields))
                $sqlData[$key] = json_encode($value);
            else
                $sqlData[$key] = implode ("|", $value);

        }
        return $sqlData;
    }
}
?>
