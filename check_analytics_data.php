<?php
/**
 * Quick diagnostic script to check analytics data
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "=== Analytics Data Check ===\n\n";

// Check if tables exist
$tables = ['analytics_visitors', 'analytics_page_views', 'analytics_product_events'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists\n";
        
        // Count records
        $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($countResult) {
            $count = $countResult->fetch_assoc();
            echo "   Records: {$count['count']}\n";
        }
    } else {
        echo "❌ Table '$table' does NOT exist\n";
    }
}

echo "\n--- Product Events Breakdown ---\n";
$eventsResult = $conn->query("SELECT event_type, COUNT(*) as count FROM analytics_product_events GROUP BY event_type");
if ($eventsResult && $eventsResult->num_rows > 0) {
    while ($row = $eventsResult->fetch_assoc()) {
        echo "{$row['event_type']}: {$row['count']}\n";
    }
} else {
    echo "No product events found\n";
}

echo "\n--- Recent Product Events ---\n";
$recentResult = $conn->query("SELECT product_id, event_type, event_source, event_at FROM analytics_product_events ORDER BY event_at DESC LIMIT 5");
if ($recentResult && $recentResult->num_rows > 0) {
    while ($row = $recentResult->fetch_assoc()) {
        echo "{$row['event_at']} | Product {$row['product_id']} | {$row['event_type']} | {$row['event_source']}\n";
    }
} else {
    echo "No recent events\n";
}

echo "\n--- Dashboard Query Test ---\n";
$fromDate = date('Y-m-d', strtotime('-7 days'));
$toDate = date('Y-m-d');

// Test product stats query
$productQuery = "SELECT 
    COUNT(CASE WHEN event_type = 'view' THEN 1 END) as product_views,
    COUNT(CASE WHEN event_type = 'click' THEN 1 END) as product_clicks,
    COUNT(CASE WHEN event_type = 'add_to_cart' THEN 1 END) as add_to_cart,
    COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) as purchases
FROM analytics_product_events
WHERE DATE(event_at) BETWEEN '$fromDate' AND '$toDate'";

$result = $conn->query($productQuery);
if ($result) {
    $stats = $result->fetch_assoc();
    echo "Product Views: {$stats['product_views']}\n";
    echo "Product Clicks: {$stats['product_clicks']}\n";
    echo "Add to Cart: {$stats['add_to_cart']}\n";
    echo "Purchases: {$stats['purchases']}\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$conn->close();
?>
