<?php
/**
 * Gilaf Rewards Widget + Popup Engine
 * Include once in new-footer.php or new-header.php
 * Outputs: floating launcher widget, popup campaigns, earn badge data
 */
if (!defined('REWARDS_WIDGET_LOADED')) {
    define('REWARDS_WIDGET_LOADED', true);

    require_once __DIR__ . '/rewards_engine.php';

    $rw_userId   = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
    $rw_isGuest  = $rw_userId === 0;
    $rw_wallet   = [];
    $rw_balance  = 0;
    $rw_locked   = 0;
    $rw_tier     = 'Silver';
    $rw_expiring = 0;
    $rw_unread   = 0;
    $rw_lockedItems = [];
    $rw_widgetEnabled = (bool)rw_get_rule('widget_enabled', 1);

    if (!$rw_isGuest && $rw_widgetEnabled) {
        $rw_wallet   = rw_get_wallet($rw_userId);
        $rw_balance  = (float)($rw_wallet['balance'] ?? 0);
        $rw_locked   = (float)($rw_wallet['locked_balance'] ?? 0);
        $rw_tier     = $rw_wallet['tier'] ?? 'Silver';
        $rw_expiring = rw_get_expiring_soon($rw_userId, 7);
        $rw_unread   = rw_unread_notifications($rw_userId);

        // Locked items
        $db = get_db_connection();
        try {
            $st = $db->prepare("SELECT * FROM rewards_locked_rewards WHERE user_id = ? AND status = 'locked' ORDER BY created_at ASC LIMIT 3");
            $st->execute([$rw_userId]);
            $rw_lockedItems = $st->fetchAll();
        } catch (Exception $e) { $rw_lockedItems = []; }
    }

    // Active popup for this session
    $rw_popup = null;
    if ($rw_widgetEnabled) {
        try {
            $db = get_db_connection();
            $targetCond = $rw_isGuest ? "(target='all' OR target='guests')" : "(target='all' OR target='logged_in')";
            $st = $db->query("SELECT * FROM rewards_popups WHERE is_active=1 AND $targetCond AND (start_at IS NULL OR start_at<=NOW()) AND (end_at IS NULL OR end_at>=NOW()) ORDER BY id ASC LIMIT 5");
            $rw_popups_raw = $st->fetchAll();
            // Pick first one not dismissed
            foreach ($rw_popups_raw as $pop) {
                $sessionKey = 'rw_popup_shown_' . $pop['id'];
                if ($pop['show_frequency'] === 'always') { $rw_popup = $pop; break; }
                if ($pop['show_frequency'] === 'per_session' && empty($_SESSION[$sessionKey])) { $rw_popup = $pop; break; }
                if ($pop['show_frequency'] === 'once' && empty($_SESSION[$sessionKey])) { $rw_popup = $pop; break; }
                if ($pop['show_frequency'] === 'daily') {
                    $dayKey = 'rw_popup_day_' . $pop['id'];
                    if (empty($_SESSION[$dayKey]) || $_SESSION[$dayKey] !== date('Y-m-d')) { $rw_popup = $pop; break; }
                }
            }
            if ($rw_popup) {
                $_SESSION['rw_popup_shown_' . $rw_popup['id']] = true;
                $_SESSION['rw_popup_day_' . $rw_popup['id']] = date('Y-m-d');
                // Track impression
                $db->prepare("UPDATE rewards_popups SET impressions = impressions + 1 WHERE id = ?")->execute([$rw_popup['id']]);
            }
        } catch (Exception $e) { $rw_popup = null; }
    }

    $rw_tierData = rw_get_tier($rw_tier);
    $rw_allTiers = rw_get_all_tiers();

    // Progress to next tier
    $rw_nextTier = null;
    $rw_tierProgress = 0;
    if (!$rw_isGuest && !empty($rw_allTiers)) {
        foreach ($rw_allTiers as $t) {
            if ($t['tier_name'] !== $rw_tier && (float)$t['min_spent'] > (float)($rw_tierData['min_spent'] ?? 0)) {
                $rw_nextTier = $t;
                break;
            }
        }
        if ($rw_nextTier) {
            $db = get_db_connection();
            $st = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM rewards_transactions WHERE user_id=? AND source='purchase' AND type='credit'");
            $st->execute([$rw_userId]);
            $totalSpent = (float)($st->fetchColumn() ?: 0);
            $needed = max(1, (float)$rw_nextTier['min_spent'] - (float)($rw_tierData['min_spent'] ?? 0));
            $done   = max(0, $totalSpent - (float)($rw_tierData['min_spent'] ?? 0));
            $rw_tierProgress = min(100, (int)($done / $needed * 100));
        }
    }

    $rw_spinEnabled = (bool)rw_get_rule('spin_wheel_enabled', 0);
    $rw_referralCode = $rw_wallet['referral_code'] ?? '';
}
?>

<?php if ($rw_widgetEnabled): ?>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- GILAF REWARDS WIDGET CSS                                                  -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<style>
/* Floating launcher */
.rw-launcher {
  position:fixed; bottom:96px; right:24px; z-index:9999;
  width:56px; height:56px; border-radius:50%;
  background:linear-gradient(135deg,#7c3aed,#a855f7);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; box-shadow:0 4px 20px rgba(124,58,237,.45);
  transition:transform .2s, box-shadow .2s;
  border:none; outline:none;
}
.rw-launcher:hover { transform:scale(1.1); box-shadow:0 6px 28px rgba(124,58,237,.55); }
.rw-launcher .rw-badge {
  position:absolute; top:-4px; right:-4px;
  background:#ef4444; color:#fff; font-size:.6rem; font-weight:700;
  border-radius:50%; width:18px; height:18px;
  display:flex; align-items:center; justify-content:center;
  border:2px solid #fff;
}
.rw-launcher i { color:#fff; font-size:1.3rem; }

/* Wallet panel */
.rw-panel {
  position:fixed; bottom:162px; right:24px; z-index:10000;
  width:340px; max-height:80vh; overflow-y:auto;
  background:#fff; border-radius:20px;
  box-shadow:0 8px 40px rgba(0,0,0,.18);
  display:none; flex-direction:column;
  animation:rwSlideIn .25s ease;
}
.rw-panel.open { display:flex; }
@keyframes rwSlideIn { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

.rw-panel-header {
  background:linear-gradient(135deg,#1e1b4b,#3730a3);
  color:#fff; padding:18px 18px 14px; border-radius:20px 20px 0 0;
  flex-shrink:0;
}
.rw-panel-header .rw-balance { font-size:2rem; font-weight:800; line-height:1; }
.rw-panel-header .rw-balance-lbl { font-size:.75rem; opacity:.75; margin-top:2px; }

.rw-tier-pill {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:99px;
  font-size:.72rem; font-weight:700;
  background:rgba(255,255,255,.15); color:#fff;
  margin-top:8px;
}

.rw-panel-tabs { display:flex; border-bottom:1px solid #f3f4f6; flex-shrink:0; }
.rw-tab { flex:1; padding:10px 4px; font-size:.72rem; font-weight:600; text-align:center;
  cursor:pointer; color:#6b7280; border:none; background:#fff;
  border-bottom:2px solid transparent; transition:all .2s; }
.rw-tab.active { color:#7c3aed; border-bottom-color:#7c3aed; }

.rw-panel-body { padding:14px; flex:1; }

.rw-earn-item { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid #f3f4f6; font-size:.83rem; }
.rw-earn-item:last-child { border-bottom:none; }
.rw-earn-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.9rem; }
.rw-earn-val { margin-left:auto; font-weight:700; color:#7c3aed; }

.rw-locked-item { background:#fef3c7; border-radius:10px; padding:10px 12px; margin-bottom:8px; font-size:.82rem; }
.rw-locked-item .lock-icon { color:#d97706; }

.rw-progress-bar { height:8px; border-radius:4px; background:#e5e7eb; overflow:hidden; margin:6px 0; }
.rw-progress-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#7c3aed,#a855f7); transition:width .6s; }

.rw-ref-box { background:linear-gradient(135deg,#ede9fe,#f5f3ff); border-radius:12px; padding:14px; text-align:center; }
.rw-ref-code { font-size:1.2rem; font-weight:800; letter-spacing:.1em; color:#7c3aed; font-family:monospace; }
.rw-copy-btn { background:#7c3aed; color:#fff; border:none; border-radius:8px; padding:6px 16px; font-size:.8rem; font-weight:600; cursor:pointer; margin-top:8px; }

/* Redeem banner (in cart/checkout context) */
.rw-redeem-banner {
  background:linear-gradient(135deg,#f5f3ff,#ede9fe);
  border:1.5px solid #c4b5fd; border-radius:14px;
  padding:14px 16px; margin:12px 0;
}

/* Popup */
.rw-popup-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999;
  display:flex; align-items:center; justify-content:center;
  animation:rwFadeIn .3s ease;
}
@keyframes rwFadeIn { from{opacity:0} to{opacity:1} }
.rw-popup-box {
  background:linear-gradient(135deg,#1e1b4b,#3730a3);
  color:#fff; border-radius:20px; padding:32px 28px 24px;
  max-width:360px; width:90%; position:relative;
  box-shadow:0 20px 60px rgba(0,0,0,.35);
  animation:rwPopupIn .3s cubic-bezier(.34,1.56,.64,1);
}
@keyframes rwPopupIn { from{opacity:0;transform:scale(.85)} to{opacity:1;transform:scale(1)} }
.rw-popup-close { position:absolute; top:14px; right:16px; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:50%; cursor:pointer; font-size:.9rem; display:flex; align-items:center; justify-content:center; }
.rw-popup-close:hover { background:rgba(255,255,255,.25); }
.rw-popup-offer { background:rgba(255,255,255,.15); border-radius:8px; padding:6px 14px; font-size:.85rem; margin-bottom:12px; display:inline-block; }
.rw-popup-title { font-size:1.25rem; font-weight:800; margin-bottom:8px; }
.rw-popup-body  { font-size:.87rem; opacity:.85; margin-bottom:20px; line-height:1.5; }
.rw-popup-cta   { background:#f59e0b; color:#1e1b4b; border:none; border-radius:10px; padding:11px 24px; font-weight:800; font-size:.95rem; cursor:pointer; width:100%; transition:transform .15s; }
.rw-popup-cta:hover { transform:scale(1.02); }
.rw-popup-skip  { text-align:center; margin-top:10px; font-size:.78rem; opacity:.6; cursor:pointer; }
.rw-popup-skip:hover { opacity:.9; }

/* Confetti */
.rw-confetti { position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:99998; }

/* Expiry warning */
.rw-expiry-warn { background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:8px 12px; font-size:.78rem; color:#991b1b; margin-bottom:8px; }

@media (max-width:480px) {
  .rw-panel { width:calc(100vw - 32px); right:16px; }
  .rw-launcher { bottom:88px; right:16px; }
}
</style>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- FLOATING LAUNCHER                                                         -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<button class="rw-launcher" id="rwLauncher" aria-label="Gilaf Rewards Wallet" title="Gilaf Rewards Wallet">
  <i class="fas fa-gift"></i>
  <?php if (!$rw_isGuest && $rw_unread > 0): ?>
    <span class="rw-badge"><?= min(9, $rw_unread) ?></span>
  <?php endif; ?>
</button>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- WALLET PANEL                                                              -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="rw-panel" id="rwPanel">

  <?php if ($rw_isGuest): ?>
  <!-- Guest view -->
  <div class="rw-panel-header text-center">
    <div style="font-size:2.5rem;margin-bottom:8px;">🎁</div>
    <div style="font-size:1.1rem;font-weight:800;margin-bottom:4px;">Get ₹1000 Rewards FREE</div>
    <div style="font-size:.8rem;opacity:.75;">Create an account to start earning cashback</div>
  </div>
  <div class="rw-panel-body">
    <?php $signupReward = rw_get_rule('signup_reward', 100); $locked1 = rw_get_rule('signup_locked_1', 300); $locked2 = rw_get_rule('signup_locked_2', 600); ?>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#dcfce7;">✅</div>
      <div><div class="fw-semibold" style="font-size:.83rem;">Instant on Signup</div><div style="font-size:.72rem;color:#6b7280;">Use immediately</div></div>
      <div class="rw-earn-val">₹<?= number_format($signupReward, 0) ?></div>
    </div>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#fef3c7;">🔓</div>
      <div><div class="fw-semibold" style="font-size:.83rem;">After First Order</div><div style="font-size:.72rem;color:#6b7280;">Unlocks automatically</div></div>
      <div class="rw-earn-val">₹<?= number_format($locked1, 0) ?></div>
    </div>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#ede9fe;">🏆</div>
      <div><div class="fw-semibold" style="font-size:.83rem;">Purchase Milestone</div><div style="font-size:.72rem;color:#6b7280;">Unlocks progressively</div></div>
      <div class="rw-earn-val">₹<?= number_format($locked2, 0) ?></div>
    </div>
    <a href="<?= base_url('register.php') ?>" style="display:block;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;text-align:center;padding:12px;border-radius:12px;font-weight:700;text-decoration:none;margin-top:12px;">
      🎁 Claim My ₹<?= number_format($signupReward + $locked1 + $locked2, 0) ?> Rewards
    </a>
    <div style="text-align:center;font-size:.75rem;color:#9ca3af;margin-top:8px;">
      Already have an account? <a href="<?= base_url('user/login.php'); ?>" style="color:#7c3aed;">Sign in</a>
    </div>
  </div>

  <?php else: ?>
  <!-- Logged in view -->
  <div class="rw-panel-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="rw-balance-lbl">Gilaf Rewards Balance</div>
        <div class="rw-balance">₹<?= number_format($rw_balance, 2) ?></div>
        <?php if ($rw_locked > 0): ?>
          <div style="font-size:.75rem;opacity:.7;margin-top:3px;">+ ₹<?= number_format($rw_locked, 0) ?> locked rewards waiting</div>
        <?php endif; ?>
      </div>
      <button onclick="document.getElementById('rwPanel').classList.remove('open')" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:50%;width:28px;height:28px;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times" style="font-size:.8rem;"></i></button>
    </div>
    <?php $tierColors = ['Silver'=>'#9ca3af','Gold'=>'#f59e0b','Platinum'=>'#8b5cf6']; ?>
    <div class="rw-tier-pill">
      <i class="fas <?= $rw_tierData['icon'] ?? 'fa-medal' ?>" style="color:<?= $tierColors[$rw_tier] ?? '#9ca3af' ?>;"></i>
      <?= htmlspecialchars($rw_tier) ?> Member
    </div>
    <?php if ($rw_expiring > 0): ?>
      <div style="background:rgba(239,68,68,.2);border-radius:8px;padding:5px 10px;font-size:.73rem;margin-top:8px;">
        ⚠️ ₹<?= number_format($rw_expiring, 0) ?> expiring in 7 days — use soon!
      </div>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="rw-panel-tabs">
    <button class="rw-tab active" onclick="rwTab(this,'earn')"><i class="fas fa-coins me-1"></i>Earn</button>
    <button class="rw-tab" onclick="rwTab(this,'redeem')"><i class="fas fa-shopping-bag me-1"></i>Redeem</button>
    <button class="rw-tab" onclick="rwTab(this,'vip')"><i class="fas fa-crown me-1"></i>VIP</button>
    <button class="rw-tab" onclick="rwTab(this,'refer')"><i class="fas fa-users me-1"></i>Refer</button>
    <button class="rw-tab" onclick="rwTab(this,'history')"><i class="fas fa-history me-1"></i>History</button>
  </div>

  <!-- Earn tab -->
  <div class="rw-panel-body" id="rwTabEarn">
    <div style="font-size:.75rem;color:#6b7280;margin-bottom:8px;">How to earn more rewards:</div>
    <?php
    $earnItems = [
        ['🛒','Shopping Cashback','₹'.rw_get_rule('purchase_per_100',5).' per ₹100 spent','#dcfce7'],
        ['👥','Refer a Friend','₹'.number_format(rw_get_rule('referral_referrer',150),0).' per referral','#dbeafe'],
        ['⭐','Write a Review','₹'.number_format(rw_get_rule('review_reward',25),0).' per review','#fef3c7'],
        ['🎂','Birthday Reward','₹'.number_format(rw_get_rule('birthday_reward',200),0).' on your birthday','#fce7f3'],
    ];
    foreach ($earnItems as [$emoji, $label, $val, $bg]):
    ?>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:<?= $bg ?>;"><?= $emoji ?></div>
      <div><div class="fw-semibold" style="font-size:.83rem;"><?= $label ?></div></div>
      <div class="rw-earn-val"><?= $val ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($rw_lockedItems)): ?>
    <div style="font-size:.75rem;font-weight:600;color:#f59e0b;margin:10px 0 6px;"><i class="fas fa-lock me-1"></i>Your Locked Rewards</div>
    <?php foreach ($rw_lockedItems as $li): ?>
    <div class="rw-locked-item">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold">🔒 ₹<?= number_format((float)$li['amount'], 0) ?></span>
        <span style="font-size:.72rem;color:#92400e;"><?= htmlspecialchars($li['description'] ?? '') ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($rw_spinEnabled): ?>
    <a href="<?= base_url('user/spin-wheel.php') ?>" style="display:block;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-align:center;padding:10px;border-radius:10px;font-weight:700;text-decoration:none;margin-top:10px;font-size:.87rem;">
      🎡 Spin & Win Rewards
    </a>
    <?php endif; ?>
  </div>

  <!-- Redeem tab -->
  <div class="rw-panel-body" id="rwTabRedeem" style="display:none;">
    <?php $minCart = rw_get_rule('min_cart_redeem', 200); $maxPct = rw_get_rule('max_redeem_pct', 10); ?>
    <div style="font-size:.83rem;color:#374151;margin-bottom:12px;">
      <strong>Your balance:</strong> <span style="color:#7c3aed;font-weight:700;">₹<?= number_format($rw_balance, 2) ?></span>
    </div>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#f3f4f6;">🛒</div>
      <div><div style="font-size:.8rem;">Minimum cart value</div></div>
      <div class="rw-earn-val" style="color:#374151;">₹<?= number_format($minCart, 0) ?></div>
    </div>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#f3f4f6;">📊</div>
      <div><div style="font-size:.8rem;">Max redeemable per order</div></div>
      <div class="rw-earn-val" style="color:#374151;"><?= $maxPct ?>% of cart</div>
    </div>
    <?php if ($rw_tier === 'Gold'): ?>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#fef3c7;">⭐</div>
      <div><div style="font-size:.8rem;">Gold tier bonus redeem</div></div>
      <div class="rw-earn-val" style="color:#d97706;">+<?= (int)($rw_tierData['redeem_bonus_pct'] ?? 0) ?>%</div>
    </div>
    <?php elseif ($rw_tier === 'Platinum'): ?>
    <div class="rw-earn-item">
      <div class="rw-earn-icon" style="background:#ede9fe;">💎</div>
      <div><div style="font-size:.8rem;">Platinum tier bonus redeem</div></div>
      <div class="rw-earn-val" style="color:#7c3aed;">+<?= (int)($rw_tierData['redeem_bonus_pct'] ?? 0) ?>%</div>
    </div>
    <?php endif; ?>
    <a href="<?= base_url('cart.php') ?>" style="display:block;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;text-align:center;padding:10px;border-radius:10px;font-weight:700;text-decoration:none;margin-top:12px;font-size:.87rem;">
      🛒 Go to Cart &amp; Redeem
    </a>
    <div style="font-size:.72rem;color:#9ca3af;margin-top:8px;text-align:center;">Rewards expire after <?= (int)rw_get_rule('reward_expiry_days', 90) ?> days of earning</div>
  </div>

  <!-- VIP tab -->
  <div class="rw-panel-body" id="rwTabVip" style="display:none;">
    <?php $tierIconMap = ['Silver'=>'🥈','Gold'=>'🥇','Platinum'=>'💎']; ?>
    <div style="text-align:center;margin-bottom:12px;">
      <div style="font-size:2rem;"><?= $tierIconMap[$rw_tier] ?? '🥈' ?></div>
      <div style="font-weight:800;font-size:1.1rem;color:#1e1b4b;"><?= htmlspecialchars($rw_tier) ?> Member</div>
      <?php if ($rw_tierData): ?><div style="font-size:.78rem;color:#6b7280;margin-top:2px;"><?= htmlspecialchars($rw_tierData['benefits'] ?? '') ?></div><?php endif; ?>
    </div>
    <?php if ($rw_nextTier): ?>
    <div style="font-size:.8rem;color:#374151;margin-bottom:4px;">Progress to <strong><?= htmlspecialchars($rw_nextTier['tier_name']) ?></strong>:</div>
    <div class="rw-progress-bar"><div class="rw-progress-fill" style="width:<?= $rw_tierProgress ?>%;"></div></div>
    <div style="font-size:.75rem;color:#6b7280;margin-bottom:12px;"><?= $rw_tierProgress ?>% — ₹<?= number_format((float)$rw_nextTier['min_spent'] - max(0, (float)($rw_tierData['min_spent'] ?? 0)), 0) ?> total spend to unlock</div>
    <?php endif; ?>
    <?php foreach ($rw_allTiers as $t):
      $isCurrentTier = $t['tier_name'] === $rw_tier;
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;margin-bottom:6px;background:<?= $isCurrentTier?'#f5f3ff':'#f9fafb' ?>;border:<?= $isCurrentTier?'2px solid #7c3aed':'1px solid #e5e7eb' ?>;">
      <span style="font-size:1.1rem;"><?= $tierIconMap[$t['tier_name']] ?? '🎖️' ?></span>
      <div class="flex-grow-1">
        <div style="font-weight:700;font-size:.85rem;"><?= htmlspecialchars($t['tier_name']) ?></div>
        <div style="font-size:.72rem;color:#6b7280;">Min ₹<?= number_format((float)$t['min_spent'],0) ?> spent · <?= $t['multiplier'] ?>× rewards</div>
      </div>
      <?php if ($isCurrentTier): ?><span style="background:#7c3aed;color:#fff;font-size:.68rem;padding:2px 8px;border-radius:99px;font-weight:700;">Current</span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- History tab -->
  <div class="rw-panel-body" id="rwTabHistory" style="display:none;">
    <div id="rwHistoryLoading" style="text-align:center;padding:20px 0;color:#9ca3af;font-size:.82rem;"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
    <div id="rwHistoryList" style="display:none;"></div>
    <div id="rwHistoryEmpty" style="display:none;text-align:center;padding:20px 0;color:#9ca3af;font-size:.82rem;">
      <div style="font-size:1.8rem;margin-bottom:8px;">📭</div>
      No transactions yet. Start shopping to earn rewards!
    </div>
    <div id="rwHistoryError" style="display:none;text-align:center;padding:12px;color:#dc2626;font-size:.78rem;"></div>
    <div style="text-align:center;margin-top:10px;">
      <a href="<?= base_url('user/profile.php') ?>" style="font-size:.75rem;color:#7c3aed;text-decoration:underline;">View full wallet history →</a>
    </div>
  </div>

  <!-- Refer tab -->
  <div class="rw-panel-body" id="rwTabRefer" style="display:none;">
    <div class="rw-ref-box">
      <div style="font-size:.8rem;font-weight:600;color:#5b21b6;margin-bottom:4px;">Your Referral Code</div>
      <div class="rw-ref-code" id="rwRefCode"><?= htmlspecialchars($rw_referralCode ?: '--------') ?></div>
      <div style="font-size:.75rem;color:#6b7280;margin:6px 0;">Share and earn ₹<?= number_format(rw_get_rule('referral_referrer',150),0) ?> per referral</div>
      <?php $refLink = base_url('register.php?ref='.urlencode($rw_referralCode)); ?>
      <button class="rw-copy-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars(addslashes($refLink)) ?>').then(()=>{this.textContent='✅ Copied!';setTimeout(()=>this.textContent='📋 Copy Link',2000)});">📋 Copy Link</button>
    </div>
    <div style="margin-top:12px;font-size:.82rem;">
      <div class="rw-earn-item">
        <div class="rw-earn-icon" style="background:#dcfce7;">🎁</div>
        <div><div style="font-size:.82rem;">Your friend gets</div></div>
        <div class="rw-earn-val">₹<?= number_format(rw_get_rule('referral_referred',100),0) ?></div>
      </div>
      <div class="rw-earn-item">
        <div class="rw-earn-icon" style="background:#dbeafe;">💰</div>
        <div><div style="font-size:.82rem;">You earn after their order</div></div>
        <div class="rw-earn-val">₹<?= number_format(rw_get_rule('referral_referrer',150),0) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- POPUP CAMPAIGN                                                            -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<?php if ($rw_popup): ?>
<div class="rw-popup-overlay" id="rwPopupOverlay" style="display:none;">
  <div class="rw-popup-box">
    <button class="rw-popup-close" onclick="document.getElementById('rwPopupOverlay').style.display='none'"><i class="fas fa-times"></i></button>
    <?php if ($rw_popup['reward_offer']): ?>
      <div class="rw-popup-offer">🎁 <?= htmlspecialchars($rw_popup['reward_offer']) ?> Rewards</div>
    <?php endif; ?>
    <div class="rw-popup-title"><?= htmlspecialchars($rw_popup['title']) ?></div>
    <div class="rw-popup-body"><?= htmlspecialchars($rw_popup['body']) ?></div>
    <?php if ($rw_popup['cta_url']): ?>
      <a href="<?= htmlspecialchars($rw_popup['cta_url']) ?>" onclick="rwTrackPopupClick(<?= $rw_popup['id'] ?>)">
        <button class="rw-popup-cta"><?= htmlspecialchars($rw_popup['cta_text']) ?></button>
      </a>
    <?php else: ?>
      <button class="rw-popup-cta" onclick="document.getElementById('rwPopupOverlay').style.display='none'"><?= htmlspecialchars($rw_popup['cta_text']) ?></button>
    <?php endif; ?>
    <div class="rw-popup-skip" onclick="document.getElementById('rwPopupOverlay').style.display='none'">No thanks, I'll skip rewards</div>
  </div>
</div>
<script>
(function(){
  const delay = <?= (int)$rw_popup['delay_seconds'] ?> * 1000;
  const trigger = '<?= addslashes($rw_popup['trigger_type']) ?>';
  function showPopup() {
    const el = document.getElementById('rwPopupOverlay');
    if (el) el.style.display = 'flex';
  }
  if (trigger === 'entry') {
    setTimeout(showPopup, delay);
  } else if (trigger === 'exit') {
    document.addEventListener('mouseleave', function(e) {
      if (e.clientY < 20) { showPopup(); document.removeEventListener('mouseleave', arguments.callee); }
    });
  } else if (trigger === 'scroll') {
    window.addEventListener('scroll', function() {
      if ((window.scrollY / document.body.scrollHeight) > 0.5) {
        showPopup(); window.removeEventListener('scroll', arguments.callee);
      }
    });
  } else if (trigger === 'cart') {
    if (window.location.pathname.includes('cart')) setTimeout(showPopup, delay);
  } else if (trigger === 'returning') {
    if (document.referrer && document.referrer.includes(window.location.hostname)) setTimeout(showPopup, delay);
  }
})();
function rwTrackPopupClick(id) {
  fetch('<?= base_url('api/rewards_api.php') ?>', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'popup_click',popup_id:id})
  });
}
</script>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- WIDGET JS                                                                 -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<script>
(function(){
  const launcher = document.getElementById('rwLauncher');
  const panel    = document.getElementById('rwPanel');
  if (!launcher || !panel) return;

  launcher.addEventListener('click', function(e) {
    e.stopPropagation();
    panel.classList.toggle('open');
  });
  document.addEventListener('click', function(e) {
    if (!panel.contains(e.target) && !launcher.contains(e.target)) {
      panel.classList.remove('open');
    }
  });
})();

function rwTab(btn, tab) {
  document.querySelectorAll('.rw-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  const ids = ['rwTabEarn','rwTabRedeem','rwTabVip','rwTabRefer','rwTabHistory'];
  ids.forEach(id => { const el=document.getElementById(id); if(el) el.style.display='none'; });
  const tabMap = {earn:'rwTabEarn',redeem:'rwTabRedeem',vip:'rwTabVip',refer:'rwTabRefer',history:'rwTabHistory'};
  const show = document.getElementById(tabMap[tab]);
  if (show) show.style.display = 'block';
  if (tab === 'history') rwLoadHistory();
}

var rwHistoryLoaded = false;
async function rwLoadHistory() {
  if (rwHistoryLoaded) return;
  const loadEl  = document.getElementById('rwHistoryLoading');
  const listEl  = document.getElementById('rwHistoryList');
  const emptyEl = document.getElementById('rwHistoryEmpty');
  const errEl   = document.getElementById('rwHistoryError');
  try {
    const res  = await fetch('<?= base_url("api/rewards_api.php") ?>', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'get_history', limit:10})
    });
    const data = await res.json();
    if (loadEl) loadEl.style.display = 'none';
    if (data.success && data.transactions && data.transactions.length > 0) {
      const typeColors = {credit:'#16a34a',debit:'#dc2626',lock:'#d97706',unlock:'#7c3aed',expire:'#6b7280',admin_credit:'#0891b2',admin_debit:'#dc2626'};
      const typeIcons  = {credit:'↑',debit:'↓',lock:'🔒',unlock:'🔓',expire:'⏰',admin_credit:'↑',admin_debit:'↓'};
      let html = '';
      data.transactions.forEach(tx => {
        const color  = typeColors[tx.type] || '#374151';
        const icon   = typeIcons[tx.type]  || '•';
        const sign   = (tx.type==='credit'||tx.type==='unlock'||tx.type==='admin_credit') ? '+' : '-';
        const date   = new Date(tx.created_at).toLocaleDateString('en-IN',{day:'numeric',month:'short'});
        html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">`
          + `<div>`
          + `<div style="font-size:.8rem;font-weight:600;color:#1e1b4b;">${escHtml(tx.description||tx.source||tx.type)}</div>`
          + `<div style="font-size:.7rem;color:#9ca3af;">${date}</div>`
          + `</div>`
          + `<div style="font-size:.88rem;font-weight:800;color:${color};">${sign}₹${parseFloat(tx.amount).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}</div>`
          + `</div>`;
      });
      listEl.innerHTML = html;
      listEl.style.display = 'block';
      rwHistoryLoaded = true;
    } else if (data.success) {
      if (emptyEl) emptyEl.style.display = 'block';
      rwHistoryLoaded = true;
    } else {
      if (errEl) { errEl.textContent = data.error || 'Could not load history'; errEl.style.display = 'block'; }
    }
  } catch(e) {
    if (loadEl) loadEl.style.display = 'none';
    if (errEl) { errEl.textContent = 'Connection error'; errEl.style.display = 'block'; }
  }
}
function escHtml(s) { const d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }

// Expose redeem data for cart/checkout
window.gilafRewards = {
  balance: <?= $rw_balance ?>,
  tier: '<?= addslashes($rw_tier) ?>',
  isGuest: <?= $rw_isGuest ? 'true' : 'false' ?>,
};
</script>

<?php endif; // rw_widgetEnabled ?>
