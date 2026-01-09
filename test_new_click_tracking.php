<?php
/**
 * Test the new server-side click tracking endpoint
 * Verifies it follows Page View pattern correctly
 */

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Test New Click Tracking';
include __DIR__ . '/includes/new-header.php';

// Get real products
$products = db_fetch_all("SELECT id, name, price, image FROM products LIMIT 3");
?>

<style>
.test-container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 30px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.product-card {
    border: 2px solid #ddd;
    padding: 20px;
    margin: 15px 0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}
.product-card:hover {
    border-color: #0d6efd;
    background: #f8f9fa;
}
.status-box {
    margin-top: 20px;
    padding: 15px;
    border-radius: 5px;
    background: #e9ecef;
}
.success { background: #d1e7dd !important; color: #0f5132; }
.error { background: #f8d7da !important; color: #842029; }
.info { background: #cfe2ff !important; color: #084298; }
</style>

<div class="test-container">
    <h1>✅ New Click Tracking Test</h1>
    <p><strong>Architecture:</strong> Server-side tracking (replicates Page View pattern)</p>
    
    <div class="status-box info">
        <h4>📊 How It Works (Same as Page Views):</h4>
        <ul>
            <li>✅ Server-side endpoint at <code>track_click.php</code></li>
            <li>✅ Uses <code>AnalyticsTracker</code> class (same as Page Views)</li>
            <li>✅ Admin exclusion check (same as Page Views)</li>
            <li>✅ Tracking enabled check (same as Page Views)</li>
            <li>✅ Session management (same as Page Views)</li>
        </ul>
    </div>
    
    <div id="status" class="status-box">
        Ready to test. Click on any product below.
    </div>
    
    <hr>
    
    <h3>Test Products (Click to Track)</h3>
    
    <?php foreach ($products as $product): ?>
        <div class="product-card" onclick="testClick(<?= $product['id']; ?>, '<?= htmlspecialchars($product['name']); ?>')">
            <h4><?= htmlspecialchars($product['name']); ?></h4>
            <p>Product ID: <?= $product['id']; ?></p>
            <p>Price: ₹<?= number_format($product['price'], 2); ?></p>
            <button type="button" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px;">
                Click to Track
            </button>
        </div>
    <?php endforeach; ?>
    
    <hr>
    
    <h3>Current Statistics</h3>
    <div id="stats">
        <button onclick="refreshStats()" style="padding: 10px 20px; background: #198754; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🔄 Refresh Statistics
        </button>
        <div id="stats-content" style="margin-top: 15px;"></div>
    </div>
</div>

<script>
let clickCount = 0;

function showStatus(message, type = 'info') {
    const statusBox = document.getElementById('status');
    statusBox.className = 'status-box ' + type;
    statusBox.innerHTML = message;
}

function testClick(productId, productName) {
    clickCount++;
    showStatus(`⏳ Tracking click #${clickCount} for "${productName}" (ID: ${productId})...`, 'info');
    
    // Use the SAME trackClick function from footer
    trackClick(productId, 'test_page_v3');
    
    // Give it a moment, then check result
    setTimeout(() => {
        fetch('<?= base_url('get_click_stats.php'); ?>')
        .then(response => response.text())
        .then(html => {
            showStatus(`✅ Click #${clickCount} sent for "${productName}"!<br>Check statistics below to verify.`, 'success');
            document.getElementById('stats-content').innerHTML = html;
        });
    }, 500);
}

function refreshStats() {
    const statsContent = document.getElementById('stats-content');
    statsContent.innerHTML = '⏳ Loading...';
    
    fetch('<?= base_url('get_click_stats.php'); ?>')
    .then(response => response.text())
    .then(html => {
        statsContent.innerHTML = html;
    })
    .catch(error => {
        statsContent.innerHTML = '❌ Error loading stats: ' + error.message;
    });
}

// Load stats on page load
refreshStats();
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
