<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';

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
$items = cart_items();
$subtotal = cart_subtotal();
$gst = cart_gst();
$total = cart_total_with_gst();

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
            
            $_SESSION['pending_order'] = [
                'order_id' => 'ORD' . time() . rand(1000, 9999),
                'total' => $total,
                'items' => $items,
                'payment_method' => 'upi',
                'address' => $addressData
            ];
            
            error_log("Checkout: Setting pending_order session - Order ID: " . $_SESSION['pending_order']['order_id'] . ", Total: " . $total);
            
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

// Force no cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include __DIR__ . '/includes/new-header.php';
?>

<style>
/* EXACT SAME CSS AS CHECKOUT */
section[data-layout="flipkart-grid"] .checkout-grid {
  display: grid !important;
  grid-template-columns: 1fr 350px !important;
  gap: 20px !important;
  width: 100% !important;
  max-width: none !important;
  margin: 0 !important;
  padding: 0 !important;
  box-sizing: border-box !important;
}

section[data-layout="flipkart-grid"] .checkout-left {
  display: flex !important;
  flex-direction: column !important;
  gap: 12px !important;
  width: 100% !important;
  min-width: 0 !important;
}

section[data-layout="flipkart-grid"] .checkout-right {
  position: sticky !important;
  top: 20px !important;
  align-self: start !important;
  width: 350px !important;
  min-width: 350px !important;
  max-width: 350px !important;
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
    <div class="checkout-grid" style="display: grid !important; grid-template-columns: 780px 350px !important; gap: 20px !important; width: 1160px !important; margin: 0 auto !important;">
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
          <h5 style="margin: 0 0 15px 0; font-size: 16px;">Order Summary</h5>
          
          <?php
          // Calculate pricing breakdown
          $itemsTotal = 0;
          foreach ($items as $item) {
              $itemsTotal += $item['price'] * $item['quantity'];
          }
          
          $deliveryCharge = 0; // Free delivery
          $subtotalInclTax = $itemsTotal + $deliveryCharge;
          
          // Check for applied promotions/discounts (placeholder - implement your discount logic)
          $promotionDiscount = 0;
          $promotionApplied = false;
          // Example: $promotionDiscount = $_SESSION['applied_promo_discount'] ?? 0;
          // if ($promotionDiscount > 0) $promotionApplied = true;
          
          // Check for bank offers (placeholder - implement your bank offer logic)
          $bankOfferDiscount = 0;
          $bankOfferApplied = false;
          // Example: $bankOfferDiscount = $_SESSION['applied_bank_offer'] ?? 0;
          // if ($bankOfferDiscount > 0) $bankOfferApplied = true;
          
          $totalPayable = $subtotalInclTax - $promotionDiscount - $bankOfferDiscount;
          $totalSavings = $promotionDiscount + $bankOfferDiscount;
          ?>
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px;">
            <span>Items:</span>
            <span><?= display_price($itemsTotal, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px;">
            <span>Delivery:</span>
            <span><?= display_price($deliveryCharge, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          
          <hr style="margin: 12px 0; border: none; border-top: 1px solid #e0e0e0;">
          
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; font-weight: 600;">
            <span>Subtotal (Incl. Taxes):</span>
            <span><?= display_price($subtotalInclTax, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          
          <?php if ($promotionApplied): ?>
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #2e7d32;">
            <span>Promotion Applied:</span>
            <span>−<?= display_price($promotionDiscount, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          <?php endif; ?>
          
          <?php if ($bankOfferApplied): ?>
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #2e7d32;">
            <span>Bank Offer:</span>
            <span>−<?= display_price($bankOfferDiscount, $currentCurrency, $currentCurrencySymbol); ?></span>
          </div>
          <?php endif; ?>
          
          <hr style="margin: 12px 0; border: none; border-top: 1px solid #e0e0e0;">
          
          <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; margin-bottom: 15px;">
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
