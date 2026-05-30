<?php
// Hybrid: uses db_connect.php for env-aware credentials (XAMPP + Hostinger)
require_once __DIR__ . '/includes/db_connect.php';

try {
    $pdo = $pdo ?? new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    echo "Connected to database successfully.\n";
    
    // Check if updated_at column exists in orders table
    $checkCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'updated_at'");
    if ($checkCol->rowCount() === 0) {
        echo "Adding updated_at column to orders table...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
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
    $stmt = $pdo->query($sql);
    echo "Backfilled " . $stmt->rowCount() . " orders.\n";
    
    echo "Schema update completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
