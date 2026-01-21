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

// Build product images array (use image_1..image_4 if available, fallback to main image)
$productImages = [];
foreach (['image_1', 'image_2', 'image_3', 'image_4'] as $imageField) {
    if (!empty($product[$imageField])) {
        $productImages[] = $product[$imageField];
    }
}
if (empty($productImages) && !empty($product['image'])) {
    $productImages[] = $product['image'];
}

$mainProductImage = $productImages[0] ?? $product['image'];

// Fetch all weights for this product (including EAN for display)
$db = get_db_connection();
$stmt = $db->prepare("SELECT id, weight_value, weight_unit, display_weight, price, ean, is_default FROM product_weights WHERE product_id = ? ORDER BY sort_order ASC, weight_value ASC");
$stmt->execute([$productId]);
$productWeights = $stmt->fetchAll(PDO::FETCH_ASSOC);
$fssaiLicense = get_site_setting('fssai_license_number', '12724064000335');
$returnPolicy = get_site_setting('return_policy', 'Returns allowed only for damaged, defective, or incorrect products within 7 days of delivery');

// Get dynamic shipping/delivery fee from admin settings
$productShippingType = $product['shipping_type'] ?? 'domestic';
$shippingSettings = get_shipping_settings($productShippingType);
$deliveryFee = (float)($shippingSettings['base_charge'] ?? 50.00);
$freeShippingThreshold = (float)($shippingSettings['free_shipping_threshold'] ?? 500.00);
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

<!-- Inline Zoom Styles (fallback) -->
<style>
.product-image-zoom-container { position: relative; display: block; width: 100%; }
.product-main-image-wrapper { position: relative; overflow: visible; cursor: crosshair; border-radius: 16px; border: 1px solid #e9ecef; background: #ffffff; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
.product-main-image-wrapper .product-main-image { width: 100%; max-width: 100%; height: auto; display: block; border: none; padding: 0; box-shadow: none; }
#zoomLens { position: absolute; border: 3px solid #d4af37; width: 120px; height: 120px; background-color: rgba(212,175,55,0.2); pointer-events: none; display: none; z-index: 100; border-radius: 4px; }
#zoomResult { position: fixed; width: 400px; height: 400px; border: 2px solid #d4af37; background-color: #fff; box-shadow: 0 8px 40px rgba(0,0,0,0.3); border-radius: 12px; display: none; z-index: 99999; overflow: hidden; pointer-events: none; }
#zoomResult img { position: absolute; max-width: none; pointer-events: none; }
@media (max-width: 991px) { #zoomLens, #zoomResult { display: none !important; } .product-main-image-wrapper { cursor: default; } }
</style>

<section class="py-5 product-detail-page">
  <div class="container">
    <div class="product-detail-grid">
      <!-- Left Column: Product Image -->
      <div class="product-image-section">
        <div class="product-image-zoom-container">
          <div class="product-main-image-wrapper" id="imageZoomWrapper">
            <img src="<?= asset_url('images/products/' . htmlspecialchars($mainProductImage)); ?>" alt="<?= htmlspecialchars($product['name']); ?>" class="product-main-image" id="mainProductImage" />
            <div id="zoomLens"></div>
          </div>
          <div id="zoomResult"></div>
        </div>
        
        <!-- Zoom Script (immediately after elements) -->
        <script>
        (function(){
          var w = document.getElementById('imageZoomWrapper');
          var img = document.getElementById('mainProductImage');
          var lens = document.getElementById('zoomLens');
          var res = document.getElementById('zoomResult');
          
          if(!w || !img || !lens || !res) return;
          
          var zoomImg = null;
          var cx = 3, cy = 3;
          
          function init() {
            if(window.innerWidth <= 991) return;
            
            var dispW = img.offsetWidth;
            var dispH = img.offsetHeight;
            
            if(dispW === 0 || dispH === 0) {
              setTimeout(init, 100);
              return;
            }
            
            // Create or update zoom image
            if(!zoomImg) {
              zoomImg = document.createElement('img');
              res.innerHTML = '';
              res.appendChild(zoomImg);
            }
            zoomImg.src = img.src;
            zoomImg.style.width = (dispW * cx) + 'px';
            zoomImg.style.height = (dispH * cy) + 'px';
            zoomImg.style.left = '0px';
            zoomImg.style.top = '0px';
            
            // Position zoom result
            var wRect = w.getBoundingClientRect();
            res.style.top = Math.max(10, wRect.top) + 'px';
            res.style.left = (wRect.right + 15) + 'px';
          }
          
          function move(e) {
            if(window.innerWidth <= 991 || !zoomImg) return;
            
            var rect = img.getBoundingClientRect();
            var dispW = img.offsetWidth;
            var dispH = img.offsetHeight;
            
            var mouseX = e.clientX - rect.left;
            var mouseY = e.clientY - rect.top;
            
            var lensX = mouseX - 60;
            var lensY = mouseY - 60;
            
            if(lensX < 0) lensX = 0;
            if(lensY < 0) lensY = 0;
            if(lensX > dispW - 120) lensX = dispW - 120;
            if(lensY > dispH - 120) lensY = dispH - 120;
            
            lens.style.left = lensX + 'px';
            lens.style.top = lensY + 'px';
            
            // Move the zoomed image
            zoomImg.style.left = '-' + (lensX * cx) + 'px';
            zoomImg.style.top = '-' + (lensY * cy) + 'px';
          }
          
          w.addEventListener('mouseenter', function() {
            if(window.innerWidth <= 991) return;
            init();
            lens.style.display = 'block';
            res.style.display = 'block';
          });
          
          w.addEventListener('mouseleave', function() {
            lens.style.display = 'none';
            res.style.display = 'none';
          });
          
          w.addEventListener('mousemove', move);
          
          // Thumbnail click
          var origChange = window.changeMainImage;
          window.changeMainImage = function(src) {
            if(origChange) origChange(src);
            zoomImg = null; // Reset zoom image
            setTimeout(init, 250);
          };
        })();
        </script>
        <div class="product-thumbnail-list">
          <?php foreach ($productImages as $index => $imagePath): ?>
            <img src="<?= asset_url('images/products/' . htmlspecialchars($imagePath)); ?>"
                 alt="Thumbnail <?= $index + 1; ?>"
                 class="product-thumbnail <?= $index === 0 ? 'active' : ''; ?>"
                 onclick="changeMainImage(this.src)" />
          <?php endforeach; ?>
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"></path>
          </svg>
          <?php if ($deliveryFee > 0): ?>
            <span class="product-delivery-text">Delivery ₹<?= number_format($deliveryFee, 0); ?></span>
          <?php else: ?>
            <span class="product-delivery-text" style="color: #10b981;">Free Delivery</span>
          <?php endif; ?>
        </div>
        
        <?php if (!empty($productWeights)): ?>
        <div class="product-size-selector">
          <label class="size-selector-label">Select Weight</label>
          <div class="size-options">
            <?php foreach ($productWeights as $index => $weight): ?>
              <button class="size-option <?= $weight['is_default'] == 1 ? 'active' : ''; ?>" 
                      data-weight-id="<?= $weight['id']; ?>" 
                      data-price="<?= $weight['price']; ?>"
                      data-weight="<?= htmlspecialchars($weight['display_weight']); ?>"
                      data-ean="<?= htmlspecialchars($weight['ean'] ?? ''); ?>"
                      onclick="selectWeight(this)">
                <?= htmlspecialchars($weight['display_weight']); ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        
        <form action="<?= base_url('cart.php'); ?>" method="post" id="productForm">
          <input type="hidden" name="action" value="add" />
          <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>" />
          <input type="hidden" name="weight_id" id="selectedWeightId" value="<?php 
            $defaultWeight = array_filter($productWeights, fn($w) => $w['is_default'] == 1);
            echo !empty($defaultWeight) ? reset($defaultWeight)['id'] : ($productWeights[0]['id'] ?? '');
          ?>" />
          <input type="hidden" name="quantity" value="1" />
          
          <div class="product-actions">
            <button type="submit" class="btn-add-to-cart">
              <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              Add to Cart
            </button>
            <button type="submit" class="btn-buy-now" formaction="<?= base_url('buy_now.php'); ?>">
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
            <?php 
              $defaultWeightData = !empty($productWeights) ? (array_filter($productWeights, fn($w) => $w['is_default'] == 1) ?: [$productWeights[0]]) : [];
              $defaultEan = !empty($defaultWeightData) ? reset($defaultWeightData)['ean'] : '';
            ?>
            <div class="detail-row" id="eanRow" style="<?= empty($defaultEan) ? 'display:none;' : ''; ?>">
              <span class="detail-label">EAN/Barcode:</span>
              <span class="detail-value" id="productEan"><?= htmlspecialchars($defaultEan); ?></span>
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

// Select product weight with dynamic pricing
function selectWeight(button) {
  // Remove active class from all weight options
  document.querySelectorAll('.size-option').forEach(opt => {
    opt.classList.remove('active');
  });
  
  // Add active class to selected option
  button.classList.add('active');
  
  // Get weight data
  const weightId = button.getAttribute('data-weight-id');
  const price = button.getAttribute('data-price');
  const weight = button.getAttribute('data-weight');
  const ean = button.getAttribute('data-ean');
  
  // Update hidden input for selected weight
  const weightIdInput = document.getElementById('selectedWeightId');
  if (weightIdInput) {
    weightIdInput.value = weightId;
  }
  
  // Update price display
  const priceElement = document.querySelector('.product-price');
  if (priceElement && price) {
    priceElement.innerHTML = '<span class="product-price-symbol">₹</span>' + parseFloat(price).toFixed(0);
  }
  
  // Update EAN display
  const eanRow = document.getElementById('eanRow');
  const eanElement = document.getElementById('productEan');
  if (eanRow && eanElement) {
    if (ean && ean.trim() !== '') {
      eanElement.textContent = ean;
      eanRow.style.display = '';
    } else {
      eanRow.style.display = 'none';
    }
  }
  
  console.log('Selected weight: ' + weight + ' - Price: ₹' + price + ' - EAN: ' + (ean || 'N/A'));
}

// Legacy function for backward compatibility
function selectVariant(button) {
  selectWeight(button);
}

// Buy Now functionality
function buyNow(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
  }
  
  const form = document.getElementById('productForm');
  const formData = new FormData(form);
  formData.set('action', 'add');
  formData.set('buy_now', '1'); // Flag for direct checkout
  
  // Add to cart first, then redirect to checkout
  fetch(form.action, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  }).then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.href = '<?= base_url('checkout.php'); ?>';
      } else {
        alert(data.message || 'Failed to add product. Please try again.');
      }
    }).catch(error => {
      console.error('Buy Now error:', error);
      // Still redirect - item might have been added
      window.location.href = '<?= base_url('checkout.php'); ?>';
    });
  
  return false;
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
