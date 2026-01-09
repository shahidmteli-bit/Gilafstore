<?php
require_once __DIR__ . '/includes/db_connect.php';

echo "=== Click Data Analysis ===\n\n";

// Check all clicks in database
$allClicks = $conn->query("SELECT product_id, event_source, visitor_id, event_at FROM analytics_product_events WHERE event_type = 'click' ORDER BY event_at DESC");

if ($allClicks && $allClicks->num_rows > 0) {
    echo "Total clicks in database: " . $allClicks->num_rows . "\n\n";
    echo "All clicks:\n";
    while ($row = $allClicks->fetch_assoc()) {
        echo "- Product {$row['product_id']} | Source: {$row['event_source']} | Visitor: " . substr($row['visitor_id'], 0, 16) . "... | Time: {$row['event_at']}\n";
    }
    
    // Check if these are test clicks
    echo "\n--- Source Breakdown ---\n";
    $sources = $conn->query("SELECT event_source, COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' GROUP BY event_source");
    if ($sources) {
        while ($row = $sources->fetch_assoc()) {
            $isTest = in_array($row['event_source'], ['manual_test', 'global_function_test', 'debug_test', 'direct_test', 'class_test']);
            $label = $isTest ? '(TEST DATA)' : '(REAL USER)';
            echo "{$row['event_source']}: {$row['count']} $label\n";
        }
    }
} else {
    echo "No clicks found in database.\n";
}

// Check date range being used in dashboard
echo "\n--- Dashboard Date Range Check ---\n";
$fromDate = date('Y-m-d', strtotime('-7 days'));
$toDate = date('Y-m-d');
echo "Dashboard shows last 7 days: $fromDate to $toDate\n";

$clicksInRange = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' AND DATE(event_at) BETWEEN '$fromDate' AND '$toDate'");
if ($clicksInRange) {
    $count = $clicksInRange->fetch_assoc();
    echo "Clicks in this date range: {$count['count']}\n";
}

// Check if you're logged in as admin
echo "\n--- Admin Check ---\n";
session_start();
if (isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    echo "⚠️ WARNING: You are logged in as ADMIN\n";
    echo "Admin users are EXCLUDED from tracking!\n";
    echo "Log out and test as guest or regular user.\n";
} else {
    echo "✅ Not logged in as admin (tracking should work)\n";
}

$conn->close();
?>
