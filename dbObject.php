<?php
abstract class dbObject {
    public $db;
    
    public function __construct () {
        $this->db = MysqliDb::getInstance();
    }

    private function cleanup ($data) {
        if (method_exists ($this, "preLoad"))
            $this->preLoad ($data);

        if (count ($data) == 0)
            return Array();

        foreach ($this->dbFields  as $key => $prop) {
            $value = $data[$key];
            if (!is_array($value)) {
                $sqlData[$key] = $value;
                continue;
            }

            if ($prop['json'])
                $sqlData[$key] = json_encode($value);
            else if ($prop['array'])
                $sqlData[$key] = implode ("|", $value);
            else
                $sqlData[$key] = $value;
        }

        return $sqlData;
    }

    public function insert (&$data) {
        if (empty ($this->dbFields))
            return false;

        $data = $this->cleanup ($data);
        $id = $this->db->insert ($this->dbTable, $data);
        $data[$this->$primaryKey] = $id;
        return $id;
    }

    public function remove ($data) {
        $this->db->where ($this->primaryKey, $data['id']);
        return $this->db->delete ($this->dbTable);
    }

    public function update ($data) {
        if (empty ($this->dbFields))
            return false;

        $data = $this->cleanup ($data);
        $this->db->where ($this->primaryKey, $data[$this->primaryKey]);
        return $this->db->update ($this->dbTable, $data);
    }

    public function getOne($id, $fields = null) {
        $this->db->where($this->primaryKey, $id);
        $results = $this->db->getOne ($this->dbTable, $fields);
        if (isset($this->jsonFields) && is_array($this->jsonFields)) {
            foreach ($this->jsonFields as $key)
                $results[$key] = json_decode ($results[$key]);
        }
        if (isset($this->arrayFields) && is_array($this->arrayFields)) {
            foreach ($this->arrayFields as $key)
                $results[$key] = explode ("|", $results[$key]);
        }
        return $results;
    }

    public function get ($limit = null, $fields = null) {
        $db = MysqliDb::getInstance();
        $results = $db->get($this->dbTable);
        foreach ($results as &$r) {
            if (isset ($this->jsonFields) && is_array($this->jsonFields)) {
                foreach ($this->jsonFields as $key)
                    $r[$key] = json_decode ($r[$key]);
            }
            if (isset ($this->arrayFields) && is_array($this->arrayFields)) {
                foreach ($this->arrayFields as $key)
                    $r[$key] = explode ("|", $r[$key]);
            }
        }
        return $results;
    }

    public function where ($whereProp, $whereValue = null, $operator = null) {
        $this->db->where ($whereProp, $whereValue, $operator);
        return $this;
    }

    public function orWhere ($whereProp, $whereValue = null, $operator = null) {
        $this->db->orWhere ($whereProp, $whereValue, $operator);
        return $this;
    }

    public function orderBy($orderByField, $orderbyDirection = "DESC", $customFields = null) {
        $this->db->orderBy ($orderByField, $orderbyDirection, $customFields);
        return $this;
    }

    public function count () {
        $res = $this->db->getValue($this->dbTable, "count(*)");
        return $res['cnt'];
    }
}
?>
