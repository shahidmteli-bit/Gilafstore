<?php
require_once __DIR__ . '/includes/db_connect.php';

// Get total clicks
$totalResult = $conn->query("SELECT COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click'");
$total = $totalResult ? $totalResult->fetch_assoc()['count'] : 0;

echo "<div style='padding: 15px; background: #f8f9fa; border-radius: 5px;'>";
echo "<h4>📊 Current Click Statistics</h4>";
echo "<p><strong>Total Clicks in Database:</strong> $total</p>";

if ($total > 0) {
    // Get clicks by source
    echo "<h5>Clicks by Source:</h5>";
    $sourcesResult = $conn->query("SELECT event_source, COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' GROUP BY event_source ORDER BY count DESC");
    
    if ($sourcesResult && $sourcesResult->num_rows > 0) {
        echo "<ul>";
        while ($row = $sourcesResult->fetch_assoc()) {
            echo "<li><strong>{$row['event_source']}:</strong> {$row['count']} clicks</li>";
        }
        echo "</ul>";
    }
    
    // Get recent clicks
    echo "<h5>Recent Clicks (Last 5):</h5>";
    $recentResult = $conn->query("SELECT product_id, event_source, event_at FROM analytics_product_events WHERE event_type = 'click' ORDER BY event_at DESC LIMIT 5");
    
    if ($recentResult && $recentResult->num_rows > 0) {
        echo "<ul>";
        while ($row = $recentResult->fetch_assoc()) {
            echo "<li>Product {$row['product_id']} from <strong>{$row['event_source']}</strong> at {$row['event_at']}</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: #6c757d;'>No clicks recorded yet.</p>";
}

echo "</div>";

$conn->close();
?>
