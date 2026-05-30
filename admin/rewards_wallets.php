<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards_engine.php';
require_admin();

$pageTitle = 'User Wallets — Gilaf Rewards';
$adminPage = 'rewards_wallets';
$db = get_db_connection();

// ── Handle admin actions ──────────────────────────────────────────────────────
$msg = $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $userId  = (int)($_POST['user_id'] ?? 0);
    $amount  = abs((float)($_POST['amount'] ?? 0));
    $note    = trim($_POST['note'] ?? '');

    if ($userId > 0) {
        if ($action === 'credit') {
            if ($amount > 0) {
                rw_credit($userId, $amount, 'admin', $note ?: 'Admin credit');
                if ($note) $db->prepare("UPDATE rewards_wallets SET admin_note=? WHERE user_id=?")->execute([$note, $userId]);
                $msg = "₹{$amount} credited to wallet."; $msgType = 'success';
            }
        } elseif ($action === 'debit') {
            if (rw_debit($userId, $amount, $note ?: 'Admin deduction')) {
                if ($note) $db->prepare("UPDATE rewards_wallets SET admin_note=? WHERE user_id=?")->execute([$note, $userId]);
                $msg = "₹{$amount} deducted from wallet."; $msgType = 'success';
            } else { $msg = "Insufficient balance or wallet issue."; $msgType = 'danger'; }
        } elseif ($action === 'freeze') {
            $db->prepare("UPDATE rewards_wallets SET is_frozen=1 WHERE user_id=?")->execute([$userId]);
            if ($note) $db->prepare("UPDATE rewards_wallets SET admin_note=? WHERE user_id=?")->execute([$note, $userId]);
            $msg = "Wallet frozen."; $msgType = 'warning';
        } elseif ($action === 'unfreeze') {
            $db->prepare("UPDATE rewards_wallets SET is_frozen=0 WHERE user_id=?")->execute([$userId]);
            $msg = "Wallet unfrozen."; $msgType = 'success';
        } elseif ($action === 'disable') {
            $db->prepare("UPDATE rewards_wallets SET is_disabled=1 WHERE user_id=?")->execute([$userId]);
            $msg = "Wallet disabled."; $msgType = 'danger';
        } elseif ($action === 'enable') {
            $db->prepare("UPDATE rewards_wallets SET is_disabled=0 WHERE user_id=?")->execute([$userId]);
            $msg = "Wallet enabled."; $msgType = 'success';
        }
    }
    // Orphan cleanup: delete wallets/transactions for deleted users
    if ($action === 'cleanup_orphans') {
        $rewardsTables = ['rewards_transactions','rewards_locked_rewards','rewards_referrals','rewards_spin_history','rewards_notifications'];
        foreach ($rewardsTables as $tbl) {
            try { $db->exec("DELETE FROM {$tbl} WHERE user_id NOT IN (SELECT id FROM users)"); } catch (Throwable $e) {}
        }
        $del = $db->exec("DELETE FROM rewards_wallets WHERE user_id NOT IN (SELECT id FROM users)");
        $msg = "Cleaned up {$del} orphan wallet(s)."; $msgType = 'success';
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$tier    = $_GET['tier'] ?? '';
$status  = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($tier) { $where .= " AND rw.tier = ?"; $params[] = $tier; }
if ($status === 'frozen')   { $where .= " AND rw.is_frozen=1"; }
if ($status === 'disabled') { $where .= " AND rw.is_disabled=1"; }
if ($status === 'active')   { $where .= " AND rw.is_frozen=0 AND rw.is_disabled=0"; }

$baseSql = "FROM rewards_wallets rw LEFT JOIN users u ON u.id = rw.user_id $where";
$stCount = $db->prepare("SELECT COUNT(*) $baseSql");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stWallets = $db->prepare("SELECT rw.*, u.name, u.email, u.phone $baseSql ORDER BY rw.lifetime_earned DESC LIMIT $perPage OFFSET $offset");
$stWallets->execute($params);
$wallets = $stWallets->fetchAll();

// view single wallet transactions
$viewUserId = (int)($_GET['view'] ?? 0);
$viewWallet = null; $viewTxs = []; $viewUser = null;
if ($viewUserId > 0) {
    $viewWallet = rw_get_wallet($viewUserId);
    $stU = $db->prepare("SELECT * FROM users WHERE id=? LIMIT 1"); $stU->execute([$viewUserId]);
    $viewUser = $stU->fetch();
    $stTx = $db->prepare("SELECT * FROM rewards_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
    $stTx->execute([$viewUserId]); $viewTxs = $stTx->fetchAll();
    $stLocked = $db->prepare("SELECT * FROM rewards_locked_rewards WHERE user_id=? ORDER BY created_at DESC");
    $stLocked->execute([$viewUserId]); $viewLocked = $stLocked->fetchAll();
}

include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.rw-card { background:#fff; border-radius:14px; box-shadow:0 2px 14px rgba(0,0,0,.07); }
.tier-badge-Silver   { background:#e5e7eb; color:#374151; padding:2px 9px; border-radius:99px; font-size:.72rem; font-weight:600; }
.tier-badge-Gold     { background:#fef3c7; color:#92400e; padding:2px 9px; border-radius:99px; font-size:.72rem; font-weight:600; }
.tier-badge-Platinum { background:#ede9fe; color:#5b21b6; padding:2px 9px; border-radius:99px; font-size:.72rem; font-weight:600; }
.w-frozen  { color:#f59e0b; font-size:.72rem; font-weight:600; }
.w-disabled{ color:#ef4444; font-size:.72rem; font-weight:600; }
.w-active  { color:#10b981; font-size:.72rem; font-weight:600; }
</style>
<div class="admin-content">
<div class="container-fluid py-3 px-3">

<?php if ($viewUserId && $viewWallet): ?>
<!-- ── Single Wallet View ──────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= base_url('admin/rewards_wallets.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
  <h5 class="mb-0 fw-bold" style="color:#1e1b4b;"><i class="fas fa-wallet me-2" style="color:#7c3aed;"></i><?= htmlspecialchars($viewUser['name'] ?? 'User #'.$viewUserId) ?>'s Wallet</h5>
</div>

<div class="row g-3 mb-3">
  <?php $walletStatClass = $viewWallet['is_disabled'] ? 'danger' : ($viewWallet['is_frozen'] ? 'warning' : 'success'); ?>
  <div class="col-md-3"><div class="rw-card p-3 text-center"><div class="text-muted small">Balance</div><div class="fw-bold" style="font-size:1.8rem;color:#7c3aed;">₹<?= number_format((float)$viewWallet['balance'],2) ?></div></div></div>
  <div class="col-md-3"><div class="rw-card p-3 text-center"><div class="text-muted small">Locked</div><div class="fw-bold" style="font-size:1.5rem;color:#f59e0b;">₹<?= number_format((float)$viewWallet['locked_balance'],2) ?></div></div></div>
  <div class="col-md-3"><div class="rw-card p-3 text-center"><div class="text-muted small">Lifetime Earned</div><div class="fw-bold" style="font-size:1.5rem;color:#10b981;">₹<?= number_format((float)$viewWallet['lifetime_earned'],2) ?></div></div></div>
  <div class="col-md-3"><div class="rw-card p-3 text-center"><div class="text-muted small">Lifetime Redeemed</div><div class="fw-bold" style="font-size:1.5rem;color:#3b82f6;">₹<?= number_format((float)$viewWallet['lifetime_redeemed'],2) ?></div></div></div>
</div>

<!-- Admin Action Panel -->
<div class="rw-card p-3 mb-3">
  <div class="fw-bold mb-2" style="font-size:.9rem;color:#1e1b4b;"><i class="fas fa-tools me-2" style="color:#7c3aed;"></i>Admin Actions</div>
  <form method="POST" class="row g-2 align-items-end">
    <input type="hidden" name="user_id" value="<?= $viewUserId ?>">
    <div class="col-auto">
      <select name="action" class="form-select form-select-sm" style="border-radius:8px;">
        <option value="credit">+ Credit Rewards</option>
        <option value="debit">− Deduct Rewards</option>
        <option value="freeze">🔒 Freeze Wallet</option>
        <option value="unfreeze">🔓 Unfreeze Wallet</option>
        <option value="disable">🚫 Disable Wallet</option>
        <option value="enable">✅ Enable Wallet</option>
      </select>
    </div>
    <div class="col-auto"><input type="number" name="amount" placeholder="Amount ₹" class="form-control form-control-sm" style="width:110px;border-radius:8px;" min="0" step="0.01"></div>
    <div class="col"><input type="text" name="note" placeholder="Admin note..." class="form-control form-control-sm" style="border-radius:8px;"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-check me-1"></i>Apply</button></div>
  </form>
  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> mt-2 py-2 px-3 mb-0" style="border-radius:8px;font-size:.85rem;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
</div>

<!-- Locked Rewards -->
<?php if (!empty($viewLocked)): ?>
<div class="rw-card p-3 mb-3">
  <div class="fw-bold mb-2" style="font-size:.9rem;color:#1e1b4b;"><i class="fas fa-lock me-2" style="color:#f59e0b;"></i>Locked Rewards</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.82rem;">
      <thead class="table-light"><tr><th>Amount</th><th>Condition</th><th>Threshold</th><th>Status</th><th>Expires</th><th>Description</th></tr></thead>
      <tbody>
        <?php foreach ($viewLocked as $lr): ?>
        <tr>
          <td class="fw-bold">₹<?= number_format((float)$lr['amount'],2) ?></td>
          <td><?= htmlspecialchars($lr['unlock_condition']) ?></td>
          <td><?= $lr['unlock_value'] > 0 ? '₹'.number_format((float)$lr['unlock_value'],0) : '—' ?></td>
          <td><span class="badge <?= $lr['status']==='locked'?'bg-warning text-dark':($lr['status']==='unlocked'?'bg-success':'bg-secondary') ?>"><?= $lr['status'] ?></span></td>
          <td class="text-muted"><?= $lr['expires_at'] ? date('M d, Y', strtotime($lr['expires_at'])) : '—' ?></td>
          <td class="text-muted"><?= htmlspecialchars($lr['description'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Transaction History -->
<div class="rw-card p-3">
  <div class="fw-bold mb-2" style="font-size:.9rem;color:#1e1b4b;"><i class="fas fa-history me-2" style="color:#3b82f6;"></i>Transaction History (Last 100)</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:.82rem;">
      <thead class="table-light"><tr><th>Type</th><th>Source</th><th>Amount</th><th>Balance After</th><th>Description</th><th>Expires</th><th>Date</th></tr></thead>
      <tbody>
        <?php if (empty($viewTxs)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No transactions yet</td></tr>
        <?php else: foreach ($viewTxs as $tx): ?>
          <tr>
            <td><span class="fw-bold <?= $tx['type']==='credit'?'text-success':($tx['type']==='debit'?'text-danger':'text-muted') ?>"><?= strtoupper($tx['type']) ?></span></td>
            <td><span class="badge bg-light text-dark" style="font-size:.72rem;"><?= htmlspecialchars($tx['source']) ?></span></td>
            <td class="<?= $tx['type']==='credit'?'text-success':'text-danger' ?> fw-bold"><?= $tx['type']==='credit'?'+':'-' ?>₹<?= number_format((float)$tx['amount'],2) ?></td>
            <td>₹<?= number_format((float)$tx['balance_after'],2) ?></td>
            <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($tx['description'] ?? '') ?></td>
            <td class="text-muted"><?= $tx['expires_at'] ? date('M d, Y', strtotime($tx['expires_at'])) : '—' ?></td>
            <td class="text-muted"><?= date('M d, H:i', strtotime($tx['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ── Wallets List ──────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold" style="color:#1e1b4b;"><i class="fas fa-wallet me-2" style="color:#7c3aed;"></i>User Wallets</h5>
  <div class="d-flex gap-2">
    <form method="POST" onsubmit="return confirm('Delete all wallets for deleted users?');" style="display:inline;">
      <input type="hidden" name="action" value="cleanup_orphans">
      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt me-1"></i>Clean Orphan Wallets</button>
    </form>
    <a href="<?= base_url('admin/rewards_dashboard.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?> py-2 px-3 mb-3" style="border-radius:10px;font-size:.87rem;"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="rw-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col"><input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email..." class="form-control form-control-sm" style="border-radius:8px;"></div>
    <div class="col-auto">
      <select name="tier" class="form-select form-select-sm" style="border-radius:8px;width:110px;">
        <option value="">All Tiers</option>
        <option value="Silver" <?= $tier==='Silver'?'selected':'' ?>>Silver</option>
        <option value="Gold" <?= $tier==='Gold'?'selected':'' ?>>Gold</option>
        <option value="Platinum" <?= $tier==='Platinum'?'selected':'' ?>>Platinum</option>
      </select>
    </div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm" style="border-radius:8px;width:120px;">
        <option value="">All Status</option>
        <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="frozen" <?= $status==='frozen'?'selected':'' ?>>Frozen</option>
        <option value="disabled" <?= $status==='disabled'?'selected':'' ?>>Disabled</option>
      </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-search me-1"></i>Search</button></div>
    <?php if ($search || $tier || $status): ?><div class="col-auto"><a href="<?= base_url('admin/rewards_wallets.php') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Clear</a></div><?php endif; ?>
  </div>
</form>

<!-- Table -->
<div class="rw-card p-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:.83rem;">
      <thead class="table-light">
        <tr><th>User</th><th>Tier</th><th>Balance</th><th>Locked</th><th>Lifetime Earned</th><th>Redeemed</th><th>Referral Code</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($wallets)): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">No wallets found</td></tr>
        <?php else: foreach ($wallets as $w): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($w['name'] ?? 'User #'.$w['user_id']) ?></div>
              <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($w['email'] ?? '') ?></div>
              <?php if (!empty($w['phone'])): ?><div style="font-size:.72rem;color:#7c3aed;"><i class="fas fa-phone" style="font-size:.65rem;"></i> <?= htmlspecialchars($w['phone']) ?></div><?php endif; ?>
            </td>
            <td><span class="tier-badge-<?= $w['tier'] ?>"><?= $w['tier'] ?></span></td>
            <td class="fw-bold" style="color:#7c3aed;">₹<?= number_format((float)$w['balance'],2) ?></td>
            <td style="color:#f59e0b;">₹<?= number_format((float)$w['locked_balance'],2) ?></td>
            <td style="color:#10b981;">₹<?= number_format((float)$w['lifetime_earned'],2) ?></td>
            <td style="color:#3b82f6;">₹<?= number_format((float)$w['lifetime_redeemed'],2) ?></td>
            <td class="font-monospace text-muted"><?= htmlspecialchars($w['referral_code'] ?? '—') ?></td>
            <td>
              <?php if ($w['is_disabled']): ?><span class="w-disabled">DISABLED</span>
              <?php elseif ($w['is_frozen']): ?><span class="w-frozen">FROZEN</span>
              <?php else: ?><span class="w-active">ACTIVE</span><?php endif; ?>
            </td>
            <td>
              <a href="?view=<?= $w['user_id'] ?>" class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 8px;border-radius:6px;"><i class="fas fa-eye me-1"></i>View</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm mb-0 justify-content-center">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?q=<?= urlencode($search) ?>&tier=<?= urlencode($tier) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
