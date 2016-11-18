<?php
require_once('MysqliDb.php');
error_reporting(E_ALL);
$action = 'adddb';
$data = array();

function printUsers () {
    global $db;

    $users = $db->get ("users");
    if ($db->count == 0) {
        echo "<td align=center colspan=4>No users found</td>";
        return;
    }
    foreach ($users as $u) {
        echo "<tr>
            <td>{$u['id']}</td>
            <td>{$u['login']}</td>
            <td>{$u['firstName']} {$u['lastName']}</td>
            <td>
                <a href='index.php?action=rm&id={$u['id']}'>rm</a> ::
                <a href='index.php?action=mod&id={$u['id']}'>ed</a>
            </td>
        </tr>";
    }
}

function action_adddb () {
    global $db;

    $data = Array(
        'login' => $_POST['login'],
        'customerId' => 1,
        'firstName' => $_POST['firstName'],
        'lastName' => $_POST['lastName'],
        'password' => $db->func('SHA1(?)',Array ($_POST['password'] . 'salt123')),
        'createdAt' => $db->now(),
        'expires' => $db->now('+1Y')
    );
    $id = $db->insert ('users', $data);
    header ("Location: index.php");
    exit;
}

function action_moddb () {
    global $db;

    $data = Array(
        'login' => $_POST['login'],
        'customerId' => 1,
        'firstName' => $_POST['firstName'],
        'lastName' => $_POST['lastName'],
    );
    $id = (int)$_POST['id'];
    $db->where ("customerId",1);
    $db->where ("id", $id);
    $db->update ('users', $data);
    header ("Location: index.php");
    exit;

}
function action_rm () {
    global $db;
    $id = (int)$_GET['id'];
    $db->where ("customerId",1);
    $db->where ("id", $id);
    $db->delete ('users');
    header ("Location: index.php");
    exit;

}
function action_mod () {
    global $db;
    global $data;
    global $action;

    $action = 'moddb';
    $id = (int)$_GET['id'];
    $db->where ("id", $id);
    $data = $db->getOne ("users");
}

$db = new Mysqlidb ('localhost', 'root', '', 'testdb');
if ($_GET) {
    $f = "action_".$_GET['action'];
    if (function_exists ($f)) {
        $f();
    }
}

?>
<!DOCTYPE html>

<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Users</title>
</head>
<body>

<center>
<h3>Users:</h3>
<table width='50%'>
    <tr bgcolor='#cccccc'>
        <th>ID</th>
        <th>Login</th>
        <th>Name</th>
        <th>Action</th>
    </tr>
    <?php printUsers();?>

</table>
<hr width=50%>
<form action='index.php?action=<?php echo $action?>' method=post>
    <input type=hidden name='id' value='<?php echo $data['id']?>'>
    <input type=text name='login' required placeholder='Login' value='<?php echo $data['login']?>'>
    <input type=text name='firstName' required placeholder='First Name' value='<?php echo $data['firstName']?>'>
    <input type=text name='lastName' required placeholder='Last Name' value='<?php echo $data['lastName']?>'>
    <input type=password name='password' placeholder='Password'>
    <input type=submit value='New User'></td>
</form>
</table>
</center>
</body>
</html>
