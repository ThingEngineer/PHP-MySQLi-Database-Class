MysqliDb -- Simple MySQLi wrapper with prepared statements
<hr>
### Table of Contents
**[Initialization](#initialization)**  
**[Insert Query](#insert-query)**  
**[Update Query](#update-query)**  
**[Select Query](#select-query)**  
**[Delete Query](#delete-query)**  
**[Generic Query](#generic-query-method)**  
**[Query Keywords](#query-keywords)**  
**[Raw Query](#raw-query-method)**  
**[Where Conditions](#where-method)**  
**[Order Conditions](#ordering-method)**  
**[Group Conditions](#grouping-method)**  
**[Properties Sharing](#properties-sharing)**  
**[Joining Tables](#join-method)**  
**[Subqueries](#subqueries)**  
**[EXISTS / NOT EXISTS condition](#exists--not-exists-condition)**  
**[Has method](#has-method)**  
**[Helper Functions](#helper-commands)**  
**[Transaction Helpers](#transaction-helpers)**  

### Installation
To utilize this class, first import MysqliDb.php into your project, and require it.

```php
require_once ('MysqliDb.php');
```

### Installation with composer
It is also possible to install library via composer
```
composer require joshcam/mysqli-database-class:dev-master
```

### Initialization
Simple initialization with utf8 charset by default:
```php
$db = new MysqliDb ('host', 'username', 'password', 'databaseName');
```
Or in case usage of the namespaces:
```php
$db = new \MysqliDb ('host', 'username', 'password', 'databaseName');
```


Advanced initialization. If no charset should be set charset, set it to null
```php
$db = new MysqliDb (Array (
                'host' => 'host',
                'username' => 'username', 
                'password' => 'password',
                'db'=> 'databaseName',
                'port' => 3306,
                'charset' => 'utf8'));
```
port and charset params are optional.

Reuse already connected mysqli:
```php
$mysqli = new mysqli ('host', 'username', 'password', 'databaseName');
$db = new MysqliDb ($mysqli);
```

Its also possible to set a table prefix:
```php
$db->setPrefix ('my_');
```

Next, prepare your data, and call the necessary methods. 

### Insert Query
Simple example
```php
$data = Array ("login" => "admin",
               "firstName" => "John",
               "lastName" => 'Doe'
);
$id = $db->insert ('users', $data);
if($id)
    echo 'user was created. Id=' . $id;
```

Insert with functions use
```php
$data = Array (
	'login' => 'admin',
    'active' => true,
	'firstName' => 'John',
	'lastName' => 'Doe',
	'password' => $db->func('SHA1(?)',Array ("secretpassword+salt")),
	// password = SHA1('secretpassword+salt')
	'createdAt' => $db->now(),
	// createdAt = NOW()
	'expires' => $db->now('+1Y')
	// expires = NOW() + interval 1 year
	// Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
);

$id = $db->insert ('users', $data);
if ($id)
    echo 'user was created. Id=' . $id;
else
    echo 'insert failed: ' . $db->getLastError();

```

### Update Query
```php
$data = Array (
	'firstName' => 'Bobby',
	'lastName' => 'Tables',
	'editCount' => $db->inc(2),
	// editCount = editCount + 2;
	'active' => $db->not()
	// active = !active;
);
$db->where ('id', 1);
if ($db->update ('users', $data))
    echo $db->count . ' records were updated';
else
    echo 'update failed: ' . $db->getLastError();
```

### Select Query
After any select/get function calls amount or returned rows
is stored in $count variable
```php
$users = $db->get('users'); //contains an Array of all users 
$users = $db->get('users', 10); //contains an Array 10 users
```

or select with custom columns set. Functions also could be used

```php
$cols = Array ("id", "name", "email");
$users = $db->get ("users", null, $cols);
if ($db->count > 0)
    foreach ($users as $user) { 
        print_r ($user);
    }
```

or select just one row

```php
$db->where ("id", 1);
$user = $db->getOne ("users");
echo $user['id'];

$stats = $db->getOne ("users", "sum(id), count(*) as cnt");
echo "total ".$stats['cnt']. "users found";
```

or select one column value or function result

```php
$count = $db->getValue ("users", "count(*)");
echo "{$count} users found";
```

### Delete Query
```php
$db->where('id', 1);
if($db->delete('users')) echo 'successfully deleted';
```

### Generic Query Method
By default rawQuery() will filter out special characters so if you getting problems with it
you might try to disable filtering function. In this case make sure that all external variables are passed to the query via bind variables

```php
// filtering enabled
$users = $db->rawQuery('SELECT * from users where customerId=?', Array (10));
// filtering disabled
//$users = $db->rawQuery('SELECT * from users where id >= ?', Array (10), false);
foreach ($users as $user) {
    print_r ($user);
}
```

More advanced examples:
```php
$params = Array(1, 'admin');
$users = $db->rawQuery("SELECT id, firstName, lastName FROM users WHERE id = ? AND login = ?", $params);
print_r($users); // contains Array of returned rows

// will handle any SQL query
$params = Array(10, 1, 10, 11, 2, 10);
$q = "(
    SELECT a FROM t1
        WHERE a = ? AND B = ?
        ORDER BY a LIMIT ?
) UNION (
    SELECT a FROM t2 
        WHERE a = ? AND B = ?
        ORDER BY a LIMIT ?
)";
$resutls = $db->rawQuery ($q, $params);
print_r ($results); // contains Array of returned rows
```


### Where Method
This method allows you to specify where parameters of the query.

WARNING: In order to use column to column comparisons only raw where conditions should be used as column name or functions cant be passed as a bind variable.

Regular == operator with variables:
```php
$db->where ('id', 1);
$db->where ('login', 'admin');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE id=1 AND login='admin';
```

Regular == operator with column to column comparison:
```php
// WRONG
$db->where ('lastLogin', 'createdAt');
// CORRECT
$db->where ('lastLogin = createdAt');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE lastLogin = createdAt;
```

```php
$db->where ('id', 50, ">=");
// or $db->where ('id', Array ('>=' => 50));
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE id >= 50;
```

BETWEEN / NOT BETWEEN:
```php
$db->where('id', Array (4, 20), 'BETWEEN');
// or $db->where ('id', Array ('BETWEEN' => Array(4, 20)));

$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id BETWEEN 4 AND 20
```

IN / NOT IN:
```php
$db->where('id', Array(1, 5, 27, -1, 'd'), 'IN');
// or $db->where('id', Array( 'IN' => Array(1, 5, 27, -1, 'd') ) );

$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id IN (1, 5, 27, -1, 'd');
```

OR CASE
```php
$db->where ('firstName', 'John');
$db->orWhere ('firstName', 'Peter');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE firstName='John' OR firstName='peter'
```

NULL comparison:
```php
$db->where ("lastName", NULL, '<=>');
$results = $db->get("users");
// Gives: SELECT * FROM users where lastName <=> NULL
```

Also you can use raw where conditions:
```php
$db->where ("id != companyId");
$db->where ("DATE(createdAt) = DATE(lastLogin)");
$results = $db->get("users");
```

Or raw condition with variables:
```php
$db->where ("(id = ? or id = ?)", Array(6,2));
$db->where ("login","mike")
$res = $db->get ("users");
// Gives: SELECT * FROM users WHERE (id = 2 or id = 2) and login='mike';
```


Find the total number of rows matched. Simple pagination example:
```php
$offset = 10;
$count = 15;
$users = $db->withTotalCount()->get('users', Array ($offset, $count));
echo "Showing {$count} from {$db->totalCount}";
```

### Query Keywords
To add LOW PRIORITY | DELAYED | HIGH PRIORITY | IGNORE and the rest of mysql keywords to INSERT , SELECT , UPDATE, DELETE query:
```php
$db->setQueryOption('LOW_PRIORITY');
$db->insert($table,$param);
// GIVES: INSERT LOW_PRIORITY INTO table ...
```

Also you can use an array of keywords:
```php
$db->setQueryOption(Array('LOW_PRIORITY', 'IGNORE'));
$db->insert($table,$param);
// GIVES: INSERT LOW_PRIORITY IGNORE INTO table ...
```

Same way keywords could be used in SELECT queries as well:
```php
$db->setQueryOption('SQL_NO_CACHE');
$db->get("users");
// GIVES: SELECT SQL_NO_CACHE * FROM USERS;
```

Optionally you can use method chaining to call where multiple times without referencing your object over an over:

```php
$results = $db
	->where('id', 1)
	->where('login', 'admin')
	->get('users');
```

### Ordering method
```php
$db->orderBy("id","asc");
$db->orderBy("login","Desc");
$db->orderBy("RAND ()");
$results = $db->get('users');
// Gives: SELECT * FROM users ORDER BY id ASC,login DESC, RAND ();
```

order by values example:
```php
$db->orderBy('userGroup', 'ASC', array('superuser', 'admin', 'users'));
$db->get('users');
// Gives: SELECT * FROM users ORDER BY FIELD (userGroup, 'superuser', 'admin', 'users') ASC;
```

### Grouping method
```php
$db->groupBy ("name");
$results = $db->get ('users');
// Gives: SELECT * FROM users GROUP BY name;
```

Join table products with table users with LEFT JOIN by tenantID
### JOIN method
```php
$db->join("users u", "p.tenantID=u.tenantID", "LEFT");
$db->where("u.id", 6);
$products = $db->get ("products p", null, "u.name, p.productName");
print_r ($products);
```

### Properties sharing
Its is also possible to copy properties

```php
$db->where ("agentId", 10);
$db->where ("active", true);

$customers = $db->copy ();
$res = $customers->get ("customers", Array (10, 10));
// SELECT * FROM customers where agentId = 10 and active = 1 limit 10, 10

$cnt = $db->getValue ("customers", "count(id)");
echo "total records found: " . $cnt;
// SELECT count(id) FROM users where agentId = 10 and active = 1
```

### Subqueries
Subquery init

Subquery init without an alias to use in inserts/updates/where Eg. (select * from users)
```php
$sq = $db->subQuery();
$sq->get ("users");
```
 
A subquery with an alias specified to use in JOINs . Eg. (select * from users) sq
```php
$sq = $db->subQuery("sq");
$sq->get ("users");
```

Subquery in selects:
```php
$ids = $db->subQuery ();
$ids->where ("qty", 2, ">");
$ids->get ("products", null, "userId");

$db->where ("id", $ids, 'in');
$res = $db->get ("users");
// Gives SELECT * FROM users WHERE id IN (SELECT userId FROM products WHERE qty > 2)
```

Subquery in inserts:
```php
$userIdQ = $db->subQuery ();
$userIdQ->where ("id", 6);
$userIdQ->getOne ("users", "name"),

$data = Array (
    "productName" => "test product",
    "userId" => $userIdQ,
    "lastUpdated" => $db->now()
);
$id = $db->insert ("products", $data);
// Gives INSERT INTO PRODUCTS (productName, userId, lastUpdated) values ("test product", (SELECT name FROM users WHERE id = 6), NOW());
```

Subquery in joins:
```php
$usersQ = $db->subQuery ("u");
$usersQ->where ("active", 1);
$usersQ->get ("users");

$db->join($usersQ, "p.userId=u.id", "LEFT");
$products = $db->get ("products p", null, "u.login, p.productName");
print_r ($products);
// SELECT u.login, p.productName FROM products p LEFT JOIN (SELECT * FROM t_users WHERE active = 1) u on p.userId=u.id;
```

###EXISTS / NOT EXISTS condition
```php
$sub = $db->subQuery();
    $sub->where("company", 'testCompany');
    $sub->get ("users", null, 'userId');
$db->where (null, $sub, 'exists');
$products = $db->get ("products");
// Gives SELECT * FROM products WHERE EXISTS (select userId from users where company='testCompany')
```

### Has method
A convenient function that returns TRUE if exists at least an element that satisfy the where condition specified calling the "where" method before this one.
```php
$db->where("user", $user);
$db->where("password", md5($password));
if($db->has("users")) {
    return "You are logged";
} else {
    return "Wrong user/password";
}
``` 
### Helper commands
Reconnect in case mysql connection died
```php
if (!$db->ping())
    $db->connect()
```

Obtain an initialized instance of the class from another class
```php
    $db = MysqliDb::getInstance();
```

Get last executed SQL query.
Please note that function returns SQL query only for debugging purposes as its execution most likely will fail due missing quotes around char variables.
```php
    $db->get('users');
    echo "Last executed query was ". $db->getLastQuery();
```

### Transaction helpers
Please keep in mind that transactions are working on innoDB tables.
Rollback transaction if insert fails:
```php
$db->startTransaction();
...
if (!$db->insert ('myTable', $insertData)) {
    //Error while saving, cancel new record
    $db->rollback();
} else {
    //OK
    $db->commit();
}
```

### Query exectution time benchmarking
To track query execution time setTrace() function should be called.
```php
$db->setTrace (true);
// As a second parameter it is possible to define prefix of the path which should be striped from filename
// $db->setTrace (true, $_SERVER['SERVER_ROOT']);
$db->get("users");
$db->get("test");
print_r ($db->trace);
```

```
    [0] => Array
        (
            [0] => SELECT  * FROM t_users ORDER BY `id` ASC
            [1] => 0.0010669231414795
            [2] => MysqliDb->get() >>  file "/avb/work/PHP-MySQLi-Database-Class/tests.php" line #151
        )

    [1] => Array
        (
            [0] => SELECT  * FROM t_test
            [1] => 0.00069189071655273
            [2] => MysqliDb->get() >>  file "/avb/work/PHP-MySQLi-Database-Class/tests.php" line #152
        )

```
