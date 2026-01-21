<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = get_product($productId);

if (!$product) {
    redirect_with_message(base_url('admin/manage_products.php'), 'Product not found', 'danger');
}

// Fetch all weights for this product
$db = get_db_connection();
$stmt = $db->prepare("SELECT id, weight_value, weight_unit, display_weight, price, ean, is_default, sort_order FROM product_weights WHERE product_id = ? ORDER BY sort_order ASC, weight_value ASC");
$stmt->execute([$productId]);
$productWeights = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Edit Product — Admin';
$adminPage = 'products';
$categories = admin_get_categories();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid" style="max-width: 920px;">
    <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-outline-secondary rounded-pill mb-3"><i class="fas fa-arrow-left me-2"></i>Back to products</a>
    <div class="card shadow-3 border-0">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="fw-semibold mb-1">Edit product</h4>
            <p class="text-muted mb-0">Update product details, pricing, or imagery.</p>
          </div>
        </div>
        <form action="<?= base_url('admin_actions.php'); ?>" method="post" enctype="multipart/form-data" class="row g-4" novalidate>
          <input type="hidden" name="action" value="update_product" />
          <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>" />
          <div class="col-md-6">
            <label class="form-label">Product name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']); ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <option value="">Select category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id']; ?>" <?= (int)$product['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($category['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Product Weights & Prices <span class="text-danger">*</span></label>
            
            <!-- Display existing weights as editable cards -->
            <div id="existingWeightsContainer" class="mb-3">
              <?php foreach ($productWeights as $index => $weight): ?>
                <div class="card border-success mb-2" data-weight-id="<?= $weight['id']; ?>">
                  <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                      <div class="col-md-2">
                        <label class="form-label small mb-1">Weight</label>
                        <input type="number" class="form-control form-control-sm weight-value" value="<?= $weight['weight_value']; ?>" step="0.01" min="0.01" data-weight-id="<?= $weight['id']; ?>">
                      </div>
                      <div class="col-md-1">
                        <label class="form-label small mb-1">Unit</label>
                        <select class="form-select form-select-sm weight-unit" data-weight-id="<?= $weight['id']; ?>">
                          <option value="g" <?= $weight['weight_unit'] === 'g' ? 'selected' : ''; ?>>g</option>
                          <option value="kg" <?= $weight['weight_unit'] === 'kg' ? 'selected' : ''; ?>>kg</option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small mb-1">Price (₹)</label>
                        <input type="number" class="form-control form-control-sm weight-price" value="<?= $weight['price']; ?>" step="0.01" min="0" data-weight-id="<?= $weight['id']; ?>">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">EAN/Barcode</label>
                        <input type="text" class="form-control form-control-sm weight-ean" value="<?= htmlspecialchars($weight['ean'] ?? ''); ?>" placeholder="e.g. 8901234567890" data-weight-id="<?= $weight['id']; ?>">
                      </div>
                      <div class="col-md-2">
                        <?php if ($weight['is_default'] == 1): ?>
                          <span class="badge bg-primary mt-4">Default</span>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-outline-primary mt-4 set-default-btn" data-weight-id="<?= $weight['id']; ?>">Set Default</button>
                        <?php endif; ?>
                      </div>
                      <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-outline-danger mt-4 delete-weight-btn" data-weight-id="<?= $weight['id']; ?>">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            
            <!-- Add new weight section -->
            <div class="card border-primary" style="background-color: #f8f9fa;">
              <div class="card-body p-3">
                <h6 class="mb-2">Add New Weight</h6>
                <div class="row g-2 align-items-end">
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Weight</label>
                    <input type="number" id="newWeightValue" class="form-control form-control-sm" step="0.01" min="0.01" placeholder="Enter weight">
                  </div>
                  <div class="col-md-1">
                    <label class="form-label small mb-1">Unit</label>
                    <select id="newWeightUnit" class="form-select form-select-sm">
                      <option value="g">g</option>
                      <option value="kg">kg</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Price (₹)</label>
                    <input type="number" id="newWeightPrice" class="form-control form-control-sm" step="0.01" min="0" placeholder="Enter price">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small mb-1">EAN/Barcode</label>
                    <input type="text" id="newWeightEan" class="form-control form-control-sm" placeholder="e.g. 8901234567890">
                  </div>
                  <div class="col-md-4">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="addNewWeightBtn">
                      <i class="fas fa-plus"></i> Add Weight
                    </button>
                  </div>
                </div>
              </div>
            </div>
            
            <input type="hidden" name="weights_data" id="weightsDataInput">
            <input type="hidden" name="price" id="defaultPriceHidden">
            <small class="text-muted d-block mt-2">Each weight can have its own price. Stock is managed via Batch Codes.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">GST Rate (%)</label>
            <input type="number" class="form-control" name="gst_rate" value="<?= htmlspecialchars($product['gst_rate'] ?? 5.00); ?>" step="0.01" min="0" max="100" />
            <small class="text-muted">Default: 5% for food items. Enter 0 for no GST.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">EAN Number</label>
            <input type="text" name="ean" class="form-control" value="<?= htmlspecialchars($product['ean'] ?? ''); ?>" maxlength="13" pattern="[0-9]{8,13}" />
            <small class="text-muted">8-13 digit barcode number (optional)</small>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($product['description']); ?></textarea>
          </div>
          <!-- Section Display Options -->
          <div class="col-12">
            <label class="form-label fw-semibold">Homepage Section Display</label>
            <div class="card border-light bg-light p-3">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="is_freshly_harvested" id="freshlyHarvestedToggle" value="1" <?= !empty($product['is_freshly_harvested']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="freshlyHarvestedToggle">
                  <i class="fas fa-leaf text-success me-1"></i> Show in "Freshly Harvested" section
                </label>
              </div>
              <small class="text-muted">Enable to display this product in the Freshly Harvested section on homepage</small>
            </div>
          </div>
          
          <div class="col-12">
            <label class="form-label">Product image</label>
            <input type="file" name="image" class="form-control" accept="image/*" />
            <small class="text-muted">Leave blank to keep current image. Current image shown below.</small>
          </div>
          <div class="col-12">
            <img src="<?= asset_url('images/products/' . ltrim($product['image'], '/')); ?>" alt="<?= htmlspecialchars($product['name']); ?>" class="rounded shadow-2" style="max-width: 260px;" />
          </div>
          <div class="col-12 d-flex gap-3">
            <button type="submit" class="btn btn-primary rounded-pill">Save changes</button>
            <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-outline-secondary rounded-pill">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const productId = <?= $productId; ?>;
  let weightsToDelete = [];
  
  // Handle weight value/unit/price changes
  document.querySelectorAll('.weight-value, .weight-unit, .weight-price').forEach(input => {
    input.addEventListener('change', function() {
      console.log('Weight data changed for ID:', this.dataset.weightId);
    });
  });
  
  // Handle delete weight button
  document.querySelectorAll('.delete-weight-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const weightId = this.dataset.weightId;
      const card = this.closest('.card');
      
      if (confirm('Are you sure you want to delete this weight?')) {
        weightsToDelete.push(weightId);
        card.remove();
        console.log('Weight marked for deletion:', weightId);
      }
    });
  });
  
  // Handle set default button
  document.querySelectorAll('.set-default-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const weightId = this.dataset.weightId;
      
      // Remove all default badges and show "Set Default" buttons
      document.querySelectorAll('.badge.bg-primary').forEach(badge => {
        const parent = badge.parentElement;
        badge.remove();
        parent.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary mt-4 set-default-btn" data-weight-id="' + parent.closest('.card').dataset.weightId + '">Set Default</button>';
      });
      
      // Set this as default
      this.parentElement.innerHTML = '<span class="badge bg-primary mt-4">Default</span>';
      console.log('Set default weight:', weightId);
    });
  });
  
  // Handle add new weight
  document.getElementById('addNewWeightBtn').addEventListener('click', function() {
    const value = parseFloat(document.getElementById('newWeightValue').value);
    const unit = document.getElementById('newWeightUnit').value;
    const price = parseFloat(document.getElementById('newWeightPrice').value);
    const ean = document.getElementById('newWeightEan').value.trim();
    
    if (!value || value <= 0) {
      alert('Please enter a valid weight');
      return;
    }
    
    if (!price || price <= 0) {
      alert('Please enter a valid price');
      return;
    }
    
    // Create new weight card
    const container = document.getElementById('existingWeightsContainer');
    const newCard = document.createElement('div');
    newCard.className = 'card border-success mb-2';
    newCard.dataset.weightId = 'new_' + Date.now();
    newCard.dataset.isNew = 'true';
    
    newCard.innerHTML = `
      <div class="card-body p-3">
        <div class="row g-2 align-items-center">
          <div class="col-md-2">
            <label class="form-label small mb-1">Weight</label>
            <input type="number" class="form-control form-control-sm weight-value" value="${value}" step="0.01" min="0.01" data-weight-id="${newCard.dataset.weightId}">
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">Unit</label>
            <select class="form-select form-select-sm weight-unit" data-weight-id="${newCard.dataset.weightId}">
              <option value="g" ${unit === 'g' ? 'selected' : ''}>g</option>
              <option value="kg" ${unit === 'kg' ? 'selected' : ''}>kg</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Price (₹)</label>
            <input type="number" class="form-control form-control-sm weight-price" value="${price}" step="0.01" min="0" data-weight-id="${newCard.dataset.weightId}">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">EAN/Barcode</label>
            <input type="text" class="form-control form-control-sm weight-ean" value="${ean}" placeholder="e.g. 8901234567890" data-weight-id="${newCard.dataset.weightId}">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-primary mt-4 set-default-btn" data-weight-id="${newCard.dataset.weightId}">Set Default</button>
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger mt-4 delete-weight-btn" data-weight-id="${newCard.dataset.weightId}">
              <i class="fas fa-trash"></i> Delete
            </button>
          </div>
        </div>
      </div>
    `;
    
    container.appendChild(newCard);
    
    // Attach event listeners to new buttons
    newCard.querySelector('.delete-weight-btn').addEventListener('click', function() {
      if (confirm('Are you sure you want to delete this weight?')) {
        newCard.remove();
      }
    });
    
    newCard.querySelector('.set-default-btn').addEventListener('click', function() {
      document.querySelectorAll('.badge.bg-primary').forEach(badge => {
        const parent = badge.parentElement;
        badge.remove();
        parent.innerHTML = '<button type="button" class="btn btn-sm btn-outline-primary mt-4 set-default-btn" data-weight-id="' + parent.closest('.card').dataset.weightId + '">Set Default</button>';
      });
      this.parentElement.innerHTML = '<span class="badge bg-primary mt-4">Default</span>';
    });
    
    // Clear inputs
    document.getElementById('newWeightValue').value = '';
    document.getElementById('newWeightPrice').value = '';
    document.getElementById('newWeightEan').value = '';
    document.getElementById('newWeightValue').focus();
  });
  
  // Handle form submission
  document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collect all weight data
    const weightsData = [];
    let defaultWeightId = null;
    
    document.querySelectorAll('#existingWeightsContainer .card').forEach(card => {
      const weightId = card.dataset.weightId;
      const isNew = card.dataset.isNew === 'true';
      const value = parseFloat(card.querySelector('.weight-value').value);
      const unit = card.querySelector('.weight-unit').value;
      const price = parseFloat(card.querySelector('.weight-price').value);
      const eanInput = card.querySelector('.weight-ean');
      const ean = eanInput ? eanInput.value.trim() : '';
      const isDefault = card.querySelector('.badge.bg-primary') !== null;
      
      if (isDefault) {
        defaultWeightId = weightId;
      }
      
      weightsData.push({
        id: isNew ? null : weightId,
        value: value,
        unit: unit,
        display: value + ' ' + unit,
        price: price,
        ean: ean,
        is_default: isDefault ? 1 : 0,
        is_new: isNew
      });
    });
    
    // Set hidden inputs
    document.getElementById('weightsDataInput').value = JSON.stringify({
      weights: weightsData,
      deleted: weightsToDelete
    });
    
    // Set default price
    const defaultWeight = weightsData.find(w => w.is_default === 1);
    if (defaultWeight) {
      document.getElementById('defaultPriceHidden').value = defaultWeight.price;
    }
    
    console.log('Submitting weights data:', weightsData);
    console.log('Deleted weights:', weightsToDelete);
    
    // Submit form
    this.submit();
  });
});
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
