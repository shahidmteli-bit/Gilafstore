<?php
require_once __DIR__ . '/includes/db_connect.php';

echo "=== Homepage/Shop Click Verification ===\n\n";

// Check all clicks
$allClicks = $conn->query("SELECT event_source, COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' GROUP BY event_source");

echo "Clicks by source:\n";
if ($allClicks && $allClicks->num_rows > 0) {
    while ($row = $allClicks->fetch_assoc()) {
        echo "  {$row['event_source']}: {$row['count']}\n";
    }
} else {
    echo "  No clicks found\n";
}

// Check date range
echo "\n--- Date Range Check ---\n";
$fromDate = date('Y-m-d', strtotime('-7 days'));
$toDate = date('Y-m-d');
echo "Dashboard date range: $fromDate to $toDate\n";

$clicksInRange = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' AND DATE(event_at) BETWEEN '$fromDate' AND '$toDate'");
if ($clicksInRange) {
    $count = $clicksInRange->fetch_assoc();
    echo "Clicks in this range: {$count['count']}\n";
}

// Check today's clicks
$today = date('Y-m-d');
$todayClicks = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' AND DATE(event_at) = '$today'");
if ($todayClicks) {
    $count = $todayClicks->fetch_assoc();
    echo "Clicks today ($today): {$count['count']}\n";
}

// Instructions
echo "\n--- NEXT STEPS ---\n";
echo "1. Clear browser cache (Ctrl+Shift+Delete)\n";
echo "2. Go to homepage: http://localhost/Gilaf%20Ecommerce%20website/\n";
echo "3. Open browser console (F12)\n";
echo "4. Click on a product image or title\n";
echo "5. Check console for 'Click tracking failed' or success\n";
echo "6. Come back here and refresh to see if click was recorded\n";

$conn->close();
?>
