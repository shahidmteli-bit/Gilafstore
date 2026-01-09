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
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($productId <= 0) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
    
    switch ($action) {
        case 'add':
            // Add product to cart or increase quantity if already exists
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId] += $quantity;
            } else {
                $_SESSION['cart'][$productId] = $quantity;
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
            if ($quantity > 0) {
                $_SESSION['cart'][$productId] = $quantity;
            } else {
                unset($_SESSION['cart'][$productId]);
            }
            header('Location: ' . base_url('cart.php'));
            exit;
            
        case 'remove':
            cart_remove($productId);
            header('Location: ' . base_url('cart.php'));
            exit;
            
        default:
            header('Location: ' . base_url('index.php'));
            exit;
    }
}
