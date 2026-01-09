<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = get_product($productId);

if (!$product) {
    redirect_with_message('/shop.php', 'Product not found', 'danger');
}

// Get user's region settings for currency conversion
$userRegion = get_user_region_settings();
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

$pageTitle = $product['name'] . ' — Gilaf Store';
$activePage = '';
$relatedProducts = get_related_products((int)$product['category_id'], $productId);
$reviews = get_reviews_for_product($productId);
$highlights = get_product_highlights($productId);
$variants = get_product_variants($productId);
$batchDetails = get_batch_details_for_product($productId);
$fssaiLicense = get_site_setting('fssai_license_number', '12724064000335');
$returnPolicy = get_site_setting('return_policy', 'Returns allowed only for damaged, defective, or incorrect products within 7 days of delivery');
$userHasPurchased = isset($_SESSION['user']) ? user_has_purchased_product((int)$_SESSION['user']['id'], $productId) : false;
$productRating = get_product_rating($productId);

// Get new product sections
$productSections = get_product_sections($productId);
$storageSection = get_product_section_by_type($productId, 'storage');
$descriptionSection = get_product_section_by_type($productId, 'description');
$nutritionalSection = get_product_section_by_type($productId, 'nutritional');
$shippingSection = get_product_section_by_type($productId, 'shipping');

// Get product reviews and ratings
$productReviews = get_product_reviews($productId);
$ratingData = get_product_average_rating($productId);

// Track product view event
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    // Primary product view tracking (existing behavior)
    trackProductEvent($productId, 'view', 'product_page', $product['category_id'], $product['price']);

    // Server-side click tracking following the same pattern as page views
    // If the visitor arrived here from the homepage or shop page, count it as a product click
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referrer)) {
        $refPath = parse_url($referrer, PHP_URL_PATH) ?? '';
        $clickSource = null;

        // Treat navigations from shop listing as shop page clicks
        if (strpos($refPath, 'shop.php') !== false) {
            $clickSource = 'shop_page';
        }
        // Treat navigations from the main homepage as homepage clicks
        elseif (strpos($refPath, 'index.php') !== false || $refPath === '/' || substr($refPath, -1) === '/') {
            $clickSource = 'homepage';
        }

        if ($clickSource !== null) {
            trackProductEvent($productId, 'click', $clickSource, $product['category_id'], $product['price']);
        }
    }
}

include __DIR__ . '/includes/new-header.php';
?>

<!-- Product Detail Page CSS -->
<link rel="stylesheet" href="<?= asset_url('css/product-detail-page.css'); ?>">

<section class="py-5 product-detail-page">
  <div class="container">
    <div class="product-detail-grid">
      <!-- Left Column: Product Image -->
      <div class="product-image-section">
        <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="<?= htmlspecialchars($product['name']); ?>" class="product-main-image" id="mainProductImage" />
        <div class="product-thumbnail-list">
          <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="Thumbnail 1" class="product-thumbnail active" onclick="changeMainImage(this.src)" />
          <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="Thumbnail 2" class="product-thumbnail" onclick="changeMainImage(this.src)" />
          <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="Thumbnail 3" class="product-thumbnail" onclick="changeMainImage(this.src)" />
        </div>
        
        <!-- Product Description Section -->
        <?php if ($descriptionSection): ?>
        <div class="product-highlights-bullets" style="margin-top: 24px;">
          <h3 class="highlights-bullets-title">
            📝 Product Description
          </h3>
          <p style="color: #6c757d; font-size: 14px; line-height: 1.8; margin: 0;"><?= nl2br(htmlspecialchars($descriptionSection['content'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Nutritional & Usage Information Section -->
        <?php if ($nutritionalSection): ?>
        <div class="product-highlights-bullets" style="margin-top: 24px;">
          <h3 class="highlights-bullets-title">
            🌿 Nutritional & Usage Information
          </h3>
          <ul class="highlights-bullets-list">
            <?php foreach (explode("\n", $nutritionalSection['content']) as $line): ?>
              <?php if (trim($line)): ?>
                <li class="highlight-bullet-item"><?= htmlspecialchars($line); ?></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right Column: Product Info -->
      <div class="product-info-section">
        <div class="product-brand">GILAF - TASTE • CULTURE • CRAFT</div>
        <h1 class="product-title"><?= htmlspecialchars($product['name']); ?></h1>
        <div class="product-size-info"><?= htmlspecialchars($batchDetails['net_quantity'] ?? '125gm'); ?> (Pack of 1)</div>
        
        <!-- Shipping Badges -->
        <div class="shipping-badges">
          <?= get_shipping_badge_html($product['shipping_type'] ?? 'domestic'); ?>
        </div>
        
        <!-- Certification Badges (Lab Tested & Organic) -->
        <?php if ($batchDetails && ($batchDetails['is_lab_tested'] || $batchDetails['is_organic'])): ?>
        <div class="certification-badges" style="display: flex; gap: 8px; margin-top: 12px;">
          <?php if ($batchDetails['is_lab_tested']): ?>
            <span class="badge-lab-tested">
              <i class="fas fa-flask"></i> LAB TESTED
            </span>
          <?php endif; ?>
          <?php if ($batchDetails['is_organic']): ?>
            <span class="badge-organic">
              <i class="fas fa-leaf"></i> ORGANIC
            </span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="product-price-section">
          <?php 
            $convertedPrice = convert_currency($product['price'], $currentCurrency);
            $displayPrice = display_price($product['price'], $currentCurrency, $currentCurrencySymbol);
          ?>
          <span class="product-price"><?= $displayPrice; ?></span>
          <svg class="product-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        
        <div class="product-rating-section">
          <span class="product-rating-badge">
            <span class="product-rating-star">★</span>
            <span><?= $productRating['rating']; ?></span>
          </span>
          <?php if ($productRating['is_actual']): ?>
            <span class="product-rating-count"><?= $productRating['count']; ?> Rating<?= $productRating['count'] != 1 ? 's' : ''; ?></span>
          <?php else: ?>
            <span class="product-rating-count" style="color: #9ca3af;">No ratings yet</span>
          <?php endif; ?>
          <span style="color: #10b981; font-size: 14px;">✓ Assured</span>
        </div>
        
        <div class="product-delivery-info">
          <svg class="product-delivery-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
          </svg>
          <span class="product-delivery-text">Delivery ₹67</span>
          <span class="product-delivery-price">₹70</span>
        </div>
        
        <?php if ($variants && count($variants) > 0): ?>
        <div class="product-size-selector">
          <label class="size-selector-label">Select Size</label>
          <div class="size-options">
            <?php foreach ($variants as $index => $variant): ?>
              <button class="size-option <?= $index === 0 ? 'active' : ''; ?>" 
                      data-variant-id="<?= $variant['id']; ?>" 
                      data-price="<?= $variant['price']; ?>"
                      data-stock="<?= $variant['stock']; ?>"
                      onclick="selectVariant(this)">
                <?= htmlspecialchars($variant['size']); ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        
        <form action="<?= base_url('cart.php'); ?>" method="post" id="productForm">
          <input type="hidden" name="action" value="add" />
          <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>" />
          <input type="hidden" name="quantity" value="1" />
          
          <div class="product-actions">
            <button type="submit" class="btn-add-to-cart">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              Add to Cart
            </button>
            <button type="button" class="btn-buy-now" onclick="buyNow()">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
              </svg>
              Buy Now
            </button>
          </div>
        </form>
        
        <!-- Product Benefits Section -->
        <?php if ($highlights): ?>
        <div class="product-highlights-bullets" style="margin-top: 24px;">
          <h3 class="highlights-bullets-title">
            ⭐ Product Benefits
          </h3>
          <ul class="highlights-bullets-list">
            <?php foreach ($highlights as $highlight): ?>
              <li class="highlight-bullet-item"><?= htmlspecialchars($highlight['highlight_text']); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        
        <div class="product-details-expandable">
          <div class="details-toggle" onclick="toggleProductDetails(this)">
            <span class="details-toggle-title">Product Details</span>
            <svg class="details-toggle-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </div>
          <div class="details-content">
            <div class="detail-row">
              <span class="detail-label">Brand:</span>
              <span class="detail-value"><?= htmlspecialchars($product['brand'] ?? 'Gilaf'); ?></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Net Quantity:</span>
              <span class="detail-value"><?= htmlspecialchars($batchDetails['net_quantity'] ?? 'As mentioned on pack'); ?></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Food Type:</span>
              <span class="detail-value"><?= htmlspecialchars($product['food_type'] ?? 'Vegetarian'); ?></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Ingredients:</span>
              <span class="detail-value"><?= htmlspecialchars($product['ingredients'] ?? '100% natural'); ?></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">FSSAI License:</span>
              <span class="detail-value"><?= htmlspecialchars($fssaiLicense); ?></span>
            </div>
          </div>
        </div>
        
        
        <?php if ($storageSection): ?>
        <div class="product-highlights-bullets" style="margin-top: 16px;">
          <h3 class="highlights-bullets-title">📦 Storage & Shelf Life</h3>
          <ul class="highlights-bullets-list">
            <?php foreach (explode("\n", $storageSection['content']) as $line): ?>
              <?php if (trim($line)): ?>
                <li class="highlight-bullet-item"><?= htmlspecialchars($line); ?></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($shippingSection): ?>
        <div class="product-highlights-bullets" style="margin-top: 16px;">
          <h3 class="highlights-bullets-title">🚚 Shipping & Returns</h3>
          <ul class="highlights-bullets-list">
            <?php foreach (explode("\n", $shippingSection['content']) as $line): ?>
              <?php if (trim($line)): ?>
                <li class="highlight-bullet-item"><?= htmlspecialchars($line); ?></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        
        <div class="product-highlights-bullets" style="margin-top: 16px;">
          <h3 class="highlights-bullets-title">🔒 Secure Checkout</h3>
          <div style="background: #e8f5e9; padding: 16px; border-radius: 8px; border-left: 3px solid #4caf50;">
            <p style="color: #2e7d32; font-size: 14px; margin: 0; font-weight: 500; line-height: 1.6;">
              <i class="fas fa-lock me-2"></i>All payments are SSL-encrypted and backed by secured sessions.
            </p>
          </div>
        </div>
        
      </div>
    </div>

    <?php if ($relatedProducts): ?>
      <section class="mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="fw-semibold">You may also like</h4>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" data-mdb-target="#relatedCarousel" data-mdb-slide="prev"><i class="fas fa-chevron-left"></i></button>
            <button class="btn btn-outline-primary btn-sm" data-mdb-target="#relatedCarousel" data-mdb-slide="next"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <div id="relatedCarousel" class="carousel slide related-products-carousel" data-mdb-ride="carousel">
          <div class="carousel-inner">
            <?php foreach (array_chunk($relatedProducts, 3) as $index => $group): ?>
              <div class="carousel-item <?= $index === 0 ? 'active' : ''; ?>">
                <div class="row g-4">
                  <?php foreach ($group as $related): ?>
                    <div class="col-md-4">
                      <div class="card product-card h-100">
                        <img src="<?= asset_url('images/products/' . htmlspecialchars($related['image'])); ?>" class="card-img-top" alt="<?= htmlspecialchars($related['name']); ?>" />
                        <div class="card-body">
                          <h6 class="fw-semibold mb-1"><?= htmlspecialchars($related['name']); ?></h6>
                          <p class="text-primary fw-semibold">$<?= number_format((float)$related['price'], 2); ?></p>
                          <a href="<?= base_url('product.php?id=' . (int)$related['id']); ?>" class="btn btn-outline-primary btn-sm rounded-pill">View</a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</section>

<script>
// Change main product image when thumbnail is clicked
function changeMainImage(src) {
  document.getElementById('mainProductImage').src = src;
  
  // Update active thumbnail
  document.querySelectorAll('.product-thumbnail').forEach(thumb => {
    thumb.classList.remove('active');
  });
  event.target.classList.add('active');
}

// Toggle product details section
function toggleProductDetails(element) {
  element.classList.toggle('open');
}

// Toggle return policy section
function toggleReturnPolicy(element) {
  element.classList.toggle('open');
}

// Select product variant
function selectVariant(button) {
  // Remove active class from all size options
  document.querySelectorAll('.size-option').forEach(opt => {
    opt.classList.remove('active');
  });
  
  // Add active class to selected option
  button.classList.add('active');
  
  // Update price
  const price = button.getAttribute('data-price');
  const priceElement = document.querySelector('.product-price');
  if (priceElement && price) {
    priceElement.innerHTML = '<span class="product-price-symbol">₹</span>' + parseFloat(price).toFixed(0);
  }
  
  // Update stock info (if you have a stock display element)
  const stock = button.getAttribute('data-stock');
  console.log('Selected variant - Price: ₹' + price + ', Stock: ' + stock);
}

// Buy Now functionality
function buyNow() {
  const form = document.getElementById('productForm');
  const formData = new FormData(form);
  
  // Add to cart first
  fetch(form.action, {
    method: 'POST',
    body: formData
  }).then(() => {
    // Redirect to checkout
    window.location.href = '<?= base_url('checkout.php'); ?>';
  });
}

// Copy product highlights
document.querySelector('.highlights-copy-btn')?.addEventListener('click', function() {
  const highlights = document.querySelectorAll('.highlight-item');
  let text = 'Product Highlights:\n\n';
  
  highlights.forEach(item => {
    const label = item.querySelector('.highlight-label').textContent;
    const value = item.querySelector('.highlight-value').textContent;
    text += `${label}: ${value}\n`;
  });
  
  navigator.clipboard.writeText(text).then(() => {
    this.textContent = 'COPIED!';
    setTimeout(() => {
      this.textContent = 'COPY';
    }, 2000);
  });
});
</script>

<?php
include __DIR__ . '/includes/new-footer.php';
?>
