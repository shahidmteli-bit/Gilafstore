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
    max-width: 600px;
    margin: 20px auto;
    padding: 25px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.success-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    animation: scaleIn 0.5s ease-out;
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

.success-icon i {
    font-size: 35px;
    color: white;
}

.success-title {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 8px;
}

.success-subtitle {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
}

.order-details {
    background: #f9fafb;
    padding: 18px;
    border-radius: 10px;
    margin: 20px 0;
    text-align: left;
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
    padding: 4px 10px;
    background: #dcfce7;
    color: #166534;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn-primary-action {
    flex: 1;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    color: white;
}

.btn-secondary-action {
    flex: 1;
    background: white;
    color: #10b981;
    border: 2px solid #10b981;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-secondary-action:hover {
    background: #f0fdf4;
    color: #059669;
}

.info-box {
    background: #eff6ff;
    padding: 15px;
    border-radius: 10px;
    margin-top: 20px;
    border-left: 4px solid #3b82f6;
}

.info-box h6 {
    color: #1e40af;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}

.info-box p {
    color: #475569;
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
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
        <i class="fas fa-check"></i>
    </div>

    <h1 class="success-title">Payment Received!</h1>
    <p class="success-subtitle">Thank you for your order. We're processing it now.</p>

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
