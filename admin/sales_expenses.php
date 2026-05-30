<?php
/**
 * Admin Panel - Travel & Field Expense Management
 * View, filter, approve/reject expenses submitted by salespersons
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
require_once __DIR__ . '/../sales-portal/includes/expense_setup.php';

$pageTitle = 'Travel & Field Expenses';
$adminPage = 'sales_expenses';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expenseId = (int)($_POST['expense_id'] ?? 0);
    $action = $_POST['expense_action'] ?? '';
    $remarks = trim($_POST['admin_remarks'] ?? '');

    if ($expenseId > 0 && in_array($action, ['approved', 'rejected'])) {
        db_query('UPDATE sales_expenses SET status = ?, admin_remarks = ?, approved_by = ?, approved_at = NOW() WHERE id = ?', [
            $action, $remarks, $_SESSION['admin_id'] ?? 0, $expenseId
        ]);

        // Send notification to executive
        try {
            $exp = db_fetch('SELECT executive_id, amount, category_id FROM sales_expenses WHERE id = ?', [$expenseId]);
            if ($exp) {
                $cat = db_fetch('SELECT name FROM sales_expense_categories WHERE id = ?', [$exp['category_id']]);
                $catName = $cat['name'] ?? 'Expense';
                $msg = $action === 'approved'
                    ? 'Your ' . $catName . ' expense of ₹' . number_format($exp['amount'], 0) . ' has been approved.'
                    : 'Your ' . $catName . ' expense of ₹' . number_format($exp['amount'], 0) . ' was rejected.' . ($remarks ? ' Reason: ' . $remarks : '');
                db_query('INSERT INTO sales_notifications (executive_id, type, title, message) VALUES (?, ?, ?, ?)', [
                    $exp['executive_id'],
                    $action === 'approved' ? 'success' : 'warning',
                    'Expense ' . ucfirst($action),
                    $msg
                ]);
            }
        } catch (PDOException $e) { /* notification table may not exist */ }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Expense ' . $action . ' successfully.'];
    }
    header('Location: ' . base_url('admin/sales_expenses.php') . '?' . http_build_query(array_filter($_GET)));
    exit;
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterExec = (int)($_GET['exec'] ?? 0);
$filterCategory = (int)($_GET['category'] ?? 0);
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterDistrict = trim($_GET['district'] ?? '');

// Build query
$sql = 'SELECT e.*, c.name as category_name, c.icon as category_icon, 
        se.name as exec_name, se.district as exec_district,
        p.shop_name
        FROM sales_expenses e 
        JOIN sales_expense_categories c ON e.category_id = c.id 
        JOIN sales_executives se ON e.executive_id = se.id 
        LEFT JOIN sales_parties p ON e.party_id = p.id 
        WHERE 1=1';
$params = [];

if ($filterStatus) { $sql .= ' AND e.status = ?'; $params[] = $filterStatus; }
if ($filterExec > 0) { $sql .= ' AND e.executive_id = ?'; $params[] = $filterExec; }
if ($filterCategory > 0) { $sql .= ' AND e.category_id = ?'; $params[] = $filterCategory; }
if ($filterDateFrom) { $sql .= ' AND e.expense_date >= ?'; $params[] = $filterDateFrom; }
if ($filterDateTo) { $sql .= ' AND e.expense_date <= ?'; $params[] = $filterDateTo; }
if ($filterDistrict) { $sql .= ' AND (e.district LIKE ? OR se.district LIKE ?)'; $params[] = "%{$filterDistrict}%"; $params[] = "%{$filterDistrict}%"; }

$sql .= ' ORDER BY e.created_at DESC LIMIT 200';

$expenses = [];
try { $expenses = db_fetch_all($sql, $params); } catch (PDOException $e) { $expenses = []; }

// Dropdowns
$executives = [];
try { $executives = db_fetch_all('SELECT id, name FROM sales_executives ORDER BY name ASC'); } catch (PDOException $e) {}
$expCategories = [];
try { $expCategories = db_fetch_all('SELECT id, name FROM sales_expense_categories ORDER BY sort_order ASC'); } catch (PDOException $e) {}

// Summary cards
$pendingCount = 0; $pendingAmount = 0;
$approvedMonth = 0; $rejectedMonth = 0; $totalMonth = 0;
try {
    $pc = db_fetch('SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM sales_expenses WHERE status = "pending"');
    $pendingCount = (int)($pc['cnt'] ?? 0);
    $pendingAmount = (float)($pc['total'] ?? 0);

    $mc = db_fetch('SELECT 
        COALESCE(SUM(amount),0) as total,
        COALESCE(SUM(CASE WHEN status="approved" THEN amount ELSE 0 END),0) as approved,
        COALESCE(SUM(CASE WHEN status="rejected" THEN amount ELSE 0 END),0) as rejected
        FROM sales_expenses WHERE YEAR(expense_date) = YEAR(NOW()) AND MONTH(expense_date) = MONTH(NOW())');
    $totalMonth = (float)($mc['total'] ?? 0);
    $approvedMonth = (float)($mc['approved'] ?? 0);
    $rejectedMonth = (float)($mc['rejected'] ?? 0);
} catch (PDOException $e) {}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-receipt me-2"></i>Travel & Field Expenses</h2>
            <p class="text-muted mb-0">
                Review and manage field expense claims
                <?php if ($pendingCount > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?= $pendingCount ?> pending (₹<?= number_format($pendingAmount, 0) ?>)</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('admin/sales_expense_settings.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog me-1"></i>Settings</a>
            <a href="<?= base_url('admin/sales_expense_reports.php') ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-primary">₹<?= number_format($totalMonth, 0) ?></div>
                    <div class="small text-muted">This Month Total</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-success">₹<?= number_format($approvedMonth, 0) ?></div>
                    <div class="small text-muted">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-warning">₹<?= number_format($pendingAmount, 0) ?></div>
                    <div class="small text-muted"><?= $pendingCount ?> Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-danger">₹<?= number_format($rejectedMonth, 0) ?></div>
                    <div class="small text-muted">Rejected</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">Executive</label>
                <select name="exec" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($executives as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filterExec == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($expCategories as $ec): ?>
                        <option value="<?= $ec['id'] ?>" <?= $filterCategory == $ec['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ec['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">District</label>
                <input type="text" name="district" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDistrict) ?>" placeholder="e.g. Sopore">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= base_url('admin/sales_expenses.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <!-- Expenses Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Executive</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>District</th>
                            <th>Notes</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">
                            <i class="fas fa-receipt fa-3x mb-3 d-block" style="opacity:0.3;"></i>No expense records found.
                        </td></tr>
                        <?php else: ?>
                        <?php
                        $badgeMap = ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'];
                        foreach ($expenses as $exp):
                        ?>
                        <tr>
                            <td class="small"><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($exp['exec_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($exp['exec_district']) ?></div>
                            </td>
                            <td>
                                <i class="<?= htmlspecialchars($exp['category_icon']) ?> me-1 text-muted"></i>
                                <?= htmlspecialchars($exp['category_name']) ?>
                            </td>
                            <td class="fw-bold">₹<?= number_format((float)$exp['amount'], 0) ?></td>
                            <td class="small"><?= htmlspecialchars($exp['district'] ?? '') ?></td>
                            <td style="max-width:200px;">
                                <small><?= htmlspecialchars($exp['notes'] ?? '—') ?></small>
                                <?php if ($exp['shop_name']): ?>
                                    <br><span class="badge bg-light text-dark border"><?= htmlspecialchars($exp['shop_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($exp['attachment']): ?>
                                    <a href="<?= base_url('sales-portal/' . $exp['attachment']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-1" title="View Receipt">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badgeMap[$exp['status']] ?? 'bg-secondary' ?>"><?= ucfirst($exp['status']) ?></span></td>
                            <td>
                                <?php if ($exp['status'] === 'pending'): ?>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu">
                                        <li><button class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#approveExp<?= $exp['id'] ?>"><i class="fas fa-check me-2"></i>Approve</button></li>
                                        <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectExp<?= $exp['id'] ?>"><i class="fas fa-times me-2"></i>Reject</button></li>
                                    </ul>
                                </div>

                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveExp<?= $exp['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                                <input type="hidden" name="expense_action" value="approved">
                                                <div class="modal-header bg-success text-white py-2">
                                                    <h6 class="modal-title mb-0"><i class="fas fa-check me-1"></i> Approve Expense</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small mb-2">
                                                        <strong><?= htmlspecialchars($exp['exec_name']) ?></strong><br>
                                                        <i class="<?= htmlspecialchars($exp['category_icon']) ?>"></i> <?= htmlspecialchars($exp['category_name']) ?> — 
                                                        <strong>₹<?= number_format((float)$exp['amount'], 0) ?></strong><br>
                                                        <?= date('d M Y', strtotime($exp['expense_date'])) ?>
                                                    </p>
                                                    <?php if ($exp['notes']): ?>
                                                        <p class="small text-muted">"<?= htmlspecialchars($exp['notes']) ?>"</p>
                                                    <?php endif; ?>
                                                    <label class="form-label fw-semibold small">Remarks (optional)</label>
                                                    <textarea name="admin_remarks" class="form-control form-control-sm" rows="2" placeholder="Any remarks..."></textarea>
                                                </div>
                                                <div class="modal-footer py-2">
                                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectExp<?= $exp['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                                <input type="hidden" name="expense_action" value="rejected">
                                                <div class="modal-header bg-danger text-white py-2">
                                                    <h6 class="modal-title mb-0"><i class="fas fa-times me-1"></i> Reject Expense</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small mb-2">
                                                        <strong><?= htmlspecialchars($exp['exec_name']) ?></strong><br>
                                                        <i class="<?= htmlspecialchars($exp['category_icon']) ?>"></i> <?= htmlspecialchars($exp['category_name']) ?> — 
                                                        <strong>₹<?= number_format((float)$exp['amount'], 0) ?></strong>
                                                    </p>
                                                    <label class="form-label fw-semibold small">Reason for rejection *</label>
                                                    <textarea name="admin_remarks" class="form-control form-control-sm" rows="2" required placeholder="Reason..."></textarea>
                                                </div>
                                                <div class="modal-footer py-2">
                                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                    <small class="text-muted"><?= htmlspecialchars($exp['admin_remarks'] ?? '—') ?></small>
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

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
