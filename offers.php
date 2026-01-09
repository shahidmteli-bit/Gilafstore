<?php
/**
 * Offers & Deals Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Offers & Deals - Gilaf Store';
$metaDescription = 'Discover exclusive offers and deals on authentic Kashmiri products. Limited time discounts on premium spices, dry fruits, and more.';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    .offers-hero {
        background: linear-gradient(135deg, #C9A961 0%, #D4B76A 20%, #1A3C34 60%, #244A36 100%);
        color: white;
        padding: 120px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .offers-hero::before {
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
    .offers-hero::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #C9A961 0%, #FFFFFF 50%, #C9A961 100%);
        z-index: 2;
    }
    .offers-hero h1 {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        font-family: 'Poppins', sans-serif;
    }
    .offers-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
        position: relative;
        z-index: 1;
        font-family: 'Poppins', sans-serif;
    }
    
    .offers-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 80px 20px;
    }
    
    .offers-intro {
        text-align: center;
        max-width: 800px;
        margin: 0 auto 60px;
    }
    
    .offers-intro h2 {
        font-size: 2.5rem;
        color: #1A3C34;
        margin-bottom: 20px;
        font-weight: 700;
    }
    
    .offers-intro p {
        font-size: 1.1rem;
        line-height: 1.8;
        color: #555;
    }
    
    .offer-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 60px;
    }
    
    .offer-card {
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .offer-card:hover {
        border-color: #C9A961;
        box-shadow: 0 8px 20px rgba(201, 169, 97, 0.2);
        transform: translateY(-5px);
    }
    
    .offer-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #dc3545;
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        z-index: 1;
    }
    
    .offer-image {
        width: 100%;
        height: 250px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: #C9A961;
    }
    
    .offer-content {
        padding: 25px;
    }
    
    .offer-content h3 {
        font-size: 1.5rem;
        color: #1A3C34;
        margin-bottom: 12px;
        font-weight: 600;
    }
    
    .offer-content p {
        font-size: 1.05rem;
        line-height: 1.7;
        color: #666;
        margin-bottom: 20px;
    }
    
    .offer-details {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
    }
    
    .offer-details li {
        padding: 8px 0;
        color: #555;
        font-size: 0.95rem;
    }
    
    .offer-details li i {
        color: #C9A961;
        margin-right: 10px;
    }
    
    .offer-cta {
        display: inline-block;
        background: #C9A961;
        color: white;
        padding: 12px 30px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .offer-cta:hover {
        background: #1A3C34;
        color: white;
        transform: translateX(5px);
    }
    
    .promo-banner {
        background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%);
        color: white;
        padding: 40px;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 60px;
    }
    
    .promo-banner h2 {
        font-size: 2rem;
        margin-bottom: 15px;
        font-weight: 700;
    }
    
    .promo-banner p {
        font-size: 1.2rem;
        margin-bottom: 20px;
        opacity: 0.95;
    }
    
    .promo-code {
        display: inline-block;
        background: white;
        color: #1A3C34;
        padding: 15px 30px;
        border-radius: 8px;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 2px;
        margin-top: 10px;
    }
    
    .benefits-section {
        background: #f8f9fa;
        padding: 60px 20px;
        border-radius: 12px;
        margin-bottom: 60px;
    }
    
    .benefits-section h2 {
        text-align: center;
        font-size: 2.2rem;
        color: #1A3C34;
        margin-bottom: 40px;
        font-weight: 700;
    }
    
    .benefits-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
    }
    
    .benefit-item {
        text-align: center;
    }
    
    .benefit-item i {
        font-size: 3rem;
        color: #C9A961;
        margin-bottom: 15px;
    }
    
    .benefit-item h3 {
        font-size: 1.3rem;
        color: #1A3C34;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .benefit-item p {
        font-size: 1rem;
        color: #666;
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .offers-hero h1 { font-size: 2.5rem; }
        .offers-intro h2 { font-size: 2rem; }
        .offer-categories { grid-template-columns: 1fr; }
        .benefits-grid { grid-template-columns: 1fr; }
        .promo-banner h2 { font-size: 1.5rem; }
        .promo-code { font-size: 1.2rem; padding: 12px 20px; }
    }
</style>

<!-- Hero Section -->
<div class="offers-hero">
    <h1>Exclusive Offers & Deals</h1>
    <p>Save big on authentic Kashmiri products with our limited-time offers</p>
</div>

<!-- Main Content -->
<div class="offers-container">
    <div class="offers-intro">
        <h2>Current Offers & Promotions</h2>
        <p>Discover amazing deals on our premium products. From seasonal discounts to bundle offers, we bring you the best value on authentic Kashmiri goods.</p>
    </div>
    
    <div class="promo-banner">
        <h2>🎉 Welcome Offer - New Customers</h2>
        <p>Get 10% OFF on your first order!</p>
        <div class="promo-code">WELCOME10</div>
        <p style="font-size: 0.9rem; margin-top: 15px; opacity: 0.8;">Use code at checkout | Valid on orders above ₹500</p>
    </div>
    
    <div class="offer-categories">
        <div class="offer-card">
            <div class="offer-badge">UP TO 50% OFF</div>
            <div class="offer-image">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="offer-content">
                <h3>Seasonal Sale</h3>
                <p>Enjoy massive discounts on selected products during our seasonal sale event.</p>
                <ul class="offer-details">
                    <li><i class="fas fa-check"></i> Up to 50% off on premium spices</li>
                    <li><i class="fas fa-check"></i> Special prices on dry fruits</li>
                    <li><i class="fas fa-check"></i> Limited time only</li>
                    <li><i class="fas fa-check"></i> Free shipping on orders above ₹999</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="offer-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="offer-card">
            <div class="offer-badge">BUY 2 GET 1</div>
            <div class="offer-image">
                <i class="fas fa-tags"></i>
            </div>
            <div class="offer-content">
                <h3>Bundle Offers</h3>
                <p>Mix and match your favorite products and save more with our bundle deals.</p>
                <ul class="offer-details">
                    <li><i class="fas fa-check"></i> Buy 2 Get 1 Free on select items</li>
                    <li><i class="fas fa-check"></i> Combo packs at special prices</li>
                    <li><i class="fas fa-check"></i> Save up to 30% on bundles</li>
                    <li><i class="fas fa-check"></i> Perfect for gifting</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="offer-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="offer-card">
            <div class="offer-badge">FREE SHIPPING</div>
            <div class="offer-image">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="offer-content">
                <h3>Free Delivery</h3>
                <p>Get free nationwide shipping on all orders above a minimum value.</p>
                <ul class="offer-details">
                    <li><i class="fas fa-check"></i> Free shipping on orders ₹999+</li>
                    <li><i class="fas fa-check"></i> Express delivery available</li>
                    <li><i class="fas fa-check"></i> Track your order in real-time</li>
                    <li><i class="fas fa-check"></i> Secure packaging guaranteed</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="offer-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="offer-card">
            <div class="offer-badge">LOYALTY REWARDS</div>
            <div class="offer-image">
                <i class="fas fa-gift"></i>
            </div>
            <div class="offer-content">
                <h3>Loyalty Program</h3>
                <p>Earn points on every purchase and redeem them for exclusive discounts.</p>
                <ul class="offer-details">
                    <li><i class="fas fa-check"></i> Earn 1 point per ₹10 spent</li>
                    <li><i class="fas fa-check"></i> Redeem points for discounts</li>
                    <li><i class="fas fa-check"></i> Exclusive member-only deals</li>
                    <li><i class="fas fa-check"></i> Birthday special offers</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="offer-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="offer-card">
            <div class="offer-badge">REFERRAL BONUS</div>
            <div class="offer-image">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="offer-content">
                <h3>Idea Sharing Reward</h3>
                <p>Share your ideas and feedback with us to earn exclusive rewards and discounts.</p>
                <ul class="offer-details">
                    <li><i class="fas fa-check"></i> Get rewarded for valuable feedback</li>
                    <li><i class="fas fa-check"></i> Earn bonus points for suggestions</li>
                    <li><i class="fas fa-check"></i> Special recognition for contributors</li>
                    <li><i class="fas fa-check"></i> Help us improve & get rewarded</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="offer-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <div class="offer-card">
            <div class="offer-badge">NEW CUSTOMER</div>
            <div class="offer-image">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="offer-content">
                <h3>Welcome Bonus</h3>
                <p>Special welcome bonus for new customers on their first purchase with us.</p>
                <ul class="offer-details">
                    <li><i class="fas fa-check"></i> Extra 15% off on first order</li>
                    <li><i class="fas fa-check"></i> Free gift with orders above ₹1500</li>
                    <li><i class="fas fa-check"></i> Priority customer support</li>
                    <li><i class="fas fa-check"></i> Join our loyalty program instantly</li>
                </ul>
                <a href="<?= base_url('shop.php'); ?>" class="offer-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="benefits-section">
        <h2>Why Shop Our Offers?</h2>
        <div class="benefits-grid">
            <div class="benefit-item">
                <i class="fas fa-certificate"></i>
                <h3>Authentic Products</h3>
                <p>All discounted products maintain the same premium quality and authenticity.</p>
            </div>
            <div class="benefit-item">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure Shopping</h3>
                <p>Safe and secure payment options with buyer protection guarantee.</p>
            </div>
            <div class="benefit-item">
                <i class="fas fa-undo"></i>
                <h3>Easy Returns</h3>
                <p>7-day return policy applies to all offer products with no questions asked.</p>
            </div>
            <div class="benefit-item">
                <i class="fas fa-headset"></i>
                <h3>24/7 Support</h3>
                <p>Our customer support team is always ready to assist you with any queries.</p>
            </div>
        </div>
    </div>
</div>

<?php 
// Display FAQ section for Offers
require_once __DIR__ . '/includes/faq_section.php';
display_faq_section('General Information', 'Offers & Deals FAQs', 8);

include __DIR__ . '/includes/new-footer.php'; 
?>
