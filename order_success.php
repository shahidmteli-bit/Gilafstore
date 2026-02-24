<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    redirect_with_message('/user/login_final.php', 'Please login to continue', 'info');
}

// Check if order success data exists
if (!isset($_SESSION['order_success'])) {
    redirect_with_message('/index.php', 'No order found', 'info');
}

$orderData = $_SESSION['order_success'];

// Record promo code usage before clearing it
$appliedPromo = get_applied_promo_code();
if ($appliedPromo && isset($appliedPromo['id'])) {
    $userId = $_SESSION['user']['id'] ?? null;
    $userEmail = $_SESSION['user']['email'] ?? null;
    $userPhone = $_SESSION['user']['phone'] ?? null;
    $discountAmount = $orderData['promo_discount'] ?? $appliedPromo['discount_amount'] ?? 0;
    $orderId = $orderData['db_order_id'] ?? null; // Database order ID if available
    
    // Get user's order count for tracking
    $orderCount = 0;
    if ($userId) {
        $orderCountResult = db_fetch("SELECT COUNT(*) as count FROM orders WHERE user_id = ?", [$userId]);
        $orderCount = $orderCountResult ? (int)$orderCountResult['count'] : 0;
    }
    
    record_promo_usage($appliedPromo['id'], $userId, $orderId, $discountAmount, $userEmail, $userPhone, $orderCount);
}

// Clear promo code after successful payment
clear_promo_after_payment();

// Clear buy_now session if this was a Buy Now order
if (isset($_SESSION['buy_now'])) {
    unset($_SESSION['buy_now']);
}

// Track purchase events for analytics
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    if (isset($orderData['items']) && is_array($orderData['items'])) {
        foreach ($orderData['items'] as $item) {
            trackProductEvent(
                $item['product_id'],
                'purchase',
                'checkout',
                $item['category_id'] ?? null,
                $item['price'] ?? null,
                $item['quantity'] ?? 1
            );
        }
    }
}

unset($_SESSION['order_success']); // Clear after reading

$pageTitle = 'Order Successful — Gilaf Store';
include __DIR__ . '/includes/new-header.php';
?>

<style>
.success-container {
    max-width: 650px;
    margin: 40px auto;
    padding: 50px 40px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 50px rgba(0, 0, 0, 0.08);
    text-align: center;
    border-top: 5px solid #C5A059;
    position: relative;
    overflow: hidden;
}

.success-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #C5A059 0%, #1A3C34 50%, #C5A059 100%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.success-icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    animation: scaleIn 0.6s ease-out;
    box-shadow: 0 10px 40px rgba(26, 60, 52, 0.3);
    position: relative;
}

.success-icon::before {
    content: '';
    position: absolute;
    width: 140px;
    height: 140px;
    background: rgba(197, 160, 89, 0.1);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes scaleIn {
    0% {
        transform: scale(0) rotate(-180deg);
        opacity: 0;
    }
    50% {
        transform: scale(1.1) rotate(10deg);
    }
    100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.3;
    }
}

.success-icon i {
    font-size: 55px;
    color: #C5A059;
    z-index: 1;
}

.success-title {
    font-size: 32px;
    font-weight: 700;
    color: #1A3C34;
    margin-bottom: 12px;
    font-family: var(--font-serif);
    letter-spacing: -0.5px;
}

.success-subtitle {
    font-size: 16px;
    color: #6b7280;
    margin-bottom: 30px;
    line-height: 1.6;
    font-weight: 400;
}

.order-details {
    background: linear-gradient(135deg, #F8F5F2 0%, #f9fafb 100%);
    padding: 25px;
    border-radius: 12px;
    margin: 30px 0;
    text-align: left;
    border: 1px solid rgba(197, 160, 89, 0.2);
}

.order-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.order-detail-row:last-child {
    border-bottom: none;
}

.order-detail-label {
    font-weight: 600;
    color: #4b5563;
    font-size: 14px;
}

.order-detail-value {
    color: #1f2937;
    font-weight: 500;
    font-size: 14px;
}

.payment-badge {
    display: inline-block;
    padding: 6px 12px;
    background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
    color: #166534;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #86efac;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn-primary-action {
    flex: 1;
    background: linear-gradient(135deg, #C5A059 0%, #b08d4b 100%);
    color: white;
    border: none;
    padding: 14px 24px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    letter-spacing: 0.5px;
}

.btn-primary-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(197, 160, 89, 0.4);
    color: white;
    background: linear-gradient(135deg, #b08d4b 0%, #C5A059 100%);
}

.btn-secondary-action {
    flex: 1;
    background: white;
    color: #1A3C34;
    border: 2px solid #1A3C34;
    padding: 14px 24px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    letter-spacing: 0.5px;
}

.btn-secondary-action:hover {
    background: #F8F5F2;
    color: #1A3C34;
    border-color: #C5A059;
}

.info-box {
    background: linear-gradient(135deg, #F8F5F2 0%, #fffbf5 100%);
    padding: 20px;
    border-radius: 12px;
    margin-top: 25px;
    border-left: 4px solid #C5A059;
    border: 1px solid rgba(197, 160, 89, 0.3);
}

.info-box h6 {
    color: #1A3C34;
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 15px;
}

.info-box p {
    color: #6b7280;
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .success-container {
        margin: 15px;
        padding: 20px;
    }
    
    .success-icon {
        width: 60px;
        height: 60px;
    }
    
    .success-icon i {
        font-size: 30px;
    }
    
    .success-title {
        font-size: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<div class="success-container">
    <div class="success-icon">
        <i class="fas fa-shopping-bag"></i>
    </div>

    <h1 class="success-title">Your Order Has Been Placed!</h1>
    <p class="success-subtitle">Thank you for shopping with Gilaf. We're preparing your order.</p>

    <div class="order-details">
        <div class="order-detail-row">
            <span class="order-detail-label">Order ID</span>
            <span class="order-detail-value">#<?= htmlspecialchars($orderData['order_id']); ?></span>
        </div>
        <div class="order-detail-row">
            <span class="order-detail-label">Transaction ID</span>
            <span class="order-detail-value"><?= htmlspecialchars($orderData['transaction_id']); ?></span>
        </div>
        <div class="order-detail-row">
            <span class="order-detail-label">Amount Paid</span>
            <span class="order-detail-value">₹<?= number_format($orderData['amount'], 2); ?></span>
        </div>
        <div class="order-detail-row">
            <span class="order-detail-label">Payment Method</span>
            <span class="order-detail-value">
                <span class="payment-badge">
                    <i class="fas fa-mobile-alt me-1"></i><?= htmlspecialchars($orderData['payment_method']); ?>
                </span>
            </span>
        </div>
    </div>

    <div class="info-box">
        <h6><i class="fas fa-info-circle me-2"></i>What's Next?</h6>
        <p>We're verifying your payment and will start processing your order shortly. You'll receive an email confirmation with tracking details once your order ships.</p>
    </div>

    <div class="action-buttons">
        <a href="<?= base_url('user/orders.php'); ?>" class="btn-secondary-action">
            <i class="fas fa-list me-2"></i>View Orders
        </a>
        <a href="<?= base_url('index.php'); ?>" class="btn-primary-action">
            <i class="fas fa-home me-2"></i>Continue Shopping
        </a>
    </div>
</div>

<?php
include __DIR__ . '/includes/new-footer.php';
?>
