<?php
/**
 * CRM Integration Debug Panel
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php'; // ISSUE-009: CSRF protection

require_admin();

// Initialize CRM Engine safely
$crm = null;
$crmError = null;
try {
    require_once __DIR__ . '/../includes/crm_engine.php';
    $crm = CRMEngine::getInstance();
} catch (Exception $e) {
    $crmError = $e->getMessage();
}

// Handle AJAX debug request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'fetch_debug') {
    header('Content-Type: application/json');

    // ISSUE-009: Validate CSRF token before executing any admin action
    require_csrf_token();

    if (!$crm) {
        echo json_encode(['success' => false, 'error' => 'CRM Engine not available']);
        exit;
    }
    
    try {
        $baseUrl = $crm->getBaseUrl();
        $url = rtrim($baseUrl, '/') . '/api/debug';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo json_encode([
            'success' => $httpCode >= 200 && $httpCode < 300,
            'data' => json_decode($response, true),
            'http_code' => $httpCode,
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get local diagnostics (safe)
$localDebug = [
    'php_version' => phpversion(),
    'server_os' => php_uname(),
    'database' => isset($pdo) ? 'Connected' : 'Error',
    'crm_enabled' => $crm ? ($crm->isEnabled() ? 'Yes' : 'No') : 'N/A',
    'base_url' => $crm ? $crm->getBaseUrl() : 'N/A',
    'api_key_exists' => $crm ? ($crm->getActiveApiKey() ? 'Yes' : 'No') : 'N/A',
    'curl' => extension_loaded('curl') ? 'Yes' : 'No',
    'openssl' => extension_loaded('openssl') ? 'Yes' : 'No',
];

require_once __DIR__ . '/admin_header.php';
// ISSUE-009: Output CSRF token as <meta> tag for JavaScript fetch() calls
echo csrf_meta();
?>

<?php if ($crmError): ?>
<div class="alert alert-warning m-3">
    <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> CRM Engine initialization failed: <?= htmlspecialchars($crmError) ?>
</div>
<?php endif; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-bug text-info"></i> CRM Debug Panel</h2>
            <p class="text-muted">Diagnostic information for troubleshooting</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="crm_integration.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to CRM
            </a>
        </div>
    </div>

    <!-- Local Diagnostics -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-desktop"></i> Local Diagnostics</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td>PHP Version</td><td><?= $localDebug['php_version'] ?></td></tr>
                        <tr><td>Database</td><td><span class="badge bg-success"><?= $localDebug['database'] ?></span></td></tr>
                        <tr><td>CRM Enabled</td><td><span class="badge <?= $crm && $crm->isEnabled() ? 'bg-success' : 'bg-secondary' ?>"><?= $localDebug['crm_enabled'] ?></span></td></tr>
                        <tr><td>Base URL</td><td><?= $localDebug['base_url'] ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td>API Key</td><td><span class="badge <?= $crm && $crm->getActiveApiKey() ? 'bg-success' : 'bg-warning' ?>"><?= $localDebug['api_key_exists'] ?></span></td></tr>
                        <tr><td>cURL</td><td><span class="badge bg-success"><?= $localDebug['curl'] ?></span></td></tr>
                        <tr><td>OpenSSL</td><td><span class="badge bg-success"><?= $localDebug['openssl'] ?></span></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- WACRM Debug Info -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-globe"></i> WACRM Remote Diagnostics</h5>
        </div>
        <div class="card-body">
            <button class="btn btn-primary" onclick="fetchRemoteDebug()" <?= $crm ? '' : 'disabled' ?>>
                <i class="fas fa-download"></i> Fetch WACRM Debug Info
            </button>
            <div id="remoteDebug" class="mt-3"></div>
        </div>
    </div>

    <!-- Connection Test -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-plug"></i> Quick Connection Test</h5>
        </div>
        <div class="card-body">
            <button class="btn btn-success" onclick="testConnection()">
                <i class="fas fa-play"></i> Test Connection to WACRM
            </button>
            <div id="testResult" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
// ISSUE-009: Read CSRF token from <meta name="csrf-token"> for all AJAX requests
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function fetchRemoteDebug() {
    const container = document.getElementById('remoteDebug');
    container.innerHTML = '<div class="alert alert-info">Fetching...</div>';

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': getCsrfToken()  // ISSUE-009
        },
        body: 'action=fetch_debug'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = '<pre class="bg-light p-3 rounded">' + JSON.stringify(data.data, null, 2) + '</pre>';
        } else {
            container.innerHTML = '<div class="alert alert-danger">Error: ' + (data.error || 'Failed to fetch') + '</div>';
        }
    })
    .catch(err => {
        container.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}

function testConnection() {
    const container = document.getElementById('testResult');
    container.innerHTML = '<div class="alert alert-info">Testing...</div>';

    fetch('crm_integration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': getCsrfToken()  // ISSUE-009
        },
        body: 'action=test_connection'
    })
    .then(r => r.json())
    .then(data => {
        if (data.connected) {
            container.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> Connected! Latency: ' + data.latency_ms + 'ms</div>';
        } else {
            container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> Failed: ' + (data.error || 'Unknown error') + '</div>';
        }
    })
    .catch(err => {
        container.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
