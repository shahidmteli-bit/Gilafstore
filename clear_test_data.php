<?php
/**
 * Clear test data and verify real click tracking
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "=== Clear Test Data & Verify Real Tracking ===\n\n";

// Step 1: Show current data
echo "Step 1: Current click data...\n";
$current = $conn->query("SELECT event_source, COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' GROUP BY event_source");
if ($current && $current->num_rows > 0) {
    while ($row = $current->fetch_assoc()) {
        echo "  {$row['event_source']}: {$row['count']}\n";
    }
} else {
    echo "  No clicks found\n";
}

// Step 2: Delete test data
echo "\nStep 2: Deleting test data...\n";
$testSources = ['manual_test', 'global_function_test', 'debug_test', 'direct_test', 'class_test', 'test_page', 'homepage_simulation'];

foreach ($testSources as $source) {
    $result = $conn->query("DELETE FROM analytics_product_events WHERE event_source = '$source'");
    if ($result) {
        echo "  ✅ Deleted clicks from: $source\n";
    }
}

// Step 3: Check remaining data
echo "\nStep 3: Remaining clicks...\n";
$remaining = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click'");
if ($remaining) {
    $count = $remaining->fetch_assoc();
    echo "  Total clicks remaining: {$count['count']}\n";
    
    if ($count['count'] > 0) {
        echo "\n  Recent real clicks:\n";
        $recent = $conn->query("SELECT product_id, event_source, event_at FROM analytics_product_events WHERE event_type = 'click' ORDER BY event_at DESC LIMIT 5");
        if ($recent && $recent->num_rows > 0) {
            while ($row = $recent->fetch_assoc()) {
                echo "    - Product {$row['product_id']} from {$row['event_source']} at {$row['event_at']}\n";
            }
        }
    }
}

// Step 4: Check admin status
echo "\nStep 4: Checking admin status...\n";
session_start();
if (isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    echo "  ⚠️ You are logged in as ADMIN\n";
    echo "  Admin clicks are NOT tracked (by design)\n";
    echo "  To test: Log out or use incognito window\n";
} else {
    echo "  ✅ Not admin - clicks should be tracked\n";
}

// Step 5: Instructions
echo "\n=== NEXT STEPS ===\n";
echo "1. If you're admin: Log out or use incognito window\n";
echo "2. Go to homepage: http://localhost/Gilaf%20Ecommerce%20website/\n";
echo "3. Click on product images or titles\n";
echo "4. Go to shop page and click product cards\n";
echo "5. Refresh Analytics dashboard\n";
echo "6. Clicks should increment!\n";

$conn->close();
?>
