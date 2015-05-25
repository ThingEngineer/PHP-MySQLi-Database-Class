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
class product extends dbObject {
    protected $dbTable = "products";
    protected $primaryKey = "id";
    protected $dbFields = Array (
        'userId' => 'int:required',
        'customerId' => 'int:required',
        'productName' => 'char:required'
    );
    protected $relations = Array (
        'userId' => Array ("hasOne", "user")
    );

    public function last () {
        $this->where ("id" , 130, '>');
        return $this;
    }
}


?>
