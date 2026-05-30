<?php
/**
 * Shipping Invoice Management System
 * Lists eligible orders (new/accepted, payment verified, NOT yet shipped)
 * Allows individual + bulk print with paper size & layout options
 * Access: Admin only (inside website security)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Shipping Invoices — Admin';
$adminPage = 'shipping_invoices';

$db = get_db_connection();

// ─── Pagination & Filters ───
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
$search   = trim($_GET['search'] ?? '');

// Eligible orders: payment completed, NOT yet delivered/cancelled
$where = "o.payment_status = 'completed' AND (
    o.order_status IN ('pending','accepted','processing','new','confirmed','shipped')
    OR o.order_status = '' OR o.order_status IS NULL
)";
$params = [];

if ($dateFrom) {
    $where .= ' AND DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= ' AND DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}
if ($search) {
    $where .= ' AND (u.name LIKE ? OR CAST(o.id AS CHAR) LIKE ? OR u.phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $where");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch
$stmt = $db->prepare("
    SELECT o.id, o.created_at, o.total_amount, o.order_status, o.payment_method, o.shipping_address,
           u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE $where
    ORDER BY CASE WHEN DATE(o.created_at) = CURDATE() THEN 0 ELSE 1 END, o.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also fetch all eligible IDs for bulk print
$allIdsStmt = $db->prepare("SELECT o.id FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $where ORDER BY o.created_at DESC");
$allIdsStmt->execute($params);
$allIds = $allIdsStmt->fetchAll(PDO::FETCH_COLUMN);

// Filter query string for pagination
$filterQs = '';
if ($dateFrom) $filterQs .= '&date_from=' . urlencode($dateFrom);
if ($dateTo)   $filterQs .= '&date_to='   . urlencode($dateTo);
if ($search)   $filterQs .= '&search='    . urlencode($search);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.si-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px; }
.si-stat-card { background: #fff; border-radius: 12px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border-left: 4px solid; }
.si-stat-card.total   { border-color: #3b82f6; }
.si-stat-card.pending { border-color: #f59e0b; }
.si-stat-card.today   { border-color: #8b5cf6; }
.si-stat-card.ready   { border-color: #22c55e; }

.si-row-today { background: linear-gradient(90deg, #faf5ff 0%, #f5f3ff 40%, #fff 100%) !important; border-left: 3px solid #8b5cf6; }
.si-row-today:hover { background: linear-gradient(90deg, #f3e8ff 0%, #ede9fe 40%, #faf8ff 100%) !important; }
.si-today-badge { display: inline-flex; align-items: center; gap: 4px; background: #8b5cf6; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: .5px; }
.si-stat-num { font-size: 28px; font-weight: 700; line-height: 1.1; }
.si-stat-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }

.si-check { width: 18px; height: 18px; cursor: pointer; }
.si-row:hover { background: #f8fafc; }
.si-addr { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; color: #64748b; }

.si-bulk-bar { background: linear-gradient(135deg, #1e3a5f 0%, #1a3c34 100%); color: #fff; border-radius: 10px; padding: 14px 20px; display: none; align-items: center; gap: 16px; margin-bottom: 16px; }
.si-bulk-bar.show { display: flex; }
.si-bulk-count { font-weight: 700; font-size: 16px; }

@media print { .no-print { display: none !important; } }
</style>

<section class="py-4 no-print">
  <div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="fw-semibold mb-0"><i class="fas fa-file-invoice text-primary me-2"></i>Shipping Invoices</h4>
        <p class="text-muted mb-0">Print shipping invoices for new orders before dispatch.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="si-stats">
      <div class="si-stat-card total">
        <div class="si-stat-num text-primary"><?= $totalOrders; ?></div>
        <div class="si-stat-label">Eligible Invoices</div>
      </div>
      <div class="si-stat-card pending">
        <?php
          $pendingCount = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status='completed' AND (order_status IN ('pending','new','confirmed') OR order_status='' OR order_status IS NULL)")->fetchColumn();
        ?>
        <div class="si-stat-num text-warning"><?= $pendingCount; ?></div>
        <div class="si-stat-label">Pending Orders</div>
      </div>
      <div class="si-stat-card today">
        <?php
          $todayCount = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status='completed' AND (order_status IN ('pending','accepted','processing','new','confirmed','shipped') OR order_status='' OR order_status IS NULL) AND DATE(created_at) = CURDATE()")->fetchColumn();
        ?>
        <div class="si-stat-num" style="color:#8b5cf6;"><?= $todayCount; ?></div>
        <div class="si-stat-label">Today's Orders</div>
      </div>
      <div class="si-stat-card ready">
        <?php
          $acceptedCount = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status='completed' AND order_status IN ('accepted','processing')")->fetchColumn();
        ?>
        <div class="si-stat-num text-success"><?= $acceptedCount; ?></div>
        <div class="si-stat-label">Ready to Ship</div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
          <span class="text-muted fw-semibold" style="font-size:13px;"><i class="fas fa-filter"></i> Filter:</span>
          <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom); ?>" class="form-control form-control-sm" style="width:150px;">
          <span class="text-muted">to</span>
          <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo); ?>" class="form-control form-control-sm" style="width:150px;">
          <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" class="form-control form-control-sm" style="width:180px;" placeholder="Search name, order #, phone">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Apply</button>
          <?php if ($dateFrom || $dateTo || $search): ?>
            <a href="<?= base_url('admin/shipping_invoices.php'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> Clear</a>
          <?php endif; ?>
          <span class="ms-auto text-muted" style="font-size:12px;"><?= number_format($totalOrders); ?> eligible orders</span>
        </form>
      </div>
    </div>

    <!-- Bulk Action Bar -->
    <div class="si-bulk-bar" id="bulkBar">
      <i class="fas fa-check-double"></i>
      <span><span class="si-bulk-count" id="bulkCount">0</span> selected</span>
      <div class="ms-auto d-flex gap-2">
        <!-- Paper Size -->
        <select id="bulkPaperSize" class="form-select form-select-sm" style="width:100px; color:#000;">
          <option value="A4">A4</option>
          <option value="A5">A5</option>
        </select>
        <!-- Layout -->
        <select id="bulkLayout" class="form-select form-select-sm" style="width:160px; color:#000;">
          <option value="1">1 Invoice / A4</option>
          <option value="2">2 Invoices / A4</option>
          <option value="4">4 Invoices / A4</option>
        </select>
        <button class="btn btn-sm btn-warning fw-semibold" onclick="printSelected()"><i class="fas fa-print"></i> Print Invoices</button>
        <button class="btn btn-sm btn-light fw-semibold" onclick="printSelectedLabels()" style="color:#000;"><i class="fas fa-tag"></i> Print Labels (4×6)</button>
      </div>
    </div>

    <!-- Print All Button -->
    <?php if ($totalOrders > 0): ?>
    <div class="d-flex gap-2 mb-3">
      <div class="d-flex align-items-center gap-2">
        <select id="allPaperSize" class="form-select form-select-sm" style="width:100px;">
          <option value="A4">A4</option>
          <option value="A5">A5</option>
        </select>
        <select id="allLayout" class="form-select form-select-sm" style="width:160px;">
          <option value="1">1 Invoice / A4</option>
          <option value="2">2 Invoices / A4</option>
          <option value="4">4 Invoices / A4</option>
        </select>
        <button class="btn btn-sm btn-success fw-semibold" onclick="printAll()">
          <i class="fas fa-print"></i> Print All Invoices (<?= $totalOrders; ?>)
        </button>
        <button class="btn btn-sm btn-outline-dark fw-semibold" onclick="printAllLabels()">
          <i class="fas fa-tag"></i> Print All Labels 4×6 (<?= $totalOrders; ?>)
        </button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:40px;"><input type="checkbox" class="si-check" id="selectAll" onclick="toggleAll(this)"></th>
                <th>Order</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Ship To</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
              <?php
                $isToday = (date('Y-m-d', strtotime($o['created_at'])) === date('Y-m-d'));
                $addr = '';
                if (!empty($o['shipping_address'])) {
                    $addrData = json_decode($o['shipping_address'], true);
                    if (is_array($addrData)) {
                        $addr = implode(', ', array_filter([
                            $addrData['address_line1'] ?? '',
                            $addrData['city'] ?? '',
                            $addrData['state'] ?? '',
                            $addrData['pincode'] ?? ($addrData['zip_code'] ?? '')
                        ]));
                    } else {
                        $addr = mb_substr($o['shipping_address'], 0, 60);
                    }
                }
                $statusColors = ['pending' => 'warning', 'accepted' => 'info', 'processing' => 'primary'];
                $statusColor = $statusColors[$o['order_status']] ?? 'secondary';
              ?>
              <tr class="si-row<?= $isToday ? ' si-row-today' : ''; ?>">
                <td><input type="checkbox" class="si-check order-check" value="<?= (int)$o['id']; ?>" onchange="updateBulk()"></td>
                <td class="fw-semibold">#<?= (int)$o['id']; ?><?php if ($isToday): ?> <span class="si-today-badge"><i class="fas fa-bolt" style="font-size:8px;"></i> Today</span><?php endif; ?></td>
                <td><?= htmlspecialchars($o['customer_name'] ?? 'Guest'); ?></td>
                <td><?= htmlspecialchars($o['customer_phone'] ?? '—'); ?></td>
                <td><div class="si-addr" title="<?= htmlspecialchars($addr); ?>"><?= htmlspecialchars($addr ?: '—'); ?></div></td>
                <td class="fw-semibold">₹<?= number_format($o['total_amount'], 2); ?></td>
                <td><span class="badge bg-<?= $statusColor; ?> text-capitalize"><?= str_replace('_', ' ', $o['order_status']); ?></span></td>
                <td><span class="badge bg-success"><i class="fas fa-check-circle"></i> Verified</span><br><small class="text-muted"><?= strtoupper($o['payment_method'] ?? 'N/A'); ?></small></td>
                <td><small><?= date('M d, Y', strtotime($o['created_at'])); ?></small></td>
                <td class="text-end">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                      <i class="fas fa-ellipsis-v"></i> More
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="shipping_invoice_print.php?ids=<?= (int)$o['id']; ?>&paper=A4&layout=1" target="_blank"><i class="fas fa-print me-2"></i>Print Invoice (A4)</a></li>
                      <li><a class="dropdown-item" href="shipping_invoice_print.php?ids=<?= (int)$o['id']; ?>&paper=A5&layout=1" target="_blank"><i class="fas fa-print me-2"></i>Print Invoice (A5)</a></li>
                      <li><a class="dropdown-item" href="shipping_label_print.php?ids=<?= (int)$o['id']; ?>&layout=4" target="_blank"><i class="fas fa-tag me-2"></i>Print Shipping Label (4×4)</a></li>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item" href="order_details.php?order_id=<?= (int)$o['id']; ?>"><i class="fas fa-eye me-2"></i>View Order Details</a></li>
                    </ul>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$orders): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">
                  <?= ($dateFrom || $dateTo || $search) ? 'No eligible orders found for the selected filters.' : 'No new orders eligible for shipping invoices.'; ?>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 px-3">
        <span class="text-muted" style="font-size:13px;">Page <?= $page; ?> of <?= $totalPages; ?> &middot; <?= number_format($totalOrders); ?> records</span>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link" href="?page=<?= $page - 1; ?><?= $filterQs; ?>">&laquo;</a>
            </li>
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1' . $filterQs . '">1</a></li>';
                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            for ($i = $start; $i <= $end; $i++):
            ?>
              <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?= $i; ?><?= $filterQs; ?>"><?= $i; ?></a>
              </li>
            <?php endfor;
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . $filterQs . '">' . $totalPages . '</a></li>';
            }
            ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
              <a class="page-link" href="?page=<?= $page + 1; ?><?= $filterQs; ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info Note -->
    <div class="alert alert-info mt-3 mb-0" style="font-size:13px;">
      <i class="fas fa-info-circle me-1"></i>
      <strong>Note:</strong> Only orders with <strong>verified payment</strong> and status <strong>Pending / Accepted / Processing</strong> appear here.
      Once an order is marked as <strong>Shipped</strong>, its shipping invoice is automatically removed from this list.
    </div>

  </div>
</section>

<script>
// All eligible IDs (for Print All with current filters)
const allEligibleIds = <?= json_encode(array_map('intval', $allIds)); ?>;

function toggleAll(master) {
    document.querySelectorAll('.order-check').forEach(cb => { cb.checked = master.checked; });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.order-check:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('bulkCount').textContent = checked.length;
    bar.classList.toggle('show', checked.length > 0);
    // Update master checkbox
    const all = document.querySelectorAll('.order-check');
    document.getElementById('selectAll').checked = all.length > 0 && checked.length === all.length;
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.order-check:checked')).map(cb => cb.value);
}

function printSelected() {
    const ids = getSelectedIds();
    if (!ids.length) return alert('Select at least one order.');
    const paper = document.getElementById('bulkPaperSize').value;
    const layout = document.getElementById('bulkLayout').value;
    window.open('shipping_invoice_print.php?ids=' + ids.join(',') + '&paper=' + paper + '&layout=' + layout, '_blank');
}

function printAll() {
    if (!allEligibleIds.length) return alert('No eligible orders to print.');
    const paper = document.getElementById('allPaperSize').value;
    const layout = document.getElementById('allLayout').value;
    if (!confirm('Print shipping invoices for all ' + allEligibleIds.length + ' eligible orders?')) return;
    window.open('shipping_invoice_print.php?ids=' + allEligibleIds.join(',') + '&paper=' + paper + '&layout=' + layout, '_blank');
}

function printSelectedLabels() {
    const ids = getSelectedIds();
    if (!ids.length) return alert('Select at least one order.');
    window.open('shipping_label_print.php?ids=' + ids.join(',') + '&layout=4', '_blank');
}

function printAllLabels() {
    if (!allEligibleIds.length) return alert('No eligible orders.');
    if (!confirm('Print shipping labels (4×4) for all ' + allEligibleIds.length + ' orders?')) return;
    window.open('shipping_label_print.php?ids=' + allEligibleIds.join(',') + '&layout=4', '_blank');
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
