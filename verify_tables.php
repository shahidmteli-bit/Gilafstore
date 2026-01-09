<?php
require_once __DIR__ . '/includes/db_connect.php';

echo "=== Checking Analytics Tables ===\n\n";

$tables = [
    'analytics_visitors',
    'analytics_page_views', 
    'analytics_product_events',
    'analytics_daily_summary',
    'analytics_geographic_data',
    'analytics_settings'
];

$missingTables = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ $table exists\n";
    } else {
        echo "❌ $table MISSING\n";
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "\n⚠️ PROBLEM FOUND: " . count($missingTables) . " tables are missing!\n\n";
    echo "SOLUTION: You need to create the analytics tables.\n";
    echo "Run this SQL file in phpMyAdmin:\n";
    echo "database_analytics_schema.sql\n\n";
    echo "Steps:\n";
    echo "1. Open phpMyAdmin (http://localhost/phpmyadmin)\n";
    echo "2. Select your database (gilaf_ecommerce or similar)\n";
    echo "3. Click 'Import' tab\n";
    echo "4. Choose file: database_analytics_schema.sql\n";
    echo "5. Click 'Go'\n";
} else {
    echo "\n✅ All analytics tables exist!\n";
    echo "The issue must be elsewhere. Checking further...\n\n";
    
    // Check if trackProductEvent function works
    echo "Testing trackProductEvent function...\n";
    try {
        require_once __DIR__ . '/includes/analytics_tracker.php';
        trackProductEvent(999, 'click', 'manual_test', null, 100.00, 1);
        echo "✅ Function executed without errors\n";
        
        // Check if it was inserted
        $check = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE product_id = 999");
        if ($check) {
            $result = $check->fetch_assoc();
            if ($result['count'] > 0) {
                echo "✅ Test click was inserted into database!\n";
                echo "Clicks ARE working. Check if you're testing as admin (admins are excluded).\n";
            } else {
                echo "❌ Function ran but data not in database\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

$conn->close();
?>
