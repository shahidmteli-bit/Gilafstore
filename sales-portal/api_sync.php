<?php
/**
 * Sales Portal - Sync API
 * Returns latest update timestamps for auto-sync in the app
 */
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Require auth via token or session
if (!sales_is_logged_in()) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$exec = sales_get_executive();
$execId = $exec['id'];

// Get latest timestamps for key data
$latestOrder = db_fetch('SELECT MAX(updated_at) as ts FROM sales_orders WHERE executive_id = ?', [$execId]);
$latestParty = db_fetch('SELECT MAX(updated_at) as ts FROM sales_parties WHERE created_by = ?', [$execId]);
$orderCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ?', [$execId]);
$partyCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_parties WHERE created_by = ?', [$execId]);
$pendingOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? AND status = ?', [$execId, 'pending']);

// Check login/logout reminders and auto-generate notifications
try {
    $now = date('H:i');
    $today = date('Y-m-d');
    $todayDow = (int)date('w'); // 0=Sun, 1=Mon, ..., 6=Sat
    $reminders = db_fetch_all('SELECT * FROM sales_login_reminders WHERE is_active = 1');
    foreach ($reminders as $rem) {
        // Check day-of-week
        $allowedDays = explode(',', $rem['days_of_week'] ?? '1,2,3,4,5');
        if (!in_array((string)$todayDow, $allowedDays)) continue;

        $remTime = substr($rem['reminder_time'], 0, 5);
        // Check if current time is within 2 minutes of reminder time
        $diff = abs(strtotime($now) - strtotime($remTime));
        if ($diff <= 120) {
            // Check if we already sent this reminder today
            $alreadySent = db_fetch(
                'SELECT id FROM sales_notifications WHERE executive_id = ? AND type = ? AND DATE(created_at) = ?',
                [$execId, 'reminder_' . $rem['reminder_type'], $today]
            );
            if (!$alreadySent) {
                $title = $rem['reminder_type'] === 'login' ? '🟢 Login Reminder' : '🔴 Logout Reminder';
                db_query(
                    'INSERT INTO sales_notifications (executive_id, type, title, message) VALUES (?, ?, ?, ?)',
                    [$execId, 'reminder_' . $rem['reminder_type'], $title, $rem['message']]
                );
            }
        }
    }
} catch (PDOException $e) { /* reminders table may not exist yet */ }

// Unread notifications count
$unreadNotifs = 0;
try {
    $nr = db_fetch('SELECT COUNT(*) as cnt FROM sales_notifications WHERE executive_id = ? AND is_read = 0', [$execId]);
    $unreadNotifs = (int)($nr['cnt'] ?? 0);
} catch (PDOException $e) { /* table may not exist yet */ }

// Compute app_version from newest file modification across PHP, CSS, JS
$appVersion = 0;
$checkPaths = [
    __DIR__ . '/includes/header.php',
    __DIR__ . '/includes/footer.php',
    __DIR__ . '/includes/auth.php',
    __DIR__ . '/assets/css/portal.css',
    __DIR__ . '/sw.js',
    __DIR__ . '/index.php',
    __DIR__ . '/parties.php',
    __DIR__ . '/new_order.php',
    __DIR__ . '/attendance.php',
    __DIR__ . '/collect_payment.php',
    __DIR__ . '/orders.php',
    __DIR__ . '/visit_schedule.php',
    __DIR__ . '/outstanding.php',
    __DIR__ . '/profile.php',
    __DIR__ . '/manifest.json',
];
foreach ($checkPaths as $fp) {
    if (file_exists($fp)) {
        $mt = filemtime($fp);
        if ($mt > $appVersion) $appVersion = $mt;
    }
}

echo json_encode([
    'ts' => time(),
    'orders_updated' => $latestOrder['ts'] ?? null,
    'parties_updated' => $latestParty['ts'] ?? null,
    'order_count' => (int)($orderCount['cnt'] ?? 0),
    'party_count' => (int)($partyCount['cnt'] ?? 0),
    'pending_orders' => (int)($pendingOrders['cnt'] ?? 0),
    'unread_notifs' => $unreadNotifs,
    'app_version' => $appVersion,
]);
