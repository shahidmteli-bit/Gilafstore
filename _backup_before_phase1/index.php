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

// Fetch hero banner data BEFORE header so preload can go in <head>
$heroImages = [];
$heroSliderEnabled = false;
$heroSliderTimer = 5;
$defaultSlideEnabled = true;
$heroContent = [
    'hero_headline' => 'The Essence of Purity & Tradition',
    'hero_tagline' => 'Premium Heritage Foods',
    'hero_description' => 'Experience the finest saffron, unadulterated honey, and hand-selected spices from the valley of Kashmir. Curated by Gilaf Foods.',
    'hero_btn1_text' => 'Shop Collection',
    'hero_btn1_link' => 'shop.php',
    'hero_btn2_text' => 'Verify My Product',
    'hero_btn2_link' => '#verification',
];
try {
    $heroImages = db_fetch_all("SELECT id, image_path, display_order FROM hero_banner_slides WHERE is_active = 1 ORDER BY display_order ASC");
    $heroSettings = db_fetch_all("SELECT setting_key, setting_value FROM hero_banner_settings");
    foreach ($heroSettings as $hs) {
        if ($hs['setting_key'] === 'slider_enabled')
            $heroSliderEnabled = ($hs['setting_value'] === '1');
        if ($hs['setting_key'] === 'slider_timer')
            $heroSliderTimer = intval($hs['setting_value']);
        if ($hs['setting_key'] === 'default_slide_enabled')
            $defaultSlideEnabled = ($hs['setting_value'] === '1');
        if (array_key_exists($hs['setting_key'], $heroContent) && $hs['setting_value'] !== '') {
            $heroContent[$hs['setting_key']] = $hs['setting_value'];
        }
    }
} catch (PDOException $e) {
    // Tables may not exist - use default
}

// Set preload hint for <head> (will be output by new-header.php)
// Preload must match the FIRST VISIBLE slide to optimize LCP
$heroPreloadTag = '';
if ($defaultSlideEnabled) {
    // Default slide is always first when enabled — preload the default WebP
    $heroPreloadTag = '<link rel="preload" as="image" imagesrcset="' . base_url('assets/images/hero/hero-default-mobile.webp') . ' 640w, ' . base_url('assets/images/hero/hero-default.webp') . ' 1200w" imagesizes="100vw" fetchpriority="high">';
} elseif (!empty($heroImages)) {
    $heroPreloadTag = '<link rel="preload" as="image" href="' . base_url($heroImages[0]['image_path']) . '" fetchpriority="high">';
}

// Output <head> FIRST so browser can start fetching assets while we query DB
include __DIR__ . '/includes/new-header.php';
// Flush head to browser immediately — browser starts downloading CSS/fonts/hero image
if (function_exists('ob_flush')) { @ob_flush(); }
@flush();
// NOW run product DB queries (browser is already fetching assets in parallel)
$trendingProducts = get_trending_products(4);
$trendingProducts = enrich_products_with_discounts($trendingProducts);
$freshlyHarvestedProducts = get_freshly_harvested_products(4);
$freshlyHarvestedProducts = enrich_products_with_discounts($freshlyHarvestedProducts);
$exploreProducts = get_explore_products(12);
$exploreProducts = enrich_products_with_discounts($exploreProducts);
?>
<style>
    /* Hero Button Hover Effects - Premium Gradient */
    .hero-buttons .btn-primary:hover,
    .hero-buttons .btn-outline:hover {
        background: linear-gradient(135deg, #8B5A2B 0%, #CD853F 50%, #D4A76A 100%) !important;
        color: #FFFFFF !important;
        border-color: #CD853F !important;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(205, 133, 63, 0.5);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    /* PREMIUM label - Golden color, no box */
    .premium-label {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #C5A059 !important;
        border: none !important;
        padding: 0 !important;
        margin-bottom: 4px;
        background: none !important;
    }
</style>
<!-- Hero Section - Fixed Content with Image-Only Slider -->
<?php
// Hero data already fetched at top of page (before header for preload in <head>)
// Build slides array
$allSlides = [];
$slideIndex = 0;
// Add default slide first if enabled (uses CSS background - no image path needed)
if ($defaultSlideEnabled) {
    $allSlides[] = ['type' => 'default', 'index' => $slideIndex++];
}
// Add uploaded images
foreach ($heroImages as $img) {
    $allSlides[] = ['type' => 'uploaded', 'image_path' => $img['image_path'], 'index' => $slideIndex++];
}
$totalSlides = count($allSlides);
$hasMultipleSlides = $heroSliderEnabled && $totalSlides > 1;
?>
<!-- Hero Banner with Fixed Content & Image-Only Slider -->
<section class="hero" id="heroSection">
    <?php if ($totalSlides > 0): ?>
        <!-- Background Image Slider Layer -->
        <div class="hero-bg-slider" id="heroBgSlider" data-timer="<?= $heroSliderTimer; ?>"
            data-enabled="<?= $hasMultipleSlides ? '1' : '0'; ?>">
            <?php foreach ($allSlides as $idx => $slide): ?>
                <?php if ($slide['type'] === 'default'): ?>
                    <!-- Default Slide (Local WebP hero image) -->
                    <div class="hero-bg-slide hero-default-slide <?= $idx === 0 ? 'active' : ''; ?>" data-index="<?= $idx; ?>">
                        <img src="<?= base_url('assets/images/hero/hero-default-mobile.webp'); ?>" srcset="<?= base_url('assets/images/hero/hero-default-mobile.webp'); ?> 640w, <?= base_url('assets/images/hero/hero-default.webp'); ?> 1200w" sizes="100vw" alt="Gilaf Store - Premium Heritage Foods" width="1200" height="800" fetchpriority="high" decoding="sync" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
                    </div>
                <?php else: ?>
                    <!-- Uploaded Image Slide -->
                    <div class="hero-bg-slide <?= $idx === 0 ? 'active' : ''; ?>" data-index="<?= $idx; ?>">
                        <img class="hero-uploaded-img" src="<?= base_url($slide['image_path']); ?>" alt="Gilaf Store Hero Banner" <?= $idx === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async" width="1200" height="800" style="position:absolute;top:0;left:0;width:100%;height:100%;">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- Content Layer -->
    <div class="container hero-content">
        <!-- Text content: visible on first slide only (toggled by JS on slide change) -->
        <div id="heroTextContent" class="hero-text-block">
            <div class="tagline-pill">
                <i class="fas fa-crown" style="color: var(--color-gold); font-size: 0.8rem;"></i>
                <span><?= htmlspecialchars($heroContent['hero_tagline']); ?></span>
            </div>
            <?php
            // Parse headline: split on " & " or " of " to style second part in gold italic
            $headline = $heroContent['hero_headline'];
            $parts = preg_split('/(Purity & Tradition|& .+)$/i', $headline, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts) >= 2): ?>
                <h2><?= htmlspecialchars($parts[0]); ?><br><span style="color: var(--color-gold); font-style: italic;"><?= htmlspecialchars($parts[1]); ?></span></h2>
            <?php else: ?>
                <h2><?= htmlspecialchars($headline); ?></h2>
            <?php endif; ?>
            <p><?= htmlspecialchars($heroContent['hero_description']); ?></p>
        </div>
    </div>
    <!-- Buttons: fixed position on ALL slides (direct child of .hero for stable positioning) -->
    <div class="hero-buttons">
        <a href="<?= base_url($heroContent['hero_btn1_link']); ?>" class="btn btn-primary"
            style="background: transparent; color: #FFFFFF; border: 2px solid #C5A059; border-radius: 8px; padding: 14px 32px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($heroContent['hero_btn1_text']); ?></a>
        <a href="<?= htmlspecialchars($heroContent['hero_btn2_link']); ?>" class="btn btn-outline"
            style="background: transparent; color: #FFFFFF; border: 2px solid #C5A059; border-radius: 8px; padding: 14px 32px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($heroContent['hero_btn2_text']); ?></a>
    </div>
    <?php if ($hasMultipleSlides): ?>
        <!-- Slider Navigation Dots -->
        <div class="hero-slider-dots"
            style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); display: flex; gap: 12px; z-index: 10;">
            <?php foreach ($allSlides as $idx => $slide): ?>
                <span class="hero-dot <?= $idx === 0 ? 'active' : ''; ?>" data-index="<?= $idx; ?>"
                    style="width: 12px; height: 12px; border-radius: 50%; background: <?= $idx === 0 ? '#C5A059' : 'rgba(255,255,255,0.5)'; ?>; cursor: pointer; transition: all 0.3s;"
                    title="<?= $slide['type'] === 'default' ? 'Default Slide' : 'Slide ' . ($idx + 1); ?>"></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<!-- Hero Image Slider CSS -->
<style>
    /* Hide original hero background when slider is active */
    .hero.has-slider {
        background: #1a3c34 !important;
    }
    .hero {
        position: relative;
        overflow: hidden;
    }
    .hero-bg-slider {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        background: #1a3c34;
        /* Solid background to prevent any flash */
    }
    .hero-bg-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0;
        visibility: hidden;
        z-index: 0;
    }
    /* Default slide — image loaded via <img> tag for LCP discovery, overlay via ::after */
    .hero-bg-slide.hero-default-slide {
        background: #1a3c34;
    }
    .hero-bg-slide.hero-default-slide::after {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(rgba(26, 60, 52, 0.3), rgba(26, 60, 52, 0.6));
        z-index: 1;
    }
    /* Active slide - fully visible */
    .hero-bg-slide.active {
        opacity: 1;
        visibility: visible;
        z-index: 2;
    }
    /* Incoming slide - slides in from right */
    .hero-bg-slide.slide-in {
        opacity: 1;
        visibility: visible;
        z-index: 3;
        animation: slideInFromRight 0.8s ease-in-out forwards;
    }
    /* Outgoing slide - slides out to left */
    .hero-bg-slide.slide-out {
        opacity: 1;
        visibility: visible;
        z-index: 2;
        animation: slideOutToLeft 0.8s ease-in-out forwards;
    }
    @keyframes slideInFromRight {
        0% {
            transform: translateX(100%);
        }
        100% {
            transform: translateX(0);
        }
    }
    @keyframes slideOutToLeft {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(-100%);
        }
    }
    .hero .hero-content {
        position: relative;
        z-index: 5;
    }
    .hero .hero-slider-dots {
        z-index: 10;
    }
    /* Hero text block: fade in/out on slide change */
    .hero-text-block {
        transition: opacity 0.5s ease, transform 0.5s ease;
        opacity: 1;
        transform: translateY(0);
    }
    .hero-text-block.hidden {
        opacity: 0;
        transform: translateY(-10px);
        pointer-events: none;
        height: 0;
        overflow: hidden;
        margin: 0;
        padding: 0;
    }
    /* Buttons: pinned to fixed bottom of .hero on ALL slides */
    .hero > .hero-buttons {
        position: absolute !important;
        bottom: 60px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 6;
        display: flex;
        gap: 16px;
        justify-content: center;
        width: auto;
    }

    /* Default slide: cover to fill full hero */
    .hero-bg-slide.hero-default-slide img {
        object-fit: cover !important;
        object-position: center center !important;
    }
    /* Uploaded banners: contain to show FULL image without cropping */
    .hero-uploaded-img {
        object-fit: contain !important;
        object-position: center center !important;
    }
</style>
<?php if ($hasMultipleSlides): ?>
    <!-- Hero Image Slider Script (Right to Left Animation) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const heroSection = document.getElementById('heroSection');
            const slider = document.getElementById('heroBgSlider');
            if (!slider || slider.dataset.enabled !== '1') return;
            // Add class to hero to hide original background
            if (heroSection) heroSection.classList.add('has-slider');
            const slides = slider.querySelectorAll('.hero-bg-slide');
            const dots = document.querySelectorAll('.hero-dot');
            const heroTextBlock = document.getElementById('heroTextContent');
            const heroContentDiv = document.querySelector('.hero .hero-content');
            const timer = parseInt(slider.dataset.timer) * 1000 || 5000;
            if (slides.length <= 1) return;
            let currentSlide = 0;
            let isAnimating = false;

            // Toggle hero text: visible on slide 0, hidden on others
            function updateHeroText(slideIndex) {
                if (!heroTextBlock) return;
                if (slideIndex === 0) {
                    heroTextBlock.classList.remove('hidden');
                } else {
                    heroTextBlock.classList.add('hidden');
                }
            }

            function showSlide(nextIndex) {
                if (isAnimating || nextIndex === currentSlide) return;
                isAnimating = true;
                const current = slides[currentSlide];
                const next = slides[nextIndex];
                // Remove any previous animation classes
                slides.forEach(s => {
                    s.classList.remove('slide-in', 'slide-out');
                });
                // Toggle hero text content visibility
                updateHeroText(nextIndex);
                // Start animations simultaneously
                current.classList.remove('active');
                current.classList.add('slide-out');
                next.classList.add('slide-in');
                // Update dots
                dots.forEach((dot, i) => {
                    dot.style.background = i === nextIndex ? '#C5A059' : 'rgba(255,255,255,0.5)';
                    dot.classList.toggle('active', i === nextIndex);
                });
                // Cleanup after animation completes
                setTimeout(() => {
                    current.classList.remove('slide-out');
                    next.classList.remove('slide-in');
                    next.classList.add('active');
                    currentSlide = nextIndex;
                    isAnimating = false;
                }, 800);
            }
            function nextSlide() {
                const next = (currentSlide + 1) % slides.length;
                showSlide(next);
            }
            // Auto advance
            setInterval(nextSlide, timer);
            // Dot click handlers
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => showSlide(index));
            });
        });
    </script>
<?php endif; ?>
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
        <div class="marquee-item"><i class="fas fa-leaf"></i>
            <div class="marquee-text-col"><span class="marquee-title">100% Organic</span><span
                    class="marquee-tagline">Sourced directly from certified farms.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-check-double"></i>
            <div class="marquee-text-col"><span class="marquee-title">Batch Verified</span><span
                    class="marquee-tagline">Every bottle comes with a lab report.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-mountain"></i>
            <div class="marquee-text-col"><span class="marquee-title">Kashmiri Origin</span><span
                    class="marquee-tagline">Authentic heritage from the valley.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-truck-fast"></i>
            <div class="marquee-text-col"><span class="marquee-title">Secure Shipping</span><span
                    class="marquee-tagline">Pan-India delivery with care.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-leaf"></i>
            <div class="marquee-text-col"><span class="marquee-title">100% Organic</span><span
                    class="marquee-tagline">Sourced directly from certified farms.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-check-double"></i>
            <div class="marquee-text-col"><span class="marquee-title">Batch Verified</span><span
                    class="marquee-tagline">Every bottle comes with a lab report.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-mountain"></i>
            <div class="marquee-text-col"><span class="marquee-title">Kashmiri Origin</span><span
                    class="marquee-tagline">Authentic heritage from the valley.</span></div>
        </div>
        <div class="marquee-item"><i class="fas fa-truck-fast"></i>
            <div class="marquee-text-col"><span class="marquee-title">Secure Shipping</span><span
                    class="marquee-tagline">Pan-India delivery with care.</span></div>
        </div>
    </div>
</section>
<?php if (!empty($freshlyHarvestedProducts)): ?>
    <!-- Freshly Harvested Section -->
    <section class="products-section freshly-harvested-section" id="freshly-harvested">
        <div class="container">
            <div class="section-header-card">
                <h2 class="section-title">Freshly Harvested</h2>
                <p class="section-subtitle">Straight from the valley of Kashmir</p>
            </div>
            <div class="product-grid">
                <?php foreach ($freshlyHarvestedProducts as $product): ?>
                    <article class="product-card" style="cursor: pointer;"
                        onclick="window.location.href='<?= product_url($product); ?>'">
                        <div class="badge-container">
                            <div class="badge fresh-badge"><i class="fas fa-leaf"></i> Fresh</div>
                            <?php if (!empty($product['has_discount'])): ?>
                                <div class="badge discount-badge">
                                    <i class="fas fa-tag"></i> <?= round($product['discount_percentage']); ?>% OFF
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-image-wrapper"
                            onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= product_url($product); ?>'"
                            style="cursor: pointer;">
                            <img src="<?= thumb_url('images/products/' . htmlspecialchars($product['image']), 600); ?>"
                                alt="<?= htmlspecialchars($product['name']); ?>" width="600" height="600" loading="lazy" decoding="async">
                            <div class="trust-overlay">
                                <i class="fas fa-award" style="color: var(--color-green);"></i>
                                <i class="fas fa-flask" style="color: var(--color-green);"></i>
                            </div>
                        </div>
                        <div class="product-details">
                            <span class="premium-label">PREMIUM</span>
                            <h3 class="product-title"
                                onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= product_url($product); ?>'"
                                style="cursor: pointer;">
                                <?= htmlspecialchars($product['name']); ?>
                            </h3>
                            <?php 
                            $ratingInfo = get_product_rating($product['id']); 
                            if ($ratingInfo['is_actual']): 
                            ?>
                            <div class="product-rating-small">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= round($ratingInfo['rating']) ? '' : 'text-muted opacity-25' ?>"></i>
                                <?php endfor; ?>
                                <span class="rating-count">(<?= $ratingInfo['count'] ?>)</span>
                            </div>
                            <?php endif; ?>
                            <?php $weightsDisplay = get_product_weights_display($product['id']); ?>
                            <?php if (!empty($weightsDisplay)): ?>
                                <span class="product-weights"><?= htmlspecialchars($weightsDisplay); ?></span>
                            <?php endif; ?>
                            <span class="product-origin">Origin: Kashmir Valley</span>
                            <div class="price-row">
                                <?php
                                $priceRange = get_product_price_range($product['id']);
                                $cardBasePrice = (float)$priceRange['min_price'];
                                $cardPriceInfo = calculate_discount_price($cardBasePrice, $product['discount'] ?? null);
                                ?>
                                <?php if ($cardPriceInfo['has_discount']): ?>
                                    <span style="color:#388e3c;font-weight:700;font-size:13px;">&darr;<?= round($cardPriceInfo['discount_percentage']); ?>%</span>
                                    <span class="product-price-original"><?= display_price($cardPriceInfo['original_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                                    <span class="product-price"><?= display_price($cardPriceInfo['discounted_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                                <?php else: ?>
                                    <span class="product-price"><?= display_price($cardBasePrice, $currentCurrency, $currentCurrencySymbol); ?></span>
                                <?php endif; ?>
                            </div>
                            <form action="<?= base_url('includes/cart.php'); ?>" method="post" onclick="event.stopPropagation();">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="add-btn" onclick="event.stopPropagation();">Add to Cart</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <style>
        /* Fresh Badge Styling */
        .badge.fresh-badge {
            background: linear-gradient(135deg, #2d5a27 0%, #4a7c43 100%);
            color: #fff;
            font-weight: 600;
        }
        .badge.fresh-badge i {
            margin-right: 4px;
        }
    </style>
<?php endif; ?>
<!-- Section Header Card Styling (applies to both Freshly Harvested and Best Sellers) -->
<style>
    .section-header-card {
        background: #fff;
        border-radius: 12px;
        padding: 30px 40px;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(197, 160, 89, 0.2);
        text-align: center;
    }
    .section-header-card .section-title {
        margin-bottom: 8px;
    }
    .section-header-card .section-subtitle {
        margin-bottom: 0;
    }
    /* Reduce gap between Trust Icons (marquee) and Freshly Harvested */
    .features-marquee {
        padding-bottom: 10px !important;
        margin-bottom: 0 !important;
    }
    .freshly-harvested-section {
        padding-top: 50px !important;
        padding-bottom: 50px !important;
    }
    /* Reduce gap between Freshly Harvested and Best Sellers */
    #products {
        padding-top: 50px !important;
        padding-bottom: 50px !important;
    }
    /* Reduce gap between Best Sellers and Explore Our Products */
    .explore-products-section {
        padding-top: 50px !important;
    }
</style>
<!-- Best Sellers Showcase -->
<section class="products-section" id="products">
    <div class="container">
        <div class="section-header-card">
            <h2 class="section-title">Our Best Sellers</h2>
            <p class="section-subtitle">Curated for the Connoisseur</p>
        </div>
        <div class="product-grid">
            <?php foreach ($trendingProducts as $product): ?>
                <article class="product-card" style="cursor: pointer;" onclick="window.location.href='<?= product_url($product); ?>'">
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
                    <div class="product-image-wrapper"
                        onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= product_url($product); ?>'"
                        style="cursor: pointer;">
                        <img src="<?= thumb_url('images/products/' . htmlspecialchars($product['image']), 600); ?>"
                            alt="<?= htmlspecialchars($product['name']); ?>" width="600" height="600" loading="lazy" decoding="async">
                        <div class="trust-overlay">
                            <i class="fas fa-award" style="color: var(--color-green);"></i>
                            <i class="fas fa-flask" style="color: var(--color-green);"></i>
                        </div>
                    </div>
                    <div class="product-details">
                        <span class="premium-label">PREMIUM</span>
                        <h3 class="product-title"
                            onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= product_url($product); ?>'"
                            style="cursor: pointer;">
                            <?= htmlspecialchars($product['name']); ?>
                        </h3>
                        <?php 
                        $ratingInfo = get_product_rating($product['id']); 
                        if ($ratingInfo['is_actual']): 
                        ?>
                        <div class="product-rating-small">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($ratingInfo['rating']) ? '' : 'text-muted opacity-25' ?>"></i>
                            <?php endfor; ?>
                            <span class="rating-count">(<?= $ratingInfo['count'] ?>)</span>
                        </div>
                        <?php endif; ?>
                        <?php $weightsDisplay = get_product_weights_display($product['id']); ?>
                        <?php if (!empty($weightsDisplay)): ?>
                            <span class="product-weights"><?= htmlspecialchars($weightsDisplay); ?></span>
                        <?php endif; ?>
                        <span class="product-origin">Origin: Kashmir Valley</span>
                        <div class="price-row">
                            <?php
                            $priceRange = get_product_price_range($product['id']);
                            $cardBasePrice = (float)$priceRange['min_price'];
                            $cardPriceInfo = calculate_discount_price($cardBasePrice, $product['discount'] ?? null);
                            ?>
                            <?php if ($cardPriceInfo['has_discount']): ?>
                                <span style="color:#388e3c;font-weight:700;font-size:13px;">&darr;<?= round($cardPriceInfo['discount_percentage']); ?>%</span>
                                <span class="product-price-original"><?= display_price($cardPriceInfo['original_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                                <span class="product-price"><?= display_price($cardPriceInfo['discounted_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                            <?php else: ?>
                                <span class="product-price"><?= display_price($cardBasePrice, $currentCurrency, $currentCurrencySymbol); ?></span>
                            <?php endif; ?>
                        </div>
                            <form action="<?= base_url('includes/cart.php'); ?>" method="post" onclick="event.stopPropagation();">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="add-btn" onclick="event.stopPropagation();">Add to Cart</button>
                            </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php if (!empty($exploreProducts)): ?>
    <!-- Explore Our Products Section -->
    <section class="products-section explore-products-section" id="explore-products">
        <div class="container">
            <div class="section-header-card">
                <h2 class="section-title">Explore Our Products</h2>
                <p class="section-subtitle">Every product crafted with taste, culture & craft</p>
            </div>
            <div class="product-grid">
                <?php foreach ($exploreProducts as $product): ?>
                <article class="product-card" style="cursor: pointer;" onclick="window.location.href='<?= product_url($product); ?>'">
                        <div class="badge-container">
                            <?php if (!empty($product['has_discount'])): ?>
                                <div class="badge discount-badge">
                                    <i class="fas fa-tag"></i> <?= round($product['discount_percentage']); ?>% OFF
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-image-wrapper"
                            onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= product_url($product); ?>'"
                            style="cursor: pointer;">
                            <img src="<?= thumb_url('images/products/' . htmlspecialchars($product['image']), 600); ?>"
                                alt="<?= htmlspecialchars($product['name']); ?>" width="600" height="600" loading="lazy" decoding="async">
                            <div class="trust-overlay">
                                <i class="fas fa-award" style="color: var(--color-green);"></i>
                                <i class="fas fa-flask" style="color: var(--color-green);"></i>
                            </div>
                        </div>
                        <div class="product-details">
                            <span class="product-cat"><?= htmlspecialchars($product['category_name'] ?? ''); ?></span>
                            <h3 class="product-title"
                                onclick="event.stopPropagation(); trackClick(<?= $product['id']; ?>, 'homepage'); window.location.href='<?= product_url($product); ?>'"
                                style="cursor: pointer;">
                                <?= htmlspecialchars($product['name']); ?>
                            </h3>
                            <?php 
                            $ratingInfo = get_product_rating($product['id']); 
                            if ($ratingInfo['is_actual']): 
                            ?>
                            <div class="product-rating-small">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= round($ratingInfo['rating']) ? '' : 'text-muted opacity-25' ?>"></i>
                                <?php endfor; ?>
                                <span class="rating-count">(<?= $ratingInfo['count'] ?>)</span>
                            </div>
                            <?php endif; ?>
                            <?php $weightsDisplay = get_product_weights_display($product['id']); ?>
                            <?php if (!empty($weightsDisplay)): ?>
                                <span class="product-weights"><?= htmlspecialchars($weightsDisplay); ?></span>
                            <?php endif; ?>
                            <span class="product-origin">Origin: Kashmir Valley</span>
                            <div class="price-row">
                                <?php
                                $priceRange = get_product_price_range($product['id']);
                                $cardBasePrice = (float)$priceRange['min_price'];
                                $cardPriceInfo = calculate_discount_price($cardBasePrice, $product['discount'] ?? null);
                                ?>
                                <?php if ($cardPriceInfo['has_discount']): ?>
                                    <span style="color:#388e3c;font-weight:700;font-size:13px;">&darr;<?= round($cardPriceInfo['discount_percentage']); ?>%</span>
                                    <span class="product-price-original"><?= display_price($cardPriceInfo['original_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                                    <span class="product-price"><?= display_price($cardPriceInfo['discounted_price'], $currentCurrency, $currentCurrencySymbol); ?></span>
                                <?php else: ?>
                                    <span class="product-price"><?= display_price($cardBasePrice, $currentCurrency, $currentCurrencySymbol); ?></span>
                                <?php endif; ?>
                            </div>
                            <form action="<?= base_url('includes/cart.php'); ?>" method="post" onclick="event.stopPropagation();">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="add-btn" onclick="event.stopPropagation();">Add to Cart</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="view-all-container" style="text-align: center; margin-top: 40px;">
                <a href="<?= base_url('shop.php'); ?>" class="btn-view-all">
                    View All Products <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>
    <style>
        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 35px;
            background: linear-gradient(135deg, #1A3C34 0%, #2d5a4e 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(26, 60, 52, 0.3);
        }
        .btn-view-all:hover {
            background: linear-gradient(135deg, #C5A059 0%, #d4b06a 100%);
            color: #1A3C34;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(197, 160, 89, 0.4);
        }
        .btn-view-all i {
            transition: transform 0.3s ease;
        }
        .btn-view-all:hover i {
            transform: translateX(5px);
        }
    </style>
<?php endif; ?>
<!-- Brand Story with Advertisement -->
<?php
// Fetch advertisements from database
$adVideo = null;
$adImages = [];
$adSliderEnabled = true;
try {
    $adVideo = db_fetch("SELECT * FROM advertisements_media WHERE media_type = 'video' AND is_active = 1 LIMIT 1");
    $adImages = db_fetch_all("SELECT * FROM advertisements_media WHERE media_type = 'image' AND is_active = 1 ORDER BY display_order ASC");
    $adSettings = db_fetch("SELECT setting_value FROM advertisements_settings WHERE setting_key = 'slider_enabled'");
    $adSliderEnabled = $adSettings ? ($adSettings['setting_value'] === '1') : true;
} catch (PDOException $e) {
    // Tables may not exist yet - show placeholder
}
$hasAdMedia = $adVideo || !empty($adImages);
?>
<section class="story-section" id="story" style="display: flex; flex-wrap: wrap;">
    <!-- LEFT: Advertisement Screen -->
    <div class="story-ad-screen"
        style="flex: 1 1 50%; min-height: 450px; display: flex; align-items: center; justify-content: center; padding: 40px; background: rgba(0,0,0,0.1);">
        <div class="ad-container"
            style="width: 100%; max-width: 450px; aspect-ratio: 4/3; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <?php if ($hasAdMedia): ?>
                <?php if ($adVideo): ?>
                    <!-- Video Display -->
                    <div id="adVideoWrapper" style="width: 100%; height: 100%; position: relative;">
                        <video id="adVideo" muted playsinline disablepictureinpicture controlslist="nodownload noplaybackrate" preload="auto" <?= empty($adImages) ? 'loop' : ''; ?>
                            style="width: 100%; height: 100%; object-fit: cover;">
                            <source src="<?= base_url($adVideo['file_path']); ?>" type="video/mp4">
                        </video>
                        <!-- Mute/Unmute Button - Hidden by default, shows on hover -->
                        <button id="videoMuteBtn" type="button"
                            style="position: absolute; bottom: 20px; right: 20px; width: 44px; height: 44px; border-radius: 50%; background: rgba(0,0,0,0.7); border: 2px solid rgba(255,255,255,0.3); cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 100; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.4); opacity: 0; pointer-events: none;"
                            title="Click to unmute">
                            <i id="muteIcon" class="fas fa-volume-mute" style="color: #fff; font-size: 18px;"></i>
                            <i id="unmuteIcon" class="fas fa-volume-up"
                                style="color: #fff; font-size: 18px; display: none;"></i>
                        </button>
                        <!-- Bottom cover strip: hides any browser-injected video indicators -->
                        <div style="position:absolute;bottom:0;left:0;width:100%;height:40px;background:linear-gradient(to top,rgba(0,0,0,0.6),transparent);z-index:10;pointer-events:none;"></div>
                        <style>
                            #adVideoWrapper:hover #videoMuteBtn {
                                opacity: 1 !important;
                                pointer-events: auto !important;
                            }
                            #adVideo::-webkit-media-controls,
                            #adVideo::-webkit-media-controls-enclosure,
                            #adVideo::-webkit-media-controls-overlay-play-button,
                            #adVideo::-webkit-media-controls-panel,
                            #adVideo::-webkit-media-controls-start-playback-button { display: none !important; }
                        </style>
                    </div>
                <?php endif; ?>
                <?php if (!empty($adImages)): ?>
                    <!-- Images Display -->
                    <div id="adImagesWrapper"
                        style="width: 100%; height: 100%; position: relative; <?= $adVideo ? 'display:none;' : ''; ?>">
                        <?php if ($adSliderEnabled && count($adImages) > 1): ?>
                            <!-- Slider Mode -->
                            <div class="ad-slider" id="adSlider" style="width: 100%; height: 100%; position: relative;">
                                <?php foreach ($adImages as $index => $img): ?>
                                    <div class="ad-slide"
                                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: <?= $index === 0 ? '1' : '0'; ?>; transition: opacity 0.6s ease;">
                                        <img src="<?= base_url($img['file_path']); ?>" alt="Advertisement"
                                            style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Slider Dots -->
                            <div class="ad-slider-dots"
                                style="position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10;">
                                <?php foreach ($adImages as $index => $img): ?>
                                    <span class="ad-dot" data-index="<?= $index; ?>"
                                        style="width: 10px; height: 10px; border-radius: 50%; background: <?= $index === 0 ? '#C5A059' : 'rgba(255,255,255,0.5)'; ?>; cursor: pointer; transition: all 0.3s;"></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Single Image Mode -->
                            <img src="<?= base_url($adImages[0]['file_path']); ?>" alt="Advertisement"
                                style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Placeholder when no media uploaded -->
                <div class="ad-placeholder"
                    style="width: 100%; height: 100%; background: linear-gradient(135deg, #1a3c34 0%, #0d1f1b 100%); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.3); font-size: 1rem; text-transform: uppercase; letter-spacing: 2px;">
                    <span>Advertisement</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- RIGHT: Our Philosophy -->
    <div class="story-content">
        <span
            style="color: var(--color-gold); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 15px; display: block; font-size: 0.85rem;">Our
            Philosophy</span>
        <h2 style="font-size: 2.2rem; margin-bottom: 25px; line-height: 1.2;">Preserving the Art of Taste</h2>
        <p style="line-height: 1.8; margin-bottom: 20px;">At <strong>Gilaf Foods & Spices</strong>, we believe that food
            is not just sustenance, it is memory. Founded by Shahid Mohammad & Muneera Shahid, our mission is to bring
            the unadulterated taste of Kashmir to your table.</p>
        <p style="line-height: 1.8; margin-bottom: 30px;">We work directly with local farmers, ensuring that every
            strand of saffron and every drop of honey retains the purity of the mountains.</p>
        <div class="founder-sig" style="margin-top: 20px;">Shahid & Muneera</div>
    </div>
</section>
<!-- Store Locator -->
<section class="store-locator-section" id="locator">
    <div class="container locator-container">
        <h2 class="section-title">Find a Store</h2>
        <p class="section-subtitle">Locate Gilaf Stores & Distributors Near You</p>
        
        <div class="search-box-wrapper">
            <div class="input-group">
                <input type="text" id="pincodeInput" class="verify-input" placeholder="Enter Pincode or City Name (e.g., 193201 or Sopore)" onkeydown="if(event.key==='Enter'){event.preventDefault();findStores();}">
                <button class="verify-btn" onclick="findStores()"><i class="fas fa-search"></i> Search</button>
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
            <p style="margin: 15px 0 25px; color: #666;">Enter the Batch Number found on your product lid to view
                complete product details.</p>
            <form onsubmit="verifyBatch(event)">
                <div class="input-group">
                    <input type="text" id="batchInput" class="verify-input"
                        placeholder="Enter Batch ID (e.g., GF-2025-01)">
                    <button type="submit" class="verify-btn">Verify Now</button>
                    <button type="button" class="clear-btn" onclick="clearBatchField()"><i
                            class="fas fa-times-circle"></i> Clear</button>
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
            <div id="verification-result"
                style="margin-top: 25px; display: none; text-align: left; background: #F8F5F2; padding: 20px; border-radius: 4px; border: 1px solid #eee;">
            </div>
        </div>
    </div>
</section>
<!-- Advertisement Slider & Video Script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const video = document.getElementById('adVideo');
        const videoWrapper = document.getElementById('adVideoWrapper');
        const imagesWrapper = document.getElementById('adImagesWrapper');
        const slider = document.getElementById('adSlider');
        const muteBtn = document.getElementById('videoMuteBtn');
        const muteIcon = document.getElementById('muteIcon');
        const unmuteIcon = document.getElementById('unmuteIcon');
        // Video playback logic - Play/Pause on scroll visibility
        if (video) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Play when section is visible
                        video.play().catch(e => console.log('Autoplay prevented'));
                    } else {
                        // Pause when section is not visible
                        video.pause();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(video);
            // Mute/Unmute button functionality
            if (muteBtn) {
                muteBtn.addEventListener('click', function () {
                    if (video.muted) {
                        video.muted = false;
                        muteIcon.style.display = 'none';
                        unmuteIcon.style.display = 'block';
                        muteBtn.title = 'Click to mute';
                        muteBtn.style.background = 'rgba(197, 160, 89, 0.8)';
                    } else {
                        video.muted = true;
                        muteIcon.style.display = 'block';
                        unmuteIcon.style.display = 'none';
                        muteBtn.title = 'Click to unmute';
                        muteBtn.style.background = 'rgba(0,0,0,0.6)';
                    }
                });
                // Hover effect
                muteBtn.addEventListener('mouseenter', function () {
                    muteBtn.style.transform = 'scale(1.1)';
                });
                muteBtn.addEventListener('mouseleave', function () {
                    muteBtn.style.transform = 'scale(1)';
                });
            }
            video.addEventListener('ended', function () {
                if (videoWrapper) videoWrapper.style.display = 'none';
                if (imagesWrapper) imagesWrapper.style.display = 'block';
                startAdSlider();
            });
        } else {
            startAdSlider();
        }
        function startAdSlider() {
            if (!slider) return;
            const slides = slider.querySelectorAll('.ad-slide');
            const dots = document.querySelectorAll('.ad-dot');
            if (slides.length <= 1) return;
            let currentSlide = 0;
            function showSlide(index) {
                slides.forEach((slide, i) => {
                    slide.style.opacity = i === index ? '1' : '0';
                });
                dots.forEach((dot, i) => {
                    dot.style.background = i === index ? '#C5A059' : 'rgba(255,255,255,0.5)';
                });
            }
            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }
            setInterval(nextSlide, 5000);
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    currentSlide = index;
                    showSlide(currentSlide);
                });
            });
        }
    });
</script>
<script>
// Subtitle marquee for phones only - wraps golden tagline text in animated span
(function() {
    if (!window.matchMedia('(max-width: 767px)').matches) return;
    document.querySelectorAll('.section-header-card .section-subtitle').forEach(function(el) {
        var text = el.textContent.trim();
        el.innerHTML = '<span class="subtitle-marquee-track">' + text + '</span>';
    });
})();
</script>
<?php include __DIR__ . '/includes/google-review-section.php'; ?>
<?php include __DIR__ . '/includes/new-footer.php'; ?>
