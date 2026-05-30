<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize region detection
require_once __DIR__ . '/region_detection.php';
require_once __DIR__ . '/currency_converter.php';
require_once __DIR__ . '/language_manager.php';

$pageTitle = $pageTitle ?? 'Gilaf Store | Taste • Culture • Craft';

// Calculate cart count handling both new array structure and legacy integer structure
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (is_array($item)) {
            $cartCount += (int)($item['quantity'] ?? 0);
        } else {
            $cartCount += (int)$item;
        }
    }
}

$userName = $_SESSION['user']['name'] ?? null;
$isLoggedIn = isset($_SESSION['user']);

// Manual language override via query parameter (persist for guests and users)
if (isset($_GET['lang'])) {
    $overrideLang = strtolower(trim($_GET['lang']));
    if (get_language_data($overrideLang)) {
        $userId = $_SESSION['user']['id'] ?? null;
        save_language_preference($overrideLang, $userId);
    }
}

// Get user's region settings
$userRegion = get_user_region_settings();
$currentCountry = $userRegion['country'];
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

// Get user's language with priority: manual > profile > browser (first visit) > default
$currentLanguage = get_user_language();
$htmlLangCode = htmlspecialchars($currentLanguage['code']);
$htmlLangDir = htmlspecialchars(get_language_direction());
?>
<!DOCTYPE html>
<html lang="<?= $htmlLangCode; ?>" dir="<?= $htmlLangDir; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Gilaf Store — Premium Kashmiri saffron, organic honey, and hand-selected spices. Farm-to-table heritage foods from the valley of Kashmir. Free shipping across India.'); ?>">
    <?php if (!empty($product['seo_keywords'])): ?>
    <meta name="keywords" content="<?= htmlspecialchars($product['seo_keywords']); ?>">
    <?php endif; ?>
    <?php if (!empty($product['backend_search_terms'])): ?>
    <meta name="search-terms" content="<?= htmlspecialchars($product['backend_search_terms']); ?>">
    <?php endif; ?>

    <!-- Canonical URL — SEO: use override if set (product/category pages), else auto-detect -->
    <?php
    if (!empty($canonicalOverride)) {
        $canonicalUrl = $canonicalOverride;
    } else {
        $canonicalPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $canonicalQuery = '';
        if (!empty($_GET['id'])) $canonicalQuery = '?id=' . (int)$_GET['id'];
        elseif (!empty($_GET['category'])) $canonicalQuery = '?category=' . (int)$_GET['category'];
        $canonicalUrl = 'https://www.gilafstore.com' . $canonicalPath . $canonicalQuery;
    }
    ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl); ?>">

    <!-- SEO: OpenGraph + Twitter Card meta tags -->
    <?php if (!empty($productMetaTags)): ?>
<?= $productMetaTags; ?>
    <?php else: ?>
    <?php
    $ogTitle = $pageTitle;
    $ogDesc = $metaDescription ?? 'Gilaf Store — Premium Kashmiri saffron, organic honey, and hand-selected spices. Farm-to-table heritage foods from the valley of Kashmir.';
    $ogUrl = $canonicalUrl;
    $ogImg = 'https://www.gilafstore.com/assets/images/gilaf-store-og-default.jpg';
    ?>
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle); ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc); ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl); ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($ogImg); ?>" />
    <meta property="og:site_name" content="Gilaf Store" />
    <meta property="og:locale" content="en_IN" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle); ?>" />
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc); ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImg); ?>" />
    <?php endif; ?>

    <!-- PWA Manifest & Icons (layout-safe) -->
    <meta name="theme-color" content="#1a3c34">
    <link rel="manifest" href="<?= base_url('manifest.json'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('assets/icons/icon-192x192.png'); ?>">

    <!-- Preload hero image FIRST for fastest LCP -->
    <?php if (!empty($heroPreloadTag)) echo '    ' . $heroPreloadTag . "\n"; ?>

    <!-- Preload critical fonts (body + heading only; icon font loads via deferred FA CSS) -->
    <link rel="preload" href="<?= base_url('assets/fonts/inter-latin.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= base_url('assets/fonts/playfair-latin.woff2'); ?>" as="font" type="font/woff2" crossorigin>

    <!-- Critical above-the-fold CSS + self-hosted fonts inlined for instant FCP -->
    <style>
    /* Self-hosted Google Fonts — font-display:optional prevents CLS from font swap */
    @font-face{font-family:'Inter';font-style:normal;font-weight:400;font-display:optional;src:url(<?= base_url('assets/fonts/inter-latin.woff2'); ?>) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
    @font-face{font-family:'Inter';font-style:normal;font-weight:600;font-display:optional;src:url(<?= base_url('assets/fonts/inter-latin.woff2'); ?>) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
    @font-face{font-family:'Playfair Display';font-style:normal;font-weight:400;font-display:optional;src:url(<?= base_url('assets/fonts/playfair-latin.woff2'); ?>) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
    @font-face{font-family:'Playfair Display';font-style:normal;font-weight:700;font-display:optional;src:url(<?= base_url('assets/fonts/playfair-latin.woff2'); ?>) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
    @font-face{font-family:'Playfair Display';font-style:italic;font-weight:400;font-display:optional;src:url(<?= base_url('assets/fonts/playfair-italic-latin.woff2'); ?>) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
    /* Critical layout CSS */
    :root{--color-ivory:#F8F5F2;--color-green:#1A3C34;--color-gold:#C5A059;--color-gold-hover:#b08d4b;--color-text:#2C241B;--color-text-light:#6b6b6b;--color-white:#fff;--color-black:#111;--font-serif:'Playfair Display',serif;--font-sans:'Inter',sans-serif;--container-width:1440px;--section-padding:100px 20px;--border-radius:4px}
    *{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
    html,body{width:100%;overflow-x:hidden;margin:0;padding:0}
    body{font-family:var(--font-sans);background-color:var(--color-ivory);color:var(--color-text);line-height:1.6;-webkit-font-smoothing:antialiased}
    main{padding-top:115px}body.scrolled main{padding-top:95px}
    body.page-home main,body.page-home.scrolled main{padding-top:0!important}
    @media(max-width:768px){main{padding-top:105px}body.scrolled main{padding-top:90px}body.page-home main,body.page-home.scrolled main{padding-top:0!important}}
    a{text-decoration:none;color:inherit;transition:.3s}ul{list-style:none}img{max-width:100%;display:block}button{border:none;outline:none;font-family:inherit}
    .container{max-width:var(--container-width);margin:0 auto;padding:0 30px;width:100%}
    h1,h2,h3,h4{font-family:var(--font-serif);font-weight:700}
    .top-bar{background-color:var(--color-green);color:var(--color-gold);font-size:.75rem;padding:10px 0;text-transform:uppercase;letter-spacing:1px;position:relative;z-index:1001}
    .top-bar .container{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap}
    .main-header{position:fixed;width:100%;z-index:1000;padding:20px 0;transition:all .5s cubic-bezier(.4,0,.2,1);top:40px;background:rgba(26,60,52,.3);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);border-bottom:1px solid rgba(197,160,89,.2);box-shadow:0 8px 32px rgba(0,0,0,.1)}
    .main-header.scrolled{background:rgba(26,60,52,.3);box-shadow:0 10px 40px rgba(26,60,52,.08);padding:12px 0;top:0;backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);border-bottom:1px solid rgba(26,60,52,.08)}
    body.scrolled .top-bar{display:none}
    .nav-container{display:flex;justify-content:space-between;align-items:center}
    .logo{text-align:center}.logo h1{font-size:2rem;font-weight:700;margin:0;letter-spacing:4px;color:var(--color-white)!important;-webkit-text-fill-color:var(--color-white)!important}
    .logo h1 .logo-gilaf{color:var(--color-gold)!important;-webkit-text-fill-color:var(--color-gold)!important}
    .logo h1 .logo-store{color:var(--color-white)!important;-webkit-text-fill-color:var(--color-white)!important}
    .logo span{display:inline-block;font-size:.62rem;font-family:var(--font-sans);letter-spacing:4.5px;text-transform:uppercase;margin-top:4px;opacity:.95;font-weight:500;color:rgba(197,160,89,.9)}
    .nav-links{display:flex;gap:40px;align-items:center}
    .nav-links>a,.dropbtn{font-size:.8rem;font-weight:600;letter-spacing:2px;text-transform:uppercase;cursor:pointer;transition:all .4s;position:relative;padding:8px 0;color:rgba(255,255,255,.95)}
    .user-actions{display:flex;gap:28px;align-items:center}.user-actions i,.user-actions a{cursor:pointer;font-size:1.15rem;transition:color .3s}
    .menu-toggle{display:none;color:var(--color-white);font-size:1.5rem;cursor:pointer;padding:8px;border-radius:4px}
    /* Dropdown/mega-menu hidden by default — prevents FOUC */
    .dropdown{position:relative;display:inline-block;height:100%}
    .dropdown-content{display:none!important;position:absolute;top:100%;left:0;min-width:200px;z-index:999;background:#fff}
    .dropdown:hover .dropdown-content{display:block!important}
    .mobile-menu-overlay{display:none;opacity:0;pointer-events:none}
    /* Hero + slider critical CSS — ensures hero image renders at first paint (fixes LCP) */
    .hero{min-height:850px;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;color:var(--color-white);padding-top:60px;padding-left:20px;padding-right:20px;position:relative;overflow:hidden}
    .hero-bg-slider{position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;background:#1a3c34}
    .hero-bg-slide{position:absolute;top:0;left:0;width:100%;height:100%;background-size:cover;background-position:center;background-repeat:no-repeat;opacity:0;visibility:hidden;z-index:0}
    .hero-bg-slide.active{opacity:1;visibility:visible;z-index:2}
    .hero-bg-slide.hero-default-slide{background:#1a3c34}
    .hero-bg-slide.hero-default-slide::after{content:'';position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(rgba(26,60,52,.3),rgba(26,60,52,.6));z-index:1}
    .hero-content{max-width:1100px;width:100%;position:relative;z-index:5}
    .tagline-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(197,160,89,.5);padding:10px 25px;border-radius:50px;margin-bottom:30px;backdrop-filter:blur(5px);background:rgba(26,60,52,.3);max-width:100%;box-sizing:border-box}
    .tagline-pill span{font-size:.85rem;letter-spacing:2px;text-transform:uppercase;color:var(--color-gold);white-space:nowrap}
    .hero h2{font-size:5.5rem;margin-bottom:30px;line-height:1.1;text-shadow:0 5px 15px rgba(0,0,0,.2)}
    .hero p{font-size:1.4rem;margin-bottom:50px;opacity:.95;font-weight:300;max-width:700px;margin-left:auto;margin-right:auto}
    .hero-buttons{display:flex;gap:20px;justify-content:center;flex-wrap:wrap}
    .btn{display:inline-block;padding:18px 42px;text-transform:uppercase;letter-spacing:2px;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .4s;position:relative;overflow:hidden;white-space:nowrap}
    .btn-primary,.btn-outline{background-color:transparent;color:var(--color-white);border:1px solid rgba(255,255,255,.6)}
    @keyframes fadeUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
    .hidden-mobile{display:inline}
    @media(max-width:768px){.hidden-mobile{display:none}.hero h2{font-size:2.5rem}.hero p{font-size:1rem}.hero{min-height:100vh}.menu-toggle{display:block}}
    </style>

    <!-- Combined CSS (10 files merged) — render-blocking to prevent CLS -->
    <link rel="stylesheet" href="<?= asset_url_versioned('css/combined.min.css'); ?>">

    <!-- Font Awesome — self-hosted, deferred (font-display:swap ensures icons always appear) -->
    <link rel="stylesheet" href="<?= asset_url_versioned('css/fontawesome.min.css'); ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= asset_url_versioned('css/fontawesome.min.css'); ?>"></noscript>

    <!-- Google Review section (below-fold, deferred) -->
    <link rel="stylesheet" href="<?= asset_url_versioned('css/google-review.css'); ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= asset_url_versioned('css/google-review.css'); ?>"></noscript>

    <!-- Google Tag (Admin Configured) -->
    <?php
    require_once __DIR__ . '/google_tag_simple.php';
    inject_google_tag_simple();
    ?>

</head>

<body class="page-<?= htmlspecialchars($activePage ?? ''); ?>">

    <?php
    // Track page view for analytics (exclude admin users)
    if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $pageTitle = $pageTitle ?? 'Gilaf Store';
        $pageType = $activePage ?? 'general';
        trackPageView($pageUrl, $pageTitle, $pageType);
    }
    ?>

    <!-- Top Bar -->
    <div class="top-bar desktop-utility-bar">
        <div class="container">
            <div style="display: flex; gap: 15px; align-items: center; justify-content: center;">
                <span><i class="fas fa-certificate"></i> Certified Organic</span>
                <span class="hidden-mobile">|</span>
                <span class="hidden-mobile">Ships to 15+ Countries</span>
                <?php
                // Get active promo codes for header display
                require_once __DIR__ . '/promo_functions.php';
                $userId = $_SESSION['user']['id'] ?? null;
                $userEmail = $_SESSION['user']['email'] ?? null;
                $userPhone = $_SESSION['user']['phone'] ?? null;
                $userProfile = get_user_profile($userEmail, $userPhone, $userId);
                $headerPromos = get_active_promo_codes(true, $userProfile);

                if (!empty($headerPromos)):
                    ?>
                    <span class="hidden-mobile">|</span>
                    <div id="promoCodeBanner"
                        style="display: inline-flex; align-items: center; overflow: hidden; width: 450px; position: relative; background: rgba(197, 160, 89, 0.1); padding: 4px 8px; border-radius: 4px;">
                        <div id="promoCodeSlider" style="position: relative; width: 100%; height: 20px; overflow: hidden;">
                            <?php foreach ($headerPromos as $index => $promo):
                                // Format discount value for display
                                $discountDisplay = $promo['discount_type'] === 'percentage'
                                    ? $promo['discount_value'] . '% OFF'
                                    : '₹' . number_format($promo['discount_value'], 0) . ' OFF';

                                // Use custom message if available, otherwise use default format
                                if (!empty($promo['promo_message'])) {
                                    $message = str_replace('{CODE}', $promo['code'], $promo['promo_message']);
                                    $message = str_replace('{DISCOUNT}', $discountDisplay, $message);
                                } else {
                                    // Default format
                                    $message = '🎁 Use code ' . $promo['code'] . ' & get ' . $discountDisplay;
                                }
                                ?>
                                <div class="promo-slide" data-index="<?= $index; ?>"
                                    style="<?= $index === 0 ? '' : 'display: none;'; ?> position: absolute; width: 100%; height: 100%;">
                                    <div class="promo-marquee">
                                        <span class="promo-text"
                                            style="font-size: 0.75rem; font-weight: 600; color: var(--color-gold); white-space: nowrap; letter-spacing: 0.5px;">
                                            <?= $message; ?>&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;<?= $message; ?>&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;<?= $message; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 20px; align-items: center; justify-content: center;">
                <div class="region-trigger" onclick="openRegionModal()">
                    <span>Change Region</span>
                    <img id="current-flag" src="https://flagcdn.com/<?= strtolower($currentCountry['code']); ?>.svg"
                        width="20" alt="<?= $currentCountry['code']; ?>" style="border-radius: 2px;">
                    <span id="current-currency" style="font-weight: 600;"><?= $currentCurrency; ?>
                        (<?= $currentCurrencySymbol; ?>)</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.6rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Promo Banner Smooth Scrolling Marquee */
        .promo-marquee {
            display: inline-block;
            animation: marqueeScroll 25s linear infinite;
            will-change: transform;
        }

        @keyframes marqueeScroll {
            0% {
                transform: translateX(100%);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        /* Pause animation on hover */
        #promoCodeBanner:hover .promo-marquee {
            animation-play-state: paused;
        }

        /* Fade in/out for slide transitions */
        .promo-slide {
            transition: opacity 0.8s ease-in-out;
        }

        .promo-slide.fade-out {
            opacity: 0;
        }

        .promo-slide.fade-in {
            opacity: 1;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #promoCodeBanner {
                width: 280px !important;
            }

            .promo-marquee {
                animation: marqueeScroll 20s linear infinite;
            }
        }

        @media (max-width: 480px) {
            #promoCodeBanner {
                width: 200px !important;
            }

            .promo-text {
                font-size: 0.7rem !important;
            }
        }
    </style>

    <script>
        // Smooth promo code rotation with continuous scrolling
        (function () {
            const slides = document.querySelectorAll('.promo-slide');
            if (slides.length === 0) return;

            let currentIndex = 0;

            // Initialize first slide
            if (slides[0]) {
                slides[0].classList.add('fade-in');
            }

            // Rotate between multiple promo codes if more than one
            if (slides.length > 1) {
                setInterval(function () {
                    // Fade out current slide
                    slides[currentIndex].classList.remove('fade-in');
                    slides[currentIndex].classList.add('fade-out');

                    setTimeout(() => {
                        // Hide current slide
                        slides[currentIndex].style.display = 'none';
                        slides[currentIndex].classList.remove('fade-out');

                        // Move to next slide
                        currentIndex = (currentIndex + 1) % slides.length;

                        // Show and fade in next slide
                        slides[currentIndex].style.display = 'block';

                        // Small delay before fade in for smooth transition
                        setTimeout(() => {
                            slides[currentIndex].classList.add('fade-in');
                        }, 50);

                    }, 800); // Wait for fade out to complete

                }, 8000); // Show each promo for 8 seconds
            }
        })();
    </script>

    <!-- Main Header -->
    <header class="main-header" id="header"
        style="background: #244A36; backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);">
        <div class="container nav-container">
            <div class="menu-toggle"><i class="fas fa-bars"></i></div>
            <div class="logo">
                <a href="<?= base_url('index.php'); ?>" style="display: inline-block; text-align: center;">
                    <h1
                        style="color: #ffffff; -webkit-text-fill-color: #ffffff; background: none; font-family: 'Playfair Display', serif;">
                        GILAF STORE</h1>
                    <span style="font-family: 'Inter', sans-serif; color: #D4AF37; opacity: 1;">TASTE • CULTURE •
                        CRAFT</span>
                </a>
            </div>
            <!-- Desktop Navigation -->
            <nav class="nav-links desktop-nav">
                <a href="<?= base_url('index.php'); ?>">HOME</a>
                <a href="<?= base_url('shop.php'); ?>">SHOP</a>
                <div class="dropdown">
                    <span class="dropbtn">SHOP BY CATEGORY <i class="fas fa-chevron-down"
                            style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <?php
                        $categories = get_categories();
                        foreach ($categories as $cat):
                            ?>
                            <a
                                href="<?= base_url('shop.php?category=' . $cat['id']); ?>"><?= htmlspecialchars($cat['name']); ?></a>
                        <?php endforeach; ?>
                        <hr style="margin: 8px 0; border: none; border-top: 1px solid #e0e0e0;">
                        <a href="<?= base_url('offers.php'); ?>">🎁 Offers & Deals</a>
                        <a href="<?= base_url('gifting-hampers.php'); ?>">🎀 Gifting & Hampers</a>
                    </div>
                </div>
                <div class="dropdown">
                    <span class="dropbtn">TRACK <i class="fas fa-chevron-down"
                            style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <a href="#" onclick="openTrackingModal(); return false;">Track Order</a>
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= base_url('user/my_tickets.php'); ?>">Track Requests</a>
                        <?php else: ?>
                            <a href="#" onclick="openLoginModal(); return false;">Track Requests</a>
                        <?php endif; ?>
                        <a href="#locator">Track Stores</a>
                        <a href="#verification">Authenticity Tracking</a>
                    </div>
                </div>
                <div class="dropdown">
                    <span class="dropbtn">OUR STORY <i class="fas fa-chevron-down"
                            style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <a href="<?= base_url('about-us.php'); ?>">About Us</a>
                        <a href="<?= base_url('our-values.php'); ?>">Our Values</a>
                        <a href="<?= base_url('blogs.php'); ?>">Blogs</a>
                    </div>
                </div>
            </nav>

            <!-- Mobile Navigation -->
            <nav class="mobile-nav">
                <!-- Mobile Menu Header with Close Button Only -->
                <div class="mobile-menu-header-bar">
                    <button class="mobile-menu-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Main Menu List -->
                <div class="mobile-menu-list">
                    <a href="<?= base_url('index.php'); ?>" class="mobile-menu-item">
                        <i class="fas fa-home"></i>
                        <span>HOME</span>
                    </a>

                    <div class="mobile-menu-item-wrapper">
                        <button class="mobile-menu-item has-submenu" data-submenu="shop">
                            <i class="fas fa-shopping-bag"></i>
                            <span>SHOP</span>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </button>
                    </div>

                    <div class="mobile-menu-item-wrapper">
                        <button class="mobile-menu-item has-submenu" data-submenu="categories">
                            <i class="fas fa-th-large"></i>
                            <span>COLLECTIONS</span>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </button>
                    </div>

                    <a href="<?= base_url('shop.php?filter=bestsellers'); ?>" class="mobile-menu-item">
                        <i class="fas fa-star"></i>
                        <span>BESTSELLERS</span>
                    </a>

                    <a href="<?= base_url('offers.php'); ?>" class="mobile-menu-item">
                        <i class="fas fa-tag"></i>
                        <span>SALE</span>
                    </a>

                    <div class="mobile-menu-item-wrapper">
                        <button class="mobile-menu-item has-submenu" data-submenu="track">
                            <i class="fas fa-box"></i>
                            <span>TRACK</span>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </button>
                    </div>

                    <div class="mobile-menu-item-wrapper">
                        <button class="mobile-menu-item has-submenu" data-submenu="story">
                            <i class="fas fa-book-open"></i>
                            <span>OUR STORY</span>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </button>
                    </div>

                    <a href="<?= base_url('contact.php'); ?>" class="mobile-menu-item">
                        <i class="fas fa-envelope"></i>
                        <span>CONTACT US</span>
                    </a>
                </div>

                <!-- Mobile Utility Section -->
                <div class="mobile-utility-section">
                    <div class="mobile-utility-item">
                        <i class="fas fa-certificate"></i>
                        <span>Certified Organic</span>
                    </div>
                    <div class="mobile-utility-divider"></div>
                    <div class="mobile-utility-item clickable" onclick="openRegionModal(); closeMobileMenu();">
                        <i class="fas fa-globe"></i>
                        <span>Change Region</span>
                        <span class="mobile-utility-separator">|</span>
                        <span class="mobile-utility-currency"><?= $currentCurrency; ?>
                            (<?= $currentCurrencySymbol; ?>)</span>
                    </div>
                </div>
            </nav>

            <!-- Submenu Panel: Shop -->
            <div class="mobile-submenu-panel" id="submenu-shop">
                <div class="mobile-submenu-header">
                    <button class="mobile-submenu-back">
                        <i class="fas fa-chevron-left"></i>
                        <span>BACK</span>
                    </button>
                </div>
                <div class="mobile-submenu-title">SHOP</div>
                <div class="mobile-submenu-list">
                    <a href="<?= base_url('shop.php'); ?>" class="mobile-submenu-item">ALL PRODUCTS</a>
                    <a href="<?= base_url('shop.php?filter=new'); ?>" class="mobile-submenu-item">NEW ARRIVALS</a>
                    <a href="<?= base_url('offers.php'); ?>" class="mobile-submenu-item">OFFERS & DEALS</a>
                    <a href="<?= base_url('gifting-hampers.php'); ?>" class="mobile-submenu-item">GIFTING & HAMPERS</a>
                </div>
            </div>

            <!-- Submenu Panel: Collections -->
            <div class="mobile-submenu-panel" id="submenu-categories">
                <div class="mobile-submenu-header">
                    <button class="mobile-submenu-back">
                        <i class="fas fa-chevron-left"></i>
                        <span>BACK</span>
                    </button>
                </div>
                <div class="mobile-submenu-title">COLLECTIONS</div>
                <div class="mobile-submenu-list">
                    <?php
                    $categories = get_categories();
                    foreach ($categories as $cat):
                        ?>
                        <a href="<?= base_url('shop.php?category=' . $cat['id']); ?>"
                            class="mobile-submenu-item"><?= strtoupper(htmlspecialchars($cat['name'])); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Submenu Panel: Track -->
            <div class="mobile-submenu-panel" id="submenu-track">
                <div class="mobile-submenu-header">
                    <button class="mobile-submenu-back">
                        <i class="fas fa-chevron-left"></i>
                        <span>BACK</span>
                    </button>
                </div>
                <div class="mobile-submenu-title">TRACK</div>
                <div class="mobile-submenu-list">
                    <a href="#" onclick="openTrackingModal(); closeMobileMenu(); return false;"
                        class="mobile-submenu-item">TRACK ORDER</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= base_url('user/my_tickets.php'); ?>" class="mobile-submenu-item">TRACK REQUESTS</a>
                    <?php else: ?>
                        <a href="#" onclick="openLoginModal(); closeMobileMenu(); return false;"
                            class="mobile-submenu-item">TRACK REQUESTS</a>
                    <?php endif; ?>
                    <a href="#locator" onclick="closeMobileMenu();" class="mobile-submenu-item">TRACK STORES</a>
                    <a href="#verification" onclick="closeMobileMenu();" class="mobile-submenu-item">AUTHENTICITY
                        TRACKING</a>
                </div>
            </div>

            <!-- Submenu Panel: Our Story -->
            <div class="mobile-submenu-panel" id="submenu-story">
                <div class="mobile-submenu-header">
                    <button class="mobile-submenu-back">
                        <i class="fas fa-chevron-left"></i>
                        <span>BACK</span>
                    </button>
                </div>
                <div class="mobile-submenu-title">OUR STORY</div>
                <div class="mobile-submenu-list">
                    <a href="<?= base_url('about-us.php'); ?>" class="mobile-submenu-item">ABOUT US</a>
                    <a href="<?= base_url('our-values.php'); ?>" class="mobile-submenu-item">OUR VALUES</a>
                    <a href="<?= base_url('blogs.php'); ?>" class="mobile-submenu-item">BLOGS</a>
                </div>
            </div>

            <!-- Mobile Menu Overlay -->
            <div class="mobile-menu-overlay"></div>

            <div class="user-actions">
                <div id="searchContainer" style="position: relative;">
                    <i class="fas fa-search" id="searchIcon" onclick="toggleSearch()" style="cursor: pointer;"></i>
                    <form id="searchForm" action="<?= base_url('search.php'); ?>" method="GET"
                        style="position: absolute; top: 100%; right: 0; margin-top: 10px; display: none; z-index: 1000;">
                        <div
                            style="background: white; border-radius: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; overflow: visible; position: relative;">
                            <input type="text" name="q" id="searchInput" placeholder="Search products..."
                                style="width: 250px; padding: 12px 18px; border: none; outline: none; font-size: 0.9rem; font-family: 'Inter', sans-serif;"
                                autocomplete="off" />
                            <button type="submit"
                                style="background: #C5A089; border: none; padding: 12px 20px; cursor: pointer; transition: background 0.3s ease;"
                                onmouseover="this.style.background='#d4b896'"
                                onmouseout="this.style.background='#C5A089'">
                                <i class="fas fa-search" style="color: white;"></i>
                            </button>
                            <div id="searchAutocomplete"
                                style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 8px; display: none; z-index: 1001; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); max-height: 400px; overflow-y: auto; width: 100%;">
                            </div>
                        </div>
                    </form>
                </div>
                <a href="javascript:void(0)" title="Support" onclick="if(typeof gilafChatbot!=='undefined'){if(!gilafChatbot.isOpen){gilafChatbot.toggleChat();}setTimeout(function(){gilafChatbot.showSupportOptions();},400);}else{window.location.href='<?= base_url('user/create_ticket.php'); ?>';}">
                    <i class="fas fa-headset"></i>
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= base_url('user/profile.php'); ?>" title="<?= htmlspecialchars($userName); ?>">
                        <i class="fas fa-user"></i>
                    </a>
                <?php else: ?>
                    <i class="fas fa-user" onclick="openLoginModal()" title="Login" style="cursor: pointer;"></i>
                <?php endif; ?>
                <a href="<?= base_url('cart.php'); ?>" aria-label="Shopping cart"
                    onclick="window.location.href='<?= base_url('cart.php'); ?>'; return true;"
                    style="position: relative; z-index: 1001; display: inline-block;">
                    <i class="fas fa-shopping-bag" style="pointer-events: none;"></i>
                    <?php if ($cartCount > 0): ?>
                        <span
                            style="position: absolute; top: -8px; right: -10px; background: #FF0000; color: white; font-size: 11px; font-weight: bold; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; pointer-events: none; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?= $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </header>

    <?php if (function_exists('display_flash')) {
        display_flash();
    } ?>
    <main>
