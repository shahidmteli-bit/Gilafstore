<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rewards_engine.php';
require_admin();

$pageTitle = 'Campaign Manager — Gilaf Rewards';
$adminPage = 'rewards_campaigns';
$db = get_db_connection();
$msg = $msgType = '';

// ── CRUD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $type        = $_POST['campaign_type'] ?? 'flat_bonus';
        $rewardVal   = (float)($_POST['reward_value'] ?? 0);
        $mult        = max(1.0, (float)($_POST['multiplier'] ?? 1));
        $minOrder    = (float)($_POST['min_order_value'] ?? 0);
        $maxPerUser  = (float)($_POST['max_reward_per_user'] ?? 0);
        $startAt     = trim($_POST['start_at'] ?? '') ?: null;
        $endAt       = trim($_POST['end_at'] ?? '') ?: null;
        $active      = isset($_POST['is_active']) ? 1 : 0;
        $catIds      = trim($_POST['target_category_ids'] ?? '');
        $prodIds     = trim($_POST['target_product_ids'] ?? '');
        if ($id > 0) {
            $db->prepare("UPDATE rewards_campaigns SET name=?,description=?,campaign_type=?,reward_value=?,multiplier=?,min_order_value=?,max_reward_per_user=?,start_at=?,end_at=?,is_active=?,target_category_ids=?,target_product_ids=? WHERE id=?")
               ->execute([$name,$desc,$type,$rewardVal,$mult,$minOrder,$maxPerUser,$startAt,$endAt,$active,$catIds,$prodIds,$id]);
            $msg = 'Campaign updated.';
        } else {
            $db->prepare("INSERT INTO rewards_campaigns (name,description,campaign_type,reward_value,multiplier,min_order_value,max_reward_per_user,start_at,end_at,is_active,target_category_ids,target_product_ids) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$desc,$type,$rewardVal,$mult,$minOrder,$maxPerUser,$startAt,$endAt,$active,$catIds,$prodIds]);
            $msg = 'Campaign created.';
        }
        $msgType = 'success';
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM rewards_campaigns WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Campaign deleted.'; $msgType = 'warning';
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE rewards_campaigns SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Status toggled.'; $msgType = 'success';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editCampaign = null;
if ($editId > 0) {
    $st = $db->prepare("SELECT * FROM rewards_campaigns WHERE id=? LIMIT 1");
    $st->execute([$editId]); $editCampaign = $st->fetch();
}

try { $campaigns = $db->query("SELECT * FROM rewards_campaigns ORDER BY created_at DESC")->fetchAll(); }
catch (Exception $e) { $campaigns = []; }

include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.rw-card { background:#fff; border-radius:14px; box-shadow:0 2px 14px rgba(0,0,0,.07); }
.camp-type { padding:2px 10px; border-radius:99px; font-size:.72rem; font-weight:600; }
.camp-type-flat_bonus   { background:#dcfce7; color:#166534; }
.camp-type-multiplier   { background:#ede9fe; color:#5b21b6; }
.camp-type-festival     { background:#fef3c7; color:#92400e; }
.camp-type-category_boost{ background:#dbeafe; color:#1e40af; }
.camp-type-product_boost { background:#fee2e2; color:#991b1b; }
</style>
<div class="admin-content">
<div class="container-fluid py-3 px-3">

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h5 class="fw-bold mb-0" style="color:#1e1b4b;"><i class="fas fa-rocket me-2" style="color:#7c3aed;"></i>Campaign Manager</h5>
    <div class="d-flex gap-2">
      <a href="#campaignForm" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-plus me-1"></i>New Campaign</a>
      <a href="<?= base_url('admin/rewards_dashboard.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2 px-3 mb-3" style="border-radius:10px;font-size:.87rem;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- Campaign Form -->
  <div class="rw-card p-4 mb-4" id="campaignForm">
    <div class="fw-bold mb-3" style="color:#1e1b4b;"><?= $editCampaign ? '✏️ Edit Campaign' : '➕ Create Campaign' ?></div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editId ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Campaign Name *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editCampaign['name'] ?? '') ?>" required class="form-control form-control-sm" style="border-radius:8px;" placeholder="e.g. Diwali Bonus Rewards">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Type</label>
          <select name="campaign_type" class="form-select form-select-sm" style="border-radius:8px;">
            <?php foreach (['flat_bonus'=>'Flat Bonus','multiplier'=>'Reward Multiplier','category_boost'=>'Category Boost','product_boost'=>'Product Boost','festival'=>'Festival Bonus'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($editCampaign['campaign_type']??'')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Reward Value (₹)</label>
          <input type="number" name="reward_value" value="<?= $editCampaign['reward_value'] ?? 0 ?>" step="0.01" min="0" class="form-control form-control-sm" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Multiplier (e.g. 2 = 2×)</label>
          <input type="number" name="multiplier" value="<?= $editCampaign['multiplier'] ?? 1 ?>" step="0.1" min="1" class="form-control form-control-sm" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Min Order Value (₹)</label>
          <input type="number" name="min_order_value" value="<?= $editCampaign['min_order_value'] ?? 0 ?>" step="0.01" min="0" class="form-control form-control-sm" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Max Reward Per User (₹, 0 = unlimited)</label>
          <input type="number" name="max_reward_per_user" value="<?= $editCampaign['max_reward_per_user'] ?? 0 ?>" step="0.01" min="0" class="form-control form-control-sm" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Active</label>
          <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="is_active" <?= ($editCampaign['is_active'] ?? 1) ? 'checked' : '' ?>></div>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Start Date</label>
          <input type="datetime-local" name="start_at" value="<?= $editCampaign['start_at'] ?? '' ?>" class="form-control form-control-sm" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">End Date</label>
          <input type="datetime-local" name="end_at" value="<?= $editCampaign['end_at'] ?? '' ?>" class="form-control form-control-sm" style="border-radius:8px;">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Target Category IDs (comma-sep, optional)</label>
          <input type="text" name="target_category_ids" value="<?= htmlspecialchars($editCampaign['target_category_ids'] ?? '') ?>" class="form-control form-control-sm" style="border-radius:8px;" placeholder="1,2,3">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Target Product IDs (comma-sep, optional)</label>
          <input type="text" name="target_product_ids" value="<?= htmlspecialchars($editCampaign['target_product_ids'] ?? '') ?>" class="form-control form-control-sm" style="border-radius:8px;" placeholder="5,12,8">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea name="description" rows="2" class="form-control form-control-sm" style="border-radius:8px;" placeholder="Internal description..."><?= htmlspecialchars($editCampaign['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-save me-1"></i><?= $editCampaign ? 'Update' : 'Create' ?> Campaign</button>
          <?php if ($editCampaign): ?><a href="<?= base_url('admin/rewards_campaigns.php') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Cancel</a><?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- Campaigns List -->
  <div class="rw-card p-3">
    <div class="fw-bold mb-3" style="color:#1e1b4b;font-size:.95rem;">All Campaigns (<?= count($campaigns) ?>)</div>
    <?php if (empty($campaigns)): ?>
      <div class="text-center text-muted py-5"><i class="fas fa-rocket fa-3x mb-3 d-block" style="color:#e5e7eb;"></i>No campaigns yet</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" style="font-size:.83rem;">
        <thead class="table-light"><tr><th>Name</th><th>Type</th><th>Value</th><th>Multiplier</th><th>Period</th><th>Usage</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($campaigns as $c): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
            <td><span class="camp-type camp-type-<?= $c['campaign_type'] ?>"><?= str_replace('_',' ',ucfirst($c['campaign_type'])) ?></span></td>
            <td>₹<?= number_format((float)$c['reward_value'],0) ?></td>
            <td><?= $c['multiplier'] ?>×</td>
            <td class="text-muted" style="font-size:.77rem;">
              <?= $c['start_at'] ? date('M d',strtotime($c['start_at'])) : '—' ?> → <?= $c['end_at'] ? date('M d',strtotime($c['end_at'])) : '∞' ?>
            </td>
            <td><?= number_format((int)$c['usage_count']) ?></td>
            <td>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-xs p-0 border-0 bg-transparent" type="submit" title="Toggle">
                  <span class="badge <?= $c['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span>
                </button>
              </form>
            </td>
            <td>
              <a href="?edit=<?= $c['id'] ?>#campaignForm" class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:2px 8px;border-radius:6px;"><i class="fas fa-edit"></i></a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 8px;border-radius:6px;"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
