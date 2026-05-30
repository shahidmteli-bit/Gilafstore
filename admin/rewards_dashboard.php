<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards_engine.php';
require_admin();

$pageTitle = 'Rewards Dashboard — Gilaf';
$adminPage = 'rewards_dashboard';
$db = get_db_connection();

// ── Stats ─────────────────────────────────────────────────────────────────────
function rw_stat(PDO $db, string $sql, array $p = []): string {
    try {
        $st = $db->prepare($sql); $st->execute($p);
        return number_format((float)($st->fetchColumn() ?: 0), 2);
    } catch (Exception $e) { return '0.00'; }
}
function rw_count(PDO $db, string $sql, array $p = []): int {
    try {
        $st = $db->prepare($sql); $st->execute($p);
        return (int)($st->fetchColumn() ?: 0);
    } catch (Exception $e) { return 0; }
}

$totalIssued    = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE type='credit'");
$totalRedeemed  = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE type='debit' AND source='redemption'");
$activeWallets  = rw_count($db, "SELECT COUNT(*) FROM rewards_wallets WHERE is_disabled=0");
$lockedPending  = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_locked_rewards WHERE status='locked'");
$walletLiab     = rw_stat($db, "SELECT COALESCE(SUM(balance),0) FROM rewards_wallets WHERE is_disabled=0");
$rewardSales    = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE source='redemption' AND type='debit'");
$frozenWallets  = rw_count($db, "SELECT COUNT(*) FROM rewards_wallets WHERE is_frozen=1");
$totalReferrals = rw_count($db, "SELECT COUNT(*) FROM rewards_referrals WHERE status='rewarded'");
$silverCount    = rw_count($db, "SELECT COUNT(*) FROM rewards_wallets WHERE tier='Silver'");
$goldCount      = rw_count($db, "SELECT COUNT(*) FROM rewards_wallets WHERE tier='Gold'");
$platCount      = rw_count($db, "SELECT COUNT(*) FROM rewards_wallets WHERE tier='Platinum'");
$expiringSoon   = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE type='credit' AND expires_at IS NOT NULL AND expires_at > NOW() AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)");

// ── Chart: last 14 days daily issued/redeemed ─────────────────────────────────
$chartLabels = $chartIssued = $chartRedeemed = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($d));
    $chartIssued[]   = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE type='credit' AND DATE(created_at)=?", [$d]);
    $chartRedeemed[] = rw_stat($db, "SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE type='debit' AND source='redemption' AND DATE(created_at)=?", [$d]);
}

// ── Top reward users ──────────────────────────────────────────────────────────
try {
    $topUsers = $db->query("SELECT rw.user_id, u.name, u.email, rw.balance, rw.lifetime_earned, rw.tier
        FROM rewards_wallets rw LEFT JOIN users u ON u.id = rw.user_id
        ORDER BY rw.lifetime_earned DESC LIMIT 10")->fetchAll();
} catch (Exception $e) { $topUsers = []; }

// ── Recent transactions ────────────────────────────────────────────────────────
try {
    $recentTx = $db->query("SELECT rt.*, u.name, u.email FROM rewards_transactions rt
        LEFT JOIN users u ON u.id = rt.user_id
        ORDER BY rt.created_at DESC LIMIT 15")->fetchAll();
} catch (Exception $e) { $recentTx = []; }

include __DIR__ . '/../includes/admin_header.php';
?>
<style>
:root { --rw-primary:#7c3aed; --rw-accent:#f59e0b; --rw-green:#10b981; --rw-red:#ef4444; --rw-blue:#3b82f6; }
.rw-card { background:#fff; border-radius:16px; box-shadow:0 2px 16px rgba(0,0,0,.07); padding:24px; }
.rw-stat-card { border-radius:14px; padding:20px 22px; color:#fff; position:relative; overflow:hidden; }
.rw-stat-card .stat-icon { font-size:2.2rem; opacity:.25; position:absolute; right:18px; top:50%; transform:translateY(-50%); }
.rw-stat-card .stat-val { font-size:1.7rem; font-weight:800; line-height:1; }
.rw-stat-card .stat-lbl { font-size:.78rem; opacity:.85; margin-top:4px; }
.bg-rw-purple { background:linear-gradient(135deg,#7c3aed,#a855f7); }
.bg-rw-gold   { background:linear-gradient(135deg,#d97706,#f59e0b); }
.bg-rw-green  { background:linear-gradient(135deg,#059669,#10b981); }
.bg-rw-blue   { background:linear-gradient(135deg,#2563eb,#3b82f6); }
.bg-rw-red    { background:linear-gradient(135deg,#dc2626,#ef4444); }
.bg-rw-indigo { background:linear-gradient(135deg,#4338ca,#6366f1); }
.tier-badge-Silver   { background:#e5e7eb; color:#374151; }
.tier-badge-Gold     { background:#fef3c7; color:#92400e; }
.tier-badge-Platinum { background:#ede9fe; color:#5b21b6; }
.tier-badge { padding:2px 10px; border-radius:99px; font-size:.72rem; font-weight:600; }
.tx-type-credit { color:#10b981; font-weight:600; }
.tx-type-debit  { color:#ef4444; font-weight:600; }
.tx-type-expire { color:#9ca3af; }
.rw-section-title { font-size:1.05rem; font-weight:700; color:#1e1b4b; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
</style>

<div class="admin-content">
<div class="container-fluid py-3 px-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-bold mb-0" style="color:#1e1b4b;"><i class="fas fa-trophy me-2" style="color:#f59e0b;"></i>Gilaf Rewards Wallet — Dashboard</h4>
      <div class="text-muted small">Rewards Analytics &amp; Overview</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= base_url('admin/rewards_wallets.php') ?>" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-wallet me-1"></i>User Wallets</a>
      <a href="<?= base_url('admin/rewards_rules.php') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fas fa-cog me-1"></i>Rules</a>
      <a href="<?= base_url('admin/rewards_campaigns.php') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fas fa-rocket me-1"></i>Campaigns</a>
    </div>
  </div>

  <!-- Stat Cards Row 1 -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
      <div class="rw-stat-card bg-rw-purple">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-val">₹<?= $totalIssued ?></div>
        <div class="stat-lbl">Total Issued</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="rw-stat-card bg-rw-green">
        <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-val">₹<?= $totalRedeemed ?></div>
        <div class="stat-lbl">Total Redeemed</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="rw-stat-card bg-rw-blue">
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-val"><?= $activeWallets ?></div>
        <div class="stat-lbl">Active Wallets</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="rw-stat-card bg-rw-gold">
        <div class="stat-icon"><i class="fas fa-lock"></i></div>
        <div class="stat-val">₹<?= $lockedPending ?></div>
        <div class="stat-lbl">Locked Rewards</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="rw-stat-card bg-rw-red">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-val">₹<?= $walletLiab ?></div>
        <div class="stat-lbl">Wallet Liability</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="rw-stat-card bg-rw-indigo">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-val"><?= $totalReferrals ?></div>
        <div class="stat-lbl">Referrals Done</div>
      </div>
    </div>
  </div>

  <!-- Stat Cards Row 2 -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="rw-card d-flex align-items-center gap-3 py-3">
        <div style="width:44px;height:44px;border-radius:12px;background:#e5e7eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-medal" style="color:#9ca3af;font-size:1.2rem;"></i></div>
        <div><div class="fw-bold"><?= $silverCount ?></div><div class="text-muted small">Silver</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="rw-card d-flex align-items-center gap-3 py-3">
        <div style="width:44px;height:44px;border-radius:12px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-star" style="color:#d97706;font-size:1.2rem;"></i></div>
        <div><div class="fw-bold"><?= $goldCount ?></div><div class="text-muted small">Gold</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="rw-card d-flex align-items-center gap-3 py-3">
        <div style="width:44px;height:44px;border-radius:12px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-gem" style="color:#7c3aed;font-size:1.2rem;"></i></div>
        <div><div class="fw-bold"><?= $platCount ?></div><div class="text-muted small">Platinum</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="rw-card d-flex align-items-center gap-3 py-3">
        <div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-clock" style="color:#ef4444;font-size:1.2rem;"></i></div>
        <div><div class="fw-bold">₹<?= $expiringSoon ?></div><div class="text-muted small">Expiring in 7d</div></div>
      </div>
    </div>
  </div>

  <!-- Chart + Top Users -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="rw-card" style="height:340px;">
        <div class="rw-section-title"><i class="fas fa-chart-area" style="color:#7c3aed;"></i>Rewards Activity (Last 14 Days)</div>
        <canvas id="rwChart" height="260"></canvas>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="rw-card" style="height:340px;overflow-y:auto;">
        <div class="rw-section-title"><i class="fas fa-crown" style="color:#f59e0b;"></i>Top Reward Earners</div>
        <table class="table table-sm mb-0" style="font-size:.83rem;">
          <thead class="table-light"><tr><th>#</th><th>User</th><th>Tier</th><th>Lifetime</th><th>Balance</th></tr></thead>
          <tbody>
            <?php if (empty($topUsers)): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No data yet</td></tr>
            <?php else: foreach ($topUsers as $i => $u): ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td><div class="fw-semibold" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($u['name'] ?? 'User #'.$u['user_id']) ?></div><div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($u['email'] ?? '') ?></div></td>
                <td><span class="tier-badge tier-badge-<?= $u['tier'] ?>"><?= $u['tier'] ?></span></td>
                <td>₹<?= number_format((float)$u['lifetime_earned'], 0) ?></td>
                <td class="fw-bold" style="color:#7c3aed;">₹<?= number_format((float)$u['balance'], 0) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Transactions -->
  <div class="rw-card mb-4">
    <div class="rw-section-title"><i class="fas fa-history" style="color:#3b82f6;"></i>Recent Transactions
      <a href="<?= base_url('admin/rewards_wallets.php') ?>" class="ms-auto btn btn-sm btn-outline-secondary" style="font-size:.75rem;border-radius:8px;">View All</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" style="font-size:.83rem;">
        <thead class="table-light">
          <tr><th>User</th><th>Type</th><th>Source</th><th>Amount</th><th>Balance After</th><th>Description</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php if (empty($recentTx)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No transactions yet</td></tr>
          <?php else: foreach ($recentTx as $tx): ?>
            <tr>
              <td><span style="font-size:.8rem;"><?= htmlspecialchars($tx['name'] ?? 'User #'.$tx['user_id']) ?></span></td>
              <td><span class="tx-type-<?= $tx['type'] ?>"><?= strtoupper($tx['type']) ?></span></td>
              <td><span class="badge bg-light text-dark" style="font-size:.72rem;"><?= htmlspecialchars($tx['source']) ?></span></td>
              <td class="<?= $tx['type']==='credit'?'text-success':'text-danger' ?> fw-bold"><?= $tx['type']==='credit'?'+':'-' ?>₹<?= number_format((float)$tx['amount'], 2) ?></td>
              <td>₹<?= number_format((float)$tx['balance_after'], 2) ?></td>
              <td class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($tx['description'] ?? '') ?></td>
              <td class="text-muted"><?= date('M d, H:i', strtotime($tx['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('rwChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [
      {
        label: 'Issued (₹)',
        data: <?= json_encode(array_map('floatval', $chartIssued)) ?>,
        borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,.1)',
        borderWidth: 2.5, pointRadius: 3, fill: true, tension: 0.4
      },
      {
        label: 'Redeemed (₹)',
        data: <?= json_encode(array_map('floatval', $chartRedeemed)) ?>,
        borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.08)',
        borderWidth: 2.5, pointRadius: 3, fill: true, tension: 0.4
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { font: { size: 12 } } } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: { beginAtZero: true, ticks: { callback: v => '₹'+v, font: { size: 11 } } }
    }
  }
});
</script>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
