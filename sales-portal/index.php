<?php
/**
 * Sales Executive Portal - Dashboard
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Fetch stats - current year
$currentYear = date('Y');
$totalOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND YEAR(created_at) = ?', [$execId, $currentYear])['cnt'] ?? 0;
$pendingOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status = "pending" AND YEAR(created_at) = ?', [$execId, $currentYear])['cnt'] ?? 0;
$approvedOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status = "approved" AND YEAR(created_at) = ?', [$execId, $currentYear])['cnt'] ?? 0;
$dispatchedOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status = "dispatched" AND YEAR(created_at) = ?', [$execId, $currentYear])['cnt'] ?? 0;
$deliveredOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status = "delivered" AND YEAR(created_at) = ?', [$execId, $currentYear])['cnt'] ?? 0;
$cancelledOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status = "cancelled" AND YEAR(created_at) = ?', [$execId, $currentYear])['cnt'] ?? 0;
$totalParties = db_fetch('SELECT COUNT(*) as cnt FROM sales_parties WHERE created_by = ?', [$execId])['cnt'] ?? 0;

// Party status counts
$activeParties = 0; $inactiveParties = 0; $atRiskParties = 0; $blockedParties = 0;
try {
    // Auto-block: parties with outstanding > 0 and oldest_due_date > 60 days ago
    try {
        db_query("UPDATE sales_parties SET is_blocked = 1, blocked_reason = 'Auto-blocked: dues unpaid for 60+ days', blocked_at = NOW() WHERE created_by = ? AND is_blocked = 0 AND outstanding_amount > 0 AND oldest_due_date IS NOT NULL AND oldest_due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY)", [$execId]);
    } catch (PDOException $eab) { /* columns may not exist */ }

    // Auto-unblock: blocked parties whose dues are cleared
    try {
        db_query("UPDATE sales_parties SET is_blocked = 0, blocked_reason = NULL, blocked_at = NULL WHERE created_by = ? AND is_blocked = 1 AND blocked_reason LIKE 'Auto-blocked%' AND outstanding_amount = 0", [$execId]);
    } catch (PDOException $eub) { /* safe */ }

    // Active: parties that have at least 1 order and are not blocked
    $activeParties = (int)(db_fetch("SELECT COUNT(DISTINCT sp.id) as cnt FROM sales_parties sp INNER JOIN sales_orders so ON so.party_id = sp.id WHERE sp.created_by = ? AND sp.is_active = 1 AND sp.is_blocked = 0", [$execId])['cnt'] ?? 0);

    // Blocked
    $blockedParties = (int)(db_fetch("SELECT COUNT(*) as cnt FROM sales_parties WHERE created_by = ? AND (is_blocked = 1 OR rating_label = 'blocked')", [$execId])['cnt'] ?? 0);

    // At-Risk: low rating, high consecutive_low_recovery, or overdue 30-60 days (not blocked)
    $atRiskParties = (int)(db_fetch("SELECT COUNT(*) as cnt FROM sales_parties WHERE created_by = ? AND is_active = 1 AND is_blocked = 0 AND (rating_label IN ('low','average') OR consecutive_low_recovery >= 2 OR (outstanding_amount > 0 AND oldest_due_date IS NOT NULL AND oldest_due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND oldest_due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)))", [$execId])['cnt'] ?? 0);

    // Inactive: active parties with zero orders and not blocked
    $inactiveParties = (int)(db_fetch("SELECT COUNT(*) as cnt FROM sales_parties sp WHERE sp.created_by = ? AND sp.is_active = 1 AND sp.is_blocked = 0 AND NOT EXISTS (SELECT 1 FROM sales_orders so WHERE so.party_id = sp.id)", [$execId])['cnt'] ?? 0);
} catch (PDOException $eps) {
    // Fallback if new columns don't exist yet
    try {
        $activeParties = (int)(db_fetch("SELECT COUNT(DISTINCT sp.id) as cnt FROM sales_parties sp INNER JOIN sales_orders so ON so.party_id = sp.id WHERE sp.created_by = ? AND sp.is_active = 1", [$execId])['cnt'] ?? 0);
        $inactiveParties = $totalParties - $activeParties;
    } catch (PDOException $ef) { /* safe */ }
}

// Monthly sales
$monthlySales = db_fetch('SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_orders WHERE executive_id = ? AND status IN ("approved","dispatched","delivered") AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())', [$execId])['total'] ?? 0;

// Today's sales
$todaySales = db_fetch('SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status IN ("approved","dispatched","delivered") AND DATE(created_at) = CURDATE()', [$execId]);
$todaySalesAmount = $todaySales['total'] ?? 0;
$todayOrderCount = $todaySales['cnt'] ?? 0;

// Monthly order count
$monthlyOrderCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status IN ("approved","dispatched","delivered") AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())', [$execId])['cnt'] ?? 0;

// Credit Recovery - Today (payments received today)
$creditRecoveryToday = db_fetch('SELECT COALESCE(SUM(amount), 0) as total FROM sales_payment_history WHERE recorded_by = ? AND DATE(created_at) = CURDATE() AND payment_type IN ("payment","adjustment")', [$execId])['total'] ?? 0;

// Credit Recovery - This Month
$creditRecoveryMonth = db_fetch('SELECT COALESCE(SUM(amount), 0) as total FROM sales_payment_history WHERE recorded_by = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND payment_type IN ("payment","adjustment")', [$execId])['total'] ?? 0;

// Credit Recovery - Last Month
$creditRecoveryLastMonth = db_fetch('SELECT COALESCE(SUM(amount), 0) as total FROM sales_payment_history WHERE recorded_by = ? AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND payment_type IN ("payment","adjustment")', [$execId])['total'] ?? 0;

// Total outstanding across all parties
$totalOutstanding = db_fetch('SELECT COALESCE(SUM(outstanding_amount), 0) as total FROM sales_parties WHERE created_by = ?', [$execId])['total'] ?? 0;

// Overdue parties (outstanding > 0 and oldest due > 60 days)
$overdueParties = [];
try {
    $overdueParties = db_fetch_all('SELECT sp.id, sp.shop_name, sp.owner_name, sp.outstanding_amount, sp.district, sp.oldest_due_date, sp.rating_label FROM sales_parties sp WHERE sp.created_by = ? AND sp.outstanding_amount > 0 AND sp.oldest_due_date IS NOT NULL AND sp.oldest_due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY sp.outstanding_amount DESC LIMIT 5', [$execId]);
} catch (PDOException $e) { /* columns may not exist yet */ }

// High-turnover parties (top 5)
$highTurnoverParties = [];
try {
    $highTurnoverParties = db_fetch_all('SELECT id, shop_name, owner_name, turnover_amount, district FROM sales_parties WHERE created_by = ? AND turnover_amount > 0 ORDER BY turnover_amount DESC LIMIT 5', [$execId]);
} catch (PDOException $e) { /* column may not exist */ }

// Increasing turnover parties (this month vs last month)
$increasingParties = [];
try {
    $increasingParties = db_fetch_all('
        SELECT sp.id, sp.shop_name, sp.district,
            COALESCE(this_m.total, 0) as this_month,
            COALESCE(last_m.total, 0) as last_month,
            COALESCE(this_m.total, 0) - COALESCE(last_m.total, 0) as growth
        FROM sales_parties sp
        LEFT JOIN (
            SELECT party_id, SUM(total_amount) as total FROM sales_orders
            WHERE status IN ("approved","dispatched","delivered") AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
            GROUP BY party_id
        ) this_m ON this_m.party_id = sp.id
        LEFT JOIN (
            SELECT party_id, SUM(total_amount) as total FROM sales_orders
            WHERE status IN ("approved","dispatched","delivered") AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
            GROUP BY party_id
        ) last_m ON last_m.party_id = sp.id
        WHERE sp.created_by = ? AND COALESCE(this_m.total, 0) > COALESCE(last_m.total, 0) AND COALESCE(last_m.total, 0) > 0
        ORDER BY growth DESC LIMIT 5
    ', [$execId]);
} catch (PDOException $e) { /* safe */ }

// Today's visit schedule
$todaySchedule = null;
$todayDow = (int)date('w'); // 0=Sun, 6=Sat
try {
    $todaySchedule = db_fetch('SELECT * FROM sales_visit_schedules WHERE executive_id = ? AND day_of_week = ?', [$execId, $todayDow]);
} catch (PDOException $e) { /* table may not exist */ }

// Visit stats for today
$visitStats = ['total' => 0, 'visited' => 0];
if ($todaySchedule && !$todaySchedule['is_off']) {
    try {
        $visitStats['total'] = db_fetch('SELECT COUNT(*) as cnt FROM sales_parties WHERE created_by = ? AND district = ? AND is_active = 1', [$execId, $todaySchedule['district']])['cnt'] ?? 0;
        $visitStats['visited'] = db_fetch('SELECT COUNT(*) as cnt FROM sales_party_visits WHERE executive_id = ? AND visit_date = CURDATE()', [$execId])['cnt'] ?? 0;
    } catch (PDOException $e) { /* safe */ }
}

// Credit alerts - parties near or over credit limit
$creditAlerts = db_fetch_all('SELECT sp.shop_name, sp.outstanding_amount, sp.credit_limit FROM sales_parties sp WHERE sp.created_by = ? AND sp.credit_limit > 0 AND sp.outstanding_amount >= (sp.credit_limit * 0.8) ORDER BY sp.outstanding_amount DESC LIMIT 5', [$execId]);

// Attendance - today's status
$todayAttendance = null;
try {
    $todayAttendance = db_fetch('SELECT * FROM sales_attendance WHERE executive_id = ? AND attendance_date = CURDATE()', [$execId]);
} catch (PDOException $e) { /* table may not exist yet */ }

// Pending leaves
$pendingLeaves = 0;
try {
    $pendingLeaves = db_fetch('SELECT COUNT(*) as cnt FROM sales_leaves WHERE executive_id = ? AND status = "pending"', [$execId])['cnt'] ?? 0;
} catch (PDOException $e) { /* table may not exist yet */ }

// Recent orders
$recentOrders = db_fetch_all('SELECT so.*, sp.shop_name FROM sales_orders so JOIN sales_parties sp ON so.party_id = sp.id WHERE so.executive_id = ? ORDER BY so.created_at DESC LIMIT 10', [$execId]);

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome & Attendance Banner -->
<div class="sp-dash-welcome">
    <div class="sp-dash-greeting">
        <h2>Hello, <?= htmlspecialchars(explode(' ', $exec['name'])[0]) ?> 👋</h2>
        <p><?= date('l, d M Y') ?> · <?= htmlspecialchars($exec['assigned_area'] ?? $exec['district'] ?? '') ?></p>
        <div class="sp-dash-clock" id="spDashClock"><?= date('h:i:s A') ?></div>
    </div>
    <?php if ($todayAttendance): ?>
        <div class="sp-dash-attendance sp-dash-att-in">
            <i class="fas fa-check-circle"></i>
            <span>In at <?= date('h:i A', strtotime($todayAttendance['check_in_time'])) ?></span>
        </div>
    <?php else: ?>
        <a href="<?= sales_base_url('attendance.php') ?>" class="sp-dash-attendance sp-dash-att-out">
            <i class="fas fa-exclamation-circle"></i>
            <span>Check In</span>
        </a>
    <?php endif; ?>
</div>

<?php if ($pendingLeaves > 0): ?>
<div class="sp-dash-alert sp-dash-alert-warn">
    <i class="fas fa-calendar-times"></i>
    <span><?= $pendingLeaves ?> leave request(s) pending approval</span>
</div>
<?php endif; ?>

<!-- Quick Actions Grid -->
<div class="sp-dash-actions">
    <a href="<?= sales_base_url('new_order.php') ?>" class="sp-dash-action-btn sp-dash-action-primary">
        <div class="sp-dash-action-icon"><i class="fas fa-cart-plus"></i></div>
        <span>New Order</span>
    </a>
    <a href="<?= sales_base_url('parties.php?action=create') ?>" class="sp-dash-action-btn sp-dash-action-gold">
        <div class="sp-dash-action-icon"><i class="fas fa-user-plus"></i></div>
        <span>Add Party</span>
    </a>
    <a href="<?= sales_base_url('collect_payment.php') ?>" class="sp-dash-action-btn sp-dash-action-green">
        <div class="sp-dash-action-icon"><i class="fas fa-hand-holding-usd"></i></div>
        <span>Collect</span>
    </a>
    <a href="<?= sales_base_url('scan_party.php') ?>" class="sp-dash-action-btn sp-dash-action-blue">
        <div class="sp-dash-action-icon"><i class="fas fa-qrcode"></i></div>
        <span>Scan QR</span>
    </a>
</div>

<!-- Stats Row -->
<div class="sp-dash-stats-row">
    <div class="sp-dash-stat">
        <div class="sp-dash-stat-num"><?= $totalOrders ?></div>
        <div class="sp-dash-stat-label">Orders (<?= $currentYear ?>)</div>
    </div>
    <div class="sp-dash-stat-divider"></div>
    <div class="sp-dash-stat">
        <div class="sp-dash-stat-num sp-color-gold"><?= $pendingOrders ?></div>
        <div class="sp-dash-stat-label">Pending</div>
    </div>
    <div class="sp-dash-stat-divider"></div>
    <div class="sp-dash-stat">
        <div class="sp-dash-stat-num sp-color-green"><?= $approvedOrders ?></div>
        <div class="sp-dash-stat-label">Approved</div>
    </div>
    <div class="sp-dash-stat-divider"></div>
    <div class="sp-dash-stat">
        <div class="sp-dash-stat-num sp-color-purple"><?= $dispatchedOrders ?></div>
        <div class="sp-dash-stat-label">Dispatched</div>
    </div>
</div>

<!-- Delivered & Cancelled Cards -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
    <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:14px;padding:16px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#047857;"><?= $deliveredOrders ?></div>
        <div style="font-size:12px;color:#065f46;font-weight:600;"><i class="fas fa-check-double"></i> Delivered</div>
    </div>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:16px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#dc2626;"><?= $cancelledOrders ?></div>
        <div style="font-size:12px;color:#991b1b;font-weight:600;"><i class="fas fa-ban"></i> Cancelled</div>
    </div>
</div>

<!-- Today's Visit Schedule -->
<?php if ($todaySchedule): ?>
<a href="<?= sales_base_url('visit_schedule.php') ?>" style="text-decoration:none;color:inherit;display:block;">
<div class="sp-card sp-mb-16" style="border-left:4px solid var(--sp-primary);cursor:pointer;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(26,60,52,0.15)'" onmouseout="this.style.boxShadow='none'">
    <div class="sp-card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3><i class="fas fa-route"></i> Today's Schedule</h3>
        <i class="fas fa-chevron-right" style="color:#9ca3af;font-size:14px;"></i>
    </div>
    <?php if ($todaySchedule['is_off']): ?>
        <div style="text-align:center;padding:12px 0;">
            <i class="fas fa-coffee" style="font-size:24px;color:#6b7280;"></i>
            <div style="font-weight:700;margin-top:6px;">Week Off</div>
            <div style="font-size:12px;color:#6b7280;">Enjoy your day!</div>
        </div>
    <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;">
            <div>
                <div style="font-size:18px;font-weight:700;"><i class="fas fa-map-marker-alt" style="color:var(--sp-primary);"></i> <?= htmlspecialchars($todaySchedule['district']) ?></div>
                <?php if ($todaySchedule['area_name']): ?>
                    <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($todaySchedule['area_name']) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="font-size:13px;font-weight:600;color:#059669;"><?= $visitStats['visited'] ?> / <?= $visitStats['total'] ?></div>
                <div style="font-size:11px;color:#6b7280;">Visited</div>
            </div>
        </div>
        <?php if ($visitStats['total'] > 0): ?>
        <div style="height:6px;background:#f3f4f6;border-radius:3px;margin-top:6px;">
            <div style="height:100%;background:#059669;border-radius:3px;width:<?= $visitStats['total'] > 0 ? round(($visitStats['visited']/$visitStats['total'])*100) : 0 ?>%;transition:width 0.5s;"></div>
        </div>
        <?php endif; ?>
        <div style="text-align:center;margin-top:10px;font-size:12px;color:var(--sp-primary);font-weight:600;">
            <i class="fas fa-list"></i> Tap to view all parties & route
        </div>
    <?php endif; ?>
</div>
</a>
<?php endif; ?>

<!-- Today & This Month -->
<div class="sp-dash-highlights">
    <div class="sp-dash-highlight-card sp-dash-hl-today">
        <div class="sp-dash-hl-header">
            <i class="fas fa-sun"></i> Today
        </div>
        <div class="sp-dash-hl-grid">
            <div class="sp-dash-hl-item">
                <div class="sp-dash-hl-value">₹<?= number_format($todaySalesAmount, 0) ?></div>
                <div class="sp-dash-hl-label">Sales</div>
            </div>
            <div class="sp-dash-hl-item">
                <div class="sp-dash-hl-value"><?= $todayOrderCount ?></div>
                <div class="sp-dash-hl-label">Orders</div>
            </div>
        </div>
    </div>
    <div class="sp-dash-highlight-card sp-dash-hl-month">
        <div class="sp-dash-hl-header">
            <i class="fas fa-calendar-alt"></i> <?= date('M Y') ?>
        </div>
        <div class="sp-dash-hl-grid">
            <div class="sp-dash-hl-item">
                <div class="sp-dash-hl-value">₹<?= number_format($monthlySales, 0) ?></div>
                <div class="sp-dash-hl-label">Sales</div>
            </div>
            <div class="sp-dash-hl-item">
                <div class="sp-dash-hl-value"><?= $monthlyOrderCount ?></div>
                <div class="sp-dash-hl-label">Orders</div>
            </div>
        </div>
    </div>
</div>

<!-- Party Status -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-users" style="color:#1A3C34;"></i> Party Status</h3>
        <a href="<?= sales_base_url('parties.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm">View All</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <a href="<?= sales_base_url('parties.php') ?>" style="text-decoration:none;text-align:center;padding:12px 4px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
            <div style="font-size:24px;font-weight:800;color:#1A3C34;"><?= $totalParties ?></div>
            <div style="font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Total</div>
        </a>
        <a href="<?= sales_base_url('parties.php?status=active') ?>" style="text-decoration:none;text-align:center;padding:12px 4px;border-radius:12px;background:#ecfdf5;border:1px solid #a7f3d0;">
            <div style="font-size:24px;font-weight:800;color:#059669;"><?= $activeParties ?></div>
            <div style="font-size:10px;font-weight:600;color:#065f46;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Active</div>
        </a>
        <a href="<?= sales_base_url('parties.php?status=inactive') ?>" style="text-decoration:none;text-align:center;padding:12px 4px;border-radius:12px;background:#f3f4f6;border:1px solid #e5e7eb;">
            <div style="font-size:24px;font-weight:800;color:#6b7280;"><?= $inactiveParties ?></div>
            <div style="font-size:10px;font-weight:600;color:#4b5563;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Inactive</div>
        </a>
        <a href="<?= sales_base_url('parties.php?status=at-risk') ?>" style="text-decoration:none;text-align:center;padding:12px 4px;border-radius:12px;background:#fef3c7;border:1px solid #fcd34d;">
            <div style="font-size:24px;font-weight:800;color:#d97706;"><?= $atRiskParties ?></div>
            <div style="font-size:10px;font-weight:600;color:#92400e;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">At-Risk</div>
        </a>
        <a href="<?= sales_base_url('parties.php?status=blocked') ?>" style="text-decoration:none;text-align:center;padding:12px 4px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;">
            <div style="font-size:24px;font-weight:800;color:#dc2626;"><?= $blockedParties ?></div>
            <div style="font-size:10px;font-weight:600;color:#991b1b;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Blocked</div>
        </a>
    </div>
</div>

<!-- Credit Recovery -->
<div class="sp-card sp-dash-recovery">
    <div class="sp-card-header">
        <h3><i class="fas fa-hand-holding-usd"></i> Credit Recovery</h3>
        <a href="<?= sales_base_url('outstanding.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm">View All</a>
    </div>
    <div class="sp-dash-recovery-grid">
        <div class="sp-dash-recovery-item sp-dash-rec-purple">
            <div class="sp-dash-rec-val">₹<?= number_format($creditRecoveryToday, 0) ?></div>
            <div class="sp-dash-rec-label">Today</div>
        </div>
        <div class="sp-dash-recovery-item sp-dash-rec-purple">
            <div class="sp-dash-rec-val">₹<?= number_format($creditRecoveryMonth, 0) ?></div>
            <div class="sp-dash-rec-label">This Month</div>
        </div>
        <div class="sp-dash-recovery-item sp-dash-rec-purple">
            <div class="sp-dash-rec-val">₹<?= number_format($creditRecoveryLastMonth, 0) ?></div>
            <div class="sp-dash-rec-label">Last Month</div>
        </div>
        <div class="sp-dash-recovery-item sp-dash-rec-red">
            <div class="sp-dash-rec-val">₹<?= number_format($totalOutstanding, 0) ?></div>
            <div class="sp-dash-rec-label">Outstanding</div>
        </div>
    </div>
</div>

<!-- Overdue Payments -->
<?php if (!empty($overdueParties)): ?>
<div class="sp-card sp-mb-16" style="border-left:4px solid #dc2626;">
    <div class="sp-card-header">
        <h3><i class="fas fa-exclamation-circle" style="color:#dc2626;"></i> Overdue Payments (60+ days)</h3>
    </div>
    <?php foreach ($overdueParties as $op):
        $daysDue = $op['oldest_due_date'] ? (int)((time() - strtotime($op['oldest_due_date'])) / 86400) : 0;
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
        <div>
            <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($op['shop_name']) ?></div>
            <div style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($op['district']) ?> · <?= $daysDue ?> days overdue</div>
        </div>
        <div style="text-align:right;">
            <div style="font-weight:700;color:#dc2626;">₹<?= number_format($op['outstanding_amount'], 0) ?></div>
            <a href="<?= sales_base_url('collect_payment.php?party_id=' . $op['id']) ?>" style="font-size:11px;color:#059669;font-weight:600;text-decoration:none;">Collect →</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- High Turnover Parties -->
<?php if (!empty($highTurnoverParties)): ?>
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-trophy" style="color:#d97706;"></i> Top Performing Parties</h3>
    </div>
    <?php foreach ($highTurnoverParties as $idx => $tp): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6;">
        <div style="width:24px;height:24px;border-radius:50%;background:<?= $idx < 3 ? '#fef3c7' : '#f3f4f6' ?>;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:<?= $idx < 3 ? '#92400e' : '#6b7280' ?>;flex-shrink:0;"><?= $idx + 1 ?></div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($tp['shop_name']) ?></div>
            <div style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($tp['district']) ?></div>
        </div>
        <div style="font-weight:700;font-size:14px;color:#059669;">₹<?= number_format($tp['turnover_amount'], 0) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Increasing Turnover Parties -->
<?php if (!empty($increasingParties)): ?>
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-chart-line" style="color:#059669;"></i> Growing Parties</h3>
    </div>
    <?php foreach ($increasingParties as $ip): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6;">
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($ip['shop_name']) ?></div>
            <div style="font-size:11px;color:#6b7280;">₹<?= number_format($ip['last_month'], 0) ?> → ₹<?= number_format($ip['this_month'], 0) ?></div>
        </div>
        <div style="font-weight:700;font-size:13px;color:#059669;"><i class="fas fa-arrow-up"></i> ₹<?= number_format($ip['growth'], 0) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Credit Alerts -->
<?php if (!empty($creditAlerts)): ?>
<div class="sp-card sp-dash-alerts-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Credit Alerts</h3>
    </div>
    <div class="sp-dash-alert-list">
        <?php foreach ($creditAlerts as $alert):
            $pct = $alert['credit_limit'] > 0 ? ($alert['outstanding_amount'] / $alert['credit_limit']) * 100 : 0;
        ?>
        <div class="sp-dash-alert-item">
            <div class="sp-dash-alert-info">
                <div class="sp-dash-alert-name"><?= htmlspecialchars($alert['shop_name']) ?></div>
                <div class="sp-dash-alert-bar">
                    <div class="sp-dash-alert-bar-fill" style="width:<?= min($pct, 100) ?>%;background:<?= $pct >= 100 ? '#dc2626' : '#f59e0b' ?>;"></div>
                </div>
            </div>
            <div class="sp-dash-alert-amt">
                <span class="sp-dash-alert-outstanding">₹<?= number_format($alert['outstanding_amount'], 0) ?></span>
                <span class="sp-dash-alert-limit">/ ₹<?= number_format($alert['credit_limit'], 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Orders -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> Recent Orders</h3>
        <a href="<?= sales_base_url('orders.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm">View All</a>
    </div>
    <?php if (empty($recentOrders)): ?>
        <div class="sp-empty">
            <i class="fas fa-clipboard"></i>
            <h3>No orders yet</h3>
            <p>Start by creating a new order from the field.</p>
            <a href="<?= sales_base_url('new_order.php') ?>" class="sp-btn sp-btn-primary">
                <i class="fas fa-cart-plus"></i> Create First Order
            </a>
        </div>
    <?php else: ?>
        <div class="sp-dash-orders-list">
            <?php foreach (array_slice($recentOrders, 0, 5) as $order):
                $statusMap = ['pending' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'clock'], 'approved' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'check'], 'dispatched' => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'icon' => 'truck'], 'delivered' => ['bg' => '#d1fae5', 'color' => '#047857', 'icon' => 'check-double'], 'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'times'], 'cancelled' => ['bg' => '#f3f4f6', 'color' => '#4b5563', 'icon' => 'ban']];
                $s = $statusMap[$order['status']] ?? ['bg' => '#f3f4f6', 'color' => '#6b7280', 'icon' => 'circle'];
            ?>
            <a href="<?= sales_base_url('order_detail.php?id=' . $order['id']) ?>" class="sp-dash-order-item">
                <div class="sp-dash-order-status-dot" style="background:<?= $s['color'] ?>;"></div>
                <div class="sp-dash-order-info">
                    <div class="sp-dash-order-shop"><?= htmlspecialchars($order['shop_name']) ?></div>
                    <div class="sp-dash-order-meta"><?= $order['order_number'] ?> · <?= date('d M', strtotime($order['created_at'])) ?></div>
                </div>
                <div class="sp-dash-order-right">
                    <div class="sp-dash-order-amount">₹<?= number_format($order['total_amount'], 0) ?></div>
                    <div class="sp-dash-order-badge" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;">
                        <i class="fas fa-<?= $s['icon'] ?>"></i> <?= ucfirst($order['status']) ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($recentOrders) > 5): ?>
        <div style="text-align:center;padding:12px 0;">
            <a href="<?= sales_base_url('orders.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm">View All <?= $totalOrders ?> Orders</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
