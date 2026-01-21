<?php
require_once __DIR__ . '/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions (add, update, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    $weightId = (int)($_POST['weight_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $cartKey = $_POST['cart_key'] ?? null;
    
    if ($productId <= 0) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
    
    switch ($action) {
        case 'add':
            // Create unique cart key based on product + weight combination
            $cartKey = $weightId > 0 ? $productId . '_' . $weightId : (string)$productId;
            
            if (isset($_SESSION['cart'][$cartKey]) && is_array($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $productId,
                    'weight_id' => $weightId,
                    'quantity' => $quantity
                ];
            }
            
            // Track add to cart event
            if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
                $product = get_product($productId);
                if ($product) {
                    trackProductEvent($productId, 'add_to_cart', 'cart_action', $product['category_id'], $product['price'], $quantity);
                }
            }
            
            header('Location: ' . base_url('cart.php'));
            exit;
            
        case 'update':
            $key = $cartKey ?? (string)$productId;
            if ($quantity > 0 && isset($_SESSION['cart'][$key])) {
                if (is_array($_SESSION['cart'][$key])) {
                    $_SESSION['cart'][$key]['quantity'] = $quantity;
                } else {
                    $_SESSION['cart'][$key] = $quantity;
                }
            } elseif ($quantity <= 0) {
                unset($_SESSION['cart'][$key]);
            }
            header('Location: ' . base_url('cart.php'));
            exit;
            
        case 'remove':
            $key = $cartKey ?? (string)$productId;
            if (isset($_SESSION['cart'][$key])) {
                unset($_SESSION['cart'][$key]);
            } else {
                cart_remove($productId);
            }
            header('Location: ' . base_url('cart.php'));
            exit;
            
        default:
            header('Location: ' . base_url('index.php'));
            exit;
    }
}
