<?php
/**
 * Comprehensive diagnosis of why clicks aren't incrementing
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<!DOCTYPE html><html><head><title>Click Tracking Diagnosis</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:900px;margin:0 auto;}";
echo ".section{background:#f8f9fa;padding:15px;margin:15px 0;border-radius:5px;}";
echo ".success{background:#d1e7dd;color:#0f5132;}";
echo ".error{background:#f8d7da;color:#842029;}";
echo ".warning{background:#fff3cd;color:#856404;}";
echo "h3{margin-top:0;}</style></head><body>";

echo "<h1>🔍 Click Tracking Diagnosis</h1>";

// Test 1: Check if trackClick function exists in footer
echo "<div class='section'>";
echo "<h3>Test 1: Check trackClick Function</h3>";
$footerContent = file_get_contents(__DIR__ . '/includes/new-footer.php');
if (strpos($footerContent, 'function trackClick') !== false) {
    echo "<p class='success'>✅ trackClick function exists in footer</p>";
} else {
    echo "<p class='error'>❌ trackClick function NOT found in footer!</p>";
}
echo "</div>";

// Test 2: Check database clicks
echo "<div class='section'>";
echo "<h3>Test 2: Database Clicks</h3>";
$clicksResult = $conn->query("SELECT event_source, COUNT(*) as count FROM analytics_product_events WHERE event_type = 'click' GROUP BY event_source");
if ($clicksResult && $clicksResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Source</th><th>Count</th></tr>";
    while ($row = $clicksResult->fetch_assoc()) {
        echo "<tr><td>{$row['event_source']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No clicks found</p>";
}
echo "</div>";

// Test 3: Check if homepage has onclick handlers
echo "<div class='section'>";
echo "<h3>Test 3: Homepage Click Handlers</h3>";
$indexContent = file_get_contents(__DIR__ . '/index.php');
if (strpos($indexContent, 'onclick="trackClick') !== false) {
    echo "<p class='success'>✅ Homepage has trackClick onclick handlers</p>";
} else {
    echo "<p class='error'>❌ Homepage missing trackClick onclick handlers!</p>";
}
echo "</div>";

// Test 4: Manual click test
echo "<div class='section'>";
echo "<h3>Test 4: Manual Click Test</h3>";
echo "<button onclick='testClick()' style='padding:15px 30px;background:#0d6efd;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;'>Click to Test Tracking</button>";
echo "<div id='test-result' style='margin-top:15px;'></div>";
echo "</div>";

// Test 5: Instructions
echo "<div class='section warning'>";
echo "<h3>⚠️ If Clicks Still Don't Work</h3>";
echo "<p><strong>The issue is likely:</strong></p>";
echo "<ol>";
echo "<li><strong>Browser Cache:</strong> Press Ctrl+Shift+Delete and clear cache</li>";
echo "<li><strong>JavaScript Error:</strong> Press F12 and check Console tab for errors</li>";
echo "<li><strong>Network Issue:</strong> Check Network tab in F12 to see if API calls are being made</li>";
echo "</ol>";
echo "<p><strong>To test right now:</strong></p>";
echo "<ol>";
echo "<li>Click the blue button above</li>";
echo "<li>If it shows ✅ success, tracking IS working</li>";
echo "<li>If it shows ❌ error, there's an API issue</li>";
echo "</ol>";
echo "</div>";

echo "<script>";
echo "function testClick() {";
echo "  const resultDiv = document.getElementById('test-result');";
echo "  resultDiv.innerHTML = '⏳ Testing...';";
echo "  fetch('api/track_product_click.php', {";
echo "    method: 'POST',";
echo "    headers: {'Content-Type': 'application/json'},";
echo "    body: JSON.stringify({product_id: 7, source: 'diagnosis_test'}),";
echo "    keepalive: true";
echo "  })";
echo "  .then(r => r.json())";
echo "  .then(d => {";
echo "    if(d.success) {";
echo "      resultDiv.innerHTML = '<p class=\"success\">✅ Click tracked! Refresh this page to see it in the table above.</p>';";
echo "    } else {";
echo "      resultDiv.innerHTML = '<p class=\"error\">❌ Failed: ' + (d.error || 'Unknown') + '</p>';";
echo "    }";
echo "  })";
echo "  .catch(e => {";
echo "    resultDiv.innerHTML = '<p class=\"error\">❌ Error: ' + e.message + '</p>';";
echo "  });";
echo "}";
echo "</script>";

echo "</body></html>";

$conn->close();
?>
