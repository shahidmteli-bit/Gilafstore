<?php
/**
 * Our Values Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Our Values - Gilaf Store';
$metaDescription = 'Discover the core values and principles that guide Gilaf Store in delivering authentic cultural products and exceptional service.';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    /* Values Hero - Using same container style as About Us */
    .values-hero {
        background-color: var(--color-ivory, #FAF8F5);
        padding: 50px 20px 25px 20px;
        text-align: center;
    }
    .values-hero-content {
        background: #fff;
        border-radius: 12px;
        padding: 30px 40px;
        max-width: 1200px;
        margin: 0 auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(197, 160, 89, 0.2);
    }
    .values-hero h1 {
        font-size: 3.0rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1A3C34;
    }
    .values-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        color: #C9A961;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .values-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 20px 80px 20px;
    }
    
    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 40px;
        margin-top: 0;
    }
    
    .value-card {
        background: white;
        border-radius: 20px;
        padding: 50px 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.4s ease;
        text-align: center;
    }
    .value-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }
    
    .value-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 30px;
        background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: #C5A059;
    }
    
    .value-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1A3C34;
        margin-bottom: 20px;
    }
    
    .value-description {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .values-hero h1 { font-size: 2.5rem; }
        .values-grid { grid-template-columns: 1fr; }
    }
</style>

<!-- Hero Section -->
<div class="values-hero">
    <div class="values-hero-content">
        <h1>Our Core Values</h1>
        <p>The principles that guide everything we do at Gilaf Store</p>
    </div>
</div>

<!-- Values Section -->
<div class="values-container">
    <div class="values-grid">
        <div class="value-card">
            <div class="value-icon">
                <i class="fas fa-certificate"></i>
            </div>
            <h3 class="value-title">Quality First</h3>
            <p class="value-description">We ensure every product meets our high standards of quality and authenticity.</p>
        </div>
        
        <div class="value-card">
            <div class="value-icon">
                <i class="fas fa-globe"></i>
            </div>
            <h3 class="value-title">Cultural Respect</h3>
            <p class="value-description">We honor and celebrate the cultural heritage behind every product we offer.</p>
        </div>
        
        <div class="value-card">
            <div class="value-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="value-title">Customer Focus</h3>
            <p class="value-description">Your satisfaction is our priority. We provide exceptional service at every step.</p>
        </div>
        
        <div class="value-card">
            <div class="value-icon">
                <i class="fas fa-leaf"></i>
            </div>
            <h3 class="value-title">Sustainability</h3>
            <p class="value-description">We support sustainable practices and fair trade with our artisan partners.</p>
        </div>
        
        <div class="value-card">
            <div class="value-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <h3 class="value-title">Authentic Sourcing</h3>
            <p class="value-description">We work directly with local farmers and artisans, ensuring every product is sourced authentically from its place of origin.</p>
        </div>
        
        <div class="value-card">
            <div class="value-icon">
                <i class="fas fa-landmark"></i>
            </div>
            <h3 class="value-title">Heritage Preservation</h3>
            <p class="value-description">We preserve centuries-old traditions and recipes, keeping cultural heritage alive through every product we offer.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
