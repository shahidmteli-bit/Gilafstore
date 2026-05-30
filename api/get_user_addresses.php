<?php
/**
 * API: Get default saved address for a phone number
 * POST /api/get_user_addresses.php
 * Body: phone=XXXXXXXXXX
 * Returns: { success: bool, name?: string, address?: {...} }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';

$phone = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
$email = strtolower(trim($_POST['email'] ?? ''));

$user = null;
if (strlen($phone) === 10) {
    $user = db_fetch('SELECT id, name FROM users WHERE phone = ?', [$phone]);
} elseif (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $user = db_fetch('SELECT id, name FROM users WHERE email = ?', [$email]);
} else {
    echo json_encode(['success' => false]);
    exit;
}

if (!$user) {
    echo json_encode(['success' => false]);
    exit;
}

$userId    = (int)$user['id'];
$fullName  = trim($user['name']);
$firstName = explode(' ', $fullName)[0];

// Fetch default address first, then any other
$address = db_fetch(
    'SELECT id, type, address_line1, address_line2, city, state, zip_code, phone, is_default
     FROM user_addresses
     WHERE user_id = ?
     ORDER BY is_default DESC, id DESC
     LIMIT 1',
    [$userId]
);

if (!$address) {
    echo json_encode(['success' => true, 'name' => $firstName, 'full_name' => $fullName, 'address' => null]);
    exit;
}

echo json_encode([
    'success'    => true,
    'name'       => htmlspecialchars($firstName, ENT_QUOTES),
    'full_name'  => htmlspecialchars($fullName,  ENT_QUOTES),
    'address' => [
        'id'           => (int)$address['id'],
        'type'         => htmlspecialchars($address['type'],          ENT_QUOTES),
        'address_line1'=> htmlspecialchars($address['address_line1'], ENT_QUOTES),
        'address_line2'=> htmlspecialchars($address['address_line2'] ?? '', ENT_QUOTES),
        'city'         => htmlspecialchars($address['city'],          ENT_QUOTES),
        'state'        => htmlspecialchars($address['state'],         ENT_QUOTES),
        'zip_code'     => htmlspecialchars($address['zip_code'],      ENT_QUOTES),
        'phone'        => htmlspecialchars($address['phone'] ?? $phone, ENT_QUOTES),
        'is_default'   => (bool)$address['is_default'],
    ],
]);
exit;
