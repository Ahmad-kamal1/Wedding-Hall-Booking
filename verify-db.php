<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Check users table has status column
echo "=== Database Verification ===\n\n";

$result = $db->query('DESCRIBE users');
$cols = $result->fetchAll(PDO::FETCH_COLUMN, 0);
echo "Users table columns: " . implode(', ', $cols) . "\n";

// Check for status column specifically
if (in_array('status', $cols)) {
    echo "✓ Status column exists in users table\n";
} else {
    echo "✗ Status column NOT found in users table\n";
}

// Check settings table exists
try {
    $result = $db->query('SELECT COUNT(*) FROM settings');
    $count = $result->fetchColumn();
    echo "✓ Settings table: EXISTS ($count records)\n";
} catch(Exception $e) {
    echo "✗ Settings table: MISSING\n";
}

// Check event_categories table exists
try {
    $result = $db->query('SELECT COUNT(*) FROM event_categories');
    $count = $result->fetchColumn();
    echo "✓ Event categories table: EXISTS ($count records)\n";
} catch(Exception $e) {
    echo "✗ Event categories table: MISSING\n";
}

echo "\n=== Test Queries ===\n\n";

// Test the statistics queries from admin-customers.php
try {
    $total = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Total users: $total\n";
    
    $active = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    echo "Active users: $active\n";
    
    $new = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    echo "New users (30 days): $new\n";
    
    echo "\n✓ All queries executed successfully!\n";
} catch(Exception $e) {
    echo "✗ Query error: " . $e->getMessage() . "\n";
}
?>
