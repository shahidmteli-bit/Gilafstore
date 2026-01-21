<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/includes/db_connect.php';
    require_once __DIR__ . '/includes/promo_functions.php';
    require_once __DIR__ . '/includes/functions.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server configuration error: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'apply':
            $code = $_POST['code'] ?? '';
            
            if (empty($code)) {
                throw new Exception('Please enter a promo code');
            }
            
            // Calculate cart total
            $cart = $_SESSION['cart'] ?? [];
            if (empty($cart)) {
                throw new Exception('Your cart is empty');
            }
            
            $cartTotal = 0;
            foreach ($cart as $cartKey => $cartItem) {
                // Handle both old format (productId => quantity) and new format (cartKey => array)
                if (is_array($cartItem)) {
                    $productId = (int)$cartItem['product_id'];
                    $quantity = (int)$cartItem['quantity'];
                    $weightId = $cartItem['weight_id'] ?? null;
                } else {
                    $productId = (int)$cartKey;
                    $quantity = (int)$cartItem;
                    $weightId = null;
                }
                
                $product = get_product($productId);
                if ($product) {
                    // Get price from weight if available
                    if ($weightId) {
                        $weightData = db_fetch("SELECT price FROM product_weights WHERE id = ?", [$weightId]);
                        $price = $weightData ? (float)$weightData['price'] : (float)$product['price'];
                    } else {
                        $productWithDiscount = enrich_products_with_discounts([$product])[0];
                        $price = $productWithDiscount['has_discount'] ? 
                                 $productWithDiscount['discounted_price'] : 
                                 $productWithDiscount['price'];
                    }
                    $cartTotal += $price * $quantity;
                }
            }
            
            // Get user email and phone for eligibility check
            $userEmail = $_SESSION['user']['email'] ?? null;
            $userPhone = $_SESSION['user']['phone'] ?? null;
            
            // Apply promo code with user identification
            $result = apply_promo_code($code, $cartTotal, $userEmail, $userPhone);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'discount' => $result['discount'],
                'code' => strtoupper($code),
                'new_total' => $cartTotal - $result['discount']
            ]);
            break;
            
        case 'remove':
            remove_promo_code();
            
            echo json_encode([
                'success' => true,
                'message' => 'Promo code removed'
            ]);
            break;
            
        case 'validate':
            // Validate current promo code with updated cart total
            $cart = $_SESSION['cart'] ?? [];
            $cartTotal = 0;
            
            foreach ($cart as $cartKey => $cartItem) {
                // Handle both old format (productId => quantity) and new format (cartKey => array)
                if (is_array($cartItem)) {
                    $productId = (int)$cartItem['product_id'];
                    $quantity = (int)$cartItem['quantity'];
                    $weightId = $cartItem['weight_id'] ?? null;
                } else {
                    $productId = (int)$cartKey;
                    $quantity = (int)$cartItem;
                    $weightId = null;
                }
                
                $product = get_product($productId);
                if ($product) {
                    // Get price from weight if available
                    if ($weightId) {
                        $weightData = db_fetch("SELECT price FROM product_weights WHERE id = ?", [$weightId]);
                        $price = $weightData ? (float)$weightData['price'] : (float)$product['price'];
                    } else {
                        $productWithDiscount = enrich_products_with_discounts([$product])[0];
                        $price = $productWithDiscount['has_discount'] ? 
                                 $productWithDiscount['discounted_price'] : 
                                 $productWithDiscount['price'];
                    }
                    $cartTotal += $price * $quantity;
                }
            }
            
            $discount = recalculate_promo_discount($cartTotal);
            $appliedPromo = get_applied_promo_code();
            
            if (!$appliedPromo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No promo code applied'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'discount' => $discount,
                    'code' => $appliedPromo['code'],
                    'new_total' => $cartTotal - $discount
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $e->getFile() . ':' . $e->getLine()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage(),
        'debug' => $e->getFile() . ':' . $e->getLine()
    ]);
}
