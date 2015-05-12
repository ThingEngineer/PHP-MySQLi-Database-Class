<?php
require_once "../dbObject.php";

class accData extends dbObject {
    protected $dbTable = "acc_data";
    protected $primaryKey = "id";

    public function last () {
        $this->db->where ("id" , 1300, '>');
        return $this;
    }
}


?>
