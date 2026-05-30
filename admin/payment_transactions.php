<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$pageTitle = 'Payment Transactions';
$adminPage = 'payment_settings';

$db = get_db_connection();

// Filters
$filters = [
    'from'   => trim((string)($_GET['from'] ?? '')),
    'to'     => trim((string)($_GET['to'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
];

// Stats
$totalPayments = $capturedPayments = $failedPayments = $refundedPayments = 0;
$totalRevenue = 0;
$transactions = [];

try {
    $totalPayments   = (int)$db->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    $capturedPayments = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'captured'")->fetchColumn();
    $failedPayments  = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'")->fetchColumn();
    $refundedPayments = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'refunded'")->fetchColumn();
    $totalRevenue    = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'captured'")->fetchColumn();

    // Filtered transactions
    $where = [];
    $params = [];
    if ($filters['from'] !== '') {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $filters['to'];
    }
    if ($filters['status'] !== '' && in_array($filters['status'], ['created','authorized','captured','failed','refunded'], true)) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    $sql = "SELECT id, order_id, internal_order_id, razorpay_order_id, razorpay_payment_id, amount, currency, status, payment_method, error_description, created_at, updated_at FROM payments";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT 500';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filtered stats
    $filteredTotal = 0;
    $filteredRevenue = 0;
    if (!empty($where)) {
        $fSql = "SELECT COUNT(*) FROM payments WHERE " . implode(' AND ', $where);
        $fStmt = $db->prepare($fSql);
        $fStmt->execute($params);
        $filteredTotal = (int)$fStmt->fetchColumn();

        $fSql2 = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'captured' AND " . implode(' AND ', $where);
        $fStmt2 = $db->prepare($fSql2);
        $fStmt2->execute($params);
        $filteredRevenue = (float)$fStmt2->fetchColumn();
    }
} catch (Exception $e) {}

$hasFilters = ($filters['from'] !== '' || $filters['to'] !== '' || $filters['status'] !== '');

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.pt-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
.pt-tabs { display:flex; gap:8px; margin-bottom: 18px; }
.pt-tab { display:inline-flex; align-items:center; gap:8px; padding: 9px 16px; border-radius: 8px; border:1px solid #e5e7eb; background:#fff; color:#374151; text-decoration:none; font-weight:700; font-size: 13px; transition: all 0.15s; }
.pt-tab:hover { background: #f9fafb; }
.pt-tab.active { background: linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; border-color: transparent; }
.pt-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 22px; }
.pt-stat { padding: 16px 12px; border-radius: 10px; color: #fff; text-align: center; }
.pt-stat-num { font-size: 24px; font-weight: 800; }
.pt-stat-lbl { font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; opacity: .88; margin-top: 3px; }
.pt-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.08); margin-bottom: 20px; overflow: hidden; }
.pt-card-header { padding: 14px 20px; font-weight: 700; font-size: 15px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
.pt-card-body { padding: 18px 20px; }
.pt-filter { display:flex; gap:12px; flex-wrap:wrap; align-items:end; }
.pt-filter label { display:block; font-weight:600; font-size:12px; color:#6b7280; margin-bottom:4px; }
.pt-filter input, .pt-filter select { padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; }
.pt-btn { padding:8px 16px; border:none; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
.pt-btn-primary { background:#3b82f6; color:#fff; }
.pt-btn-primary:hover { background:#2563eb; }
.pt-btn-reset { background:#f3f4f6; color:#374151; }
.pt-btn-reset:hover { background:#e5e7eb; }
.pt-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.3px; }
.pt-badge-captured { background:#dcfce7; color:#166534; }
.pt-badge-failed { background:#fee2e2; color:#991b1b; }
.pt-badge-created { background:#e0e7ff; color:#3730a3; }
.pt-badge-authorized { background:#fef3c7; color:#92400e; }
.pt-badge-refunded { background:#ffedd5; color:#9a3412; }
.pt-table { width:100%; border-collapse:collapse; font-size:13px; }
.pt-table th { background:#f9fafb; padding:10px 14px; text-align:left; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280; white-space:nowrap; border-bottom:2px solid #e5e7eb; }
.pt-table td { padding:10px 14px; border-bottom:1px solid #f3f4f6; white-space:nowrap; color:#374151; }
.pt-table tr:hover { background:#f9fafb; }
.pt-table code { font-size:11px; color:#6b7280; background:#f3f4f6; padding:2px 6px; border-radius:4px; }
.pt-info { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:14px; font-size:13px; color:#6b7280; }
.pt-info strong { color:#374151; }
@media (max-width: 768px) {
    .pt-stats { grid-template-columns: repeat(3, 1fr); }
    .pt-filter { flex-direction: column; }
}
</style>

<div class="pt-wrap">
    <h4 style="margin-bottom: 5px;"><i class="fas fa-exchange-alt me-2"></i>Payment Transactions</h4>
    <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">View all payment transactions with date-wise reports</p>

    <div class="pt-tabs">
        <a class="pt-tab" href="<?= base_url('admin/payment_settings.php'); ?>"><i class="fas fa-sliders-h"></i> Settings</a>
        <a class="pt-tab active" href="<?= base_url('admin/payment_transactions.php'); ?>"><i class="fas fa-exchange-alt"></i> Transactions</a>
    </div>

    <!-- Dashboard Stats -->
    <div class="pt-stats">
        <div class="pt-stat" style="background: linear-gradient(135deg,#2563eb,#3b82f6);">
            <div class="pt-stat-num"><?= number_format($totalPayments); ?></div>
            <div class="pt-stat-lbl">Total Payments</div>
        </div>
        <div class="pt-stat" style="background: linear-gradient(135deg,#059669,#10b981);">
            <div class="pt-stat-num"><?= number_format($capturedPayments); ?></div>
            <div class="pt-stat-lbl">Successful</div>
        </div>
        <div class="pt-stat" style="background: linear-gradient(135deg,#dc2626,#ef4444);">
            <div class="pt-stat-num"><?= number_format($failedPayments); ?></div>
            <div class="pt-stat-lbl">Failed</div>
        </div>
        <div class="pt-stat" style="background: linear-gradient(135deg,#f97316,#fb923c);">
            <div class="pt-stat-num"><?= number_format($refundedPayments); ?></div>
            <div class="pt-stat-lbl">Refunded</div>
        </div>
        <div class="pt-stat" style="background: linear-gradient(135deg,#7c3aed,#8b5cf6);">
            <div class="pt-stat-num">₹<?= number_format($totalRevenue, 0); ?></div>
            <div class="pt-stat-lbl">Revenue</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="pt-card">
        <div class="pt-card-header"><i class="fas fa-filter" style="color:#10b981;"></i> Date & Status Filter</div>
        <div class="pt-card-body">
            <form method="GET" class="pt-filter">
                <div>
                    <label>From Date</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($filters['from']); ?>">
                </div>
                <div>
                    <label>To Date</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($filters['to']); ?>">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="created" <?= $filters['status'] === 'created' ? 'selected' : ''; ?>>Created</option>
                        <option value="authorized" <?= $filters['status'] === 'authorized' ? 'selected' : ''; ?>>Authorized</option>
                        <option value="captured" <?= $filters['status'] === 'captured' ? 'selected' : ''; ?>>Captured</option>
                        <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?= $filters['status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:end;">
                    <button type="submit" class="pt-btn pt-btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a class="pt-btn pt-btn-reset" href="<?= base_url('admin/payment_transactions.php'); ?>">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($hasFilters): ?>
    <div class="pt-info">
        <span>Showing <strong><?= count($transactions); ?></strong> transactions</span>
        <?php if ($filters['from']): ?><span>From: <strong><?= htmlspecialchars($filters['from']); ?></strong></span><?php endif; ?>
        <?php if ($filters['to']): ?><span>To: <strong><?= htmlspecialchars($filters['to']); ?></strong></span><?php endif; ?>
        <?php if ($filters['status']): ?><span>Status: <strong><?= ucfirst($filters['status']); ?></strong></span><?php endif; ?>
        <?php if (isset($filteredRevenue) && $filteredRevenue > 0): ?><span>Filtered Revenue: <strong>₹<?= number_format($filteredRevenue, 2); ?></strong></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Transactions Table -->
    <div class="pt-card">
        <div class="pt-card-header">
            <i class="fas fa-list" style="color:#3b82f6;"></i> 
            Transactions
            <span style="margin-left:auto;font-size:12px;color:#9ca3af;font-weight:500;"><?= count($transactions); ?> records</span>
        </div>
        <div style="overflow:auto;">
            <table class="pt-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Order ID</th>
                        <th>Internal Order</th>
                        <th>RZP Order</th>
                        <th>RZP Payment</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="9" style="padding:20px; color:#9ca3af; text-align:center;">No transactions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $p): ?>
                            <?php
                            $badgeClass = 'pt-badge-created';
                            if ($p['status'] === 'captured') $badgeClass = 'pt-badge-captured';
                            elseif ($p['status'] === 'failed') $badgeClass = 'pt-badge-failed';
                            elseif ($p['status'] === 'authorized') $badgeClass = 'pt-badge-authorized';
                            elseif ($p['status'] === 'refunded') $badgeClass = 'pt-badge-refunded';
                            ?>
                            <tr>
                                <td style="font-weight:700;"><?= (int)$p['id']; ?></td>
                                <td><span class="pt-badge <?= $badgeClass; ?>"><?= htmlspecialchars((string)$p['status']); ?></span></td>
                                <td style="font-weight:800;">₹<?= number_format((float)$p['amount'], 2); ?></td>
                                <td><?= htmlspecialchars((string)($p['order_id'] ?: '—')); ?></td>
                                <td><code><?= htmlspecialchars((string)($p['internal_order_id'] ?: '—')); ?></code></td>
                                <td><code><?= htmlspecialchars((string)($p['razorpay_order_id'] ?: '—')); ?></code></td>
                                <td><code><?= htmlspecialchars((string)($p['razorpay_payment_id'] ?: '—')); ?></code></td>
                                <td style="color:#6b7280;"><?= htmlspecialchars((string)($p['created_at'] ?? '')); ?></td>
                                <td style="color:#6b7280;"><?= htmlspecialchars((string)($p['updated_at'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
