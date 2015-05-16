<?
require_once ("../MysqliDb.php");
require_once ("models/department.php");

$db = new Mysqlidb('localhost', 'root', '', 'akorbi');

$dept = new department();
$dept->userid = 10;
$dept->name = 'avb test';
$dept->authcode = Array('1234','123456');
$dept->iscallerid = 1;
$dept->insert();

$dept2 = new department([
        'userid' => '11',
        'name' => 'avb2 test',
        'authcode' => '5678',
        'iscallerid' => 0,
]);
$dept2->save();
$dept2->iscallerid=1;
print_r ($dept2->data);
$dept2->save();

//echo $db->getLastQuery();

echo "List\n";
$depts = department::ObjectBuilder()->last()->get ();
foreach ($depts as $d) {
//    print_r ($d->data);
    echo $d . "\n";
}

echo "getOne\n";
$dept3 = department::ObjectBuilder()->byId ("181");
$dept3->authcode=333;
$dept3->save();
print_r ($dept3->data) . "\n";

echo $dept3->count;
print_r ($dept3->trace);

echo $dept3->qqq;


?>
