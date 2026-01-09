<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Discounts — Admin';
$adminPage = 'discounts';

// Get all products for dropdown
$products = admin_get_products();

// Get all discounts with product details
function get_all_discounts() {
    try {
        $sql = "SELECT pd.*, p.name as product_name, p.price as product_price 
                FROM product_discounts pd 
                LEFT JOIN products p ON pd.product_id = p.id 
                ORDER BY pd.created_at DESC";
        return db_fetch_all($sql);
    } catch (PDOException $e) {
        return [];
    }
}

$discounts = get_all_discounts();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Product Discounts</h4>
        <p class="text-muted mb-0">Manage percentage and flat discounts for products.</p>
      </div>
      <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
        <i class="fas fa-tag me-2"></i>Add Discount
      </button>
    </div>

    <!-- Discounts Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
              <tr>
                <th class="px-4 py-3">Product</th>
                <th class="py-3">Original Price</th>
                <th class="py-3">Discount Type</th>
                <th class="py-3">Discount Value</th>
                <th class="py-3">Final Price</th>
                <th class="py-3">Valid Period</th>
                <th class="py-3">Status</th>
                <th class="py-3 text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($discounts)): ?>
                <tr>
                  <td colspan="8" class="text-center py-5 text-muted">
                    <i class="fas fa-tag fa-3x mb-3 d-block opacity-25"></i>
                    <p class="mb-0">No discounts found. Click "Add Discount" to create one.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($discounts as $discount): ?>
                  <?php
                    $priceInfo = calculate_discount_price((float)$discount['product_price'], $discount);
                    $isActive = $discount['is_active'] && 
                                strtotime($discount['start_date']) <= time() && 
                                strtotime($discount['end_date']) >= time();
                  ?>
                  <tr>
                    <td class="px-4">
                      <strong><?= htmlspecialchars($discount['product_name'] ?? 'Unknown Product'); ?></strong>
                      <br><small class="text-muted">ID: <?= $discount['product_id']; ?></small>
                    </td>
                    <td>₹<?= number_format($discount['product_price'], 2); ?></td>
                    <td>
                      <span class="badge bg-<?= $discount['discount_type'] === 'percentage' ? 'info' : 'warning'; ?>">
                        <?= ucfirst($discount['discount_type']); ?>
                      </span>
                    </td>
                    <td>
                      <strong>
                        <?php if ($discount['discount_type'] === 'percentage'): ?>
                          <?= $discount['discount_value']; ?>%
                        <?php else: ?>
                          ₹<?= number_format($discount['discount_value'], 2); ?>
                        <?php endif; ?>
                      </strong>
                    </td>
                    <td>
                      <span class="text-success fw-bold">₹<?= number_format($priceInfo['discounted_price'], 2); ?></span>
                      <small class="text-muted d-block">(<?= round($priceInfo['discount_percentage']); ?>% off)</small>
                    </td>
                    <td>
                      <small>
                        <?= date('M d, Y', strtotime($discount['start_date'])); ?><br>
                        to <?= date('M d, Y', strtotime($discount['end_date'])); ?>
                      </small>
                    </td>
                    <td>
                      <?php if ($isActive): ?>
                        <span class="badge bg-success">Active</span>
                      <?php elseif (!$discount['is_active']): ?>
                        <span class="badge bg-secondary">Disabled</span>
                      <?php elseif (strtotime($discount['start_date']) > time()): ?>
                        <span class="badge bg-info">Scheduled</span>
                      <?php else: ?>
                        <span class="badge bg-danger">Expired</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                      <button class="btn btn-sm btn-outline-primary me-1" 
                              onclick="editDiscount(<?= htmlspecialchars(json_encode($discount)); ?>)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger" 
                              onclick="deleteDiscount(<?= $discount['id']; ?>, '<?= htmlspecialchars($discount['product_name']); ?>')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Add Discount Modal -->
<div class="modal fade" id="addDiscountModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-tag me-2"></i>Add New Discount</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addDiscountForm">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Product *</label>
            <select class="form-select" name="product_id" required>
              <option value="">Select a product</option>
              <?php foreach ($products as $product): ?>
                <option value="<?= $product['id']; ?>">
                  <?= htmlspecialchars($product['name']); ?> (₹<?= number_format($product['price'], 2); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Discount Type *</label>
            <select class="form-select" name="discount_type" id="discountType" required>
              <option value="percentage">Percentage (%)</option>
              <option value="flat">Flat Amount (₹)</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Discount Value *</label>
            <input type="number" class="form-control" name="discount_value" 
                   step="0.01" min="0" max="100" required 
                   placeholder="e.g., 15 for 15% or 50 for ₹50">
            <small class="text-muted" id="discountHint">Enter percentage (0-100)</small>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Date *</label>
              <input type="datetime-local" class="form-control" name="start_date" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Date *</label>
              <input type="datetime-local" class="form-control" name="end_date" required>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
              <label class="form-check-label" for="isActive">Active</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Discount</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Discount Modal -->
<div class="modal fade" id="editDiscountModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Discount</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editDiscountForm">
        <input type="hidden" name="discount_id" id="editDiscountId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Product *</label>
            <select class="form-select" name="product_id" id="editProductId" required>
              <?php foreach ($products as $product): ?>
                <option value="<?= $product['id']; ?>">
                  <?= htmlspecialchars($product['name']); ?> (₹<?= number_format($product['price'], 2); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Discount Type *</label>
            <select class="form-select" name="discount_type" id="editDiscountType" required>
              <option value="percentage">Percentage (%)</option>
              <option value="flat">Flat Amount (₹)</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Discount Value *</label>
            <input type="number" class="form-control" name="discount_value" id="editDiscountValue"
                   step="0.01" min="0" required>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Date *</label>
              <input type="datetime-local" class="form-control" name="start_date" id="editStartDate" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Date *</label>
              <input type="datetime-local" class="form-control" name="end_date" id="editEndDate" required>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
              <label class="form-check-label" for="editIsActive">Active</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Discount</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Update hint based on discount type
document.getElementById('discountType').addEventListener('change', function() {
  const hint = document.getElementById('discountHint');
  const input = document.querySelector('[name="discount_value"]');
  if (this.value === 'percentage') {
    hint.textContent = 'Enter percentage (0-100)';
    input.max = 100;
  } else {
    hint.textContent = 'Enter flat amount in ₹';
    input.max = 999999;
  }
});

// Add discount
document.getElementById('addDiscountForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'add');
  
  try {
    const response = await fetch('discount_actions.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      alert('Discount added successfully!');
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error adding discount: ' + error.message);
  }
});

// Edit discount
function editDiscount(discount) {
  document.getElementById('editDiscountId').value = discount.id;
  document.getElementById('editProductId').value = discount.product_id;
  document.getElementById('editDiscountType').value = discount.discount_type;
  document.getElementById('editDiscountValue').value = discount.discount_value;
  document.getElementById('editStartDate').value = discount.start_date.replace(' ', 'T');
  document.getElementById('editEndDate').value = discount.end_date.replace(' ', 'T');
  document.getElementById('editIsActive').checked = discount.is_active == 1;
  
  new bootstrap.Modal(document.getElementById('editDiscountModal')).show();
}

document.getElementById('editDiscountForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'edit');
  
  try {
    const response = await fetch('discount_actions.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      alert('Discount updated successfully!');
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error updating discount: ' + error.message);
  }
});

// Delete discount
async function deleteDiscount(id, productName) {
  if (!confirm(`Are you sure you want to delete the discount for "${productName}"?`)) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('discount_id', id);
    
    const response = await fetch('discount_actions.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      alert('Discount deleted successfully!');
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error deleting discount: ' + error.message);
  }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
