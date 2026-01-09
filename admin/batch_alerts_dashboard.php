<?php
/**
 * Batch Alerts Dashboard
 * Shows admin alerts for expiring batches, suspicious activity, etc.
 */

$pageTitle = 'Batch Alerts - Admin Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/batch_functions.php';

require_admin();

// Auto-check for expired batches and expiring batches
check_and_update_expired_batches();
check_expiring_batches();

$db = get_db_connection();

// Get unread alerts count
$unreadStmt = $db->query("SELECT COUNT(*) as count FROM batch_alerts WHERE is_read = 0");
$unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get alerts with filters
$filter = $_GET['filter'] ?? 'all';
$severity = $_GET['severity'] ?? 'all';

$sql = "SELECT ba.*, bc.product_name 
        FROM batch_alerts ba
        LEFT JOIN batch_codes bc ON bc.id = ba.batch_code_id
        WHERE 1=1";

if ($filter === 'unread') {
    $sql .= " AND ba.is_read = 0";
} elseif ($filter === 'resolved') {
    $sql .= " AND ba.is_resolved = 1";
} elseif ($filter === 'unresolved') {
    $sql .= " AND ba.is_resolved = 0";
}

if ($severity !== 'all') {
    $sql .= " AND ba.severity = :severity";
}

$sql .= " ORDER BY ba.created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
if ($severity !== 'all') {
    $stmt->execute([':severity' => $severity]);
} else {
    $stmt->execute();
}
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.alerts-header {
    background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.alert-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid #6b7280;
    transition: all 0.3s;
}

.alert-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateX(5px);
}

.alert-card.critical {
    border-left-color: #dc2626;
    background: #fef2f2;
}

.alert-card.high {
    border-left-color: #ea580c;
    background: #fff7ed;
}

.alert-card.medium {
    border-left-color: #f59e0b;
    background: #fffbeb;
}

.alert-card.low {
    border-left-color: #3b82f6;
    background: #eff6ff;
}

.alert-card.unread {
    font-weight: 600;
}

.severity-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.severity-critical {
    background: #dc2626;
    color: white;
}

.severity-high {
    background: #ea580c;
    color: white;
}

.severity-medium {
    background: #f59e0b;
    color: white;
}

.severity-low {
    background: #3b82f6;
    color: white;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    color: #374151;
}

.filter-tab:hover {
    border-color: var(--color-green);
    color: var(--color-green);
}

.filter-tab.active {
    background: var(--color-green);
    color: white;
    border-color: var(--color-green);
}

.alert-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.alert-btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-mark-read {
    background: #10b981;
    color: white;
}

.btn-resolve {
    background: #3b82f6;
    color: white;
}

.btn-view-batch {
    background: #6b7280;
    color: white;
}
</style>

<div class="container-fluid py-4">
    <div class="alerts-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-2">🚨 Batch Alerts Dashboard</h1>
                <p class="mb-0">Monitor batch expiry, stock levels, and suspicious activity</p>
            </div>
            <div class="text-end">
                <div class="badge bg-danger" style="font-size: 24px; padding: 10px 20px;">
                    <?= $unreadCount ?> Unread
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-tabs">
        <a href="?filter=all&severity=<?= $severity ?>" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All Alerts
        </a>
        <a href="?filter=unread&severity=<?= $severity ?>" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
            Unread (<?= $unreadCount ?>)
        </a>
        <a href="?filter=unresolved&severity=<?= $severity ?>" class="filter-tab <?= $filter === 'unresolved' ? 'active' : '' ?>">
            Unresolved
        </a>
        <a href="?filter=resolved&severity=<?= $severity ?>" class="filter-tab <?= $filter === 'resolved' ? 'active' : '' ?>">
            Resolved
        </a>
    </div>

    <div class="filter-tabs">
        <a href="?filter=<?= $filter ?>&severity=all" class="filter-tab <?= $severity === 'all' ? 'active' : '' ?>">
            All Severity
        </a>
        <a href="?filter=<?= $filter ?>&severity=critical" class="filter-tab <?= $severity === 'critical' ? 'active' : '' ?>">
            Critical
        </a>
        <a href="?filter=<?= $filter ?>&severity=high" class="filter-tab <?= $severity === 'high' ? 'active' : '' ?>">
            High
        </a>
        <a href="?filter=<?= $filter ?>&severity=medium" class="filter-tab <?= $severity === 'medium' ? 'active' : '' ?>">
            Medium
        </a>
        <a href="?filter=<?= $filter ?>&severity=low" class="filter-tab <?= $severity === 'low' ? 'active' : '' ?>">
            Low
        </a>
    </div>

    <!-- Alerts List -->
    <?php if (empty($alerts)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No alerts found
        </div>
    <?php else: ?>
        <?php foreach ($alerts as $alert): ?>
            <div class="alert-card <?= $alert['severity'] ?> <?= $alert['is_read'] ? '' : 'unread' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="severity-badge severity-<?= $alert['severity'] ?>">
                                <?= strtoupper($alert['severity']) ?>
                            </span>
                            <span class="badge bg-secondary">
                                <?= ucfirst(str_replace('_', ' ', $alert['alert_type'])) ?>
                            </span>
                            <?php if (!$alert['is_read']): ?>
                                <span class="badge bg-primary">NEW</span>
                            <?php endif; ?>
                            <?php if ($alert['is_resolved']): ?>
                                <span class="badge bg-success">✓ Resolved</span>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="mb-2">
                            Batch: <code><?= htmlspecialchars($alert['batch_code']) ?></code>
                            <?php if ($alert['product_name']): ?>
                                - <?= htmlspecialchars($alert['product_name']) ?>
                            <?php endif; ?>
                        </h5>
                        
                        <p class="mb-2"><?= htmlspecialchars($alert['message']) ?></p>
                        
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> <?= date('M d, Y g:i A', strtotime($alert['created_at'])) ?>
                        </small>
                        
                        <div class="alert-actions">
                            <?php if (!$alert['is_read']): ?>
                                <form method="POST" action="batch_alert_actions.php" style="display: inline;">
                                    <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <button type="submit" class="alert-btn btn-mark-read">
                                        <i class="fas fa-check"></i> Mark Read
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (!$alert['is_resolved']): ?>
                                <form method="POST" action="batch_alert_actions.php" style="display: inline;">
                                    <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                    <input type="hidden" name="action" value="resolve">
                                    <button type="submit" class="alert-btn btn-resolve">
                                        <i class="fas fa-check-circle"></i> Resolve
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="manage_batches.php?highlight=<?= $alert['batch_code_id'] ?>" class="alert-btn btn-view-batch">
                                <i class="fas fa-eye"></i> View Batch
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
