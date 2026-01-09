<?php
/**
 * Database Migration: Add EAN column to products table
 * Run this file once to add the EAN field to the products table
 */

require_once __DIR__ . '/../includes/db_connect.php';

try {
    $db = get_db_connection();
    
    // Check if EAN column already exists
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'ean'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add EAN column to products table
        $db->exec("ALTER TABLE products ADD COLUMN ean VARCHAR(13) DEFAULT NULL AFTER sku");
        echo "✅ EAN column added successfully to products table!<br>";
    } else {
        echo "ℹ️ EAN column already exists in products table.<br>";
    }
    
    echo "<br><a href='manage_products.php'>Go to Manage Products</a>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
