<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize region detection
require_once __DIR__ . '/region_detection.php';
require_once __DIR__ . '/currency_converter.php';
require_once __DIR__ . '/language_manager.php';

$pageTitle = $pageTitle ?? 'Gilaf Store | Taste • Culture • Craft';
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- New Design CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/new-design.css'); ?>?v=<?= time(); ?>">
    
    <!-- Mobile Navigation CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/mobile-nav.css'); ?>?v=<?= time(); ?>">
    <link rel="stylesheet" href="<?= asset_url('css/mobile-menu-redesign.css'); ?>?v=<?= time(); ?>">
    <link rel="stylesheet" href="<?= asset_url('css/navigation-separation.css'); ?>?v=<?= time(); ?>">
    
    <!-- Layout Fixes CSS - Comprehensive responsive and layout fixes -->
    <link rel="stylesheet" href="<?= asset_url('css/layout-fixes.css'); ?>">
    
    <!-- Tablet Layout Fixes - Dedicated tablet optimization (768px-1024px only) -->
    <link rel="stylesheet" href="<?= asset_url('css/tablet-layout-fixes.css'); ?>?v=<?= time(); ?>">
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
                <div id="promoCodeBanner" style="display: inline-flex; align-items: center; overflow: hidden; width: 450px; position: relative; background: rgba(197, 160, 89, 0.1); padding: 4px 8px; border-radius: 4px;">
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
                            <div class="promo-slide" data-index="<?= $index; ?>" style="<?= $index === 0 ? '' : 'display: none;'; ?> position: absolute; width: 100%; height: 100%;">
                                <div class="promo-marquee">
                                    <span class="promo-text" style="font-size: 0.75rem; font-weight: 600; color: var(--color-gold); white-space: nowrap; letter-spacing: 0.5px;">
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
                    <img id="current-flag" src="https://flagcdn.com/<?= strtolower($currentCountry['code']); ?>.svg" width="20" alt="<?= $currentCountry['code']; ?>" style="border-radius: 2px;">
                    <span id="current-currency" style="font-weight: 600;"><?= $currentCurrency; ?> (<?= $currentCurrencySymbol; ?>)</span>
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
        (function() {
            const slides = document.querySelectorAll('.promo-slide');
            if (slides.length === 0) return;
            
            let currentIndex = 0;
            
            // Initialize first slide
            if (slides[0]) {
                slides[0].classList.add('fade-in');
            }
            
            // Rotate between multiple promo codes if more than one
            if (slides.length > 1) {
                setInterval(function() {
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
    <header class="main-header" id="header" style="background: #244A36; backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);">
        <div class="container nav-container">
            <div class="menu-toggle"><i class="fas fa-bars"></i></div>
            <div class="logo">
                <a href="<?= base_url('index.php'); ?>">
                    <h1 style="color: #ffffff; -webkit-text-fill-color: #ffffff; background: none;">GILAF STORE</h1>
                    <span>Taste • Culture • Craft</span>
                </a>
            </div>
            <!-- Desktop Navigation -->
            <nav class="nav-links desktop-nav">
                <a href="<?= base_url('index.php'); ?>">HOME</a>
                <a href="<?= base_url('shop.php'); ?>">SHOP</a>
                <div class="dropdown">
                    <span class="dropbtn">SHOP BY CATEGORY <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px;"></i></span>
                    <div class="dropdown-content">
                        <?php
                        $categories = get_categories();
                        foreach ($categories as $cat):
                        ?>
                            <a href="<?= base_url('shop.php?category=' . $cat['id']); ?>"><?= htmlspecialchars($cat['name']); ?></a>
                        <?php endforeach; ?>
                        <hr style="margin: 8px 0; border: none; border-top: 1px solid #e0e0e0;">
                        <a href="<?= base_url('offers.php'); ?>">🎁 Offers & Deals</a>
                        <a href="<?= base_url('gifting-hampers.php'); ?>">🎀 Gifting & Hampers</a>
                    </div>
                </div>
                <div class="dropdown">
                    <span class="dropbtn">TRACK <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px;"></i></span>
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
                    <span class="dropbtn">OUR STORY <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px;"></i></span>
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
                    
                    <a href="#" onclick="openTrackingModal(); return false;" class="mobile-menu-item">
                        <i class="fas fa-box"></i>
                        <span>TRACK ORDER</span>
                    </a>
                    
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
                        <span class="mobile-utility-currency"><?= $currentCurrency; ?> (<?= $currentCurrencySymbol; ?>)</span>
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
                        <a href="<?= base_url('shop.php?category=' . $cat['id']); ?>" class="mobile-submenu-item"><?= strtoupper(htmlspecialchars($cat['name'])); ?></a>
                    <?php endforeach; ?>
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
                    <form id="searchForm" action="<?= base_url('search.php'); ?>" method="GET" style="position: absolute; top: 100%; right: 0; margin-top: 10px; display: none; z-index: 1000;">
                        <div style="background: white; border-radius: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; overflow: visible; position: relative;">
                            <input type="text" name="q" id="searchInput" placeholder="Search products..." style="width: 250px; padding: 12px 18px; border: none; outline: none; font-size: 0.9rem; font-family: 'Inter', sans-serif;" autocomplete="off" />
                            <button type="submit" style="background: #C5A089; border: none; padding: 12px 20px; cursor: pointer; transition: background 0.3s ease;" onmouseover="this.style.background='#d4b896'" onmouseout="this.style.background='#C5A089'">
                                <i class="fas fa-search" style="color: white;"></i>
                            </button>
                            <div id="searchAutocomplete" style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 8px; display: none; z-index: 1001; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); max-height: 400px; overflow-y: auto; width: 100%;"></div>
                        </div>
                    </form>
                </div>
                <a href="<?= base_url('contact.php'); ?>" title="Support">
                    <i class="fas fa-headset"></i>
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= base_url('user/profile.php'); ?>" title="<?= htmlspecialchars($userName); ?>">
                        <i class="fas fa-user"></i>
                    </a>
                <?php else: ?>
                    <i class="fas fa-user" onclick="openLoginModal()" title="Login" style="cursor: pointer;"></i>
                <?php endif; ?>
                <a href="<?= base_url('cart.php'); ?>" onclick="window.location.href='<?= base_url('cart.php'); ?>'; return true;" style="position: relative; z-index: 1001; display: inline-block;">
                    <i class="fas fa-shopping-bag" style="pointer-events: none;"></i>
                    <?php if ($cartCount > 0): ?>
                        <span style="position: absolute; top: -5px; right: -8px; background: var(--color-gold); color: white; font-size: 10px; padding: 2px 5px; border-radius: 50%; pointer-events: none;"><?= $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </header>

    <?php if (function_exists('display_flash')) { display_flash(); } ?>
    <main>
