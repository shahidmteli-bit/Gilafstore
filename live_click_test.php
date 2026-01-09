<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Live Click Test';
include __DIR__ . '/includes/new-header.php';

// Get a real product
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
.product-test-card {
    border: 2px solid #ddd;
    padding: 20px;
    margin: 15px 0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}
.product-test-card:hover {
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
</style>

<div class="test-container">
    <h1>🧪 Live Click Tracking Test</h1>
    <p>Click on the products below to test if tracking is working in real-time.</p>
    
    <div id="status" class="status-box">
        Ready to test. Click on any product below.
    </div>
    
    <hr>
    
    <h3>Test Products (Click to Track)</h3>
    
    <?php foreach ($products as $product): ?>
        <div class="product-test-card" onclick="testProductClick(<?= $product['id']; ?>, '<?= htmlspecialchars($product['name']); ?>')">
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

function testProductClick(productId, productName) {
    clickCount++;
    showStatus(`⏳ Tracking click #${clickCount} for "${productName}" (ID: ${productId})...`, 'info');
    
    // Track the click
    fetch('<?= base_url('api/track_product_click.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            product_id: productId, 
            source: 'live_test_page' 
        }),
        keepalive: true
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            showStatus(`✅ Click #${clickCount} tracked successfully for "${productName}"!<br>Now refresh statistics to see the update.`, 'success');
            // Auto-refresh stats after 1 second
            setTimeout(refreshStats, 1000);
        } else {
            showStatus(`❌ Tracking failed: ${data.error || 'Unknown error'}`, 'error');
        }
    })
    .catch(error => {
        console.error('Tracking error:', error);
        showStatus(`❌ Network error: ${error.message}`, 'error');
    });
}

function refreshStats() {
    const statsContent = document.getElementById('stats-content');
    statsContent.innerHTML = '⏳ Loading...';
    
    fetch('get_click_stats.php')
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
