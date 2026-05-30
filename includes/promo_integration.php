<?php
/**
 * PROMOTIONAL SYSTEM INTEGRATION
 * Include this file in pages where promotions should be displayed
 */

require_once __DIR__ . '/promotional_system.php';

/**
 * Initialize promotional system on page load
 */
function init_promotional_system() {
    // Add CSS and JS to page
    echo '<link rel="stylesheet" href="/assets/css/promotional-system.css">';
    echo '<script src="/assets/js/promotional-system.js" defer></script>';
}

/**
 * Display promotions on product page
 */
function display_product_page_promos($productId, $categoryId) {
    $promotions = get_active_promotions('product', $productId, $categoryId);
    
    if (empty($promotions)) return;
    
    echo '<div class="product-promos">';
    foreach ($promotions as $promo) {
        echo render_promo_banner($promo, 'product');
    }
    echo '</div>';
}

/**
 * Display promotions on cart page
 */
function display_cart_page_promos($cartTotal) {
    $promotions = get_active_promotions('cart');
    
    if (empty($promotions)) return;
    
    echo '<div class="cart-promos">';
    foreach ($promotions as $promo) {
        echo render_promo_banner($promo, 'cart');
    }
    
    // Free shipping progress
    $freeShipping = check_free_shipping_threshold($cartTotal);
    if ($freeShipping) {
        $percentage = $freeShipping['eligible'] ? 100 : min(($cartTotal / $freeShipping['threshold']) * 100, 100);
        echo '<div class="free-shipping-progress">';
        echo '<div class="shipping-progress-bar">';
        echo '<div class="shipping-progress-fill" style="width: ' . $percentage . '%"></div>';
        echo '</div>';
        echo '<div class="shipping-progress-text">' . htmlspecialchars($freeShipping['message']) . '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Display promotions on checkout page
 */
function display_checkout_page_promos() {
    $promotions = get_active_promotions('checkout');
    
    if (empty($promotions)) return;
    
    echo '<div class="checkout-promos">';
    foreach ($promotions as $promo) {
        echo render_promo_banner($promo, 'checkout');
    }
    echo '</div>';
}

/**
 * Display homepage promotional banners
 */
function display_homepage_banners($position = null) {
    $banners = get_homepage_banners($position);
    
    if (empty($banners)) return;
    
    echo '<div class="homepage-banners">';
    foreach ($banners as $banner) {
        echo render_homepage_banner($banner);
    }
    echo '</div>';
}

/**
 * Display exit intent popup
 */
function display_exit_intent_popup() {
    $popup = get_exit_intent_popup();
    
    if (!$popup) return;
    
    echo render_exit_intent_popup($popup);
}

/**
 * Display signup incentive banner
 */
function display_signup_incentive($page = 'register') {
    $incentive = get_signup_incentives($page);
    
    if (!$incentive) return;
    
    echo render_signup_incentive($incentive);
}

/**
 * Display sticky mobile promo
 */
function display_sticky_mobile_promo() {
    if (!is_mobile_device()) return;
    
    $promotions = get_active_promotions('all');
    $stickyPromo = null;
    
    foreach ($promotions as $promo) {
        if ($promo['show_sticky_mobile']) {
            $stickyPromo = $promo;
            break;
        }
    }
    
    if (!$stickyPromo) return;
    
    echo render_promo_banner($stickyPromo, 'sticky-mobile');
}

/**
 * Check if device is mobile
 */
function is_mobile_device() {
    return preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/**
 * Get promotional meta tags for SEO
 */
function get_promo_meta_tags() {
    $promotions = get_active_promotions('homepage');
    
    if (empty($promotions)) return '';
    
    $firstPromo = $promotions[0];
    $description = htmlspecialchars($firstPromo['promo_message']);
    
    return '<meta name="description" content="' . $description . '">';
}
