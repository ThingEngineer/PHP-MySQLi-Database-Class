<?php
/**
 * To make IDEs autocomplete happy
 *
 * @property int id
 * @property string login
 * @property bool active
 * @property string customerId
 * @property string firstName
 * @property string lastName
 * @property string password
 * @property string created_at
 * @property string updated_at
 * @property string expires
 * @property int loginCount
 */
class user extends dbObject {
    protected $dbTable = "users";
    protected $dbFields = Array (
        'login' => Array ('text', 'required'),
        'active' => Array ('bool'),
        'customerId' => Array ('int'),
        'firstName' => Array ('/[a-zA-Z0-9 ]+/'),
        'lastName' => Array ('text'),
        'password' => Array ('text'),
        'created_at' => Array ('datetime'),
        'updated_at' => Array ('datetime'),
        'expires' => Array ('datetime'),
        'loginCount' => Array ('int')
    );

    protected $timestamps = Array ('created_at', 'updated_at');
    protected $relations = Array (
        'products' => Array ("hasMany", "product", 'userid')
    );
}


?>
