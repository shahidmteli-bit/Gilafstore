<?php
require_once __DIR__ . '/includes/db_connect.php';

try {
    $db = get_db_connection();
    
    // Check if phone column exists in users table
    $checkPhone = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($checkPhone->rowCount() === 0) {
        echo "Adding phone column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
        echo "Phone column added successfully.\n";
    } else {
        echo "Phone column already exists.\n";
    }
    
    echo "Schema update completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
