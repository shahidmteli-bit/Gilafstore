<?php
/**
 * Sales Pricing Enhancement - Deployment Verification Script
 * Run this after uploading all files to verify deployment
 */
require_once 'includes/db_connect.php';

echo "<!DOCTYPE html><html><head><title>Pricing Enhancement Deployment</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;padding:20px;}";
echo ".success{color:#059669;}.error{color:#dc2626;}.warning{color:#d97706;}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "th,td{padding:12px;text-align:left;border-bottom:1px solid #e5e7eb;}";
echo "th{background:#f3f4f6;font-weight:600;}.status{font-weight:700;}</style></head><body>";

echo "<h1>🚀 Sales Pricing Enhancement - Deployment Verification</h1>";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p><hr>";

$allPassed = true;

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
try {
    $db = get_db_connection();
    echo "<p class='success status'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error status'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    $allPassed = false;
}

// Test 2: Check Required Columns
echo "<h2>2. Database Schema Validation</h2>";
try {
    $stmt = $db->query("DESCRIBE product_weights");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = [
        'wholesale_price', 'wholesale_gst',
        'distributor_price', 'distributor_gst',
        'franchise_price', 'franchise_gst',
        'retail_price', 'retail_gst',
        'offline_mrp'
    ];
    
    echo "<table><thead><tr><th>Column</th><th>Status</th></tr></thead><tbody>";
    
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        $exists = in_array($col, $columns);
        $status = $exists ? "<span class='success'>✓ Exists</span>" : "<span class='error'>✗ Missing</span>";
        echo "<tr><td>{$col}</td><td class='status'>{$status}</td></tr>";
        if (!$exists) {
            $missingColumns[] = $col;
            $allPassed = false;
        }
    }
    echo "</tbody></table>";
    
    if (!empty($missingColumns)) {
        echo "<p class='error'>⚠ Missing columns detected. Run migration_sales_pricing_enhancement.sql</p>";
    }
} catch (Exception $e) {
    echo "<p class='error status'>✗ Schema check failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    $allPassed = false;
}

// Test 3: Check Required Files
echo "<h2>3. Required Files Check</h2>";
$requiredFiles = [
    'includes/sales_pricing_helper.php' => 'Pricing Helper Functions',
    'admin/sales_pricing_enhanced.php' => 'Enhanced Admin Pricing Page',
    'sales-portal/api_products_enhanced.php' => 'Enhanced API Endpoint',
    'migration_sales_pricing_enhancement.sql' => 'Database Migration Script',
    'test_pricing_system.php' => 'Test Suite'
];

echo "<table><thead><tr><th>File</th><th>Description</th><th>Status</th></tr></thead><tbody>";
foreach ($requiredFiles as $file => $desc) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $status = $exists ? "<span class='success'>✓ Found</span>" : "<span class='error'>✗ Missing</span>";
    echo "<tr><td>{$file}</td><td>{$desc}</td><td class='status'>{$status}</td></tr>";
    if (!$exists) {
        $allPassed = false;
    }
}
echo "</tbody></table>";

// Test 4: Function Availability
echo "<h2>4. Helper Functions Check</h2>";
if (file_exists(__DIR__ . '/includes/sales_pricing_helper.php')) {
    require_once __DIR__ . '/includes/sales_pricing_helper.php';
    
    $functions = [
        'get_party_price',
        'get_party_prices_bulk',
        'calculate_order_total',
        'validate_party_pricing',
        'get_all_pricing_tiers'
    ];
    
    echo "<table><thead><tr><th>Function</th><th>Status</th></tr></thead><tbody>";
    foreach ($functions as $func) {
        $exists = function_exists($func);
        $status = $exists ? "<span class='success'>✓ Available</span>" : "<span class='error'>✗ Not Found</span>";
        echo "<tr><td>{$func}()</td><td class='status'>{$status}</td></tr>";
        if (!$exists) {
            $allPassed = false;
        }
    }
    echo "</tbody></table>";
} else {
    echo "<p class='error'>⚠ sales_pricing_helper.php not found - cannot check functions</p>";
    $allPassed = false;
}

// Test 5: Sample Data Check
echo "<h2>5. Pricing Data Status</h2>";
try {
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_weights,
            SUM(CASE WHEN wholesale_price > 0 THEN 1 ELSE 0 END) as with_wholesale,
            SUM(CASE WHEN distributor_price > 0 THEN 1 ELSE 0 END) as with_distributor,
            SUM(CASE WHEN franchise_price > 0 THEN 1 ELSE 0 END) as with_franchise,
            SUM(CASE WHEN retail_price > 0 THEN 1 ELSE 0 END) as with_retail,
            SUM(CASE WHEN offline_mrp > 0 THEN 1 ELSE 0 END) as with_mrp
        FROM product_weights
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "<table><thead><tr><th>Metric</th><th>Count</th><th>Status</th></tr></thead><tbody>";
    echo "<tr><td>Total Product Weights</td><td>{$stats['total_weights']}</td><td class='success'>—</td></tr>";
    echo "<tr><td>With Wholesale Price</td><td>{$stats['with_wholesale']}</td><td>" . ($stats['with_wholesale'] > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠</span>") . "</td></tr>";
    echo "<tr><td>With Distributor Price</td><td>{$stats['with_distributor']}</td><td>" . ($stats['with_distributor'] > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠</span>") . "</td></tr>";
    echo "<tr><td>With Franchise Price</td><td>{$stats['with_franchise']}</td><td>" . ($stats['with_franchise'] > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠</span>") . "</td></tr>";
    echo "<tr><td>With Retail Price</td><td>{$stats['with_retail']}</td><td>" . ($stats['with_retail'] > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠</span>") . "</td></tr>";
    echo "<tr><td>With Offline MRP</td><td>{$stats['with_mrp']}</td><td>" . ($stats['with_mrp'] > 0 ? "<span class='success'>✓</span>" : "<span class='warning'>⚠</span>") . "</td></tr>";
    echo "</tbody></table>";
    
    if ($stats['with_wholesale'] == 0 && $stats['with_distributor'] == 0 && $stats['with_franchise'] == 0 && $stats['with_retail'] == 0) {
        echo "<p class='warning'>⚠ No pricing data found. Set prices via admin/sales_pricing_enhanced.php</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Data check failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Final Summary
echo "<hr><h2>📊 Deployment Summary</h2>";
if ($allPassed) {
    echo "<div style='background:#d1fae5;padding:20px;border-radius:8px;border:2px solid #059669;'>";
    echo "<h3 class='success' style='margin:0;'>✓ All Checks Passed!</h3>";
    echo "<p style='margin:10px 0 0 0;'>The Sales Pricing Enhancement is successfully deployed and ready to use.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Navigate to <a href='admin/sales_pricing_enhanced.php'>admin/sales_pricing_enhanced.php</a> to set pricing data</li>";
    echo "<li>Run <code>php test_pricing_system.php</code> from command line for detailed tests</li>";
    echo "<li>Test order creation in sales portal with different party types</li>";
    echo "<li>Delete this file (deploy_pricing_enhancement.php) after verification</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background:#fee2e2;padding:20px;border-radius:8px;border:2px solid #dc2626;'>";
    echo "<h3 class='error' style='margin:0;'>✗ Deployment Issues Detected</h3>";
    echo "<p style='margin:10px 0 0 0;'>Please review the errors above and take corrective action.</p>";
    echo "<p><strong>Common Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Missing columns: Run migration_sales_pricing_enhancement.sql in phpMyAdmin</li>";
    echo "<li>Missing files: Upload all required files via FileZilla</li>";
    echo "<li>Function errors: Ensure sales_pricing_helper.php is uploaded correctly</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr><p style='text-align:center;color:#6b7280;font-size:14px;'>Sales Pricing Enhancement v2.0 | " . date('Y') . "</p>";
echo "</body></html>";
