<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards_engine.php';

require_login();

$pageTitle = 'My Rewards Wallet — Gilaf Store';
$activePage = 'wallet';

$userId = (int)$_SESSION['user']['id'];
$wallet  = rw_get_wallet($userId);
$bal     = (float)($wallet['balance'] ?? 0);
$locked  = (float)($wallet['locked_balance'] ?? 0);
$earned  = (float)($wallet['lifetime_earned'] ?? 0);
$redeem  = (float)($wallet['lifetime_redeemed'] ?? 0);
$tier    = $wallet['tier'] ?? 'Silver';
$refCode = $wallet['referral_code'] ?? '';

$tierColors = ['Silver'=>'#9ca3af','Gold'=>'#f59e0b','Platinum'=>'#8b5cf6'];
$tierIcons  = ['Silver'=>'🥈','Gold'=>'🥇','Platinum'=>'💎'];
$tierColor  = $tierColors[$tier] ?? '#9ca3af';
$tierIcon   = $tierIcons[$tier]  ?? '🥈';

// Recent transactions
$db   = get_db_connection();
$stTx = $db->prepare("SELECT type,amount,balance_after,source,description,created_at FROM rewards_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$stTx->execute([$userId]);
$txns = $stTx->fetchAll();

// Expiring soon — returns a float (sum)
$expiryDays         = (int)rw_get_rule('reward_expiry_days', 90);
$expiringThisMonth  = rw_get_expiring_soon($userId, 15);

// Locked rewards
$lockedItems = [];
try {
    $stLocked = $db->prepare("SELECT * FROM rewards_locked_rewards WHERE user_id=? AND is_unlocked=0 ORDER BY created_at DESC LIMIT 10");
    $stLocked->execute([$userId]);
    $lockedItems = $stLocked->fetchAll();
} catch (Exception $e) {}

// Next tier — use lifetime_earned as proxy for progress
$allTiers     = rw_get_all_tiers();
$nextTier     = null;
$tierProgress = 0;
foreach ($allTiers as $t) {
    if ((float)$t['min_spent'] > $earned) {
        $nextTier = $t; break;
    }
}
if ($nextTier) {
    $minNext      = (float)$nextTier['min_spent'];
    $tierProgress = $minNext > 0 ? min(100, round(($earned / $minNext) * 100)) : 0;
}

$spinEnabled = (bool)rw_get_rule('spin_wheel_enabled', 1);

include __DIR__ . '/../includes/new-header.php';
?>
<style>
.rw-wallet-wrap { max-width:640px; margin:0 auto; padding:20px 16px 80px; }
.rw-wallet-title { font-size:1.3rem; font-weight:800; color:#1e1b4b; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
.rw-stat-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:18px; }
.rw-stat-card { border-radius:16px; padding:18px 16px; color:#fff; text-align:center; }
.rw-stat-label { font-size:.72rem; opacity:.8; margin-bottom:6px; }
.rw-stat-val   { font-size:1.5rem; font-weight:800; line-height:1; }
.rw-stat-sub   { font-size:.68rem; opacity:.65; margin-top:4px; }
.rw-section    { background:#fff; border-radius:16px; border:1px solid #e5e7eb; padding:16px; margin-bottom:14px; }
.rw-section-title { font-size:.82rem; font-weight:700; color:#374151; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
.rw-tx-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #f3f4f6; }
.rw-tx-row:last-child { border-bottom:none; }
.rw-tx-desc { font-size:.8rem; font-weight:600; color:#1e1b4b; }
.rw-tx-date { font-size:.68rem; color:#9ca3af; margin-top:1px; }
.rw-tx-amt  { font-size:.9rem; font-weight:800; }
.rw-progress-bar { height:8px; border-radius:4px; background:#e5e7eb; overflow:hidden; margin:8px 0 4px; }
.rw-progress-fill { height:100%; background:linear-gradient(90deg,#7c3aed,#a855f7); border-radius:4px; transition:width .6s; }
.rw-action-btn { display:block; text-align:center; padding:13px; border-radius:12px; font-weight:700; font-size:.9rem; text-decoration:none; margin-bottom:10px; }
.rw-expiry-alert { background:#fef3c7; border:1px solid #fcd34d; border-radius:12px; padding:12px 14px; margin-bottom:14px; font-size:.8rem; color:#92400e; }
</style>

<div class="rw-wallet-wrap">
  <div class="rw-wallet-title">
    <i class="fas fa-gift" style="color:#7c3aed;"></i> Gilaf Rewards Wallet
  </div>

  <!-- Balance Stats -->
  <div class="rw-stat-grid">
    <div class="rw-stat-card" style="background:linear-gradient(135deg,#1e1b4b,#3730a3);grid-column:1/-1;">
      <div class="rw-stat-label">Available Balance</div>
      <div class="rw-stat-val">₹<?= number_format($bal, 2) ?></div>
      <div class="rw-stat-sub">Ready to use at checkout</div>
    </div>
    <?php if ($locked > 0): ?>
    <div class="rw-stat-card" style="background:linear-gradient(135deg,#78350f,#b45309);">
      <div class="rw-stat-label">🔒 Locked</div>
      <div class="rw-stat-val">₹<?= number_format($locked, 2) ?></div>
      <div class="rw-stat-sub">Unlocks on orders</div>
    </div>
    <?php endif; ?>
    <div class="rw-stat-card" style="background:linear-gradient(135deg,#14532d,#166534);">
      <div class="rw-stat-label">Total Earned</div>
      <div class="rw-stat-val">₹<?= number_format($earned, 2) ?></div>
      <div class="rw-stat-sub">Lifetime rewards</div>
    </div>
    <div class="rw-stat-card" style="background:linear-gradient(135deg,#4c1d95,#6d28d9);">
      <div class="rw-stat-label">Total Redeemed</div>
      <div class="rw-stat-val">₹<?= number_format($redeem, 2) ?></div>
      <div class="rw-stat-sub">Saved on orders</div>
    </div>
    <!-- Expiring This Month -->
    <div class="rw-stat-card" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);">
      <div class="rw-stat-label">⏰ Expiring (<?= $expiryDays ?>-day validity)</div>
      <div class="rw-stat-val">₹<?= number_format($expiringThisMonth, 2) ?></div>
      <div class="rw-stat-sub">Use within 15 days</div>
    </div>
  </div>

  <!-- Expiry Warning -->
  <?php if ($expiringThisMonth > 0): ?>
  <div class="rw-expiry-alert">
    <i class="fas fa-clock me-2"></i><strong>⚠️ Don't lose your rewards!</strong>
    ₹<?= number_format($expiringThisMonth, 2) ?> expires within 15 days. Rewards are valid for <?= $expiryDays ?> days from earning (admin-configured).
  </div>
  <?php endif; ?>

  <!-- VIP Tier -->
  <div class="rw-section">
    <div class="rw-section-title"><i class="fas fa-crown" style="color:#f59e0b;"></i> VIP Status</div>
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="font-size:2rem;"><?= $tierIcon ?></div>
      <div class="flex-grow-1">
        <div style="font-weight:800;font-size:.95rem;color:#1e1b4b;"><?= htmlspecialchars($tier) ?> Member</div>
        <?php if ($nextTier): ?>
        <div style="font-size:.75rem;color:#6b7280;margin-top:2px;">
          ₹<?= number_format(max(0,(float)$nextTier['min_spent'] - $earned),0) ?> more to <?= htmlspecialchars($nextTier['tier_name']) ?>
        </div>
        <div class="rw-progress-bar"><div class="rw-progress-fill" style="width:<?= $tierProgress ?>%;"></div></div>
        <div style="font-size:.7rem;color:#9ca3af;"><?= $tierProgress ?>% to <?= htmlspecialchars($nextTier['tier_name']) ?></div>
        <?php else: ?>
        <div style="font-size:.75rem;color:#7c3aed;margin-top:2px;">🏆 Highest tier achieved!</div>
        <?php endif; ?>
      </div>
      <div style="font-size:.75rem;font-weight:700;color:<?= $tierColor ?>;background:<?= $tierColor ?>18;padding:5px 12px;border-radius:99px;border:1px solid <?= $tierColor ?>40;">
        <?= htmlspecialchars($tier) ?>
      </div>
    </div>
  </div>

  <!-- Locked Rewards -->
  <?php if (!empty($lockedItems)): ?>
  <div class="rw-section">
    <div class="rw-section-title"><i class="fas fa-lock" style="color:#d97706;"></i> Locked Rewards</div>
    <?php foreach ($lockedItems as $li): ?>
    <div class="rw-tx-row">
      <div>
        <div class="rw-tx-desc">🔒 ₹<?= number_format((float)$li['amount'],2) ?></div>
        <div class="rw-tx-date"><?= htmlspecialchars($li['description'] ?? '') ?></div>
      </div>
      <div style="font-size:.75rem;color:#d97706;font-weight:600;">Locked</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Referral Code -->
  <?php if ($refCode): ?>
  <div class="rw-section" style="background:#f5f3ff;border-color:#c4b5fd;">
    <div class="rw-section-title"><i class="fas fa-users" style="color:#7c3aed;"></i> Your Referral Code</div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <code style="font-size:1.1rem;font-weight:800;color:#7c3aed;letter-spacing:.12em;background:#ede9fe;padding:8px 16px;border-radius:10px;"><?= htmlspecialchars($refCode) ?></code>
      <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars(addslashes(base_url('register.php?ref='.urlencode($refCode)))) ?>').then(()=>{this.textContent='✅ Copied!';setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy me-1\'></i>Copy Link',2000)})"
        style="background:#7c3aed;color:#fff;border:none;border-radius:10px;padding:9px 16px;font-size:.82rem;font-weight:700;cursor:pointer;">
        <i class="fas fa-copy me-1"></i>Copy Link
      </button>
    </div>
    <div style="font-size:.73rem;color:#6b7280;margin-top:8px;">
      Share and earn ₹<?= number_format(rw_get_rule('referral_referrer',150),0) ?> when a friend places their first order
    </div>
  </div>
  <?php endif; ?>

  <!-- Transaction History -->
  <div class="rw-section">
    <div class="rw-section-title"><i class="fas fa-history" style="color:#6b7280;"></i> Transaction History</div>
    <?php if (!empty($txns)):
      $txColors = ['credit'=>'#16a34a','debit'=>'#dc2626','lock'=>'#d97706','unlock'=>'#7c3aed','expire'=>'#6b7280','admin_credit'=>'#0891b2','admin_debit'=>'#dc2626'];
      $txSigns  = ['credit'=>'+','debit'=>'-','lock'=>'','unlock'=>'+','expire'=>'-','admin_credit'=>'+','admin_debit'=>'-'];
      foreach ($txns as $tx):
        $c = $txColors[$tx['type']] ?? '#374151';
        $s = $txSigns[$tx['type']]  ?? '';
    ?>
    <div class="rw-tx-row">
      <div>
        <div class="rw-tx-desc"><?= htmlspecialchars($tx['description'] ?: ucfirst(str_replace('_',' ',$tx['source'] ?: $tx['type']))) ?></div>
        <div class="rw-tx-date"><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></div>
      </div>
      <div class="rw-tx-amt" style="color:<?= $c ?>;"><?= $s ?>₹<?= number_format((float)$tx['amount'],2) ?></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="text-align:center;padding:24px 0;color:#9ca3af;font-size:.85rem;">
      <div style="font-size:2rem;margin-bottom:8px;">📭</div>
      No transactions yet. Start shopping to earn rewards!
    </div>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <a href="<?= base_url('cart.php') ?>" class="rw-action-btn" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;">
    <i class="fas fa-shopping-bag me-2"></i>Redeem Rewards at Cart
  </a>
  <?php if ($spinEnabled): ?>
  <a href="<?= base_url('user/spin-wheel.php') ?>" class="rw-action-btn" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
    🎡 Spin the Wheel &amp; Win More
  </a>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/new-footer.php'; ?>
