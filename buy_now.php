<?php
/**
 * Buy Now - Single product quick checkout (separate from cart)
 * Does NOT add to cart - uses separate buy_now session
 */
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $weightId = (int)($_POST['weight_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($productId > 0 && $quantity > 0) {
        // Get product details
        $product = get_product($productId);
        
        if ($product) {
            // Get price from weight if available
            $price = (float)$product['price'];
            $weightName = null;
            
            if ($weightId > 0) {
                $weightData = db_fetch("SELECT price, display_weight FROM product_weights WHERE id = ?", [$weightId]);
                if ($weightData) {
                    $price = (float)$weightData['price'];
                    $weightName = $weightData['display_weight'];
                }
            }
            
            // Store in separate buy_now session (NOT cart)
            $_SESSION['buy_now'] = [
                'product_id' => $productId,
                'weight_id' => $weightId,
                'quantity' => $quantity,
                'price' => $price,
                'name' => $product['name'],
                'image' => $product['image'],
                'weight_name' => $weightName,
                'category_id' => $product['category_id'] ?? null
            ];
            
            // Track event for analytics
            if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
                trackProductEvent($productId, 'buy_now', 'product_page', $product['category_id'], $price, $quantity);
            }
        }
    }
}

// Redirect to checkout with buy_now flag
header('Location: ' . base_url('checkout.php?buy_now=1'));
exit;
