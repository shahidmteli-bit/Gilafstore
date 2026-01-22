<?php
/**
 * Gifting & Hampers Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Gifting & Hampers - Gilaf Store';
$metaDescription = 'Discover our premium gift hampers and gifting options. Perfect for any occasion with authentic Kashmiri products.';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    /* Gifting Hero - Using same container style as Our Values */
    .gifting-hero {
        background-color: var(--color-ivory, #FAF8F5);
        padding: 50px 20px 25px 20px;
        text-align: center;
    }
    .gifting-hero-content {
        background: #fff;
        border-radius: 12px;
        padding: 30px 40px;
        max-width: 1200px;
        margin: 0 auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(197, 160, 89, 0.2);
    }
    .gifting-hero h1 {
        font-size: 3.0rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1A3C34;
    }
    .gifting-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        color: #C9A961;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .gifting-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px 80px 20px;
    }
    
    .gifting-intro {
        text-align: center;
        max-width: 800px;
        margin: 0 auto 60px;
    }
    
    .gifting-intro h2 {
        font-size: 2.5rem;
        color: #1A3C34;
        margin-bottom: 20px;
        font-weight: 700;
    }
    
    .gifting-intro p {
        font-size: 1.1rem;
        line-height: 1.8;
        color: #555;
    }
    
    .hamper-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 60px;
    }
    
    .hamper-card {
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .hamper-card:hover {
        border-color: #C9A961;
        box-shadow: 0 8px 20px rgba(201, 169, 97, 0.2);
        transform: translateY(-5px);
    }
    
    .hamper-image {
        width: 100%;
        height: 250px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: #C9A961;
    }
    
    .hamper-content {
        padding: 25px;
    }
    
    .hamper-content h3 {
        font-size: 1.5rem;
        color: #1A3C34;
        margin-bottom: 12px;
        font-weight: 600;
    }
    
    .hamper-content p {
        font-size: 1.05rem;
        line-height: 1.7;
        color: #666;
        margin-bottom: 20px;
    }
    
    .hamper-features {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
    }
    
    .hamper-features li {
        padding: 8px 0;
        color: #555;
        font-size: 0.95rem;
    }
    
    .hamper-features li i {
        color: #C9A961;
        margin-right: 10px;
    }
    
    .hamper-cta {
        display: inline-block;
        background: #C9A961;
        color: white;
        padding: 12px 30px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .hamper-cta:hover {
        background: #1A3C34;
        color: white;
        transform: translateX(5px);
    }
    
    .why-choose {
        background: #f8f9fa;
        padding: 60px 20px;
        border-radius: 12px;
        margin-bottom: 60px;
    }
    
    .why-choose h2 {
        text-align: center;
        font-size: 2.2rem;
        color: #1A3C34;
        margin-bottom: 40px;
        font-weight: 700;
    }
    
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
    }
    
    .feature-item {
        text-align: center;
    }
    
    .feature-item i {
        font-size: 3rem;
        color: #C9A961;
        margin-bottom: 15px;
    }
    
    .feature-item h3 {
        font-size: 1.3rem;
        color: #1A3C34;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .feature-item p {
        font-size: 1rem;
        color: #666;
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .gifting-hero h1 { font-size: 2.5rem; }
        .gifting-intro h2 { font-size: 2rem; }
        .hamper-categories { grid-template-columns: 1fr; }
        .features-grid { grid-template-columns: 1fr; }
    }
</style>

<!-- Hero Section -->
<div class="gifting-hero">
    <div class="gifting-hero-content">
        <h1>Gifting & Hampers</h1>
        <p>Premium gift hampers curated with authentic Kashmiri products</p>
    </div>
</div>

<!-- Main Content -->
<div class="gifting-container">
    <div class="gifting-intro">
        <h2>Perfect Gifts for Every Occasion</h2>
        <p>Our carefully curated gift hampers bring together the finest Kashmiri products, beautifully packaged to create memorable gifting experiences. Whether it's for festivals, corporate events, or personal celebrations, we have the perfect hamper for you.</p>
    </div>
    
    <div class="hamper-categories">
        <div class="hamper-card">
            <div class="hamper-image">
                <i class="fas fa-gift"></i>
            </div>
            <div class="hamper-content">
                <h3>Premium Spice Hampers</h3>
                <p>A collection of our finest Kashmiri spices, perfect for food enthusiasts and home chefs.</p>
                <ul class="hamper-features">
                    <li><i class="fas fa-check"></i> Authentic Kashmiri saffron</li>
                    <li><i class="fas fa-check"></i> Premium dry fruits selection</li>
                    <li><i class="fas fa-check"></i> Traditional spice blends</li>
                    <li><i class="fas fa-check"></i> Elegant gift packaging</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="hamper-cta">Explore Collection <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="hamper-card">
            <div class="hamper-image">
                <i class="fas fa-mug-hot"></i>
            </div>
            <div class="hamper-content">
                <h3>Tea & Beverage Hampers</h3>
                <p>Exquisite tea collections featuring premium Kashmiri Kahwa and specialty blends.</p>
                <ul class="hamper-features">
                    <li><i class="fas fa-check"></i> Kashmiri Kahwa tea</li>
                    <li><i class="fas fa-check"></i> Specialty tea blends</li>
                    <li><i class="fas fa-check"></i> Traditional brewing accessories</li>
                    <li><i class="fas fa-check"></i> Premium gift box</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="hamper-cta">Explore Collection <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="hamper-card">
            <div class="hamper-image">
                <i class="fas fa-star"></i>
            </div>
            <div class="hamper-content">
                <h3>Corporate Gift Hampers</h3>
                <p>Professionally curated hampers ideal for corporate gifting and business relationships.</p>
                <ul class="hamper-features">
                    <li><i class="fas fa-check"></i> Customizable options</li>
                    <li><i class="fas fa-check"></i> Bulk order discounts</li>
                    <li><i class="fas fa-check"></i> Branded packaging available</li>
                    <li><i class="fas fa-check"></i> Worldwide delivery with options</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="hamper-cta">Explore Collection <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="hamper-card">
            <div class="hamper-image">
                <i class="fas fa-heart"></i>
            </div>
            <div class="hamper-content">
                <h3>Festival Special Hampers</h3>
                <p>Celebrate festivals with our specially curated hampers featuring traditional delicacies.</p>
                <ul class="hamper-features">
                    <li><i class="fas fa-check"></i> Festival-themed packaging</li>
                    <li><i class="fas fa-check"></i> Traditional sweets & snacks</li>
                    <li><i class="fas fa-check"></i> Premium dry fruits</li>
                    <li><i class="fas fa-check"></i> Greeting cards included</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="hamper-cta">Explore Collection <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="hamper-card">
            <div class="hamper-image">
                <i class="fas fa-snowflake"></i>
            </div>
            <div class="hamper-content">
                <h3>Winter Warmth Hamper</h3>
                <p>Cozy winter essentials featuring warming spices, premium teas, and traditional Kashmiri delicacies.</p>
                <ul class="hamper-features">
                    <li><i class="fas fa-check"></i> Kashmiri Kahwa & warming teas</li>
                    <li><i class="fas fa-check"></i> Saffron & winter spices</li>
                    <li><i class="fas fa-check"></i> Premium honey selection</li>
                    <li><i class="fas fa-check"></i> Festive winter packaging</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="hamper-cta">Explore Collection <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="hamper-card">
            <div class="hamper-image">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="hamper-content">
                <h3>Dry Fruits & Nut Hampers</h3>
                <p>Premium selection of handpicked dry fruits and nuts from Kashmir, perfect for health-conscious gifting.</p>
                <ul class="hamper-features">
                    <li><i class="fas fa-check"></i> Kashmiri almonds & walnuts</li>
                    <li><i class="fas fa-check"></i> Premium cashews & pistachios</li>
                    <li><i class="fas fa-check"></i> Dried fruits assortment</li>
                    <li><i class="fas fa-check"></i> Luxury gift packaging</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="hamper-cta">Explore Collection <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="why-choose">
        <h2>Why Choose Our Gift Hampers?</h2>
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-certificate"></i>
                <h3>100% Authentic</h3>
                <p>All products are sourced directly from Kashmir, ensuring authenticity and quality.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-box-open"></i>
                <h3>Premium Packaging</h3>
                <p>Elegant and eco-friendly packaging that makes your gift truly special.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-shipping-fast"></i>
                <h3>Worldwide Delivery with Options</h3>
                <p>We deliver globally with multiple shipping options, secure packaging, and timely delivery.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-edit"></i>
                <h3>Customization</h3>
                <p>Personalize your hampers with custom messages and product selections.</p>
            </div>
        </div>
    </div>
</div>

<?php 
// Display FAQ section for Gifting & Hampers
require_once __DIR__ . '/includes/faq_section.php';
display_faq_section('General Information', 'Gifting & Hampers FAQs', 8);

include __DIR__ . '/includes/new-footer.php'; 
?>
