<?php
error_reporting (E_ALL|E_STRICT);
require_once ("../MysqliDb.php");
require_once ("../dbObject.php");

$db = new Mysqlidb('localhost', 'root', '', 'testdb');
$prefix = 't_';
$db->setPrefix($prefix);
dbObject::autoload ("models");

$tables = Array (
    'users' => Array (
        'login' => 'char(10) not null',
        'active' => 'bool default 0',
        'customerId' => 'int(10) not null',
        'firstName' => 'char(10) not null',
        'lastName' => 'char(10)',
        'password' => 'text not null',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'expires' => 'datetime',
        'loginCount' => 'int(10) default 0'
    ),
    'products' => Array (
        'customerId' => 'int(10) not null',
        'userId' => 'int(10) not null',
        'productName' => 'char(50)'
    )
);

$data = Array (
    'user' => Array (
        Array ('login' => 'user1',
               'customerId' => 10,
               'firstName' => 'John',
               'lastName' => 'Doe',
               'password' => $db->func('SHA1(?)',Array ("secretpassword+salt")),
               'expires' => $db->now('+1Y'),
               'loginCount' => $db->inc()
        ),
        Array ('login' => 'user2',
               'customerId' => 10,
               'firstName' => 'Mike',
               'lastName' => NULL,
               'password' => $db->func('SHA1(?)',Array ("secretpassword2+salt")),
               'expires' => $db->now('+1Y'),
               'loginCount' => $db->inc(2)
        ),
        Array ('login' => 'user3',
               'active' => true,
               'customerId' => 11,
               'firstName' => 'Pete',
               'lastName' => 'D',
               'password' => $db->func('SHA1(?)',Array ("secretpassword2+salt")),
               'expires' => $db->now('+1Y'),
               'loginCount' => $db->inc(3)
        )
    ),
    'product' => Array (
        Array ('customerId' => 1,
               'userId' => 1,
               'productName' => 'product1',
        ),
        Array ('customerId' => 1,
               'userId' => 1,
               'productName' => 'product2',
        ),
        Array ('customerId' => 1,
               'userId' => 1,
               'productName' => 'product3',
        ),
        Array ('customerId' => 1,
               'userId' => 2,
               'productName' => 'product4',
        ),
        Array ('customerId' => 1,
               'userId' => 2,
               'productName' => 'product5',
        ),

    )
);
function createTable ($name, $data) {
    global $db;
    //$q = "CREATE TABLE $name (id INT(9) UNSIGNED PRIMARY KEY NOT NULL";
    $q = "CREATE TABLE $name (id INT(9) UNSIGNED PRIMARY KEY AUTO_INCREMENT";
    foreach ($data as $k => $v) {
        $q .= ", $k $v";
    }
    $q .= ")";
    $db->rawQuery($q);
}

// rawQuery test
foreach ($tables as $name => $fields) {
    $db->rawQuery("DROP TABLE " . $prefix . $name);
    createTable ($prefix . $name, $fields);
}

foreach ($data as $name => $datas) {
    foreach ($data[$name] as $userData) {
        $obj = new $name ($userData);
        $id  = $obj->save();
        if ($obj->errors) {
            echo "errors:";
            print_r ($obj->errors);
            exit;
        }
    }
}

$products = product::ArrayBuilder()->get(2);
foreach ($products as $p) {
    if (!is_array ($p)) {
        echo "ArrayBuilder do not return an array\n";
        exit;
    }
}

$product = product::ArrayBuilder()->with('userId')->byId(5);
if (!is_array ($product['userId'])) {
    echo "Error in with processing in getOne";
    exit;
}

$product = product::with('userId')->byId(5);
if (!is_object ($product->data['userId'])) {
    echo "Error in with processing in getOne object";
    exit;
}

$product = product::with('user')->byId(5);
if (!is_object ($product->data['user'])) {
    echo "Error in with processing in getOne object";
    exit;
}

$products = product::ArrayBuilder()->with('userId')->get(2);
if (!is_array ($products[0]['userId']) || !is_array ($products[1]['userId'])) {
    echo "Error in with processing in get";
    exit;
}

$products = product::with('userId')->ArrayBuilder()->get(2);
if (!is_array ($products[0]['userId']) || !is_array ($products[1]['userId'])) {
    echo "Error in with processing in get";
    exit;
}

$depts = product::join('user')->orderBy('`products`.id', 'desc')->get(5);
foreach ($depts as $d) {
    if (!is_object($d)) {
        echo "Return should be an object\n";
        exit;
    }
}

$dept = product::join('user')->byId(5);
if (count ($dept->data) != 13) {
    echo "wrong props count " .count ($dept->data). "\n";
    exit;
}
if ($db->count != 1) {
    echo "wrong count after byId\n";
    exit;
}

// hasOne
$products = product::get ();
$cnt = 0;
foreach ($products as $p) {
    if (get_class ($d) != 'product') {
        echo "wrong class returned\n";
        exit;
    }

    if (!($p->userId instanceof user)) {
        echo "wrong return class of hasOne result\n";
        exit;
    }
    
    $cnt++;
}

if (($cnt != $db->count) && ($cnt != 5)) {
    echo "wrong count after get\n";
    exit;
}

// hasMany
$user = user::where('id',1)->getOne();
if (!is_array ($user->products) || (count ($user->products) != 3)) {
    echo "wrong count in hasMany\n";
    exit;
}

foreach ($user->products as $p) {
    if (!($p instanceof product)) {
        echo "wrong return class of hasMany result\n";
        exit;
    }
}

// multi save
$client = new user;
$client->login = 'testuser';
$client->firstName = 'john';
$client->lastName = 'Doe Jr';

$obj = new product;
$obj->customerId = 2;
$obj->userId = 2;
$obj->productName = "product6";
$obj->save();

$obj->userId = 5;
$obj->save();

$obj->userId = $client;
$obj->save();
if ($client->errors) {
    echo "errors:";
    print_r ($client->errors);
    exit;
}

$expected = '{"customerId":2,"userId":{"id":4,"login":"testuser","active":0,"customerId":0,"firstName":"john","lastName":"Doe Jr","password":"","createdAt":"' .$client->createdAt. '","updatedAt":null,"expires":null,"loginCount":0},"productName":"product6","id":6}';

if ($obj->with('userId')->toJson() != $expected) {
    echo "Multisave problem\n";
    echo $obj->with('userId')->toJson();
    exit;
}

$u= new user;
$u->active='test';
$u->customerId = 'test';
$u->expires = 'test;';
$u->firstName = 'test';

$obj = new product;
$obj->userId = $u;
$obj->save();
if ($obj->save()) {
    echo "validation 1 failed\n";
    exit;
}
if (count ($obj->errors) != 7) {
    print_r ($obj->errors);
    echo "validation 2 failed\n";
    exit;
}

if (!user::byId(1) instanceof user)
    echo "wrong return type1";

if (!is_array (user::ArrayBuilder()->byId(1)))
    echo "wrong return type2";

if (!is_array (product::join('user')->orderBy('`products`.id', 'desc')->get(2)))
    echo "wrong return type2";

if (!is_array (product::orderBy('`products`.id', 'desc')->join('user')->get(2)))
    echo "wrong return type2";

$u = new user;
if (!$u->byId(1) instanceof user)
    echo "wrong return type2";

$p = new product;
if (!is_array ($p->join('user')->orderBy('`products`.id', 'desc')->get(2)))
    echo "wrong return type2";

if (!is_array ($p->orderBy('`products`.id', 'desc')->join('user')->get(2)))
    echo "wrong return type2";


$json = user::jsonBuilder()->get(null, "id, login");
if ($json != '[{"id":1,"login":"user1"},{"id":2,"login":"user2"},{"id":3,"login":"user3"},{"id":4,"login":"testuser"}]') {
    echo "jsonbuilder fail";
    exit;
}

echo "All done";
?>
