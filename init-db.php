<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check and add status column to users table if not exists
try {
    $db->query("SELECT status FROM users LIMIT 1");
} catch (Exception $e) {
    echo "Adding status column to users table...\n";
    try {
        $db->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active' NOT NULL");
        echo "Status column added to users table.\n";
    } catch (Exception $e2) {
        echo "Status column may already exist or error: " . $e2->getMessage() . "\n";
    }
}

// Check and add venue_tier column to venues table if not exists
try {
    $db->query("SELECT venue_tier FROM venues LIMIT 1");
} catch (Exception $e) {
    echo "Adding venue_tier column to venues table...\n";
    try {
        $db->exec("ALTER TABLE venues ADD COLUMN venue_tier VARCHAR(20) DEFAULT 'basic' NOT NULL");
        echo "Venue tier column added to venues table.\n";
    } catch (Exception $e2) {
        echo "Venue tier column may already exist or error: " . $e2->getMessage() . "\n";
    }
}

// Check and create settings table if not exists
try {
    $db->query("SELECT 1 FROM settings LIMIT 1");
} catch (Exception $e) {
    echo "Creating settings table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        site_name VARCHAR(100) DEFAULT 'Elegant Venues',
        site_email VARCHAR(100),
        site_phone VARCHAR(20),
        site_address TEXT,
        booking_confirmation TINYINT(1) DEFAULT 1,
        admin_notifications TINYINT(1) DEFAULT 1,
        email_notifications TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    
    $insert = "INSERT INTO settings (site_name, site_email, site_phone, site_address) 
               VALUES ('Elegant Venues', 'admin@elegantvenues.com', '+923341513407', 'Zaman Khan Plaza 2nd Floor University Town Peshawar')";
    $db->exec($insert);
    echo "Settings table created.\n";
}

// Check and create event_categories table if not exists
try {
    $db->query("SELECT 1 FROM event_categories LIMIT 1");
} catch (Exception $e) {
    echo "Creating event_categories table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS event_categories (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        icon VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    
    $insert = "INSERT INTO event_categories (name, description, icon) 
               VALUES 
               ('Wedding', 'Wedding events and celebrations', 'fa-ring'),
               ('Engagement', 'Engagement ceremonies', 'fa-heart'),
               ('Mehndi', 'Mehndi events', 'fa-palette'),
               ('Barat', 'Barat ceremonies', 'fa-horse'),
               ('Valima', 'Valima receptions', 'fa-utensils'),
               ('Corporate', 'Corporate events and conferences', 'fa-briefcase')";
    $db->exec($insert);
    echo "Event categories table created with default categories.\n";
}

echo "\nDatabase initialization complete!\n";
echo "✓ Users table with status column: OK\n";
echo "✓ Venues table with venue_tier column: OK\n";
echo "✓ Settings table: OK\n";
echo "✓ Event categories table: OK\n";
?>

