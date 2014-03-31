<?php
require_once ("MysqliDb.php");
error_reporting(E_ALL);

$db = new Mysqlidb('localhost', 'root', '', 'testdb');
if(!$db) die("Database error");

$tables = Array (
    'users' => Array (
        'login' => 'char(10) not null',
        'customerId' => 'int(10) not null',
        'firstName' => 'char(10) not null',
        'lastName' => 'char(10) not null',
        'password' => 'text not null',
        'createdAt' => 'datetime',
        'expires' => 'datetime',
        'loginCount' => 'int(10) default 0'
    )
);
$data = Array (
    Array ('login' => 'user1',
           'customerId' => 10,
           'firstName' => 'John',
           'lastName' => 'Doe',
           'password' => $db->func('SHA1(?)',Array ("secretpassword+salt")),
           'createdAt' => $db->now(),
           'expires' => $db->now('+1Y'),
           'loginCount' => $db->inc()
    ),
    Array ('login' => 'user2',
           'customerId' => 10,
           'firstName' => 'Mike',
           'lastName' => 'B',
           'password' => $db->func('SHA1(?)',Array ("secretpassword2+salt")),
           'createdAt' => $db->now(),
           'expires' => $db->now('+1Y'),
           'loginCount' => $db->inc(2)
    ),
    Array ('login' => 'user3',
           'customerId' => 11,
           'firstName' => 'Pete',
           'lastName' => 'D',
           'password' => $db->func('SHA1(?)',Array ("secretpassword2+salt")),
           'createdAt' => $db->now(),
           'expires' => $db->now('+1Y'),
           'loginCount' => $db->inc(3)
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

foreach ($tables as $name => $fields) {
    $db->rawQuery("DROP TABLE $name");
    createTable ($name, $fields);
}

foreach ($data as $d) {
    $id = $db->insert("users", $d);
    if ($id)
        $d['id'] = $id;
    else {
        echo "failed to insert: ".$db->getLastQuery() ."\n". $db->getLastError();
    }
}

$db->orderBy("id","asc");
$users = $db->get("users");
if ($db->count != 3) {
    echo "Invalid total insert count";
    exit;
}
// TODO
//$db->where("createdAt", Array (">" => $db->interval("-1h")));
//$users = $db->get("users");
//print_r ($users);

$db->where("firstname", Array("LIKE" => '%John%'));
$users = $db->get("users");
if ($db->count != 1) {
    echo "Invalid insert count in LIKE: ".$db->count;
    print_r ($users);
    echo $db->getLastQuery();
    exit;
}
// FIXME ADD IN and BETWEEN CHECKS

$db->groupBy("customerId");
$cnt = $db->get ("users", null, "customerId, count(id) as cnt");
if ($db->count != 2) {
    echo "Invalid records count with group by";
}


$upData = Array (
    'expires' => $db->now("+5M","expires"),
    'loginCount' => $db->inc()
);
$db->where ("id", 1);
$cnt = $db->update("users", $upData);
echo "all done\n";

$db->where ("id", 1);
$db->getOne("users");
echo "cnt=".$db->count;

//print_r($db->rawQuery("CALL simpleproc(?)",Array("test")));

?>
