<?php
require_once "product.php";

/**
 * To make IDEs autocomplete happy
 *
 * @property string id
 * @property string userid
 * @property string name
 * @property string authcode 
 * @property string iscallerid
 */
class user extends dbObject {
    protected $dbTable = "users";
    protected $primaryKey = "id";
    protected $dbFields = Array (
        'login' => 'text',
        'active' => 'int',
        'customerId' => 'int',
        'firstName' => 'text',
        'lastName' => 'text',
        'password' => 'text',
        'createdAt' => 'datetime',
        'expires' => 'datetime',
        'loginCount' => 'int'
    );

    protected $relations = Array (
        'products' => Array ("hasMany", "product", 'userid')
    );
}


?>
