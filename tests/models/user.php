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
 * @property string createdAt
 * @property string updatedAt
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
        'createdAt' => Array ('datetime'),
        'updatedAt' => Array ('datetime'),
        'expires' => Array ('datetime'),
        'loginCount' => Array ('int')
    );

    protected $timestamps = Array ('createdAt', 'updatedAt');
    protected $relations = Array (
        'products' => Array ("hasMany", "product", 'userid')
    );
}


?>
