<?
require_once ("../MysqliDb.php");
require_once ("../dbObject.php");
require_once ("models/department.php");

$db = new Mysqlidb('localhost', 'root', '', 'akorbi');

$dept4 = department::ArrayBuilder()->join('user')->get(2);
echo json_encode ($dept4);

$dept = new department();
$dept->userid = 10;
$dept->name = 'avb test';
$dept->authcode = Array('1234','123456');
$dept->iscallerid = 1;
$dept->insert();

$dept2 = new department([
        'userid' => '11',
        'name' => 'john doe',
        'authcode' => '5678',
        'iscallerid' => 0,
]);
$dept2->save();
$dept2->iscallerid=1;
print_r ($dept2->data);
$dept2->save();

echo "List\n";
$depts = department::get ();
foreach ($depts as $d) {
//    print_r ($d->data);
    echo $d . "\n";
}

echo "getOne\n";
$dept3 = department::byId ("181");
echo 'cnt ' . $dept3->count . "\n";
$dept3->authcode=333;
$dept3->save();
print_r ($dept3->data) . "\n";


echo "hasOne\n";
echo json_encode ($dept3->userid->data);

echo "\nhasMany\n";
foreach ($dept3->userid->departments as $d) {
        echo $d;
}


?>
