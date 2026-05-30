<?php
/**
 * Admin: SMS Logs
 * View SMS delivery history, OTP status, provider usage
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_service.php';
require_admin();

$pageTitle = 'SMS Logs — Admin';
$adminPage = 'sms_logs';

// Filters
$filterPhone = trim($_GET['phone'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$result = SMSService::getLogs($perPage, $offset, $filterPhone ?: null, $filterStatus ?: null);
$logs = $result['logs'];
$total = $result['total'];
$totalPages = ceil($total / $perPage);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="fas fa-clipboard-list text-primary"></i> SMS Logs</h4>
            <p class="text-muted mb-0">Track SMS delivery, OTP verification status, and provider usage</p>
        </div>
        <span class="badge bg-secondary fs-6"><?= number_format($total); ?> total</span>
    </div>
    
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold mb-1">Phone Number</label>
                    <input type="text" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($filterPhone); ?>" placeholder="Search by phone...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                </div>
                <?php if ($filterPhone || $filterStatus): ?>
                <div class="col-md-2">
                    <a href="sms_logs.php" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-times"></i> Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                <p>No SMS logs found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Provider</th>
                            <th>Status</th>
                            <th>OTP</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap small"><?= date('M d, H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><code><?= htmlspecialchars($log['phone']); ?></code></td>
                            <td>
                                <?php
                                $typeColors = ['otp' => 'primary', 'notification' => 'info', 'marketing' => 'warning', 'test' => 'secondary'];
                                $tc = $typeColors[$log['message_type']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $tc; ?>"><?= ucfirst($log['message_type']); ?></span>
                            </td>
                            <td class="small"><?= htmlspecialchars($log['provider_name'] ?? '—'); ?></td>
                            <td>
                                <?php
                                $statusColors = ['sent' => 'success', 'delivered' => 'success', 'failed' => 'danger', 'pending' => 'warning'];
                                $sc = $statusColors[$log['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $sc; ?>"><?= ucfirst($log['status']); ?></span>
                            </td>
                            <td>
                                <?php if ($log['otp_code']): ?>
                                    <code><?= htmlspecialchars($log['otp_code']); ?></code>
                                    <?php if ($log['otp_verified']): ?>
                                        <i class="fas fa-check-circle text-success" title="Verified"></i>
                                    <?php else: ?>
                                        <i class="fas fa-clock text-muted" title="Not verified"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['failure_reason']): ?>
                                    <span class="text-danger small" title="<?= htmlspecialchars($log['failure_reason']); ?>">
                                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars(substr($log['failure_reason'], 0, 40)); ?>...
                                    </span>
                                <?php else: ?>
                                    <span class="text-success small"><i class="fas fa-check"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <nav class="p-3">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?= $i; ?>&phone=<?= urlencode($filterPhone); ?>&status=<?= urlencode($filterStatus); ?>"><?= $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
