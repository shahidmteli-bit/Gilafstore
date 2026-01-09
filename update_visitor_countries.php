<?php
/**
 * Update existing visitor records with default country data
 * Run this once to populate country field for existing visitors
 */

require_once __DIR__ . '/includes/db_connect.php';

// Update all existing visitors without country data
$updateQuery = "UPDATE analytics_visitors 
                SET country = 'India', 
                    country_code = 'IN' 
                WHERE country IS NULL OR country = ''";

$result = $conn->query($updateQuery);

if ($result) {
    $affectedRows = $conn->affected_rows;
    echo "✅ Successfully updated {$affectedRows} visitor records with default country data (India).\n";
    echo "\nYou can now refresh the Analytics & Insights dashboard to see geographic distribution.\n";
} else {
    echo "❌ Error updating visitor records: " . $conn->error . "\n";
}

// Show current country distribution
echo "\n--- Current Geographic Distribution ---\n";
$statsQuery = "SELECT 
                country, 
                country_code, 
                COUNT(*) as visitor_count 
               FROM analytics_visitors 
               WHERE country IS NOT NULL 
               GROUP BY country, country_code 
               ORDER BY visitor_count DESC";

$statsResult = $conn->query($statsQuery);

if ($statsResult && $statsResult->num_rows > 0) {
    while ($row = $statsResult->fetch_assoc()) {
        echo "{$row['country']} ({$row['country_code']}): {$row['visitor_count']} visitors\n";
    }
} else {
    echo "No visitor data found.\n";
}

$conn->close();
?>
