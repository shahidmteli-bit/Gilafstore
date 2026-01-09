<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Promo Codes — Admin';
$adminPage = 'promo_codes';

function get_all_promo_codes() {
    try {
        $sql = "SELECT * FROM promo_codes ORDER BY created_at DESC";
        return db_fetch_all($sql);
    } catch (PDOException $e) {
        return [];
    }
}

function get_promo_code_stats($promoId) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_uses,
                    SUM(discount_amount) as total_discount_given,
                    COUNT(DISTINCT user_id) as unique_users
                FROM promo_code_usage 
                WHERE promo_code_id = ?";
        return db_fetch($sql, [$promoId]) ?? ['total_uses' => 0, 'total_discount_given' => 0, 'unique_users' => 0];
    } catch (PDOException $e) {
        return ['total_uses' => 0, 'total_discount_given' => 0, 'unique_users' => 0];
    }
}

$promoCodes = get_all_promo_codes();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0"><i class="fas fa-ticket-alt me-2 text-primary"></i>Promo Code Management</h4>
        <p class="text-muted mb-0">Create and manage promotional discount codes for customers</p>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= base_url('admin/promo_analytics.php'); ?>" class="btn btn-outline-primary rounded-pill shadow-sm">
          <i class="fas fa-chart-line me-2"></i>View Analytics
        </a>
        <button class="btn btn-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addPromoModal">
          <i class="fas fa-plus-circle me-2"></i>Create Promo Code
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                  <i class="fas fa-ticket-alt fa-2x text-primary"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Total Codes</h6>
                <h3 class="mb-0"><?= count($promoCodes); ?></h3>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-success bg-opacity-10 rounded-3 p-3">
                  <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Active Codes</h6>
                <h3 class="mb-0"><?= count(array_filter($promoCodes, fn($p) => $p['is_active'] && strtotime($p['valid_until']) >= time())); ?></h3>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                  <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Scheduled</h6>
                <h3 class="mb-0"><?= count(array_filter($promoCodes, fn($p) => $p['is_active'] && strtotime($p['valid_from']) > time())); ?></h3>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0">
                <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                  <i class="fas fa-ban fa-2x text-danger"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Expired</h6>
                <h3 class="mb-0"><?= count(array_filter($promoCodes, fn($p) => strtotime($p['valid_until']) < time())); ?></h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Promo Codes Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
              <tr>
                <th class="px-4 py-3">Code</th>
                <th class="py-3">Description</th>
                <th class="py-3">Discount</th>
                <th class="py-3">Min Order</th>
                <th class="py-3">Usage</th>
                <th class="py-3">Valid Period</th>
                <th class="py-3">Status</th>
                <th class="py-3 text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($promoCodes)): ?>
                <tr>
                  <td colspan="8" class="text-center py-5 text-muted">
                    <i class="fas fa-ticket-alt fa-3x mb-3 d-block opacity-25"></i>
                    <p class="mb-0">No promo codes found. Click "Create Promo Code" to add one.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($promoCodes as $promo): ?>
                  <?php
                    $stats = get_promo_code_stats($promo['id']);
                    $isActive = $promo['is_active'] && 
                                strtotime($promo['valid_from']) <= time() && 
                                strtotime($promo['valid_until']) >= time();
                    $isScheduled = $promo['is_active'] && strtotime($promo['valid_from']) > time();
                    $isExpired = strtotime($promo['valid_until']) < time();
                    $usagePercent = $promo['usage_limit'] ? ($promo['used_count'] / $promo['usage_limit']) * 100 : 0;
                  ?>
                  <tr>
                    <td class="px-4">
                      <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded px-3 py-2">
                          <code class="text-primary fw-bold fs-6"><?= htmlspecialchars($promo['code']); ?></code>
                        </div>
                        <button class="btn btn-sm btn-link text-muted ms-2" onclick="copyCode('<?= htmlspecialchars($promo['code']); ?>')" title="Copy code">
                          <i class="fas fa-copy"></i>
                        </button>
                      </div>
                    </td>
                    <td>
                      <small class="text-muted"><?= htmlspecialchars($promo['description'] ?? 'No description'); ?></small>
                    </td>
                    <td>
                      <?php if ($promo['discount_type'] === 'percentage'): ?>
                        <span class="badge bg-info text-dark"><?= $promo['discount_value']; ?>% OFF</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">₹<?= number_format($promo['discount_value'], 0); ?> OFF</span>
                      <?php endif; ?>
                      <?php if ($promo['max_discount']): ?>
                        <br><small class="text-muted">Max: ₹<?= number_format($promo['max_discount'], 0); ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($promo['min_order_value'] > 0): ?>
                        <small>₹<?= number_format($promo['min_order_value'], 0); ?></small>
                      <?php else: ?>
                        <small class="text-muted">No minimum</small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                          <?php if ($promo['usage_limit']): ?>
                            <div class="progress" style="height: 6px;">
                              <div class="progress-bar <?= $usagePercent >= 100 ? 'bg-danger' : ($usagePercent >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                   style="width: <?= min($usagePercent, 100); ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $promo['used_count']; ?> / <?= $promo['usage_limit']; ?> used</small>
                          <?php else: ?>
                            <small class="text-muted"><?= $promo['used_count']; ?> used</small>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td>
                      <small>
                        <?= date('M d, Y', strtotime($promo['valid_from'])); ?><br>
                        <span class="text-muted">to</span> <?= date('M d, Y', strtotime($promo['valid_until'])); ?>
                      </small>
                    </td>
                    <td>
                      <?php if ($isActive): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                      <?php elseif ($isScheduled): ?>
                        <span class="badge bg-info"><i class="fas fa-clock me-1"></i>Scheduled</span>
                      <?php elseif ($isExpired): ?>
                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Expired</span>
                      <?php elseif (!$promo['is_active']): ?>
                        <span class="badge bg-secondary"><i class="fas fa-pause-circle me-1"></i>Disabled</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                      <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="viewStats(<?= htmlspecialchars(json_encode($promo)); ?>, <?= htmlspecialchars(json_encode($stats)); ?>)"
                                title="View Statistics">
                          <i class="fas fa-chart-bar"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" 
                                onclick="editPromo(<?= htmlspecialchars(json_encode($promo)); ?>)"
                                title="Edit">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="deletePromo(<?= $promo['id']; ?>, '<?= htmlspecialchars($promo['code']); ?>')"
                                title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
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

<!-- Add Promo Code Modal -->
<div class="modal fade" id="addPromoModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create New Promo Code</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addPromoForm">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-8 mb-3">
              <label class="form-label fw-semibold">Promo Code *</label>
              <input type="text" class="form-control form-control-lg" name="code" required 
                     placeholder="e.g., SUMMER2026" pattern="[A-Z0-9]+" maxlength="50"
                     style="font-family: monospace; letter-spacing: 2px;">
              <small class="text-muted">Use uppercase letters and numbers only</small>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label fw-semibold">&nbsp;</label>
              <button type="button" class="btn btn-outline-secondary w-100" onclick="generateCode()">
                <i class="fas fa-random me-2"></i>Generate
              </button>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" name="description" rows="2" 
                      placeholder="e.g., Summer sale - 20% off on all products"></textarea>
            <small class="text-muted">Internal note (not shown to customers)</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Header Display Message</label>
            <textarea class="form-control" name="promo_message" rows="2" id="addPromoMessage"
                      placeholder="e.g., 🎉 New User Offer! Use code {CODE} & get up to {DISCOUNT} OFF" maxlength="500"></textarea>
            <small class="text-muted">
              Message shown in website header. Use {CODE} for promo code, {DISCOUNT} for discount value. Emojis supported! ✨
            </small>
            <div class="mt-2">
              <strong>Quick Templates:</strong>
              <div class="btn-group btn-group-sm mt-1" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('add', 'new')">🎉 New User</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('add', 'first')">🛒 First Order</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('add', 'return')">💚 Welcome Back</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('add', 'limited')">⏰ Limited Time</button>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">User Eligibility *</label>
            <select class="form-select" name="eligibility_type" id="addEligibilityType" required>
              <option value="all_users">All Users</option>
              <option value="new_users">New Users Only (No account)</option>
              <option value="first_time">First-Time Buyers (0 orders)</option>
              <option value="second_time">Second-Time Buyers (1 order)</option>
              <option value="first_second_time">First & Second-Time Buyers</option>
              <option value="third_time">Third-Time Buyers (2 orders)</option>
              <option value="repeat_users">Repeat Customers (4+ orders)</option>
              <option value="returning_inactive">Returning Inactive Users</option>
              <option value="all_existing">All Existing Customers</option>
            </select>
            <small class="text-muted">Define which customer segments can use this promo code</small>
          </div>
          
          <div class="mb-3" id="addInactiveDaysField" style="display: none;">
            <label class="form-label fw-semibold">Inactive Days Required</label>
            <input type="number" class="form-control" name="inactive_days" min="1" value="30" placeholder="30">
            <small class="text-muted">Number of days since last order for returning inactive users</small>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Discount Type *</label>
              <select class="form-select" name="discount_type" id="addDiscountType" required>
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Amount (₹)</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Discount Value *</label>
              <input type="number" class="form-control" name="discount_value" 
                     step="0.01" min="0" required placeholder="e.g., 20">
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Minimum Order Value</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control" name="min_order_value" 
                       step="0.01" min="0" value="0" placeholder="0 for no minimum">
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Maximum Discount</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control" name="max_discount" 
                       step="0.01" min="0" placeholder="Leave empty for no limit">
              </div>
              <small class="text-muted">Only for percentage discounts</small>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Usage Limit</label>
            <input type="number" class="form-control" name="usage_limit" 
                   min="1" placeholder="Leave empty for unlimited uses">
            <small class="text-muted">Total number of times this code can be used</small>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Valid From *</label>
              <input type="datetime-local" class="form-control" name="valid_from" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Valid Until *</label>
              <input type="datetime-local" class="form-control" name="valid_until" required>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" checked>
              <label class="form-check-label fw-semibold" for="addIsActive">
                Active <small class="text-muted">(Code will be available for use)</small>
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="display_in_header" id="addDisplayHeader">
              <label class="form-check-label fw-semibold" for="addDisplayHeader">
                Display in Website Header <small class="text-muted">(Show promo code to visitors)</small>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Create Promo Code
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Promo Code Modal -->
<div class="modal fade" id="editPromoModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Promo Code</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="editPromoForm">
        <input type="hidden" name="promo_id" id="editPromoId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Promo Code *</label>
            <input type="text" class="form-control form-control-lg" name="code" id="editCode" required 
                   pattern="[A-Z0-9]+" maxlength="50" style="font-family: monospace; letter-spacing: 2px;">
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" name="description" id="editDescription" rows="2"></textarea>
            <small class="text-muted">Internal note (not shown to customers)</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Header Display Message</label>
            <textarea class="form-control" name="promo_message" rows="2" id="editPromoMessage"
                      placeholder="e.g., 🎉 New User Offer! Use code {CODE} & get up to {DISCOUNT} OFF" maxlength="500"></textarea>
            <small class="text-muted">
              Message shown in website header. Use {CODE} for promo code, {DISCOUNT} for discount value. Emojis supported! ✨
            </small>
            <div class="mt-2">
              <strong>Quick Templates:</strong>
              <div class="btn-group btn-group-sm mt-1" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('edit', 'new')">🎉 New User</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('edit', 'first')">🛒 First Order</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('edit', 'return')">💚 Welcome Back</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setTemplate('edit', 'limited')">⏰ Limited Time</button>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">User Eligibility *</label>
            <select class="form-select" name="eligibility_type" id="editEligibilityType" required>
              <option value="all_users">All Users</option>
              <option value="new_users">New Users Only (No account)</option>
              <option value="first_time">First-Time Buyers (0 orders)</option>
              <option value="second_time">Second-Time Buyers (1 order)</option>
              <option value="first_second_time">First & Second-Time Buyers</option>
              <option value="third_time">Third-Time Buyers (2 orders)</option>
              <option value="repeat_users">Repeat Customers (4+ orders)</option>
              <option value="returning_inactive">Returning Inactive Users</option>
              <option value="all_existing">All Existing Customers</option>
            </select>
          </div>
          
          <div class="mb-3" id="editInactiveDaysField" style="display: none;">
            <label class="form-label fw-semibold">Inactive Days Required</label>
            <input type="number" class="form-control" name="inactive_days" id="editInactiveDays" min="1" value="30">
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Discount Type *</label>
              <select class="form-select" name="discount_type" id="editDiscountType" required>
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Amount (₹)</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Discount Value *</label>
              <input type="number" class="form-control" name="discount_value" id="editDiscountValue"
                     step="0.01" min="0" required>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Minimum Order Value</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control" name="min_order_value" id="editMinOrder"
                       step="0.01" min="0">
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Maximum Discount</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control" name="max_discount" id="editMaxDiscount"
                       step="0.01" min="0">
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Usage Limit</label>
            <input type="number" class="form-control" name="usage_limit" id="editUsageLimit" min="1">
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Valid From *</label>
              <input type="datetime-local" class="form-control" name="valid_from" id="editValidFrom" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Valid Until *</label>
              <input type="datetime-local" class="form-control" name="valid_until" id="editValidUntil" required>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
              <label class="form-check-label fw-semibold" for="editIsActive">Active</label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="display_in_header" id="editDisplayHeader">
              <label class="form-check-label fw-semibold" for="editDisplayHeader">Display in Website Header</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info text-white">
            <i class="fas fa-save me-2"></i>Update Promo Code
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Stats Modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-chart-bar me-2"></i>Promo Code Statistics</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <h6 class="mb-3">Code: <code id="statsCode" class="text-primary fs-5"></code></h6>
        <div class="row text-center">
          <div class="col-4">
            <div class="p-3 bg-light rounded">
              <h3 class="mb-0" id="statsTotalUses">0</h3>
              <small class="text-muted">Total Uses</small>
            </div>
          </div>
          <div class="col-4">
            <div class="p-3 bg-light rounded">
              <h3 class="mb-0" id="statsUniqueUsers">0</h3>
              <small class="text-muted">Unique Users</small>
            </div>
          </div>
          <div class="col-4">
            <div class="p-3 bg-light rounded">
              <h3 class="mb-0" id="statsTotalDiscount">₹0</h3>
              <small class="text-muted">Total Discount</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Toggle inactive days field based on eligibility type
document.getElementById('addEligibilityType').addEventListener('change', function() {
  const inactiveDaysField = document.getElementById('addInactiveDaysField');
  inactiveDaysField.style.display = this.value === 'returning_inactive' ? 'block' : 'none';
});

document.getElementById('editEligibilityType').addEventListener('change', function() {
  const inactiveDaysField = document.getElementById('editInactiveDaysField');
  inactiveDaysField.style.display = this.value === 'returning_inactive' ? 'block' : 'none';
});

// Generate random promo code
function generateCode() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let code = '';
  for (let i = 0; i < 8; i++) {
    code += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  document.querySelector('#addPromoModal [name="code"]').value = code;
}

// Copy code to clipboard
function copyCode(code) {
  navigator.clipboard.writeText(code).then(() => {
    alert('Code copied: ' + code);
  });
}

// Add promo code
document.getElementById('addPromoForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'add');
  
  try {
    const response = await fetch('promo_code_actions.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      alert('Promo code created successfully!');
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error creating promo code: ' + error.message);
  }
});

// Template messages
function setTemplate(formType, templateType) {
  const templates = {
    'new': '🎉 New User Offer! Use code {CODE} & get up to {DISCOUNT} OFF',
    'first': '🛒 First Order Special — Use code {CODE} & save {DISCOUNT}',
    'return': '💚 Welcome Back! Use code {CODE} & save {DISCOUNT}',
    'limited': '⏰ Today Only — Use code {CODE} & get {DISCOUNT} OFF'
  };
  
  const fieldId = formType === 'add' ? 'addPromoMessage' : 'editPromoMessage';
  document.getElementById(fieldId).value = templates[templateType];
}

// Edit promo code
function editPromo(promo) {
  document.getElementById('editPromoId').value = promo.id;
  document.getElementById('editCode').value = promo.code;
  document.getElementById('editDescription').value = promo.description || '';
  document.getElementById('editPromoMessage').value = promo.promo_message || '';
  document.getElementById('editEligibilityType').value = promo.eligibility_type || 'all_users';
  document.getElementById('editInactiveDays').value = promo.inactive_days || 30;
  document.getElementById('editInactiveDaysField').style.display = promo.eligibility_type === 'returning_inactive' ? 'block' : 'none';
  document.getElementById('editDiscountType').value = promo.discount_type;
  document.getElementById('editDiscountValue').value = promo.discount_value;
  document.getElementById('editMinOrder').value = promo.min_order_value;
  document.getElementById('editMaxDiscount').value = promo.max_discount || '';
  document.getElementById('editUsageLimit').value = promo.usage_limit || '';
  document.getElementById('editValidFrom').value = promo.valid_from.replace(' ', 'T');
  document.getElementById('editValidUntil').value = promo.valid_until.replace(' ', 'T');
  document.getElementById('editIsActive').checked = promo.is_active == 1;
  document.getElementById('editDisplayHeader').checked = promo.display_in_header == 1;
  
  new bootstrap.Modal(document.getElementById('editPromoModal')).show();
}

document.getElementById('editPromoForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'edit');
  
  try {
    const response = await fetch('promo_code_actions.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      alert('Promo code updated successfully!');
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error updating promo code: ' + error.message);
  }
});

// Delete promo code
async function deletePromo(id, code) {
  if (!confirm(`Are you sure you want to delete the promo code "${code}"?`)) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('promo_id', id);
    
    const response = await fetch('promo_code_actions.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      alert('Promo code deleted successfully!');
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error deleting promo code: ' + error.message);
  }
}

// View statistics
function viewStats(promo, stats) {
  document.getElementById('statsCode').textContent = promo.code;
  document.getElementById('statsTotalUses').textContent = stats.total_uses;
  document.getElementById('statsUniqueUsers').textContent = stats.unique_users;
  document.getElementById('statsTotalDiscount').textContent = '₹' + parseFloat(stats.total_discount_given || 0).toFixed(2);
  
  new bootstrap.Modal(document.getElementById('statsModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
