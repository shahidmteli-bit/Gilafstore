<?php
/**
 * Shiprocket API Debug Tool
 * Step-by-step diagnostic: Auth → Create Order → Assign AWB → Pickup → Label
 * Shows raw API responses at each step to identify where AWB assignment fails.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/shipping_helpers.php';

require_admin();

$pageTitle = 'Shiprocket Debug Tool — Admin';
$adminPage = 'shipping';
$db = get_db_connection();

// Get API partners (Shiprocket)
$partners = $db->query("SELECT * FROM shipping_partners WHERE integration_type='api' AND is_active=1 ORDER BY partner_name")->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders for dropdown
$recentOrders = $db->query("SELECT o.id, o.created_at, o.total_amount, o.order_status, o.payment_status, o.tracking_id, o.courier_company, u.name AS customer_name FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Run debug if requested
$debugResults = null;
$selectedOrder = null;
$selectedPartner = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_debug'])) {
  try {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $partnerId = (int)($_POST['partner_id'] ?? 0);
    $stopAfter = $_POST['stop_after'] ?? 'all'; // auth, create, awb, pickup, label, all
    $forceFreshLogin = !empty($_POST['force_fresh_login']);

    $debugResults = ['steps' => [], 'started_at' => date('Y-m-d H:i:s')];

    // Get partner
    $stmt = $db->prepare("SELECT * FROM shipping_partners WHERE id = ?");
    $stmt->execute([$partnerId]);
    $selectedPartner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedPartner) {
        $debugResults['steps'][] = ['name' => 'Partner Lookup', 'status' => 'error', 'message' => "Partner ID {$partnerId} not found"];
    } else {
        $debugResults['partner'] = [
            'id' => $selectedPartner['id'],
            'name' => $selectedPartner['partner_name'],
            'code' => $selectedPartner['partner_code'],
            'type' => $selectedPartner['integration_type'],
            'base_url' => $selectedPartner['base_url'],
            'sandbox_url' => $selectedPartner['sandbox_url'] ?? '',
            'is_sandbox' => $selectedPartner['is_sandbox'] ?? 0,
        ];

        // Detect which URL will be used
        $baseUrl = $selectedPartner['is_sandbox']
            ? ($selectedPartner['sandbox_url'] ?: $selectedPartner['base_url'])
            : ($selectedPartner['base_url'] ?: ($selectedPartner['sandbox_url'] ?? ''));
        $debugResults['effective_base_url'] = $baseUrl;

        // Check if detected as Shiprocket
        $codeLower = strtolower($selectedPartner['partner_code']);
        $urlLower = strtolower($baseUrl);
        $isShiprocket = ($codeLower === 'shiprocket' || strpos($urlLower, 'shiprocket') !== false);
        $debugResults['is_shiprocket_detected'] = $isShiprocket;
        $debugResults['detection_method'] = $codeLower === 'shiprocket' ? 'partner_code' : (strpos($urlLower, 'shiprocket') !== false ? 'base_url' : 'NOT DETECTED');

        $debugResults['steps'][] = [
            'name' => 'Partner Config',
            'status' => $isShiprocket ? 'pass' : 'warning',
            'message' => $isShiprocket
                ? "Shiprocket detected via {$debugResults['detection_method']}"
                : "WARNING: Not detected as Shiprocket! Code='{$codeLower}', URL='{$urlLower}'",
            'data' => $debugResults['partner'],
        ];

        if (!$isShiprocket) {
            $debugResults['steps'][] = ['name' => 'Aborted', 'status' => 'error', 'message' => 'Partner not detected as Shiprocket. Fix partner_code to "shiprocket" or set base_url to contain "shiprocket".'];
        } else {
            // ── STEP 1: Credentials check ──
            $email = sp_decrypt($selectedPartner['api_key_enc']);
            $password = sp_decrypt($selectedPartner['api_secret_enc']);
            $debugResults['steps'][] = [
                'name' => 'Credentials Decrypt',
                'status' => (!empty($email) && !empty($password)) ? 'pass' : 'error',
                'message' => (!empty($email) && !empty($password))
                    ? "Email: " . (strpos($email, '@') !== false ? substr($email, 0, 3) . '***@' . explode('@', $email)[1] : $email) . " | Password: " . str_repeat('*', strlen($password))
                    : "FAILED: Email=" . (empty($email) ? 'EMPTY' : 'OK') . ", Password=" . (empty($password) ? 'EMPTY' : 'OK'),
            ];

            if (empty($email) || empty($password)) {
                $debugResults['steps'][] = ['name' => 'Aborted', 'status' => 'error', 'message' => 'Cannot proceed without credentials'];
            } else {
                // ── STEP 2: Auth / Login ──
                if ($forceFreshLogin) {
                    // Force fresh login — bypass stored token
                    $loginUrl = rtrim($baseUrl, '/') . '/v1/external/auth/login';
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $loginUrl, CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
                    ]);
                    $loginRaw = curl_exec($ch);
                    $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $loginErr = curl_error($ch);
                    curl_close($ch);
                    $loginData = json_decode($loginRaw, true);
                    if ($loginCode === 200 && !empty($loginData['token'])) {
                        $auth = ['token' => $loginData['token'], 'base_url' => $baseUrl];
                    } else {
                        $auth = ['token' => null, 'error' => "Fresh login failed (HTTP {$loginCode}): " . ($loginData['message'] ?? $loginRaw)];
                    }
                    $authMethod = 'FRESH login (forced)';
                    $authExtra = $loginErr ? " | cURL error: {$loginErr}" : '';
                    $authExtra .= " | HTTP {$loginCode}";
                } else {
                    $auth = shiprocket_get_token($db, $selectedPartner);
                    $authMethod = 'Cached/auto';
                    $authExtra = '';
                }
                $debugResults['steps'][] = [
                    'name' => 'Authentication',
                    'status' => !empty($auth['token']) ? 'pass' : 'error',
                    'message' => !empty($auth['token'])
                        ? "Token obtained via {$authMethod} (length: " . strlen($auth['token']) . "). Base URL: " . ($auth['base_url'] ?? 'N/A') . $authExtra
                        : "FAILED ({$authMethod}): " . ($auth['error'] ?? 'Unknown error') . $authExtra,
                ];

                if (empty($auth['token']) || $stopAfter === 'auth') {
                    // stop
                } else {
                    $token = $auth['token'];
                    $baseUrl = $auth['base_url'];

                    // ── STEP 3: Fetch order data ──
                    $stmt = $db->prepare("SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
                    $stmt->execute([$orderId]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    $selectedOrder = $order;

                    if (!$order) {
                        $debugResults['steps'][] = ['name' => 'Order Lookup', 'status' => 'error', 'message' => "Order #{$orderId} not found"];
                    } else {
                        // Get items
                        $itemStmt = $db->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                        $itemStmt->execute([$orderId]);
                        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Parse address
                        $addr = ['name' => '', 'address' => '', 'city' => '', 'state' => '', 'pincode' => '', 'phone' => '', 'email' => ''];
                        if (!empty($order['shipping_address'])) {
                            $ad = json_decode($order['shipping_address'], true);
                            if (is_array($ad)) {
                                $addr = [
                                    'name' => $ad['name'] ?? $ad['full_name'] ?? $order['customer_name'],
                                    'address' => trim(($ad['address_line1'] ?? '') . ' ' . ($ad['address_line2'] ?? '')),
                                    'city' => $ad['city'] ?? '',
                                    'state' => $ad['state'] ?? '',
                                    'pincode' => $ad['pincode'] ?? ($ad['zip_code'] ?? ''),
                                    'phone' => $ad['phone'] ?? $order['customer_phone'],
                                    'email' => $order['customer_email'] ?? '',
                                ];
                            }
                        }
                        if (empty($addr['address'])) {
                            $addrStmt = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1");
                            $addrStmt->execute([$order['user_id']]);
                            $ua = $addrStmt->fetch(PDO::FETCH_ASSOC);
                            if ($ua) {
                                $addr = [
                                    'name' => $order['customer_name'] ?? 'Customer',
                                    'address' => implode(', ', array_filter([$ua['flat_number'] ?? '', $ua['address_line1'] ?? '', $ua['address_line2'] ?? ''])),
                                    'city' => $ua['city'] ?? '',
                                    'state' => $ua['state'] ?? '',
                                    'pincode' => $ua['zip_code'] ?? ($ua['pincode'] ?? ''),
                                    'phone' => $ua['phone'] ?? $order['customer_phone'],
                                    'email' => $order['customer_email'] ?? '',
                                ];
                            }
                        }
                        // Final fallback: use customer data from users table
                        if (empty($addr['name']))  $addr['name']  = $order['customer_name'] ?? 'Customer';
                        if (empty($addr['phone'])) $addr['phone'] = $order['customer_phone'] ?? '';
                        if (empty($addr['email'])) $addr['email'] = $order['customer_email'] ?? '';

                        $debugResults['steps'][] = [
                            'name' => 'Order Data',
                            'status' => 'pass',
                            'message' => "Order #{$orderId}: {$order['customer_name']}, " . count($items) . " item(s), ₹{$order['total_amount']}",
                            'data' => [
                                'customer' => $order['customer_name'],
                                'phone' => $addr['phone'],
                                'address' => $addr['address'],
                                'city' => $addr['city'],
                                'state' => $addr['state'],
                                'pincode' => $addr['pincode'],
                                'items' => count($items),
                                'payment_method' => $order['payment_method'],
                                'total' => $order['total_amount'],
                            ],
                        ];

                        // Build payload (same as shipping_helpers)
                        $srItems = [];
                        foreach ($items as $item) {
                            $srItems[] = [
                                'name' => $item['product_name'] ?? ('Item #' . $item['product_id']),
                                'sku' => $item['sku'] ?? ('SKU-' . ($item['product_id'] ?? '0')),
                                'units' => (int)$item['quantity'],
                                'selling_price' => (float)$item['price'],
                                'discount' => '', 'tax' => '', 'hsn' => '',
                            ];
                        }
                        $paymentMethod = strtolower($order['payment_method'] ?? '');
                        $isCOD = (strpos($paymentMethod, 'cod') !== false || strpos($paymentMethod, 'cash') !== false);
                        $nameParts = explode(' ', trim($addr['name']), 2);
                        $firstName = $nameParts[0] ?: 'Customer';
                        $lastName = $nameParts[1] ?? '';

                        // Fetch pickup location from Shiprocket
                        $pickupLocName = 'Primary';
                        $t1 = microtime(true);
                        $pickupLocResp = shiprocket_request($baseUrl, '/v1/external/settings/company/pickup', $token, null, 'GET');
                        $t2 = microtime(true);
                        if ($pickupLocResp['code'] === 200 && !empty($pickupLocResp['body']['data']['shipping_address'])) {
                            $pickupLocName = $pickupLocResp['body']['data']['shipping_address'][0]['pickup_location'] ?? 'Primary';
                        }
                        $debugResults['steps'][] = [
                            'name' => 'Pickup Location',
                            'status' => ($pickupLocResp['code'] === 200) ? 'pass' : 'warning',
                            'message' => "Using: \"{$pickupLocName}\" — " . round(($t2 - $t1) * 1000) . "ms",
                            'data' => $pickupLocResp['body']['data']['shipping_address'] ?? $pickupLocResp['body'],
                        ];

                        $orderPayload = [
                            'order_id' => 'GILAF-' . $orderId,
                            'order_date' => date('Y-m-d H:i', strtotime($order['created_at'])),
                            'pickup_location' => $pickupLocName,
                            'billing_customer_name' => $firstName,
                            'billing_last_name' => $lastName,
                            'billing_address' => $addr['address'] ?: 'NA',
                            'billing_address_2' => '',
                            'billing_city' => $addr['city'] ?: 'NA',
                            'billing_pincode' => $addr['pincode'] ?: '000000',
                            'billing_state' => $addr['state'] ?: 'NA',
                            'billing_country' => 'India',
                            'billing_email' => $addr['email'] ?: 'customer@example.com',
                            'billing_phone' => sr_normalize_phone($addr['phone'] ?: '0000000000'),
                            'shipping_is_billing' => true,
                            'order_items' => $srItems,
                            'payment_method' => $isCOD ? 'COD' : 'Prepaid',
                            'sub_total' => (float)$order['total_amount'],
                            'length' => 20, 'breadth' => 15, 'height' => 10, 'weight' => 0.5,
                        ];

                        $debugResults['steps'][] = [
                            'name' => 'Order Payload',
                            'status' => 'info',
                            'message' => 'Payload built for Shiprocket create order API',
                            'data' => $orderPayload,
                        ];

                        if ($stopAfter === 'create_preview') {
                            // Stop here — just show the payload without sending
                        } else {
                            // ── STEP 4: Create Order on Shiprocket ──
                            $t1 = microtime(true);
                            $createResp = shiprocket_request($baseUrl, '/v1/external/orders/create/adhoc', $token, $orderPayload);
                            $t2 = microtime(true);

                            $createOk = ($createResp['code'] >= 200 && $createResp['code'] < 300 && !empty($createResp['body']['shipment_id']));
                            $debugResults['steps'][] = [
                                'name' => 'Create Order',
                                'status' => $createOk ? 'pass' : 'error',
                                'message' => "HTTP {$createResp['code']} — " . round(($t2 - $t1) * 1000) . "ms",
                                'data' => $createResp['body'] ?? $createResp['raw'],
                                'raw' => $createResp['raw'],
                                'curl_error' => $createResp['error'] ?? '',
                            ];

                            if (!$createOk || $stopAfter === 'create') {
                                // stop
                            } else {
                                $srOrderId = $createResp['body']['order_id'] ?? '';
                                $srShipmentId = $createResp['body']['shipment_id'] ?? '';

                                // ── STEP 5: Assign AWB ──
                                $t1 = microtime(true);
                                $awbResp = shiprocket_request($baseUrl, '/v1/external/courier/assign/awb', $token, [
                                    'shipment_id' => (int)$srShipmentId,
                                ]);
                                $t2 = microtime(true);

                                $body = $awbResp['body'] ?? [];
                                $awbData = $body['response']['data'] ?? $body['data'] ?? $body ?? [];
                                $awbCode = $awbData['awb_code'] ?? ($body['awb_code'] ?? '');
                                $courierName = $awbData['courier_name'] ?? ($body['courier_name'] ?? '');
                                $assignStatus = $body['awb_assign_status'] ?? ($body['response']['data']['awb_assign_status'] ?? null);
                                $courierId = $awbData['courier_company_id'] ?? ($body['courier_company_id'] ?? '');

                                $awbOk = ($awbResp['code'] >= 200 && $awbResp['code'] < 300 && !empty($awbCode));
                                $debugResults['steps'][] = [
                                    'name' => 'Assign AWB',
                                    'status' => $awbOk ? 'pass' : ($awbResp['code'] >= 200 && $awbResp['code'] < 300 ? 'warning' : 'error'),
                                    'message' => "HTTP {$awbResp['code']} — " . round(($t2 - $t1) * 1000) . "ms | AWB: " . ($awbCode ?: 'EMPTY') . " | Courier: " . ($courierName ?: 'EMPTY') . " | assign_status: " . json_encode($assignStatus),
                                    'data' => $body,
                                    'raw' => $awbResp['raw'],
                                    'curl_error' => $awbResp['error'] ?? '',
                                    'parsed' => [
                                        'awb_code' => $awbCode,
                                        'courier_name' => $courierName,
                                        'courier_company_id' => $courierId,
                                        'awb_assign_status' => $assignStatus,
                                    ],
                                ];

                                if ($stopAfter === 'awb') {
                                    // stop
                                } else {
                                    // ── STEP 6: Request Pickup ──
                                    $t1 = microtime(true);
                                    $pickupResp = shiprocket_request($baseUrl, '/v1/external/courier/generate/pickup', $token, [
                                        'shipment_id' => [$srShipmentId],
                                    ]);
                                    $t2 = microtime(true);

                                    $debugResults['steps'][] = [
                                        'name' => 'Generate Pickup',
                                        'status' => ($pickupResp['code'] >= 200 && $pickupResp['code'] < 300) ? 'pass' : 'warning',
                                        'message' => "HTTP {$pickupResp['code']} — " . round(($t2 - $t1) * 1000) . "ms",
                                        'data' => $pickupResp['body'] ?? $pickupResp['raw'],
                                        'raw' => $pickupResp['raw'],
                                    ];

                                    if ($stopAfter === 'pickup') {
                                        // stop
                                    } else {
                                        // ── STEP 7: Get Label ──
                                        $t1 = microtime(true);
                                        $labelResp = shiprocket_request($baseUrl, '/v1/external/courier/generate/label', $token, [
                                            'shipment_id' => [$srShipmentId],
                                        ]);
                                        $t2 = microtime(true);

                                        $labelUrl = $labelResp['body']['label_url'] ?? '';
                                        $debugResults['steps'][] = [
                                            'name' => 'Generate Label',
                                            'status' => !empty($labelUrl) ? 'pass' : 'warning',
                                            'message' => "HTTP {$labelResp['code']} — " . round(($t2 - $t1) * 1000) . "ms | Label: " . ($labelUrl ?: 'EMPTY'),
                                            'data' => $labelResp['body'] ?? $labelResp['raw'],
                                            'raw' => $labelResp['raw'],
                                        ];

                                        // ── SUMMARY ──
                                        $debugResults['summary'] = [
                                            'sr_order_id' => $srOrderId,
                                            'shipment_id' => $srShipmentId,
                                            'awb_code' => $awbCode,
                                            'courier_name' => $courierName,
                                            'label_url' => $labelUrl,
                                            'overall' => !empty($awbCode) ? 'SUCCESS' : 'AWB_MISSING',
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $debugResults['finished_at'] = date('Y-m-d H:i:s');
  } catch (Exception $e) {
      if (!$debugResults) $debugResults = ['steps' => [], 'started_at' => date('Y-m-d H:i:s')];
      $debugResults['steps'][] = ['name' => 'PHP Exception', 'status' => 'error', 'message' => $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()];
      $debugResults['finished_at'] = date('Y-m-d H:i:s');
  } catch (\Error $e) {
      if (!$debugResults) $debugResults = ['steps' => [], 'started_at' => date('Y-m-d H:i:s')];
      $debugResults['steps'][] = ['name' => 'PHP Fatal Error', 'status' => 'error', 'message' => $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()];
      $debugResults['finished_at'] = date('Y-m-d H:i:s');
  }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.dbg-card { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; margin-bottom:20px; }
.dbg-header { background:linear-gradient(135deg,#1e293b,#334155); color:#fff; padding:24px; }
.dbg-header h4 { margin:0; font-weight:700; }
.dbg-header p { margin:4px 0 0; opacity:.7; font-size:13px; }
.dbg-body { padding:20px 24px; }
.dbg-form { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.dbg-form .full { grid-column:1/-1; }
.dbg-label { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:6px; }
.dbg-select, .dbg-btn { width:100%; padding:10px 14px; border-radius:8px; border:1px solid #e2e8f0; font-size:14px; }
.dbg-btn { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border:none; font-weight:700; cursor:pointer; transition:all .2s; }
.dbg-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(37,99,235,.3); }
.dbg-btn-danger { background:linear-gradient(135deg,#ef4444,#dc2626); }

.step { border:1px solid #e2e8f0; border-radius:12px; margin-bottom:12px; overflow:hidden; }
.step-header { padding:14px 18px; display:flex; align-items:center; gap:12px; cursor:pointer; }
.step-header:hover { background:#f8fafc; }
.step-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.step-icon.pass { background:#dcfce7; color:#16a34a; }
.step-icon.error { background:#fee2e2; color:#dc2626; }
.step-icon.warning { background:#fef3c7; color:#d97706; }
.step-icon.info { background:#dbeafe; color:#2563eb; }
.step-name { font-weight:700; font-size:14px; flex:1; }
.step-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
.step-badge.pass { background:#dcfce7; color:#16a34a; }
.step-badge.error { background:#fee2e2; color:#dc2626; }
.step-badge.warning { background:#fef3c7; color:#d97706; }
.step-badge.info { background:#dbeafe; color:#2563eb; }
.step-msg { font-size:13px; color:#64748b; margin-top:2px; }
.step-detail { display:none; padding:0 18px 16px; border-top:1px solid #f1f5f9; }
.step-detail.open { display:block; }
.step-json { background:#1e293b; color:#e2e8f0; border-radius:8px; padding:14px; font-size:12px; font-family:'Courier New',monospace; overflow-x:auto; max-height:400px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; margin-top:10px; }
.step-raw { background:#fafafa; border:1px solid #e2e8f0; border-radius:8px; padding:14px; font-size:11px; font-family:monospace; overflow-x:auto; max-height:200px; overflow-y:auto; word-break:break-all; margin-top:8px; }

.summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-top:16px; }
.summary-item { background:#f8fafc; border-radius:10px; padding:16px; text-align:center; }
.summary-item.success { background:#f0fdf4; border:1px solid #bbf7d0; }
.summary-item.fail { background:#fef2f2; border:1px solid #fecaca; }
.summary-val { font-size:18px; font-weight:700; font-family:monospace; margin-top:4px; }
.summary-label { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#64748b; }
</style>

<section class="py-4">
<div class="container-fluid" style="max-width:900px;">

    <!-- Form Card -->
    <div class="dbg-card">
        <div class="dbg-header">
            <h4><i class="fas fa-bug me-2"></i>Shiprocket API Debug Tool</h4>
            <p>Test the full shipment creation flow step-by-step and inspect raw API responses</p>
        </div>
        <div class="dbg-body">
            <form method="POST" class="dbg-form">
                <div>
                    <div class="dbg-label">API Partner</div>
                    <select name="partner_id" class="dbg-select" required>
                        <option value="">— Select API Partner —</option>
                        <?php foreach ($partners as $p): ?>
                        <option value="<?= $p['id']; ?>" <?= (($p['id'] ?? '') == ($_POST['partner_id'] ?? '')) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($p['partner_name']); ?> (<?= $p['partner_code']; ?>) — <?= $p['base_url']; ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($partners)): ?>
                        <option disabled>No API partners found! Add one in Shipping Partners.</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <div class="dbg-label">Order to Test</div>
                    <select name="order_id" class="dbg-select" required>
                        <option value="">— Select Order —</option>
                        <?php foreach ($recentOrders as $ro): ?>
                        <option value="<?= $ro['id']; ?>" <?= (($ro['id'] ?? '') == ($_POST['order_id'] ?? '')) ? 'selected' : ''; ?>>
                            #<?= $ro['id']; ?> — <?= htmlspecialchars($ro['customer_name'] ?? 'Guest'); ?> — ₹<?= number_format($ro['total_amount'],2); ?> — <?= $ro['order_status']; ?> <?= $ro['tracking_id'] ? "[AWB: {$ro['tracking_id']}]" : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <div class="dbg-label">Stop After Step</div>
                    <select name="stop_after" class="dbg-select">
                        <option value="all" <?= ($_POST['stop_after'] ?? '') === 'all' ? 'selected' : ''; ?>>Run All Steps</option>
                        <option value="auth" <?= ($_POST['stop_after'] ?? '') === 'auth' ? 'selected' : ''; ?>>1. Auth Only</option>
                        <option value="create_preview" <?= ($_POST['stop_after'] ?? '') === 'create_preview' ? 'selected' : ''; ?>>2. Preview Payload (no API call)</option>
                        <option value="create" <?= ($_POST['stop_after'] ?? '') === 'create' ? 'selected' : ''; ?>>3. Create Order</option>
                        <option value="awb" <?= ($_POST['stop_after'] ?? '') === 'awb' ? 'selected' : ''; ?>>4. Assign AWB</option>
                        <option value="pickup" <?= ($_POST['stop_after'] ?? '') === 'pickup' ? 'selected' : ''; ?>>5. Generate Pickup</option>
                    </select>
                </div>
                <div>
                    <div class="dbg-label">&nbsp;</div>
                    <button type="submit" name="run_debug" value="1" class="dbg-btn">
                        <i class="fas fa-play me-2"></i>Run Debug
                    </button>
                </div>
                <div class="full" style="display:flex;align-items:center;gap:8px;padding:6px 0;">
                    <input type="checkbox" name="force_fresh_login" id="forceFresh" value="1" <?= !empty($_POST['force_fresh_login']) ? 'checked' : ''; ?> style="width:18px;height:18px;">
                    <label for="forceFresh" style="font-size:13px;font-weight:600;color:#374151;cursor:pointer;">Force Fresh Login (bypass cached token — recommended if getting 403)</label>
                </div>
            </form>
        </div>
    </div>

    <?php if ($debugResults): ?>
    <!-- Results -->
    <div class="dbg-card">
        <div class="dbg-header" style="background:linear-gradient(135deg,<?php
            $hasError = false; $hasWarning = false;
            foreach ($debugResults['steps'] as $s) { if ($s['status'] === 'error') $hasError = true; if ($s['status'] === 'warning') $hasWarning = true; }
            echo $hasError ? '#991b1b,#dc2626' : ($hasWarning ? '#92400e,#d97706' : '#065f46,#059669');
        ?>);">
            <h4><i class="fas fa-<?= $hasError ? 'times-circle' : ($hasWarning ? 'exclamation-triangle' : 'check-circle'); ?> me-2"></i>Debug Results</h4>
            <p><?= $debugResults['started_at']; ?> → <?= $debugResults['finished_at']; ?><?php
                if (!empty($debugResults['effective_base_url'])) echo " | URL: " . $debugResults['effective_base_url'];
                if (isset($debugResults['is_shiprocket_detected'])) echo " | Shiprocket: " . ($debugResults['is_shiprocket_detected'] ? 'YES' : 'NO') . " ({$debugResults['detection_method']})";
            ?></p>
        </div>
        <div class="dbg-body">

            <?php if (!empty($debugResults['summary'])): ?>
            <div class="summary-grid">
                <?php $s = $debugResults['summary']; ?>
                <div class="summary-item <?= !empty($s['awb_code']) ? 'success' : 'fail'; ?>">
                    <div class="summary-label">AWB / Tracking</div>
                    <div class="summary-val"><?= $s['awb_code'] ?: '—'; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Courier</div>
                    <div class="summary-val" style="font-size:14px;"><?= $s['courier_name'] ?: '—'; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">SR Order ID</div>
                    <div class="summary-val"><?= $s['sr_order_id'] ?: '—'; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Shipment ID</div>
                    <div class="summary-val"><?= $s['shipment_id'] ?: '—'; ?></div>
                </div>
                <div class="summary-item <?= !empty($s['label_url']) ? 'success' : ''; ?>">
                    <div class="summary-label">Label</div>
                    <div class="summary-val" style="font-size:13px;">
                        <?php if ($s['label_url']): ?>
                        <a href="<?= htmlspecialchars($s['label_url']); ?>" target="_blank" class="text-primary"><i class="fas fa-file-pdf"></i> Download</a>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
                <div class="summary-item <?= $s['overall'] === 'SUCCESS' ? 'success' : 'fail'; ?>">
                    <div class="summary-label">Overall</div>
                    <div class="summary-val"><?= $s['overall']; ?></div>
                </div>
            </div>
            <hr style="margin:20px 0;">
            <?php endif; ?>

            <h6 class="fw-bold mb-3">Step-by-Step Log (<?= count($debugResults['steps']); ?> steps)</h6>
            <?php foreach ($debugResults['steps'] as $i => $step): ?>
            <div class="step">
                <div class="step-header" onclick="this.nextElementSibling.classList.toggle('open')">
                    <div class="step-icon <?= $step['status']; ?>">
                        <?php $iconMap = ['pass'=>'check','error'=>'times','warning'=>'exclamation','info'=>'info']; ?>
                        <i class="fas fa-<?= $iconMap[$step['status']] ?? 'circle'; ?>"></i>
                    </div>
                    <div style="flex:1;">
                        <div class="step-name"><?= ($i + 1) . '. ' . htmlspecialchars($step['name']); ?></div>
                        <div class="step-msg"><?= htmlspecialchars($step['message']); ?></div>
                    </div>
                    <span class="step-badge <?= $step['status']; ?>"><?= strtoupper($step['status']); ?></span>
                    <i class="fas fa-chevron-down" style="color:#94a3b8;font-size:12px;"></i>
                </div>
                <div class="step-detail">
                    <?php if (!empty($step['parsed'])): ?>
                    <div style="margin-top:10px;">
                        <strong style="font-size:12px;text-transform:uppercase;color:#64748b;">Parsed Values:</strong>
                        <div class="step-json"><?= json_encode($step['parsed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($step['data'])): ?>
                    <div style="margin-top:10px;">
                        <strong style="font-size:12px;text-transform:uppercase;color:#64748b;">Response Data:</strong>
                        <div class="step-json"><?= is_array($step['data']) ? json_encode($step['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : htmlspecialchars($step['data']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($step['raw'])): ?>
                    <div style="margin-top:8px;">
                        <strong style="font-size:12px;text-transform:uppercase;color:#64748b;">Raw Response:</strong>
                        <div class="step-raw"><?= htmlspecialchars($step['raw']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($step['curl_error'])): ?>
                    <div style="margin-top:8px;">
                        <strong style="font-size:12px;text-transform:uppercase;color:#ef4444;">cURL Error:</strong>
                        <div class="step-raw" style="border-color:#fecaca;background:#fef2f2;"><?= htmlspecialchars($step['curl_error']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Reference -->
    <div class="dbg-card">
        <div class="dbg-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb text-warning me-2"></i>Common AWB Failure Reasons</h6>
            <table class="table table-sm table-bordered" style="font-size:13px;">
                <thead class="table-light"><tr><th>Issue</th><th>Symptom</th><th>Fix</th></tr></thead>
                <tbody>
                    <tr><td>Partner not detected as Shiprocket</td><td>Falls into placeholder code, returns empty data</td><td>Set partner_code to <code>shiprocket</code> or base_url must contain "shiprocket"</td></tr>
                    <tr><td>Credentials wrong</td><td>Auth step fails (HTTP 401/422)</td><td>Re-enter email/password in Shipping Partners</td></tr>
                    <tr><td>No pickup location</td><td>Create order fails (422)</td><td>Set up "Primary" pickup location in Shiprocket dashboard</td></tr>
                    <tr><td>Invalid pincode</td><td>Create order or AWB fails</td><td>Ensure billing_pincode is valid 6-digit Indian pincode</td></tr>
                    <tr><td>No serviceable courier</td><td>AWB assign returns empty awb_code</td><td>Check serviceability for the pincode in Shiprocket dashboard</td></tr>
                    <tr><td>Duplicate order_id</td><td>Create order fails with "already exists"</td><td>Order was already sent to Shiprocket before</td></tr>
                    <tr><td>Wallet balance low</td><td>AWB fails silently</td><td>Recharge Shiprocket wallet</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
