<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Testing Event Categories</h2>";

try {
    $event_categories = $db->query("SELECT * FROM event_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Total categories found: " . count($event_categories) . "</p>";
    
    echo "<pre>";
    print_r($event_categories);
    echo "</pre>";
    
    if (count($event_categories) > 0) {
        echo "<h3>Categories List:</h3>";
        echo "<ul>";
        foreach($event_categories as $cat) {
            echo "<li>" . htmlspecialchars($cat['name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>No categories found! Run init-db.php to populate them.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
