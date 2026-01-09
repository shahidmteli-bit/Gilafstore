<!DOCTYPE html>
<html>
<head>
    <title>Click Tracking Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .test-btn { padding: 15px 30px; margin: 10px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .test-btn:hover { background: #0b5ed7; }
        .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d1e7dd; color: #0f5132; }
        .error { background: #f8d7da; color: #842029; }
        .info { background: #cfe2ff; color: #084298; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Click Tracking Debug Tool</h1>
    
    <div class="result info">
        <strong>Instructions:</strong>
        <ol>
            <li>Click "Test API Endpoint" to verify the tracking API works</li>
            <li>Check the results below</li>
            <li>Click "Check Database" to see current click data</li>
        </ol>
    </div>

    <button class="test-btn" onclick="testAPI()">Test API Endpoint</button>
    <button class="test-btn" onclick="checkDatabase()">Check Database</button>
    <button class="test-btn" onclick="testRealClick()">Simulate Real Click</button>
    
    <div id="output"></div>

    <script>
    function showResult(message, type = 'info') {
        const output = document.getElementById('output');
        const div = document.createElement('div');
        div.className = 'result ' + type;
        div.innerHTML = message;
        output.appendChild(div);
    }

    function testAPI() {
        showResult('⏳ Testing API endpoint...', 'info');
        
        fetch('api/track_product_click.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: 1, source: 'debug_test' })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showResult('✅ API is working! Click was tracked successfully.<br>Product ID: 1, Source: debug_test', 'success');
            } else {
                showResult('⚠️ API responded but reported error: ' + (data.error || 'Unknown'), 'error');
            }
        })
        .catch(error => {
            showResult('❌ API test failed: ' + error.message + '<br>Check if api/track_product_click.php exists', 'error');
        });
    }

    function checkDatabase() {
        showResult('⏳ Checking database...', 'info');
        
        fetch('check_database_clicks.php')
        .then(response => response.text())
        .then(html => {
            showResult('<strong>Database Status:</strong><pre>' + html + '</pre>', 'info');
        })
        .catch(error => {
            showResult('❌ Database check failed: ' + error.message, 'error');
        });
    }

    function testRealClick() {
        showResult('⏳ Simulating real product click...', 'info');
        
        // Simulate what happens when user clicks on homepage
        const productId = 1;
        const source = 'homepage_simulation';
        
        fetch('api/track_product_click.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, source: source }),
            keepalive: true
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showResult('✅ Real click simulation successful!<br>Now check the Analytics dashboard.', 'success');
            } else {
                showResult('⚠️ Simulation failed: ' + (data.error || 'Unknown'), 'error');
            }
        })
        .catch(error => {
            showResult('❌ Simulation error: ' + error.message, 'error');
        });
    }
    </script>
</body>
</html>
