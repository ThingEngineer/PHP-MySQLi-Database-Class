<?php
require_once('MysqlDb.php');

$Db = new MysqlDb('localhost', 'root', 'root', 'db');

$insertData = array(
   'title' => 'Inserted title',
    'body' => 'Inserted body'
);


$results = $Db->insert('posts', $insertData);
print_r($results);

?>
<!DOCTYPE html>

<html lang="en">
<head>
   <meta charset="utf-8">
   <title>untitled</title>
</head>
<body>

</body>
</html>