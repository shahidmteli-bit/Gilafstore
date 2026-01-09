<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/promo_functions.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';

// Get user's region settings for currency conversion
$userRegion = get_user_region_settings();
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if ($action === 'add' && $productId > 0) {
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity > 0) {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId] += $quantity;
            } else {
                $_SESSION['cart'][$productId] = $quantity;
            }
            
            // Track add to cart event for analytics
            if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
                $product = get_product($productId);
                if ($product) {
                    trackProductEvent($productId, 'add_to_cart', 'product_page', $product['category_id'], $product['price'], $quantity);
                }
            }
        }
        header('Location: ' . base_url('cart.php'));
        exit;
    }
    
    if ($action === 'update' && $productId > 0) {
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity > 0) {
            $_SESSION['cart'][$productId] = $quantity;
        }
        header('Location: ' . base_url('cart.php'));
        exit;
    }
    
    if ($action === 'remove' && $productId > 0) {
        cart_remove($productId);
        header('Location: ' . base_url('cart.php'));
        exit;
    }
}

$pageTitle = 'Your Cart — Gilaf Store';
$activePage = 'cart';
$items = cart_items();
$total = cart_total();

include __DIR__ . '/includes/new-header.php';
?>

<!-- Cinematic Cart & Checkout CSS -->
<link rel="stylesheet" href="<?= asset_url('css/cinematic-cart-checkout.css'); ?>">

<style>
/* Modern Shopping Cart Layout */
* {
  box-sizing: border-box;
}

.cart-grid {
  display: grid !important;
  grid-template-columns: 1fr 320px !important;
  gap: 24px !important;
  max-width: 1200px !important;
  margin: 0 auto !important;
  padding: 0 16px !important;
}

.cart-left {
  display: flex !important;
  flex-direction: column !important;
  gap: 16px !important;
}

.cart-right {
  position: sticky !important;
  top: 20px !important;
  align-self: start !important;
}

.cart-item-card {
  background: white;
  border: 1px solid #e3e6e8;
  border-radius: 8px;
  padding: 20px;
  transition: box-shadow 0.2s;
  margin-bottom: 16px;
}

.cart-item-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.cart-item-grid {
  display: grid;
  grid-template-columns: 140px 1fr 160px;
  gap: 24px;
  align-items: center;
}

.product-image-wrapper {
  width: 140px;
  height: 180px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  flex-shrink: 0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.product-image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.product-info-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.product-details-column {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.product-title-cart {
  font-size: 16px;
  font-weight: 700;
  color: #0f1111;
  margin: 0;
  line-height: 1.3;
}

.product-meta {
  font-size: 13px;
  color: #565959;
  margin: 0;
  line-height: 1.5;
}

.stock-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #007600;
}

.delivery-info {
  font-size: 13px;
  color: #0f1111;
  margin: 0;
}

.delivery-info i {
  color: #007185;
  margin-right: 4px;
}

.sku-text {
  font-size: 12px;
  color: #565959;
  margin: 0;
}

.quantity-row {
  display: flex;
  align-items: center;
  margin-top: 8px;
}

.quantity-selector {
  display: inline-flex;
  align-items: center;
  border: 1px solid #d5d9d9;
  border-radius: 4px;
  background: white;
  overflow: hidden;
  height: 32px;
}

.quantity-btn {
  background: transparent;
  border: none;
  padding: 0 12px;
  height: 100%;
  cursor: pointer;
  color: #0f1111;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.quantity-btn:hover {
  background: #f7f8f8;
}

.quantity-input {
  border: none;
  width: 50px;
  text-align: center;
  font-weight: 600;
  font-size: 14px;
  background: white;
  outline: none;
  height: 100%;
  padding: 0;
}

.cart-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.action-link {
  font-size: 13px;
  color: #007185;
  text-decoration: none;
  font-weight: 400;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  line-height: 1;
}

.action-link:hover {
  color: #c7511f;
  text-decoration: underline;
}

.action-separator {
  color: #d5d9d9;
  font-size: 13px;
}

.price-column {
  text-align: right;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  align-items: flex-end;
}

.price-amount {
  font-size: 20px;
  font-weight: 700;
  color: #B12704;
  margin: 0;
}

.price-gst {
  font-size: 11px;
  color: #565959;
  margin: 4px 0 0 0;
}

.order-summary-card {
  background: white;
  border: 1px solid #e3e6e8;
  border-radius: 8px;
  padding: 20px;
}

.summary-title {
  font-size: 18px;
  font-weight: 700;
  color: #0f1111;
  margin: 0 0 16px 0;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.summary-label {
  font-size: 14px;
  color: #565959;
}

.summary-value {
  font-size: 14px;
  font-weight: 600;
  color: #0f1111;
}

.summary-divider {
  border: none;
  border-top: 1px solid #e3e6e8;
  margin: 16px 0;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.total-label {
  font-size: 16px;
  font-weight: 700;
  color: #0f1111;
}

.total-amount {
  font-size: 20px;
  font-weight: 700;
  color: #B12704;
}

.checkout-btn {
  width: 100%;
  background: linear-gradient(to bottom, #f7dfa5, #f0c14b);
  border: 1px solid #a88734;
  border-radius: 8px;
  padding: 12px;
  font-size: 14px;
  font-weight: 700;
  color: #0f1111;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  box-shadow: 0 1px 0 rgba(255,255,255,.4) inset;
  transition: background 0.2s;
}

.checkout-btn:hover {
  background: linear-gradient(to bottom, #f5d78e, #edb933);
}

.secure-badge {
  background: #f7f8f8;
  border: 1px solid #d5d9d9;
  border-radius: 8px;
  padding: 12px;
  margin-top: 12px;
  text-align: center;
}

.secure-text {
  font-size: 12px;
  color: #565959;
  margin: 0;
}

.secure-text i {
  color: #067d62;
  margin-right: 6px;
}

@media (max-width: 992px) {
  .cart-item-grid {
    grid-template-columns: 120px 1fr 140px;
    gap: 20px;
  }
  
  .product-image-wrapper {
    width: 120px;
    height: 150px;
    padding: 4px;
  }
  
  .product-title-cart {
    font-size: 15px;
  }
  
  .product-meta {
    font-size: 12px;
  }
}

@media (max-width: 768px) {
  .cart-grid {
    grid-template-columns: 1fr !important;
  }
  
  .cart-right {
    position: static !important;
  }
  
  .cart-item-grid {
    grid-template-columns: 100px 1fr;
    gap: 16px;
    align-items: start;
  }
  
  .product-image-wrapper {
    width: 100px;
    height: 130px;
    padding: 4px;
  }
  
  .price-column {
    grid-column: 1 / -1;
    text-align: left;
    align-items: flex-start;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e3e6e8;
  }
  
  .price-amount {
    font-size: 18px;
  }
}

@media (max-width: 480px) {
  .cart-item-card {
    padding: 16px;
  }
  
  .cart-item-grid {
    grid-template-columns: 80px 1fr;
    gap: 12px;
  }
  
  .product-image-wrapper {
    width: 80px;
    height: 100px;
    padding: 3px;
  }
  
  .product-title-cart {
    font-size: 14px;
  }
  
  .product-meta {
    font-size: 11px;
  }
  
  .delivery-info {
    font-size: 11px;
  }
  
  .quantity-selector {
    height: 28px;
  }
  
  .quantity-btn {
    padding: 0 10px;
  }
  
  .quantity-input {
    width: 35px;
    font-size: 13px;
  }
  
  .price-amount {
    font-size: 16px;
  }
}
</style>

<section class="py-5" style="background: #f1f3f6; padding-top: 60px !important; padding-bottom: 30px !important;">
  <div class="container">
    <h2 class="section-title mb-4 text-center">Shopping Cart</h2>
    <?php if ($items): ?>
      <div class="cart-grid">
        <div class="cart-left">
          <?php foreach ($items as $item): ?>
            <div class="cart-item-card">
              <div class="cart-item-grid">
                <!-- Product Image -->
                <div class="product-image-wrapper">
                  <img src="<?= asset_url('images/products/' . htmlspecialchars($item['image'])); ?>" 
                       alt="<?= htmlspecialchars($item['name']); ?>" />
                </div>
                
                <!-- Product Details + Quantity -->
                <div class="product-info-wrapper">
                  <div class="product-details-column">
                    <h3 class="product-title-cart">
                      <?= htmlspecialchars($item['name']); ?>
                    </h3>
                    <p class="product-meta">
                      Net Weight: <?= htmlspecialchars($item['weight']); ?>
                    </p>
                    <p class="product-meta">
                      Batch Code: <?= htmlspecialchars($item['batch_code']); ?>
                    </p>
                    
                    <div class="stock-badge">
                      <?php if ($item['stock_quantity'] > 0): ?>
                        <i class="fas fa-circle" style="font-size: 6px;"></i> IN STOCK
                      <?php else: ?>
                        <i class="fas fa-circle" style="font-size: 6px; color: #dc3545;"></i> OUT OF STOCK
                      <?php endif; ?>
                    </div>
                    
                    <p class="delivery-info">
                      <i class="fas fa-truck"></i><strong>Expected Delivery: <?= date('D M j', strtotime('+5 days')); ?></strong>
                    </p>
                  </div>
                  
                  <!-- Quantity Controls Below Product Info -->
                  <div class="quantity-row">
                    <form action="<?= base_url('cart.php'); ?>" method="post" style="margin: 0;" id="qty-form-<?= (int)$item['product_id']; ?>">
                      <input type="hidden" name="action" value="update" id="action-<?= (int)$item['product_id']; ?>" />
                      <input type="hidden" name="product_id" value="<?= (int)$item['product_id']; ?>" />
                      <div class="quantity-selector">
                        <?php if ($item['quantity'] == 1): ?>
                          <button type="button" class="quantity-btn" onclick="if(confirm('Remove this item from cart?')) { document.getElementById('action-<?= (int)$item['product_id']; ?>').value = 'remove'; document.getElementById('qty-form-<?= (int)$item['product_id']; ?>').submit(); }">
                            <i class="fas fa-minus"></i>
                          </button>
                        <?php else: ?>
                          <button type="button" class="quantity-btn" onclick="let form = document.getElementById('qty-form-<?= (int)$item['product_id']; ?>'); let input = form.querySelector('.quantity-input'); input.value = parseInt(input.value) - 1; form.submit();">
                            <i class="fas fa-minus"></i>
                          </button>
                        <?php endif; ?>
                        <input type="number" class="quantity-input" name="quantity" value="<?= (int)$item['quantity']; ?>" min="1" readonly />
                        <button type="button" class="quantity-btn" onclick="let form = document.getElementById('qty-form-<?= (int)$item['product_id']; ?>'); let input = form.querySelector('.quantity-input'); input.value = parseInt(input.value) + 1; form.submit();">
                          <i class="fas fa-plus"></i>
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
                
                <!-- Price Column -->
                <div class="price-column">
                  <?php 
                    $itemTotal = convert_currency($item['price'] * $item['quantity'], $currentCurrency);
                    $itemPrice = convert_currency($item['price'], $currentCurrency);
                  ?>
                  <p class="price-amount"><?= display_price($item['price'] * $item['quantity'], $currentCurrency, $currentCurrencySymbol); ?></p>
                  <p class="price-gst">(<?= display_price($item['price'], $currentCurrency, $currentCurrencySymbol); ?> incl. GST)</p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <div class="cart-right">
          <div class="order-summary-card">
            <h2 class="summary-title">Order Summary</h2>
            
            <?php
            // Get GST rate and promotional discount from settings
            $gstRate = get_gst_rate();
            $promotionalDiscount = get_promotional_discount();
            
            // Calculate tax breakdown
            $gstMultiplier = 1 + ($gstRate / 100);
            $itemPriceExclTax = $total / $gstMultiplier;
            $gstAmount = $total - $itemPriceExclTax;
            
            // Calculate savings
            $savings = $itemPriceExclTax * ($promotionalDiscount / 100);
            ?>
            
            <div class="summary-row" style="cursor: pointer; user-select: none;" onclick="toggleTaxBreakdown()">
              <span class="summary-label">Subtotal (Incl. Taxes)</span>
              <span class="summary-value">
                <?= display_price($total, $currentCurrency, $currentCurrencySymbol); ?>
                <i class="fas fa-chevron-down" id="tax-chevron" style="margin-left: 8px; font-size: 12px; transition: transform 0.3s;"></i>
              </span>
            </div>
            
            <div id="tax-breakdown" style="display: none; margin-left: 20px; margin-top: 8px; margin-bottom: 8px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; color: #565959;">Item Price</span>
                <span style="font-size: 13px; color: #565959;"><?= display_price($itemPriceExclTax, $currentCurrency, $currentCurrencySymbol); ?></span>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span style="font-size: 13px; color: #565959;">GST (<?= number_format($gstRate, 2) ?>%)</span>
                <span style="font-size: 13px; color: #565959;"><?= display_price($gstAmount, $currentCurrency, $currentCurrencySymbol); ?></span>
              </div>
            </div>
            
            <div class="summary-row">
              <span class="summary-label">Shipping</span>
              <span class="summary-value" style="color: #067d62; font-weight: 600;">Free</span>
            </div>
            
            <?php if ($savings > 0): ?>
            <div style="margin: 12px 0; padding: 10px 12px; background: rgba(34, 197, 94, 0.12); border-radius: 6px; text-align: center; border: 1px solid rgba(34, 197, 94, 0.2);">
              <p style="color: #16a34a; font-weight: 600; margin: 0; font-size: 0.85rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;">
                You will save <?= display_price($savings, $currentCurrency, $currentCurrencySymbol); ?>
              </p>
            </div>
            <?php endif; ?>
            
            <!-- Promo Code Section -->
            <?php 
            $appliedPromo = get_applied_promo_code();
            $promoDiscount = 0;
            if ($appliedPromo) {
                $promoDiscount = recalculate_promo_discount($total);
            }
            ?>
            
            <div style="margin: 16px 0;">
              <?php if ($appliedPromo && $promoDiscount > 0): ?>
                <!-- Applied Promo Code -->
                <div style="padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin-bottom: 12px;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <i class="fas fa-ticket-alt" style="color: white;"></i>
                        <code style="color: white; font-weight: 700; font-size: 14px; letter-spacing: 1px;"><?= htmlspecialchars($appliedPromo['code']); ?></code>
                      </div>
                      <small style="color: rgba(255,255,255,0.9); font-size: 12px;">Promo code applied</small>
                    </div>
                    <button onclick="removePromoCode()" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                      <i class="fas fa-times"></i> Remove
                    </button>
                  </div>
                </div>
                
                <div class="summary-row" style="color: #067d62;">
                  <span class="summary-label">Promo Discount</span>
                  <span class="summary-value" style="font-weight: 700;">-<?= display_price($promoDiscount, $currentCurrency, $currentCurrencySymbol); ?></span>
                </div>
              <?php else: ?>
                <!-- Promo Code Input -->
                <div style="margin-bottom: 12px;">
                  <button onclick="togglePromoInput()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <i class="fas fa-tag"></i>
                    <span>Have a Promo Code?</span>
                  </button>
                </div>
                
                <div id="promoInputSection" style="display: none; margin-bottom: 12px;">
                  <div style="display: flex; gap: 8px;">
                    <input type="text" id="promoCodeInput" placeholder="Enter promo code" style="flex: 1; padding: 10px 12px; border: 2px solid #e3e6e8; border-radius: 6px; font-size: 14px; text-transform: uppercase; font-family: monospace; letter-spacing: 1px;" maxlength="50">
                    <button onclick="applyPromoCode()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; white-space: nowrap; transition: all 0.3s;" onmouseover="this.style.background='#5568d3'" onmouseout="this.style.background='#667eea'">
                      Apply
                    </button>
                  </div>
                  <div id="promoMessage" style="margin-top: 8px; font-size: 13px; display: none;"></div>
                </div>
              <?php endif; ?>
            </div>
            
            <hr style="margin: 16px 0; border: 0; border-top: 1px solid #e3e6e8;">
            
            <div class="summary-row" style="margin-bottom: 0;">
              <span style="font-size: 18px; font-weight: 700; color: #0f1111;">Total</span>
              <span style="font-size: 18px; font-weight: 700; color: #B12704;" id="finalTotal"><?= display_price($total - $promoDiscount, $currentCurrency, $currentCurrencySymbol); ?></span>
            </div>
            
            <a href="<?= base_url('checkout.php'); ?>" class="checkout-btn">
              <i class="fas fa-shopping-cart" style="margin-right: 8px;"></i>CHECKOUT
            </a>
          </div>
          
          <div class="secure-badge">
            <p class="secure-text">
              <i class="fas fa-shield-alt"></i><strong>Secure checkout</strong> backed by SSL encryption
            </p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card shadow-3 border-0">
        <div class="card-body text-center py-5">
          <div class="display-4 text-primary mb-3"><i class="fas fa-cart-arrow-down"></i></div>
          <h4 class="fw-semibold">Your cart is empty</h4>
          <p class="text-muted">Discover trending products and add them to your cart for a seamless checkout experience.</p>
          <a href="<?= base_url('shop.php'); ?>" class="btn btn-primary rounded-pill mt-3">Browse shop</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
// Toggle promo code input section
function togglePromoInput() {
  const section = document.getElementById('promoInputSection');
  if (section.style.display === 'none') {
    section.style.display = 'block';
    document.getElementById('promoCodeInput').focus();
  } else {
    section.style.display = 'none';
  }
}

// Apply promo code
async function applyPromoCode() {
  const input = document.getElementById('promoCodeInput');
  const code = input.value.trim().toUpperCase();
  const messageDiv = document.getElementById('promoMessage');
  
  if (!code) {
    showPromoMessage('Please enter a promo code', 'error');
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'apply');
    formData.append('code', code);
    
    const response = await fetch('<?= base_url('apply_promo.php'); ?>', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      showPromoMessage(result.message, 'success');
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      showPromoMessage(result.message, 'error');
    }
  } catch (error) {
    showPromoMessage('Error applying promo code. Please try again.', 'error');
  }
}

// Remove promo code
async function removePromoCode() {
  if (!confirm('Remove promo code?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'remove');
    
    const response = await fetch('<?= base_url('apply_promo.php'); ?>', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      location.reload();
    } else {
      alert('Error removing promo code');
    }
  } catch (error) {
    alert('Error removing promo code. Please try again.');
  }
}

// Show promo message
function showPromoMessage(message, type) {
  const messageDiv = document.getElementById('promoMessage');
  messageDiv.textContent = message;
  messageDiv.style.display = 'block';
  messageDiv.style.padding = '8px 12px';
  messageDiv.style.borderRadius = '6px';
  messageDiv.style.fontWeight = '600';
  
  if (type === 'success') {
    messageDiv.style.background = 'rgba(34, 197, 94, 0.12)';
    messageDiv.style.color = '#16a34a';
    messageDiv.style.border = '1px solid rgba(34, 197, 94, 0.2)';
  } else {
    messageDiv.style.background = 'rgba(239, 68, 68, 0.12)';
    messageDiv.style.color = '#dc2626';
    messageDiv.style.border = '1px solid rgba(239, 68, 68, 0.2)';
  }
}

// Allow Enter key to apply promo code
document.addEventListener('DOMContentLoaded', function() {
  const promoInput = document.getElementById('promoCodeInput');
  if (promoInput) {
    promoInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyPromoCode();
      }
    });
  }
});
</script>

<?php
include __DIR__ . '/includes/new-footer.php';
?>

<script>
function shareProduct(productName, productId) {
  const url = '<?= base_url("product.php?id="); ?>' + productId;
  const text = 'Check out ' + productName + ' on Gilaf Store!';
  
  // Check if Web Share API is available
  if (navigator.share) {
    navigator.share({
      title: productName,
      text: text,
      url: url
    }).then(() => {
      console.log('Shared successfully');
    }).catch((error) => {
      console.log('Error sharing:', error);
      fallbackShare(url, text);
    });
  } else {
    fallbackShare(url, text);
  }
}

function fallbackShare(url, text) {
  // Fallback: Copy to clipboard
  const tempInput = document.createElement('input');
  tempInput.value = url;
  document.body.appendChild(tempInput);
  tempInput.select();
  document.execCommand('copy');
  document.body.removeChild(tempInput);
  
  alert('Product link copied to clipboard!\n\n' + text + '\n' + url);
}

function toggleTaxBreakdown() {
  const breakdown = document.getElementById('tax-breakdown');
  const chevron = document.getElementById('tax-chevron');
  
  if (breakdown.style.display === 'none') {
    breakdown.style.display = 'block';
    chevron.style.transform = 'rotate(180deg)';
  } else {
    breakdown.style.display = 'none';
    chevron.style.transform = 'rotate(0deg)';
  }
}
</script>
