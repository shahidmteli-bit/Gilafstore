<?php
/**
 * Gilaf Rewards API — AJAX endpoint
 * Handles: wallet check, redeem apply/remove, spin, notifications, popup click
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards_engine.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

function rw_json(array $data): void { echo json_encode($data); exit; }
function rw_error(string $msg): void { rw_json(['success' => false, 'error' => $msg]); }

// ── Wallet balance ─────────────────────────────────────────────────────────
if ($action === 'get_balance') {
    if (!$userId) rw_error('Not logged in');
    $wallet = rw_get_wallet($userId);
    rw_json([
        'success'  => true,
        'balance'  => (float)($wallet['balance'] ?? 0),
        'locked'   => (float)($wallet['locked_balance'] ?? 0),
        'tier'     => $wallet['tier'] ?? 'Silver',
        'lifetime' => (float)($wallet['lifetime_earned'] ?? 0),
    ]);
}

// ── Cart: check how much can be redeemed ───────────────────────────────────
if ($action === 'check_redeem') {
    if (!$userId) rw_json(['allowed' => false, 'max' => 0, 'balance' => 0, 'reason' => 'Please log in to use rewards']);
    $cartTotal = (float)($input['cart_total'] ?? 0);
    $result = rw_get_max_redeem($userId, $cartTotal);
    rw_json(array_merge(['success' => true], $result));
}

// ── Cart: apply redemption (store in session) ──────────────────────────────
if ($action === 'apply_redeem') {
    if (!$userId) rw_error('Not logged in');
    $cartTotal = (float)($input['cart_total'] ?? 0);
    $requested = (float)($input['amount'] ?? 0);

    $result = rw_get_max_redeem($userId, $cartTotal);
    if (!$result['allowed']) rw_error($result['reason']);

    $toApply = min($requested, $result['max']);
    if ($toApply <= 0) rw_error('Invalid amount');

    $_SESSION['rw_redeem_amount'] = $toApply;
    rw_json(['success' => true, 'applied' => $toApply, 'message' => '₹' . number_format($toApply, 2) . ' rewards will be applied at checkout']);
}

// ── Cart: remove redemption ─────────────────────────────────────────────────
if ($action === 'remove_redeem') {
    unset($_SESSION['rw_redeem_amount']);
    rw_json(['success' => true]);
}

// ── Get applied redeem ──────────────────────────────────────────────────────
if ($action === 'get_applied_redeem') {
    rw_json(['success' => true, 'applied' => (float)($_SESSION['rw_redeem_amount'] ?? 0)]);
}

// ── Spin wheel ──────────────────────────────────────────────────────────────
if ($action === 'spin') {
    if (!$userId) rw_error('Please log in to spin');
    $result = rw_spin_wheel($userId);
    if (isset($result['error'])) rw_error($result['error']);
    rw_json($result);
}

// ── Popup click tracking ────────────────────────────────────────────────────
if ($action === 'popup_click') {
    $popupId = (int)($input['popup_id'] ?? 0);
    if ($popupId > 0) {
        $db = get_db_connection();
        $db->prepare("UPDATE rewards_popups SET clicks = clicks + 1 WHERE id = ?")->execute([$popupId]);
    }
    rw_json(['success' => true]);
}

// ── Mark notifications read ─────────────────────────────────────────────────
if ($action === 'mark_notifications_read') {
    if (!$userId) rw_error('Not logged in');
    $db = get_db_connection();
    $db->prepare("UPDATE rewards_notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
    rw_json(['success' => true]);
}

// ── Get notifications ───────────────────────────────────────────────────────
if ($action === 'get_notifications') {
    if (!$userId) rw_error('Not logged in');
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM rewards_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $st->execute([$userId]);
    rw_json(['success' => true, 'notifications' => $st->fetchAll()]);
}

// ── Earn estimate for product ───────────────────────────────────────────────
if ($action === 'earn_estimate') {
    $price = (float)($input['price'] ?? 0);
    $tierName = 'Silver';
    if ($userId) {
        $wallet = rw_get_wallet($userId);
        $tierName = $wallet['tier'] ?? 'Silver';
    }
    $earn = rw_earn_per_100_label($price, $tierName);
    rw_json(['success' => true, 'earn_label' => $earn, 'tier' => $tierName]);
}

// ── Transaction history ─────────────────────────────────────────────────────
if ($action === 'get_history') {
    if (!$userId) rw_error('Not logged in');
    $limit = min(50, max(5, (int)($input['limit'] ?? 10)));
    $db = get_db_connection();
    $st = $db->prepare(
        "SELECT type, amount, balance_after, source, description, created_at
         FROM rewards_transactions
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $st->execute([$userId, $limit]);
    rw_json(['success' => true, 'transactions' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── Run expiry (called by cron or manually) ─────────────────────────────────
if ($action === 'run_expiry') {
    $adminKey = $input['key'] ?? '';
    if ($adminKey !== 'gilaf_rewards_cron_2026') rw_error('Unauthorized');
    $count = rw_run_expiry();
    rw_json(['success' => true, 'expired_count' => $count]);
}

rw_error('Unknown action');
