<?php
/**
 * Test script to verify click tracking is working
 * Access this page to manually test the tracking system
 */

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Click Tracking Test';
include __DIR__ . '/includes/new-header.php';
?>

<style>
.test-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 30px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.test-button {
    padding: 15px 30px;
    margin: 10px;
    font-size: 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    background: #0d6efd;
    color: white;
}
.test-button:hover {
    background: #0b5ed7;
}
.result {
    margin-top: 20px;
    padding: 15px;
    border-radius: 5px;
    display: none;
}
.result.success {
    background: #d1e7dd;
    color: #0f5132;
    border: 1px solid #badbcc;
}
.result.error {
    background: #f8d7da;
    color: #842029;
    border: 1px solid #f5c2c7;
}
.stats {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 5px;
}
</style>

<div class="test-container">
    <h1>🧪 Click Tracking Test</h1>
    <p>Use this page to test if click tracking is working correctly.</p>
    
    <hr>
    
    <h3>Test Click Tracking</h3>
    <button class="test-button" onclick="testClickTracking(1, 'test_page')">
        Test Track Product #1
    </button>
    <button class="test-button" onclick="testClickTracking(2, 'test_page')">
        Test Track Product #2
    </button>
    
    <div id="result" class="result"></div>
    
    <hr>
    
    <h3>Current Click Statistics</h3>
    <div class="stats">
        <?php
        // Get click statistics
        $clickStats = db_fetch("SELECT COUNT(*) as total_clicks FROM analytics_product_events WHERE event_type = 'click'");
        $recentClicks = db_fetch_all("SELECT product_id, event_source, event_at FROM analytics_product_events WHERE event_type = 'click' ORDER BY event_at DESC LIMIT 10");
        ?>
        
        <p><strong>Total Product Clicks:</strong> <?= $clickStats['total_clicks'] ?? 0; ?></p>
        
        <?php if (!empty($recentClicks)): ?>
            <h4>Recent Clicks:</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #e9ecef;">
                        <th style="padding: 8px; text-align: left;">Product ID</th>
                        <th style="padding: 8px; text-align: left;">Source</th>
                        <th style="padding: 8px; text-align: left;">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentClicks as $click): ?>
                        <tr>
                            <td style="padding: 8px; border-top: 1px solid #dee2e6;"><?= $click['product_id']; ?></td>
                            <td style="padding: 8px; border-top: 1px solid #dee2e6;"><?= htmlspecialchars($click['event_source']); ?></td>
                            <td style="padding: 8px; border-top: 1px solid #dee2e6;"><?= date('Y-m-d H:i:s', strtotime($click['event_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #6c757d;">No clicks recorded yet. Try clicking the test buttons above.</p>
        <?php endif; ?>
        
        <button class="test-button" onclick="location.reload()" style="margin-top: 20px;">
            🔄 Refresh Statistics
        </button>
    </div>
    
    <hr>
    
    <h3>Instructions</h3>
    <ol>
        <li>Click one of the test buttons above</li>
        <li>Wait for the success message</li>
        <li>Click "Refresh Statistics" to see the new click</li>
        <li>Go to <strong>Admin Panel → Analytics & Insights</strong> to see updated metrics</li>
    </ol>
</div>

<script>
function testClickTracking(productId, source) {
    const resultDiv = document.getElementById('result');
    resultDiv.style.display = 'block';
    resultDiv.className = 'result';
    resultDiv.innerHTML = '⏳ Tracking click...';
    
    fetch('<?= base_url('api/track_product_click.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, source: source }),
        keepalive: true
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.className = 'result success';
            resultDiv.innerHTML = '✅ Click tracked successfully! Product ID: ' + productId + ', Source: ' + source;
        } else {
            resultDiv.className = 'result error';
            resultDiv.innerHTML = '❌ Tracking failed: ' + (data.error || 'Unknown error');
        }
    })
    .catch(error => {
        resultDiv.className = 'result error';
        resultDiv.innerHTML = '❌ Network error: ' + error.message;
    });
}
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
