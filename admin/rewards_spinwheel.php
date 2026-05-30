<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Spin Wheel Manager — Gilaf Rewards';
$adminPage = 'rewards_spinwheel';
$db = get_db_connection();
$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_segment') {
        $id     = (int)($_POST['id'] ?? 0);
        $label  = trim($_POST['label'] ?? '');
        $type   = $_POST['reward_type'] ?? 'cashback';
        $val    = (float)($_POST['reward_value'] ?? 0);
        $prob   = max(1, (int)($_POST['probability'] ?? 10));
        $color  = trim($_POST['color'] ?? '#4ade80');
        $icon   = trim($_POST['icon'] ?? '🎁');
        $limit  = (int)($_POST['daily_limit'] ?? 100);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0) {
            $db->prepare("UPDATE rewards_spinwheel SET label=?,reward_type=?,reward_value=?,probability=?,color=?,icon=?,daily_limit=?,is_active=? WHERE id=?")
               ->execute([$label,$type,$val,$prob,$color,$icon,$limit,$active,$id]);
            $msg = 'Segment updated.';
        } else {
            $db->prepare("INSERT INTO rewards_spinwheel (label,reward_type,reward_value,probability,color,icon,daily_limit,is_active) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$label,$type,$val,$prob,$color,$icon,$limit,$active]);
            $msg = 'Segment added.';
        }
        $msgType = 'success';
    } elseif ($action === 'delete_segment') {
        $db->prepare("DELETE FROM rewards_spinwheel WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Segment deleted.'; $msgType = 'warning';
    } elseif ($action === 'toggle_segment') {
        $db->prepare("UPDATE rewards_spinwheel SET is_active = 1-is_active WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Toggled.'; $msgType = 'success';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editSeg = null;
if ($editId > 0) {
    $st = $db->prepare("SELECT * FROM rewards_spinwheel WHERE id=? LIMIT 1"); $st->execute([$editId]); $editSeg = $st->fetch();
}
try { $segments = $db->query("SELECT * FROM rewards_spinwheel ORDER BY id")->fetchAll(); } catch (Exception $e) { $segments = []; }
$totalProb = array_sum(array_column($segments, 'probability'));

include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.rw-card { background:#fff; border-radius:14px; box-shadow:0 2px 14px rgba(0,0,0,.07); }
.wheel-preview { width:260px; height:260px; border-radius:50%; position:relative; overflow:hidden; margin:0 auto; box-shadow:0 8px 32px rgba(0,0,0,.18); }
.prob-bar { height:8px; border-radius:4px; background:#e5e7eb; overflow:hidden; }
.prob-fill { height:100%; border-radius:4px; transition:width .3s; }
</style>
<div class="admin-content">
<div class="container-fluid py-3 px-3">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-0" style="color:#1e1b4b;"><i class="fas fa-dharmachakra me-2" style="color:#7c3aed;"></i>Spin Wheel Manager</h5>
      <div class="text-muted small">Configure reward segments and probabilities</div>
    </div>
    <div class="d-flex gap-2">
      <a href="#segForm" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-plus me-1"></i>Add Segment</a>
      <a href="<?= base_url('admin/rewards_rules.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-cog me-1"></i>Enable/Disable Wheel</a>
      <a href="<?= base_url('admin/rewards_dashboard.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2 px-3 mb-3" style="border-radius:10px;font-size:.87rem;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-5">
      <!-- Wheel Preview -->
      <div class="rw-card p-4 text-center mb-3">
        <div class="fw-bold mb-3" style="font-size:.95rem;color:#1e1b4b;">Wheel Preview</div>
        <canvas id="wheelCanvas" width="260" height="260" style="border-radius:50%;box-shadow:0 8px 32px rgba(0,0,0,.15);cursor:pointer;" title="Click to preview spin"></canvas>
        <div class="mt-2 text-muted small">Total probability weight: <?= $totalProb ?></div>
      </div>

      <!-- Stats -->
      <?php
      try {
          $spins = $db->query("SELECT COUNT(*) FROM rewards_spin_history")->fetchColumn();
          $spinToday = $db->query("SELECT COUNT(*) FROM rewards_spin_history WHERE DATE(spun_at)=CURDATE()")->fetchColumn();
          $spinRewards = $db->query("SELECT COALESCE(SUM(reward_value),0) FROM rewards_spin_history")->fetchColumn();
      } catch (Exception $e) { $spins = $spinToday = $spinRewards = 0; }
      ?>
      <div class="rw-card p-3">
        <div class="fw-bold mb-2" style="font-size:.9rem;color:#1e1b4b;">Spin Stats</div>
        <div class="row g-2 text-center">
          <div class="col-4"><div class="fw-bold" style="font-size:1.3rem;color:#7c3aed;"><?= number_format((int)$spins) ?></div><div class="text-muted small">Total Spins</div></div>
          <div class="col-4"><div class="fw-bold" style="font-size:1.3rem;color:#10b981;"><?= number_format((int)$spinToday) ?></div><div class="text-muted small">Today</div></div>
          <div class="col-4"><div class="fw-bold" style="font-size:1.3rem;color:#f59e0b;">₹<?= number_format((float)$spinRewards,0) ?></div><div class="text-muted small">Rewards Given</div></div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <!-- Segment Form -->
      <div class="rw-card p-4 mb-3" id="segForm">
        <div class="fw-bold mb-3" style="color:#1e1b4b;"><?= $editSeg ? '✏️ Edit Segment' : '➕ Add Segment' ?></div>
        <form method="POST">
          <input type="hidden" name="action" value="save_segment">
          <input type="hidden" name="id" value="<?= $editId ?>">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small fw-semibold">Label</label>
              <input type="text" name="label" value="<?= htmlspecialchars($editSeg['label'] ?? '') ?>" required class="form-control form-control-sm" style="border-radius:8px;" placeholder="₹50 Cashback"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Icon / Emoji</label>
              <input type="text" name="icon" value="<?= htmlspecialchars($editSeg['icon'] ?? '🎁') ?>" class="form-control form-control-sm" style="border-radius:8px;"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Reward Type</label>
              <select name="reward_type" class="form-select form-select-sm" style="border-radius:8px;">
                <?php foreach (['cashback'=>'Cashback','free_shipping'=>'Free Shipping','bonus_rewards'=>'Bonus Rewards','unlock_reward'=>'Unlock Reward','no_reward'=>'No Reward (Try Again)'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= ($editSeg['reward_type']??'cashback')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Reward Value (₹)</label>
              <input type="number" name="reward_value" value="<?= $editSeg['reward_value'] ?? 0 ?>" step="0.01" min="0" class="form-control form-control-sm" style="border-radius:8px;"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Probability Weight</label>
              <input type="number" name="probability" value="<?= $editSeg['probability'] ?? 10 ?>" min="1" max="100" class="form-control form-control-sm" style="border-radius:8px;" title="Higher = more likely"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Color</label>
              <input type="color" name="color" value="<?= htmlspecialchars($editSeg['color'] ?? '#4ade80') ?>" class="form-control form-control-sm" style="border-radius:8px;height:34px;"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Daily Limit</label>
              <input type="number" name="daily_limit" value="<?= $editSeg['daily_limit'] ?? 100 ?>" min="1" class="form-control form-control-sm" style="border-radius:8px;"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold d-block">Active</label>
              <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="is_active" <?= ($editSeg['is_active']??1)?'checked':'' ?>></div></div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;"><i class="fas fa-save me-1"></i><?= $editSeg?'Update':'Add' ?> Segment</button>
              <?php if ($editSeg): ?><a href="<?= base_url('admin/rewards_spinwheel.php') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">Cancel</a><?php endif; ?>
            </div>
          </div>
        </form>
      </div>

      <!-- Segments Table -->
      <div class="rw-card p-3">
        <div class="fw-bold mb-2" style="font-size:.9rem;color:#1e1b4b;">Segments (<?= count($segments) ?>)</div>
        <?php if (empty($segments)): ?>
          <div class="text-center text-muted py-4 small">No segments yet</div>
        <?php else: ?>
        <table class="table table-sm mb-0" style="font-size:.82rem;">
          <thead class="table-light"><tr><th>Color</th><th>Label</th><th>Type</th><th>Value</th><th>Prob</th><th>Chance</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($segments as $s):
              $chance = $totalProb > 0 ? round((int)$s['probability'] / $totalProb * 100, 1) : 0;
            ?>
            <tr>
              <td><span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:<?= htmlspecialchars($s['color']) ?>;vertical-align:middle;"></span></td>
              <td class="fw-semibold"><?= htmlspecialchars($s['icon']) ?> <?= htmlspecialchars($s['label']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($s['reward_type']) ?></td>
              <td><?= $s['reward_value'] > 0 ? '₹'.number_format((float)$s['reward_value'],0) : '—' ?></td>
              <td><?= $s['probability'] ?></td>
              <td>
                <div class="prob-bar"><div class="prob-fill" style="width:<?= $chance ?>%;background:<?= htmlspecialchars($s['color']) ?>;"></div></div>
                <span class="text-muted" style="font-size:.7rem;"><?= $chance ?>%</span>
              </td>
              <td>
                <form method="POST" class="d-inline"><input type="hidden" name="action" value="toggle_segment"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button class="btn btn-xs p-0 border-0 bg-transparent" type="submit"><span class="badge <?= $s['is_active']?'bg-success':'bg-secondary' ?>"><?= $s['is_active']?'On':'Off' ?></span></button>
                </form>
              </td>
              <td>
                <a href="?edit=<?= $s['id'] ?>#segForm" class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 7px;border-radius:5px;"><i class="fas fa-edit"></i></a>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_segment"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:2px 7px;border-radius:5px;" type="submit"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
<script>
(function(){
  const segs = <?= json_encode(array_map(fn($s) => ['label'=>$s['label'],'color'=>$s['color'],'prob'=>(int)$s['probability'],'active'=>(bool)$s['is_active']], $segments)) ?>;
  const canvas = document.getElementById('wheelCanvas');
  if (!canvas || !segs.length) return;
  const ctx = canvas.getContext('2d');
  const cx = 130, cy = 130, r = 120;
  const total = segs.reduce((a,s)=>a+(s.active?s.prob:0),0);
  let startAngle = -Math.PI/2;
  segs.filter(s=>s.active).forEach(s=>{
    const slice = (s.prob/total)*2*Math.PI;
    ctx.beginPath(); ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,startAngle,startAngle+slice);
    ctx.closePath(); ctx.fillStyle=s.color; ctx.fill();
    ctx.strokeStyle='#fff'; ctx.lineWidth=2; ctx.stroke();
    // Label
    ctx.save(); ctx.translate(cx,cy);
    ctx.rotate(startAngle+slice/2);
    ctx.textAlign='right'; ctx.fillStyle='#fff';
    ctx.font='bold 11px sans-serif'; ctx.shadowColor='rgba(0,0,0,.4)'; ctx.shadowBlur=3;
    ctx.fillText(s.label.substring(0,12), r-8, 4);
    ctx.restore();
    startAngle+=slice;
  });
  // Center circle
  ctx.beginPath(); ctx.arc(cx,cy,22,0,2*Math.PI);
  ctx.fillStyle='#fff'; ctx.fill();
  ctx.strokeStyle='#e5e7eb'; ctx.lineWidth=2; ctx.stroke();
  ctx.fillStyle='#7c3aed'; ctx.font='bold 11px sans-serif';
  ctx.textAlign='center'; ctx.textBaseline='middle';
  ctx.fillText('SPIN', cx, cy);
})();
</script>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
