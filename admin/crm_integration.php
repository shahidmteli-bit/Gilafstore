<?php
/**
 * WhatsApp CRM Integration Panel
 * Central hub for managing GilafStore ↔ WACRM integration.
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // Ensure clean output - no PHP warnings/errors in JSON response
    ob_clean();
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // Catch all errors and return as JSON
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $errstr]);
        exit;
    });

    switch ($action) {
        case 'test_connection':
            // Simple connection test
            $baseUrl = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_api_url'");
            $url = rtrim($baseUrl['setting_value'] ?? 'http://localhost:3000', '/') . '/api/integration/health';
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $latency = (int)((microtime(true) - $startTime) * 1000);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            echo json_encode([
                'connected' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'latency_ms' => $latency,
                'error' => $error ?: null,
                'response' => json_decode($response, true),
            ]);
            exit;

        case 'save_settings':
            try {
                $settings = $_POST['settings'] ?? [];
                $savedKeys = [];
                
                foreach ($settings as $key => $value) {
                    if (strpos($key, 'crm_') === 0 || strpos($key, 'whatsapp_') === 0) {
                        $stmt = $pdo->prepare("UPDATE crm_settings SET setting_value = ? WHERE setting_key = ?");
                        $stmt->execute([$value, $key]);
                        $savedKeys[] = $key;
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Settings saved', 'saved_keys' => $savedKeys]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'toggle_crm':
            $newState = $_POST['enabled'] === '1' ? '1' : '0';
            $pdo->prepare("UPDATE crm_settings SET setting_value = ? WHERE setting_key = 'crm_enabled'")->execute([$newState]);
            echo json_encode(['success' => true, 'enabled' => $newState === '1']);
            exit;

        default:
            echo json_encode(['error' => 'Unknown action']);
            exit;
    }
}

// Fetch data for display
$stats = [
    'total_synced_customers' => 0,
    'recovered_carts' => 0,
    'recovered_revenue' => 0,
    'webhook_success_rate' => 100,
    'pending_events' => 0,
    'failed_events' => 0,
];

$apiKey = db_fetch_one("SELECT * FROM crm_api_keys WHERE is_active = 1 LIMIT 1");
$isEnabledRow = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_enabled'");
$isEnabled = ($isEnabledRow['setting_value'] ?? '0') === '1';

// Recent webhook logs
try {
    $recentLogs = db_fetch_all(
        "SELECT * FROM crm_webhook_logs ORDER BY created_at DESC LIMIT 20"
    );
} catch (\PDOException $e) {
    $recentLogs = [];
}

// Recent activity
try {
    $recentActivity = db_fetch_all(
        "SELECT * FROM crm_activity_log ORDER BY created_at DESC LIMIT 15"
    );
} catch (\PDOException $e) {
    $recentActivity = [];
}

// All settings
try {
    $allSettings = db_fetch_all("SELECT * FROM crm_settings ORDER BY setting_key");
} catch (\PDOException $e) {
    $allSettings = [];
}

// Notification templates
try {
    $templates = db_fetch_all("SELECT * FROM crm_notification_templates ORDER BY category, template_key");
} catch (\PDOException $e) {
    $templates = [];
}

$pageTitle = 'WhatsApp CRM Integration — Gilaf Admin';
$adminPage = 'crm_integration';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.crm-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.crm-status-badge.online { background: #dcfce7; color: #166534; }
.crm-status-badge.offline { background: #fef2f2; color: #991b1b; }
.crm-stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #f0f0f0; transition: transform 0.2s; }
.crm-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.crm-stat-card .stat-value { font-size: 28px; font-weight: 700; color: #1a1a1a; }
.crm-stat-card .stat-label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
.crm-stat-card .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.crm-section { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #f0f0f0; margin-bottom: 20px; }
.crm-section h5 { font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.crm-tab-content { display: none; }
.crm-tab-content.active { display: block; }
.crm-nav-tab { cursor: pointer; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 13px; transition: all 0.2s; border: 1px solid transparent; }
.crm-nav-tab:hover { background: #f3f4f6; }
.crm-nav-tab.active { background: #25D366; color: #fff; border-color: #25D366; }
.wa-gradient { background: linear-gradient(135deg, #25D366, #128C7E); }
.log-row { font-size: 12px; border-bottom: 1px solid #f3f4f6; padding: 8px 0; }
.log-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.toggle-switch { position: relative; width: 48px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: 0.3s; }
.toggle-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
.toggle-switch input:checked + .toggle-slider { background: #25D366; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }
.api-key-display { font-family: 'Courier New', monospace; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; font-size: 13px; word-break: break-all; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center wa-gradient" style="width:52px;height:52px;">
                <i class="fab fa-whatsapp text-white" style="font-size:26px;"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold">WhatsApp CRM Integration</h4>
                <small class="text-muted">GilafStore.com ↔ WACRM Real-time Sync</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="crmStatusBadge" class="crm-status-badge <?= $isEnabled ? 'online' : 'offline' ?>">
                <i class="fas fa-circle" style="font-size:8px;"></i>
                <span id="crmStatusText"><?= $isEnabled ? 'Connected' : 'Disconnected' ?></span>
            </span>
            <label class="toggle-switch">
                <input type="checkbox" id="crmToggle" <?= $isEnabled ? 'checked' : '' ?> onchange="toggleCRM(this)">
                <span class="toggle-slider"></span>
            </label>
            <button class="btn btn-sm btn-outline-success" onclick="testConnection()">
                <i class="fas fa-plug me-1"></i> Test Connection
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="crm-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($stats['total_synced_customers'] ?? 0) ?></div>
                        <div class="stat-label">Synced Customers</div>
                    </div>
                    <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="crm-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($stats['recovered_carts'] ?? 0) ?></div>
                        <div class="stat-label">Recovered Carts</div>
                    </div>
                    <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="crm-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">₹<?= number_format($stats['recovered_revenue'] ?? 0) ?></div>
                        <div class="stat-label">Recovered Revenue</div>
                    </div>
                    <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-rupee-sign"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="crm-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($stats['webhook_success_rate'] ?? 100, 1) ?>%</div>
                        <div class="stat-label">Webhook Success</div>
                    </div>
                    <div class="stat-icon" style="background:#f3e8ff;color:#6b21a8;"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <div class="crm-nav-tab active" onclick="switchTab('connection')"><i class="fas fa-link me-1"></i> Connection</div>
        <div class="crm-nav-tab" onclick="switchTab('api_keys')"><i class="fas fa-key me-1"></i> API Keys</div>
        <div class="crm-nav-tab" onclick="switchTab('webhooks')"><i class="fas fa-exchange-alt me-1"></i> Webhooks</div>
        <a href="crm_debug.php" class="crm-nav-tab" style="text-decoration:none;"><i class="fas fa-tools me-1"></i> Debug Panel</a>
        <div class="crm-nav-tab" onclick="switchTab('whatsapp')"><i class="fab fa-whatsapp me-1"></i> WhatsApp</div>
        <div class="crm-nav-tab" onclick="switchTab('otp')"><i class="fas fa-key me-1"></i> OTP</div>
        <div class="crm-nav-tab" onclick="switchTab('cart_recovery')"><i class="fas fa-shopping-cart me-1"></i> Cart Recovery</div>
        <div class="crm-nav-tab" onclick="switchTab('notifications')"><i class="fas fa-bell me-1"></i> Notifications</div>
        <div class="crm-nav-tab" onclick="switchTab('templates')"><i class="fas fa-file-alt me-1"></i> Templates</div>
        <div class="crm-nav-tab" onclick="switchTab('sync')"><i class="fas fa-sync me-1"></i> Customer Sync</div>
        <div class="crm-nav-tab" onclick="switchTab('logs')"><i class="fas fa-history me-1"></i> Logs</div>
    </div>

    <!-- TAB: Connection Status -->
    <div id="tab-connection" class="crm-tab-content active">
        <div class="crm-section">
            <h5><i class="fas fa-server text-success"></i> CRM Connection Settings</h5>
            <form id="connectionForm" onsubmit="return saveSettings(this)" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">WACRM API URL</label>
                        <input type="text" class="form-control" name="settings[crm_api_url]"
                               value="<?php 
                                   $url = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_api_url'");
                                   echo htmlspecialchars($url['setting_value'] ?? 'http://localhost:3000');
                               ?>"
                               placeholder="https://wacrm-wyjo.onrender.com or http://localhost:3000"
                               pattern="https?://.*"
                               title="Enter full URL including https://">
                        <small class="text-muted">Base URL of your WACRM instance (e.g., https://wacrm-wyjo.onrender.com)</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Webhook Secret</label>
                        <input type="text" class="form-control" name="settings[crm_webhook_secret]" 
                               value="<?php 
                                   $secret = db_fetch_one("SELECT setting_value FROM crm_settings WHERE setting_key = 'crm_webhook_secret'");
                                   echo htmlspecialchars($secret['setting_value'] ?? '');
                               ?>" 
                               placeholder="Auto-generated if empty">
                        <small class="text-muted">Shared secret for webhook signature verification</small>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Connection Settings</button>
                    <button type="button" class="btn btn-outline-success ms-2" onclick="testConnection()">
                        <i class="fas fa-plug me-1"></i> Test Connection
                    </button>
                </div>
            </form>
            <div id="connectionResult" class="mt-3" style="display:none;"></div>
        </div>
    </div>

    <!-- TAB: API Keys -->
    <div id="tab-api_keys" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-key text-warning"></i> API Key Management</h5>
            <?php if ($apiKey): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Active API Key</label>
                <div class="api-key-display">
                    <div class="d-flex justify-content-between align-items-center">
                        <span id="apiKeyValue"><?= htmlspecialchars($apiKey['api_key']) ?></span>
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('apiKeyValue')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <small class="text-muted">Name: <?= htmlspecialchars($apiKey['key_name']) ?> | Last used: <?= $apiKey['last_used_at'] ?? 'Never' ?></small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">API Secret</label>
                <div class="api-key-display">
                    <div class="d-flex justify-content-between align-items-center">
                        <span id="apiSecretValue"><?= htmlspecialchars($apiKey['api_secret']) ?></span>
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('apiSecretValue')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <small class="text-muted">Keep this secret! Used for HMAC signature verification.</small>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">No API key found. Generate one below.</div>
            <?php endif; ?>
            <button class="btn btn-warning" onclick="generateApiKey()">
                <i class="fas fa-plus me-1"></i> Generate New API Key
            </button>
        </div>
    </div>

    <!-- TAB: Webhooks -->
    <div id="tab-webhooks" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-exchange-alt text-info"></i> Webhook Delivery Log</h5>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="badge bg-success"><?= $stats['pending_events'] ?? 0 ?> Pending</span>
                    <span class="badge bg-danger ms-1"><?= $stats['failed_events'] ?? 0 ?> Failed</span>
                </div>
                <button class="btn btn-sm btn-outline-warning" onclick="retryFailed()">
                    <i class="fas fa-redo me-1"></i> Retry Failed
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Time</th><th>Direction</th><th>Event</th><th>Status</th><th>Code</th><th>Duration</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr class="log-row">
                            <td><?= date('M d H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><span class="badge <?= $log['direction'] === 'outgoing' ? 'bg-primary' : 'bg-info' ?>"><?= $log['direction'] ?></span></td>
                            <td><code><?= htmlspecialchars($log['event_type']) ?></code></td>
                            <td>
                                <span class="log-badge <?= $log['status'] === 'delivered' ? 'bg-success text-white' : ($log['status'] === 'failed' ? 'bg-danger text-white' : 'bg-warning') ?>">
                                    <?= $log['status'] ?>
                                </span>
                            </td>
                            <td><?= $log['response_code'] ?? '-' ?></td>
                            <td><?= $log['duration_ms'] ?? '-' ?>ms</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentLogs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No webhook logs yet. Events will appear here once integration is active.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: WhatsApp Settings -->
    <div id="tab-whatsapp" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fab fa-whatsapp text-success"></i> WhatsApp Configuration</h5>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-1"></i> WhatsApp Business API credentials are configured in the WACRM dashboard. 
                This panel controls which automated messages are sent from GilafStore.
            </div>
            <form onsubmit="return saveSettings(this)">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="settings[order_notifications_enabled]" value="1">
                            <label class="form-check-label fw-semibold">Order Lifecycle Notifications</label>
                        </div>
                        <small class="text-muted">Send WhatsApp notifications for order status changes</small>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="settings[cart_recovery_enabled]" value="1">
                            <label class="form-check-label fw-semibold">Cart Recovery Messages</label>
                        </div>
                        <small class="text-muted">Send reminders for abandoned carts</small>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="settings[whatsapp_otp_enabled]" value="1">
                            <label class="form-check-label fw-semibold">WhatsApp OTP Login</label>
                        </div>
                        <small class="text-muted">Allow customers to login via WhatsApp OTP</small>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="settings[customer_sync_enabled]" value="1">
                            <label class="form-check-label fw-semibold">Auto Customer Sync</label>
                        </div>
                        <small class="text-muted">Automatically sync customer data to CRM</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save</button>
            </form>
        </div>
    </div>

    <!-- TAB: OTP Settings -->
    <div id="tab-otp" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-shield-alt text-primary"></i> WhatsApp OTP Configuration</h5>
            <form onsubmit="return saveSettings(this)">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">OTP Expiry (seconds)</label>
                        <input type="number" class="form-control" name="settings[whatsapp_otp_expiry]" 
                               value="300" min="60" max="600">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Max Verification Attempts</label>
                        <input type="number" class="form-control" name="settings[whatsapp_otp_max_attempts]" 
                               value="5" min="3" max="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Resend Cooldown (seconds)</label>
                        <input type="number" class="form-control" name="settings[whatsapp_otp_resend_cooldown]" 
                               value="60" min="30" max="300">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rate Limit (per phone/hour)</label>
                        <input type="number" class="form-control" name="settings[whatsapp_otp_rate_limit]" 
                               value="10" min="3" max="20">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save OTP Settings</button>
            </form>
            <hr>
            <h6>Today's OTP Statistics</h6>
            <div class="row g-3">
                <div class="col-md-3"><div class="p-3 bg-light rounded"><strong><?= $stats['otps_sent_today'] ?? 0 ?></strong><br><small class="text-muted">OTPs Sent Today</small></div></div>
            </div>
        </div>
    </div>

    <!-- TAB: Cart Recovery -->
    <div id="tab-cart_recovery" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-shopping-cart text-warning"></i> Abandoned Cart Recovery</h5>
            <form onsubmit="return saveSettings(this)">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">1st Reminder Delay (minutes)</label>
                        <input type="number" class="form-control" name="settings[cart_recovery_delay_1]" 
                               value="15" min="5" max="60">
                        <small class="text-muted">Gentle reminder</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">2nd Reminder Delay (minutes)</label>
                        <input type="number" class="form-control" name="settings[cart_recovery_delay_2]" 
                               value="60" min="30" max="240">
                        <small class="text-muted">Urgency message</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">3rd Reminder Delay (minutes)</label>
                        <input type="number" class="form-control" name="settings[cart_recovery_delay_3]" 
                               value="1440" min="120" max="2880">
                        <small class="text-muted">Discount offer</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Recovery Discount Code</label>
                        <input type="text" class="form-control" name="settings[cart_recovery_discount]" 
                               value="" 
                               placeholder="e.g., COMEBACK10">
                        <small class="text-muted">Promo code offered in 3rd reminder</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save Cart Recovery Settings</button>
            </form>
            <hr>
            <div class="row g-3">
                <div class="col-md-3"><div class="p-3 bg-light rounded"><strong><?= $stats['active_abandoned_carts'] ?? 0 ?></strong><br><small>Active Abandoned</small></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded"><strong><?= $stats['recovered_carts'] ?? 0 ?></strong><br><small>Recovered</small></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded"><strong>₹<?= number_format($stats['recovered_revenue'] ?? 0) ?></strong><br><small>Revenue Recovered</small></div></div>
            </div>
        </div>
    </div>

    <!-- TAB: Order Notifications -->
    <div id="tab-notifications" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-bell text-primary"></i> Order Lifecycle Notifications</h5>
            <p class="text-muted">Configure which order events trigger WhatsApp notifications.</p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Event</th><th>Template</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php 
                    $orderEvents = ['order_placed','payment_success','payment_failed','order_packed','order_shipped','out_for_delivery','order_delivered','refund_initiated','cod_confirmation'];
                    foreach ($orderEvents as $evt): 
                        $tpl = array_filter($templates, function($t) use ($evt) { return $t['template_key'] === $evt; });
                        $tpl = reset($tpl);
                    ?>
                        <tr>
                            <td><code><?= $evt ?></code></td>
                            <td><?= $tpl ? htmlspecialchars($tpl['template_name']) : '<span class="text-muted">Not configured</span>' ?></td>
                            <td><?= $tpl && $tpl['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted">Notifications sent today: <strong><?= $stats['notifications_sent_today'] ?? 0 ?></strong></small>
        </div>
    </div>

    <!-- TAB: Templates -->
    <div id="tab-templates" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-file-alt text-info"></i> Notification Templates</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Key</th><th>Name</th><th>Category</th><th>Channel</th><th>WA Template</th><th>Active</th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $tpl): ?>
                        <tr>
                            <td><code style="font-size:11px;"><?= htmlspecialchars($tpl['template_key']) ?></code></td>
                            <td><?= htmlspecialchars($tpl['template_name']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= $tpl['category'] ?></span></td>
                            <td><span class="badge bg-success"><?= $tpl['channel'] ?></span></td>
                            <td><code style="font-size:11px;"><?= htmlspecialchars($tpl['whatsapp_template_name'] ?? '-') ?></code></td>
                            <td><?= $tpl['is_active'] ? '✓' : '✗' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: Customer Sync -->
    <div id="tab-sync" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-sync text-success"></i> Customer Synchronization</h5>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="p-3 bg-light rounded"><strong><?= $stats['total_synced_customers'] ?? 0 ?></strong><br><small>Synced</small></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded"><strong><?= $stats['pending_sync'] ?? 0 ?></strong><br><small>Pending</small></div></div>
            </div>
            <form onsubmit="return saveSettings(this)">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sync Interval (seconds)</label>
                    <input type="number" class="form-control" name="settings[customer_sync_interval]" 
                           value="300" min="60" max="3600">
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save</button>
            </form>
            <hr>
            <button class="btn btn-success" onclick="syncAllCustomers()">
                <i class="fas fa-sync me-1"></i> Sync All Customers Now
            </button>
            <div id="syncResult" class="mt-2"></div>
        </div>
    </div>

    <!-- TAB: Activity Logs -->
    <div id="tab-logs" class="crm-tab-content">
        <div class="crm-section">
            <h5><i class="fas fa-history text-secondary"></i> Activity Log</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentActivity as $act): ?>
                        <tr class="log-row">
                            <td><?= date('M d H:i', strtotime($act['created_at'])) ?></td>
                            <td><span class="badge bg-light text-dark"><?= $act['actor_type'] ?></span></td>
                            <td><code><?= htmlspecialchars($act['action']) ?></code></td>
                            <td><?= $act['entity_type'] ? $act['entity_type'] . '#' . $act['entity_id'] : '-' ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(substr($act['details'] ?? '', 0, 80)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentActivity)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No activity logged yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.crm-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.crm-nav-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.currentTarget.classList.add('active');
}

function toggleCRM(el) {
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_crm&enabled=' + (el.checked ? '1' : '0')
    })
    .then(r => r.json())
    .then(data => {
        const badge = document.getElementById('crmStatusBadge');
        const text = document.getElementById('crmStatusText');
        if (data.enabled) {
            badge.className = 'crm-status-badge online';
            text.textContent = 'Connected';
        } else {
            badge.className = 'crm-status-badge offline';
            text.textContent = 'Disconnected';
        }
    });
}

function testConnection() {
    showToast('<i class="fas fa-spinner fa-spin me-1"></i> Testing connection...', 'info');

    fetch(location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=test_connection'
    })
    .then(r => r.json())
    .then(data => {
        const resultDiv = document.getElementById('connectionResult');

        // Build detailed message
        let errorDetails = '';
        if (!data.connected && data.debug) {
            errorDetails += '<br><small class="text-muted">';
            errorDetails += 'URL tested: ' + (data.url_tested || data.debug.baseUrl || 'N/A') + '<br>';
            if (data.debug.endpoint) {
                errorDetails += 'Endpoint: ' + data.debug.endpoint + '<br>';
            }
            if (data.debug.httpCode) {
                errorDetails += 'HTTP Code: ' + data.debug.httpCode + '<br>';
            }
            if (data.debug.curlError) {
                errorDetails += 'cURL Error: ' + data.debug.curlError + '<br>';
            }
            if (data.url_debug) {
                errorDetails += 'Raw URL: ' + (data.url_debug.raw || 'N/A') + '<br>';
            }
            errorDetails += '</small>';
        }

        if (data.connected) {
            const msg = '<i class="fas fa-check-circle me-1"></i> <strong>Connected!</strong> Latency: ' + data.latency_ms + 'ms | Service: ' + (data.response && data.response.service ? data.response.service : 'wacrm');
            showToast(msg, 'success');
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert alert-success">' + msg + '</div>';
            }
        } else {
            let errorMsg = data.error || 'Unknown error';
            const msg = '<i class="fas fa-times-circle me-1"></i> <strong>Connection Failed</strong> — ' + errorMsg + errorDetails;
            showToast('<i class="fas fa-times-circle me-1"></i> ' + errorMsg, 'danger');
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
            }

            // Log debug info to console for troubleshooting
            console.log('[CRM Debug]', data.debug);
        }
    })
    .catch(err => {
        showToast('<i class="fas fa-times-circle me-1"></i> Network error: ' + err.message, 'danger');
        console.error('[CRM Error]', err);
    });
}

function saveSettings(form) {
    showToast('<i class="fas fa-spinner fa-spin me-1"></i> Saving settings...', 'info');

    const formData = new FormData(form);
    formData.append('action', 'save_settings');

    // Debug: Log what's being sent
    console.log('[SaveSettings] Sending:', Object.fromEntries(formData));

    fetch(location.href, { method: 'POST', body: formData })
    .then(async r => {
        const text = await r.text();
        console.log('[SaveSettings] Raw response:', text);
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('[SaveSettings] JSON parse error:', e);
            throw new Error('Invalid JSON response: ' + text.substring(0, 200));
        }
    })
    .then(data => {
        console.log('[SaveSettings] Parsed:', data);
        if (data.success) {
            showToast('<i class="fas fa-check-circle me-1"></i> ' + (data.message || 'Settings saved successfully!'), 'success');
        } else {
            showToast('<i class="fas fa-times-circle me-1"></i> ' + (data.error || 'Failed to save settings'), 'danger');
        }
    })
    .catch(err => {
        console.error('[SaveSettings] Error:', err);
        showToast('<i class="fas fa-times-circle me-1"></i> Save failed: ' + err.message, 'danger');
    });
    return false;
}

function generateApiKey() {
    if (!confirm('Generate a new API key? The old key will remain active.')) return;
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=generate_api_key&key_name=Generated ' + new Date().toLocaleDateString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('New API key generated! Reload page to see it.', 'success');
            setTimeout(() => location.reload(), 1500);
        }
    });
}

function retryFailed() {
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=retry_failed'
    })
    .then(r => r.json())
    .then(data => {
        showToast(`Processed ${data.processed} events`, 'success');
    });
}

function syncAllCustomers() {
    document.getElementById('syncResult').innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Syncing...</span>';
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=sync_all_customers'
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('syncResult').innerHTML = `<span class="text-success"><i class="fas fa-check"></i> Synced ${data.synced}/${data.total} customers</span>`;
    });
}

function copyToClipboard(id) {
    const text = document.getElementById(id).textContent;
    navigator.clipboard.writeText(text).then(() => showToast('Copied to clipboard!', 'info'));
}

function showToast(message, type) {
    const uid = 'toast_' + Math.random().toString(36).substr(2,6);
    const bgColor = type === 'success' ? '#25D366' : type === 'danger' ? '#dc3545' : '#0d6efd';
    const html = `<div id="${uid}" style="position:fixed;top:16px;right:16px;z-index:99999;min-width:280px;background:${bgColor};color:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.18);padding:14px 20px;font-size:14px;font-weight:500;animation:flashIn .4s ease;">
        ${message}
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    setTimeout(() => { const el = document.getElementById(uid); if(el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }}, 3000);
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
