<?php
/**
 * API: Verify OTP & Login
 * POST /api/verify_otp.php
 * Body: { phone: "9876543210", otp: "123456", purpose: "login" }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_service.php';

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? $_POST['phone'] ?? '');
$otp = trim($input['otp'] ?? $_POST['otp'] ?? '');
$purpose = trim($input['purpose'] ?? $_POST['purpose'] ?? 'login');
$redirect = trim($input['redirect'] ?? $_POST['redirect'] ?? '');

if (empty($phone) || empty($otp)) {
    echo json_encode(['success' => false, 'error' => 'Phone and OTP are required']);
    exit;
}

$sms = new SMSService();
$result = $sms->verifyOTP($phone, $otp, $purpose);

if (!$result['success']) {
    echo json_encode($result);
    exit;
}

// OTP verified — log the user in (or create account)
$loginResult = otp_login_user($phone);

if ($loginResult['success']) {
    $response = [
        'success' => true,
        'message' => $loginResult['is_new'] ? 'Account created & logged in!' : 'Welcome back!',
        'is_new' => $loginResult['is_new'],
        'redirect' => $redirect === 'checkout' ? '/checkout.php' : '/index.php',
    ];
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'error' => $loginResult['error'] ?? 'Login failed']);
}
