<?php
$pageTitle = 'Gilaf Store | Taste • Culture • Craft';
$activePage = 'home';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';

// Get user's region settings for currency conversion
$userRegion = get_user_region_settings();
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

$trendingProducts = get_trending_products(4);
$trendingProducts = enrich_products_with_discounts($trendingProducts);
include __DIR__ . '/includes/new-header.php';
?>

<!-- DEPLOYMENT TEST BANNER - WILL BE REMOVED -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; font-size: 18px; font-weight: bold; border-bottom: 5px solid #ffd700; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; z-index: 9999;">
    🚀 DEPLOYMENT TEST - Timestamp: <?= date('Y-m-d H:i:s'); ?> - Commit: 9a4c34b - If you see this, GitHub sync is working! 🚀
</div>

<!-- Hero Section -->
<section class="hero">
    <div class="container hero-content">
        <div class="tagline-pill">
            <i class="fas fa-crown" style="color: var(--color-gold); font-size: 0.8rem;"></i>
            <span>Premium Heritage Foods</span>
        </div>
        <h2>The Essence of <br><span style="color: var(--color-gold); font-style: italic;">Purity & Tradition</span></h2>
        <p>Experience the finest saffron, unadulterated honey, and hand-selected spices from the valleys of Kashmir. Curated by Gilaf Foods.</p>
        <div class="hero-buttons">
            <a href="<?= base_url('shop.php'); ?>" class="btn btn-primary">Shop Collection</a>
            <a href="#verification" class="btn btn-outline">Verify My Product</a>
        </div>
    </div>
</section>

<!-- Marquee Trust Bar -->
<section class="features-marquee" style="padding: 11px 0 !important;">
    <div class="marquee-track">
        <div class="marquee-item">
            <i class="fas fa-leaf"></i> 
            <div class="marquee-text-col">
                <span class="marquee-title">100% Organic</span>
                <span class="marquee-tagline">Sourced directly from certified farms.</span>
            </div>
        </div>

        <div class="marquee-item">
            <i class="fas fa-check-double"></i> 
            <div class="marquee-text-col">
                <span class="marquee-title">Batch Verified</span>
                <span class="marquee-tagline">Every bottle comes with a lab report.</span>
            </div>
        </div>

        <div class="marquee-item">
            <i class="fas fa-mountain"></i> 
            <div class="marquee-text-col">
                <span class="marquee-title">Kashmiri Origin</span>
                <span class="marquee-tagline">Authentic heritage from the valley.</span>
            </div>
        </div>

        <div class="marquee-item">
            <i class="fas fa-truck-fast"></i> 
            <div class="marquee-text-col">
                <span class="marquee-title">Secure Shipping</span>
                <span class="marquee-tagline">Pan-India delivery with care.</span>
            </div>
        </div>

        <!-- Duplicates for infinite loop -->
        <div class="marquee-item"><i class="fas fa-leaf"></i><div class="marquee-text-col"><span class="marquee-title">100% Organic</span><span class="marquee-tagline">Sourced directly from certified farms.</span></div></div>
        <div class="marquee-item"><i class="fas fa-check-double"></i><div class="marquee-text-col"><span class="marquee-title">Batch Verified</span><span class="marquee-tagline">Every bottle comes with a lab report.</span></div></div>
        <div class="marquee-item"><i class="fas fa-mountain"></i><div class="marquee-text-col"><span class="marquee-title">Kashmiri Origin</span><span class="marquee-tagline">Authentic heritage from the valley.</span></div></div>
        <div class="marquee-item"><i class="fas fa-truck-fast"></i><div class="marquee-text-col"><span class="marquee-title">Secure Shipping</span><span class="marquee-tagline">Pan-India delivery with care.</span></div></div>
        
        <div class="marquee-item"><i class="fas fa-leaf"></i><div class="marquee-text-col"><span class="marquee-title">100% Organic</span><span class="marquee-tagline">Sourced directly from certified farms.</span></div></div>
        <div class="marquee-item"><i class="fas fa-check-double"></i><div class="marquee-text-col"><span class="marquee-title">Batch Verified</span><span class="marquee-tagline">Every bottle comes with a lab report.</span></div></div>
        <div class="marquee-item"><i class="fas fa-mountain"></i><div class="marquee-text-col"><span class="marquee-title">Kashmiri Origin</span><span class="marquee-tagline">Authentic heritage from the valley.</span></div></div>
        <div class="marquee-item"><i class="fas fa-truck-fast"></i><div class="marquee-text-col"><span class="marquee-title">Secure Shipping</span><span class="marquee-tagline">Pan-India delivery with care.</span></div></div>
    </div>
</section>

<!-- Best Sellers Showcase -->
<section class="products-section" id="products">
    <div class="container">
        <h2 class="section-title">Our Best Sellers</h2>
        <p class="section-subtitle">Curated for the Connoisseur</p>
        <div class="product-grid">
            <?php foreach ($trendingProducts as $product): ?>
                <article class="product-card" style="cursor: pointer;" onclick="window.location.href='<?= base_url('product.php?id=' . $product['id']); ?>'">
                    <div class="badge-container">
                        <?php if (isset($product['popularity']) && $product['popularity'] > 80): ?>
                            <div class="badge green">Bestseller</div>
                        <?php endif; ?>
                        <?php if (!empty($product['has_discount'])): ?>
                            <div class="badge discount-badge">
                                <i class="fas fa-tag"></i> <?= round($product['discount_percentage']); ?>% OFF
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-image-wrapper" onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= base_url('product.php?id=' . $product['id']); ?>'" style="cursor: pointer;">
                        <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                        <div class="trust-overlay">
                            <i class="fas fa-award" style="color: var(--color-green);"></i> 
                            <i class="fas fa-flask" style="color: var(--color-green);"></i>
                        </div>
                    </div>
                    <div class="product-details">
                        <span class="product-cat"><?= htmlspecialchars($product['category_name'] ?? 'Premium'); ?></span>
                        <h3 class="product-title" onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= base_url('product.php?id=' . $product['id']); ?>'" style="cursor: pointer;">
                            <?= htmlspecialchars($product['name']); ?>
                        </h3>
                        <span class="product-origin">Origin: Kashmir Valley</span>
                        <div class="price-row">
                            <?php 
                                $priceToConvert = !empty($product['has_discount']) ? $product['discounted_price'] : $product['price'];
                                $convertedPrice = display_price($priceToConvert, $currentCurrency, $currentCurrencySymbol);
                            ?>
                            <?php if (!empty($product['has_discount'])): ?>
                                <span class="product-price-original"><?= display_price($product['original_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                                <span class="product-price"><?= $convertedPrice; ?></span>
                            <?php else: ?>
                                <span class="product-price"><?= $convertedPrice; ?></span>
                            <?php endif; ?>
                        </div>
                        <form action="<?= base_url('includes/cart.php'); ?>" method="post" onclick="event.stopPropagation();">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="add-btn" onclick="event.stopPropagation();">Add to Cart</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Brand Story -->
<section class="story-section" id="story">
    <div class="story-visual"></div>
    <div class="story-content">
        <span style="color: var(--color-gold); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px;">Our Philosophy</span>
        <h2>Preserving the <br>Art of Taste</h2>
        <p>At <strong>Gilaf Foods & Spices</strong>, we believe that food is not just sustenance—it is memory. Founded by Shahid Mohammad & Muneera Shahid, our mission is to bring the unadulterated taste of Kashmir to your table.</p>
        <p>We work directly with local farmers, ensuring that every strand of saffron and every drop of honey retains the purity of the mountains.</p>
        <div class="founder-sig">Shahid & Muneera</div>
    </div>
</section>

<!-- Store Locator -->
<section class="store-locator-section" id="locator">
    <div class="container locator-container">
        <h2 class="section-title">Find a Store</h2>
        <p class="section-subtitle">Locate Gilaf Stores & Distributors Near You</p>
        <div class="search-box-wrapper">
            <div class="input-group">
                <input type="text" id="pincodeInput" class="verify-input" placeholder="Enter Pincode (e.g., 110001)">
                <button class="verify-btn" onclick="findStores()">Search</button>
                <button class="clear-btn" onclick="clearLocator()"><i class="fas fa-times-circle"></i> Clear</button>
            </div>
            <div id="locator-error" style="color: #d9534f; margin-top: 10px; font-size: 0.9rem;"></div>
        </div>
        <div id="store-results-container"></div>
    </div>
</section>

<!-- Verification Section -->
<section class="verification" id="verification">
    <div class="container">
        <h2 class="section-title">Trace Your Product</h2>
        <p class="section-subtitle">Transparency from Farm to Kitchen</p>
        <div class="verify-box">
            <div class="verify-icon"><i class="fas fa-shield-alt"></i></div>
            <h3 style="font-size: 1.5rem; color: var(--color-green);">Authenticity Check</h3>
            <p style="margin: 15px 0 25px; color: #666;">Enter the Batch Number found on your product lid to view complete product details.</p>
            <form onsubmit="verifyBatch(event)">
                <div class="input-group">
                    <input type="text" id="batchInput" class="verify-input" placeholder="Enter Batch ID (e.g., GF-2025-01)">
                    <button type="submit" class="verify-btn">Verify Now</button>
                    <button type="button" class="clear-btn" onclick="clearBatchField()"><i class="fas fa-times-circle"></i> Clear</button>
                </div>
            </form>
            <script>
            function clearBatchField() {
                const input = document.getElementById('batchInput');
                const resultDiv = document.getElementById('verification-result');
                input.value = '';
                input.focus();
                if (resultDiv) {
                    resultDiv.style.display = 'none';
                }
            }
            </script>
            <div id="verification-result" style="margin-top: 25px; display: none; text-align: left; background: #F8F5F2; padding: 20px; border-radius: 4px; border: 1px solid #eee;"></div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
