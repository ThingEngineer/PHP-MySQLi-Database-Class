<?php
require_once "../dbObject.php";

class accData extends dbObject {
    protected $dbTable = "acc_data";
    protected $primaryKey = "id";

    public $calldate;
    public $callid;
    public $clientid;
    public $queueid;
    
    public function last () {
        $this->where ("id" , 1300, '>');
        return $this;
    }
}


?>
