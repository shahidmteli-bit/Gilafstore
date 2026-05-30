<?php
/**
 * Shipping Partners — API + Manual Hybrid
 * Top tabs: API Integration | Non-API Integration
 * Each with sub-tabs: Domestic | International
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pageTitle = 'Shipping Partners';
$adminPage = 'shipping_partners';
$db = get_db_connection();

// ─── Auto-create / migrate tables ───
try {
    $db->exec("CREATE TABLE IF NOT EXISTS shipping_partners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL,
        partner_code VARCHAR(50) NOT NULL UNIQUE,
        partner_type ENUM('domestic','international','both') DEFAULT 'domestic',
        integration_type ENUM('api','manual') NOT NULL DEFAULT 'api',
        api_key_enc TEXT, api_secret_enc TEXT,
        webhook_url VARCHAR(500), webhook_secret_enc TEXT,
        account_number VARCHAR(100),
        base_url VARCHAR(500), sandbox_url VARCHAR(500),
        is_sandbox TINYINT(1) DEFAULT 1, is_active TINYINT(1) DEFAULT 0,
        extra_fields TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}
try { $db->exec("ALTER TABLE shipping_partners ADD COLUMN integration_type ENUM('api','manual') NOT NULL DEFAULT 'api' AFTER partner_type"); } catch (PDOException $e) {}

// ─── Encryption helpers (AES-256-CBC) ───
function sp_get_key() {
    $keyFile = dirname(__DIR__) . '/.gilaf_security_key';
    if (file_exists($keyFile)) return trim(file_get_contents($keyFile));
    return hash('sha256', 'gilaf_shipping_' . DB_NAME . '_secret_2026', true);
}
function sp_encrypt($plain) {
    if (empty($plain)) return '';
    $key = sp_get_key(); $iv = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . '::' . $cipher);
}
function sp_decrypt($enc) {
    if (empty($enc)) return '';
    $key = sp_get_key(); $data = base64_decode($enc);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return '';
    return openssl_decrypt($parts[1], 'AES-256-CBC', $key, 0, $parts[0]);
}
function sp_mask($val) {
    if (empty($val)) return '';
    $len = strlen($val);
    if ($len <= 8) return str_repeat("\u{2022}", $len);
    return substr($val, 0, 4) . str_repeat("\u{2022}", $len - 8) . substr($val, -4);
}

// ─── Predefined API carriers ───
$carriers = [
    'domestic' => [
        ['code'=>'shiprocket','name'=>'ShipRocket','icon'=>'fas fa-rocket'],
        ['code'=>'delhivery','name'=>'Delhivery','icon'=>'fas fa-box'],
        ['code'=>'dtdc','name'=>'DTDC Express','icon'=>'fas fa-truck'],
        ['code'=>'bluedart','name'=>'Blue Dart','icon'=>'fas fa-shipping-fast'],
        ['code'=>'indiapost','name'=>'India Post','icon'=>'fas fa-mail-bulk'],
        ['code'=>'ekart','name'=>'Ekart Logistics','icon'=>'fas fa-dolly'],
        ['code'=>'xpressbees','name'=>'XpressBees','icon'=>'fas fa-paw'],
        ['code'=>'ecom','name'=>'Ecom Express','icon'=>'fas fa-truck-loading'],
        ['code'=>'shadowfax','name'=>'Shadowfax','icon'=>'fas fa-horse'],
    ],
    'international' => [
        ['code'=>'dhl','name'=>'DHL Express','icon'=>'fas fa-globe'],
        ['code'=>'fedex','name'=>'FedEx','icon'=>'fas fa-plane'],
        ['code'=>'aramex','name'=>'Aramex','icon'=>'fas fa-globe-asia'],
        ['code'=>'dpworld','name'=>'DP World','icon'=>'fas fa-ship'],
        ['code'=>'ups','name'=>'UPS','icon'=>'fas fa-box-open'],
        ['code'=>'usps','name'=>'USPS','icon'=>'fas fa-mail-bulk'],
    ],
];

// ─── Handle POST ───
$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_partner') {
        $code = trim($_POST['partner_code'] ?? '');
        $name = trim($_POST['partner_name'] ?? '');
        $type = $_POST['partner_type'] ?? 'domestic';
        $apiKey = trim($_POST['api_key'] ?? '');
        $apiSecret = trim($_POST['api_secret'] ?? '');
        $webhookUrl = trim($_POST['webhook_url'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        $sandboxUrl = trim($_POST['sandbox_url'] ?? '');
        $isSandbox = isset($_POST['is_sandbox']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code) || empty($name)) {
            $flash = 'Partner name and code are required.'; $flashType = 'danger';
        } else {
            $exists = $db->prepare("SELECT id, api_key_enc, api_secret_enc, webhook_secret_enc FROM shipping_partners WHERE partner_code = ?");
            $exists->execute([$code]); $existing = $exists->fetch(PDO::FETCH_ASSOC);

            $apiKeyEnc = (!empty($apiKey) && strpos($apiKey, "\u{2022}") === false) ? sp_encrypt($apiKey) : ($existing['api_key_enc'] ?? '');
            $apiSecretEnc = (!empty($apiSecret) && strpos($apiSecret, "\u{2022}") === false) ? sp_encrypt($apiSecret) : ($existing['api_secret_enc'] ?? '');
            $webhookSecretEnc = (!empty($webhookSecret) && strpos($webhookSecret, "\u{2022}") === false) ? sp_encrypt($webhookSecret) : ($existing['webhook_secret_enc'] ?? '');

            if ($existing) {
                $stmt = $db->prepare("UPDATE shipping_partners SET partner_name=?, partner_type=?, integration_type='api', api_key_enc=?, api_secret_enc=?, webhook_url=?, webhook_secret_enc=?, account_number=?, base_url=?, sandbox_url=?, is_sandbox=?, is_active=? WHERE partner_code=?");
                $stmt->execute([$name, $type, $apiKeyEnc, $apiSecretEnc, $webhookUrl, $webhookSecretEnc, $accountNumber, $baseUrl, $sandboxUrl, $isSandbox, $isActive, $code]);
            } else {
                $stmt = $db->prepare("INSERT INTO shipping_partners (partner_name, partner_code, partner_type, integration_type, api_key_enc, api_secret_enc, webhook_url, webhook_secret_enc, account_number, base_url, sandbox_url, is_sandbox, is_active) VALUES (?,?,?,'api',?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $code, $type, $apiKeyEnc, $apiSecretEnc, $webhookUrl, $webhookSecretEnc, $accountNumber, $baseUrl, $sandboxUrl, $isSandbox, $isActive]);
            }
            $flash = htmlspecialchars($name) . ' configuration saved.'; $flashType = 'success';
        }

    } elseif ($action === 'toggle_partner') {
        $id = (int)($_POST['partner_id'] ?? 0);
        $db->prepare("UPDATE shipping_partners SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $flash = 'Partner status updated.';

    } elseif ($action === 'delete_partner') {
        $id = (int)($_POST['partner_id'] ?? 0);
        $db->prepare("DELETE FROM shipping_partners WHERE id = ?")->execute([$id]);
        $flash = 'Partner removed.';

    } elseif ($action === 'test_connection') {
        header('Content-Type: application/json');
        if (empty($_SESSION['admin'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM shipping_partners WHERE id = ?");
        $stmt->execute([$partnerId]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$partner) { echo json_encode(['success'=>false,'message'=>'Partner not found.']); exit; }

        $apiKey = sp_decrypt($partner['api_key_enc']);
        $apiSecret = sp_decrypt($partner['api_secret_enc']);
        $testUrl = $partner['is_sandbox'] ? ($partner['sandbox_url'] ?: $partner['base_url']) : ($partner['base_url'] ?: $partner['sandbox_url']);

        if (empty($testUrl)) { echo json_encode(['success'=>false,'message'=>'No API URL configured.']); exit; }

        $knownEndpoints = [
            'shiprocket'=>'/v1/external/auth/login','delhivery'=>'/api/kinko/v1/invoice/charges/.json',
            'dtdc'=>'/api/dtdc/authenticate','bluedart'=>'/API/Finder/SearchServiceFinder.svc/GetServicesforPincode/Pin/',
            'dhl'=>'/express/rates','fedex'=>'/rate/v1/rates/quotes',
        ];

        $code = strtolower($partner['partner_code']);
        $fullUrl = rtrim($testUrl, '/') . ($knownEndpoints[$code] ?? '');

        // Build request per carrier
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $isPost = false;
        $postBody = null;

        if ($code === 'shiprocket') {
            // Shiprocket: POST to /v1/external/auth/login with email + password
            // api_key stores email, api_secret stores password
            $isPost = true;
            $postBody = json_encode(['email' => $apiKey, 'password' => $apiSecret]);
            // Do NOT send Authorization header for login — the endpoint returns a token
        } else {
            // Other carriers: send Bearer token if available
            if (!empty($apiKey)) {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            }
        }

        $startTime = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
            // Maintain POST through redirects (301/302 can convert POST→GET)
            curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
        }
        $response = curl_exec($ch);
        $elapsed = round((microtime(true) - $startTime) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($curlError) {
            echo json_encode(['success'=>false,'message'=>'Connection failed: '.$curlError,'http_code'=>0,'latency_ms'=>$elapsed,'url_tested'=>$effectiveUrl]);
            exit;
        }

        // For Shiprocket, extract token on success
        $extra = [];
        if ($code === 'shiprocket' && $httpCode >= 200 && $httpCode < 300) {
            $respData = json_decode($response, true);
            if (!empty($respData['token'])) {
                // Store token encrypted in extra_fields for future API calls
                try {
                    $tokenEnc = sp_encrypt($respData['token']);
                    $db->prepare("UPDATE shipping_partners SET extra_fields = JSON_SET(COALESCE(extra_fields, '{}'), '$.auth_token', ?) WHERE id = ?")->execute([$tokenEnc, $partnerId]);
                    $extra['token_saved'] = true;
                } catch (Exception $e) {
                    $extra['token_saved'] = false;
                }
            }
        }

        $statusLabel = match(true) {
            $httpCode >= 200 && $httpCode < 300 => 'API reachable - Authentication successful',
            $httpCode === 401 || $httpCode === 403 => 'API reachable - Auth failed (check credentials)',
            $httpCode === 404 => 'API reachable - Endpoint not found',
            $httpCode >= 400 && $httpCode < 500 => 'API reachable - Client error',
            $httpCode >= 500 => 'API reachable - Server error on carrier side',
            default => 'Unexpected response',
        };
        $result = ['success'=>($httpCode>=200&&$httpCode<300),'message'=>$statusLabel,'http_code'=>$httpCode,'latency_ms'=>$elapsed,'url_tested'=>$effectiveUrl,'mode'=>$partner['is_sandbox']?'Sandbox':'Production'];
        if (!empty($extra)) $result['extra'] = $extra;
        echo json_encode($result);
        exit;

    } elseif ($action === 'reveal_key') {
        header('Content-Type: application/json');
        if (empty($_SESSION['admin'])) { echo json_encode(['error'=>'Unauthorized']); exit; }
        $field = $_POST['field'] ?? ''; $id = (int)($_POST['partner_id'] ?? 0);
        $allowed = ['api_key_enc','api_secret_enc','webhook_secret_enc'];
        if (!in_array($field, $allowed)) { echo json_encode(['error'=>'Invalid field']); exit; }
        $stmt = $db->prepare("SELECT {$field} FROM shipping_partners WHERE id = ?");
        $stmt->execute([$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['value' => $row ? sp_decrypt($row[$field]) : '']);
        exit;

    // ─── Manual courier actions ───
    } elseif ($action === 'save_manual') {
        $name = trim($_POST['manual_name'] ?? '');
        $scope = $_POST['manual_scope'] ?? 'domestic';
        $trackingUrl = trim($_POST['manual_tracking_url'] ?? '');
        $isActive = isset($_POST['manual_active']) ? 1 : 0;
        $editId = (int)($_POST['manual_edit_id'] ?? 0);

        if (empty($name)) { $flash = 'Courier name is required.'; $flashType = 'danger'; }
        else {
            if ($editId > 0) {
                $db->prepare("UPDATE shipping_partners SET partner_name=?, partner_type=?, base_url=?, is_active=? WHERE id=? AND integration_type='manual'")->execute([$name, $scope, $trackingUrl, $isActive, $editId]);
                $flash = htmlspecialchars($name) . ' updated.';
            } else {
                $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
                $code = trim($code, '_') . '_m' . time();
                $db->prepare("INSERT INTO shipping_partners (partner_name,partner_code,partner_type,integration_type,base_url,is_active) VALUES (?,?,?,'manual',?,?)")->execute([$name, $code, $scope, $trackingUrl, $isActive]);
                $flash = htmlspecialchars($name) . ' added as manual courier.';
            }
        }
    } elseif ($action === 'delete_manual') {
        $id = (int)($_POST['partner_id'] ?? 0);
        $db->prepare("DELETE FROM shipping_partners WHERE id=? AND integration_type='manual'")->execute([$id]);
        $flash = 'Manual courier removed.';
    } elseif ($action === 'toggle_manual') {
        $id = (int)($_POST['partner_id'] ?? 0);
        $db->prepare("UPDATE shipping_partners SET is_active = NOT is_active WHERE id=? AND integration_type='manual'")->execute([$id]);
        $flash = 'Courier status updated.';
    }
}

// ─── Fetch API partners ───
$savedPartners = [];
try {
    $rows = $db->query("SELECT * FROM shipping_partners WHERE integration_type='api' ORDER BY partner_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $savedPartners[$r['partner_code']] = $r;
} catch (PDOException $e) {}

$predefinedCodes = [];
foreach ($carriers as $group) { foreach ($group as $c) $predefinedCodes[] = $c['code']; }
$customApiPartners = ['domestic'=>[],'international'=>[]];
foreach ($savedPartners as $code => $p) {
    if (!in_array($code, $predefinedCodes)) {
        $t = ($p['partner_type']==='international') ? 'international' : 'domestic';
        $customApiPartners[$t][] = $p;
    }
}

// ─── Fetch Manual partners ───
$manualPartners = ['domestic'=>[],'international'=>[]];
try {
    $manualRows = $db->query("SELECT * FROM shipping_partners WHERE integration_type='manual' ORDER BY partner_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($manualRows as $m) {
        $t = ($m['partner_type']==='international') ? 'international' : 'domestic';
        $manualPartners[$t][] = $m;
    }
} catch (PDOException $e) {}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ═══ Shipping Partners Hybrid Styles ═══ */
.sp-page { max-width:1200px; margin:0 auto; padding:0; }
.sp-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.sp-header h1 { font-size:24px; font-weight:700; color:#1e293b; margin:0; }
.sp-header h1 i { color:#01875f; margin-right:8px; }

/* Top-level tabs */
.sp-top-tabs { display:flex; gap:0; margin-bottom:0; }
.sp-top-tab { padding:12px 28px; font-size:14px; font-weight:700; cursor:pointer; border:2px solid #e2e8f0; border-bottom:none; background:#f8fafc; color:#64748b; border-radius:10px 10px 0 0; transition:all .2s; margin-right:4px; }
.sp-top-tab.active { background:#fff; color:#01875f; border-color:#01875f; }
.sp-top-tab i { margin-right:6px; }
.sp-top-content { border:2px solid #01875f; border-radius:0 12px 12px 12px; padding:24px; background:#fff; }
.sp-top-panel { display:none; }
.sp-top-panel.active { display:block; }

/* Sub-tabs */
.sp-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin-bottom:20px; }
.sp-tab { padding:10px 20px; font-size:13px; font-weight:600; color:#64748b; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; background:none; border-top:none; border-left:none; border-right:none; }
.sp-tab:hover { color:#334155; }
.sp-tab.active { color:#01875f; border-bottom-color:#01875f; }
.sp-tab i { margin-right:6px; }

/* Cards */
.sp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }
.sp-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; transition:box-shadow .2s; position:relative; }
.sp-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.06); }
.sp-card.configured { border-left:4px solid #01875f; }
.sp-card.inactive { opacity:.7; }
.sp-card-head { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.sp-card-icon { width:44px; height:44px; border-radius:10px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:18px; color:#475569; flex-shrink:0; }
.sp-card.configured .sp-card-icon { background:#f0fdf4; color:#01875f; }
.sp-card-title { font-size:15px; font-weight:700; color:#1e293b; }
.sp-card-code { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; }
.sp-card-status { position:absolute; top:14px; right:14px; }
.sp-status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
.sp-status-dot.active { background:#22c55e; box-shadow:0 0 6px rgba(34,197,94,.4); }
.sp-status-dot.inactive { background:#cbd5e1; }
.sp-card-fields { font-size:13px; color:#64748b; margin-bottom:14px; }
.sp-card-fields .sp-field { display:flex; justify-content:space-between; align-items:center; padding:3px 0; border-bottom:1px solid #f1f5f9; }
.sp-card-fields .sp-field:last-child { border-bottom:none; }
.sp-field-label { font-weight:500; color:#475569; }
.sp-field-value { font-family:'Courier New',monospace; color:#1e293b; font-size:12px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sp-card-actions { display:flex; gap:6px; flex-wrap:wrap; }

/* Buttons */
.sp-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600; border:none; cursor:pointer; transition:all .2s; }
.sp-btn-primary { background:#01875f; color:#fff; }
.sp-btn-primary:hover { background:#016d4d; }
.sp-btn-outline { background:#fff; color:#475569; border:1px solid #e2e8f0; }
.sp-btn-outline:hover { background:#f8fafc; }
.sp-btn-danger { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.sp-btn-danger:hover { background:#fee2e2; }
.sp-btn-test { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.sp-btn-test:hover { background:#dbeafe; }
.sp-btn-test.testing { opacity:.7; pointer-events:none; }
.sp-btn-sm { padding:5px 10px; font-size:12px; }

/* Manual courier list */
.sp-manual-table { width:100%; border-collapse:collapse; font-size:14px; }
.sp-manual-table th { text-align:left; padding:10px 14px; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid #e2e8f0; background:#f8fafc; }
.sp-manual-table td { padding:12px 14px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.sp-manual-table tr:hover td { background:#f8fafc; }
.sp-manual-table .sp-m-name { font-weight:600; }
.sp-manual-table .sp-m-url { font-size:12px; color:#64748b; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sp-manual-add-row { background:#f0fdf4; border:2px dashed #bbf7d0; border-radius:10px; padding:16px; text-align:center; cursor:pointer; color:#166534; font-weight:600; font-size:14px; margin-top:16px; transition:background .2s; }
.sp-manual-add-row:hover { background:#dcfce7; }

/* Test result */
.sp-test-result { margin:0 24px; padding:14px 16px; border-radius:10px; font-size:13px; animation:spFadeIn .3s ease; }
.sp-test-result.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
.sp-test-result.fail { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.sp-test-result.warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.sp-test-result .sp-test-head { font-weight:700; font-size:14px; margin-bottom:6px; display:flex; align-items:center; gap:8px; }
.sp-test-result .sp-test-details { display:grid; grid-template-columns:auto 1fr; gap:4px 12px; font-size:12px; opacity:.85; }
.sp-test-result .sp-test-details dt { font-weight:600; }
@keyframes spFadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

/* Modal */
.sp-modal-backdrop { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.4); align-items:center; justify-content:center; padding:20px; }
.sp-modal-backdrop.show { display:flex; }
.sp-modal { background:#fff; border-radius:16px; max-width:580px; width:100%; max-height:85vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.15); }
.sp-modal-header { padding:18px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:#fff; z-index:1; border-radius:16px 16px 0 0; }
.sp-modal-header h3 { font-size:18px; font-weight:700; color:#1e293b; margin:0; }
.sp-modal-close { background:none; border:none; font-size:24px; color:#94a3b8; cursor:pointer; }
.sp-modal-body { padding:24px; }
.sp-modal-footer { padding:14px 24px; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:10px; }
.sp-form-group { margin-bottom:14px; }
.sp-form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
.sp-form-group label .required { color:#dc2626; }
.sp-form-input { width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; color:#1e293b; transition:border-color .2s; background:#fff; box-sizing:border-box; }
.sp-form-input:focus { outline:none; border-color:#01875f; box-shadow:0 0 0 3px rgba(1,135,95,.1); }
.sp-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.sp-form-check { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.sp-form-check input[type="checkbox"] { width:18px; height:18px; accent-color:#01875f; }
.sp-form-check label { font-size:13px; color:#475569; margin:0; }
.sp-form-hint { font-size:11px; color:#94a3b8; margin-top:4px; }
.sp-security-notice { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 16px; font-size:12px; color:#92400e; display:flex; align-items:flex-start; gap:8px; margin-bottom:16px; }
.sp-security-notice i { margin-top:2px; color:#f59e0b; }
.sp-flash { padding:12px 20px; border-radius:8px; font-size:14px; font-weight:500; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.sp-flash.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.sp-flash.danger { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

@media (max-width:768px) {
    .sp-grid { grid-template-columns:1fr; }
    .sp-form-row { grid-template-columns:1fr; }
    .sp-top-tab { padding:10px 16px; font-size:13px; }
    .sp-tab { padding:8px 14px; font-size:12px; }
    .sp-manual-table .sp-m-url { display:none; }
}
</style>

<div class="sp-page">
    <div class="sp-header">
        <h1><i class="fas fa-satellite-dish"></i> Shipping Partners</h1>
    </div>

    <?php if ($flash): ?>
    <div class="sp-flash <?= $flashType; ?>"><i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?= $flash; ?></div>
    <?php endif; ?>

    <!-- ═══ TOP-LEVEL TABS ═══ -->
    <div class="sp-top-tabs">
        <button class="sp-top-tab active" onclick="spTopTab('api',this)"><i class="fas fa-plug"></i> API Integration</button>
        <button class="sp-top-tab" onclick="spTopTab('manual',this)"><i class="fas fa-hand-paper"></i> Non-API Integration</button>
    </div>

    <div class="sp-top-content">

        <!-- ════════════════════════════════════════ -->
        <!-- API INTEGRATION PANEL                    -->
        <!-- ════════════════════════════════════════ -->
        <div class="sp-top-panel active" id="panelApi">

            <div class="sp-security-notice">
                <i class="fas fa-shield-alt"></i>
                <div><strong>Security:</strong> All API keys are encrypted with AES-256-CBC. Only admins can view decrypted values.</div>
            </div>

            <div class="sp-tabs">
                <button class="sp-tab active" onclick="spSubTab('api','domestic',this)"><i class="fas fa-flag"></i> Domestic</button>
                <button class="sp-tab" onclick="spSubTab('api','international',this)"><i class="fas fa-globe"></i> International</button>
            </div>

            <!-- API Domestic -->
            <div class="sp-sub-panel" data-group="api" data-scope="domestic">
                <div class="sp-grid">
                    <?php foreach ($carriers['domestic'] as $c):
                        $saved = $savedPartners[$c['code']] ?? null;
                        $isConfigured = $saved && !empty($saved['api_key_enc']);
                        $isActive = $saved && $saved['is_active'];
                    ?>
                    <div class="sp-card <?= $isConfigured?'configured':''; ?> <?= ($saved&&!$isActive)?'inactive':''; ?>">
                        <div class="sp-card-status"><?php if ($saved): ?><span class="sp-status-dot <?= $isActive?'active':'inactive'; ?>"></span><?php endif; ?></div>
                        <div class="sp-card-head">
                            <div class="sp-card-icon"><i class="<?= $c['icon']; ?>"></i></div>
                            <div><div class="sp-card-title"><?= htmlspecialchars($c['name']); ?></div><div class="sp-card-code"><?= $c['code']; ?></div></div>
                        </div>
                        <?php if ($isConfigured): ?>
                        <div class="sp-card-fields">
                            <div class="sp-field"><span class="sp-field-label">API Key</span><span class="sp-field-value"><?= sp_mask(sp_decrypt($saved['api_key_enc'])); ?></span></div>
                            <div class="sp-field"><span class="sp-field-label">Mode</span><span class="sp-field-value"><?= $saved['is_sandbox']?'Sandbox':'Production'; ?></span></div>
                        </div>
                        <?php endif; ?>
                        <div class="sp-card-actions">
                            <button class="sp-btn sp-btn-primary sp-btn-sm" onclick="spOpenModal('<?= $c['code']; ?>','<?= htmlspecialchars($c['name'],ENT_QUOTES); ?>','domestic')"><i class="fas fa-<?= $isConfigured?'edit':'plus'; ?>"></i> <?= $isConfigured?'Edit':'Configure'; ?></button>
                            <?php if ($isConfigured): ?><button class="sp-btn sp-btn-test sp-btn-sm" onclick="spTestFromCard(<?= $saved['id']; ?>,this)"><i class="fas fa-plug"></i> Test</button><?php endif; ?>
                            <?php if ($saved): ?><form method="post" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_partner"><input type="hidden" name="partner_id" value="<?= $saved['id']; ?>"><button type="submit" class="sp-btn sp-btn-outline sp-btn-sm"><i class="fas fa-power-off"></i> <?= $isActive?'Disable':'Enable'; ?></button></form><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($customApiPartners['domestic'] as $cp): $isCfg=!empty($cp['api_key_enc']); $isAct=$cp['is_active']; ?>
                    <div class="sp-card <?= $isCfg?'configured':''; ?> <?= !$isAct?'inactive':''; ?>" style="border-left-color:#8b5cf6;">
                        <div class="sp-card-status"><span class="sp-status-dot <?= $isAct?'active':'inactive'; ?>"></span></div>
                        <div class="sp-card-head"><div class="sp-card-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-handshake"></i></div><div><div class="sp-card-title"><?= htmlspecialchars($cp['partner_name']); ?></div><div class="sp-card-code"><?= htmlspecialchars($cp['partner_code']); ?> <span style="font-size:9px;background:#f5f3ff;color:#7c3aed;padding:1px 6px;border-radius:4px;">CUSTOM</span></div></div></div>
                        <?php if ($isCfg): ?><div class="sp-card-fields"><div class="sp-field"><span class="sp-field-label">API Key</span><span class="sp-field-value"><?= sp_mask(sp_decrypt($cp['api_key_enc'])); ?></span></div><div class="sp-field"><span class="sp-field-label">Mode</span><span class="sp-field-value"><?= $cp['is_sandbox']?'Sandbox':'Production'; ?></span></div></div><?php endif; ?>
                        <div class="sp-card-actions">
                            <button class="sp-btn sp-btn-primary sp-btn-sm" onclick="spOpenModal('<?= htmlspecialchars($cp['partner_code'],ENT_QUOTES); ?>','<?= htmlspecialchars($cp['partner_name'],ENT_QUOTES); ?>','domestic')"><i class="fas fa-edit"></i> Edit</button>
                            <?php if ($isCfg): ?><button class="sp-btn sp-btn-test sp-btn-sm" onclick="spTestFromCard(<?= $cp['id']; ?>,this)"><i class="fas fa-plug"></i> Test</button><?php endif; ?>
                            <form method="post" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_partner"><input type="hidden" name="partner_id" value="<?= $cp['id']; ?>"><button type="submit" class="sp-btn sp-btn-outline sp-btn-sm"><i class="fas fa-power-off"></i> <?= $isAct?'Disable':'Enable'; ?></button></form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete?');"><?php csrf_field(); ?><input type="hidden" name="action" value="delete_partner"><input type="hidden" name="partner_id" value="<?= $cp['id']; ?>"><button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="sp-card" style="border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;min-height:140px;cursor:pointer;" onclick="spOpenCustomModal('domestic')">
                        <div style="text-align:center;color:#94a3b8;"><i class="fas fa-plus-circle" style="font-size:28px;display:block;margin-bottom:8px;"></i><div style="font-weight:600;font-size:13px;color:#64748b;">Add Custom API Partner</div></div>
                    </div>
                </div>
            </div>

            <!-- API International -->
            <div class="sp-sub-panel" data-group="api" data-scope="international" style="display:none;">
                <div class="sp-grid">
                    <?php foreach ($carriers['international'] as $c):
                        $saved = $savedPartners[$c['code']] ?? null;
                        $isConfigured = $saved && !empty($saved['api_key_enc']);
                        $isActive = $saved && $saved['is_active'];
                    ?>
                    <div class="sp-card <?= $isConfigured?'configured':''; ?> <?= ($saved&&!$isActive)?'inactive':''; ?>">
                        <div class="sp-card-status"><?php if ($saved): ?><span class="sp-status-dot <?= $isActive?'active':'inactive'; ?>"></span><?php endif; ?></div>
                        <div class="sp-card-head"><div class="sp-card-icon"><i class="<?= $c['icon']; ?>"></i></div><div><div class="sp-card-title"><?= htmlspecialchars($c['name']); ?></div><div class="sp-card-code"><?= $c['code']; ?></div></div></div>
                        <?php if ($isConfigured): ?>
                        <div class="sp-card-fields"><div class="sp-field"><span class="sp-field-label">API Key</span><span class="sp-field-value"><?= sp_mask(sp_decrypt($saved['api_key_enc'])); ?></span></div><div class="sp-field"><span class="sp-field-label">Mode</span><span class="sp-field-value"><?= $saved['is_sandbox']?'Sandbox':'Production'; ?></span></div></div>
                        <?php endif; ?>
                        <div class="sp-card-actions">
                            <button class="sp-btn sp-btn-primary sp-btn-sm" onclick="spOpenModal('<?= $c['code']; ?>','<?= htmlspecialchars($c['name'],ENT_QUOTES); ?>','international')"><i class="fas fa-<?= $isConfigured?'edit':'plus'; ?>"></i> <?= $isConfigured?'Edit':'Configure'; ?></button>
                            <?php if ($isConfigured): ?><button class="sp-btn sp-btn-test sp-btn-sm" onclick="spTestFromCard(<?= $saved['id']; ?>,this)"><i class="fas fa-plug"></i> Test</button><?php endif; ?>
                            <?php if ($saved): ?><form method="post" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_partner"><input type="hidden" name="partner_id" value="<?= $saved['id']; ?>"><button type="submit" class="sp-btn sp-btn-outline sp-btn-sm"><i class="fas fa-power-off"></i> <?= $isActive?'Disable':'Enable'; ?></button></form><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($customApiPartners['international'] as $cp): $isCfg=!empty($cp['api_key_enc']); $isAct=$cp['is_active']; ?>
                    <div class="sp-card <?= $isCfg?'configured':''; ?> <?= !$isAct?'inactive':''; ?>" style="border-left-color:#8b5cf6;">
                        <div class="sp-card-status"><span class="sp-status-dot <?= $isAct?'active':'inactive'; ?>"></span></div>
                        <div class="sp-card-head"><div class="sp-card-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-handshake"></i></div><div><div class="sp-card-title"><?= htmlspecialchars($cp['partner_name']); ?></div><div class="sp-card-code"><?= htmlspecialchars($cp['partner_code']); ?> <span style="font-size:9px;background:#f5f3ff;color:#7c3aed;padding:1px 6px;border-radius:4px;">CUSTOM</span></div></div></div>
                        <div class="sp-card-actions">
                            <button class="sp-btn sp-btn-primary sp-btn-sm" onclick="spOpenModal('<?= htmlspecialchars($cp['partner_code'],ENT_QUOTES); ?>','<?= htmlspecialchars($cp['partner_name'],ENT_QUOTES); ?>','international')"><i class="fas fa-edit"></i> Edit</button>
                            <form method="post" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_partner"><input type="hidden" name="partner_id" value="<?= $cp['id']; ?>"><button type="submit" class="sp-btn sp-btn-outline sp-btn-sm"><i class="fas fa-power-off"></i> <?= $isAct?'Disable':'Enable'; ?></button></form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete?');"><?php csrf_field(); ?><input type="hidden" name="action" value="delete_partner"><input type="hidden" name="partner_id" value="<?= $cp['id']; ?>"><button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="sp-card" style="border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;min-height:140px;cursor:pointer;" onclick="spOpenCustomModal('international')">
                        <div style="text-align:center;color:#94a3b8;"><i class="fas fa-plus-circle" style="font-size:28px;display:block;margin-bottom:8px;"></i><div style="font-weight:600;font-size:13px;color:#64748b;">Add Custom API Partner</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════ -->
        <!-- NON-API / MANUAL PANEL                   -->
        <!-- ════════════════════════════════════════ -->
        <div class="sp-top-panel" id="panelManual">
            <div class="sp-tabs">
                <button class="sp-tab active" onclick="spSubTab('manual','domestic',this)"><i class="fas fa-flag"></i> Domestic</button>
                <button class="sp-tab" onclick="spSubTab('manual','international',this)"><i class="fas fa-globe"></i> International</button>
            </div>

            <?php foreach (['domestic','international'] as $scope): ?>
            <div class="sp-sub-panel" data-group="manual" data-scope="<?= $scope; ?>" <?= $scope==='international'?'style="display:none;"':''; ?>>
                <?php if (empty($manualPartners[$scope])): ?>
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    <i class="fas fa-truck-loading" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                    <div style="font-size:15px;font-weight:600;color:#64748b;">No <?= $scope; ?> manual couriers yet</div>
                    <div style="font-size:13px;margin-top:4px;">Add your first courier below</div>
                </div>
                <?php else: ?>
                <table class="sp-manual-table">
                    <thead>
                        <tr><th>Courier Name</th><th>Tracking URL</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualPartners[$scope] as $mc): ?>
                        <tr>
                            <td class="sp-m-name"><i class="fas fa-truck" style="color:#01875f;margin-right:6px;"></i><?= htmlspecialchars($mc['partner_name']); ?></td>
                            <td class="sp-m-url" title="<?= htmlspecialchars($mc['base_url']); ?>"><?= htmlspecialchars($mc['base_url'] ?: '—'); ?></td>
                            <td><span class="sp-status-dot <?= $mc['is_active']?'active':'inactive'; ?>" style="vertical-align:middle;"></span> <?= $mc['is_active']?'Active':'Inactive'; ?></td>
                            <td>
                                <button class="sp-btn sp-btn-outline sp-btn-sm" onclick="spEditManual(<?= htmlspecialchars(json_encode(['id'=>$mc['id'],'name'=>$mc['partner_name'],'scope'=>$mc['partner_type'],'url'=>$mc['base_url'],'active'=>$mc['is_active']])); ?>)"><i class="fas fa-edit"></i></button>
                                <form method="post" style="display:inline;"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_manual"><input type="hidden" name="partner_id" value="<?= $mc['id']; ?>"><button type="submit" class="sp-btn sp-btn-outline sp-btn-sm"><i class="fas fa-power-off"></i></button></form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this courier?');"><?php csrf_field(); ?><input type="hidden" name="action" value="delete_manual"><input type="hidden" name="partner_id" value="<?= $mc['id']; ?>"><button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <div class="sp-manual-add-row" onclick="spOpenManualModal('<?= $scope; ?>')">
                    <i class="fas fa-plus-circle"></i> Add <?= ucfirst($scope); ?> Courier
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div><!-- .sp-top-content -->
</div><!-- .sp-page -->

<!-- ═══ API CONFIGURE MODAL ═══ -->
<div class="sp-modal-backdrop" id="spModal">
    <div class="sp-modal">
        <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save_partner">
            <input type="hidden" name="partner_code" id="spCode">
            <input type="hidden" name="partner_type" id="spType">
            <div class="sp-modal-header">
                <h3 id="spModalTitle"><i class="fas fa-cog"></i> Configure</h3>
                <button type="button" class="sp-modal-close" onclick="spCloseModal()">&times;</button>
            </div>
            <div class="sp-modal-body">
                <div id="spCarrierHelper"></div>
                <div class="sp-form-group"><label>Partner Name <span class="required">*</span></label><input type="text" name="partner_name" id="spName" class="sp-form-input" required></div>
                <div class="sp-form-row">
                    <div class="sp-form-group"><label id="spApiKeyLabel">API Key</label><input type="password" name="api_key" id="spApiKey" class="sp-form-input" placeholder="Enter API key"></div>
                    <div class="sp-form-group"><label id="spApiSecretLabel">API Secret</label><input type="password" name="api_secret" id="spApiSecret" class="sp-form-input" placeholder="Enter API secret"></div>
                </div>
                <div class="sp-form-group"><label>Webhook URL</label><input type="url" name="webhook_url" id="spWebhookUrl" class="sp-form-input" placeholder="https://..."></div>
                <div class="sp-form-group"><label>Webhook Secret</label><input type="password" name="webhook_secret" id="spWebhookSecret" class="sp-form-input"></div>
                <div class="sp-form-group"><label>Account Number</label><input type="text" name="account_number" id="spAccNum" class="sp-form-input"></div>
                <div class="sp-form-row">
                    <div class="sp-form-group"><label>Production URL</label><input type="url" name="base_url" id="spBaseUrl" class="sp-form-input" placeholder="https://api.carrier.com"></div>
                    <div class="sp-form-group"><label>Sandbox URL</label><input type="url" name="sandbox_url" id="spSandboxUrl" class="sp-form-input" placeholder="https://sandbox.carrier.com"></div>
                </div>
                <div class="sp-form-check"><input type="checkbox" name="is_sandbox" id="spSandbox" value="1"><label for="spSandbox">Sandbox / Test Mode</label></div>
                <div class="sp-form-check"><input type="checkbox" name="is_active" id="spActive" value="1"><label for="spActive">Enable this partner</label></div>
            </div>
            <div id="spTestResult" style="display:none;"></div>
            <div class="sp-modal-footer">
                <button type="button" class="sp-btn sp-btn-outline" onclick="spCloseModal()">Cancel</button>
                <button type="button" class="sp-btn sp-btn-test" id="spTestBtn" onclick="spTestConnection()" style="margin-right:auto;"><i class="fas fa-plug"></i> Test Connection</button>
                <button type="submit" class="sp-btn sp-btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ CUSTOM API PARTNER MODAL ═══ -->
<div class="sp-modal-backdrop" id="spCustomModal">
    <div class="sp-modal">
        <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save_partner">
            <input type="hidden" name="partner_type" id="spCustomType">
            <div class="sp-modal-header">
                <h3><i class="fas fa-handshake"></i> Add Custom API Partner</h3>
                <button type="button" class="sp-modal-close" onclick="spCloseCustomModal()">&times;</button>
            </div>
            <div class="sp-modal-body">
                <div class="sp-form-row">
                    <div class="sp-form-group"><label>Partner Name <span class="required">*</span></label><input type="text" name="partner_name" id="spCustomName" class="sp-form-input" required></div>
                    <div class="sp-form-group"><label>Partner Code <span class="required">*</span></label><input type="text" name="partner_code" id="spCustomCode" class="sp-form-input" required></div>
                </div>
                <div class="sp-form-row">
                    <div class="sp-form-group"><label>API Key</label><input type="password" name="api_key" id="spCustomApiKey" class="sp-form-input"></div>
                    <div class="sp-form-group"><label>API Secret</label><input type="password" name="api_secret" id="spCustomApiSecret" class="sp-form-input"></div>
                </div>
                <div class="sp-form-check"><input type="checkbox" name="is_sandbox" id="spCustomSandbox" value="1" checked><label for="spCustomSandbox">Sandbox Mode</label></div>
                <div class="sp-form-check"><input type="checkbox" name="is_active" id="spCustomActive" value="1"><label for="spCustomActive">Enable</label></div>
            </div>
            <div class="sp-modal-footer">
                <button type="button" class="sp-btn sp-btn-outline" onclick="spCloseCustomModal()">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MANUAL COURIER ADD/EDIT MODAL ═══ -->
<div class="sp-modal-backdrop" id="spManualModal">
    <div class="sp-modal" style="max-width:460px;">
        <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save_manual">
            <input type="hidden" name="manual_edit_id" id="spManualEditId" value="0">
            <div class="sp-modal-header">
                <h3 id="spManualModalTitle"><i class="fas fa-truck"></i> Add Manual Courier</h3>
                <button type="button" class="sp-modal-close" onclick="spCloseManualModal()">&times;</button>
            </div>
            <div class="sp-modal-body">
                <div class="sp-form-group"><label>Courier Name <span class="required">*</span></label><input type="text" name="manual_name" id="spManualName" class="sp-form-input" required placeholder="e.g. Local Express"></div>
                <div class="sp-form-group">
                    <label>Scope</label>
                    <select name="manual_scope" id="spManualScope" class="sp-form-input">
                        <option value="domestic">Domestic</option>
                        <option value="international">International</option>
                    </select>
                </div>
                <div class="sp-form-group"><label>Tracking URL Pattern</label><input type="text" name="manual_tracking_url" id="spManualUrl" class="sp-form-input" placeholder="https://track.example.com/?id={TN}"><div class="sp-form-hint">Use {TN} as placeholder for tracking number</div></div>
                <div class="sp-form-check"><input type="checkbox" name="manual_active" id="spManualActive" value="1" checked><label for="spManualActive">Active (available in order shipping)</label></div>
            </div>
            <div class="sp-modal-footer">
                <button type="button" class="sp-btn sp-btn-outline" onclick="spCloseManualModal()">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-primary"><i class="fas fa-save"></i> Save Courier</button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── Top-level tab switching ───
function spTopTab(tab, btn) {
    document.querySelectorAll('.sp-top-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sp-top-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
}

// ─── Sub-tab switching ───
function spSubTab(group, scope, btn) {
    var parent = btn.closest('.sp-top-panel');
    parent.querySelectorAll('.sp-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    parent.querySelectorAll('.sp-sub-panel').forEach(p => {
        p.style.display = (p.dataset.group === group && p.dataset.scope === scope) ? '' : 'none';
    });
}

// ─── API Configure Modal ───
var spSavedData = <?= json_encode(array_map(function($p) {
    return [
        'id' => (int)$p['id'], 'code' => $p['partner_code'], 'name' => $p['partner_name'],
        'type' => $p['partner_type'],
        'api_key_masked' => sp_mask(sp_decrypt($p['api_key_enc'])),
        'api_secret_masked' => sp_mask(sp_decrypt($p['api_secret_enc'])),
        'webhook_url' => $p['webhook_url'],
        'webhook_secret_masked' => sp_mask(sp_decrypt($p['webhook_secret_enc'])),
        'account_number' => $p['account_number'],
        'base_url' => $p['base_url'], 'sandbox_url' => $p['sandbox_url'],
        'is_sandbox' => (int)$p['is_sandbox'], 'is_active' => (int)$p['is_active'],
    ];
}, $savedPartners)); ?>;

function spOpenModal(code, name, type) {
    var d = spSavedData[code];
    var lc = code.toLowerCase();
    document.getElementById('spCode').value = code;
    document.getElementById('spName').value = name;
    document.getElementById('spType').value = type;
    document.getElementById('spModalTitle').innerHTML = '<i class="fas fa-cog"></i> ' + name;
    document.getElementById('spApiKey').value = d ? d.api_key_masked : '';
    document.getElementById('spApiSecret').value = d ? d.api_secret_masked : '';
    document.getElementById('spWebhookUrl').value = d ? d.webhook_url : '';
    document.getElementById('spWebhookSecret').value = d ? d.webhook_secret_masked : '';
    document.getElementById('spAccNum').value = d ? d.account_number : '';
    document.getElementById('spBaseUrl').value = d ? d.base_url : '';
    document.getElementById('spSandboxUrl').value = d ? d.sandbox_url : '';
    document.getElementById('spSandbox').checked = d ? !!d.is_sandbox : true;
    document.getElementById('spActive').checked = d ? !!d.is_active : false;
    document.getElementById('spTestResult').style.display = 'none';

    // Dynamic labels per carrier
    var keyLabel = 'API Key', secretLabel = 'API Secret', keyPlaceholder = 'Enter API key', secretPlaceholder = 'Enter API secret (optional)';
    var helperHtml = '';
    switch (lc) {
        case 'shiprocket':
            keyLabel = 'Email (API User)'; secretLabel = 'Password';
            keyPlaceholder = 'api-user@example.com'; secretPlaceholder = 'Enter Shiprocket API password';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Shiprocket:</strong> Dashboard → Settings → API → Create API User. Email goes in API Key, password (sent to registered email) in Password field.<br>Docs: <a href="https://apidocs.shiprocket.in/" target="_blank">apidocs.shiprocket.in</a></div>';
            break;
        case 'delhivery':
            keyLabel = 'API Token'; secretLabel = 'Client Name (optional)';
            keyPlaceholder = 'Enter Delhivery API token'; secretPlaceholder = 'Your Delhivery client name';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Delhivery:</strong> Get your API token from Delhivery dashboard → Settings → API Setup. Use <code>https://track.delhivery.com</code> as Production URL.<br>Docs: <a href="https://dlv-api.delhivery.com/documentation/" target="_blank">Delhivery API Docs</a></div>';
            break;
        case 'dtdc':
            keyLabel = 'API Key / Customer Code'; secretLabel = 'API Secret / Token';
            keyPlaceholder = 'Your DTDC customer code'; secretPlaceholder = 'Your DTDC API secret';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>DTDC:</strong> Contact DTDC business team to get API credentials. Use customer code as API Key and token as API Secret.<br>Docs: <a href="https://www.dtdc.in/dtdc-api.asp" target="_blank">DTDC API</a></div>';
            break;
        case 'bluedart':
            keyLabel = 'API Key (Licence Key)'; secretLabel = 'Login ID';
            keyPlaceholder = 'Your Blue Dart licence key'; secretPlaceholder = 'Your Blue Dart login ID';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Blue Dart:</strong> Register at Blue Dart API portal. Licence Key goes in API Key, Login ID in API Secret. Use <code>https://api.bluedart.com</code> as Production URL.<br>Docs: <a href="https://www.bluedart.com/web-services-api" target="_blank">Blue Dart API</a></div>';
            break;
        case 'indiapost':
            keyLabel = 'API Key'; secretLabel = 'Secret Key (optional)';
            keyPlaceholder = 'India Post API key'; secretPlaceholder = 'India Post secret key';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>India Post:</strong> Apply for API access via India Post Digital Advancement portal. Use <code>https://apigw.indiapost.gov.in</code> as Production URL.</div>';
            break;
        case 'ekart':
            keyLabel = 'API Token'; secretLabel = 'Seller ID (optional)';
            keyPlaceholder = 'Your Ekart API token'; secretPlaceholder = 'Your Ekart seller ID';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Ekart:</strong> Contact Ekart Logistics to get API credentials for integration.</div>';
            break;
        case 'xpressbees':
            keyLabel = 'API Token / Email'; secretLabel = 'Password / Secret';
            keyPlaceholder = 'Your XpressBees email or token'; secretPlaceholder = 'Your XpressBees password';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>XpressBees:</strong> Get credentials from XpressBees dashboard → API section. Use <code>https://shipment.xpressbees.com/api</code> as Production URL.</div>';
            break;
        case 'ecom':
            keyLabel = 'Username'; secretLabel = 'Password';
            keyPlaceholder = 'Ecom Express username'; secretPlaceholder = 'Ecom Express password';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Ecom Express:</strong> Contact Ecom Express team for API access. Use username/password authentication.</div>';
            break;
        case 'shadowfax':
            keyLabel = 'API Token'; secretLabel = 'Client ID (optional)';
            keyPlaceholder = 'Your Shadowfax API token'; secretPlaceholder = 'Your Shadowfax client ID';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Shadowfax:</strong> Get API token from Shadowfax partner dashboard.</div>';
            break;
        case 'dhl':
            keyLabel = 'API Key'; secretLabel = 'API Secret';
            keyPlaceholder = 'DHL API key'; secretPlaceholder = 'DHL API secret';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>DHL Express:</strong> Register at <a href="https://developer.dhl.com/" target="_blank">developer.dhl.com</a> → Create App → Get API Key. Use <code>https://express.api.dhl.com</code> as Production URL.</div>';
            break;
        case 'fedex':
            keyLabel = 'API Key (Client ID)'; secretLabel = 'Secret Key';
            keyPlaceholder = 'FedEx client ID'; secretPlaceholder = 'FedEx secret key';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>FedEx:</strong> Register at <a href="https://developer.fedex.com/" target="_blank">developer.fedex.com</a> → Create Project → Get Client ID + Secret. Use <code>https://apis.fedex.com</code> as Production URL.</div>';
            break;
        case 'aramex':
            keyLabel = 'Username'; secretLabel = 'Password';
            keyPlaceholder = 'Aramex API username'; secretPlaceholder = 'Aramex API password';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>Aramex:</strong> Register at <a href="https://www.aramex.com/developers" target="_blank">aramex.com/developers</a>. Enter Account Number above. Use username/password for auth.</div>';
            break;
        case 'ups':
            keyLabel = 'Client ID'; secretLabel = 'Client Secret';
            keyPlaceholder = 'UPS OAuth client ID'; secretPlaceholder = 'UPS OAuth client secret';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>UPS:</strong> Register at <a href="https://developer.ups.com/" target="_blank">developer.ups.com</a> → Create App → Get OAuth credentials. Use <code>https://onlinetools.ups.com</code> as Production URL.</div>';
            break;
        case 'usps':
            keyLabel = 'Consumer Key'; secretLabel = 'Consumer Secret';
            keyPlaceholder = 'USPS consumer key'; secretPlaceholder = 'USPS consumer secret';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>USPS:</strong> Register at <a href="https://developer.usps.com/" target="_blank">developer.usps.com</a> for API access. Use <code>https://api.usps.com</code> as Production URL.</div>';
            break;
        case 'dpworld':
            keyLabel = 'API Key'; secretLabel = 'API Secret (optional)';
            keyPlaceholder = 'DP World API key'; secretPlaceholder = 'DP World API secret';
            helperHtml = '<div style="background:#fffbe6;border:1px solid #ffe58f;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#7c6a00;"><i class="fas fa-info-circle"></i> <strong>DP World:</strong> Contact DP World for logistics API integration credentials.</div>';
            break;
    }
    document.getElementById('spApiKeyLabel').textContent = keyLabel;
    document.getElementById('spApiSecretLabel').textContent = secretLabel;
    document.getElementById('spApiKey').placeholder = keyPlaceholder;
    document.getElementById('spApiSecret').placeholder = secretPlaceholder;
    document.getElementById('spCarrierHelper').innerHTML = helperHtml;

    document.getElementById('spModal').classList.add('show');
}
function spCloseModal() { document.getElementById('spModal').classList.remove('show'); }
document.getElementById('spModal').addEventListener('click', function(e) { if (e.target === this) spCloseModal(); });

// ─── Custom API Modal ───
function spOpenCustomModal(type) {
    document.getElementById('spCustomType').value = type;
    document.getElementById('spCustomName').value = '';
    document.getElementById('spCustomCode').value = '';
    document.getElementById('spCustomApiKey').value = '';
    document.getElementById('spCustomApiSecret').value = '';
    document.getElementById('spCustomSandbox').checked = true;
    document.getElementById('spCustomActive').checked = false;
    document.getElementById('spCustomModal').classList.add('show');
}
function spCloseCustomModal() { document.getElementById('spCustomModal').classList.remove('show'); }
document.getElementById('spCustomModal').addEventListener('click', function(e) { if (e.target === this) spCloseCustomModal(); });
document.getElementById('spCustomName').addEventListener('input', function() {
    document.getElementById('spCustomCode').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
});

// ─── Manual Courier Modal ───
function spOpenManualModal(scope) {
    document.getElementById('spManualEditId').value = '0';
    document.getElementById('spManualName').value = '';
    document.getElementById('spManualScope').value = scope;
    document.getElementById('spManualUrl').value = '';
    document.getElementById('spManualActive').checked = true;
    document.getElementById('spManualModalTitle').innerHTML = '<i class="fas fa-truck"></i> Add Manual Courier';
    document.getElementById('spManualModal').classList.add('show');
}
function spEditManual(data) {
    document.getElementById('spManualEditId').value = data.id;
    document.getElementById('spManualName').value = data.name;
    document.getElementById('spManualScope').value = data.scope || 'domestic';
    document.getElementById('spManualUrl').value = data.url || '';
    document.getElementById('spManualActive').checked = !!data.active;
    document.getElementById('spManualModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Courier';
    document.getElementById('spManualModal').classList.add('show');
}
function spCloseManualModal() { document.getElementById('spManualModal').classList.remove('show'); }
document.getElementById('spManualModal').addEventListener('click', function(e) { if (e.target === this) spCloseManualModal(); });

// ─── Test Connection ───
var csrfToken = document.querySelector('input[name="csrf_token"]').value;

function spTestConnection() {
    var code = document.getElementById('spCode').value;
    var d = spSavedData[code];
    if (!d || !d.id) { alert('Save configuration first before testing.'); return; }
    var btn = document.getElementById('spTestBtn');
    var res = document.getElementById('spTestResult');
    btn.classList.add('testing'); btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    res.style.display = 'none';
    var fd = new FormData(); fd.append('action','test_connection'); fd.append('partner_id',d.id); fd.append('csrf_token',csrfToken);
    fetch(window.location.pathname,{method:'POST',body:fd}).then(r=>r.json()).then(function(data){
        btn.classList.remove('testing'); btn.innerHTML='<i class="fas fa-plug"></i> Test Connection';
        var cls='fail',icon='<i class="fas fa-times-circle"></i>';
        if(data.http_code>=200&&data.http_code<300){cls='success';icon='<i class="fas fa-check-circle"></i>';}
        else if(data.http_code>=400&&data.http_code<500){cls='warn';icon='<i class="fas fa-exclamation-triangle"></i>';}
        res.className='sp-test-result '+cls;
        res.innerHTML='<div class="sp-test-head">'+icon+' '+(data.message||'Unknown')+'</div><dl class="sp-test-details"><dt>HTTP</dt><dd>'+(data.http_code||'—')+'</dd><dt>Latency</dt><dd>'+(data.latency_ms||'—')+' ms</dd><dt>Mode</dt><dd>'+(data.mode||'—')+'</dd><dt>URL</dt><dd style="word-break:break-all;">'+(data.url_tested||'—')+'</dd></dl>';
        res.style.display='block';
    }).catch(function(err){
        btn.classList.remove('testing'); btn.innerHTML='<i class="fas fa-plug"></i> Test Connection';
        res.className='sp-test-result fail'; res.innerHTML='<div class="sp-test-head"><i class="fas fa-times-circle"></i> Request failed</div><p>'+err.message+'</p>'; res.style.display='block';
    });
}

function spTestFromCard(id, el) {
    el.classList.add('testing'); el.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    var fd=new FormData(); fd.append('action','test_connection'); fd.append('partner_id',id); fd.append('csrf_token',csrfToken);
    fetch(window.location.pathname,{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
        el.classList.remove('testing');
        if(d.http_code>=200&&d.http_code<300){el.innerHTML='<i class="fas fa-check-circle" style="color:#22c55e"></i> '+d.latency_ms+'ms';el.style.color='#166534';el.style.background='#f0fdf4';el.style.borderColor='#bbf7d0';}
        else if(d.http_code>=400&&d.http_code<500){el.innerHTML='<i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> '+d.http_code;el.style.color='#92400e';el.style.background='#fffbeb';}
        else{el.innerHTML='<i class="fas fa-times-circle" style="color:#dc2626"></i> Fail';el.style.color='#991b1b';el.style.background='#fef2f2';}
        el.title=d.message; setTimeout(function(){el.innerHTML='<i class="fas fa-plug"></i> Test';el.style='';el.title='';},5000);
    }).catch(function(){el.classList.remove('testing');el.innerHTML='<i class="fas fa-times-circle" style="color:#dc2626"></i>';setTimeout(function(){el.innerHTML='<i class="fas fa-plug"></i> Test';el.style='';},3000);});
}

// Clear masked values on focus
['spApiKey','spApiSecret','spWebhookSecret'].forEach(function(id){
    var el=document.getElementById(id);
    el.addEventListener('focus',function(){if(this.value.indexOf('\u2022')!==-1)this.value='';this.type='text';});
    el.addEventListener('blur',function(){this.type='password';});
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
