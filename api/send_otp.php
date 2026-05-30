<?php
/**
 * API: Send OTP
 * POST /api/send_otp.php
 * Body: { phone: "9876543210", purpose: "login" }
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

// Rate limit by IP
if (function_exists('rate_limit_check')) {
    $rateCheck = rate_limit_check('send_otp', 5, 300, 600);
    if (!$rateCheck['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a few minutes.']);
        exit;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? $_POST['phone'] ?? '');
$purpose = trim($input['purpose'] ?? $_POST['purpose'] ?? 'login');

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Phone number is required']);
    exit;
}

// Validate purpose
if (!in_array($purpose, ['login', 'signup', 'verify', 'order'])) {
    $purpose = 'login';
}

$sms = new SMSService();
$result = $sms->sendOTP($phone, $purpose);

echo json_encode($result);
