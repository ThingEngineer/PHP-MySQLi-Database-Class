<?
require_once ("../MysqliDb.php");
require_once ("models/accData.php");

$db = new Mysqlidb('localhost', 'root', '', 'akorbi');

$accData = new accData();
$d = $accData->getOne(1288);
print_r ($d);

print_r ($accData->last()->get());

//$a = new accData;



?>
