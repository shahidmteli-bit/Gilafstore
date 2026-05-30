<?php
/**
 * Shipping API AJAX Endpoints
 * - get_partners: Return active shipping partners for dropdowns
 * - create_shipment: Create shipment via Shiprocket (or other API partner)
 * - get_shipment: Get shipment details for an order
 * - track_shipment: Track AWB via Shiprocket
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/shipping_helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin']) && empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = get_db_connection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── Get active partners for order modal dropdowns ───
    case 'get_partners':
        $type = $_GET['type'] ?? '';
        $where = "is_active = 1";
        $params = [];
        if ($type === 'api' || $type === 'manual') {
            $where .= " AND integration_type = ?";
            $params[] = $type;
        }
        $stmt = $db->prepare("SELECT id, partner_name, partner_code, partner_type, integration_type, base_url FROM shipping_partners WHERE {$where} ORDER BY partner_name ASC");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'partners' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ─── Get shipment info for an order ───
    case 'get_shipment':
        $orderId = (int)($_GET['order_id'] ?? 0);
        if (!$orderId) { echo json_encode(['success' => false, 'message' => 'Missing order_id']); break; }
        $stmt = $db->prepare("SELECT * FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$orderId]);
        echo json_encode(['success' => true, 'shipment' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null]);
        break;

    // ─── Create shipment via API carrier ───
    case 'create_shipment':
        require_csrf_token();
        $orderId = (int)($_POST['order_id'] ?? 0);
        $partnerId = (int)($_POST['partner_id'] ?? 0);

        if (!$orderId || !$partnerId) {
            echo json_encode(['success' => false, 'message' => 'Missing order_id or partner_id']);
            break;
        }

        // Get partner
        $stmt = $db->prepare("SELECT * FROM shipping_partners WHERE id = ? AND integration_type = 'api' AND is_active = 1");
        $stmt->execute([$partnerId]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$partner) { echo json_encode(['success' => false, 'message' => 'API partner not found']); break; }

        $code = strtolower($partner['partner_code']);
        $adminId = $_SESSION['user']['id'] ?? $_SESSION['admin']['id'] ?? null;
        $baseUrlLower = strtolower($partner['base_url'] ?? '');

        // ──── SHIPROCKET (detect by code OR base_url) ────
        $isShiprocket = ($code === 'shiprocket' || strpos($baseUrlLower, 'shiprocket') !== false);
        if ($isShiprocket) {
            $srResult = shiprocket_create_full_shipment($db, $partner, $orderId, $adminId);
            echo json_encode([
                'success' => $srResult['success'],
                'message' => $srResult['message'],
                'data' => [
                    'sr_order_id' => $srResult['sr_order_id'],
                    'shipment_id' => $srResult['shipment_id'],
                    'awb_code' => $srResult['awb_code'] ?: 'Pending assignment',
                    'courier_name' => $srResult['courier_name'],
                    'label_url' => $srResult['label_url'],
                ],
            ]);
        } else {
            // Other carriers — placeholder
            $shipmentId = 'SHP' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '_' . time();
            $stmt = $db->prepare("INSERT INTO order_shipments (order_id, shipping_type, shipping_partner, shipping_partner_code, api_shipment_id, shipping_status, created_by_admin_id, notes) VALUES (?, 'api', ?, ?, ?, 'created', ?, ?)");
            $stmt->execute([$orderId, $partner['partner_name'], $partner['partner_code'], $shipmentId, $adminId, 'Shipment created. Carrier-specific integration pending.']);
            echo json_encode(['success' => true, 'message' => 'Shipment record created with ' . $partner['partner_name'], 'shipment_id' => $shipmentId]);
        }
        break;

    // ─── Track AWB via Shiprocket ───
    case 'track_shipment':
        $awb = trim($_GET['awb'] ?? '');
        $partnerId = (int)($_GET['partner_id'] ?? 0);
        if (empty($awb) || !$partnerId) { echo json_encode(['success' => false, 'message' => 'Missing AWB or partner_id']); break; }

        $stmt = $db->prepare("SELECT * FROM shipping_partners WHERE id = ?");
        $stmt->execute([$partnerId]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$partner) { echo json_encode(['success' => false, 'message' => 'Partner not found']); break; }

        $code = strtolower($partner['partner_code']);
        if ($code === 'shiprocket') {
            $auth = shiprocket_get_token($db, $partner);
            if (empty($auth['token'])) { echo json_encode(['success' => false, 'message' => 'Auth failed']); break; }
            $resp = shiprocket_request($auth['base_url'], '/v1/external/courier/track/awb/' . urlencode($awb), $auth['token'], null, 'GET');
            echo json_encode(['success' => $resp['code'] === 200, 'tracking' => $resp['body']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tracking not implemented for ' . $partner['partner_name']]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
}
