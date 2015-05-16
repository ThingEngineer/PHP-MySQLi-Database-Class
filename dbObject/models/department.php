<?php
require_once "../dbObject.php";

/**
 * To make IDEs autocomplete happy
 *
 * @property string id
 * @property string userid
 * @property string name
 * @property string authcode 
 * @property string iscallerid
 */
class department extends dbObject {
    protected $dbTable = "departments";
    protected $primaryKey = "id";
    protected $dbFields = Array (
        'userid' => 'int:required',
        'name' => 'int:required',
        'authcode' => 'int',
        'iscallerid' => 'int',
        'testvar' => 'int'
    );
    protected $jsonFields = Array ('authcode');
    
    public function last () {
        $this->setTrace (true);
        $this->where ("id" , 130, '>');
        return $this;
    }
}


?>
