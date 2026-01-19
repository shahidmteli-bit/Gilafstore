<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Products — Admin';
$adminPage = 'products';
$categories = admin_get_categories();
$products = admin_get_products();

// Fetch all weights for each product
$db = get_db_connection();
$productWeights = [];
foreach ($products as $product) {
    $stmt = $db->prepare("SELECT id, display_weight, price FROM product_weights WHERE product_id = ? ORDER BY sort_order ASC, weight_value ASC");
    $stmt->execute([$product['id']]);
    $productWeights[$product['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if new product system columns exist
$systemNeedsUpdate = false;
try {
    $testQuery = db_fetch('SHOW COLUMNS FROM categories LIKE "category_code"');
    if (!$testQuery) {
        $systemNeedsUpdate = true;
    }
} catch (PDOException $e) {
    $systemNeedsUpdate = true;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Products</h4>
        <p class="text-muted mb-0">Add new products, update details, or adjust inventory.</p>
      </div>
      <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fas fa-plus me-2"></i>Add Product</button>
    </div>

    <?php if ($systemNeedsUpdate): ?>
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Database Update Required</h5>
        <p class="mb-3">The new product management system requires database updates. Please run the SQL migration to enable all features.</p>
        <hr>
        <p class="mb-2"><strong>Steps to update:</strong></p>
        <ol class="mb-3">
          <li>Open phpMyAdmin: <code>http://localhost/phpmyadmin</code></li>
          <li>Select database: <strong>ecommerce_db</strong></li>
          <li>Click the <strong>SQL</strong> tab</li>
          <li>Open file: <code>redesign_product_system.sql</code></li>
          <li>Copy and paste the SQL code</li>
          <li>Click <strong>Go</strong></li>
          <li>Refresh this page</li>
        </ol>
        <p class="mb-0"><strong>New features after update:</strong> Category codes, Unit types, Cost/Selling price with auto-discount, 3-image upload, Product highlights</p>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="card shadow-3 border-0">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>C-CODE</th>
                <th>Weight</th>
                <th>Discount</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <img src="<?= asset_url('images/products/' . ltrim($product['image'], '/')); ?>" alt="<?= htmlspecialchars($product['name']); ?>" class="rounded" style="width: 60px; height: 60px; object-fit: cover;" />
                      <div>
                        <strong><?= htmlspecialchars($product['name']); ?></strong>
                        <p class="text-muted mb-0 small">ID: <?= (int)$product['id']; ?></p>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php if (!empty($product['category_code'])): ?>
                      <span class="badge bg-primary" style="font-size: 0.9rem;">[<?= htmlspecialchars($product['category_code']); ?>]</span>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php 
                      $weights = $productWeights[$product['id']] ?? [];
                      if (!empty($weights)) {
                        foreach ($weights as $weight) {
                          echo '<span class="badge bg-info me-1 mb-1" style="font-size: 0.85rem;">' . htmlspecialchars($weight['display_weight']) . '</span>';
                        }
                      } else {
                        echo '<span class="text-muted">Not set</span>';
                      }
                    ?>
                  </td>
                  <td>
                    <?php 
                      $costPrice = $product['cost_price'] ?? ($product['price'] ?? 0) * 0.8;
                      $sellingPrice = $product['selling_price'] ?? ($product['price'] ?? 0);
                      
                      // Prevent division by zero
                      if ($sellingPrice > 0) {
                        $discount = (($sellingPrice - $costPrice) / $sellingPrice) * 100;
                      } else {
                        $discount = 0;
                      }
                    ?>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Cost: ₹<?= number_format($costPrice, 2); ?> | Selling: ₹<?= number_format($sellingPrice, 2); ?> | Discount: <?= number_format($discount, 1); ?>%">
                      <i class="bi bi-percent"></i> <?= number_format($discount, 1); ?>%
                    </button>
                  </td>
                  <td>
                    <div class="btn-group" role="group">
                      <button type="button" class="btn btn-sm btn-outline-primary rounded-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Edit
                      </button>
                      <ul class="dropdown-menu shadow-sm" style="min-width: 250px;">
                        <li><h6 class="dropdown-header">Edit Product</h6></li>
                        <li><a class="dropdown-item" href="<?= base_url('admin/product_edit.php?id=' . (int)$product['id']); ?>">
                          <i class="fas fa-edit me-2"></i>Edit All Details
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Edit By Weight</h6></li>
                        <?php 
                          $weights = $productWeights[$product['id']] ?? [];
                          if (!empty($weights)):
                            foreach ($weights as $weight):
                              // Ensure weight has required fields
                              if (isset($weight['id']) && isset($weight['display_weight']) && isset($weight['price'])):
                        ?>
                          <li><a class="dropdown-item" href="<?= base_url('admin/product_weight_edit.php?product_id=' . (int)$product['id'] . '&weight_id=' . (int)$weight['id']); ?>" target="_blank" style="font-size: 14px; padding: 8px 16px;">
                            <i class="fas fa-weight me-2"></i><strong><?= htmlspecialchars($weight['display_weight']); ?></strong> - ₹<?= number_format((float)$weight['price'], 2); ?>
                          </a></li>
                        <?php 
                              endif;
                            endforeach;
                          else:
                        ?>
                          <li><span class="dropdown-item text-muted">No weights available</span></li>
                        <?php endif; ?>
                      </ul>
                    </div>
                    <form action="<?= base_url('admin_actions.php'); ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                      <input type="hidden" name="action" value="delete_product" />
                      <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$products): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">No products yet. Add your first product.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="<?= base_url('admin_actions.php'); ?>" method="post" enctype="multipart/form-data" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Add product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create_product" />
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Product name</label>
              <input type="text" name="name" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">C-CODE</label>
              <select name="category_id" id="categorySelect" class="form-select" required>
                <option value="">Select C-CODE</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= (int)$category['id']; ?>" data-code="<?= htmlspecialchars($category['category_code'] ?? ''); ?>">
                    <?php if (!empty($category['category_code'])): ?>
                      [<?= htmlspecialchars($category['category_code']); ?>] 
                    <?php endif; ?>
                    <?= htmlspecialchars($category['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label">Product Weights & Prices <span class="text-danger">*</span></label>
              <!-- Display added weights with prices as cards -->
              <div id="weightPriceCardsContainer" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px;">
                <!-- Weight-price cards will appear here -->
              </div>
              <!-- Single input for adding new weight with price -->
              <div class="card border-primary" style="background-color: #f8f9fa;">
                <div class="card-body p-3">
                  <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                      <label class="form-label small mb-1">Weight <span class="text-danger">*</span></label>
                      <input type="number" id="newWeightValue" class="form-control form-control-sm" step="0.01" min="0.01" placeholder="Enter weight" />
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small mb-1">Unit</label>
                      <select id="newWeightUnit" class="form-select form-select-sm">
                        <option value="g">g</option>
                        <option value="kg">kg</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small mb-1">Price <span class="text-danger">*</span></label>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">₹</span>
                        <input type="number" id="newWeightPrice" class="form-control" step="0.01" min="0" placeholder="Enter price" />
                      </div>
                    </div>
                    <div class="col-md-3">
                      <button type="button" class="btn btn-primary btn-sm w-100" id="addWeightBtn">
                        <i class="fas fa-plus"></i> ADD WEIGHT
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <input type="hidden" name="weights" id="weightsData" />
              <input type="hidden" name="price" id="defaultPriceHidden" />
              <input type="hidden" name="stock_quantity" value="0" />
              <small class="text-muted d-block mt-2"><i class="bi bi-info-circle"></i> Each weight must have its own price. Stock is managed via Batch Codes.</small>
            </div>
            <div class="col-12">
              <label class="form-label">Product Images (Minimum 2, Maximum 4)</label>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label small">Image 1 <span class="text-danger">*</span></label>
                  <input type="file" name="image_1" id="image_1" class="form-control image-input" accept="image/*" required />
                  <small class="text-success d-none image-status" id="status_image_1"><i class="fas fa-check-circle"></i> <span class="filename"></span></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Image 2 <span class="text-danger">*</span></label>
                  <input type="file" name="image_2" id="image_2" class="form-control image-input" accept="image/*" required />
                  <small class="text-success d-none image-status" id="status_image_2"><i class="fas fa-check-circle"></i> <span class="filename"></span></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Image 3 <span class="text-muted">(Optional)</span></label>
                  <input type="file" name="image_3" id="image_3" class="form-control image-input" accept="image/*" />
                  <small class="text-success d-none image-status" id="status_image_3"><i class="fas fa-check-circle"></i> <span class="filename"></span></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Image 4 <span class="text-muted">(Optional)</span></label>
                  <input type="file" name="image_4" id="image_4" class="form-control image-input" accept="image/*" />
                  <small class="text-success d-none image-status" id="status_image_4"><i class="fas fa-check-circle"></i> <span class="filename"></span></small>
                </div>
              </div>
              <small class="text-muted">Upload at least 2 images, up to 4 images. Images will be displayed as a slider/carousel on product page</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Weight Confirmation Modal -->
<div class="modal fade" id="deleteWeightModal" tabindex="-1" aria-labelledby="deleteWeightModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteWeightModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Are you sure you want to remove <strong id="deleteWeightText">this weight</strong> from the product?</p>
        <p class="text-muted small mb-0 mt-2">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteWeight">
          <i class="fas fa-trash me-1"></i>Delete Weight
        </button>
      </div>
    </div>
  </div>
</div>

<?php include 'pricing_modal.php'; ?>

<script>
// Weight-Price Management System with Image Upload Visibility
// VERSION: 2.0 - CACHE BUST - <?= time(); ?>
// IMPORTANT: weightPrices must be outside DOMContentLoaded to persist
let weightPrices = []; // Array to store weights with prices
let weightToDelete = null;

console.log('=== PRODUCT ADD SCRIPT LOADED - VERSION 2.0 ===');
console.log('Script timestamp:', '<?= date('Y-m-d H:i:s'); ?>');

// TEMPORARY: Visible confirmation that new code loaded
setTimeout(function() {
  const banner = document.createElement('div');
  banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#00ff00;color:#000;padding:10px;text-align:center;z-index:99999;font-weight:bold;';
  banner.textContent = '✅ NEW CODE LOADED - VERSION 2.0 - ' + '<?= date('H:i:s'); ?>';
  document.body.appendChild(banner);
  setTimeout(() => banner.remove(), 3000);
}, 500);

document.addEventListener('DOMContentLoaded', function() {
  const cardsContainer = document.getElementById('weightPriceCardsContainer');
  const addWeightBtn = document.getElementById('addWeightBtn');
  const newWeightValue = document.getElementById('newWeightValue');
  const newWeightUnit = document.getElementById('newWeightUnit');
  const newWeightPrice = document.getElementById('newWeightPrice');
  const weightsDataInput = document.getElementById('weightsData');
  const defaultPriceHidden = document.getElementById('defaultPriceHidden');
  const addProductModal = document.getElementById('addProductModal');
  
  // Image upload visibility
  document.querySelectorAll('.image-input').forEach(input => {
    input.addEventListener('change', function() {
      const statusEl = document.getElementById('status_' + this.id);
      const filenameEl = statusEl.querySelector('.filename');
      
      if (this.files && this.files[0]) {
        const fileName = this.files[0].name;
        const fileSize = (this.files[0].size / 1024).toFixed(1) + ' KB';
        filenameEl.textContent = fileName + ' (' + fileSize + ')';
        statusEl.classList.remove('d-none');
        this.classList.add('border-success');
      } else {
        statusEl.classList.add('d-none');
        this.classList.remove('border-success');
      }
    });
  });
  
  // Add new weight with price
  addWeightBtn.addEventListener('click', function() {
    const value = parseFloat(newWeightValue.value);
    const unit = newWeightUnit.value;
    const price = parseFloat(newWeightPrice.value);
    
    // Validation
    if (!value || value <= 0) {
      showValidationError('Please enter a valid weight');
      newWeightValue.focus();
      return;
    }
    
    if (!price || price <= 0) {
      showValidationError('Please enter a valid price for this weight');
      newWeightPrice.focus();
      return;
    }
    
    const displayWeight = value + ' ' + unit;
    
    // Check for duplicate weight
    if (weightPrices.some(w => w.value === value && w.unit === unit)) {
      showValidationError('This weight already exists');
      return;
    }
    
    // Add to array
    weightPrices.push({ 
      value: value, 
      unit: unit, 
      display: displayWeight,
      price: price
    });
    
    console.log('✅ Weight added to array:', {value, unit, price});
    console.log('Current weightPrices array:', weightPrices);
    console.log('Array length:', weightPrices.length);
    
    // Clear inputs
    newWeightValue.value = '';
    newWeightPrice.value = '';
    newWeightValue.focus();
    
    // Render cards
    renderWeightPriceCards();
  });
  
  // Render weight-price cards
  function renderWeightPriceCards() {
    cardsContainer.innerHTML = '';
    
    if (weightPrices.length === 0) {
      cardsContainer.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle"></i> No weights added yet. Please add at least one weight with price.</div>';
      return;
    }
    
    weightPrices.forEach((wp, index) => {
      const card = document.createElement('div');
      card.className = 'card border-success';
      card.innerHTML = `
        <div class="card-body p-2 d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
            <span class="badge bg-success" style="font-size: 14px; padding: 6px 12px;">${wp.display}</span>
            <span class="text-dark"><strong>Price:</strong> ₹${wp.price.toFixed(2)}</span>
            ${index === 0 ? '<span class="badge bg-primary">Default</span>' : ''}
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" data-index="${index}">
            <i class="fas fa-trash"></i> Remove
          </button>
        </div>
      `;
      cardsContainer.appendChild(card);
    });
  }
  
  // Remove weight-price with confirmation
  cardsContainer.addEventListener('click', function(e) {
    const btn = e.target.closest('button[data-index]');
    if (btn) {
      const index = parseInt(btn.dataset.index);
      weightToDelete = index;
      
      // Show confirmation modal
      document.getElementById('deleteWeightText').textContent = weightPrices[index].display + ' (₹' + weightPrices[index].price + ')';
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteWeightModal'));
      deleteModal.show();
    }
  });
  
  // Confirm delete weight
  document.getElementById('confirmDeleteWeight').addEventListener('click', function() {
    if (weightToDelete !== null) {
      weightPrices.splice(weightToDelete, 1);
      renderWeightPriceCards();
      weightToDelete = null;
      bootstrap.Modal.getInstance(document.getElementById('deleteWeightModal')).hide();
    }
  });
  
  // Allow Enter key to add weight
  newWeightValue.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      newWeightPrice.focus();
    }
  });
  
  newWeightPrice.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      addWeightBtn.click();
    }
  });
  
  // Form validation and submission
  const addProductForm = document.querySelector('#addProductModal form');
  if (!addProductForm) {
    console.error('Add product form not found!');
    return;
  }
  
  addProductForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    console.log('Form submit - weightPrices array:', weightPrices);
    console.log('weightPrices.length:', weightPrices.length);
    
    // Clear previous validation errors
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    let errors = [];
    
    // Validate product name
    const productName = this.querySelector('input[name="name"]');
    if (!productName.value.trim()) {
      errors.push('Product name is required');
      productName.classList.add('is-invalid');
    }
    
    // Validate category
    const category = this.querySelector('select[name="category_id"]');
    if (!category.value) {
      errors.push('Category (C-CODE) is required');
      category.classList.add('is-invalid');
    }
    
    // Validate weights and prices
    console.log('Checking weightPrices.length:', weightPrices.length);
    if (weightPrices.length === 0) {
      console.log('ERROR: No weights added');
      errors.push('Please add at least one weight with price');
    } else {
      console.log('SUCCESS: Weights found:', weightPrices);
    }
    
    // Validate images
    const image1 = this.querySelector('input[name="image_1"]');
    const image2 = this.querySelector('input[name="image_2"]');
    
    if (!image1.files || !image1.files[0]) {
      errors.push('Image 1 is required');
      image1.classList.add('is-invalid');
    }
    
    if (!image2.files || !image2.files[0]) {
      errors.push('Image 2 is required');
      image2.classList.add('is-invalid');
    }
    
    // Show errors if any
    if (errors.length > 0) {
      showValidationError(errors.join('\n'));
      return false;
    }
    
    // Prepare data for submission
    weightsDataInput.value = JSON.stringify(weightPrices);
    
    // Set default price (first weight's price)
    defaultPriceHidden.value = weightPrices[0].price;
    
    // Submit form
    this.submit();
  });
  
  // Show validation error
  function showValidationError(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    alertDiv.innerHTML = `
      <strong><i class="fas fa-exclamation-circle"></i> Validation Error</strong><br>
      ${message.replace(/\n/g, '<br>')}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
      alertDiv.remove();
    }, 5000);
  }
  
  // Reset form and weightPrices when modal is closed
  if (addProductModal) {
    addProductModal.addEventListener('hidden.bs.modal', function() {
      console.log('Modal closed - resetting form and weightPrices');
      weightPrices = [];
      renderWeightPriceCards();
      
      // Reset form
      const form = addProductModal.querySelector('form');
      if (form) {
        form.reset();
        // Clear image status indicators
        document.querySelectorAll('.image-status').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.image-input').forEach(el => el.classList.remove('border-success'));
      }
    });
    
    // Initialize when modal is shown
    addProductModal.addEventListener('shown.bs.modal', function() {
      console.log('Modal opened - initializing weightPrices:', weightPrices);
      renderWeightPriceCards();
    });
  }
  
  // Initialize
  renderWeightPriceCards();
});
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
