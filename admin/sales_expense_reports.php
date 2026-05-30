<?php
/**
 * Admin Panel - Expense Reports & Analytics
 * Category-wise, executive-wise, district-wise, monthly trend reports
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
require_once __DIR__ . '/../sales-portal/includes/expense_setup.php';

$pageTitle = 'Expense Reports';
$adminPage = 'sales_expense_reports';

// Date range filter
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterYear = substr($filterMonth, 0, 4);
$filterMo = substr($filterMonth, 5, 2);

// Overall stats for selected month
$overview = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'count' => 0];
try {
    $ov = db_fetch('SELECT 
        COUNT(*) as count,
        COALESCE(SUM(amount),0) as total,
        COALESCE(SUM(CASE WHEN status="approved" THEN amount ELSE 0 END),0) as approved,
        COALESCE(SUM(CASE WHEN status="pending" THEN amount ELSE 0 END),0) as pending,
        COALESCE(SUM(CASE WHEN status="rejected" THEN amount ELSE 0 END),0) as rejected
        FROM sales_expenses WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?', [$filterYear, $filterMo]);
    if ($ov) $overview = $ov;
} catch (PDOException $e) {}

// Category-wise breakdown
$catReport = [];
try {
    $catReport = db_fetch_all(
        'SELECT c.name, c.icon, COUNT(*) as entries, 
         COALESCE(SUM(e.amount),0) as total,
         COALESCE(SUM(CASE WHEN e.status="approved" THEN e.amount ELSE 0 END),0) as approved,
         COALESCE(SUM(CASE WHEN e.status="pending" THEN e.amount ELSE 0 END),0) as pending
         FROM sales_expenses e 
         JOIN sales_expense_categories c ON e.category_id = c.id 
         WHERE YEAR(e.expense_date) = ? AND MONTH(e.expense_date) = ?
         GROUP BY c.id ORDER BY total DESC', [$filterYear, $filterMo]
    );
} catch (PDOException $e) {}

// Executive-wise breakdown
$execReport = [];
try {
    $execReport = db_fetch_all(
        'SELECT se.name, se.district, COUNT(*) as entries,
         COALESCE(SUM(e.amount),0) as total,
         COALESCE(SUM(CASE WHEN e.status="approved" THEN e.amount ELSE 0 END),0) as approved,
         COALESCE(SUM(CASE WHEN e.status="pending" THEN e.amount ELSE 0 END),0) as pending
         FROM sales_expenses e 
         JOIN sales_executives se ON e.executive_id = se.id 
         WHERE YEAR(e.expense_date) = ? AND MONTH(e.expense_date) = ?
         GROUP BY se.id ORDER BY total DESC', [$filterYear, $filterMo]
    );
} catch (PDOException $e) {}

// District-wise breakdown
$distReport = [];
try {
    $distReport = db_fetch_all(
        'SELECT COALESCE(e.district, se.district) as district_name, COUNT(*) as entries,
         COALESCE(SUM(e.amount),0) as total
         FROM sales_expenses e 
         JOIN sales_executives se ON e.executive_id = se.id 
         WHERE YEAR(e.expense_date) = ? AND MONTH(e.expense_date) = ?
         GROUP BY district_name ORDER BY total DESC', [$filterYear, $filterMo]
    );
} catch (PDOException $e) {}

// Monthly trend (last 6 months)
$trendData = [];
try {
    $trendData = db_fetch_all(
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') as month_key,
         DATE_FORMAT(expense_date, '%b %Y') as month_label,
         COUNT(*) as entries,
         COALESCE(SUM(amount),0) as total,
         COALESCE(SUM(CASE WHEN status='approved' THEN amount ELSE 0 END),0) as approved
         FROM sales_expenses 
         WHERE expense_date >= DATE_SUB(LAST_DAY(CONCAT(?, '-01')), INTERVAL 5 MONTH)
         GROUP BY month_key ORDER BY month_key ASC", [$filterMonth]
    );
} catch (PDOException $e) {}

// Today's stats
$todayStats = ['count' => 0, 'total' => 0];
try {
    $ts = db_fetch('SELECT COUNT(*) as count, COALESCE(SUM(amount),0) as total FROM sales_expenses WHERE expense_date = CURDATE()');
    if ($ts) $todayStats = $ts;
} catch (PDOException $e) {}

// Pending approvals
$pendingApprovals = 0;
try {
    $pa = db_fetch('SELECT COUNT(*) as cnt FROM sales_expenses WHERE status = "pending"');
    $pendingApprovals = (int)($pa['cnt'] ?? 0);
} catch (PDOException $e) {}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2"></i>Expense Reports & Analytics</h2>
            <p class="text-muted mb-0">Detailed expense analysis for <?= date('F Y', strtotime($filterMonth . '-01')) ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($filterMonth) ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
            </form>
            <a href="<?= base_url('admin/sales_expenses.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Expenses</a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-5 fw-bold"><?= (int)$todayStats['count'] ?></div>
                    <div class="small text-muted">Today's Entries</div>
                    <div class="small fw-semibold text-primary">₹<?= number_format((float)$todayStats['total'], 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-5 fw-bold text-warning"><?= $pendingApprovals ?></div>
                    <div class="small text-muted">Pending Approvals</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-5 fw-bold"><?= (int)$overview['count'] ?></div>
                    <div class="small text-muted">Monthly Entries</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-5 fw-bold text-primary">₹<?= number_format((float)$overview['total'], 0) ?></div>
                    <div class="small text-muted">Total Claimed</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-5 fw-bold text-success">₹<?= number_format((float)$overview['approved'], 0) ?></div>
                    <div class="small text-muted">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-5 fw-bold text-danger">₹<?= number_format((float)$overview['rejected'], 0) ?></div>
                    <div class="small text-muted">Rejected</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Category-wise Report -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i>Category-wise Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Category</th><th class="text-end">Entries</th><th class="text-end">Total</th><th class="text-end">Approved</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($catReport)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No data for this month</td></tr>
                                <?php else: ?>
                                <?php foreach ($catReport as $cr): ?>
                                <tr>
                                    <td><i class="<?= htmlspecialchars($cr['icon']) ?> me-1 text-muted"></i> <?= htmlspecialchars($cr['name']) ?></td>
                                    <td class="text-end"><?= (int)$cr['entries'] ?></td>
                                    <td class="text-end fw-semibold">₹<?= number_format((float)$cr['total'], 0) ?></td>
                                    <td class="text-end text-success">₹<?= number_format((float)$cr['approved'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?= array_sum(array_column($catReport, 'entries')) ?></td>
                                    <td class="text-end">₹<?= number_format(array_sum(array_column($catReport, 'total')), 0) ?></td>
                                    <td class="text-end text-success">₹<?= number_format(array_sum(array_column($catReport, 'approved')), 0) ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Executive-wise Report -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>Salesperson-wise Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Salesperson</th><th>District</th><th class="text-end">Entries</th><th class="text-end">Total</th><th class="text-end">Approved</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($execReport)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No data for this month</td></tr>
                                <?php else: ?>
                                <?php foreach ($execReport as $er): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($er['name']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($er['district']) ?></td>
                                    <td class="text-end"><?= (int)$er['entries'] ?></td>
                                    <td class="text-end fw-semibold">₹<?= number_format((float)$er['total'], 0) ?></td>
                                    <td class="text-end text-success">₹<?= number_format((float)$er['approved'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- District-wise Report -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-primary"></i>District-wise Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>District</th><th class="text-end">Entries</th><th class="text-end">Total</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($distReport)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No data</td></tr>
                                <?php else: ?>
                                <?php foreach ($distReport as $dr): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($dr['district_name'] ?? 'Unknown') ?></td>
                                    <td class="text-end"><?= (int)$dr['entries'] ?></td>
                                    <td class="text-end fw-semibold">₹<?= number_format((float)$dr['total'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trend -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Monthly Trend (Last 6 Months)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Month</th><th class="text-end">Entries</th><th class="text-end">Total Claimed</th><th class="text-end">Approved</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($trendData)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No data yet</td></tr>
                                <?php else: ?>
                                <?php foreach ($trendData as $td): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($td['month_label']) ?></td>
                                    <td class="text-end"><?= (int)$td['entries'] ?></td>
                                    <td class="text-end">₹<?= number_format((float)$td['total'], 0) ?></td>
                                    <td class="text-end text-success">₹<?= number_format((float)$td['approved'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($trendData)): ?>
                    <!-- Visual bar chart using CSS -->
                    <div style="padding:16px;">
                        <?php
                        $maxTotal = max(array_column($trendData, 'total')) ?: 1;
                        foreach ($trendData as $td):
                            $pct = ((float)$td['total'] / $maxTotal) * 100;
                            $approvedPct = ((float)$td['approved'] / $maxTotal) * 100;
                        ?>
                        <div style="margin-bottom:8px;">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= htmlspecialchars($td['month_label']) ?></span>
                                <span class="fw-bold">₹<?= number_format((float)$td['total'], 0) ?></span>
                            </div>
                            <div style="background:#f3f4f6;border-radius:4px;height:20px;position:relative;overflow:hidden;">
                                <div style="background:#10b981;height:100%;width:<?= $approvedPct ?>%;border-radius:4px;position:absolute;"></div>
                                <div style="background:#fbbf24;height:100%;width:<?= $pct ?>%;border-radius:4px;opacity:0.4;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="d-flex gap-3 mt-2 small text-muted">
                            <span><span style="display:inline-block;width:12px;height:12px;background:#10b981;border-radius:2px;"></span> Approved</span>
                            <span><span style="display:inline-block;width:12px;height:12px;background:#fbbf24;border-radius:2px;opacity:0.4;"></span> Total Claimed</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
