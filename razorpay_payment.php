<?php
/**
 * Razorpay Payment Page
 * Creates a Razorpay order and opens the Razorpay Checkout modal
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/company_profile_functions.php';

// Allow guests with a pending order to view the payment page (account created AFTER payment)
if (!isset($_SESSION['user']) && empty($_SESSION['pending_order']['guest_info'])) {
    redirect_with_message(base_url('user/login.php'), 'Please login to continue', 'info');
}

// Check if order details are in session
if (!isset($_SESSION['pending_order'])) {
    redirect_with_message(base_url('checkout.php'), 'No pending order found', 'error');
}

// Check Razorpay is enabled
if (get_setting('razorpay_enabled', '0') !== '1') {
    redirect_with_message(base_url('checkout.php'), 'Online payment is not available right now', 'error');
}

$order = $_SESSION['pending_order'];
$orderAmount = (float)$order['total'];
$orderId = $order['order_id'] ?? 'ORD' . time();
$items = $order['items'] ?? [];
$subtotal = $order['subtotal'] ?? $orderAmount;
$promoDiscount = $order['promo_discount'] ?? 0;
$promoCode = $order['promo_code'] ?? '';

$razorpayKeyId = get_setting('razorpay_key_id', '');
$businessName = 'Gilaf Store';

// Get user details (logged-in user or guest info from pending order)
if (isset($_SESSION['user'])) {
    $user = get_user((int)$_SESSION['user']['id']);
} else {
    $guestInfo = $order['guest_info'] ?? [];
    $user = [
        'name'  => $guestInfo['name'] ?? 'Guest',
        'email' => $guestInfo['email'] ?? '',
        'phone' => $guestInfo['phone'] ?? '',
    ];
}
$address = $order['address'] ?? [];

$pageTitle = 'Payment — Gilaf Store';
include __DIR__ . '/includes/new-header.php';
?>

<style>
.rzp-page { max-width: 600px; margin: 30px auto; padding: 0 15px; }
.rzp-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
.rzp-header { background: linear-gradient(135deg, #1a237e, #283593); color: #fff; padding: 24px; text-align: center; }
.rzp-header h2 { margin: 0 0 5px; font-size: 20px; }
.rzp-header .rzp-amount { font-size: 32px; font-weight: 700; }
.rzp-header .rzp-order-id { font-size: 12px; opacity: .7; margin-top: 5px; }
.rzp-body { padding: 24px; }
.rzp-summary { margin-bottom: 20px; }
.rzp-summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; color: #374151; }
.rzp-summary-row.rzp-total { font-weight: 700; font-size: 16px; border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 4px; color: #111; }
.rzp-summary-row.rzp-discount { color: #059669; }
.rzp-items { margin-bottom: 20px; }
.rzp-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
.rzp-item:last-child { border-bottom: none; }
.rzp-item img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; }
.rzp-item-info { flex: 1; }
.rzp-item-name { font-size: 14px; font-weight: 500; }
.rzp-item-qty { font-size: 12px; color: #6b7280; }
.rzp-item-price { font-weight: 600; font-size: 14px; }
.rzp-pay-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #1a237e, #283593); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .2s; }
.rzp-pay-btn:hover { background: linear-gradient(135deg, #0d1642, #1a237e); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,35,126,.3); }
.rzp-pay-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
.rzp-pay-btn i { font-size: 18px; }
.rzp-secure { text-align: center; margin-top: 16px; font-size: 12px; color: #6b7280; }
.rzp-secure i { color: #059669; }
.rzp-back { display: block; text-align: center; margin-top: 12px; color: #6b7280; font-size: 13px; text-decoration: none; }
.rzp-back:hover { color: #374151; }
.rzp-status { display: none; text-align: center; padding: 20px; }
.rzp-status.rzp-success { display: block; }
.rzp-status.rzp-error { display: block; }
.rzp-spinner { display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: rzpSpin .8s linear infinite; }
@keyframes rzpSpin { to { transform: rotate(360deg); } }
</style>

<div class="rzp-page">
    <div class="rzp-card">
        <div class="rzp-header">
            <h2><i class="fas fa-shield-alt"></i> Secure Payment</h2>
            <div class="rzp-amount">₹<?= number_format($orderAmount, 2); ?></div>
            <div class="rzp-order-id">Order: <?= htmlspecialchars($orderId); ?></div>
        </div>
        
        <div class="rzp-body">
            <!-- Order Items -->
            <div class="rzp-items">
                <?php foreach ($items as $item): ?>
                <div class="rzp-item">
                    <?php if (!empty($item['image'])): ?>
                    <img src="<?= asset_url('images/products/' . htmlspecialchars($item['image'])); ?>" alt="">
                    <?php endif; ?>
                    <div class="rzp-item-info">
                        <div class="rzp-item-name"><?= htmlspecialchars($item['name']); ?></div>
                        <div class="rzp-item-qty">
                            Qty: <?= $item['quantity']; ?>
                            <?php if (!empty($item['weight_name'])): ?>
                                · <?= htmlspecialchars($item['weight_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rzp-item-price">₹<?= number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <div class="rzp-summary">
                <div class="rzp-summary-row">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($subtotal, 2); ?></span>
                </div>
                <?php if ($promoDiscount > 0): ?>
                <div class="rzp-summary-row rzp-discount">
                    <span>Promo (<?= htmlspecialchars($promoCode); ?>)</span>
                    <span>-₹<?= number_format($promoDiscount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="rzp-summary-row rzp-total">
                    <span>Total</span>
                    <span>₹<?= number_format($orderAmount, 2); ?></span>
                </div>
            </div>

            <!-- Pay Button -->
            <button class="rzp-pay-btn" id="rzpPayBtn" onclick="initiatePayment()">
                <i class="fas fa-lock"></i> Pay ₹<?= number_format($orderAmount, 2); ?>
            </button>

            <div class="rzp-secure">
                <i class="fas fa-lock"></i> Payments are secured by Razorpay with 256-bit SSL encryption
            </div>

            <a href="<?= base_url('checkout.php'); ?>" class="rzp-back"><i class="fas fa-arrow-left"></i> Back to Checkout</a>

            <!-- Status messages -->
            <div id="rzpStatusSuccess" class="rzp-status" style="display:none;">
                <div style="color: #059669; font-size: 48px; margin-bottom: 10px;"><i class="fas fa-check-circle"></i></div>
                <h3 style="color: #059669;">Payment Successful!</h3>
                <p style="color: #6b7280;">Redirecting to order confirmation...</p>
            </div>

            <div id="rzpStatusError" class="rzp-status" style="display:none;">
                <div style="color: #dc2626; font-size: 48px; margin-bottom: 10px;"><i class="fas fa-times-circle"></i></div>
                <h3 style="color: #dc2626;">Payment Failed</h3>
                <p id="rzpErrorMsg" style="color: #6b7280;"></p>
                <button class="rzp-pay-btn" onclick="initiatePayment()" style="margin-top: 15px; max-width: 300px; margin-left: auto; margin-right: auto;">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Razorpay Checkout.js -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
function initiatePayment() {
    const btn = document.getElementById('rzpPayBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="rzp-spinner"></span> Creating order...';

    // Hide any previous status
    document.getElementById('rzpStatusSuccess').style.display = 'none';
    document.getElementById('rzpStatusError').style.display = 'none';

    // Step 1: Create Razorpay order on server
    fetch('<?= base_url("razorpay_create_order.php"); ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'create_order=1'
    })
    .then(r => r.text())
    .then(text => { try { return JSON.parse(text); } catch(e) { throw new Error('Server returned invalid response. Please try again.'); } })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Failed to create order');
        }

        btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹<?= number_format($orderAmount, 2); ?>';
        btn.disabled = false;

        // Step 2: Open Razorpay Checkout
        const options = {
            key: data.key_id,
            amount: data.amount,
            currency: data.currency,
            name: '<?= addslashes($businessName); ?>',
            description: 'Order ' + data.order_receipt,
            order_id: data.razorpay_order_id,
            image: '<?= get_company_logo_url(); ?>',
            prefill: {
                name: data.prefill.name || '',
                email: data.prefill.email || '',
                contact: data.prefill.contact || ''
            },
            notes: data.notes || {},
            theme: {
                color: '#1a237e'
            },
            handler: function(response) {
                // Step 3: Verify payment on server
                btn.disabled = true;
                btn.innerHTML = '<span class="rzp-spinner"></span> Verifying payment...';

                fetch('<?= base_url("razorpay_verify.php"); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'razorpay_payment_id=' + encodeURIComponent(response.razorpay_payment_id) +
                          '&razorpay_order_id=' + encodeURIComponent(response.razorpay_order_id) +
                          '&razorpay_signature=' + encodeURIComponent(response.razorpay_signature)
                })
                .then(r => r.text().then(text => ({ ok: r.ok, status: r.status, text: text })))
                .then(({ ok, status, text }) => {
                    // Try to parse JSON regardless of HTTP status
                    let data = null;
                    try { data = text && text.trim() ? JSON.parse(text) : null; } catch(e) {
                        console.error('Verify raw response (HTTP ' + status + '):', text);
                    }

                    if (data && data.success) {
                        // Order placed successfully
                        btn.style.display = 'none';
                        document.getElementById('rzpStatusSuccess').style.display = 'block';
                        setTimeout(() => {
                            window.location.href = data.redirect || '<?= base_url("thank-you.php"); ?>';
                        }, 1500);
                        return;
                    }

                    if (!ok || !data) {
                        // Server error but payment was captured at Razorpay
                        // Log the error and redirect — order may have been created
                        console.error('Verify HTTP ' + status + ':', text);
                        btn.style.display = 'none';
                        document.getElementById('rzpStatusSuccess').style.display = 'block';
                        document.querySelector('#rzpStatusSuccess h3').textContent = 'Payment Received!';
                        document.querySelector('#rzpStatusSuccess p').textContent = 'Confirming your order...';
                        setTimeout(() => {
                            window.location.href = '<?= base_url("thank-you.php"); ?>';
                        }, 2000);
                        return;
                    }

                    // data.success === false
                    throw new Error(data.error || 'Verification failed');
                })
                .catch(err => {
                    // Network error or explicit failure
                    console.error('Verify catch:', err);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹<?= number_format($orderAmount, 2); ?>';
                    document.getElementById('rzpStatusError').style.display = 'block';
                    document.getElementById('rzpErrorMsg').textContent = err.message || 'Verification failed. Check My Orders.';
                });
            },
            modal: {
                ondismiss: function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹<?= number_format($orderAmount, 2); ?>';
                }
            }
        };

        const rzp = new Razorpay(options);
        
        rzp.on('payment.failed', function(response) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹<?= number_format($orderAmount, 2); ?>';
            document.getElementById('rzpStatusError').style.display = 'block';
            document.getElementById('rzpErrorMsg').textContent = response.error.description || 'Payment failed. Please try again.';
        });

        rzp.open();
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹<?= number_format($orderAmount, 2); ?>';
        document.getElementById('rzpStatusError').style.display = 'block';
        document.getElementById('rzpErrorMsg').textContent = err.message;
    });
}
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
