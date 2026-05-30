<?php
/**
 * Admin Panel - Sales Executive Leave Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Leaves';
$adminPage = 'sales_leaves';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveId = (int)($_POST['leave_id'] ?? 0);
    $action = $_POST['leave_action'] ?? '';
    $remarks = trim($_POST['admin_remarks'] ?? '');

    if ($leaveId > 0 && in_array($action, ['approved', 'rejected'])) {
        db_query('UPDATE sales_leaves SET status = ?, admin_remarks = ?, approved_by = ? WHERE id = ?', [
            $action, $remarks, $_SESSION['admin_id'] ?? 0, $leaveId
        ]);

        // If approved, mark attendance as on_leave for those dates
        if ($action === 'approved') {
            $leave = db_fetch('SELECT * FROM sales_leaves WHERE id = ?', [$leaveId]);
            if ($leave) {
                $start = new DateTime($leave['start_date']);
                $end = new DateTime($leave['end_date']);
                $end->modify('+1 day');
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end);
                foreach ($period as $date) {
                    $dateStr = $date->format('Y-m-d');
                    try {
                        $existing = db_fetch('SELECT id FROM sales_attendance WHERE executive_id = ? AND attendance_date = ?', [$leave['executive_id'], $dateStr]);
                        if ($existing) {
                            db_query('UPDATE sales_attendance SET status = "on_leave" WHERE id = ?', [$existing['id']]);
                        } else {
                            db_query('INSERT INTO sales_attendance (executive_id, attendance_date, status) VALUES (?, ?, "on_leave")', [$leave['executive_id'], $dateStr]);
                        }
                    } catch (PDOException $e) { /* ignore duplicates */ }
                }
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Leave ' . $action . ' successfully.'];
    }
    header('Location: ' . base_url('admin/sales_leaves.php'));
    exit;
}

$filterStatus = $_GET['status'] ?? '';
$filterExec = (int)($_GET['exec'] ?? 0);

$hasArea = false;
try { db_fetch('SELECT assigned_area FROM sales_executives LIMIT 1'); $hasArea = true; } catch (PDOException $e) {}
$areaCol = $hasArea ? ', se.assigned_area' : '';
$sql = 'SELECT sl.*, se.name as exec_name, se.district' . $areaCol . ' FROM sales_leaves sl JOIN sales_executives se ON sl.executive_id = se.id WHERE 1=1';
$params = [];

if ($filterStatus) {
    $sql .= ' AND sl.status = ?';
    $params[] = $filterStatus;
}
if ($filterExec > 0) {
    $sql .= ' AND sl.executive_id = ?';
    $params[] = $filterExec;
}
$sql .= ' ORDER BY sl.created_at DESC';
$leaves = db_fetch_all($sql, $params);

$executives = db_fetch_all('SELECT id, name FROM sales_executives ORDER BY name ASC');

// Pending count
$pendingCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_leaves WHERE status = "pending"')['cnt'] ?? 0;

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Leave Management</h2>
            <p class="text-muted mb-0">
                Review and manage leave requests
                <?php if ($pendingCount > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?= $pendingCount ?> pending</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">Executive</label>
                <select name="exec" class="form-select">
                    <option value="0">All</option>
                    <?php foreach ($executives as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filterExec == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= base_url('admin/sales_leaves.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <!-- Leaves Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Executive</th>
                            <th>Area</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaves)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>No leave records found.</td></tr>
                        <?php else: ?>
                        <?php
                        $typeLabels = ['annual_leave' => 'Annual Leave', 'sick_leave' => 'Sick Leave', 'casual_leave' => 'Casual Leave', 'emergency' => 'Emergency'];
                        $badgeMap = ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'];
                        foreach ($leaves as $leave):
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($leave['exec_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($leave['assigned_area'] ?? $leave['district'] ?? '') ?></td>
                            <td><?= $typeLabels[$leave['leave_type']] ?? $leave['leave_type'] ?></td>
                            <td><?= date('d M Y', strtotime($leave['start_date'])) ?></td>
                            <td><?= date('d M Y', strtotime($leave['end_date'])) ?></td>
                            <td class="fw-bold"><?= $leave['total_days'] ?></td>
                            <td style="max-width:200px;"><small><?= htmlspecialchars($leave['reason']) ?></small></td>
                            <td><span class="badge <?= $badgeMap[$leave['status']] ?? 'bg-secondary' ?>"><?= ucfirst($leave['status']) ?></span></td>
                            <td>
                                <?php if ($leave['status'] === 'pending'): ?>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu">
                                        <li><button class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $leave['id'] ?>"><i class="fas fa-check me-2"></i>Approve</button></li>
                                        <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $leave['id'] ?>"><i class="fas fa-times me-2"></i>Reject</button></li>
                                    </ul>
                                </div>

                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?= $leave['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                <input type="hidden" name="leave_action" value="approved">
                                                <div class="modal-header bg-success text-white">
                                                    <h6 class="modal-title"><i class="fas fa-check me-1"></i> Approve Leave</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small"><strong><?= htmlspecialchars($leave['exec_name']) ?></strong> — <?= $typeLabels[$leave['leave_type']] ?? '' ?> (<?= $leave['total_days'] ?> days)</p>
                                                    <label class="form-label fw-semibold small">Remarks (optional)</label>
                                                    <textarea name="admin_remarks" class="form-control" rows="2" placeholder="Any remarks..."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?= $leave['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                <input type="hidden" name="leave_action" value="rejected">
                                                <div class="modal-header bg-danger text-white">
                                                    <h6 class="modal-title"><i class="fas fa-times me-1"></i> Reject Leave</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small"><strong><?= htmlspecialchars($leave['exec_name']) ?></strong> — <?= $typeLabels[$leave['leave_type']] ?? '' ?> (<?= $leave['total_days'] ?> days)</p>
                                                    <label class="form-label fw-semibold small">Reason for rejection *</label>
                                                    <textarea name="admin_remarks" class="form-control" rows="2" required placeholder="Reason..."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                    <small class="text-muted"><?= htmlspecialchars($leave['admin_remarks'] ?? '—') ?></small>
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
