<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('user/orders.php', 'Invalid request method', 'error');
}

$userId = (int)$_SESSION['user']['id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$reviewText = trim($_POST['review_text'] ?? '');

// Basic Validation
if ($orderId <= 0 || $productId <= 0) {
    redirect_with_message('user/orders.php', 'Invalid order or product details', 'error');
}

if ($rating < 1 || $rating > 5) {
    redirect_with_message("user/order_details.php?id=$orderId", 'Please provide a valid rating between 1 and 5 stars.', 'error');
}

if (empty($reviewText)) {
    redirect_with_message("user/order_details.php?id=$orderId", 'Please write a review describing your experience.', 'error');
}

// Verify that the user actually purchased this product in this order
$db = get_db_connection();
$stmt = $db->prepare("
    SELECT 1 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = ? AND o.user_id = ? AND oi.product_id = ? AND o.order_status = 'delivered'
");
$stmt->execute([$orderId, $userId, $productId]);

if (!$stmt->fetchColumn()) {
    redirect_with_message("user/order_details.php?id=$orderId", 'You can only review products from delivered orders you have purchased.', 'error');
}

// Check if review already exists
$checkStmt = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?");
$checkStmt->execute([$userId, $productId, $orderId]);

if ($checkStmt->fetchColumn()) {
    // Update existing review
    $updateStmt = $db->prepare("UPDATE reviews SET rating = ?, review_text = ?, created_at = NOW() WHERE user_id = ? AND product_id = ? AND order_id = ?");
    $result = $updateStmt->execute([$rating, $reviewText, $userId, $productId, $orderId]);
    $message = 'Review updated successfully!';
} else {
    // Insert new review
    $insertStmt = $db->prepare("INSERT INTO reviews (user_id, product_id, order_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
    $result = $insertStmt->execute([$userId, $productId, $orderId, $rating, $reviewText]);
    $message = 'Thank you for your review!';
}

if ($result) {
    redirect_with_message("user/order_details.php?id=$orderId", $message, 'success');
} else {
    redirect_with_message("user/order_details.php?id=$orderId", 'Failed to save review. Please try again.', 'error');
}
