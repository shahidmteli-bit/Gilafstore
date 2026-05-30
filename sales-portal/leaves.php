<?php
/**
 * Sales Portal - Leave Management
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Leave Management';
$currentPage = 'leaves';

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Please fill all required fields.'];
    } else {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        if ($end < $start) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'End date cannot be before start date.'];
        } else {
            $totalDays = $start->diff($end)->days + 1;
            try {
                db_query('INSERT INTO sales_leaves (executive_id, leave_type, start_date, end_date, total_days, reason) VALUES (?,?,?,?,?,?)', [
                    $execId, $leaveType, $startDate, $endDate, $totalDays, $reason
                ]);
                $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Leave application submitted for ' . $totalDays . ' day(s).'];
            } catch (PDOException $e) {
                $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
            }
        }
    }
    header('Location: ' . sales_base_url('leaves.php'));
    exit;
}

// Fetch leave history
$leaves = [];
try {
    $leaves = db_fetch_all('SELECT * FROM sales_leaves WHERE executive_id = ? ORDER BY created_at DESC', [$execId]);
} catch (PDOException $e) { /* table may not exist */ }

// Leave balance summary (this year)
$yearLeaves = ['annual_leave' => 0, 'sick_leave' => 0, 'casual_leave' => 0, 'emergency' => 0];
try {
    $yearData = db_fetch_all('SELECT leave_type, SUM(total_days) as days FROM sales_leaves WHERE executive_id = ? AND status = "approved" AND YEAR(start_date) = YEAR(NOW()) GROUP BY leave_type', [$execId]);
    foreach ($yearData as $yl) {
        $yearLeaves[$yl['leave_type']] = (int)$yl['days'];
    }
} catch (PDOException $e) { /* table may not exist */ }

include __DIR__ . '/includes/header.php';
?>

<!-- Leave Balance -->
<div class="sp-leave-balance">
    <div class="sp-leave-bal-item">
        <div class="sp-leave-bal-val sp-color-green"><?= $yearLeaves['annual_leave'] ?></div>
        <div class="sp-leave-bal-lbl">Annual</div>
    </div>
    <div class="sp-leave-bal-item">
        <div class="sp-leave-bal-val sp-color-red"><?= $yearLeaves['sick_leave'] ?></div>
        <div class="sp-leave-bal-lbl">Sick</div>
    </div>
    <div class="sp-leave-bal-item">
        <div class="sp-leave-bal-val" style="color:#0284c7;"><?= $yearLeaves['casual_leave'] ?></div>
        <div class="sp-leave-bal-lbl">Casual</div>
    </div>
    <div class="sp-leave-bal-item">
        <div class="sp-leave-bal-val" style="color:#d97706;"><?= $yearLeaves['emergency'] ?></div>
        <div class="sp-leave-bal-lbl">Emergency</div>
    </div>
</div>

<!-- Apply Leave Form -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-calendar-plus"></i> Apply for Leave</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="apply_leave" value="1">
        <div class="sp-form-group">
            <label>Leave Type *</label>
            <select name="leave_type" class="sp-select" required>
                <option value="">Select type</option>
                <option value="annual_leave">Annual Leave (AL)</option>
                <option value="sick_leave">Sick Leave</option>
                <option value="casual_leave">Casual Leave</option>
                <option value="emergency">Emergency Leave</option>
            </select>
        </div>
        <div class="sp-leave-dates">
            <div class="sp-form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" class="sp-input" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="sp-form-group">
                <label>End Date *</label>
                <input type="date" name="end_date" class="sp-input" required min="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="sp-form-group">
            <label>Reason *</label>
            <textarea name="reason" class="sp-textarea" required placeholder="Explain the reason for leave..."></textarea>
        </div>
        <button type="submit" class="sp-btn sp-btn-primary sp-btn-block sp-btn-lg">
            <i class="fas fa-paper-plane"></i> Submit Leave Application
        </button>
    </form>
</div>

<!-- Leave History -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> Leave History</h3>
    </div>
    <?php if (empty($leaves)): ?>
        <div class="sp-empty">
            <i class="fas fa-calendar"></i>
            <h3>No leave records</h3>
            <p>You haven't applied for any leave yet.</p>
        </div>
    <?php else: ?>
        <div class="sp-leave-history">
            <?php
            $typeLabels = ['annual_leave' => 'Annual', 'sick_leave' => 'Sick', 'casual_leave' => 'Casual', 'emergency' => 'Emergency'];
            $typeColors = ['annual_leave' => '#059669', 'sick_leave' => '#dc2626', 'casual_leave' => '#0284c7', 'emergency' => '#d97706'];
            $statusBadgeColors = ['pending' => ['bg' => '#fef3c7', 'color' => '#92400e'], 'approved' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b']];
            foreach ($leaves as $leave):
                $tc = $typeColors[$leave['leave_type']] ?? '#6b7280';
                $sb = $statusBadgeColors[$leave['status']] ?? ['bg' => '#f3f4f6', 'color' => '#6b7280'];
            ?>
            <div class="sp-leave-item">
                <div class="sp-leave-item-left">
                    <div class="sp-leave-type-dot" style="background:<?= $tc ?>;"></div>
                    <div class="sp-leave-item-info">
                        <div class="sp-leave-item-type"><?= $typeLabels[$leave['leave_type']] ?? $leave['leave_type'] ?> Leave</div>
                        <div class="sp-leave-item-dates">
                            <?= date('d M', strtotime($leave['start_date'])) ?>
                            <?php if ($leave['start_date'] !== $leave['end_date']): ?>
                                – <?= date('d M', strtotime($leave['end_date'])) ?>
                            <?php endif; ?>
                            <span class="sp-leave-item-days">(<?= $leave['total_days'] ?>d)</span>
                        </div>
                        <?php if (!empty($leave['reason'])): ?>
                            <div class="sp-leave-item-reason"><?= htmlspecialchars($leave['reason']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($leave['admin_remarks']) && $leave['admin_remarks'] !== '—'): ?>
                            <div class="sp-leave-item-remark"><i class="fas fa-comment-alt"></i> <?= htmlspecialchars($leave['admin_remarks']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sp-leave-item-badge" style="background:<?= $sb['bg'] ?>;color:<?= $sb['color'] ?>;">
                    <?= ucfirst($leave['status']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
