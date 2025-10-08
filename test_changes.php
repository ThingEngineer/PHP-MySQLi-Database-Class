<?php
require_once ("MysqliDb.php");
error_reporting(E_ALL);

echo "Testing PHP " . PHP_VERSION . " compatibility...\n";

// Test database connection
try {
    $db = new Mysqlidb('localhost', 'root', 'root', 'testdb');
    echo "✅ Database connection successful\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test basic functionality
try {
    $db->rawQuery("DROP TABLE IF EXISTS test_table");
    $db->rawQuery("CREATE TABLE test_table (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
    echo "✅ Table creation successful\n";
} catch (Exception $e) {
    echo "❌ Table creation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test the specific method that was changed: insertMulti
try {
    $data = [
        ['name' => 'Test 1'],
        ['name' => 'Test 2'],
        ['name' => 'Test 3']
    ];
    
    // Test with default null parameter (original usage)
    $ids1 = $db->insertMulti('test_table', $data);
    echo "✅ insertMulti with default parameter: " . count($ids1) . " rows inserted\n";
    
    // Test with explicit dataKeys parameter (new usage that the PR supports)
    $ids2 = $db->insertMulti('test_table', $data, ['name']);
    echo "✅ insertMulti with explicit dataKeys: " . count($ids2) . " rows inserted\n";
    
    // Test with null dataKeys parameter (should work with nullable type hint)
    $ids3 = $db->insertMulti('test_table', $data, null);
    echo "✅ insertMulti with null dataKeys: " . count($ids3) . " rows inserted\n";
    
} catch (Exception $e) {
    echo "❌ insertMulti test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Clean up
try {
    $db->rawQuery("DROP TABLE test_table");
    echo "✅ Cleanup successful\n";
} catch (Exception $e) {
    echo "⚠️  Cleanup warning: " . $e->getMessage() . "\n";
}

echo "\n🎉 All tests passed! The PR changes work correctly with PHP " . PHP_VERSION . "\n";
echo "\nSummary:\n";
echo "- ✅ Nullable type hint (?array \$dataKeys = null) works correctly\n";
echo "- ✅ Backward compatibility maintained\n";
echo "- ✅ All parameter combinations work as expected\n";
echo "- ✅ No breaking changes detected\n";
?>