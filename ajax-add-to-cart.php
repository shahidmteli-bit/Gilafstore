<?php
/**
 * Minimal isolated AJAX add-to-cart endpoint.
 * No heavy includes — zero interference from other code.
 */

// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$weightId  = (int)($_POST['weight_id']  ?? 0);
$quantity  = max(1, (int)($_POST['quantity'] ?? 1));

if ($productId <= 0) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Invalid product_id']);
    exit;
}

// Init cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Cart key: productId_weightId  or  productId
$cartKey = $weightId > 0 ? $productId . '_' . $weightId : (string)$productId;

if (isset($_SESSION['cart'][$cartKey]) && is_array($_SESSION['cart'][$cartKey])) {
    $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
} else {
    $_SESSION['cart'][$cartKey] = [
        'product_id' => $productId,
        'weight_id'  => $weightId,
        'quantity'   => $quantity,
    ];
}

// Persist session before output
session_write_close();

// Return clean JSON — no buffer leakage
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
exit;
