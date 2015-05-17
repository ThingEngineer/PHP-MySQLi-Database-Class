<?php
require_once ("user.php");

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
        'userid' => 'int',
        'iscallerid' => 'int',
        'testvar' => 'int'
    );
    protected $relations = Array (
        'userid' => Array ("hasOne", "user")
    );

    protected $jsonFields = Array ('authcode');
    
    public function last () {
        $this->where ("id" , 130, '>');
        return $this;
    }
}


?>
