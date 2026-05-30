<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Popup Campaigns — Gilaf Rewards';
$adminPage = 'rewards_popups';
$db = get_db_connection();
$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $body    = trim($_POST['body'] ?? '');
        $cta     = trim($_POST['cta_text'] ?? 'Claim Rewards');
        $ctaUrl  = trim($_POST['cta_url'] ?? '');
        $trigger = $_POST['trigger_type'] ?? 'entry';
        $showOn  = trim($_POST['show_on'] ?? 'all');
        $mobile  = isset($_POST['show_mobile']) ? 1 : 0;
        $desktop = isset($_POST['show_desktop']) ? 1 : 0;
        $delay   = max(0, (int)($_POST['delay_seconds'] ?? 3));
        $freq    = $_POST['show_frequency'] ?? 'once';
        $offer   = trim($_POST['reward_offer'] ?? '');
        $target  = $_POST['target'] ?? 'all';
        $startAt = trim($_POST['start_at'] ?? '') ?: null;
        $endAt   = trim($_POST['end_at'] ?? '') ?: null;
        $active  = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            $db->prepare("UPDATE rewards_popups SET title=?,body=?,cta_text=?,cta_url=?,trigger_type=?,show_on=?,show_mobile=?,show_desktop=?,delay_seconds=?,show_frequency=?,reward_offer=?,target=?,start_at=?,end_at=?,is_active=? WHERE id=?")
               ->execute([$title,$body,$cta,$ctaUrl,$trigger,$showOn,$mobile,$desktop,$delay,$freq,$offer,$target,$startAt,$endAt,$active,$id]);
            $msg = 'Popup updated.';
        } else {
            $db->prepare("INSERT INTO rewards_popups (title,body,cta_text,cta_url,trigger_type,show_on,show_mobile,show_desktop,delay_seconds,show_frequency,reward_offer,target,start_at,end_at,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$title,$body,$cta,$ctaUrl,$trigger,$showOn,$mobile,$desktop,$delay,$freq,$offer,$target,$startAt,$endAt,$active]);
            $msg = 'Popup created.';
        }
        $msgType = 'success';
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM rewards_popups WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Popup deleted.'; $msgType = 'warning';
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE rewards_popups SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Status toggled.'; $msgType = 'success';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editPopup = null;
if ($editId > 0) {
    $st = $db->prepare("SELECT * FROM rewards_popups WHERE id=? LIMIT 1");
    $st->execute([$editId]); $editPopup = $st->fetch();
}

try { $popups = $db->query("SELECT * FROM rewards_popups ORDER BY id DESC")->fetchAll(); }
catch (Exception $e) { $popups = []; }

$sampleMessages = [
    "🎁 Unlock ₹1000 Gilaf Rewards FREE",
    "Wait! Your rewards are still unclaimed 👀",
    "Login & unlock cashback rewards on this order.",
    "Create your account & start earning rewards instantly.",
    "🔒 ₹600 rewards waiting — complete your order to unlock!",
    "You're ₹50 away from unlocking your bonus rewards!",
];

include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.rw-card { background:#fff; border-radius:14px; box-shadow:0 2px 14px rgba(0,0,0,.07); }
.popup-preview { background:linear-gradient(135deg,#1e1b4b,#3730a3); color:#fff; border-radius:16px; padding:24px; max-width:340px; position:relative; }
.popup-preview .popup-offer { background:rgba(255,255,255,.15); border-radius:8px; padding:8px 14px; font-size:.85rem; margin-bottom:12px; display:inline-block; }
.popup-preview h3 { font-size:1.1rem; font-weight:800; margin-bottom:8px; }
.popup-preview p  { font-size:.82rem; opacity:.85; margin-bottom:16px; }
.popup-preview .popup-cta { background:#f59e0b; color:#1e1b4b; border:none; border-radius:8px; padding:8px 20px; font-weight:700; font-size:.85rem; cursor:pointer; }
.trigger-badge { padding:2px 9px; border-radius:99px; font-size:.72rem; font-weight:600; }
.trig-entry    { background:#dbeafe; color:#1e40af; }
.trig-exit     { background:#fee2e2; color:#991b1b; }
.trig-cart     { background:#dcfce7; color:#166534; }
.trig-scroll   { background:#fef3c7; color:#92400e; }
.trig-returning{ background:#ede9fe; color:#5b21b6; }
</style>
<div class="admin-content">
<div class="container-fluid py-3 px-3">

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-0" style="color:#1e1b4b;"><i class="fas fa-layer-group me-2" style="color:#7c3aed;"></i>Popup Campaign Manager</h5>
      <div class="text-muted small">Control every popup shown to users — no code changes needed</div>
    </div>
    <div class="d-flex gap-2">
      <a href="#popupForm" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-plus me-1"></i>New Popup</a>
      <a href="<?= base_url('admin/rewards_dashboard.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2 px-3 mb-3" style="border-radius:10px;font-size:.87rem;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="row g-4">
    <!-- Form -->
    <div class="col-lg-7">
      <div class="rw-card p-4" id="popupForm">
        <div class="fw-bold mb-3" style="color:#1e1b4b;"><?= $editPopup ? '✏️ Edit Popup' : '➕ Create Popup' ?></div>
        <form method="POST" id="popupFormEl">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editId ?>">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">Popup Title *</label>
              <input type="text" name="title" id="previewTitle" value="<?= htmlspecialchars($editPopup['title'] ?? '') ?>" required class="form-control form-control-sm" style="border-radius:8px;" placeholder="🎁 Unlock ₹1000 Gilaf Rewards FREE">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Body Message *</label>
              <textarea name="body" id="previewBody" rows="2" class="form-control form-control-sm" style="border-radius:8px;" placeholder="Create your account and start earning cashback instantly..."><?= htmlspecialchars($editPopup['body'] ?? '') ?></textarea>
              <div class="mt-1 d-flex flex-wrap gap-1">
                <?php foreach ($sampleMessages as $s): ?>
                  <button type="button" class="btn btn-xs" style="font-size:.7rem;padding:1px 7px;border-radius:6px;border:1px solid #e5e7eb;background:#f9fafb;" onclick="document.querySelector('[name=title]').value='<?= addslashes($s) ?>'; updatePreview();"><?= htmlspecialchars(mb_substr($s, 0, 30)) ?>…</button>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">CTA Button Text</label>
              <input type="text" name="cta_text" id="previewCta" value="<?= htmlspecialchars($editPopup['cta_text'] ?? 'Claim Rewards') ?>" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">CTA URL</label>
              <input type="text" name="cta_url" value="<?= htmlspecialchars($editPopup['cta_url'] ?? '/register.php') ?>" class="form-control form-control-sm" style="border-radius:8px;" placeholder="/register.php">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Trigger</label>
              <select name="trigger_type" class="form-select form-select-sm" style="border-radius:8px;">
                <?php foreach (['entry'=>'Page Entry','exit'=>'Exit Intent','cart'=>'Cart Page','scroll'=>'Scroll 50%','returning'=>'Returning Visitor'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= ($editPopup['trigger_type']??'entry')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Show To</label>
              <select name="target" class="form-select form-select-sm" style="border-radius:8px;">
                <?php foreach (['all'=>'All Visitors','guests'=>'Guests Only','logged_in'=>'Logged In','new'=>'New Users','returning'=>'Returning'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= ($editPopup['target']??'all')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Show Frequency</label>
              <select name="show_frequency" class="form-select form-select-sm" style="border-radius:8px;">
                <?php foreach (['once'=>'Once','per_session'=>'Per Session','daily'=>'Daily','always'=>'Always'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= ($editPopup['show_frequency']??'once')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Delay (sec)</label>
              <input type="number" name="delay_seconds" value="<?= $editPopup['delay_seconds'] ?? 3 ?>" min="0" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Reward Offer Text</label>
              <input type="text" name="reward_offer" id="previewOffer" value="<?= htmlspecialchars($editPopup['reward_offer'] ?? '₹1000') ?>" class="form-control form-control-sm" style="border-radius:8px;" placeholder="₹1000 Rewards">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Pages (comma-sep or 'all')</label>
              <input type="text" name="show_on" value="<?= htmlspecialchars($editPopup['show_on'] ?? 'all') ?>" class="form-control form-control-sm" style="border-radius:8px;" placeholder="all">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold d-block">Devices</label>
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="show_mobile" <?= ($editPopup['show_mobile']??1)?'checked':'' ?>> <label class="form-check-label small">Mobile</label></div>
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="show_desktop" <?= ($editPopup['show_desktop']??1)?'checked':'' ?>> <label class="form-check-label small">Desktop</label></div>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Start Date</label>
              <input type="datetime-local" name="start_at" value="<?= $editPopup['start_at'] ?? '' ?>" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">End Date</label>
              <input type="datetime-local" name="end_at" value="<?= $editPopup['end_at'] ?? '' ?>" class="form-control form-control-sm" style="border-radius:8px;">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold d-block">Active</label>
              <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="is_active" <?= ($editPopup['is_active']??1)?'checked':'' ?>></div>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-save me-1"></i><?= $editPopup ? 'Update' : 'Create' ?> Popup</button>
              <?php if ($editPopup): ?><a href="<?= base_url('admin/rewards_popups.php') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Cancel</a><?php endif; ?>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Live Preview -->
    <div class="col-lg-5">
      <div class="fw-semibold mb-2 text-muted small text-uppercase">Live Preview</div>
      <div class="popup-preview">
        <div class="popup-offer" id="prevOffer">₹1000 Rewards</div>
        <h3 id="prevTitle">🎁 Unlock ₹1000 Gilaf Rewards FREE</h3>
        <p id="prevBody">Create your account and start earning cashback rewards instantly.</p>
        <div class="d-flex gap-2 align-items-center">
          <button class="popup-cta" id="prevCta">Claim My Rewards</button>
          <span style="font-size:.75rem;opacity:.5;cursor:pointer;">✕ Close</span>
        </div>
      </div>

      <!-- All Popups List -->
      <div class="rw-card p-3 mt-4">
        <div class="fw-bold mb-2" style="font-size:.9rem;color:#1e1b4b;">All Popups (<?= count($popups) ?>)</div>
        <?php if (empty($popups)): ?>
          <div class="text-center text-muted small py-3">No popups yet</div>
        <?php else: foreach ($popups as $p): ?>
          <div class="d-flex align-items-start gap-2 py-2 border-bottom">
            <div class="flex-grow-1">
              <div class="fw-semibold" style="font-size:.83rem;"><?= htmlspecialchars($p['title']) ?></div>
              <div class="d-flex gap-1 mt-1 flex-wrap">
                <span class="trigger-badge trig-<?= $p['trigger_type'] ?>"><?= $p['trigger_type'] ?></span>
                <span class="badge bg-light text-dark" style="font-size:.68rem;"><?= $p['target'] ?></span>
                <?php if ($p['reward_offer']): ?><span class="badge" style="background:#fef3c7;color:#92400e;font-size:.68rem;"><?= htmlspecialchars($p['reward_offer']) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="d-flex flex-column gap-1 align-items-end">
              <form method="POST" class="d-inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-xs p-0 border-0 bg-transparent" type="submit"><span class="badge <?= $p['is_active']?'bg-success':'bg-secondary' ?>"><?= $p['is_active']?'On':'Off' ?></span></button>
              </form>
              <a href="?edit=<?= $p['id'] ?>#popupForm" class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:1px 7px;border-radius:5px;">Edit</a>
              <form method="POST" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:1px 7px;border-radius:5px;" type="submit">Del</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</div>
</div>
<script>
function updatePreview() {
    const t = document.getElementById('previewTitle');
    const b = document.getElementById('previewBody');
    const c = document.getElementById('previewCta');
    const o = document.getElementById('previewOffer');
    if (t) document.getElementById('prevTitle').textContent = t.value || '🎁 Popup Title';
    if (b) document.getElementById('prevBody').textContent = b.value || 'Your message here...';
    if (c) document.getElementById('prevCta').textContent = c.value || 'Claim Rewards';
    if (o) document.getElementById('prevOffer').textContent = o.value || 'Special Offer';
}
['previewTitle','previewBody','previewCta','previewOffer'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePreview);
});
updatePreview();
</script>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
