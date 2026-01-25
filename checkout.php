<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';
require_once __DIR__ . '/includes/promo_functions.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    $_SESSION['checkout_redirect'] = true;
    redirect_with_message(base_url('user/login.php?redirect=checkout'), 'Please login to continue with checkout', 'info');
}

// Get user's region settings for currency conversion
$userRegion = get_user_region_settings();
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

$pageTitle = 'Checkout — Gilaf Store';
$activePage = '';

// Check if this is a Buy Now flow (single product quick checkout)
$isBuyNow = isset($_GET['buy_now']) && isset($_SESSION['buy_now']);

if ($isBuyNow) {
    // Buy Now: Use single product from buy_now session
    $buyNowItem = $_SESSION['buy_now'];
    $items = [[
        'product_id' => $buyNowItem['product_id'],
        'weight_id' => $buyNowItem['weight_id'],
        'quantity' => $buyNowItem['quantity'],
        'price' => $buyNowItem['price'],
        'name' => $buyNowItem['name'],
        'image' => $buyNowItem['image'],
        'weight_name' => $buyNowItem['weight_name']
    ]];
    $subtotal = $buyNowItem['price'] * $buyNowItem['quantity'];
    $gst = 0; // Price already includes GST
    $total = $subtotal;
} else {
    // Regular cart checkout
    $items = cart_items();
    $subtotal = cart_subtotal();
    $gst = cart_gst();
    $total = cart_total(); // Use cart_total() - prices already include GST
}

// Get dynamic shipping fee from admin settings
$shippingSettings = get_shipping_settings('domestic');
$baseDeliveryFee = (float)($shippingSettings['base_charge'] ?? 50.00);
$freeShippingThreshold = (float)($shippingSettings['free_shipping_threshold'] ?? 500.00);

if (!$items) {
    redirect_with_message('/cart.php', 'Your cart is empty', 'info');
}

// Fetch user's saved addresses
$userId = (int)$_SESSION['user']['id'];
$savedAddresses = db_fetch_all('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC', [$userId]);

$errors = [];
$paymentMethod = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    if (!in_array($paymentMethod, ['card', 'cod', 'upi'], true)) {
        $errors['payment_method'] = 'Select a payment method';
    }
    
    if (!$errors) {
        if ($paymentMethod === 'upi') {
            // Get selected address
            $selectedAddressId = $_POST['address_id'] ?? null;
            $addressData = [];
            
            if ($selectedAddressId) {
                $address = db_fetch_one('SELECT * FROM user_addresses WHERE id = ? AND user_id = ?', [$selectedAddressId, $userId]);
                if ($address) {
                    $addressData = $address;
                }
            }
            
            // Calculate final total with promo discount
            $appliedPromoForOrder = get_applied_promo_code();
            $promoDiscountForOrder = 0;
            $promoCodeForOrder = '';
            if ($appliedPromoForOrder) {
                $promoDiscountForOrder = recalculate_promo_discount($total);
                $promoCodeForOrder = $appliedPromoForOrder['code'];
            }
            $finalTotal = $total - $promoDiscountForOrder;
            
            $_SESSION['pending_order'] = [
                'order_id' => 'ORD' . time() . rand(1000, 9999),
                'total' => $finalTotal,
                'subtotal' => $total,
                'promo_discount' => $promoDiscountForOrder,
                'promo_code' => $promoCodeForOrder,
                'items' => $items,
                'payment_method' => 'upi',
                'address' => $addressData,
                'is_buy_now' => $isBuyNow
            ];
            
            error_log("Checkout: Setting pending_order session - Order ID: " . $_SESSION['pending_order']['order_id'] . ", Total: " . $finalTotal . ", Promo: " . $promoCodeForOrder . " (-" . $promoDiscountForOrder . ")");
            
            header('Location: ' . base_url('upi_payment.php'));
            exit;
        }
        
        try {
            $orderId = place_order((int)$_SESSION['user']['id'], $items);
            $_SESSION['order_confirmation'] = [
                'order_id' => $orderId,
                'total' => $total,
                'payment_method' => $paymentMethod,
            ];
            redirect_with_message('/thank-you.php', 'Order placed successfully!');
        } catch (Exception $exception) {
            $errors['general'] = 'Unable to process order at the moment. Please try again.';
        }
    }
}

// Force no cache - AGGRESSIVE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

include __DIR__ . '/includes/new-header.php';
?>

<script src="<?= base_url('assets/js/checkout-mobile.js'); ?>?v=<?= time(); ?>"></script>

<style id="checkout-styles-v<?= time(); ?>">
/* Checkout Page Styles - Version <?= time(); ?> */
.checkout-grid {
  display: grid;
  grid-template-columns: 1fr 350px;
  gap: 20px;
}

@media (max-width: 1023px) {
  .checkout-grid {
    grid-template-columns: 1fr !important;
    width: 100% !important;
  }
  .checkout-right {
    order: -1;
  }
}

@media (max-width: 820px) {
  section[data-layout="flipkart-grid"] {
    padding: clamp(12px, 3vw, 15px) 0 !important;
  }
  
  section[data-layout="flipkart-grid"] > div {
    max-width: 100% !important;
    padding: 0 clamp(8px, 2.5vw, 10px) !important;
  }
  
  .checkout-grid {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    gap: clamp(12px, 3.5vw, 15px) !important;
    margin: 0 !important;
  }
  
  .checkout-left,
  .checkout-right {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
  }
  
  /* Make address and payment sections more visible */
  .checkout-left > div {
    border: 2px solid #2874f0 !important;
    box-shadow: 0 2px 8px rgba(40, 116, 240, 0.1) !important;
    margin-bottom: clamp(12px, 3.5vw, 15px) !important;
    padding: clamp(15px, 4vw, 20px) !important;
  }
  
  .checkout-left label {
    font-size: clamp(13px, 3.5vw, 14px) !important;
    padding: clamp(10px, 3vw, 12px) clamp(8px, 2.5vw, 10px) !important;
    display: flex !important;
    align-items: center !important;
  }
  
  .checkout-left input[type="radio"] {
    width: clamp(16px, 4.5vw, 18px) !important;
    height: clamp(16px, 4.5vw, 18px) !important;
    min-width: clamp(16px, 4.5vw, 18px) !important;
    margin-right: clamp(8px, 2.5vw, 10px) !important;
    flex-shrink: 0 !important;
  }
  
  /* Professional button styling with fluid scaling */
  .checkout-left button,
  .checkout-left a {
    padding: clamp(6px, 2.5vw, 10px) clamp(10px, 4vw, 16px) !important;
    font-size: clamp(10px, 3.5vw, 12px) !important;
    font-weight: 600 !important;
    border-radius: clamp(3px, 1vw, 4px) !important;
    flex: 1 !important;
    text-align: center !important;
    min-height: clamp(38px, 10vw, 40px) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }
  
  /* Hide Pay button initially, show only after payment method selected */
  .checkout-right {
    display: none;
    order: 2;
  }
  
  .checkout-right.show-payment {
    display: block !important;
  }
  
  .checkout-right > div {
    width: 100% !important;
    padding: clamp(8px, 2.5vw, 18px) !important;
    box-sizing: border-box !important;
  }
  
  .checkout-right h5 {
    font-size: clamp(13px, 4vw, 18px) !important;
    margin-bottom: clamp(8px, 2.5vw, 15px) !important;
    font-weight: 700 !important;
    color: #1a1a1a !important;
    margin-top: 0 !important;
  }
  
  .checkout-right > div > div {
    font-size: clamp(14px, 3.8vw, 15px) !important;
    margin-bottom: clamp(10px, 3vw, 12px) !important;
    line-height: 1.5 !important;
  }
  
  .checkout-right > div > div span {
    font-weight: 500 !important;
    color: #333 !important;
  }
  
  /* Order summary rows with clamp() - aggressive minimums for Galaxy S9+ */
  .checkout-right .order-row,
  .checkout-right .order-row span,
  .checkout-right div[class*="order-row"] {
    font-size: clamp(10px, 3vw, 14px) !important;
    white-space: nowrap !important;
    overflow: visible !important;
  }
  
  .checkout-right .order-row span {
    flex-shrink: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  
  
  .checkout-right .order-total,
  .checkout-right .order-total span,
  .checkout-right div[class*="order-total"] {
    font-size: clamp(12px, 4.5vw, 18px) !important;
  }
  
  .checkout-right h5 {
    font-size: clamp(12px, 4.5vw, 18px) !important;
  }
  
  /* Force all text in order summary to use clamp */
  .checkout-right > div > div,
  .checkout-right > div > div > span {
    font-size: clamp(11px, 3.2vw, 14px) !important;
  }
  
  /* Ensure proper spacing for price columns */
  .checkout-right > div > div[style*="display: flex"] {
    gap: 2px !important;
    justify-content: space-between !important;
    padding: 0 !important;
  }
  
  .checkout-right > div > div[style*="margin-bottom"] {
    margin-bottom: 6px !important;
  }
  
  .checkout-right button[type="submit"] {
    position: sticky !important;
    bottom: 10px !important;
    z-index: 100 !important;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.15) !important;
    font-size: clamp(15px, 4vw, 16px) !important;
    padding: clamp(12px, 3.5vw, 14px) !important;
    width: 100% !important;
    min-height: clamp(48px, 12vw, 50px) !important;
    border-radius: clamp(4px, 1.5vw, 6px) !important;
  }
}


section[data-layout="flipkart-grid"] .checkout-right {
  position: sticky !important;
  top: 20px !important;
  align-self: start !important;
  width: 350px !important;
  min-width: 350px !important;
  max-width: 350px !important;
}

/* Extra small screens - Galaxy S9+ at 320px */
@media (max-width: 340px) {
  .checkout-right > div {
    padding: 5px !important;
  }
  
  .checkout-right h5 {
    font-size: 10px !important;
    margin-bottom: 6px !important;
  }
  
  /* Target ALL flex divs in order summary */
  .checkout-right > div > div[style*="display: flex"],
  .checkout-right .order-row,
  .checkout-right div[class*="order"] {
    font-size: 8px !important;
    gap: 1px !important;
  }
  
  /* Force label width limit */
  .checkout-right > div > div[style*="display: flex"] span:first-child,
  .checkout-right .order-row span:first-child {
    max-width: 50% !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    display: inline-block !important;
  }
  
  /* Force price visibility */
  .checkout-right > div > div[style*="display: flex"] span:last-child,
  .checkout-right .order-row span:last-child {
    min-width: 45% !important;
    max-width: 45% !important;
    text-align: right !important;
    flex-shrink: 0 !important;
    display: inline-block !important;
  }
  
  .checkout-right .order-total {
    font-size: 10px !important;
  }
}
</style>

<section data-layout="flipkart-grid" style="background: #f1f3f6; padding: 20px 0;">
  <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
    <h2 style="text-align: center; margin-bottom: 10px;">Checkout</h2>
    <p style="text-align: center; color: #666; margin-bottom: 30px;">Complete your order with secure billing and shipping details.</p>
    
    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>
    
    <form id="checkoutForm" method="post">
    <div class="checkout-grid">
      <div class="checkout-left">
        <!-- DELIVERY ADDRESS SECTION -->
        <div style="background: white; padding: 20px; border-radius: 4px; margin-bottom: 12px;">
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
            <span style="background: #2874f0; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600;">2</span>
            <h5 style="margin: 0; font-size: 16px;">DELIVERY ADDRESS</h5>
          </div>
          
          <?php if ($savedAddresses): ?>
            <?php foreach (array_slice($savedAddresses, 0, 1) as $addr): ?>
              <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 12px;">
                <div style="margin-bottom: 8px;">
                  <strong><?= htmlspecialchars($_SESSION['user']['name']); ?></strong>
                  <span style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 8px;"><?= htmlspecialchars(strtoupper($addr['type'])); ?></span>
                  <span style="color: #666; margin-left: 8px;"><?= htmlspecialchars($addr['phone'] ?? ''); ?></span>
                </div>
                <p style="margin: 0; color: #666; font-size: 14px;">
                  <?= htmlspecialchars($addr['address_line1']); ?><?= $addr['address_line2'] ? ', ' . htmlspecialchars($addr['address_line2']) : ''; ?>, 
                  <?= htmlspecialchars($addr['city']); ?>, <?= htmlspecialchars($addr['state']); ?> - <?= htmlspecialchars($addr['zip_code']); ?>
                </p>
              </div>
            <?php endforeach; ?>
            
            <div style="display: flex; gap: 12px; margin-top: 15px;">
              <button type="button" style="background: #ff9800; color: white; border: none; padding: 10px 20px; border-radius: 3px; font-weight: 600; font-size: 13px; cursor: pointer;">DELIVER HERE</button>
              <a href="<?= base_url('user/manage_addresses.php?from=checkout'); ?>" style="background: white; color: #2874f0; border: 1px solid #2874f0; padding: 10px 20px; border-radius: 3px; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block;">+ ADD NEW ADDRESS</a>
            </div>
          <?php else: ?>
            <p>No saved addresses. <a href="<?= base_url('user/manage_addresses.php?from=checkout'); ?>">Add an address</a></p>
          <?php endif; ?>
        </div>
        
        <!-- PAYMENT METHOD SECTION -->
        <div style="background: white; padding: 20px; border-radius: 4px;">
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
            <span style="background: #2874f0; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600;">3</span>
            <h5 style="margin: 0; font-size: 16px;">PAYMENT METHOD</h5>
          </div>
          
          <div style="margin-bottom: 12px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
              <input type="radio" name="payment_method" value="card" <?= $paymentMethod === 'card' ? 'checked' : ''; ?> required style="margin-right: 10px;">
              <i class="fas fa-credit-card" style="margin-right: 8px;"></i> Credit / Debit Card
            </label>
          </div>
          
          <div style="margin-bottom: 12px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
              <input type="radio" name="payment_method" value="upi" <?= $paymentMethod === 'upi' ? 'checked' : ''; ?> style="margin-right: 10px;">
              <i class="fas fa-mobile-alt" style="margin-right: 8px;"></i> UPI (Pay using any UPI app)
            </label>
          </div>
          
          <div style="padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
              <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : ''; ?> style="margin-right: 10px;">
              <i class="fas fa-money-bill-wave" style="margin-right: 8px;"></i> Cash on Delivery
            </label>
          </div>
          
          <?php if (!empty($errors['payment_method'])): ?>
            <div style="color: #dc3545; margin-top: 10px; font-size: 14px;"><?= htmlspecialchars($errors['payment_method']); ?></div>
          <?php endif; ?>
          
          <p style="color: #2e7d32; margin-top: 15px; font-size: 13px;">
            <i class="fas fa-lock"></i> Secure checkout - All payments are SSL-encrypted
          </p>
        </div>
      </div>
      
      <div class="checkout-right">
        <!-- ORDER SUMMARY -->
        <div style="background: white; padding: 20px; border-radius: 4px;">
          <h5 style="margin: 0 0 15px 0;">Order Summary</h5>
          
          <?php
          // Calculate pricing breakdown
          $itemsTotal = 0;
          foreach ($items as $item) {
              $itemsTotal += $item['price'] * $item['quantity'];
          }
          
          // Dynamic delivery charge from admin settings (free if above threshold)
          $deliveryCharge = ($itemsTotal >= $freeShippingThreshold) ? 0 : $baseDeliveryFee;
          $subtotalInclTax = $itemsTotal + $deliveryCharge;
          
          // Check for applied promo code from cart
          $appliedPromo = get_applied_promo_code();
          $promotionDiscount = 0;
          $promotionApplied = false;
          $promoCode = '';
          if ($appliedPromo) {
              $promotionDiscount = recalculate_promo_discount($itemsTotal);
              if ($promotionDiscount > 0) {
                  $promotionApplied = true;
                  $promoCode = $appliedPromo['code'];
              }
          }
          
          // Check for bank offers (placeholder - implement your bank offer logic)
          $bankOfferDiscount = 0;
          $bankOfferApplied = false;
          // Example: $bankOfferDiscount = $_SESSION['applied_bank_offer'] ?? 0;
          // if ($bankOfferDiscount > 0) $bankOfferApplied = true;
          
          // Calculate product discount savings (same as cart page)
          $gstRate = get_gst_rate();
          $promotionalDiscountPercent = get_promotional_discount();
          $gstMultiplier = 1 + ($gstRate / 100);
          $itemPriceExclTax = $itemsTotal / $gstMultiplier;
          $productSavings = $itemPriceExclTax * ($promotionalDiscountPercent / 100);
          
          $totalPayable = $subtotalInclTax - $promotionDiscount - $bankOfferDiscount;
          $totalSavings = $productSavings + $promotionDiscount + $bankOfferDiscount;
          ?>
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px;" class="order-row">
            <span>Items:</span>
            <span><?= display_price($itemsTotal, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px;" class="order-row">
            <span>Delivery:</span>
            <?php if ($deliveryCharge > 0): ?>
              <span><?= display_price($deliveryCharge, $currentCurrency, $currentCurrencySymbol); ?></span>
            <?php else: ?>
              <span style="color: #2e7d32; font-weight: 600;">Free</span>
            <?php endif; ?>
          </div>
          
          <hr style="margin: 12px 0; border: none; border-top: 1px solid #e0e0e0;">
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 600;" class="order-row">
            <span>Subtotal (Incl. Taxes):</span>
            <span><?= display_price($subtotalInclTax, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          
          <?php if ($promotionApplied): ?>
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #2e7d32;" class="order-row">
            <span>Promo (<?= htmlspecialchars($promoCode); ?>):</span>
            <span>−<?= display_price($promotionDiscount, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          <?php endif; ?>
          
          <?php if ($bankOfferApplied): ?>
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #2e7d32;" class="order-row">
            <span>Bank Offer:</span>
            <span>−<?= display_price($bankOfferDiscount, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          <?php endif; ?>
          
          <hr style="margin: 12px 0; border: none; border-top: 1px solid #e0e0e0;">
          
          <div style="display: flex; justify-content: space-between; font-weight: 700; margin-bottom: 15px;" class="order-total">
            <span>Total Payable:</span>
            <span style="color: #2874f0;"><?= display_price($totalPayable, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          
          <?php if ($totalSavings > 0): ?>
          <div style="background: #e8f5e9; padding: 12px; border-radius: 4px; text-align: center;">
            <span style="color: #2e7d32; font-weight: 600; font-size: 14px;">
              Your Total Savings on this order: <?= display_price($totalSavings, $currentCurrency, $currentCurrencySymbol); ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
        
        <button type="submit" style="background: #ffb800; color: #000; border: none; padding: 14px; border-radius: 4px; font-weight: 600; font-size: 16px; width: 100%; margin-top: 12px; cursor: pointer;">
          Pay <?= display_price($totalPayable, $currentCurrency, $currentCurrencySymbol); ?>
        </button>
      </div>
    </div>
    </form>
  </div>
</section>

<?php
include __DIR__ . '/includes/new-footer.php';
?>
