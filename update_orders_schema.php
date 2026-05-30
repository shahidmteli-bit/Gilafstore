<?php
require_once __DIR__ . '/includes/db_connect.php';

try {
    $db = get_db_connection();
    
    // Check if updated_at column exists in orders table
    $checkCol = $db->query("SHOW COLUMNS FROM orders LIKE 'updated_at'");
    if ($checkCol->rowCount() === 0) {
        echo "Adding updated_at column to orders table...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
        echo "updated_at column added successfully.\n";
    } else {
        echo "updated_at column already exists.\n";
    }
    
    // Backfill updated_at for delivered orders that have it null
    // We'll use picked_up_at if available, otherwise created_at
    echo "Backfilling updated_at for existing delivered orders...\n";
    $sql = "UPDATE orders 
            SET updated_at = COALESCE(picked_up_at, created_at) 
            WHERE order_status = 'delivered' AND updated_at IS NULL";
    $stmt = $db->query($sql);
    echo "Backfilled " . $stmt->rowCount() . " orders.\n";
    
    echo "Schema update completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
