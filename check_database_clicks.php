<?php
require_once __DIR__ . '/includes/db_connect.php';

// Check product events
$result = $conn->query("SELECT COUNT(*) as total FROM analytics_product_events WHERE event_type = 'click'");
$clickCount = $result ? $result->fetch_assoc()['total'] : 0;

echo "Total Clicks in Database: $clickCount\n\n";

// Recent clicks
$recent = $conn->query("SELECT product_id, event_source, event_at FROM analytics_product_events WHERE event_type = 'click' ORDER BY event_at DESC LIMIT 5");

if ($recent && $recent->num_rows > 0) {
    echo "Recent Clicks:\n";
    while ($row = $recent->fetch_assoc()) {
        echo "- Product {$row['product_id']} from {$row['event_source']} at {$row['event_at']}\n";
    }
} else {
    echo "No clicks found in database.\n";
    echo "\nPossible issues:\n";
    echo "1. Analytics tables not created (run database_analytics_schema.sql)\n";
    echo "2. API endpoint not accessible\n";
    echo "3. JavaScript not executing\n";
}

$conn->close();
?>
