<?php
/**
 * Disclaimer Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Disclaimer - Gilaf Store';
$metaDescription = 'Read our disclaimer regarding product information, website content, and liability limitations.';

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
    
    .warning-box {
        background: #fff9e6;
        border: 2px solid #ffc107;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .warning-box h3 {
        color: #856404;
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
    <h1>Disclaimer</h1>
    <p>Important information about our products and services</p>
</div>

<!-- Policy Content -->
<div class="policy-container">
    <div class="last-updated">
        <strong>Last Updated:</strong> January 9, 2026
    </div>
    
    <div class="policy-section">
        <h2>General Disclaimer</h2>
        <p>The information provided by Gilaf Foods & Spices ("we," "us," or "our") on our website and through our services is for general informational purposes only. All information is provided in good faith, however we make no representation or warranty of any kind, express or implied, regarding the accuracy, adequacy, validity, reliability, availability, or completeness of any information on the website.</p>
        <p>Under no circumstance shall we have any liability to you for any loss or damage of any kind incurred as a result of the use of the website or reliance on any information provided on the website. Your use of the website and your reliance on any information on the website is solely at your own risk.</p>
    </div>
    
    <div class="policy-section">
        <h2>Product Information</h2>
        
        <h3>Product Descriptions</h3>
        <p>We strive to provide accurate product descriptions, images, and specifications. However:</p>
        <ul>
            <li>Product images are for illustration purposes and may differ from actual products</li>
            <li>Colors may vary due to screen settings and photography</li>
            <li>Product packaging may change without notice</li>
            <li>Weights and measurements are approximate</li>
            <li>Ingredients and nutritional information may be updated by manufacturers</li>
        </ul>
        
        <h3>Product Availability</h3>
        <p>Product availability is subject to change without notice. We reserve the right to:</p>
        <ul>
            <li>Discontinue products at any time</li>
            <li>Limit quantities available for purchase</li>
            <li>Refuse orders for out-of-stock items</li>
            <li>Substitute similar products when necessary</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Health and Dietary Information</h2>
        
        <div class="warning-box">
            <h3>⚠ Important Health Notice</h3>
            <p>The products sold on this website are food items and are not intended to diagnose, treat, cure, or prevent any disease. Always consult with a healthcare professional before making dietary changes or if you have specific health concerns.</p>
        </div>
        
        <h3>Allergen Information</h3>
        <p>While we provide allergen information where available:</p>
        <ul>
            <li>Information is based on manufacturer data</li>
            <li>Products may be processed in facilities that handle allergens</li>
            <li>Cross-contamination may occur during processing or packaging</li>
            <li>We cannot guarantee products are free from specific allergens</li>
            <li>Always check product labels before consumption</li>
        </ul>
        
        <h3>Dietary Claims</h3>
        <p>Any dietary claims (organic, gluten-free, vegan, etc.) are based on:</p>
        <ul>
            <li>Information provided by manufacturers</li>
            <li>Product certifications and labels</li>
            <li>Industry standards and definitions</li>
        </ul>
        <p>We recommend verifying dietary suitability before purchase if you have specific requirements.</p>
    </div>
    
    <div class="policy-section">
        <h2>Website Content</h2>
        
        <h3>Accuracy of Information</h3>
        <p>We make every effort to ensure information on our website is accurate and up-to-date. However:</p>
        <ul>
            <li>Information may contain errors or omissions</li>
            <li>Content may be outdated or incomplete</li>
            <li>We do not guarantee accuracy of third-party information</li>
            <li>Prices and availability are subject to change</li>
        </ul>
        
        <h3>External Links</h3>
        <p>Our website may contain links to external websites. We are not responsible for:</p>
        <ul>
            <li>Content on third-party websites</li>
            <li>Privacy practices of external sites</li>
            <li>Accuracy of information on linked sites</li>
            <li>Products or services offered by third parties</li>
        </ul>
        <p>Visiting external links is at your own risk.</p>
    </div>
    
    <div class="policy-section">
        <h2>Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, Gilaf Foods & Spices shall not be liable for:</p>
        <ul>
            <li>Any direct, indirect, incidental, or consequential damages</li>
            <li>Loss of profits, revenue, or business opportunities</li>
            <li>Damage to property or personal injury</li>
            <li>Data loss or corruption</li>
            <li>Errors or interruptions in website service</li>
            <li>Unauthorized access to your account or data</li>
            <li>Actions of third-party service providers</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Professional Advice</h2>
        <p>Information on our website is not a substitute for professional advice:</p>
        <ul>
            <li><strong>Medical Advice:</strong> Consult healthcare professionals for medical concerns</li>
            <li><strong>Nutritional Advice:</strong> Seek guidance from qualified nutritionists</li>
            <li><strong>Legal Advice:</strong> Consult legal professionals for legal matters</li>
            <li><strong>Financial Advice:</strong> Seek professional financial guidance</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Product Quality and Safety</h2>
        
        <h3>Quality Assurance</h3>
        <p>While we implement quality control measures:</p>
        <ul>
            <li>We cannot guarantee products are free from defects</li>
            <li>Natural variations may occur in food products</li>
            <li>Shelf life depends on storage conditions</li>
            <li>Follow storage instructions on product labels</li>
        </ul>
        
        <h3>Food Safety</h3>
        <p>To ensure food safety:</p>
        <ul>
            <li>Check expiration dates before consumption</li>
            <li>Store products according to label instructions</li>
            <li>Inspect products for signs of damage or spoilage</li>
            <li>Report any quality concerns immediately</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Pricing and Promotions</h2>
        <p>Regarding pricing and promotional offers:</p>
        <ul>
            <li>Prices are subject to change without notice</li>
            <li>Pricing errors may occur and will be corrected</li>
            <li>Promotional offers are valid for limited periods</li>
            <li>Discounts cannot be combined unless stated</li>
            <li>We reserve the right to cancel orders with pricing errors</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Testimonials and Reviews</h2>
        <p>Customer testimonials and reviews on our website:</p>
        <ul>
            <li>Reflect individual experiences and opinions</li>
            <li>May not be representative of all customers</li>
            <li>Are not verified for accuracy</li>
            <li>Do not constitute professional endorsements</li>
            <li>Results may vary for different individuals</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Intellectual Property</h2>
        <p>All content on this website is protected by intellectual property laws:</p>
        <ul>
            <li>Trademarks belong to their respective owners</li>
            <li>Product images may be owned by manufacturers</li>
            <li>Website content is owned by Gilaf Foods & Spices</li>
            <li>Unauthorized use is prohibited</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Technical Issues</h2>
        <p>We strive to maintain website availability, but:</p>
        <ul>
            <li>Technical issues may cause interruptions</li>
            <li>Maintenance may require temporary downtime</li>
            <li>We are not liable for service disruptions</li>
            <li>Data transmission is not guaranteed to be secure</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>International Customers</h2>
        <p>For customers outside India:</p>
        <ul>
            <li>Products may be subject to import restrictions</li>
            <li>Customs duties and taxes are customer's responsibility</li>
            <li>Delivery times may vary by location</li>
            <li>Local laws and regulations apply</li>
        </ul>
    </div>
    
    <div class="policy-section">
        <h2>Changes to Disclaimer</h2>
        <p>We reserve the right to modify this disclaimer at any time. Changes will be effective immediately upon posting on this page. Your continued use of the website after changes constitutes acceptance of the modified disclaimer.</p>
    </div>
    
    <div class="policy-section">
        <h2>Governing Law</h2>
        <p>This disclaimer is governed by the laws of India. Any disputes arising from this disclaimer shall be subject to the exclusive jurisdiction of the courts in Srinagar, Kashmir, India.</p>
    </div>
    
    <div class="policy-section">
        <h2>Contact Information</h2>
        <p>If you have questions about this disclaimer, please contact us:</p>
        <p>
            <strong>Gilaf Foods & Spices</strong><br>
            Email: <a href="mailto:gilaf.help@gmail.com" style="color: #C9A961;">gilaf.help@gmail.com</a><br>
            Address: Gilaf Foods, Sopore, Baramulla, Jammu & Kashmir – 193201, India
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
