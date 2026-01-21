<?php
/**
 * Payment Policy Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Payment Policy - Gilaf Store';
$metaDescription = 'Learn about our secure payment methods, accepted payment options, and payment security measures.';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    .policy-hero {
        background: linear-gradient(135deg, #C9A961 0%, #D4B76A 20%, #1A3C34 60%, #244A36 100%);
        color: white;
        padding: 120px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .policy-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 30% 40%, rgba(201, 169, 97, 0.2) 0%, transparent 60%), 
                    radial-gradient(circle at 70% 60%, rgba(26, 60, 52, 0.3) 0%, transparent 60%);
        opacity: 0.25;
        z-index: 0;
    }
    .policy-hero::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #C9A961 0%, #FFFFFF 50%, #C9A961 100%);
        z-index: 2;
    }
    .policy-hero h1 {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        font-family: 'Poppins', sans-serif;
    }
    .policy-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
        position: relative;
        z-index: 1;
        font-family: 'Poppins', sans-serif;
    }
    
    .policy-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 80px 20px;
    }
    
    .policy-section {
        margin-bottom: 50px;
    }
    
    .policy-section h2 {
        font-size: 2rem;
        color: #1A3C34;
        margin-bottom: 20px;
        font-weight: 700;
    }
    
    .policy-section h3 {
        font-size: 1.5rem;
        color: #244A36;
        margin-bottom: 15px;
        margin-top: 30px;
        font-weight: 600;
    }
    
    .policy-section p {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #555;
        margin-bottom: 15px;
    }
    
    .policy-section ul {
        margin-left: 30px;
        margin-bottom: 20px;
    }
    
    .policy-section li {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #555;
        margin-bottom: 10px;
    }
    
    .last-updated {
        background: #f8f9fa;
        padding: 15px 20px;
        border-left: 4px solid #C9A961;
        margin-bottom: 40px;
        font-size: 0.95rem;
        color: #666;
    }
    
    .payment-methods {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }
    
    .payment-card {
        background: #fff;
        border: 2px solid #e0e0e0;
        padding: 25px;
        border-radius: 8px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .payment-card:hover {
        border-color: #C9A961;
        box-shadow: 0 4px 12px rgba(201, 169, 97, 0.2);
    }
    
    .payment-card i {
        font-size: 3rem;
        color: #C9A961;
        margin-bottom: 15px;
    }
    
    .payment-card h4 {
        color: #1A3C34;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .security-badge {
        background: #f0fff4;
        border: 2px solid #28a745;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        text-align: center;
    }
    
    .security-badge i {
        font-size: 2.5rem;
        color: #28a745;
        margin-bottom: 10px;
    }
    
    @media (max-width: 768px) {
        .policy-hero h1 { font-size: 2.5rem; }
        .policy-section h2 { font-size: 1.6rem; }
        .policy-section h3 { font-size: 1.3rem; }
        .payment-methods { grid-template-columns: 1fr; }
    }
</style>

<!-- Hero Section -->
<div class="policy-hero">
    <h1>Payment Policy</h1>
    <p>Secure and convenient payment options</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="security-badge">
        <i class="fas fa-shield-alt"></i>
        <h3 style="color: #28a745; margin: 10px 0;">100% Secure Payments</h3>
        <p style="margin: 0;">All transactions are encrypted and protected by industry-standard security measures</p>
    </div>
    
    <div class="policy-section">
        <h2>Accepted Payment Methods</h2>
        <p>We accept a variety of secure payment methods for your convenience:</p>
        
        <div class="payment-methods">
            <div class="payment-card">
                <i class="fab fa-cc-visa"></i>
                <h4>Credit Cards</h4>
                <p>Visa, Mastercard, American Express</p>
            </div>
            
            <div class="payment-card">
                <i class="fab fa-cc-mastercard"></i>
                <h4>Debit Cards</h4>
                <p>All major debit cards accepted</p>
            </div>
            
            <div class="payment-card">
                <i class="fab fa-paypal"></i>
                <h4>PayPal</h4>
                <p>Secure PayPal checkout</p>
            </div>
            
            <div class="payment-card">
                <i class="fas fa-mobile-alt"></i>
                <h4>UPI</h4>
                <p>Google Pay, PhonePe, Paytm</p>
            </div>
            
            <div class="payment-card">
                <i class="fas fa-university"></i>
                <h4>Net Banking</h4>
                <p>All major Indian banks</p>
            </div>
            
            <div class="payment-card">
                <i class="fas fa-wallet"></i>
                <h4>Digital Wallets</h4>
                <p>Paytm, Amazon Pay, etc.</p>
            </div>
        </div>
    </div>
    
    <div class="policy-section">
        <h2>Payment Currency</h2>
        <p>All prices on our website are displayed in Indian Rupees (INR) by default. For international customers:</p>
        <ul>
            <li>Prices can be viewed in multiple currencies</li>
            <li>Currency conversion is done at current exchange rates</li>
            <li>Final amount charged may vary slightly due to exchange rate fluctuations</li>
            <li>Your bank may charge additional currency conversion fees</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Payment Processing</h2>
        
        <h3>Order Confirmation</h3>
        <p>When you place an order:</p>
        <ul>
            <li>Payment is authorized immediately</li>
            <li>Order confirmation email is sent within minutes</li>
            <li>Payment is captured only when order is dispatched</li>
            <li>If order is cancelled, authorization is released</li>
        </ul>
        
        <h3>Payment Verification</h3>
        <p>For security purposes, we may verify payments by:</p>
        <ul>
            <li>Requesting additional identification documents</li>
            <li>Contacting you via phone or email</li>
            <li>Verifying billing address with card issuer</li>
            <li>Checking for suspicious transaction patterns</li>
        </ul>
        <p>Orders may be held until verification is complete.</p>
    </div>
    
    <div class="policy-section">
        <h2>Payment Security</h2>
        <p>We take payment security seriously and implement multiple layers of protection:</p>
        
        <h3>Encryption</h3>
        <ul>
            <li>All payment data is encrypted using SSL/TLS technology</li>
            <li>We use 256-bit encryption for data transmission</li>
            <li>Payment information is never stored on our servers</li>
            <li>PCI DSS compliant payment gateway</li>
        </ul>
        
        <h3>Fraud Prevention</h3>
        <ul>
            <li>Advanced fraud detection systems</li>
            <li>Real-time transaction monitoring</li>
            <li>3D Secure authentication for card payments</li>
            <li>Address verification system (AVS)</li>
        </ul>
        
        <h3>Your Responsibility</h3>
        <p>To ensure secure transactions:</p>
        <ul>
            <li>Never share your password or OTP with anyone</li>
            <li>Use secure internet connections for payments</li>
            <li>Keep your payment information confidential</li>
            <li>Report suspicious activity immediately</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Payment Failures</h2>
        <p>If your payment fails, it may be due to:</p>
        <ul>
            <li>Insufficient funds in your account</li>
            <li>Incorrect card details or CVV</li>
            <li>Card expired or blocked</li>
            <li>Bank declining the transaction</li>
            <li>Daily transaction limit exceeded</li>
            <li>Technical issues with payment gateway</li>
        </ul>
        
        <h3>What to Do</h3>
        <ul>
            <li>Verify your payment details are correct</li>
            <li>Check with your bank for any restrictions</li>
            <li>Try a different payment method</li>
            <li>Contact our customer support for assistance</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Pricing and Taxes</h2>
        
        <h3>Product Pricing</h3>
        <ul>
            <li>All prices include applicable taxes (GST for India)</li>
            <li>Prices are subject to change without notice</li>
            <li>Promotional prices are valid for limited periods</li>
            <li>Pricing errors will be corrected before order confirmation</li>
        </ul>
        
        <h3>Additional Charges</h3>
        <p>The following may apply to your order:</p>
        <ul>
            <li>Shipping charges (free for domestic orders)</li>
            <li>International shipping fees</li>
            <li>Customs duties and import taxes (international orders)</li>
            <li>Gift wrapping charges (if selected)</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Refunds and Chargebacks</h2>
        
        <h3>Refund Processing</h3>
        <p>When a refund is issued:</p>
        <ul>
            <li>Refund is processed to original payment method</li>
            <li>Processing time: 24-48 hours after approval</li>
            <li>Credit to account: 5-10 business days</li>
            <li>Refund confirmation sent via email</li>
        </ul>
        
        <h3>Chargebacks</h3>
        <p>If you initiate a chargeback with your bank:</p>
        <ul>
            <li>Contact us first to resolve the issue</li>
            <li>Chargebacks may delay refund processing</li>
            <li>We may request additional documentation</li>
            <li>Repeated chargebacks may result in account suspension</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>International Payments</h2>
        <p>For customers outside India:</p>
        <ul>
            <li>Payments are processed in your local currency</li>
            <li>Exchange rates are determined by your payment provider</li>
            <li>International transaction fees may apply</li>
            <li>Your bank may charge additional fees</li>
            <li>Customs duties are customer's responsibility</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Payment Disputes</h2>
        <p>If you have a payment dispute:</p>
        <ul>
            <li>Contact us immediately at <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a></li>
            <li>Provide order number and transaction details</li>
            <li>We will investigate and respond within 48 hours</li>
            <li>Resolution will be provided within 7 business days</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Promotional Codes and Discounts</h2>
        <p>When using promotional codes:</p>
        <ul>
            <li>Enter code at checkout before payment</li>
            <li>Only one promotional code per order</li>
            <li>Codes cannot be combined with other offers</li>
            <li>Codes have expiration dates and terms</li>
            <li>Invalid codes will not be applied</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Contact Us</h2>
        <p>For payment-related questions or issues, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
