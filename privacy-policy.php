<?php
/**
 * Privacy Policy Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Privacy Policy - Gilaf Store';
$metaDescription = 'Read our privacy policy to understand how Gilaf Store collects, uses, and protects your personal information.';

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
    <h1>Privacy Policy</h1>
    <p>Your privacy is important to us</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="policy-section">
        <h2>Introduction</h2>
        <p>Gilaf Foods & Spices ("we," "us," or "our") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or make a purchase from us.</p>
        <p>Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access the site.</p>
    </div>
    
    <div class="policy-section">
        <h2>Information We Collect</h2>
        
        <h3>Personal Information</h3>
        <p>We may collect personal information that you voluntarily provide to us when you:</p>
        <ul>
            <li>Register on the website</li>
            <li>Place an order</li>
            <li>Subscribe to our newsletter</li>
            <li>Contact us via email or phone</li>
            <li>Participate in surveys or promotions</li>
        </ul>
        <p>This information may include:</p>
        <ul>
            <li>Name and contact information (email address, phone number, shipping address)</li>
            <li>Payment information (credit card details, billing address)</li>
            <li>Order history and preferences</li>
            <li>Account credentials (username, password)</li>
        </ul>
        
        <h3>Automatically Collected Information</h3>
        <p>When you visit our website, we may automatically collect certain information about your device, including:</p>
        <ul>
            <li>IP address and browser type</li>
            <li>Operating system and device information</li>
            <li>Pages visited and time spent on pages</li>
            <li>Referring website addresses</li>
            <li>Cookies and similar tracking technologies</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>How We Use Your Information</h2>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Process and fulfill your orders</li>
            <li>Communicate with you about your orders and account</li>
            <li>Send you marketing communications (with your consent)</li>
            <li>Improve our website and customer service</li>
            <li>Detect and prevent fraud or security issues</li>
            <li>Comply with legal obligations</li>
            <li>Analyze website usage and trends</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Information Sharing and Disclosure</h2>
        <p>We do not sell, trade, or rent your personal information to third parties. We may share your information with:</p>
        <ul>
            <li><strong>Service Providers:</strong> Third-party companies that help us operate our business (payment processors, shipping companies, email service providers)</li>
            <li><strong>Legal Requirements:</strong> When required by law or to protect our rights and safety</li>
            <li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Data Security</h2>
        <p>We implement appropriate technical and organizational security measures to protect your personal information from unauthorized access, disclosure, alteration, or destruction. However, no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.</p>
    </div>
    
    <div class="policy-section">
        <h2>Your Rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Access and receive a copy of your personal information</li>
            <li>Correct inaccurate or incomplete information</li>
            <li>Request deletion of your personal information</li>
            <li>Opt-out of marketing communications</li>
            <li>Withdraw consent for data processing</li>
        </ul>
        <p>To exercise these rights, please contact us at <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a></p>
    </div>
    
    <div class="policy-section">
        <h2>Cookies and Tracking Technologies</h2>
        <p>We use cookies and similar tracking technologies to enhance your browsing experience, analyze website traffic, and personalize content. You can control cookies through your browser settings, but disabling cookies may affect website functionality.</p>
    </div>
    
    <div class="policy-section">
        <h2>Third-Party Links</h2>
        <p>Our website may contain links to third-party websites. We are not responsible for the privacy practices of these external sites. We encourage you to review their privacy policies before providing any personal information.</p>
    </div>
    
    <div class="policy-section">
        <h2>Children's Privacy</h2>
        <p>Our website is not intended for children under the age of 13. We do not knowingly collect personal information from children. If you believe we have collected information from a child, please contact us immediately.</p>
    </div>
    
    <div class="policy-section">
        <h2>Changes to This Privacy Policy</h2>
        <p>We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated "Last Updated" date. We encourage you to review this policy periodically.</p>
    </div>
    
    <div class="policy-section">
        <h2>Contact Us</h2>
        <p>If you have any questions or concerns about this Privacy Policy, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
