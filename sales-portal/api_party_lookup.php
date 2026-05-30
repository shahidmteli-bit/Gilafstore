<?php
/**
 * Sales Portal API - Party Lookup by code or search term
 * Returns JSON for AJAX calls from QR scanner / search
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// For API endpoints: return JSON error instead of HTML redirect
if (!sales_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$exec = sales_get_executive();
$execId = $exec['id'];

$code = trim($_GET['code'] ?? '');
$search = trim($_GET['search'] ?? '');
$idParam = (int)($_GET['id'] ?? 0);

// Helper to format party data safely
function formatPartyResponse($party) {
    return [
        'id' => (int)$party['id'],
        'party_code' => $party['party_code'] ?? '',
        'shop_name' => $party['shop_name'] ?? '',
        'owner_name' => $party['owner_name'] ?? '',
        'phone' => $party['phone'] ?? '',
        'email' => $party['email'] ?? '',
        'address' => $party['address'] ?? '',
        'district' => $party['district'] ?? '',
        'city' => $party['city'] ?? '',
        'state' => $party['state'] ?? '',
        'pincode' => $party['pincode'] ?? '',
        'gst_number' => $party['gst_number'] ?? '',
        'credit_limit' => (float)($party['credit_limit'] ?? 0),
        'outstanding_amount' => (float)($party['outstanding_amount'] ?? 0),
        'latitude' => $party['latitude'] ?? null,
        'longitude' => $party['longitude'] ?? null,
        'google_maps_url' => $party['google_maps_url'] ?? '',
        'profile_type' => $party['profile_type'] ?? 'wholesaler',
    ];
}

try {

if ($idParam > 0) {
    $party = db_fetch('SELECT * FROM sales_parties WHERE id = ? AND is_active = 1', [$idParam]);
    if ($party) {
        echo json_encode(['success' => true, 'party' => formatPartyResponse($party)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Party not found with ID: ' . $idParam]);
    }
    exit;
}

if ($code) {
    $party = db_fetch('SELECT * FROM sales_parties WHERE party_code = ? AND created_by = ? AND is_active = 1', [$code, $execId]);
    if (!$party) {
        $party = db_fetch('SELECT * FROM sales_parties WHERE party_code = ? AND is_active = 1', [$code]);
    }
    if ($party) {
        echo json_encode(['success' => true, 'party' => formatPartyResponse($party)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Party not found with code: ' . $code]);
    }
} elseif ($search && strlen($search) >= 2) {
    $like = '%' . $search . '%';
    $parties = db_fetch_all('SELECT id, party_code, shop_name, owner_name, phone, district, outstanding_amount, credit_limit, profile_type FROM sales_parties WHERE created_by = ? AND is_active = 1 AND (shop_name LIKE ? OR owner_name LIKE ? OR phone LIKE ? OR party_code LIKE ?) ORDER BY shop_name ASC LIMIT 10', [$execId, $like, $like, $like, $like]);
    echo json_encode(['success' => true, 'parties' => $parties]);
} else {
    echo json_encode(['success' => false, 'message' => 'Provide ?code= or ?search= parameter']);
}

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
