<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards_engine.php';
require_admin();

$pageTitle = 'Reward Rules — Gilaf';
$adminPage = 'rewards_rules';
$db = get_db_connection();

$msg = $msgType = '';

// ── Save rule ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_rule') {
    $id     = (int)$_POST['id'];
    // For boolean rules the hidden input always carries the correct 0/1 value
    $rawValues = $_POST['value'] ?? 0;
    $value  = is_array($rawValues) ? (float)end($rawValues) : (float)$rawValues;
    $value2 = (float)($_POST['value2'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    $db->prepare("UPDATE rewards_rules SET value=?, value2=?, is_active=? WHERE id=?")->execute([$value, $value2, $active, $id]);
    $msg = 'Rule updated.'; $msgType = 'success';
}

// ── Save tier ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_tier') {
    $id       = (int)$_POST['id'];
    $minSpent = (float)$_POST['min_spent'];
    $mult     = (float)$_POST['multiplier'];
    $bonus    = (float)$_POST['redeem_bonus_pct'];
    $benefits = trim($_POST['benefits'] ?? '');
    $active   = isset($_POST['is_active']) ? 1 : 0;
    $db->prepare("UPDATE rewards_tiers SET min_spent=?, multiplier=?, redeem_bonus_pct=?, benefits=?, is_active=? WHERE id=?")
       ->execute([$minSpent, $mult, $bonus, $benefits, $active, $id]);
    $msg = 'Tier updated.'; $msgType = 'success';
}

// ── Fetch all rules & tiers ───────────────────────────────────────────────────
try { $rules = $db->query("SELECT * FROM rewards_rules ORDER BY rule_group, id")->fetchAll(); } catch (Exception $e) { $rules = []; }
try { $tiers = $db->query("SELECT * FROM rewards_tiers ORDER BY sort_order")->fetchAll(); } catch (Exception $e) { $tiers = []; }

$ruleGroups = ['earning' => '💰 Earning Rules', 'locked' => '🔒 Locked Reward Config', 'redemption' => '🛒 Redemption Rules', 'display' => '👁️ Display Settings', 'referral' => '👥 Referral Rewards', 'gamification' => '🎡 Gamification'];

include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.rw-card { background:#fff; border-radius:14px; box-shadow:0 2px 14px rgba(0,0,0,.07); }
.rule-group-header { font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#7c3aed; padding:10px 16px 4px; background:#faf5ff; border-radius:10px 10px 0 0; border-bottom:1px solid #ede9fe; }
.rule-row { display:grid; grid-template-columns:1fr 110px 110px 80px 80px; gap:12px; align-items:center; padding:10px 16px; border-bottom:1px solid #f3f4f6; font-size:.85rem; }
.rule-row:last-child { border-bottom:none; }
.rule-row:hover { background:#fafafa; }
.rule-label { font-weight:600; color:#1e1b4b; }
.rule-desc { font-size:.73rem; color:#9ca3af; }
.tier-card { border-radius:12px; border:2px solid #e5e7eb; padding:20px; }
.tier-card.silver { border-color:#9ca3af; }
.tier-card.gold   { border-color:#f59e0b; }
.tier-card.plat   { border-color:#8b5cf6; }
.tier-icon { font-size:2rem; }
</style>

<div class="admin-content">
<div class="container-fluid py-3 px-3">

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-0" style="color:#1e1b4b;"><i class="fas fa-cog me-2" style="color:#7c3aed;"></i>Reward Rules & Configuration</h5>
      <div class="text-muted small">All values are dynamically configurable — no code changes needed</div>
    </div>
    <a href="<?= base_url('admin/rewards_dashboard.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> py-2 px-3 mb-3" style="border-radius:10px;font-size:.87rem;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- ── Rule Groups ──────────────────────────────────────────────────────── -->
  <?php foreach ($ruleGroups as $groupKey => $groupLabel):
    $groupRules = array_filter($rules, fn($r) => $r['rule_group'] === $groupKey);
    if (empty($groupRules)) continue;
  ?>
  <div class="rw-card mb-3 overflow-hidden">
    <div class="rule-group-header"><?= $groupLabel ?></div>
    <div class="rule-row" style="background:#f9fafb;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;padding:6px 16px;">
      <div>Rule</div><div>Value (₹ / %)</div><div>Value 2</div><div>Active</div><div>Save</div>
    </div>
    <?php
    // Rules where value itself is a 0/1 boolean flag (not a numeric amount)
    $boolRuleKeys = ['allow_wallet_on_cod','allow_wallet_with_coupon','spin_wheel_enabled','widget_enabled','product_page_enabled'];
    foreach ($groupRules as $rule):
    $isBool = in_array($rule['rule_key'], $boolRuleKeys);
    ?>
    <form method="POST">
      <input type="hidden" name="action" value="save_rule">
      <input type="hidden" name="id" value="<?= $rule['id'] ?>">
      <div class="rule-row">
        <div>
          <div class="rule-label"><?= htmlspecialchars($rule['rule_label']) ?></div>
          <div class="rule-desc"><?= htmlspecialchars($rule['description'] ?? '') ?></div>
        </div>
        <div>
          <?php if ($isBool): ?>
            <div class="d-flex align-items-center gap-2">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="value" value="1"
                       id="boolVal_<?= $rule['id'] ?>"
                       onchange="document.getElementById('boolValHidden_<?= $rule['id'] ?>').value = this.checked ? 1 : 0"
                       <?= ((float)$rule['value'] >= 1) ? 'checked' : '' ?> style="cursor:pointer;width:2.5em;height:1.3em;">
                <input type="hidden" name="value" id="boolValHidden_<?= $rule['id'] ?>" value="<?= ((float)$rule['value'] >= 1) ? 1 : 0 ?>">
              </div>
              <label for="boolVal_<?= $rule['id'] ?>" class="small mb-0" style="color:#374151;"><?= ((float)$rule['value'] >= 1) ? '<span style="color:#16a34a;font-weight:700;">ON</span>' : '<span style="color:#dc2626;font-weight:700;">OFF</span>' ?></label>
            </div>
          <?php else: ?>
            <input type="number" name="value" value="<?= htmlspecialchars($rule['value']) ?>" step="0.01" min="0"
                   class="form-control form-control-sm" style="border-radius:7px;"
                   <?= $rule['rule_key'] === 'max_redeem_fixed' ? 'placeholder="0 = no cap"' : '' ?>>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($groupKey === 'locked'): ?>
            <input type="number" name="value2" value="<?= htmlspecialchars($rule['value2']) ?>" step="0.01" min="0"
                   placeholder="Threshold ₹" class="form-control form-control-sm" style="border-radius:7px;" title="Unlock threshold">
          <?php else: ?>
            <input type="hidden" name="value2" value="<?= htmlspecialchars($rule['value2']) ?>">
            <span style="font-size:.72rem;color:#d1d5db;">—</span>
          <?php endif; ?>
        </div>
        <div class="text-center">
          <div class="form-check form-switch d-inline-flex">
            <input class="form-check-input" type="checkbox" name="is_active" <?= $rule['is_active'] ? 'checked' : '' ?> style="cursor:pointer;">
          </div>
        </div>
        <div>
          <button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:7px;padding:3px 12px;font-size:.78rem;"><i class="fas fa-save"></i></button>
        </div>
      </div>
    </form>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <!-- ── VIP Tiers ────────────────────────────────────────────────────────── -->
  <div class="rw-card p-4 mb-3">
    <div class="fw-bold mb-3" style="font-size:1rem;color:#1e1b4b;"><i class="fas fa-crown me-2" style="color:#f59e0b;"></i>VIP Tier Configuration</div>
    <div class="row g-3">
      <?php
      $tierStyles = ['Silver'=>['silver','fas fa-medal','#9ca3af'], 'Gold'=>['gold','fas fa-star','#f59e0b'], 'Platinum'=>['plat','fas fa-gem','#8b5cf6']];
      foreach ($tiers as $tier):
        [$cls, $icon, $color] = $tierStyles[$tier['tier_name']] ?? ['silver','fas fa-medal','#9ca3af'];
      ?>
      <div class="col-md-4">
        <form method="POST">
          <input type="hidden" name="action" value="save_tier">
          <input type="hidden" name="id" value="<?= $tier['id'] ?>">
          <div class="tier-card <?= $cls ?>">
            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="tier-icon"><i class="<?= $icon ?>" style="color:<?= $color ?>;"></i></span>
              <div><div class="fw-bold" style="font-size:1.05rem;"><?= htmlspecialchars($tier['tier_name']) ?></div>
              <div class="text-muted small">VIP Tier</div></div>
              <div class="ms-auto">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" <?= $tier['is_active']?'checked':'' ?>></div>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold text-muted mb-1">Min Lifetime Spend (₹) to Qualify</label>
              <input type="number" name="min_spent" value="<?= $tier['min_spent'] ?>" step="0.01" min="0" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold text-muted mb-1">Reward Multiplier (e.g. 1.5 = 1.5×)</label>
              <input type="number" name="multiplier" value="<?= $tier['multiplier'] ?>" step="0.1" min="1" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold text-muted mb-1">Max Redeem Bonus % (added on top of base %)</label>
              <input type="number" name="redeem_bonus_pct" value="<?= $tier['redeem_bonus_pct'] ?>" step="0.5" min="0" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold text-muted mb-1">Benefits (shown to user)</label>
              <textarea name="benefits" rows="2" class="form-control form-control-sm" style="border-radius:8px;"><?= htmlspecialchars($tier['benefits'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-sm w-100" style="background:<?= $color ?>;color:#fff;border-radius:8px;"><i class="fas fa-save me-1"></i>Save <?= $tier['tier_name'] ?></button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Quick Reference ─────────────────────────────────────────────────── -->
  <div class="rw-card p-4" style="background:linear-gradient(135deg,#faf5ff,#ede9fe);">
    <div class="fw-bold mb-2" style="color:#5b21b6;"><i class="fas fa-lightbulb me-2"></i>How the Reward System Works</div>
    <div class="row g-3 small text-muted">
      <div class="col-md-4">
        <strong style="color:#1e1b4b;">🎁 Signup (₹1000 Total):</strong>
        <ul class="mb-0 ps-3 mt-1">
          <li>₹100 instant usable</li>
          <li>₹300 unlocks after 1st order</li>
          <li>₹600 unlocks after spending threshold</li>
        </ul>
      </div>
      <div class="col-md-4">
        <strong style="color:#1e1b4b;">🛒 Purchase Cashback:</strong>
        <ul class="mb-0 ps-3 mt-1">
          <li>₹5 per ₹100 spent (configurable)</li>
          <li>Gold tier = 1.5× multiplier</li>
          <li>Platinum tier = 2× multiplier</li>
        </ul>
      </div>
      <div class="col-md-4">
        <strong style="color:#1e1b4b;">💱 Redemption Rules:</strong>
        <ul class="mb-0 ps-3 mt-1">
          <li>Max % cap (default 10%) of cart</li>
          <li>Optional fixed ₹ cap (e.g. ₹200 max)</li>
          <li>Toggle wallet on COD orders</li>
          <li>Toggle wallet + coupon combo</li>
          <li>Min cart ₹200 / expiry 90 days</li>
        </ul>
      </div>
    </div>
  </div>

</div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
