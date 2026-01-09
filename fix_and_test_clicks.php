<?php
/**
 * Fix and test click tracking with valid product IDs
 */

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/analytics_tracker.php';

echo "=== Click Tracking Fix & Test ===\n\n";

// Step 1: Get valid product IDs from database
echo "Step 1: Finding valid products...\n";
$productsResult = $conn->query("SELECT id, name FROM products LIMIT 5");

if (!$productsResult || $productsResult->num_rows === 0) {
    echo "❌ No products found in database!\n";
    echo "You need to add products first before tracking clicks.\n";
    exit;
}

$products = [];
while ($row = $productsResult->fetch_assoc()) {
    $products[] = $row;
    echo "  - Product {$row['id']}: {$row['name']}\n";
}

// Step 2: Test click tracking with valid product
echo "\nStep 2: Testing click tracking with valid product ID...\n";
$testProduct = $products[0];

try {
    $tracker = new AnalyticsTracker($conn);
    $result = $tracker->trackProductEvent(
        $testProduct['id'], 
        'click', 
        'manual_test', 
        null, 
        100.00, 
        1
    );
    
    if ($result) {
        echo "✅ Click tracked successfully!\n";
    } else {
        echo "❌ Click tracking returned false\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

// Step 3: Verify in database
echo "\nStep 3: Verifying in database...\n";
$checkResult = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE product_id = {$testProduct['id']} AND event_type = 'click'");

if ($checkResult) {
    $count = $checkResult->fetch_assoc();
    echo "Clicks for product {$testProduct['id']}: {$count['count']}\n";
    
    if ($count['count'] > 0) {
        echo "✅ SUCCESS! Click tracking is now working!\n\n";
        
        // Show recent clicks
        $recentResult = $conn->query("SELECT product_id, event_source, event_at FROM analytics_product_events WHERE event_type = 'click' ORDER BY event_at DESC LIMIT 5");
        
        if ($recentResult && $recentResult->num_rows > 0) {
            echo "Recent clicks:\n";
            while ($row = $recentResult->fetch_assoc()) {
                echo "  - Product {$row['product_id']} from {$row['event_source']} at {$row['event_at']}\n";
            }
        }
        
        echo "\n✅ You can now go to Analytics & Insights dashboard to see the data!\n";
    } else {
        echo "❌ Still not working. Check error logs.\n";
    }
}

// Step 4: Test the global function
echo "\nStep 4: Testing global trackProductEvent function...\n";
trackProductEvent($testProduct['id'], 'click', 'global_function_test', null, 150.00, 1);

$checkResult2 = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_source = 'global_function_test'");
if ($checkResult2) {
    $count2 = $checkResult2->fetch_assoc();
    if ($count2['count'] > 0) {
        echo "✅ Global function works!\n";
    } else {
        echo "❌ Global function not inserting\n";
    }
}

// Step 5: Summary
echo "\n=== SUMMARY ===\n";
$totalClicks = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click'");
if ($totalClicks) {
    $total = $totalClicks->fetch_assoc();
    echo "Total clicks in database: {$total['count']}\n";
    
    if ($total['count'] > 0) {
        echo "\n✅ CLICK TRACKING IS WORKING!\n";
        echo "The issue was: Test was using non-existent product IDs (1, 2)\n";
        echo "Solution: Always use valid product IDs from your products table\n";
        echo "\nNext steps:\n";
        echo "1. Go to homepage and click on real products\n";
        echo "2. Go to shop page and click on product cards\n";
        echo "3. Check Analytics & Insights dashboard\n";
        echo "4. Clicks should now appear!\n";
    }
}

$conn->close();
?>
