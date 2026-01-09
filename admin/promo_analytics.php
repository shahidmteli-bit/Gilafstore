<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/promo_functions.php';

require_admin();

$pageTitle = 'Promo Code Analytics — Admin';
$adminPage = 'promo_codes';

// Get filter parameters
$filterPromoId = $_GET['promo_id'] ?? null;
$filterDateFrom = $_GET['date_from'] ?? null;
$filterDateTo = $_GET['date_to'] ?? null;
$filterUserType = $_GET['user_type'] ?? null;

// Get all promo codes for filter dropdown
$allPromoCodes = db_fetch_all("SELECT id, code FROM promo_codes ORDER BY code");

// Get analytics data
$analyticsData = get_promo_usage_analytics($filterPromoId, $filterDateFrom, $filterDateTo, $filterUserType);

// Calculate summary statistics
$totalUsage = count($analyticsData);
$totalDiscount = array_sum(array_column($analyticsData, 'discount_amount'));
$uniqueUsers = count(array_unique(array_filter(array_column($analyticsData, 'user_email'))));
$avgDiscount = $totalUsage > 0 ? $totalDiscount / $totalUsage : 0;

// Group by user type
$userTypeBreakdown = [];
foreach ($analyticsData as $usage) {
    $type = $usage['user_type'] ?? 'Unknown';
    if (!isset($userTypeBreakdown[$type])) {
        $userTypeBreakdown[$type] = ['count' => 0, 'discount' => 0];
    }
    $userTypeBreakdown[$type]['count']++;
    $userTypeBreakdown[$type]['discount'] += $usage['discount_amount'];
}

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Promo Code Analytics</h4>
        <p class="text-muted mb-0">Detailed usage statistics and user-level insights</p>
      </div>
      <a href="<?= base_url('admin/manage_promo_codes.php'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Promo Codes
      </a>
    </div>

    <!-- Summary Cards -->
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
                <h6 class="text-muted mb-1">Total Usage</h6>
                <h3 class="mb-0"><?= $totalUsage; ?></h3>
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
                  <i class="fas fa-users fa-2x text-success"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Unique Users</h6>
                <h3 class="mb-0"><?= $uniqueUsers; ?></h3>
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
                  <i class="fas fa-rupee-sign fa-2x text-warning"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Total Discount</h6>
                <h3 class="mb-0">₹<?= number_format($totalDiscount, 2); ?></h3>
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
                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                  <i class="fas fa-chart-bar fa-2x text-info"></i>
                </div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="text-muted mb-1">Avg Discount</h6>
                <h3 class="mb-0">₹<?= number_format($avgDiscount, 2); ?></h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title mb-3"><i class="fas fa-filter me-2"></i>Filters</h5>
        <form method="GET" action="">
          <div class="row">
            <div class="col-md-3 mb-3">
              <label class="form-label">Promo Code</label>
              <select class="form-select" name="promo_id">
                <option value="">All Promo Codes</option>
                <?php foreach ($allPromoCodes as $promo): ?>
                  <option value="<?= $promo['id']; ?>" <?= $filterPromoId == $promo['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($promo['code']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label">Date From</label>
              <input type="date" class="form-select" name="date_from" value="<?= htmlspecialchars($filterDateFrom ?? ''); ?>">
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label">Date To</label>
              <input type="date" class="form-select" name="date_to" value="<?= htmlspecialchars($filterDateTo ?? ''); ?>">
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">User Type</label>
              <select class="form-select" name="user_type">
                <option value="">All User Types</option>
                <option value="First-Time Buyer" <?= $filterUserType === 'First-Time Buyer' ? 'selected' : ''; ?>>First-Time Buyer</option>
                <option value="Second-Time Buyer" <?= $filterUserType === 'Second-Time Buyer' ? 'selected' : ''; ?>>Second-Time Buyer</option>
                <option value="Third-Time Buyer" <?= $filterUserType === 'Third-Time Buyer' ? 'selected' : ''; ?>>Third-Time Buyer</option>
                <option value="Repeat Customer" <?= $filterUserType === 'Repeat Customer' ? 'selected' : ''; ?>>Repeat Customer</option>
              </select>
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                  <i class="fas fa-search me-1"></i>Filter
                </button>
                <a href="<?= base_url('admin/promo_analytics.php'); ?>" class="btn btn-outline-secondary">
                  <i class="fas fa-redo"></i>
                </a>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- User Type Breakdown -->
    <?php if (!empty($userTypeBreakdown)): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title mb-3"><i class="fas fa-users me-2"></i>User Type Breakdown</h5>
        <div class="row">
          <?php foreach ($userTypeBreakdown as $type => $data): ?>
            <div class="col-md-3 mb-3">
              <div class="p-3 bg-light rounded">
                <h6 class="text-muted mb-1"><?= htmlspecialchars($type); ?></h6>
                <h4 class="mb-0"><?= $data['count']; ?> <small class="text-muted">uses</small></h4>
                <small class="text-success">₹<?= number_format($data['discount'], 2); ?> discount</small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Usage Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Detailed Usage Records</h5>
        <button class="btn btn-sm btn-success" onclick="exportToCSV()">
          <i class="fas fa-file-excel me-1"></i>Export to CSV
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="analyticsTable">
            <thead class="bg-light">
              <tr>
                <th class="px-4 py-3">Promo Code</th>
                <th class="py-3">User</th>
                <th class="py-3">User Type</th>
                <th class="py-3">Order Count</th>
                <th class="py-3">Order Value</th>
                <th class="py-3">Discount</th>
                <th class="py-3">Final Value</th>
                <th class="py-3">Date</th>
                <th class="py-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($analyticsData)): ?>
                <tr>
                  <td colspan="9" class="text-center py-5 text-muted">
                    <i class="fas fa-chart-line fa-3x mb-3 d-block opacity-25"></i>
                    <p class="mb-0">No usage data found for the selected filters.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($analyticsData as $usage): ?>
                  <tr>
                    <td class="px-4">
                      <code class="text-primary fw-bold"><?= htmlspecialchars($usage['code']); ?></code>
                    </td>
                    <td>
                      <?php if ($usage['user_email']): ?>
                        <div>
                          <small class="text-muted">Email:</small> <?= htmlspecialchars($usage['user_email']); ?>
                        </div>
                        <?php if ($usage['user_phone']): ?>
                          <div>
                            <small class="text-muted">Phone:</small> <?= htmlspecialchars($usage['user_phone']); ?>
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">Guest User</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-info"><?= htmlspecialchars($usage['user_type'] ?? 'Unknown'); ?></span>
                    </td>
                    <td><?= $usage['order_count_at_use']; ?></td>
                    <td>₹<?= number_format($usage['order_value'] ?? 0, 2); ?></td>
                    <td class="text-success fw-bold">-₹<?= number_format($usage['discount_amount'], 2); ?></td>
                    <td>₹<?= number_format(($usage['order_value'] ?? 0) - $usage['discount_amount'], 2); ?></td>
                    <td>
                      <small><?= date('M d, Y H:i', strtotime($usage['used_at'])); ?></small>
                    </td>
                    <td>
                      <?php if ($usage['order_status'] === 'completed'): ?>
                        <span class="badge bg-success">Completed</span>
                      <?php elseif ($usage['order_status'] === 'pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                      <?php elseif ($usage['order_status'] === 'cancelled'): ?>
                        <span class="badge bg-danger">Cancelled</span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars($usage['order_status'] ?? 'N/A'); ?></span>
                      <?php endif; ?>
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

<script>
// Export to CSV
function exportToCSV() {
  const table = document.getElementById('analyticsTable');
  let csv = [];
  
  // Get headers
  const headers = [];
  table.querySelectorAll('thead th').forEach(th => {
    headers.push(th.textContent.trim());
  });
  csv.push(headers.join(','));
  
  // Get data rows
  table.querySelectorAll('tbody tr').forEach(tr => {
    const row = [];
    tr.querySelectorAll('td').forEach(td => {
      // Clean up the text content
      let text = td.textContent.trim().replace(/\s+/g, ' ');
      // Escape quotes and wrap in quotes if contains comma
      if (text.includes(',') || text.includes('"')) {
        text = '"' + text.replace(/"/g, '""') + '"';
      }
      row.push(text);
    });
    if (row.length > 0) {
      csv.push(row.join(','));
    }
  });
  
  // Create download
  const csvContent = csv.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  
  link.setAttribute('href', url);
  link.setAttribute('download', 'promo_analytics_' + new Date().toISOString().split('T')[0] + '.csv');
  link.style.visibility = 'hidden';
  
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
