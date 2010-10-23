<?php
require_once('MysqliDb.php');

$db = new MysqliDb('localhost', 'root', 'root', 'db');

?>
<!DOCTYPE html>

<html lang="en">
<head>
   <meta charset="utf-8">
   <title>untitled</title>
</head>
<body>
	
<?php
$insertData = array(
   'title' => 'Inserted title',
    'body' => 'Inserted body'
);

$results = $db->insert('posts', $insertData);
print_r($results);
?>

</body>
</html>