<?php
/**
 * CRM Integration Debug Panel
 * Displays detailed diagnostic information about WACRM connection
 */

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

require_admin();

// Handle AJAX fetch debug request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'fetch_debug') {
    header('Content-Type: application/json');
    
    try {
        // Get WACRM URL from database
        $result = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_api_url' LIMIT 1");
        $baseUrl = $result['setting_value'] ?? 'http://localhost:3000';
        $url = rtrim($baseUrl, '/') . '/api/debug';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode([
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode,
            ]);
            exit;
        }
        
        $data = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'http_code' => $httpCode,
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
        exit;
    }
}

// Get local debug info
$localDebug = [
    'timestamp' => date('c'),
    'php_version' => phpversion(),
    'server_os' => php_uname(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'memory_usage' => [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit'),
    ],
    'database' => [
        'connected' => 'Yes',
        'driver' => 'PDO',
    ],
    'curl' => [
        'enabled' => extension_loaded('curl') ? 'Yes' : 'No',
        'version' => curl_version()['version'] ?? 'Unknown',
    ],
    'ssl' => [
        'enabled' => extension_loaded('openssl') ? 'Yes' : 'No',
    ],
];

// Get CRM settings
try {
    $crm_enabled = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_enabled' LIMIT 1");
    $crm_url = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_api_url' LIMIT 1");
    $api_key = db_fetch_one("SELECT api_key FROM crm_api_keys WHERE is_active = 1 LIMIT 1");
    
    $localDebug['crm_settings'] = [
        'enabled' => ($crm_enabled['setting_value'] ?? '0') === '1' ? 'Yes' : 'No',
        'base_url' => $crm_url['setting_value'] ?? 'Not set',
        'api_key_exists' => $api_key ? 'Yes' : 'No',
    ];
} catch (Exception $e) {
    $localDebug['crm_settings'] = [
        'enabled' => 'Error',
        'base_url' => 'Error',
        'api_key_exists' => 'Error',
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Debug Panel - GilafStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .debug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .debug-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .debug-card h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .debug-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
            font-size: 13px;
        }
        
        .debug-item:last-child {
            border-bottom: none;
        }
        
        .debug-label {
            color: #666;
            font-weight: 500;
        }
        
        .debug-value {
            color: #333;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            text-align: right;
            max-width: 50%;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #721c24;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #155724;
        }
        
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
            border-left: 4px solid #667eea;
        }
        
        .icon {
            font-size: 18px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="crm_integration.php" class="back-link">← Back to CRM Integration</a>
        
        <div class="header">
            <h1>
                <span class="icon">🔧</span>
                CRM Integration Debug Panel
            </h1>
            <p>Comprehensive diagnostic information for WACRM connection troubleshooting</p>
        </div>
        
        <div class="button-group">
            <button class="btn-primary" onclick="fetchRemoteDebug()">
                <span class="icon">🌐</span> Fetch WACRM Debug Info
            </button>
            <button class="btn-secondary" onclick="copyAllDebug()">
                <span class="icon">📋</span> Copy All Debug Info
            </button>
            <button class="btn-secondary" onclick="location.reload()">
                <span class="icon">🔄</span> Refresh Page
            </button>
        </div>
        
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>Fetching WACRM debug information...</p>
        </div>
        
        <div id="message"></div>
        
        <!-- Local Debug Info -->
        <div class="debug-grid">
            <div class="debug-card">
                <h2><span class="icon">💻</span> Server Information</h2>
                <div class="debug-item">
                    <span class="debug-label">PHP Version</span>
                    <span class="debug-value"><?php echo $localDebug['php_version']; ?></span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">OS</span>
                    <span class="debug-value"><?php echo substr($localDebug['server_os'], 0, 50); ?></span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Server</span>
                    <span class="debug-value"><?php echo substr($localDebug['server_software'], 0, 50); ?></span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Timestamp</span>
                    <span class="debug-value"><?php echo $localDebug['timestamp']; ?></span>
                </div>
            </div>
            
            <div class="debug-card">
                <h2><span class="icon">🗄️</span> Database</h2>
                <div class="debug-item">
                    <span class="debug-label">Connected</span>
                    <span class="debug-value">
                        <span class="status-badge status-success">
                            <?php echo $localDebug['database']['connected']; ?>
                        </span>
                    </span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Driver</span>
                    <span class="debug-value"><?php echo $localDebug['database']['driver']; ?></span>
                </div>
            </div>
            
            <div class="debug-card">
                <h2><span class="icon">⚙️</span> CRM Settings</h2>
                <div class="debug-item">
                    <span class="debug-label">Enabled</span>
                    <span class="debug-value">
                        <span class="status-badge <?php echo $localDebug['crm_settings']['enabled'] === 'Yes' ? 'status-success' : 'status-warning'; ?>">
                            <?php echo $localDebug['crm_settings']['enabled']; ?>
                        </span>
                    </span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Base URL</span>
                    <span class="debug-value"><?php echo $localDebug['crm_settings']['base_url']; ?></span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">API Key</span>
                    <span class="debug-value">
                        <span class="status-badge <?php echo $localDebug['crm_settings']['api_key_exists'] === 'Yes' ? 'status-success' : 'status-warning'; ?>">
                            <?php echo $localDebug['crm_settings']['api_key_exists']; ?>
                        </span>
                    </span>
                </div>
            </div>
            
            <div class="debug-card">
                <h2><span class="icon">📦</span> Extensions</h2>
                <div class="debug-item">
                    <span class="debug-label">cURL</span>
                    <span class="debug-value">
                        <span class="status-badge <?php echo $localDebug['curl']['enabled'] === 'Yes' ? 'status-success' : 'status-error'; ?>">
                            <?php echo $localDebug['curl']['enabled']; ?>
                        </span>
                    </span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">OpenSSL</span>
                    <span class="debug-value">
                        <span class="status-badge <?php echo $localDebug['ssl']['enabled'] === 'Yes' ? 'status-success' : 'status-error'; ?>">
                            <?php echo $localDebug['ssl']['enabled']; ?>
                        </span>
                    </span>
                </div>
            </div>
            
            <div class="debug-card">
                <h2><span class="icon">💾</span> Memory Usage</h2>
                <div class="debug-item">
                    <span class="debug-label">Current</span>
                    <span class="debug-value"><?php echo number_format($localDebug['memory_usage']['current'] / 1024 / 1024, 2); ?> MB</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Peak</span>
                    <span class="debug-value"><?php echo number_format($localDebug['memory_usage']['peak'] / 1024 / 1024, 2); ?> MB</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Limit</span>
                    <span class="debug-value"><?php echo $localDebug['memory_usage']['limit']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Remote Debug Info (loaded via AJAX) -->
        <div id="remoteDebugContainer"></div>
    </div>
    
    <script>
        function fetchRemoteDebug() {
            const loading = document.getElementById('loading');
            const message = document.getElementById('message');
            const container = document.getElementById('remoteDebugContainer');
            
            loading.classList.add('active');
            message.innerHTML = '';
            container.innerHTML = '';
            
            const formData = new FormData();
            formData.append('action', 'fetch_debug');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then(data => {
                loading.classList.remove('active');
                
                if (!data.success) {
                    message.innerHTML = `
                        <div class="error-message">
                            <strong>Error:</strong> ${data.error || 'Unknown error'}
                        </div>
                    `;
                    return;
                }
                
                message.innerHTML = `
                    <div class="success-message">
                        <strong>✓ Success!</strong> WACRM debug information retrieved (HTTP ${data.http_code})
                    </div>
                `;
                
                renderRemoteDebug(data.data);
            })
            .catch(err => {
                loading.classList.remove('active');
                message.innerHTML = `
                    <div class="error-message">
                        <strong>Error:</strong> ${err.message}
                    </div>
                `;
            });
        }
        
        function renderRemoteDebug(debugData) {
            const container = document.getElementById('remoteDebugContainer');
            
            if (!debugData || !debugData.debug) {
                container.innerHTML = '<div class="error-message">No debug data received</div>';
                return;
            }
            
            const debug = debugData.debug;
            let html = '';
            
            // Environment
            if (debug.env) {
                html += `
                    <div class="debug-card">
                        <h2><span class="icon">🌍</span> WACRM Environment</h2>
                        ${Object.entries(debug.env).map(([key, value]) => `
                            <div class="debug-item">
                                <span class="debug-label">${key}</span>
                                <span class="debug-value">
                                    ${typeof value === 'string' && value.includes('✓') 
                                        ? `<span class="status-badge status-success">${value}</span>` 
                                        : typeof value === 'string' && value.includes('✗')
                                        ? `<span class="status-badge status-error">${value}</span>`
                                        : value}
                                </span>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            // Supabase
            if (debug.supabase) {
                html += `
                    <div class="debug-card">
                        <h2><span class="icon">🔗</span> Supabase Connection</h2>
                        <div class="debug-item">
                            <span class="debug-label">Status</span>
                            <span class="debug-value">
                                <span class="status-badge ${debug.supabase.status === 'Connected' ? 'status-success' : 'status-error'}">
                                    ${debug.supabase.status}
                                </span>
                            </span>
                        </div>
                        ${debug.supabase.error ? `
                            <div class="debug-item">
                                <span class="debug-label">Error</span>
                                <span class="debug-value">${debug.supabase.error}</span>
                            </div>
                        ` : ''}
                        <div class="debug-item">
                            <span class="debug-label">Contacts Count</span>
                            <span class="debug-value">${debug.supabase.contactsCount}</span>
                        </div>
                    </div>
                `;
            }
            
            // Integration Keys
            if (debug.integrationKeys) {
                html += `
                    <div class="debug-card">
                        <h2><span class="icon">🔑</span> Integration Keys</h2>
                        <div class="debug-item">
                            <span class="debug-label">Status</span>
                            <span class="debug-value">
                                <span class="status-badge ${debug.integrationKeys.status === 'Connected' ? 'status-success' : 'status-error'}">
                                    ${debug.integrationKeys.status}
                                </span>
                            </span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Keys Found</span>
                            <span class="debug-value">${debug.integrationKeys.count}</span>
                        </div>
                        ${debug.integrationKeys.keys && debug.integrationKeys.keys.length > 0 ? `
                            <div style="margin-top: 10px;">
                                ${debug.integrationKeys.keys.map((k, i) => `
                                    <div class="debug-item">
                                        <span class="debug-label">Key ${i + 1}</span>
                                        <span class="debug-value">
                                            ${k.name} 
                                            <span class="status-badge ${k.active ? 'status-success' : 'status-warning'}">
                                                ${k.active ? 'Active' : 'Inactive'}
                                            </span>
                                        </span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            // Routes
            if (debug.routes) {
                html += `
                    <div class="debug-card">
                        <h2><span class="icon">🛣️</span> Available Routes</h2>
                        ${Object.entries(debug.routes).map(([key, value]) => `
                            <div class="debug-item">
                                <span class="debug-label">${key}</span>
                                <span class="debug-value">${value}</span>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            // Performance
            if (debug.performance) {
                html += `
                    <div class="debug-card">
                        <h2><span class="icon">⚡</span> Performance</h2>
                        <div class="debug-item">
                            <span class="debug-label">Response Time</span>
                            <span class="debug-value">${debug.performance.responseTimeMs}ms</span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Server Uptime</span>
                            <span class="debug-value">${Math.floor(debug.uptime)}s</span>
                        </div>
                    </div>
                `;
            }
            
            // Raw JSON
            html += `
                <div class="debug-card">
                    <h2><span class="icon">📄</span> Raw JSON Response</h2>
                    <div class="code-block">${JSON.stringify(debugData, null, 2)}</div>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function copyAllDebug() {
            const allText = document.body.innerText;
            navigator.clipboard.writeText(allText).then(() => {
                alert('✓ All debug information copied to clipboard!');
            }).catch(err => {
                alert('✗ Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>
