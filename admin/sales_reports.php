<?php
/**
 * Admin Panel - Sales Reports Dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Reports';
$adminPage = 'sales_reports';

// Date range
$period = $_GET['period'] ?? 'this_month';
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');

switch ($period) {
    case 'today':
        $startDate = $endDate = date('Y-m-d');
        break;
    case 'this_week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d');
        break;
    case 'this_month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'last_month':
        $startDate = date('Y-m-01', strtotime('-1 month'));
        $endDate = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'this_year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
    case 'custom':
        $startDate = $_GET['start'] ?? date('Y-m-01');
        $endDate = $_GET['end'] ?? date('Y-m-t');
        break;
}

// Overall Stats
$totalOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate])['cnt'] ?? 0;
$totalRevenue = db_fetch('SELECT COALESCE(SUM(total_amount),0) as total FROM sales_orders WHERE status IN ("approved","dispatched","delivered") AND DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate])['total'] ?? 0;
$pendingOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE status = "pending" AND DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate])['cnt'] ?? 0;
$totalOutstanding = db_fetch('SELECT COALESCE(SUM(outstanding_amount),0) as total FROM sales_parties')['total'] ?? 0;

// Orders by Sales Person
$byExecutive = db_fetch_all('SELECT se.name, se.district, COUNT(so.id) as order_count, COALESCE(SUM(CASE WHEN so.status IN ("approved","dispatched","delivered") THEN so.total_amount ELSE 0 END),0) as revenue, COUNT(CASE WHEN so.status = "pending" THEN 1 END) as pending_count FROM sales_executives se LEFT JOIN sales_orders so ON so.executive_id = se.id AND DATE(so.created_at) BETWEEN ? AND ? WHERE se.is_active = 1 GROUP BY se.id ORDER BY revenue DESC', [$startDate, $endDate]);

// Orders by District
$byDistrict = db_fetch_all('SELECT so.district, COUNT(*) as order_count, COALESCE(SUM(CASE WHEN so.status IN ("approved","dispatched","delivered") THEN so.total_amount ELSE 0 END),0) as revenue FROM sales_orders so WHERE so.district IS NOT NULL AND DATE(so.created_at) BETWEEN ? AND ? GROUP BY so.district ORDER BY revenue DESC', [$startDate, $endDate]);

// Outstanding by Party (top 10)
$topOutstanding = db_fetch_all('SELECT shop_name, owner_name, district, outstanding_amount, credit_limit FROM sales_parties WHERE outstanding_amount > 0 ORDER BY outstanding_amount DESC LIMIT 10');

// Monthly Sales Summary (current year)
$monthlySales = db_fetch_all('SELECT MONTH(created_at) as month, COUNT(*) as orders, COALESCE(SUM(CASE WHEN status IN ("approved","dispatched","delivered") THEN total_amount ELSE 0 END),0) as revenue FROM sales_orders WHERE YEAR(created_at) = YEAR(NOW()) GROUP BY MONTH(created_at) ORDER BY month');

$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Reports</h2>
            <p class="text-muted mb-0">Performance analytics for your sales team</p>
        </div>
    </div>

    <!-- Period Filter -->
    <div class="d-flex gap-2 flex-wrap mb-4">
        <?php foreach (['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year'] as $key => $label): ?>
            <a href="<?= base_url('admin/sales_reports.php?period=' . $key) ?>" class="btn btn-sm <?= $period === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(59,130,246,0.1);color:#3b82f6;font-size:20px;"><i class="fas fa-clipboard-list"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $totalOrders ?></h3>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(16,185,129,0.1);color:#10b981;font-size:20px;"><i class="fas fa-rupee-sign"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0">₹<?= number_format($totalRevenue, 0) ?></h3>
                            <small class="text-muted">Revenue</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(245,158,11,0.1);color:#f59e0b;font-size:20px;"><i class="fas fa-clock"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $pendingOrders ?></h3>
                            <small class="text-muted">Pending Approval</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(239,68,68,0.1);color:#ef4444;font-size:20px;"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0">₹<?= number_format($totalOutstanding, 0) ?></h3>
                            <small class="text-muted">Total Outstanding</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Orders by Sales Person -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-tie text-primary me-2"></i>Orders by Sales Person</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Executive</th>
                                    <th>District</th>
                                    <th>Orders</th>
                                    <th>Pending</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byExecutive)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No data</td></tr>
                                <?php else: ?>
                                <?php foreach ($byExecutive as $ex): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($ex['name']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($ex['district']) ?></td>
                                    <td><span class="badge bg-primary"><?= $ex['order_count'] ?></span></td>
                                    <td><?php if ($ex['pending_count'] > 0): ?><span class="badge bg-warning text-dark"><?= $ex['pending_count'] ?></span><?php else: ?>-<?php endif; ?></td>
                                    <td class="fw-bold text-success">₹<?= number_format($ex['revenue'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders by District -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-map-marked-alt text-primary me-2"></i>Orders by District</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>District</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byDistrict)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No data</td></tr>
                                <?php else: ?>
                                <?php foreach ($byDistrict as $d): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($d['district']) ?></td>
                                    <td><span class="badge bg-primary"><?= $d['order_count'] ?></span></td>
                                    <td class="fw-bold text-success">₹<?= number_format($d['revenue'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-0">
        <!-- Monthly Sales Summary -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar text-primary me-2"></i>Monthly Sales Summary (<?= date('Y') ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthlySales)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No data yet</td></tr>
                                <?php else: ?>
                                <?php foreach ($monthlySales as $ms): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $monthNames[$ms['month']] ?? $ms['month'] ?></td>
                                    <td><span class="badge bg-primary"><?= $ms['orders'] ?></span></td>
                                    <td class="fw-bold text-success">₹<?= number_format($ms['revenue'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Outstanding Amounts -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Top Outstanding Amounts</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Party</th>
                                    <th>District</th>
                                    <th>Outstanding</th>
                                    <th>Credit Limit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topOutstanding)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No outstanding amounts</td></tr>
                                <?php else: ?>
                                <?php foreach ($topOutstanding as $to): 
                                    $pct = $to['credit_limit'] > 0 ? ($to['outstanding_amount'] / $to['credit_limit']) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($to['shop_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($to['owner_name']) ?></small>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($to['district']) ?></td>
                                    <td class="fw-bold text-danger">
                                        ₹<?= number_format($to['outstanding_amount'], 0) ?>
                                        <?php if ($pct >= 100): ?>
                                            <span class="badge bg-danger ms-1" style="font-size:9px;">EXCEEDED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>₹<?= number_format($to['credit_limit'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
