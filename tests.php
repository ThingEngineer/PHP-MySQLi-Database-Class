<?php
require_once ("MysqliDb.php");
error_reporting(E_ALL);

$db = new Mysqlidb('localhost', 'root', '', 'testdb');
if(!$db) die("Database error");

$mysqli = new mysqli ('localhost', 'root', '', 'testdb');
$db = new Mysqlidb($mysqli);

$db = new Mysqlidb(Array (
                'host' => 'localhost',
                'username' => 'root', 
                'password' => '',
                'db'=> 'testdb',
                'charset' => null));
if(!$db) die("Database error");


$prefix = 't_';
$db->setPrefix($prefix);
$db->setTrace(true);

$tables = Array (
    'users' => Array (
        'login' => 'char(10) not null',
        'active' => 'bool default 0',
        'customerId' => 'int(10) not null',
        'firstName' => 'char(10) not null',
        'lastName' => 'char(10)',
        'password' => 'text not null',
        'createdAt' => 'datetime',
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
    'users' => Array (
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
               'lastName' => NULL,
               'password' => $db->func('SHA1(?)',Array ("secretpassword2+salt")),
               'createdAt' => $db->now(),
               'expires' => $db->now('+1Y'),
               'loginCount' => $db->inc(2)
        ),
        Array ('login' => 'user3',
               'active' => true,
               'customerId' => 11,
               'firstName' => 'Pete',
               'lastName' => 'D',
               'password' => $db->func('SHA1(?)',Array ("secretpassword2+salt")),
               'createdAt' => $db->now(),
               'expires' => $db->now('+1Y'),
               'loginCount' => $db->inc(3)
        )
    ),
    'products' => Array (
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
    $db->rawQuery("DROP TABLE ".$prefix.$name);
    createTable ($prefix.$name, $fields);
}

// insert test with autoincrement
foreach ($data as $name => $datas) {
    foreach ($datas as $d) {
        $id = $db->insert($name, $d);
        if ($id)
            $d['id'] = $id;
        else {
            echo "failed to insert: ".$db->getLastQuery() ."\n". $db->getLastError();
            exit;
        }
    }
}

// bad insert test
$badUser = Array ('login' => null,
               'customerId' => 10,
               'firstName' => 'John',
               'lastName' => 'Doe',
               'password' => 'test',
               'createdAt' => $db->now(),
               'expires' => $db->now('+1Y'),
               'loginCount' => $db->inc()
        );
$id = $db->insert ("users", $badUser);
if ($id) {
    echo "bad insert test failed";
    exit;
}

// insert without autoincrement
$q = "create table {$prefix}test (id int(10), name varchar(10));";
$db->rawQuery($q);
$id = $db->insert ("test", Array ("id" => 1, "name" => "testname"));
if (!$id) {
    echo "insert without autoincrement failed";
    exit;
}
$db->get("test");
if ($db->count != 1) {
    echo "insert without autoincrement failed -- wrong insert count";
    exit;
}

$q = "drop table {$prefix}test;";
$db->rawQuery($q);


$db->orderBy("`id`","asc");
$users = $db->get("users");
if ($db->count != 3) {
    echo "Invalid total insert count";
    exit;
}

// order by field
$db->orderBy("login","asc", Array ("user3","user2","user1"));
$login = $db->getValue ("users", "login");
if ($login != "user3") {
    echo "order by field test failed";
    exit;
}

$db->where ("active", true);
$users = $db->get("users");
if ($db->count != 1) {
    echo "Invalid total insert count with boolean";
    exit;
}

$db->where ("active", false);
$db->update("users", Array ("active" => $db->not()));
if ($db->count != 2) {
    echo "Invalid update count with not()";
    exit;
}

$db->where ("active", true);
$users = $db->get("users");
if ($db->count != 3) {
    echo "Invalid total insert count with boolean";
    exit;
}

$db->where ("active", true);
$users = $db->get("users", 2);
if ($db->count != 2) {
    echo "Invalid total insert count with boolean";
    exit;
}

// TODO
//$db->where("createdAt", Array (">" => $db->interval("-1h")));
//$users = $db->get("users");
//print_r ($users);

$db->where("firstname", Array ('LIKE' => '%John%'));
$users = $db->get("users");
if ($db->count != 1) {
    echo "Invalid insert count in LIKE: ".$db->count;
    print_r ($users);
    echo $db->getLastQuery();
    exit;
}

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
if ($db->count != 1) {
    echo "Invalid update count with functions";
    exit;
}


$db->where ("id", 1);
$r = $db->getOne("users");
if ($db->count != 1) {
    echo "Invalid users count on getOne()";
    exit;
}
if ($r['password'] != '546f98b24edfdc3b9bbe0d241bd8b29783f71b32') {
    echo "Invalid password were set".
    exit;
}

$db->where ("id", Array('1','2','3'), 'IN');
$db->get("users");
if ($db->count != 3) {
    echo "Invalid users count on where() with in ";
    exit;
}

$db->where ("id", Array('2','3'), 'between');
$db->get("users");
if ($db->count != 2) {
    echo "Invalid users count on where() with between";
    exit;
}
///
$db->where ("id", 2);
$db->orWhere ("customerId", 11);
$r = $db->get("users");
if ($db->count != 2) {
    echo "Invalid users count on orWhere()";
    exit;
}
///
$db->where ("lastName", NULL, '<=>');
$r = $db->get("users");
if ($db->count != 1) {
    echo "Invalid users count on null where()";
    exit;
}
///
$db->join("users u", "p.userId=u.id", "LEFT");
$db->where("u.login",'user2');
$db->orderBy("CONCAT(u.login, u.firstName)");
$products = $db->get ("products p", null, "u.login, p.productName");
if ($db->count != 2) {
    echo "Invalid products count on join ()";
    exit;
}
///
$db->where("id = ? or id = ?", Array(1,2));
$res = $db->get ("users");
if ($db->count != 2) {
    echo "Invalid users count on select with multiple params";
    exit;
}

///
$db->where("id = 1 or id = 2");
$res = $db->get ("users");
if ($db->count != 2) {
    echo "Invalid users count on select with multiple params";
    exit;
}
///
$usersQ = $db->subQuery();
$usersQ->where ("login", "user2");
$usersQ->getOne ("users", "id");

$db->where ("userId", $usersQ);
$cnt = $db->getValue ("products", "count(id)");
if ($cnt != 2) {
    echo "Invalid select result with subquery";
    exit;
}
///
$dbi_sub = $db->subQuery();
$dbi_sub->where ('active', 1);
$dbi_sub->get ('users', null, 'id');

$db->where ('id', $dbi_sub, 'IN');

$cnt = $db->copy();
$c = $cnt->getValue ('users', "COUNT(id)");
if ($c != 3) {
    echo "copy with subquery count failed";
    exit;
}
$data = $db->get('users');
if (count($data) != 3) {
    echo "copy with subquery data count failed";
    exit;
}
///
$usersQ = $db->subQuery ("u");
$usersQ->where ("active", 1);
$usersQ->get("users");

$db->join($usersQ, "p.userId=u.id", "LEFT");
$products = $db->get ("products p", null, "u.login, p.productName");
if ($products[2]['login'] != 'user1' || $products[2]['productName'] != 'product3') {
    echo "invalid join with subquery";
    exit;
}
if ($db->count != 5) {
    echo "invalid join with subquery count";
    exit;
}

$db->withTotalCount()->get('users', 1);
if ($db->totalCount != 3) {
    echo "error in totalCount";
    exit;
}
///
//TODO: insert test
$db->delete("users");
$db->get("users");
if ($db->count != 0) {
    echo "Invalid users count after delete"; 
    exit;
}
$db->delete("products");

echo "All done";

//print_r($db->rawQuery("CALL simpleproc(?)",Array("test")));

print_r ($db->trace);
?>
