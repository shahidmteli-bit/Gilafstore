<?php
/**
 * One-time migration: Add fssai_number column to company_profile table
 * Safe to run multiple times — checks if column exists before adding
 * 
 * Run once via: https://gilafstore.com/admin/add_fssai_column.php
 * Then delete this file.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // Check if column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM company_profile LIKE 'fssai_number'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "OK: Column 'fssai_number' already exists in company_profile table.\n";
        echo "No changes needed. You can delete this file.\n";
    } else {
        $pdo->exec("ALTER TABLE company_profile ADD COLUMN fssai_number VARCHAR(20) DEFAULT '' AFTER website");
        echo "SUCCESS: Column 'fssai_number' added to company_profile table.\n";
        
        // Pre-fill with existing FSSAI number
        $pdo->exec("UPDATE company_profile SET fssai_number = '12724064000335' WHERE id = 1");
        echo "Pre-filled FSSAI number: 12724064000335\n";
        echo "\nDone! You can now edit FSSAI from Admin > Company Profile.\n";
        echo "Please DELETE this migration file after running it.\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
