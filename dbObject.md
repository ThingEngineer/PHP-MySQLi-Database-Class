dbObject - model implementation on top of the MysqliDb
Please note, that this library is not pretending to be a full stack ORM but a simple OOP wrapper for mysqlidb

<hr>
**[Initialization] (#initialization)**
1. Include mysqlidb and dbObject classes. 
2. If you want to use model autoloading instead of manually including them in the scripts use autoload () method.

```php
require_once ("libs/MysqliDb.php");
require_once ("libs/dbObject.php");

// db instance
$db = new Mysqlidb ('localhost', 'user', '', 'testdb');
// enable class autoloading
dbObject::autoload ("models");

```
2. Create simple user class (models/user.php):
```php
class user extends dbObject {
  protected $dbTable = "users";
  protected $primaryKey = "id";
  protected $dbFields = Array (
    'login' => Array ('text', 'required'),
    'password' => Array ('text'),
    'createdAt' => Array ('datetime'),
    'updatedAt' => Array ('datetime'),
  );
}
```
**[Insert Row]**
1. OOP Way. Just create new object of a needed class, fill it in and call save () method. Save will return 
record id in case of success and false in case if insert will fail.
```php
$user = new user;
$user->login = 'demo';
$user->password = 'demo';
$id = $user->save ();
if ($id)
  echo "user created with id = " . $id;
```
2. Using arrays
```php
$data = Array ('login' => 'demo',
        'password' => 'demo');
$user = new user ($data);
$id = $user->save ();
if ($id == null) {
    print_r ($user->errors);
    echo $db->getLastError;
} else
    echo "user created with id = " . $id;
```

2. Multisave
```php
$user = new user;
$user->login = 'demo';
$user->pass = 'demo';

$p = new product;
$p->title = "Apples";
$p->price = 0.5;
$p->seller = $user;
$p->save ();
```

After save() is call both new objects (user and product) will be saved.

**[Selects]**
Retrieving objects from the database is pretty much the same process of a get ()/getOne () execution without a need to specify table name.
All mysqlidb functions like where(), orWhere(), orderBy(), join etc are supported.
Please note that objects returned with join() will not save changes to a joined properties. For this you can use relationships.

Select row by primary key
```php
$user = user::byId (1);
echo $user->login;
```

Get all users
```php
$users = user::orderBy ('id')->get ();
foreach (users as $u) {
  echo $u->login;
}
```

Using where with limit
```php
$users = user::where ("login", "demo")->get (Array (10, 20));
foreach (users as $u) ...
```

**[Update]**
To update model properties just set them and call save () method. As well values that needed to by changed could be passed as an array to the save () method.

```php
$user = user::byId (1);
$user->password = 'demo2';
$user->save ();
```
```php
$data = Array ('password', 'demo2');
$user = user::byId (1);
$user->save ($data);
```

**[Delete]**
Use delete() method on any loaded object. 
```php
$user = user::byId (1);
$user->delete ();
```

**[Relations]**
Currently dbObject supports only hasMany and hasOne relations only. To use them declare $relations array in the model class like:
```php
    protected $relations = Array (
        'person' => Array ("hasOne", "person", 'id');
        'products' => Array ("hasMany", "product", 'userid')
    );
```
After that you can get related object via variables and do their modification/removal/display:
```php
    $user = user::byId (1);
    // sql: select * from $persontable where id = $personValue
    echo $user->person->firstName . " " . $user->person->lastName . " have the following products:\n";
    // sql: select * from $product_table where userid = $userPrimaryKey
    foreach ($user->products as $p) {
            echo $p->title;
    }
```
**[Error checking]**
TBD
**[Validation]**
TBD
**[Array as return values]**
TBD
**[2array and 2json]**
TBD


