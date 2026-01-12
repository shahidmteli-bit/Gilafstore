<?php
require_once __DIR__ . '/includes/functions.php';

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($productId > 0 && $quantity > 0) {
        // Verify product exists
        $product = db_fetch("SELECT id, name, price, weight, batch_code FROM products WHERE id = ?", [$productId]);
        
        if ($product) {
            // Add or update cart
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId] += $quantity;
            } else {
                $_SESSION['cart'][$productId] = $quantity;
            }
            
            // Track add to cart event for analytics
            if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
                trackProductEvent($productId, 'add_to_cart', 'shop_page', $product['category_id'] ?? null, $product['price'], $quantity);
            }
            
            // Redirect to prevent form resubmission
            header('Location: ' . base_url('shop.php?added=' . $productId));
            exit;
        } else {
            $_SESSION['cart_error'] = 'Product not found!';
        }
    } else {
        $_SESSION['cart_error'] = 'Invalid product or quantity!';
    }
}

$pageTitle = 'Shop — Gilaf Store';
$activePage = 'shop';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';

// Get user's region settings for currency conversion
$userRegion = get_user_region_settings();
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';

$filters = [
    'category_id' => $categoryId,
    'search' => $search,
    'sort' => $sort,
];

$categories = get_categories();
$products = get_products($filters);
include __DIR__ . '/includes/new-header.php';
?>

<?php if (isset($_SESSION['cart_message'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['cart_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['cart_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['cart_error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['cart_error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['cart_error']); ?>
<?php endif; ?>

<!-- Premium Design System CSS -->
<link rel="stylesheet" href="<?= asset_url('css/premium-design-system.css'); ?>">
<link rel="stylesheet" href="<?= asset_url('css/shop-page.css'); ?>">
<link rel="stylesheet" href="<?= asset_url('css/shop-page-fixes.css'); ?>">
<link rel="stylesheet" href="<?= asset_url('css/product-card-button-fixes.css'); ?>">
<link rel="stylesheet" href="<?= asset_url('css/shop-ads-panel.css?v=' . time()); ?>">

<!-- Critical Inline CSS for Full-Width Hero Promo Billboard -->
<style>
/* GLOBAL: Hide VIEW button on all devices */
.btn-danger[href*="product.php"],
a.btn-danger {
    display: none !important;
}

/* MOBILE: Reduce top padding on shop page */
@media (max-width: 768px) {
    section.py-5 {
        padding-top: 1rem !important;
        padding-bottom: 3rem !important;
    }
    
    .shop-breadcrumb {
        margin-bottom: 1rem !important;
    }
    
    .shop-page {
        padding-top: 0 !important;
        margin-top: 0 !important;
    }
    
    .shop-products {
        padding-top: 0 !important;
        margin-top: 0 !important;
    }
}

/* FORCE 2-COLUMN MOBILE PRODUCT GRID */
@media (max-width: 768px) {
    .products-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
        width: 100% !important;
    }
    
    .product-card {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .product-card-body {
        padding: 10px !important;
    }
    
    .product-card-title {
        font-size: 13px !important;
    }
    
    .product-card-category {
        font-size: 9px !important;
    }
    
    /* Fix button layout for 2-column grid */
    .product-card-body > div[style*="display: flex"] {
        flex-direction: column !important;
        gap: 6px !important;
        width: 100% !important;
    }
    
    /* Hide VIEW button - keep only ADD TO CART */
    .product-card-body .btn-danger,
    .product-card-body a.btn[href*="product.php"] {
        display: none !important;
    }
    
    .product-card-body .btn-primary {
        width: 100% !important;
        padding: 10px !important;
        font-size: 12px !important;
        white-space: nowrap !important;
    }
}

.shop-header-ad-panel { display: none; }
@media (min-width: 768px) {
    .shop-header-ad-panel { display: block; flex: 0 0 auto; margin-left: auto; margin-right: 16px; width: 50%; max-width: 50%; box-sizing: border-box; }
}
.promo-carousel { position: relative; height: 120px; width: 100%; overflow: hidden; border-radius: 12px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1); }
.promo-carousel-track { display: flex; gap: 0; height: 100%; width: 700%; overflow-x: hidden; transition: transform 0.6s ease-in-out; scrollbar-width: none; -ms-overflow-style: none; }
.promo-carousel-track::-webkit-scrollbar { display: none; }
.promo-card { flex: 0 0 calc(100% / 7); min-width: calc(100% / 7); width: calc(100% / 7); height: 100%; border-radius: 16px; position: relative; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease; }
.promo-card:hover { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2); }
.promo-card-1 { background: linear-gradient(135deg, #C9A961 0%, #D4B76A 20%, #1A3C34 60%, #244A36 100%); }
.promo-card-2 { background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 50%, #FE6B8B 100%); }
.promo-card-3 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.promo-card-4 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.promo-card-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.promo-card-6 { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
.promo-card-7 { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
.promo-card-content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: row; align-items: center; justify-content: space-between; padding: 20px 30px; text-align: left; }
.promo-card-badge { display: inline-block; background: rgba(255, 255, 255, 0.95); color: #1A3C34; padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 6px; width: fit-content; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15); text-transform: uppercase; }
.promo-card-title { font-size: 1.5rem; font-weight: 800; color: #ffffff; margin-bottom: 4px; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3); font-family: 'Poppins', sans-serif; line-height: 1.2; letter-spacing: -0.5px; }
.promo-card-text { font-size: 0.85rem; color: rgba(255, 255, 255, 0.9); margin-bottom: 0; line-height: 1.3; font-weight: 500; }
.promo-card-btn { display: inline-block; background: #ffffff; color: #1A3C34; padding: 8px 20px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2); width: fit-content; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
.promo-card-btn:hover { background: #C9A961; color: #ffffff; transform: translateY(-3px); box-shadow: 0 8px 24px rgba(201, 169, 97, 0.5); }
.promo-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.9); border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15); }
.promo-nav:hover { background: #C9A961; color: #ffffff; transform: translateY(-50%) scale(1.1); }
.promo-nav-prev { left: 10px; }
.promo-nav-next { right: 10px; }
.promo-nav i { font-size: 0.9rem; }
@media (min-width: 1024px) {
    .promo-carousel { height: 120px; }
}
@media (min-width: 1200px) {
    .promo-carousel { height: 130px; }
}
/* Professional Sort Dropdown Styling - Inline Layout */
.shop-header-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 0;
}
.shop-sort { 
    display: flex; 
    flex-direction: column;
    align-items: flex-start; 
    gap: 8px;
    margin-top: 12px;
}
.shop-sort-label { 
    font-size: 0.9rem; 
    font-weight: 600; 
    color: #1A3C34;
    font-family: 'Poppins', sans-serif;
    margin: 0;
}
.shop-sort-select { 
    padding: 8px 14px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #333;
    background: #ffffff;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    min-width: 180px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
.shop-sort-select:hover {
    border-color: #C9A961;
    box-shadow: 0 4px 12px rgba(201, 169, 97, 0.2);
}
.shop-sort-select:focus {
    outline: none;
    border-color: #C9A961;
    box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
}
.shop-header {
    overflow: hidden;
}
.shop-header-top {
    overflow: visible;
}
.shop-products {
    overflow: hidden;
}
/* Trust Highlights Auto-Slider Styles */
.trust-highlights-container {
    position: relative;
    overflow: hidden;
}
.trust-highlights-track {
    position: relative;
    width: 100%;
    height: 100%;
}
.trust-highlight-card {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    opacity: 0;
    transition: opacity 0.6s ease-in-out;
    pointer-events: none;
}
.trust-highlight-card.active {
    opacity: 1;
    pointer-events: auto;
}
@media (min-width: 768px) {
    .shop-header-ad-panel { 
        display: block; 
        flex: 0 0 auto; 
        margin-left: auto; 
        width: 60%; 
        max-width: 60%;
        box-sizing: border-box;
    }
}
</style>

<section class="py-5" style="background: var(--neutral-50); min-height: 100vh;">
  <div class="container">
    <!-- Breadcrumb -->
    <nav class="shop-breadcrumb" aria-label="breadcrumb">
      <a href="<?= base_url('index.php'); ?>" class="breadcrumb-item">Home</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-item active">Shop</span>
    </nav>

    <div class="shop-page">
      <!-- Sticky Filter Sidebar -->
      <aside class="shop-filters" id="shopFilters">
        <div class="shop-filters-header">
          <h2 class="shop-filters-title">Filters</h2>
          <button class="shop-filters-clear" onclick="window.location.href='<?= base_url('shop.php'); ?>'">Clear All</button>
        </div>

        <!-- Category Filter -->
        <div class="filter-section">
          <h3 class="filter-section-title">
            Categories
            <span class="filter-section-toggle">▼</span>
          </h3>
          <div class="filter-section-content">
            <label class="filter-option">
              <input type="checkbox" class="filter-checkbox" <?= !$categoryId ? 'checked' : ''; ?> onchange="window.location.href='<?= base_url('shop.php'); ?>'">
              <span class="filter-label">All Products</span>
              <span class="filter-count"><?= count($products); ?></span>
            </label>
            <?php foreach ($categories as $category): ?>
              <label class="filter-option">
                <input type="checkbox" class="filter-checkbox" <?= $categoryId === (int)$category['id'] ? 'checked' : ''; ?> onchange="window.location.href='<?= base_url('shop.php?category=' . (int)$category['id']); ?>'">
                <span class="filter-label"><?= htmlspecialchars($category['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Highlights & Trust Panel -->
        <div class="card" style="margin-top: var(--space-6); border: 2px solid rgba(201, 169, 97, 0.4); background: linear-gradient(135deg, rgba(255, 250, 240, 0.95) 0%, rgba(255, 255, 255, 0.95) 50%, rgba(250, 245, 235, 0.95) 100%); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: 0 10px 40px rgba(201, 169, 97, 0.2); position: relative; overflow: hidden; border-radius: 16px;">
          <div class="card-body" style="padding: 20px;">
            <h4 style="font-size: 0.82rem; font-weight: 800; color: #1A3C34; margin-bottom: 12px; font-family: 'Poppins', sans-serif; text-align: center; letter-spacing: 0.6px; text-transform: uppercase; background: linear-gradient(135deg, #B8935A, #C9A961, #D4B76A); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; text-shadow: 0 1px 2px rgba(0,0,0,0.05);">✨ WHY CHOOSE US</h4>
            
            <!-- Auto-sliding Highlights -->
            <div class="trust-highlights-container" style="position: relative; height: 68px; margin-bottom: 0;">
              <div class="trust-highlights-track">
                <!-- Slide 1: Fast Delivery -->
                <div class="trust-highlight-card active">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 252, 248, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(201, 169, 97, 0.18); border: 1.5px solid rgba(201, 169, 97, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #C9A961, #D4B76A); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(201, 169, 97, 0.4);">🚀</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Fast Delivery</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Quick shipping</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 2: Easy Returns -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(252, 250, 255, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(102, 126, 234, 0.18); border: 1.5px solid rgba(102, 126, 234, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);">↩️</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Easy Returns</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Hassle-free policy</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 3: Secure Checkout -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 255, 255, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(48, 207, 208, 0.18); border: 1.5px solid rgba(48, 207, 208, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #30cfd0, #330867); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(48, 207, 208, 0.4);">🔒</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Secure Checkout</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Safe payments</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 4: Lab Tested -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 250, 250, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(255, 107, 107, 0.18); border: 1.5px solid rgba(255, 107, 107, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #FF6B6B, #FF8E53); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);">🔬</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Lab Tested</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Quality verified</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 5: Traceable Sourcing -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 255, 253, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(168, 237, 234, 0.18); border: 1.5px solid rgba(168, 237, 234, 0.35);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #a8edea, #fed6e3); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(168, 237, 234, 0.4);">📍</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Traceable Source</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Know origin</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 6: Certified Quality -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 250, 255, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(240, 147, 251, 0.18); border: 1.5px solid rgba(240, 147, 251, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #f093fb, #f5576c); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);">✓</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Certified Quality</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Premium grade</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 7: Trusted Brand -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 253, 248, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(250, 112, 154, 0.18); border: 1.5px solid rgba(250, 112, 154, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #fa709a, #fee140); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(250, 112, 154, 0.4);">⭐</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Trusted Brand</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Happy customers</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 8: Premium Packaging -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 252, 248, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(201, 169, 97, 0.18); border: 1.5px solid rgba(201, 169, 97, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #C9A961, #1A3C34); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(201, 169, 97, 0.4);">📦</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Premium Pack</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Gift-ready boxes</div>
                    </div>
                  </div>
                </div>
                
                <!-- Slide 9: Quality Guaranteed -->
                <div class="trust-highlight-card">
                  <div style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(252, 250, 255, 0.98) 100%); backdrop-filter: blur(10px); padding: 10px; border-radius: 8px; box-shadow: 0 3px 15px rgba(102, 126, 234, 0.18); border: 1.5px solid rgba(102, 126, 234, 0.3);">
                    <div style="flex-shrink: 0; width: 36px; height: 36px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);">🏆</div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 0.8rem; font-weight: 700; color: #0d1f1a; margin-bottom: 2px; font-family: 'Poppins', sans-serif; letter-spacing: 0.05px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Quality Assured</div>
                      <div style="font-size: 0.68rem; color: #3a4a45; line-height: 1.2; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">100% satisfaction</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </aside>
      <!-- Main Content -->
      <div class="shop-products">
        <!-- Shop Header with Search & Controls -->
        <div class="shop-header">
          <div class="shop-header-top">
            <div>
              <h1 class="shop-title">Our Products</h1>
              <p class="shop-results-count"><?= count($products); ?> products found</p>
              
              <!-- Sort Control -->
              <div class="shop-sort">
                <label class="shop-sort-label">Sort by:</label>
                <select class="shop-sort-select" name="sort" onchange="this.form.submit()">
                  <option value="">Default</option>
                  <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                  <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                  <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Alphabetical</option>
                </select>
              </div>
            </div>
            
            <!-- Advertisement & Promotional Display Area -->
            <div class="shop-header-ad-panel">
              <div class="promo-carousel">
                <div class="promo-carousel-track">
                  <!-- Offer Card 1 -->
                  <div class="promo-card promo-card-1">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">🎉 SPECIAL OFFER</span>
                      <h3 class="promo-card-title">Up to 50% OFF</h3>
                      <p class="promo-card-text">Premium Kashmiri saffron</p>
                      <a href="<?= base_url('offers.php'); ?>" class="promo-card-btn">Shop Now</a>
                    </div>
                  </div>

                  <!-- Offer Card 2 -->
                  <div class="promo-card promo-card-2">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">✨ NEW ARRIVALS</span>
                      <h3 class="promo-card-title">Fresh Collection</h3>
                      <p class="promo-card-text">Latest authentic products</p>
                      <a href="<?= base_url('shop.php'); ?>" class="promo-card-btn">Explore</a>
                    </div>
                  </div>

                  <!-- Offer Card 3 -->
                  <div class="promo-card promo-card-3">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">🎁 GIFT HAMPERS</span>
                      <h3 class="promo-card-title">Premium Hampers</h3>
                      <p class="promo-card-text">Perfect for every occasion</p>
                      <a href="<?= base_url('gifting-hampers.php'); ?>" class="promo-card-btn">View</a>
                    </div>
                  </div>

                  <!-- Offer Card 4 - Promo Code -->
                  <div class="promo-card promo-card-4">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">💳 PROMO CODE</span>
                      <h3 class="promo-card-title">SAVE20</h3>
                      <p class="promo-card-text">Get 20% off on first order</p>
                      <a href="<?= base_url('shop.php'); ?>" class="promo-card-btn">Use Code</a>
                    </div>
                  </div>

                  <!-- Offer Card 5 - Limited Time Deal -->
                  <div class="promo-card promo-card-5">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">⏰ LIMITED TIME</span>
                      <h3 class="promo-card-title">Flash Sale</h3>
                      <p class="promo-card-text">24 hours only - Up to 40% off</p>
                      <a href="<?= base_url('offers.php'); ?>" class="promo-card-btn">Shop Now</a>
                    </div>
                  </div>

                  <!-- Offer Card 6 - Free Shipping -->
                  <div class="promo-card promo-card-6">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">🚚 FREE SHIPPING</span>
                      <h3 class="promo-card-title">Orders Above ₹999</h3>
                      <p class="promo-card-text">No delivery charges nationwide</p>
                      <a href="<?= base_url('shop.php'); ?>" class="promo-card-btn">Shop Now</a>
                    </div>
                  </div>

                  <!-- Offer Card 7 - Organic Products -->
                  <div class="promo-card promo-card-7">
                    <div class="promo-card-content">
                      <span class="promo-card-badge">🌿 100% ORGANIC</span>
                      <h3 class="promo-card-title">Pure & Natural</h3>
                      <p class="promo-card-text">Certified organic spices</p>
                      <a href="<?= base_url('shop.php?category=organic'); ?>" class="promo-card-btn">Explore</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Products Grid -->
        <div class="products-grid">
          <?php foreach ($products as $product): ?>
            <div class="product-card animate-fadeIn" data-product-url="<?= base_url('product.php?id=' . (int)$product['id']); ?>" data-product-id="<?= (int)$product['id']; ?>" tabindex="0" role="link" aria-label="View details for <?= htmlspecialchars($product['name']); ?>">
              <div class="product-card-image">
                <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                <?php if (isset($product['is_new']) && $product['is_new']): ?>
                  <span class="product-card-badge">NEW</span>
                <?php endif; ?>
              </div>
              <div class="product-card-body">
                <div class="product-card-category"><?= htmlspecialchars($product['category_name'] ?? 'Product'); ?></div>
                <h3 class="product-card-title"><?= htmlspecialchars($product['name']); ?></h3>
                <div class="product-card-meta">
                  <span>⭐ 4.8</span>
                  <span>•</span>
                  <span>120 reviews</span>
                </div>
                <div class="product-card-price">
                  <?php 
                    $convertedPrice = convert_currency($product['price'], $currentCurrency);
                    $displayPrice = display_price($product['price'], $currentCurrency, $currentCurrencySymbol);
                  ?>
                  <span class="product-card-price-current"><?= $displayPrice; ?></span>
                  <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): 
                    $convertedOriginal = convert_currency($product['original_price'], $currentCurrency);
                    $displayOriginal = display_price($product['original_price'], $currentCurrency, $currentCurrencySymbol);
                  ?>
                    <span class="product-card-price-original"><?= $displayOriginal; ?></span>
                    <span class="product-card-discount"><?= round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>% OFF</span>
                  <?php endif; ?>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                  <a href="<?= base_url('product.php?id=' . $product['id']); ?>" class="btn btn-sm btn-danger">VIEW</a>
                  <form action="<?= base_url('shop.php'); ?>" method="post" style="display: inline;">
                    <input type="hidden" name="action" value="add_to_cart" />
                    <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>" />
                    <input type="hidden" name="quantity" value="1" />
                    <button type="submit" class="btn btn-sm btn-success">ADD TO CART</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <script>
          (function() {
            const interactiveSelectors = 'a, button, input, select, textarea, label, [role="button"], [role="link"], [data-no-card-nav]';

            function shouldIgnore(eventTarget) {
              return eventTarget.closest(interactiveSelectors) !== null;
            }

            function handleNavigation(card) {
              const url = card.getAttribute('data-product-url');
              const productId = card.getAttribute('data-product-id');
              
              if (url && productId) {
                // Track click before navigation using global trackClick function
                trackClick(parseInt(productId), 'shop_page');
                
                // Small delay to ensure tracking completes
                setTimeout(() => {
                  window.location.href = url;
                }, 100);
              } else if (url) {
                window.location.href = url;
              }
            }

            document.querySelectorAll('.product-card[data-product-url]').forEach(card => {
              card.addEventListener('click', event => {
                if (shouldIgnore(event.target)) return;
                handleNavigation(card);
              });

              card.addEventListener('keydown', event => {
                const isEnter = event.key === 'Enter';
                const isSpace = event.key === ' ' || event.key === 'Spacebar';
                if (!isEnter && !isSpace) return;
                if (shouldIgnore(event.target)) return;
                event.preventDefault();
                handleNavigation(card);
              });
            });
          })();
        </script>

        <?php if (!$products): ?>
          <div class="shop-empty">
            <div class="shop-empty-icon">🔍</div>
            <h2 class="shop-empty-title">No Products Found</h2>
            <p class="shop-empty-text">Try adjusting your filters or search terms</p>
            <a href="<?= base_url('shop.php'); ?>" class="btn btn-primary btn-lg">Clear Filters</a>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<script>
// Auto-scroll carousel - One slide at a time
(function() {
  const track = document.querySelector('.promo-carousel-track');
  if (!track) return;
  
  const carousel = document.querySelector('.promo-carousel');
  const cards = document.querySelectorAll('.promo-card');
  let currentIndex = 0;
  
  function scrollToSlide(index) {
    const carouselWidth = carousel.offsetWidth;
    track.style.transform = `translateX(-${index * carouselWidth}px)`;
  }
  
  function nextSlide() {
    currentIndex++;
    if (currentIndex >= cards.length) {
      currentIndex = 0;
    }
    scrollToSlide(currentIndex);
  }
  
  let autoScrollInterval = setInterval(nextSlide, 5000);
  
  // Pause auto-scroll on hover
  track.addEventListener('mouseenter', () => clearInterval(autoScrollInterval));
  track.addEventListener('mouseleave', () => {
    autoScrollInterval = setInterval(nextSlide, 5000);
  });
})();

// Trust Highlights Auto-Slider
(function() {
  const cards = document.querySelectorAll('.trust-highlight-card');
  if (!cards.length) return;
  
  let currentIndex = 0;
  
  function showSlide(index) {
    cards.forEach((card, i) => {
      card.classList.remove('active');
      if (i === index) {
        card.classList.add('active');
      }
    });
  }
  
  function nextSlide() {
    currentIndex++;
    if (currentIndex >= cards.length) {
      currentIndex = 0;
    }
    showSlide(currentIndex);
  }
  
  // Auto-advance every 3 seconds
  setInterval(nextSlide, 3000);
})();
</script>

<!-- Mobile Filter Toggle -->
<button class="shop-filters-mobile-toggle" onclick="document.getElementById('shopFilters').classList.toggle('open'); document.getElementById('filterOverlay').classList.toggle('open');">
  <i class="fas fa-filter"></i> Filters
</button>
<div class="shop-filters-overlay" id="filterOverlay" onclick="document.getElementById('shopFilters').classList.remove('open'); this.classList.remove('open');"></div>

<?php
include __DIR__ . '/includes/new-footer.php';
?>
