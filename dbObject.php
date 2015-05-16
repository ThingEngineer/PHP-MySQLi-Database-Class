<?php
abstract class dbObject {
    private $db;
    public $data;
    public $isNew = true;
    public $returnType = 'Object';


    public function __construct ($data = null) {
        $this->db = MysqliDb::getInstance();
        if ($data)
            $this->data = $data;
    }

    public function __set ($name, $value) {
        $this->data[$name] = $value;
    }

    public function __get ($name) {
        if (isset ($this->data[$name]))
            return $this->data[$name];

        if (property_exists ($this->db, $name))
            return $this->db->$name;
    }

    public function __isset ($name) {
        if ($this->data[$name])
            return isset ($this->data[$name]);

        if (property_exists ($this->db, $name))
            return isset ($this->db->$name);
    }

    public function __unset ($name) {
        unset ($this->data[$name]);
    }

    public static function ObjectBuilder () {
        $obj = self::objectCopy ();
        $obj->returnType = 'Object';
        return $obj;
    }

    public static function ArrayBuilder () {
        $obj = self::objectCopy ();
        $obj->returnType = 'Array';
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


    public function byId ($id, $fields = null) {
        $this->db->where($this->primaryKey, $id);
        return $this->getOne ($fields);
    }

    public function getOne ($fields = null) {
        $results = $this->db->getOne ($this->dbTable, $fields);
        if (isset($this->jsonFields) && is_array($this->jsonFields)) {
            foreach ($this->jsonFields as $key)
                $results[$key] = json_decode ($results[$key]);
        }
        if (isset($this->arrayFields) && is_array($this->arrayFields)) {
            foreach ($this->arrayFields as $key)
                $results[$key] = explode ("|", $results[$key]);
        }
        if ($this->returnType == 'Array')
            return $results;

        $item = $this->objectCopy ($results);
        $item->isNew = false;

        return $item;
    }

    public function get ($limit = null, $fields = null) {
        $objects = Array ();
        $results = $this->db->get($this->dbTable);
        foreach ($results as &$r) {
            if (isset ($this->jsonFields) && is_array($this->jsonFields)) {
                foreach ($this->jsonFields as $key)
                    $r[$key] = json_decode ($r[$key]);
            }
            if (isset ($this->arrayFields) && is_array($this->arrayFields)) {
                foreach ($this->arrayFields as $key)
                    $r[$key] = explode ("|", $r[$key]);
            }
            if ($this->returnType == 'Object') {
                $item = $this->objectCopy ($r);
                $item->isNew = false;
                $objects[] = $item;
            }
        }
        if ($this->returnType == 'Object')
            return $objects;
        return $results;
    }

    public function count () {
        $res = $this->db->getValue ($this->dbTable, "count(*)");
        return $res['cnt'];
    }


    public function __call ($method, $arg) {
        call_user_func_array (array ($this->db, $method), $arg);
        return $this;
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


    private static function objectCopy ($data = null) {
        $className = get_called_class ();
        return new $className ($data);
    }


}
?>
