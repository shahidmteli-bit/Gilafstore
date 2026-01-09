<?php
/**
 * Shipping Policy Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Shipping Policy - Gilaf Store';
$metaDescription = 'Learn about our shipping methods, delivery times, and international shipping options.';

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
    
    .shipping-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .shipping-table th,
    .shipping-table td {
        padding: 15px;
        text-align: left;
        border: 1px solid #ddd;
    }
    
    .shipping-table th {
        background: #1A3C34;
        color: white;
        font-weight: 600;
    }
    
    .shipping-table tr:nth-child(even) {
        background: #f8f9fa;
    }
    
    @media (max-width: 768px) {
        .policy-hero h1 { font-size: 2.5rem; }
        .policy-section h2 { font-size: 1.6rem; }
        .policy-section h3 { font-size: 1.3rem; }
        .shipping-table { font-size: 0.9rem; }
        .shipping-table th,
        .shipping-table td { padding: 10px; }
    }
</style>

<!-- Hero Section -->
<div class="policy-hero">
    <h1>Shipping Policy</h1>
    <p>Fast, reliable delivery worldwide</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="policy-section">
        <h2>Domestic Shipping (India)</h2>
        <p>We offer free domestic shipping across India for all orders. Our trusted shipping partners ensure safe and timely delivery of your products.</p>
        
        <h3>Shipping Partners</h3>
        <ul>
            <li>India Post</li>
            <li>Delhivery</li>
            <li>Blue Dart</li>
            <li>DTDC</li>
        </ul>
        
        <h3>Delivery Timeframes</h3>
        <table class="shipping-table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Estimated Delivery</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Metro Cities (Delhi, Mumbai, Bangalore, etc.)</td>
                    <td>3-5 business days</td>
                </tr>
                <tr>
                    <td>Tier 2 Cities</td>
                    <td>5-7 business days</td>
                </tr>
                <tr>
                    <td>Remote Areas</td>
                    <td>7-10 business days</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="policy-section">
        <h2>International Shipping</h2>
        <p>We ship authentic Kashmiri products worldwide through our international shipping partners.</p>
        
        <h3>Shipping Partners</h3>
        <ul>
            <li>DHL Express</li>
            <li>FedEx</li>
            <li>DP World</li>
        </ul>
        
        <h3>International Delivery Timeframes</h3>
        <table class="shipping-table">
            <thead>
                <tr>
                    <th>Region</th>
                    <th>Estimated Delivery</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Middle East (UAE, Saudi Arabia, Qatar, etc.)</td>
                    <td>5-7 business days</td>
                </tr>
                <tr>
                    <td>Asia Pacific (Singapore, Australia, etc.)</td>
                    <td>7-10 business days</td>
                </tr>
                <tr>
                    <td>Europe (UK, Germany, France, etc.)</td>
                    <td>7-12 business days</td>
                </tr>
                <tr>
                    <td>North America (USA, Canada)</td>
                    <td>10-14 business days</td>
                </tr>
            </tbody>
        </table>
        
        <h3>International Shipping Costs</h3>
        <p>International shipping costs are calculated based on:</p>
        <ul>
            <li>Destination country</li>
            <li>Package weight and dimensions</li>
            <li>Selected shipping method</li>
            <li>Customs and import duties (customer responsibility)</li>
        </ul>
        <p>Shipping costs will be displayed at checkout before you complete your order.</p>
    </div>
    
    <div class="policy-section">
        <h2>Order Processing</h2>
        <p>Orders are processed within 1-2 business days after payment confirmation. You will receive:</p>
        <ul>
            <li>Order confirmation email immediately after purchase</li>
            <li>Shipping confirmation with tracking number once dispatched</li>
            <li>Delivery updates via email and SMS</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Order Tracking</h2>
        <p>Once your order is shipped, you will receive a tracking number via email. You can track your shipment:</p>
        <ul>
            <li>Through our website's tracking portal</li>
            <li>Directly on the courier's website</li>
            <li>By contacting our customer support team</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Delivery Address</h2>
        <p>Please ensure your shipping address is accurate and complete. We are not responsible for:</p>
        <ul>
            <li>Delays or non-delivery due to incorrect addresses</li>
            <li>Packages returned due to incomplete information</li>
            <li>Failed delivery attempts due to recipient unavailability</li>
        </ul>
        <p>Address changes after order placement may not be possible. Contact us immediately if you need to update your address.</p>
    </div>
    
    <div class="policy-section">
        <h2>Customs and Import Duties</h2>
        <p>For international orders:</p>
        <ul>
            <li>Customs duties and taxes are the responsibility of the customer</li>
            <li>Import regulations vary by country</li>
            <li>Delays may occur due to customs clearance</li>
            <li>We are not responsible for customs-related delays or fees</li>
        </ul>
        <p>Please check your country's import regulations before placing an order.</p>
    </div>
    
    <div class="policy-section">
        <h2>Shipping Restrictions</h2>
        <p>We may not be able to ship to certain locations due to:</p>
        <ul>
            <li>Legal or regulatory restrictions</li>
            <li>Courier service limitations</li>
            <li>Product-specific shipping restrictions</li>
        </ul>
        <p>If we cannot ship to your location, we will notify you and process a full refund.</p>
    </div>
    
    <div class="policy-section">
        <h2>Damaged or Lost Packages</h2>
        <p>If your package arrives damaged or is lost in transit:</p>
        <ul>
            <li>Contact us within 48 hours of delivery (for damaged packages)</li>
            <li>Provide photos of the damaged packaging and products</li>
            <li>We will investigate and arrange a replacement or refund</li>
            <li>Lost packages will be investigated with the courier</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Contact Us</h2>
        <p>For shipping-related questions or concerns, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
