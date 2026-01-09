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
            <div class="col-md-6">
              <label class="form-label">Weight</label>
              <div class="input-group">
                <input type="number" name="weight_value" id="weightValue" class="form-control" step="0.01" min="0.01" placeholder="Enter weight" required onkeyup="updateWeightDisplay()" />
                <select name="weight_unit" id="weightUnit" class="form-select" style="max-width: 100px;" required onchange="updateWeightDisplay()">
                  <option value="g">g</option>
                  <option value="kg">kg</option>
                </select>
              </div>
              <input type="hidden" name="net_weight" id="finalNetWeight" />
              <small class="text-muted">Final: <strong id="weightDisplay">Not set</strong></small>
            </div>
            <div class="col-md-12">
              <label class="form-label">Pricing & Discount</label>
              <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#pricingModal">
                <i class="bi bi-percent"></i> Set Pricing & Discount
              </button>
              <input type="hidden" name="cost_price" id="costPrice" value="0" />
              <input type="hidden" name="selling_price" id="sellingPrice" value="0" />
              <input type="hidden" name="stock_quantity" value="0" />
              <small class="text-muted" id="pricingSummary">Not set</small>
              <small class="text-info d-block mt-1"><i class="bi bi-info-circle"></i> Stock is managed via Batch Codes</small>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3" required></textarea>
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
            <div class="col-12">
              <label class="form-label">Bullet Points / Key Highlights</label>
              <textarea name="highlights" class="form-control" rows="4" placeholder="• 100% Pure and Natural&#10;• Lab Tested for Quality&#10;• Premium Grade A&#10;• Direct from Kashmir" required></textarea>
              <small class="text-muted">Enter key product highlights in bullet-point format</small>
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

<?php include 'pricing_modal.php'; ?>

<script>
// Update weight display
function updateWeightDisplay() {
  const weightValue = parseFloat(document.getElementById('weightValue').value) || 0;
  const weightUnit = document.getElementById('weightUnit').value;
  const display = document.getElementById('weightDisplay');
  const finalInput = document.getElementById('finalNetWeight');
  
  if (weightValue > 0) {
    const finalWeight = weightValue + ' ' + weightUnit;
    finalInput.value = weightValue + weightUnit;
    display.textContent = finalWeight;
  } else {
    display.textContent = 'Not set';
    finalInput.value = '';
  }
}

// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
