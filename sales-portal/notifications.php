<?php
/**
 * Sales Executive Portal - Notifications
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Notifications';
$currentPage = 'notifications';

// Mark single notification as read and redirect to its link
if (isset($_GET['read_id'])) {
    $readId = (int)$_GET['read_id'];
    $redirect = $_GET['redirect'] ?? '';
    db_query('UPDATE sales_notifications SET is_read = 1 WHERE id = ? AND executive_id = ?', [$readId, $execId]);
    if ($redirect) {
        header('Location: ' . sales_base_url($redirect));
    } else {
        header('Location: ' . sales_base_url('notifications.php'));
    }
    exit;
}

// Ensure notifications table exists
try {
    $db = get_db_connection();
    $db->exec("CREATE TABLE IF NOT EXISTS sales_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executive_id INT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'info',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(500) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_exec_read (executive_id, is_read),
        INDEX idx_created (created_at)
    )");
} catch (PDOException $e) { /* table exists */ }

// Auto-generate notifications from order status changes
$recentOrders = db_fetch_all(
    'SELECT so.id, so.order_number, so.status, so.updated_at, sp.shop_name 
     FROM sales_orders so 
     JOIN sales_parties sp ON so.party_id = sp.id 
     WHERE so.executive_id = ? AND so.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
     ORDER BY so.updated_at DESC',
    [$execId]
);

// Check for order status notifications that don't exist yet
foreach ($recentOrders as $ro) {
    if (in_array($ro['status'], ['approved', 'rejected', 'dispatched', 'delivered'])) {
        $exists = db_fetch(
            'SELECT id FROM sales_notifications WHERE executive_id = ? AND type = ? AND link LIKE ?',
            [$execId, 'order_' . $ro['status'], '%id=' . $ro['id'] . '%']
        );
        if (!$exists) {
            $titles = [
                'approved' => 'Order Approved',
                'rejected' => 'Order Rejected',
                'dispatched' => 'Order Dispatched',
                'delivered' => 'Order Delivered',
            ];
            $icons = [
                'approved' => '✅',
                'rejected' => '❌',
                'dispatched' => '🚚',
                'delivered' => '📦',
            ];
            db_query(
                'INSERT INTO sales_notifications (executive_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $execId,
                    'order_' . $ro['status'],
                    $titles[$ro['status']] ?? 'Order Update',
                    $icons[$ro['status']] . ' ' . $ro['order_number'] . ' for ' . $ro['shop_name'] . ' has been ' . $ro['status'],
                    'order_detail.php?id=' . $ro['id'],
                    $ro['updated_at'],
                ]
            );
        }
    }
}

// Fetch notifications
$notifications = db_fetch_all(
    'SELECT * FROM sales_notifications WHERE executive_id = ? ORDER BY created_at DESC LIMIT 50',
    [$execId]
);

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($unreadCount > 0): ?>
<div class="sp-mb-16">
    <div class="sp-text-muted" style="font-size:13px;">
        <?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <div class="sp-card">
        <div class="sp-empty">
            <i class="fas fa-bell-slash"></i>
            <h3>No notifications yet</h3>
            <p>You'll see order updates and alerts here.</p>
        </div>
    </div>
<?php else: ?>
    <div class="sp-notif-list">
        <?php
        $typeIcons = [
            'order_approved' => ['icon' => 'check-circle', 'bg' => '#d1fae5', 'color' => '#065f46'],
            'order_rejected' => ['icon' => 'times-circle', 'bg' => '#fee2e2', 'color' => '#991b1b'],
            'order_dispatched' => ['icon' => 'truck', 'bg' => '#ede9fe', 'color' => '#5b21b6'],
            'order_delivered' => ['icon' => 'box-open', 'bg' => '#d1fae5', 'color' => '#047857'],
            'admin_announcement' => ['icon' => 'bullhorn', 'bg' => '#dbeafe', 'color' => '#1e40af'],
            'admin_alert' => ['icon' => 'exclamation-triangle', 'bg' => '#fee2e2', 'color' => '#991b1b'],
            'admin_reminder' => ['icon' => 'clock', 'bg' => '#fef3c7', 'color' => '#92400e'],
            'reminder_login' => ['icon' => 'sign-in-alt', 'bg' => '#d1fae5', 'color' => '#065f46'],
            'reminder_logout' => ['icon' => 'sign-out-alt', 'bg' => '#fee2e2', 'color' => '#991b1b'],
            'info' => ['icon' => 'info-circle', 'bg' => '#dbeafe', 'color' => '#1e40af'],
        ];
        foreach ($notifications as $notif):
            $ti = $typeIcons[$notif['type']] ?? $typeIcons['info'];
            $isUnread = !$notif['is_read'];
            $timeAgo = time() - strtotime($notif['created_at']);
            if ($timeAgo < 60) $ago = 'Just now';
            elseif ($timeAgo < 3600) $ago = floor($timeAgo / 60) . 'm ago';
            elseif ($timeAgo < 86400) $ago = floor($timeAgo / 3600) . 'h ago';
            else $ago = floor($timeAgo / 86400) . 'd ago';
        ?>
        <a href="<?= sales_base_url('notifications.php?read_id=' . $notif['id'] . ($notif['link'] ? '&redirect=' . urlencode($notif['link']) : '')) ?>" class="sp-notif-item <?= $isUnread ? 'sp-notif-unread' : '' ?>">
            <div class="sp-notif-icon" style="background:<?= $ti['bg'] ?>;color:<?= $ti['color'] ?>;">
                <i class="fas fa-<?= $ti['icon'] ?>"></i>
            </div>
            <div class="sp-notif-content">
                <div class="sp-notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="sp-notif-msg"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="sp-notif-time"><i class="far fa-clock"></i> <?= $ago ?></div>
            </div>
            <?php if ($isUnread): ?>
            <div class="sp-notif-dot"></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
