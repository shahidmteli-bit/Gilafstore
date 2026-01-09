<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['user']) || !isset($_GET['order_id'])) {
    redirect_with_message('/index.php', 'Invalid access', 'error');
}

$orderId = (int)$_GET['order_id'];
$orderConfirmation = $_SESSION['order_confirmation'] ?? null;

if (!$orderConfirmation) {
    redirect_with_message('/index.php', 'Order not found', 'error');
}

include __DIR__ . '/includes/new-header.php';
?>

<style>
.payment-gateway-container {
    min-height: 80vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.payment-card {
    background: white;
    border-radius: 24px;
    padding: 50px;
    max-width: 600px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.payment-header {
    text-align: center;
    margin-bottom: 40px;
}

.payment-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.5rem;
    color: white;
}

.payment-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: #1A3C34;
    margin-bottom: 10px;
}

.payment-header p {
    color: #666;
    font-size: 1rem;
}

.order-details {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
}

.order-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}

.order-row:last-child {
    border-bottom: none;
    font-size: 1.2rem;
    font-weight: 700;
    color: #667eea;
    padding-top: 20px;
    margin-top: 10px;
    border-top: 2px solid #e5e7eb;
}

.card-form {
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #1A3C34;
    margin-bottom: 8px;
}

.form-group input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 15px;
}

.pay-button {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pay-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.security-info {
    text-align: center;
    margin-top: 20px;
    padding: 15px;
    background: rgba(102, 126, 234, 0.05);
    border-radius: 12px;
    color: #666;
    font-size: 0.9rem;
}

.security-info i {
    color: #667eea;
    margin-right: 8px;
}

.card-logos {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.card-logos i {
    font-size: 2rem;
    color: #666;
}
</style>

<div class="payment-gateway-container">
    <div class="payment-card">
        <div class="payment-header">
            <div class="payment-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <h1>Secure Payment</h1>
            <p>Complete your payment to confirm order #<?= $orderId ?></p>
        </div>

        <div class="order-details">
            <div class="order-row">
                <span>Order ID</span>
                <span><strong>#<?= $orderId ?></strong></span>
            </div>
            <div class="order-row">
                <span>Customer</span>
                <span><?= htmlspecialchars($orderConfirmation['name'] ?? $_SESSION['user']['name']); ?></span>
            </div>
            <div class="order-row">
                <span>Email</span>
                <span><?= htmlspecialchars($orderConfirmation['email'] ?? $_SESSION['user']['email']); ?></span>
            </div>
            <div class="order-row">
                <span>Total Amount</span>
                <span>$<?= number_format($orderConfirmation['total'], 2); ?></span>
            </div>
        </div>

        <form method="post" action="process_payment.php" class="card-form">
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> Card Number</label>
                <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Cardholder Name</label>
                <input type="text" name="card_name" placeholder="John Doe" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Expiry Date</label>
                    <input type="text" name="expiry" placeholder="MM/YY" maxlength="5" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> CVV</label>
                    <input type="text" name="cvv" placeholder="123" maxlength="4" required>
                </div>
            </div>

            <button type="submit" class="pay-button">
                <i class="fas fa-lock"></i> Pay $<?= number_format($orderConfirmation['total'], 2); ?>
            </button>
        </form>

        <div class="card-logos">
            <i class="fab fa-cc-visa"></i>
            <i class="fab fa-cc-mastercard"></i>
            <i class="fab fa-cc-amex"></i>
            <i class="fab fa-cc-discover"></i>
        </div>

        <div class="security-info">
            <i class="fas fa-shield-alt"></i>
            Your payment information is encrypted and secure. We never store your card details.
        </div>
    </div>
</div>

<script>
// Auto-format card number
document.querySelector('input[name="card_number"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Auto-format expiry date
document.querySelector('input[name="expiry"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
    } else {
        e.target.value = value;
    }
});

// Only allow numbers for CVV
document.querySelector('input[name="cvv"]').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '');
});
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
