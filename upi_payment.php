<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    redirect_with_message('/user/login_final.php', 'Please login to continue', 'info');
}

// Check if order details are in session
if (!isset($_SESSION['pending_order'])) {
    redirect_with_message('/checkout.php', 'No pending order found', 'error');
}

$order = $_SESSION['pending_order'];
$orderAmount = $order['total'];
$orderId = $order['order_id'] ?? 'ORD' . time();
$items = $order['items'] ?? [];

// Calculate price breakdown
$subtotal = $orderAmount;
$discount = 0;
$additionalFees = 0;
$total = $subtotal - $discount + $additionalFees;

// Business UPI details
$businessUpiId = 'gilaf@ptyes';
$businessName = 'Gilaf Foods Andspices';
$currency = 'INR';

// Generate UPI payment URL
$upiUrl = "upi://pay?pa=" . urlencode($businessUpiId) . 
          "&pn=" . urlencode($businessName) . 
          "&am=" . urlencode($orderAmount) . 
          "&cu=" . urlencode($currency) . 
          "&tn=" . urlencode("Order " . $orderId);

// App-specific UPI URLs
$googlePayUrl = "tez://upi/pay?pa=" . urlencode($businessUpiId) . 
                "&pn=" . urlencode($businessName) . 
                "&am=" . urlencode($orderAmount) . 
                "&cu=" . urlencode($currency) . 
                "&tn=" . urlencode("Order " . $orderId);

$phonepeUrl = "phonepe://pay?pa=" . urlencode($businessUpiId) . 
              "&pn=" . urlencode($businessName) . 
              "&am=" . urlencode($orderAmount) . 
              "&cu=" . urlencode($currency) . 
              "&tn=" . urlencode("Order " . $orderId);

$paytmUrl = "paytmmp://pay?pa=" . urlencode($businessUpiId) . 
            "&pn=" . urlencode($businessName) . 
            "&am=" . urlencode($orderAmount) . 
            "&cu=" . urlencode($currency) . 
            "&tn=" . urlencode("Order " . $orderId);

// Detect if mobile device
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$isMobileDevice = isMobile();

$pageTitle = 'UPI Payment — Gilaf Store';
include __DIR__ . '/includes/new-header.php';

// Cache busting
$cacheVersion = time();
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #f5f5f5;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.payment-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.payment-grid {
    display: flex;
    flex-direction: column;
    gap: 0;
    align-items: center;
}

.payment-section {
    background: white;
    border-radius: 8px;
    padding: 40px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 600px;
    text-align: center;
}

.payment-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.payment-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 10px;
}

.offers-link {
    color: #7c3aed;
    font-size: 14px;
    text-decoration: none;
    font-weight: 500;
}

.offers-link:hover {
    text-decoration: underline;
}

.bank-offers {
    background: #eff6ff;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bank-offers i {
    color: #3b82f6;
}

.bank-offers span {
    font-size: 13px;
    color: #1e40af;
}

.upi-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    cursor: pointer;
}

.upi-section-header h3 {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}

.offers-badge {
    background: #dcfce7;
    color: #166534;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.qr-section {
    padding: 20px 0;
}

.qr-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 16px;
}

.qr-title i {
    font-size: 16px;
}

.upi-apps-row {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 20px;
}

.upi-app-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    background: white;
    padding: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.upi-app-icon:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.upi-app-icon img {
    width: 30px;
    height: 30px;
    object-fit: contain;
}

.qr-code-wrapper {
    text-align: center;
    padding: 30px;
    background: #000;
    border-radius: 8px;
    margin: 20px auto;
    max-width: 400px;
}

.qr-code-wrapper img {
    width: 280px;
    height: 280px;
    display: block;
    margin: 0 auto;
    background: white;
    padding: 15px;
    border-radius: 4px;
}

.qr-payment-text {
    color: white;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.qr-amount-text {
    color: white;
    font-size: 13px;
    margin-top: 15px;
    font-weight: 500;
}

.qr-validity {
    text-align: center;
    font-size: 14px;
    color: #374151;
    margin: 20px 0;
    padding: 12px;
    background: #fef3c7;
    border-radius: 6px;
}

.qr-validity .time {
    color: #dc2626;
    font-weight: 700;
    font-size: 15px;
}

.timer-progress {
    width: 100%;
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    margin-top: 10px;
    overflow: hidden;
}

.timer-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #fbbf24 50%, #ef4444 100%);
    transition: width 1s linear;
}

.upi-id-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
    padding: 20px;
    border-radius: 8px;
    border: 2px solid #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.upi-id-label {
    font-size: 16px;
    font-weight: 700;
    color: #1e40af;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.upi-id-label::before {
    content: '📝';
    font-size: 20px;
}

.upi-id-help {
    color: #7c3aed;
    font-size: 13px;
    text-decoration: none;
    float: right;
}

.upi-id-input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #3b82f6;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 12px;
    background: white;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.upi-id-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.upi-id-note {
    font-size: 13px;
    color: #1e40af;
    background: rgba(59, 130, 246, 0.1);
    padding: 10px 12px;
    border-radius: 6px;
    border-left: 3px solid #3b82f6;
    margin-bottom: 12px;
}

.help-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.help-modal.active {
    display: flex;
}

.help-modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.help-modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.help-modal-steps {
    margin: 20px 0;
}

.help-step {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
}

.help-step-number {
    width: 30px;
    height: 30px;
    background: #7c3aed;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.help-step-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 5px;
}

.help-step-content p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}

.help-modal-close {
    width: 100%;
    padding: 12px;
    background: #7c3aed;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 20px;
}

.transaction-id-example {
    background: #f3f4f6;
    padding: 12px;
    border-radius: 6px;
    margin: 15px 0;
    text-align: center;
}

.transaction-id-example code {
    font-size: 16px;
    font-weight: 700;
    color: #7c3aed;
    letter-spacing: 1px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.btn-cancel {
    flex: 1;
    padding: 10px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.btn-verify {
    flex: 1;
    padding: 10px;
    border: none;
    background: #7c3aed;
    color: white;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.btn-verify:hover {
    background: #6d28d9;
}

.summary-section {
    display: none;
}

.summary-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 16px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 14px;
}

.price-row.label {
    color: #6b7280;
}

.price-row.value {
    color: #1f2937;
    font-weight: 500;
}

.price-row.discount .value {
    color: #059669;
}

.price-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 12px 0;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
}

.discount-badge {
    background: #dcfce7;
    color: #059669;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    margin: 16px 0;
}

.discount-note {
    font-size: 12px;
    color: #6b7280;
    text-align: center;
    margin-top: 12px;
}

.btn-continue {
    width: 100%;
    padding: 12px;
    background: #7c3aed;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 16px;
}

.btn-continue:hover {
    background: #6d28d9;
}

.upi-apps-logos {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin: 20px 0;
    padding: 15px;
    background: white;
    border-radius: 8px;
    flex-wrap: wrap;
}

.upi-app-logo {
    height: 35px;
    width: auto;
    max-width: 80px;
    object-fit: contain;
}

.payment-note {
    font-size: 13px;
    color: #6b7280;
    text-align: center;
    margin-top: 20px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
}

@media (max-width: 768px) {
    .payment-section {
        padding: 20px;
    }
    
    .qr-code-wrapper img {
        width: 220px;
        height: 220px;
    }
}
</style>

<div class="payment-container">
    <div class="payment-grid">
        <!-- Payment Section -->
        <div class="payment-section">
            <div class="payment-header">
                <div class="payment-title">Complete your payment</div>
            </div>

            <div class="qr-section">
                <div class="qr-code-wrapper">
                    <div class="qr-payment-text">Scan and Pay using any UPI app</div>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($upiUrl); ?>" alt="UPI QR Code">
                    <div class="qr-amount-text">Payment of ₹ <?= number_format($orderAmount, 2); ?></div>
                </div>

                <div class="upi-apps-logos">
                    <img src="assets/images/payment-logos/amazonpay-secondary-logo-rgb_clr.svg" alt="Amazon Pay" class="upi-app-logo">
                    <img src="assets/images/payment-logos/PhonePe_Logo.svg.png" alt="PhonePe" class="upi-app-logo">
                    <img src="assets/images/payment-logos/Google_Pay_Logo.svg.png" alt="Google Pay" class="upi-app-logo">
                    <img src="assets/images/payment-logos/bhim-upi-icon.svg" alt="BHIM UPI" class="upi-app-logo">
                </div>

                <div class="qr-validity">
                    QR code is valid for <span class="time" id="timer">15:00</span> minutes
                    <div class="timer-progress">
                        <div class="timer-progress-bar" id="progressBar" style="width: 100%;"></div>
                    </div>
                </div>

                <div class="payment-note">
                    Please don't refresh the page or hit the back button until the transaction is complete.
                </div>

                <div class="upi-id-section">
                    <div class="upi-id-label">
                        Enter Transaction ID
                        <a href="#" class="upi-id-help" onclick="event.preventDefault(); showTransactionIdHelp()">How To Find Transaction ID?</a>
                    </div>
                    <input type="text" class="upi-id-input" id="transactionId" placeholder="Enter your 12-digit UPI Transaction ID (e.g., 123456789012)">
                    <div class="upi-id-note">After making payment via any UPI app, enter the Transaction ID/Reference Number here</div>

                    <div class="action-buttons">
                        <button class="btn-cancel" onclick="window.location.href='checkout.php'">Cancel</button>
                        <button class="btn-verify" onclick="confirmPayment()">Verify</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Help Modal -->
<div id="helpModal" class="help-modal">
    <div class="help-modal-content">
        <h3 class="help-modal-title">
            <span>🔍</span>
            How to Find Your Transaction ID
        </h3>
        
        <div class="help-modal-steps">
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h4>Make Payment via UPI App</h4>
                    <p>Scan the QR code or click on any UPI app icon above to make the payment</p>
                </div>
            </div>
            
            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h4>Complete the Payment</h4>
                    <p>Enter your UPI PIN and confirm the payment in your UPI app (Google Pay, PhonePe, Paytm, etc.)</p>
                </div>
            </div>
            
            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h4>Check Payment Success Message</h4>
                    <p>After successful payment, you'll see a confirmation screen with payment details</p>
                </div>
            </div>
            
            <div class="help-step">
                <div class="help-step-number">4</div>
                <div class="help-step-content">
                    <h4>Find Transaction ID</h4>
                    <p>Look for "Transaction ID", "Reference Number", "UTR Number", or "Transaction Reference" on the success screen</p>
                </div>
            </div>
            
            <div class="help-step">
                <div class="help-step-number">5</div>
                <div class="help-step-content">
                    <h4>Copy and Enter Here</h4>
                    <p>Copy the 12-digit transaction ID and paste it in the input field above</p>
                </div>
            </div>
        </div>
        
        <div class="transaction-id-example">
            <p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280;">Example Transaction ID:</p>
            <code>123456789012</code>
        </div>
        
        <p style="font-size: 13px; color: #6b7280; margin: 15px 0;">
            <strong>Note:</strong> The transaction ID is usually 12 digits long and can be found in your UPI app's transaction history or payment confirmation screen.
        </p>
        
        <button class="help-modal-close" onclick="closeHelpModal()">Got it!</button>
    </div>
</div>

<script>
// Display error message if present
<?php if (isset($_SESSION['payment_error'])): ?>
    alert('<?= addslashes($_SESSION['payment_error']); ?>');
    <?php unset($_SESSION['payment_error']); ?>
<?php endif; ?>

function showTransactionIdHelp() {
    document.getElementById('helpModal').classList.add('active');
}

function closeHelpModal() {
    document.getElementById('helpModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('helpModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeHelpModal();
    }
});

// 15-minute countdown timer
let timeLeft = 15 * 60; // 15 minutes in seconds
const timerElement = document.getElementById('timer');
const progressBar = document.getElementById('progressBar');
const totalTime = 15 * 60;

function updateTimer() {
    if (timeLeft <= 0) {
        clearInterval(timerInterval);
        timerElement.textContent = '0:00';
        progressBar.style.width = '0%';
        alert('QR code has expired. Please try again.');
        window.location.href = 'checkout.php';
        return;
    }
    
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    // Update progress bar
    const percentage = (timeLeft / totalTime) * 100;
    progressBar.style.width = percentage + '%';
    
    timeLeft--;
}

// Start timer
updateTimer();
const timerInterval = setInterval(updateTimer, 1000);

function confirmPayment() {
    const transactionId = document.getElementById('transactionId').value.trim();
    
    if (timeLeft <= 0) {
        alert('QR code has expired. Please try again.');
        window.location.href = 'checkout.php';
        return;
    }
    
    if (!transactionId) {
        alert('Please enter your UPI transaction ID');
        return;
    }
    
    if (transactionId.length < 10) {
        alert('Please enter a valid transaction ID (minimum 10 characters)');
        return;
    }
    
    // Disable button to prevent double submission
    const verifyBtn = event.target;
    verifyBtn.disabled = true;
    verifyBtn.textContent = 'Processing...';
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'confirm_upi_payment.php';
    
    const txnInput = document.createElement('input');
    txnInput.type = 'hidden';
    txnInput.name = 'transaction_id';
    txnInput.value = transactionId;
    
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = 'order_id';
    orderInput.value = '<?= htmlspecialchars($orderId); ?>';
    
    form.appendChild(txnInput);
    form.appendChild(orderInput);
    document.body.appendChild(form);
    
    console.log('Submitting payment confirmation with Transaction ID:', transactionId);
    form.submit();
}
</script>

<?php
include __DIR__ . '/includes/new-footer.php';
?>
