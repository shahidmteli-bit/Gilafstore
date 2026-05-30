<?php
/**
 * WhatsApp CRM Integration Panel
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

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    // ISSUE-009: Validate CSRF token — rejects with HTTP 403 if token is missing or invalid.
    // Token is read from X-CSRF-TOKEN header (AJAX) or csrf_token POST field (form fallback).
    require_csrf_token();

    $action = $_POST['action'];

    if (!$crm) {
        echo json_encode(['success' => false, 'error' => 'CRM Engine not available: ' . $crmError]);
        exit;
    }
    
    switch ($action) {
        case 'test_connection':
            $result = $crm->testConnection();
            echo json_encode($result);
            exit;

        case 'save_settings':
            try {
                $settings = $_POST['settings'] ?? [];
                foreach ($settings as $key => $value) {
                    if (strpos($key, 'crm_') === 0 || strpos($key, 'whatsapp_') === 0) {
                        $crm->updateSetting($key, $value);
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Settings saved']);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'toggle_crm':
            $newState = $_POST['enabled'] === '1';
            $crm->updateSetting('crm_enabled', $newState);
            echo json_encode(['success' => true, 'enabled' => $newState]);
            exit;

        case 'generate_api_key':
            $result = $crm->generateApiKey('Integration Key');
            echo json_encode(['success' => true, 'data' => $result]);
            exit;

        default:
            echo json_encode(['error' => 'Unknown action']);
            exit;
    }
}

// Get data for display (safe fallback if CRM not available)
$stats = $crm ? $crm->getStats() : [];
$apiKey = $crm ? $crm->getActiveApiKey() : null;
$isEnabled = $crm ? $crm->isEnabled() : false;
$baseUrl = $crm ? $crm->getBaseUrl() : '';

// Get settings for display (safe query)
$settings = [];
if ($pdo) {
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM crm_settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\PDOException $e) {
        // Tables may not exist - that's OK
    }
}

$pageTitle = 'WhatsApp CRM Integration - Gilaf Admin';
require_once __DIR__ . '/admin_header.php';
// ISSUE-009: Output CSRF token as <meta> tag for JavaScript fetch() calls
echo csrf_meta();
?>

<?php if ($crmError): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> CRM Engine initialization failed: <?= htmlspecialchars($crmError) ?><br>
    <small>This usually means the CRM database tables don't exist yet. Run the database migration first.</small>
</div>
<?php endif; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fab fa-whatsapp text-success"></i> WhatsApp CRM Integration</h2>
            <p class="text-muted">Manage GilafStore ↔ WACRM connection</p>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge <?= $isEnabled ? 'bg-success' : 'bg-secondary' ?> fs-6">
                <?= $isEnabled ? '🟢 Connected' : '⚪ Disconnected' ?>
            </span>
            <?php if ($crm): ?>
            <label class="form-check-label ms-3">
                <input type="checkbox" class="form-check-input" id="crmToggle" <?= $isEnabled ? 'checked' : '' ?> onchange="toggleCRM(this)">
                Enable CRM
            </label>
            <?php endif; ?>
        </div>
    </div>

    <!-- Connection Settings -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-server"></i> Connection Settings</h5>
        </div>
        <div class="card-body">
            <?php if (!$crm): ?>
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> CRM Engine is not available. Please check:
                <ul class="mt-2 mb-0">
                    <li>Database tables exist (run crm_migration.php)</li>
                    <li>crm_engine.php file exists in includes folder</li>
                    <li>No PHP errors in error log</li>
                </ul>
            </div>
            <?php endif; ?>
            
            <form id="connectionForm" onsubmit="return saveSettings(this)">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">WACRM API URL</label>
                        <input type="url" class="form-control" name="settings[crm_api_url]" 
                               value="<?= htmlspecialchars($baseUrl) ?>" 
                               placeholder="https://wacrm-wyjo.onrender.com"
                               <?= $crm ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Webhook Secret</label>
                        <input type="text" class="form-control" name="settings[crm_webhook_secret]" 
                               value="<?= htmlspecialchars($settings['crm_webhook_secret'] ?? '') ?>"
                               <?= $crm ? '' : 'disabled' ?>>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" <?= $crm ? '' : 'disabled' ?>><i class="fas fa-save"></i> Save Settings</button>
                <button type="button" class="btn btn-success ms-2" onclick="testConnection()" <?= $crm ? '' : 'disabled' ?>>
                    <i class="fas fa-plug"></i> Test Connection
                </button>
                <a href="crm_debug.php" class="btn btn-info ms-2">
                    <i class="fas fa-bug"></i> Debug Panel
                </a>
            </form>
            <div id="connectionResult" class="mt-3"></div>
        </div>
    </div>

    <!-- API Key -->
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="fas fa-key"></i> API Key</h5>
        </div>
        <div class="card-body">
            <?php if ($apiKey): ?>
                <div class="mb-3">
                    <label class="form-label">Active API Key</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($apiKey['api_key']) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?= htmlspecialchars($apiKey['api_key']) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">API Secret</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($apiKey['api_secret']) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?= htmlspecialchars($apiKey['api_secret']) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">No API key configured.</div>
            <?php endif; ?>
            <button class="btn btn-warning" onclick="generateApiKey()" <?= $crm ? '' : 'disabled' ?>>
                <i class="fas fa-plus"></i> Generate New API Key
            </button>
        </div>
    </div>

    <!-- Statistics -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistics</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h4><?= number_format($stats['total_synced_customers'] ?? 0) ?></h4>
                        <small class="text-muted">Synced Customers</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4><?= number_format($stats['recovered_carts'] ?? 0) ?></h4>
                        <small class="text-muted">Recovered Carts</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4>₹<?= number_format($stats['recovered_revenue'] ?? 0) ?></h4>
                        <small class="text-muted">Revenue Recovered</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4><?= number_format($stats['webhook_success_rate'] ?? 100, 1) ?>%</h4>
                        <small class="text-muted">Webhook Success</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ISSUE-009: Read CSRF token from <meta name="csrf-token"> for all AJAX requests
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function testConnection() {
    const resultDiv = document.getElementById('connectionResult');
    resultDiv.innerHTML = '<div class="alert alert-info">Testing connection...</div>';

    fetch(location.href, {
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
            resultDiv.innerHTML = `<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>Connected!</strong>
                Latency: ${data.latency_ms}ms
            </div>`;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> <strong>Connection Failed</strong><br>
                ${data.error || 'Unknown error'}
            </div>`;
        }
    })
    .catch(err => {
        resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
    });
}

function saveSettings(form) {
    const resultDiv = document.getElementById('connectionResult');
    resultDiv.innerHTML = '<div class="alert alert-info">Saving...</div>';

    const formData = new FormData(form);
    formData.append('action', 'save_settings');

    fetch(location.href, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken() },  // ISSUE-009
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success">Settings saved!</div>';
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
        }
    })
    .catch(err => {
        resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
    });
    return false;
}

function toggleCRM(el) {
    fetch(location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': getCsrfToken()  // ISSUE-009
        },
        body: 'action=toggle_crm&enabled=' + (el.checked ? '1' : '0')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function generateApiKey() {
    if (!confirm('Generate new API key?')) return;

    fetch(location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': getCsrfToken()  // ISSUE-009
        },
        body: 'action=generate_api_key'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('API key generated! Page will reload.');
            location.reload();
        }
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    });
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
