<?php
/**
 * Order Cancellation Policy Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Order Cancellation Policy - Gilaf Store';
$metaDescription = 'Learn about our order cancellation policy, timeframes, and refund process.';

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
    
    .status-box {
        background: #fff;
        border: 2px solid #ddd;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .status-box.can-cancel {
        border-color: #28a745;
        background: #f0fff4;
    }
    
    .status-box.cannot-cancel {
        border-color: #dc3545;
        background: #fff5f5;
    }
    
    @media (max-width: 768px) {
        .policy-hero h1 { font-size: 2.5rem; }
        .policy-section h2 { font-size: 1.6rem; }
        .policy-section h3 { font-size: 1.3rem; }
    }
</style>

<!-- Hero Section -->
<div class="policy-hero">
    <h1>Order Cancellation Policy</h1>
    <p>Flexible cancellation options for your convenience</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="policy-section">
        <h2>Overview</h2>
        <p>We understand that circumstances change, and you may need to cancel your order. This policy outlines when and how you can cancel your order with Gilaf Foods & Spices.</p>
    </div>
    
    <div class="policy-section">
        <h2>Cancellation Timeframes</h2>
        
        <div class="status-box can-cancel">
            <h3 style="color: #28a745; margin-top: 0;">✓ You CAN Cancel</h3>
            <p><strong>Before Order Dispatch:</strong> Orders can be cancelled free of charge before they are dispatched from our warehouse.</p>
            <ul>
                <li>No cancellation fee</li>
                <li>Full refund processed</li>
                <li>Cancellation confirmed via email</li>
            </ul>
        </div>
        
        <div class="status-box cannot-cancel">
            <h3 style="color: #dc3545; margin-top: 0;">✗ You CANNOT Cancel</h3>
            <p><strong>After Order Dispatch:</strong> Once your order has been dispatched and is in transit, it cannot be cancelled.</p>
            <ul>
                <li>Order is already with the courier</li>
                <li>You may refuse delivery or initiate a return instead</li>
                <li>Return policy will apply</li>
            </ul>
        </div>
    </div>
    
    <div class="policy-section">
        <h2>How to Cancel Your Order</h2>
        
        <h3>Method 1: Through Your Account</h3>
        <ul>
            <li>Log in to your Gilaf Store account</li>
            <li>Go to "My Orders" section</li>
            <li>Find the order you wish to cancel</li>
            <li>Click "Cancel Order" button</li>
            <li>Select cancellation reason and confirm</li>
        </ul>
        
        <h3>Method 2: Contact Customer Support</h3>
        <ul>
            <li>Email us at <a href="mailto:gilafstore@gmail.com" style="color: #C9A961;">gilafstore@gmail.com</a></li>
            <li>Provide your order number and reason for cancellation</li>
            <li>Our team will process your request within 2-4 hours</li>
            <li>You will receive confirmation via email</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Order Status and Cancellation</h2>
        <p>Your ability to cancel depends on your order status:</p>
        
        <h3>Order Confirmed</h3>
        <p><strong>Status:</strong> Payment received, order being prepared</p>
        <p><strong>Cancellation:</strong> ✓ Can be cancelled</p>
        <p><strong>Timeframe:</strong> Usually within 24 hours of order placement</p>
        
        <h3>Processing</h3>
        <p><strong>Status:</strong> Order being packed and prepared for shipment</p>
        <p><strong>Cancellation:</strong> ✓ Can be cancelled (contact us immediately)</p>
        <p><strong>Timeframe:</strong> Limited window, act quickly</p>
        
        <h3>Dispatched</h3>
        <p><strong>Status:</strong> Order handed over to courier</p>
        <p><strong>Cancellation:</strong> ✗ Cannot be cancelled</p>
        <p><strong>Alternative:</strong> Refuse delivery or initiate return after delivery</p>
        
        <h3>In Transit</h3>
        <p><strong>Status:</strong> Order is being delivered</p>
        <p><strong>Cancellation:</strong> ✗ Cannot be cancelled</p>
        <p><strong>Alternative:</strong> Refuse delivery at your doorstep</p>
        
        <h3>Delivered</h3>
        <p><strong>Status:</strong> Order successfully delivered</p>
        <p><strong>Cancellation:</strong> ✗ Cannot be cancelled</p>
        <p><strong>Alternative:</strong> Initiate return within 7 days</p>
    </div>
    
    <div class="policy-section">
        <h2>Refund After Cancellation</h2>
        <p>When your cancellation is approved:</p>
        <ul>
            <li>Full refund is processed to your original payment method</li>
            <li>Refund initiated within 24-48 hours of cancellation</li>
            <li>Amount credited to your account within 5-10 business days</li>
            <li>Refund confirmation sent via email</li>
        </ul>
        
        <h3>Refund Amount</h3>
        <p>You will receive a full refund including:</p>
        <ul>
            <li>Product price</li>
            <li>Shipping charges (if applicable)</li>
            <li>Any taxes or fees paid</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Partial Cancellation</h2>
        <p>If you want to cancel only some items from your order:</p>
        <ul>
            <li>Contact customer support immediately</li>
            <li>Partial cancellation is possible before dispatch</li>
            <li>Partial refund will be processed for cancelled items</li>
            <li>Remaining items will be shipped as planned</li>
        </ul>
        <p><strong>Note:</strong> Partial cancellation may not be possible if the order is already being packed.</p>
    </div>
    
    <div class="policy-section">
        <h2>Seller-Initiated Cancellation</h2>
        <p>We may cancel your order in the following situations:</p>
        <ul>
            <li>Product is out of stock or unavailable</li>
            <li>Pricing or product information error</li>
            <li>Delivery address is unserviceable</li>
            <li>Payment verification issues</li>
            <li>Suspected fraudulent activity</li>
        </ul>
        <p>If we cancel your order:</p>
        <ul>
            <li>You will be notified immediately via email</li>
            <li>Full refund will be processed automatically</li>
            <li>No cancellation fee will be charged</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Non-Cancellable Orders</h2>
        <p>The following orders cannot be cancelled under any circumstances:</p>
        <ul>
            <li>Custom or personalized products</li>
            <li>Special orders placed on request</li>
            <li>Gift cards or vouchers</li>
            <li>Orders already delivered</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>International Orders</h2>
        <p>For international orders:</p>
        <ul>
            <li>Same cancellation policy applies</li>
            <li>Cancellation must be requested before dispatch</li>
            <li>Refund processing may take longer due to international payment systems</li>
            <li>Currency conversion rates at the time of refund will apply</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Important Notes</h2>
        <ul>
            <li>Cancellation requests are processed in the order they are received</li>
            <li>We recommend cancelling as soon as possible to avoid dispatch</li>
            <li>Check your order status before requesting cancellation</li>
            <li>Keep your order number handy when contacting support</li>
            <li>Cancellation confirmation will be sent to your registered email</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Contact Us</h2>
        <p>For order cancellation assistance, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
