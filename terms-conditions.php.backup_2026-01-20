<?php
/**
 * Terms & Conditions Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Terms & Conditions - Gilaf Store';
$metaDescription = 'Read the terms and conditions for using Gilaf Store and purchasing our products.';

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
    
    @media (max-width: 768px) {
        .policy-hero h1 { font-size: 2.5rem; }
        .policy-section h2 { font-size: 1.6rem; }
        .policy-section h3 { font-size: 1.3rem; }
    }
</style>

<!-- Hero Section -->
<div class="policy-hero">
    <h1>Terms & Conditions</h1>
    <p>Please read these terms carefully before using our services</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="policy-section">
        <h2>Agreement to Terms</h2>
        <p>By accessing and using the Gilaf Store website, you accept and agree to be bound by these Terms and Conditions. If you do not agree to these terms, please do not use our website or services.</p>
    </div>
    
    <div class="policy-section">
        <h2>Use of Website</h2>
        <p>You agree to use our website only for lawful purposes and in accordance with these Terms. You must not:</p>
        <ul>
            <li>Use the website in any way that violates applicable laws or regulations</li>
            <li>Engage in any conduct that restricts or inhibits anyone's use of the website</li>
            <li>Attempt to gain unauthorized access to any portion of the website</li>
            <li>Use any automated system to access the website without our permission</li>
            <li>Transmit any viruses, malware, or harmful code</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Account Registration</h2>
        <p>To access certain features of our website, you may be required to create an account. You agree to:</p>
        <ul>
            <li>Provide accurate, current, and complete information</li>
            <li>Maintain and update your information as necessary</li>
            <li>Keep your password confidential and secure</li>
            <li>Notify us immediately of any unauthorized use of your account</li>
            <li>Accept responsibility for all activities under your account</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Product Information and Pricing</h2>
        <p>We strive to provide accurate product descriptions and pricing. However:</p>
        <ul>
            <li>Product images are for illustration purposes and may vary from actual products</li>
            <li>Prices are subject to change without notice</li>
            <li>We reserve the right to correct pricing errors</li>
            <li>Product availability is not guaranteed</li>
            <li>We may limit quantities available for purchase</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Orders and Payment</h2>
        <p>When you place an order with us:</p>
        <ul>
            <li>Your order constitutes an offer to purchase products</li>
            <li>We reserve the right to accept or decline any order</li>
            <li>Payment must be received before order processing</li>
            <li>All prices are in Indian Rupees (INR) unless otherwise stated</li>
            <li>You are responsible for all applicable taxes and duties</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Shipping and Delivery</h2>
        <p>We ship products to addresses provided by customers. Please refer to our Shipping Policy for detailed information about:</p>
        <ul>
            <li>Shipping methods and timeframes</li>
            <li>Shipping costs and free shipping eligibility</li>
            <li>International shipping options</li>
            <li>Delivery tracking and confirmation</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Returns and Refunds</h2>
        <p>Our return and refund policy is designed to ensure customer satisfaction. Please refer to our Refund & Return Policy for complete details on:</p>
        <ul>
            <li>Eligible products and return timeframes</li>
            <li>Return process and requirements</li>
            <li>Refund processing and timelines</li>
            <li>Non-returnable items</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Intellectual Property</h2>
        <p>All content on this website, including text, graphics, logos, images, and software, is the property of Gilaf Foods & Spices and is protected by copyright and intellectual property laws. You may not:</p>
        <ul>
            <li>Reproduce, distribute, or modify any content without permission</li>
            <li>Use our trademarks or branding without authorization</li>
            <li>Create derivative works from our content</li>
            <li>Remove or alter any copyright notices</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, Gilaf Foods & Spices shall not be liable for:</p>
        <ul>
            <li>Indirect, incidental, or consequential damages</li>
            <li>Loss of profits, data, or business opportunities</li>
            <li>Damages arising from use or inability to use our website</li>
            <li>Errors or omissions in website content</li>
            <li>Unauthorized access to your data or account</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Indemnification</h2>
        <p>You agree to indemnify and hold harmless Gilaf Foods & Spices from any claims, damages, losses, or expenses arising from:</p>
        <ul>
            <li>Your violation of these Terms and Conditions</li>
            <li>Your use of the website or services</li>
            <li>Your violation of any third-party rights</li>
            <li>Any content you submit or transmit through the website</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Governing Law</h2>
        <p>These Terms and Conditions are governed by the laws of India. Any disputes arising from these terms shall be subject to the exclusive jurisdiction of the courts in Srinagar, Kashmir, India.</p>
    </div>
    
    <div class="policy-section">
        <h2>Changes to Terms</h2>
        <p>We reserve the right to modify these Terms and Conditions at any time. Changes will be effective immediately upon posting. Your continued use of the website after changes constitutes acceptance of the modified terms.</p>
    </div>
    
    <div class="policy-section">
        <h2>Contact Information</h2>
        <p>If you have questions about these Terms and Conditions, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
