<?php
/**
 * API: Inline Login for Checkout
 * POST /api/inline_login.php
 * Body: phone=XXXXXXXXXX&password=secret
 * Returns: { success: bool, error?: string }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

$phone    = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// Determine identifier: prefer phone, fall back to email
if (strlen($phone) === 10) {
    $identifier = $phone;
} elseif (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $identifier = $email;
} else {
    echo json_encode(['success' => false, 'error' => 'Phone or email and password are required.']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required.']);
    exit;
}

// attempt_login handles: rate limiting, password_verify, PIN login, session setup
$ok = attempt_login($identifier, $password, 'user');

if ($ok) {
    // Don't expose is_admin users through this endpoint
    if (!empty($_SESSION['user']['is_admin'])) {
        logout_user();
        echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
        exit;
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Incorrect password. Please try again.']);
}
exit;
