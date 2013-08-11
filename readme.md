To utilize this class, first import Mysqldbi.php into your project, and require it.

```php
require_once('Mysqlidb.php');
```

After that, create a new instance of the class.

```php
$db = new Mysqlidb('host', 'username', 'password', 'databaseName');
```

Next, prepare your data, and call the necessary methods. 

### Insert Query

```php
$insertData = array(
	'title' => 'Inserted title',
	'body' => 'Inserted body'
);

if($db->insert('posts', $insertData)) echo 'success!';
```

### Select Query

```php
$results = $db->get('tableName', 'numberOfRows-optional');
print_r($results); // contains array of returned rows
```

### Update Query

```php
$updateData = array(
	'fieldOne' => 'fieldValue',
	'fieldTwo' => 'fieldValue'
);
$db->where('id', int);
$results = $db->update('tableName', $updateData);
```

### Delete Query

```php
$db->where('id', int);
if($db->delete('posts')) echo 'successfully deleted'; 
```

### Generic Query Method

```php
$results = $db->query('SELECT * from posts');
print_r($results); // contains array of returned rows
```

### Raw Query Method

```php
$params = array(3, 'My Title');
$resutls = $db->rawQuery("SELECT id, title, body FROM posts WHERE id = ? AND tile = ?", $params);
print_r($results); // contains array of returned rows

// will handle any SQL query

$params = array(10, 1, 10, 11, 2, 10);
$resutls = $db->rawQuery("(SELECT a FROM t1 WHERE a = ? AND B = ? ORDER BY a LIMIT ?) UNION(SELECT a FROM t2 WHERE a = ? AND B = ? ORDER BY a LIMIT ?)", $params);
print_r($results); // contains array of returned rows
```


### Where Method
This method allows you to specify the parameters of the query.

Regular == operator:
```php
$db->where('id', int);
$db->where('title', string);
$results = $db->get('tableName');
print_r($results); // contains array of returned rows
```

Custom Operators:
```php
$db->where( 'id', array( '>=' => 50 ) );
$results = $db->get('tableName');
// Gives: SELECT * FROM tableName WHERE id >= ?
```

BETWEEN:
```php
$db->where( 'id', array( 'between' => array(4, 20) ) );
$results = $db->get('tableName');
// Gives: SELECT * FROM tableName WHERE id BETWEEN ? AND ?
```

IN:
``php
$db->where( 'id', array( 'in' => array(1, 5, 27, -1, 'd') ) );
$results = $db->get('tableName');
// Gives: SELECT * FROM tableName WHERE id IN ( ?, ?, ?, ?, ? )
```

Optionally you can use method chaining to call where multiple times without referencing your object over an over:

```php
$results = $db
	->where('id', 1)
	->where('title', 'MyTitle')
	->get('tableName');
```
