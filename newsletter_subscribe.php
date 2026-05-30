<?php
/**
 * Newsletter Subscription Endpoint
 * Accepts POST: email
 * Returns JSON: {success, message}
 */

session_start();
require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Sanitize
$email = strtolower($email);

try {
    $db = get_db_connection();

    // Auto-create table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            email      VARCHAR(255) NOT NULL UNIQUE,
            source     VARCHAR(50)  DEFAULT 'blog',
            status     ENUM('active','unsubscribed') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert (IGNORE duplicate silently)
    $stmt = $db->prepare("INSERT IGNORE INTO newsletter_subscribers (email, source) VALUES (?, 'blog')");
    $stmt->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'You are now subscribed!']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Subscription failed. Please try again later.']);
}
