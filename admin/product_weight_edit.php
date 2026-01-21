<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$weightId = isset($_GET['weight_id']) ? (int)$_GET['weight_id'] : 0;

if (!$productId || !$weightId) {
    redirect_with_message(base_url('admin/manage_products.php'), 'Invalid parameters', 'danger');
}

// Get product info
$product = get_product($productId);
if (!$product) {
    redirect_with_message(base_url('admin/manage_products.php'), 'Product not found', 'danger');
}

// Get weight info
$db = get_db_connection();
$stmt = $db->prepare("SELECT * FROM product_weights WHERE id = ? AND product_id = ?");
$stmt->execute([$weightId, $productId]);
$weight = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$weight) {
    redirect_with_message(base_url('admin/manage_products.php'), 'Weight not found', 'danger');
}

$pageTitle = 'Edit Weight — ' . $product['name'];
$adminPage = 'products';

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid" style="max-width: 720px;">
    <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-outline-secondary rounded-pill mb-3">
      <i class="fas fa-arrow-left me-2"></i>Back to products
    </a>
    
    <div class="card shadow-3 border-0">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start mb-4">
          <div>
            <h4 class="fw-semibold mb-1">Edit Weight: <?= htmlspecialchars($weight['display_weight']); ?></h4>
            <p class="text-muted mb-0">Product: <?= htmlspecialchars($product['name']); ?></p>
          </div>
          <?php if ($weight['is_default'] == 1): ?>
            <span class="badge bg-primary">Default Weight</span>
          <?php endif; ?>
        </div>
        
        <form action="<?= base_url('admin_actions.php'); ?>" method="post" class="row g-4" id="weightEditForm">
          <input type="hidden" name="action" value="update_product_weight" />
          <input type="hidden" name="product_id" value="<?= (int)$productId; ?>" />
          <input type="hidden" name="weight_id" value="<?= (int)$weightId; ?>" />
          
          <div class="col-12">
            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Weight-Level Editing:</strong> Changes here affect only this specific weight (<?= htmlspecialchars($weight['display_weight']); ?>). 
              To edit product name, category, or images, use "Edit All Details" from the dropdown.
            </div>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Weight Value <span class="text-danger">*</span></label>
            <input type="number" name="weight_value" class="form-control" value="<?= htmlspecialchars($weight['weight_value']); ?>" step="0.01" min="0.01" required />
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Unit <span class="text-danger">*</span></label>
            <select name="weight_unit" class="form-select" required>
              <option value="g" <?= $weight['weight_unit'] === 'g' ? 'selected' : ''; ?>>g (grams)</option>
              <option value="kg" <?= $weight['weight_unit'] === 'kg' ? 'selected' : ''; ?>>kg (kilograms)</option>
            </select>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
            <input type="number" name="price" class="form-control" value="<?= htmlspecialchars($weight['price']); ?>" step="0.01" min="0" required />
            <small class="text-muted">Price for this specific weight</small>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">EAN Number <span class="text-danger">*</span></label>
            <input type="text" name="ean" id="eanInput" class="form-control" value="<?= htmlspecialchars($weight['ean'] ?? ''); ?>" maxlength="13" pattern="[0-9]{8,13}" required />
            <small class="text-muted">8-13 digit barcode (required for batch generation)</small>
            <div class="invalid-feedback">EAN must be 8-13 numeric digits only</div>
          </div>
          
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefaultCheck" <?= $weight['is_default'] == 1 ? 'checked' : ''; ?>>
              <label class="form-check-label" for="isDefaultCheck">
                Set as default weight for this product
              </label>
              <small class="d-block text-muted">The default weight's price will be used as the product's main price</small>
            </div>
          </div>
          
          <div class="col-12">
            <hr>
            <h6 class="mb-3">Product Information (Read-Only)</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-muted">Product Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($product['name']); ?>" disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label text-muted">Category</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($product['category_name'] ?? 'N/A'); ?>" disabled>
              </div>
            </div>
          </div>
          
          <div class="col-12 d-flex gap-3">
            <button type="submit" class="btn btn-primary rounded-pill">
              <i class="fas fa-save me-2"></i>Save Changes
            </button>
            <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-outline-secondary rounded-pill">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
document.getElementById('weightEditForm').addEventListener('submit', function(e) {
    const eanInput = document.getElementById('eanInput');
    const ean = eanInput.value.trim();
    
    // Validate EAN: must be 8-13 numeric digits
    const eanRegex = /^[0-9]{8,13}$/;
    
    if (!ean) {
        e.preventDefault();
        eanInput.classList.add('is-invalid');
        alert('EAN number is required. Please add the EAN to save this weight.');
        eanInput.focus();
        return false;
    }
    
    if (!eanRegex.test(ean)) {
        e.preventDefault();
        eanInput.classList.add('is-invalid');
        alert('EAN must be 8-13 numeric digits only. No letters or special characters allowed.');
        eanInput.focus();
        return false;
    }
    
    eanInput.classList.remove('is-invalid');
    return true;
});

// Real-time EAN validation
document.getElementById('eanInput').addEventListener('input', function() {
    const ean = this.value.trim();
    const eanRegex = /^[0-9]{8,13}$/;
    
    // Remove non-numeric characters
    this.value = this.value.replace(/[^0-9]/g, '');
    
    if (ean && !eanRegex.test(ean)) {
        this.classList.add('is-invalid');
    } else if (ean) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.classList.remove('is-valid', 'is-invalid');
    }
});
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
