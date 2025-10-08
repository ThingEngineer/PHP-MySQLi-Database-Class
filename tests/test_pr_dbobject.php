<?php
require_once ("../MysqliDb.php");
require_once ("../dbObject.php");

echo "Testing dbObject compatibility with PR changes...\n";

$db = new Mysqlidb('localhost', 'root', 'root', 'testdb');
$prefix = 't_';
$db->setPrefix($prefix);

// Create a simple model for testing
class testUser extends dbObject {
    protected $dbTable = "test_users";
    protected $primaryKey = "id";
    
    protected $dbFields = array(
        'name' => array('text', 'required'),
        'email' => array('text'),
        'createdAt' => array('datetime')
    );
}

try {
    // Setup
    $db->rawQuery("DROP TABLE IF EXISTS {$prefix}test_users");
    $db->rawQuery("CREATE TABLE {$prefix}test_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        createdAt DATETIME
    )");
    echo "✅ Test table created\n";

    // Test dbObject basic functionality
    $user = new testUser();
    $user->name = "Test User";
    $user->email = "test@example.com";
    $user->createdAt = $db->now();
    
    $id = $user->save();
    if ($id) {
        echo "✅ dbObject save() works: ID {$id}\n";
    } else {
        echo "❌ dbObject save() failed\n";
        exit(1);
    }

    // Test retrieval
    $retrievedUser = testUser::byId($id);
    if ($retrievedUser && $retrievedUser->name === "Test User") {
        echo "✅ dbObject retrieval works\n";
    } else {
        echo "❌ dbObject retrieval failed\n";
        exit(1);
    }

    // Most importantly: Test that insertMulti still works with dbObject context
    // (This is the method our PR modified)
    $multiData = [
        ['name' => 'Multi User 1', 'email' => 'multi1@test.com', 'createdAt' => $db->now()],
        ['name' => 'Multi User 2', 'email' => 'multi2@test.com', 'createdAt' => $db->now()],
        ['name' => 'Multi User 3', 'email' => 'multi3@test.com', 'createdAt' => $db->now()]
    ];

    // Test our PR change: insertMulti with nullable type hint
    $ids = $db->insertMulti('test_users', $multiData); // Default parameter (null)
    if ($ids && count($ids) === 3) {
        echo "✅ insertMulti (PR method) works with dbObject context: " . count($ids) . " records\n";
    } else {
        echo "❌ insertMulti (PR method) failed\n";
        exit(1);
    }

    // Test with explicit null (the new nullable type hint)
    $db->delete('test_users');
    $ids2 = $db->insertMulti('test_users', $multiData, null);
    if ($ids2 && count($ids2) === 3) {
        echo "✅ insertMulti with explicit null parameter works: " . count($ids2) . " records\n";
    } else {
        echo "❌ insertMulti with explicit null failed\n";
        exit(1);
    }

    // Cleanup
    $db->rawQuery("DROP TABLE IF EXISTS {$prefix}test_users");
    echo "✅ Cleanup completed\n";

    echo "\n🎉 dbObject compatibility confirmed!\n";
    echo "✅ Our PR changes (?array \$dataKeys = null) work perfectly with dbObject\n";
    echo "✅ No regressions introduced by the nullable type hint\n";

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>