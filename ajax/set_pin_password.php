<?php
/**
 * AJAX: Set PIN or Password for newly created guest account
 * Called from post-checkout popup on thank-you.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$type   = trim($_POST['type'] ?? '');   // 'pin' or 'password'
$value  = $_POST['value'] ?? '';
$confirm = $_POST['confirm'] ?? '';

if ($type === 'pin') {
    // Validate: 4–6 digits
    if (!preg_match('/^[0-9]{4,6}$/', $value)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be 4–6 digits']);
        exit;
    }
    if ($value !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'PINs do not match']);
        exit;
    }
    $hashed = password_hash($value, PASSWORD_DEFAULT);
    try {
        db_query("UPDATE users SET pin = ?, temp_password_active = 0 WHERE id = ?", [$hashed, $userId]);
        echo json_encode(['success' => true, 'message' => 'PIN saved successfully!']);
    } catch (Exception $e) {
        error_log("set_pin failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not save PIN. Please try again.']);
    }
    exit;
}

if ($type === 'password') {
    // Validate: min 8 chars
    if (strlen($value) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    if ($value !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    $hashed = password_hash($value, PASSWORD_DEFAULT);
    try {
        db_query("UPDATE users SET password = ?, temp_password_active = 0 WHERE id = ?", [$hashed, $userId]);
        echo json_encode(['success' => true, 'message' => 'Password saved successfully!']);
    } catch (Exception $e) {
        error_log("set_password failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not save password. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid type']);
