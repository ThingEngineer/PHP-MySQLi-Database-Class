<?php
// TEST HAS BEEN DONE USING PHP 7.0
// Basic Stuff
error_reporting (E_ALL|E_STRICT);
require_once ("../../MysqliDb.php");
require_once ("../../dbObject.php");
// Create database context
$db = new MysqliDb ('localdev', 'noneatme', '12345', 'db_test');

// Import CSV
echo "Testing: File Not Found", PHP_EOL;
// File not Found
goto test_filenotfound;
// File Not Found Test
test_filenotfound:
try
{
	// It should throw an exception
	$db->loadData("users", "datanew.csv");
}
catch(Exception $e)
{
	echo "Test 1 Succeeded!", PHP_EOL;
	// goto newtest
	goto test_import1;
}
test_import1:
{
	try
	{
		// Import the CSV
		$db->loadData("users", 	// Table
					  "D:\\DEV\\git\\PHP-MySQLi-Database-Class\\tests\\dataimport\\data.csv",
					  Array("fieldEnclosure" => '', "lineStarting" => ''));
		echo "Test 2 Succeeded!", PHP_EOL;
		
		goto test_import2;
	}
	catch(Exception $e)
	{
		echo($e);
	}
}
test_import2:
{
	try
	{
		$db->setLockMethod("WRITE")->lock(array("users", "log"));
		
		$db->loadXML("users",
					 "D:\\DEV\\git\\PHP-MySQLi-Database-Class\\tests\\dataimport\\data.xml");
			echo "Test 3 Succeeded!", PHP_EOL;
		
		$db->unlock();

	}
	catch(Exception $e)
	{
		echo($e);
	}
}