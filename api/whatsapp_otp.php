<?php
/**
 * WhatsApp OTP API
 * Frontend endpoint for sending and verifying OTPs via WhatsApp.
 * 
 * POST /api/whatsapp_otp.php
 *   action=send    → sends OTP to phone
 *   action=verify  → verifies OTP
 *   action=resend  → resends OTP (with cooldown)
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/crm_engine.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$crm = CRMEngine::getInstance();

if (!$crm->getSetting('whatsapp_otp_enabled')) {
    echo json_encode(['success' => false, 'error' => 'WhatsApp OTP is not enabled']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';
$phone = trim($input['phone'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Validate phone
if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Phone number is required']);
    exit;
}

// Normalize phone (Indian format)
$phone = preg_replace('/[^0-9+]/', '', $phone);
if (strlen($phone) === 10 && !str_starts_with($phone, '+')) {
    $phone = '+91' . $phone;
} elseif (strlen($phone) === 12 && str_starts_with($phone, '91')) {
    $phone = '+' . $phone;
}

switch ($action) {
    case 'send':
    case 'resend':
        // Check resend cooldown
        if ($action === 'resend') {
            $cooldown = $crm->getSetting('whatsapp_otp_resend_cooldown', 60);
            $lastOtp = db_fetch(
                "SELECT created_at FROM crm_whatsapp_otp WHERE phone = ? ORDER BY created_at DESC LIMIT 1",
                [$phone]
            );
            if ($lastOtp && (time() - strtotime($lastOtp['created_at'])) < $cooldown) {
                $remaining = $cooldown - (time() - strtotime($lastOtp['created_at']));
                echo json_encode([
                    'success' => false,
                    'error' => "Please wait {$remaining} seconds before requesting another OTP",
                    'retry_after' => $remaining,
                ]);
                exit;
            }
        }

        $result = $crm->sendOTP($phone, 'login', $ip);
        echo json_encode($result);
        break;

    case 'verify':
        $otp = trim($input['otp'] ?? '');
        if (empty($otp) || strlen($otp) !== 6) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid 6-digit OTP']);
            exit;
        }

        $result = $crm->verifyOTP($phone, $otp);
        
        if ($result['success']) {
            // OTP verified — login or register the user
            $user = db_fetch("SELECT * FROM users WHERE phone = ? OR mobile = ? LIMIT 1", [$phone, $phone]);
            
            if ($user) {
                // Existing user — start session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'] ?? $user['full_name'] ?? '',
                    'email' => $user['email'] ?? '',
                    'phone' => $phone,
                    'role' => $user['role'] ?? 'customer',
                    'login_method' => 'whatsapp_otp',
                ];
                
                // Fire login event to CRM
                $crm->fireEvent('customer.login', [
                    'user_id' => $user['id'],
                    'phone' => $phone,
                    'method' => 'whatsapp_otp',
                ]);

                echo json_encode([
                    'success' => true,
                    'action' => 'login',
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'] ?? $user['full_name'] ?? '',
                    ],
                    'redirect' => base_url('user/dashboard.php'),
                ]);
            } else {
                // New user — auto-register with phone
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (phone, mobile, role, created_at) VALUES (?, ?, 'customer', NOW())"
                    );
                    $stmt->execute([$phone, $phone]);
                    $newUserId = (int)$pdo->lastInsertId();

                    $_SESSION['user'] = [
                        'id' => $newUserId,
                        'name' => '',
                        'email' => '',
                        'phone' => $phone,
                        'role' => 'customer',
                        'login_method' => 'whatsapp_otp',
                    ];

                    // Fire events to CRM
                    $crm->fireEvent('customer.created', [
                        'user_id' => $newUserId,
                        'phone' => $phone,
                        'source' => 'whatsapp_otp',
                    ]);

                    echo json_encode([
                        'success' => true,
                        'action' => 'register',
                        'user' => ['id' => $newUserId],
                        'redirect' => base_url('user/complete-profile.php'),
                    ]);
                } catch (\PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
                }
            }
        } else {
            echo json_encode($result);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action. Use: send, verify, resend']);
}
