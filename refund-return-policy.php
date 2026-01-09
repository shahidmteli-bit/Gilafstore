<?php
/**
 * Refund & Return Policy Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Refund & Return Policy - Gilaf Store';
$metaDescription = 'Learn about our refund and return policy, including eligible products and return procedures.';

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
    
    .highlight-box {
        background: #fff9e6;
        border: 2px solid #C9A961;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .highlight-box h3 {
        color: #1A3C34;
        margin-top: 0;
    }
    
    @media (max-width: 768px) {
        .policy-hero h1 { font-size: 2.5rem; }
        .policy-section h2 { font-size: 1.6rem; }
        .policy-section h3 { font-size: 1.3rem; }
    }
</style>

<!-- Hero Section -->
<div class="policy-hero">
    <h1>Refund & Return Policy</h1>
    <p>Your satisfaction is our priority</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="policy-section">
        <h2>Our Commitment</h2>
        <p>At Gilaf Foods & Spices, we are committed to providing high-quality products and excellent customer service. If you are not completely satisfied with your purchase, we offer a fair and transparent return and refund policy.</p>
    </div>
    
    <div class="policy-section">
        <h2>Return Eligibility</h2>
        <p>You may return products within <strong>7 days</strong> of delivery if they meet the following conditions:</p>
        <ul>
            <li>Product is unused, unopened, and in original packaging</li>
            <li>All seals, labels, and tags are intact</li>
            <li>Product is not damaged or tampered with</li>
            <li>Original invoice or proof of purchase is provided</li>
        </ul>
        
        <div class="highlight-box">
            <h3>Important Note</h3>
            <p>Due to the nature of food products, we cannot accept returns of opened or used items for health and safety reasons.</p>
        </div>
    </div>
    
    <div class="policy-section">
        <h2>Non-Returnable Items</h2>
        <p>The following items cannot be returned or refunded:</p>
        <ul>
            <li>Opened or partially consumed food products</li>
            <li>Products with broken seals or damaged packaging</li>
            <li>Perishable items past their return window</li>
            <li>Custom or personalized orders</li>
            <li>Gift cards or vouchers</li>
            <li>Sale or clearance items (unless defective)</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Damaged or Defective Products</h2>
        <p>If you receive a damaged or defective product:</p>
        <ul>
            <li>Contact us within <strong>48 hours</strong> of delivery</li>
            <li>Provide photos of the damaged product and packaging</li>
            <li>Include your order number and description of the issue</li>
            <li>We will arrange a replacement or full refund</li>
        </ul>
        <p>We take full responsibility for products damaged during shipping or manufacturing defects.</p>
    </div>
    
    <div class="policy-section">
        <h2>How to Initiate a Return</h2>
        <p>To return a product, follow these steps:</p>
        <ul>
            <li><strong>Step 1:</strong> Contact our customer support team at <a href="mailto:gilafstore@gmail.com" style="color: #C9A961;">gilafstore@gmail.com</a></li>
            <li><strong>Step 2:</strong> Provide your order number and reason for return</li>
            <li><strong>Step 3:</strong> Wait for return authorization and instructions</li>
            <li><strong>Step 4:</strong> Pack the product securely in original packaging</li>
            <li><strong>Step 5:</strong> Ship the product to the provided return address</li>
            <li><strong>Step 6:</strong> Provide tracking information for the return shipment</li>
        </ul>
        
        <div class="highlight-box">
            <h3>Return Shipping Costs</h3>
            <p><strong>Customer Responsibility:</strong> Return shipping costs are borne by the customer for change of mind returns.</p>
            <p><strong>Our Responsibility:</strong> We cover return shipping for damaged, defective, or incorrect products.</p>
        </div>
    </div>
    
    <div class="policy-section">
        <h2>Refund Process</h2>
        <p>Once we receive and inspect your returned product:</p>
        <ul>
            <li>Inspection is completed within 2-3 business days</li>
            <li>Refund is processed to your original payment method</li>
            <li>You will receive a refund confirmation email</li>
            <li>Refund appears in your account within 5-10 business days</li>
        </ul>
        
        <h3>Refund Amount</h3>
        <p>Your refund will include:</p>
        <ul>
            <li>Full product price</li>
            <li>Original shipping charges (if product was defective or incorrect)</li>
        </ul>
        <p>Refunds do not include:</p>
        <ul>
            <li>Return shipping costs (for change of mind returns)</li>
            <li>Gift wrapping or special packaging fees</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Exchanges</h2>
        <p>We currently do not offer direct product exchanges. If you wish to exchange a product:</p>
        <ul>
            <li>Return the original product following our return process</li>
            <li>Place a new order for the desired product</li>
            <li>Refund from the return will be processed separately</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Wrong or Missing Items</h2>
        <p>If you receive the wrong item or items are missing from your order:</p>
        <ul>
            <li>Contact us immediately at <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a></li>
            <li>Provide your order number and details of the issue</li>
            <li>We will send the correct items at no additional cost</li>
            <li>Return shipping labels will be provided for wrong items</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Partial Refunds</h2>
        <p>Partial refunds may be issued in the following cases:</p>
        <ul>
            <li>Product shows signs of use or damage</li>
            <li>Product is missing parts or accessories</li>
            <li>Product is returned after the 7-day window</li>
            <li>Packaging is damaged or incomplete</li>
        </ul>
        <p>The refund amount will be determined based on the product condition.</p>
    </div>
    
    <div class="policy-section">
        <h2>International Returns</h2>
        <p>For international orders:</p>
        <ul>
            <li>Same return policy applies (7 days from delivery)</li>
            <li>Return shipping costs are customer's responsibility</li>
            <li>Customs duties and taxes are non-refundable</li>
            <li>Refunds may take longer due to international processing</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Contact Us</h2>
        <p>For return and refund inquiries, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
