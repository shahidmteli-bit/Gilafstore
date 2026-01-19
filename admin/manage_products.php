<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Products — Admin';
$adminPage = 'products';
$categories = admin_get_categories();
$products = admin_get_products();

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
                      $netWeight = $product['net_weight'] ?? '';
                      if ($netWeight) {
                        echo '<span class="badge bg-info">' . htmlspecialchars($netWeight) . '</span>';
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
                    <a href="<?= base_url('admin/product_edit.php?id=' . (int)$product['id']); ?>" class="btn btn-sm btn-outline-primary rounded-pill">Edit</a>
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
              <label class="form-label">Product Weights</label>
              <div id="weightsContainer" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                <div class="weight-input-row">
                  <div class="input-group" style="width: auto;">
                    <input type="number" class="form-control weight-value" step="0.01" min="0.01" placeholder="Enter weight" required style="width: 120px;" />
                    <select class="form-select weight-unit" style="width: 70px;" required>
                      <option value="g">g</option>
                      <option value="kg">kg</option>
                    </select>
                    <button type="button" class="btn btn-outline-danger remove-weight" disabled><i class="fas fa-times"></i></button>
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-outline-primary btn-sm" id="addWeightBtn"><i class="fas fa-plus"></i> ADD WEIGHT</button>
              <input type="hidden" name="weights" id="weightsData" />
              <small class="text-muted d-block mt-2">Add multiple weights for this product (e.g., 250g, 500g, 1kg)</small>
            </div>
            <div class="col-md-12">
              <label class="form-label">Product Price</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" name="price" class="form-control" step="0.01" min="0" placeholder="Enter price" required />
              </div>
              <input type="hidden" name="stock_quantity" value="0" />
              <small class="text-info d-block mt-1"><i class="bi bi-info-circle"></i> Stock is managed via Batch Codes</small>
            </div>
            <div class="col-12">
              <label class="form-label">Product Images (Minimum 2, Maximum 4)</label>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label small">Image 1 <span class="text-danger">*</span></label>
                  <input type="file" name="image_1" class="form-control" accept="image/*" required />
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Image 2 <span class="text-danger">*</span></label>
                  <input type="file" name="image_2" class="form-control" accept="image/*" required />
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Image 3 <span class="text-muted">(Optional)</span></label>
                  <input type="file" name="image_3" class="form-control" accept="image/*" />
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Image 4 <span class="text-muted">(Optional)</span></label>
                  <input type="file" name="image_4" class="form-control" accept="image/*" />
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
// Multiple Weights Management
document.addEventListener('DOMContentLoaded', function() {
  const weightsContainer = document.getElementById('weightsContainer');
  const addWeightBtn = document.getElementById('addWeightBtn');
  const weightsDataInput = document.getElementById('weightsData');
  
  // Add new weight input row
  addWeightBtn.addEventListener('click', function() {
    const newRow = document.createElement('div');
    newRow.className = 'weight-input-row';
    newRow.innerHTML = `
      <div class="input-group" style="width: auto;">
        <input type="number" class="form-control weight-value" step="0.01" min="0.01" placeholder="Enter weight" required style="width: 120px;" />
        <select class="form-select weight-unit" style="width: 70px;" required>
          <option value="g">g</option>
          <option value="kg">kg</option>
        </select>
        <button type="button" class="btn btn-outline-danger remove-weight"><i class="fas fa-times"></i></button>
      </div>
    `;
    weightsContainer.appendChild(newRow);
    updateRemoveButtons();
  });
  
  // Remove weight row with confirmation
  let weightToRemove = null;
  
  weightsContainer.addEventListener('click', function(e) {
    if (e.target.closest('.remove-weight')) {
      weightToRemove = e.target.closest('.weight-input-row');
      const weightValue = weightToRemove.querySelector('.weight-value').value;
      const weightUnit = weightToRemove.querySelector('.weight-unit').value;
      const displayWeight = weightValue && weightUnit ? `${weightValue} ${weightUnit}` : 'this weight';
      
      // Show confirmation modal
      document.getElementById('deleteWeightText').textContent = displayWeight;
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteWeightModal'));
      deleteModal.show();
    }
  });
  
  // Confirm delete weight
  document.getElementById('confirmDeleteWeight').addEventListener('click', function() {
    if (weightToRemove) {
      weightToRemove.remove();
      updateRemoveButtons();
      weightToRemove = null;
      bootstrap.Modal.getInstance(document.getElementById('deleteWeightModal')).hide();
    }
  });
  
  // Update remove buttons state (disable if only one weight)
  function updateRemoveButtons() {
    const rows = weightsContainer.querySelectorAll('.weight-input-row');
    const removeButtons = weightsContainer.querySelectorAll('.remove-weight');
    removeButtons.forEach(btn => {
      btn.disabled = rows.length === 1;
    });
  }
  
  // Collect weights data before form submission
  document.querySelector('form[action*="admin_actions.php"]').addEventListener('submit', function(e) {
    const weights = [];
    const rows = weightsContainer.querySelectorAll('.weight-input-row');
    
    rows.forEach(row => {
      const value = row.querySelector('.weight-value').value;
      const unit = row.querySelector('.weight-unit').value;
      if (value && parseFloat(value) > 0) {
        weights.push({
          value: parseFloat(value),
          unit: unit,
          display: value + ' ' + unit
        });
      }
    });
    
    if (weights.length === 0) {
      e.preventDefault();
      alert('Please add at least one weight for the product');
      return false;
    }
    
    weightsDataInput.value = JSON.stringify(weights);
  });
  
  // Initialize Bootstrap tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
