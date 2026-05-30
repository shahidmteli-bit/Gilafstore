<?php
/**
 * Gilaf Rewards — Spin & Win Page
 * Accessible after placing an order OR via the rewards wallet widget
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rewards_engine.php';

if (!isset($_SESSION['user'])) {
    redirect_with_message(base_url('user/login.php?redirect=user/spin-wheel.php'), 'Please login to spin', 'info');
}

$userId   = (int)$_SESSION['user']['id'];
$wallet   = rw_get_wallet($userId);
$balance  = (float)($wallet['balance'] ?? 0);

// Load spin wheel segments
$db = get_db_connection();
$segments = [];
try {
    $st = $db->query("SELECT * FROM rewards_spinwheel WHERE is_active = 1 ORDER BY id ASC");
    $segments = $st->fetchAll();
} catch (Exception $e) { $segments = []; }

// Check if spin is available
$spinEnabled  = (bool)rw_get_rule('spin_wheel_enabled', 1);
$everyDays    = max(1, (int)rw_get_rule('spin_wheel_per_days', 1));
$canSpin      = false;
$nextSpinDate = '';
$lastSpinAt   = null;

if ($spinEnabled && !empty($segments)) {
    $st2 = $db->prepare("SELECT MAX(spun_at) FROM rewards_spin_history WHERE user_id = ?");
    $st2->execute([$userId]);
    $lastSpinAt = $st2->fetchColumn();
    if (!$lastSpinAt || strtotime($lastSpinAt) <= strtotime("-{$everyDays} days")) {
        $canSpin = true;
    } else {
        $nextSpinDate = date('D, d M Y', strtotime($lastSpinAt . " +{$everyDays} days"));
    }
}

// Spin history (last 5)
$spinHistory = [];
try {
    $sh = $db->prepare("SELECT h.*, s.label, s.color FROM rewards_spin_history h LEFT JOIN rewards_spinwheel s ON h.spinwheel_id = s.id WHERE h.user_id = ? ORDER BY h.spun_at DESC LIMIT 5");
    $sh->execute([$userId]);
    $spinHistory = $sh->fetchAll();
} catch (Exception $e) { $spinHistory = []; }

$pageTitle  = 'Spin & Win — Gilaf Rewards';
$activePage = '';
include __DIR__ . '/../includes/new-header.php';
?>

<style>
.spin-page { max-width: 560px; margin: 40px auto; padding: 0 16px 80px; }
.spin-header { text-align: center; margin-bottom: 28px; }
.spin-header h1 { font-size: 1.8rem; font-weight: 800; color: #1e1b4b; margin: 0 0 6px; }
.spin-header p  { color: #6b7280; font-size: .92rem; }

/* Wallet bar */
.spin-wallet-bar {
  background: linear-gradient(135deg, #1e1b4b, #3730a3);
  color: #fff; border-radius: 16px; padding: 14px 20px;
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px;
}
.spin-wallet-bar .lbl { font-size: .75rem; opacity: .75; }
.spin-wallet-bar .val { font-size: 1.4rem; font-weight: 800; }

/* Wheel wrapper */
.spin-wheel-wrap {
  position: relative; display: flex; flex-direction: column;
  align-items: center; gap: 0; margin-bottom: 28px;
}
.spin-pointer {
  width: 0; height: 0;
  border-left: 14px solid transparent;
  border-right: 14px solid transparent;
  border-top: 28px solid #1e1b4b;
  position: relative; z-index: 10; margin-bottom: -4px;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,.3));
}
#spinCanvas {
  border-radius: 50%;
  box-shadow: 0 8px 40px rgba(124,58,237,.35), 0 0 0 6px #fff, 0 0 0 10px #ede9fe;
  display: block;
}
.spin-btn {
  margin-top: 22px;
  background: linear-gradient(135deg, #7c3aed, #a855f7);
  color: #fff; border: none; border-radius: 50px;
  padding: 14px 48px; font-size: 1.05rem; font-weight: 800;
  cursor: pointer; transition: transform .15s, box-shadow .15s;
  box-shadow: 0 6px 24px rgba(124,58,237,.4);
  letter-spacing: .03em;
}
.spin-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 32px rgba(124,58,237,.5); }
.spin-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }

/* Result card */
.spin-result {
  display: none; text-align: center; padding: 28px 24px;
  background: #fff; border-radius: 20px;
  box-shadow: 0 8px 40px rgba(0,0,0,.12);
  border: 2px solid #c4b5fd; margin-bottom: 20px;
  animation: rwPopupIn .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes rwPopupIn { from{opacity:0;transform:scale(.85)} to{opacity:1;transform:scale(1)} }
.spin-result.show { display: block; }
.spin-result-emoji { font-size: 3.5rem; margin-bottom: 8px; }
.spin-result-title { font-size: 1.3rem; font-weight: 800; color: #1e1b4b; margin-bottom: 6px; }
.spin-result-msg { font-size: .92rem; color: #374151; margin-bottom: 16px; }
.spin-result-amount {
  font-size: 2rem; font-weight: 800; color: #7c3aed;
  background: linear-gradient(135deg, #ede9fe, #f5f3ff);
  border-radius: 12px; padding: 10px 24px; display: inline-block; margin-bottom: 16px;
}

/* Next spin info */
.spin-next-info {
  background: #f3f4f6; border-radius: 12px; padding: 14px 16px;
  text-align: center; margin-top: 12px; font-size: .87rem; color: #374151;
}
.spin-next-info strong { color: #7c3aed; }

/* History */
.spin-history { background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
.spin-history h3 { font-size: .9rem; font-weight: 700; color: #1e1b4b; margin: 0 0 12px; }
.spin-history-item { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid #f3f4f6; font-size: .82rem; }
.spin-history-item:last-child { border-bottom: none; }
.spin-history-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.spin-history-win { font-weight: 700; color: #7c3aed; margin-left: auto; white-space: nowrap; }

/* Disabled state */
.spin-disabled-card {
  background: linear-gradient(135deg, #f5f3ff, #ede9fe);
  border: 2px solid #c4b5fd; border-radius: 20px;
  padding: 32px 24px; text-align: center; margin-bottom: 24px;
}
.spin-disabled-card .emoji { font-size: 3rem; margin-bottom: 10px; }
.spin-disabled-card h2 { font-size: 1.15rem; font-weight: 800; color: #1e1b4b; margin-bottom: 6px; }
.spin-disabled-card p { font-size: .87rem; color: #6b7280; }

/* Confetti canvas */
#confettiCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; display: none; }
</style>

<canvas id="confettiCanvas"></canvas>

<main class="spin-page">
  <div class="spin-header">
    <div style="font-size:2.8rem;margin-bottom:8px;">🎡</div>
    <h1>Spin & Win Rewards</h1>
    <p>Spin the wheel and instantly win cashback, bonus rewards, and more!</p>
  </div>

  <!-- Wallet balance bar -->
  <div class="spin-wallet-bar">
    <div>
      <div class="lbl">Gilaf Rewards Balance</div>
      <div class="val" id="spinWalletBal">₹<?= number_format($balance, 2) ?></div>
    </div>
    <div style="text-align:right;">
      <div class="lbl">Your Tier</div>
      <div style="font-weight:700;font-size:.95rem;margin-top:2px;">
        <?php
        $tierIcons = ['Silver'=>'🥈','Gold'=>'🥇','Platinum'=>'💎'];
        echo ($tierIcons[$wallet['tier'] ?? 'Silver'] ?? '🥈') . ' ' . htmlspecialchars($wallet['tier'] ?? 'Silver');
        ?>
      </div>
    </div>
  </div>

  <?php if (!$spinEnabled || empty($segments)): ?>
  <!-- Spin disabled or not configured -->
  <div class="spin-disabled-card">
    <div class="emoji">😴</div>
    <h2>Spin Wheel Not Available</h2>
    <p>The spin wheel is currently paused. Check back soon for exciting rewards!</p>
  </div>

  <?php elseif (!$canSpin): ?>
  <!-- Already spun today -->
  <div class="spin-disabled-card">
    <div class="emoji">⏰</div>
    <h2>Come Back Tomorrow!</h2>
    <p>You've already spun today. Your next spin is available on:</p>
    <div style="font-size:1.1rem;font-weight:800;color:#7c3aed;margin:10px 0;">📅 <?= htmlspecialchars($nextSpinDate) ?></div>
    <p style="font-size:.78rem;color:#9ca3af;">1 free spin every <?= $everyDays ?> day<?= $everyDays > 1 ? 's' : '' ?></p>
  </div>

  <?php else: ?>
  <!-- Spin result (hidden until spin) -->
  <div class="spin-result" id="spinResult">
    <div class="spin-result-emoji" id="spinResultEmoji">🎉</div>
    <div class="spin-result-title" id="spinResultTitle">Congratulations!</div>
    <div class="spin-result-amount" id="spinResultAmount"></div>
    <div class="spin-result-msg" id="spinResultMsg"></div>
    <button onclick="location.reload()" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;border:none;border-radius:10px;padding:10px 28px;font-weight:700;font-size:.9rem;cursor:pointer;margin-right:8px;">🎡 Done</button>
    <a href="<?= base_url('cart.php') ?>" style="background:#f59e0b;color:#1e1b4b;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.9rem;text-decoration:none;display:inline-block;">🛒 Use Rewards</a>
  </div>

  <!-- Wheel -->
  <div class="spin-wheel-wrap">
    <div class="spin-pointer"></div>
    <canvas id="spinCanvas" width="300" height="300"></canvas>
    <button class="spin-btn" id="spinBtn" onclick="doSpin()">
      🎡 &nbsp;SPIN NOW
    </button>
    <div style="font-size:.75rem;color:#9ca3af;margin-top:8px;text-align:center;">
      1 free spin every <?= $everyDays ?> day<?= $everyDays > 1 ? 's' : '' ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Spin history -->
  <?php if (!empty($spinHistory)): ?>
  <div class="spin-history">
    <h3><i class="fas fa-history me-1" style="color:#7c3aed;"></i> Recent Spins</h3>
    <?php foreach ($spinHistory as $sh): ?>
    <div class="spin-history-item">
      <div class="spin-history-dot" style="background:<?= htmlspecialchars($sh['color'] ?? '#7c3aed') ?>;"></div>
      <div>
        <div style="font-weight:600;"><?= htmlspecialchars($sh['label'] ?? $sh['reward_type']) ?></div>
        <div style="font-size:.72rem;color:#9ca3af;"><?= date('d M Y, h:i A', strtotime($sh['spun_at'])) ?></div>
      </div>
      <div class="spin-history-win">
        <?php if ((float)$sh['reward_value'] > 0): ?>
          ₹<?= number_format((float)$sh['reward_value'], 0) ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Rules -->
  <div style="background:#f9fafb;border-radius:12px;padding:14px 16px;margin-top:16px;font-size:.78rem;color:#6b7280;line-height:1.7;">
    <strong style="color:#374151;">Spin Wheel Rules:</strong><br>
    • One free spin every <?= $everyDays ?> day<?= $everyDays > 1 ? 's' : '' ?>.<br>
    • Cashback rewards are credited instantly to your Gilaf Rewards Wallet.<br>
    • Free shipping prizes apply to your next order.<br>
    • Rewards expire after <?= (int)rw_get_rule('reward_expiry_days', 90) ?> days.
  </div>
</main>

<?php
// Pass segments to JS
$segmentsJson = json_encode(array_values($segments), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<script>
// ── Wheel Segments Data ───────────────────────────────────────────────────
const RW_SEGMENTS = <?= $segmentsJson ?>;
const RW_API_URL  = '<?= base_url("api/rewards_api.php") ?>';

// ── Draw Wheel ────────────────────────────────────────────────────────────
const canvas = document.getElementById('spinCanvas');
if (canvas && RW_SEGMENTS.length > 0) {
  const ctx  = canvas.getContext('2d');
  const W    = canvas.width;
  const H    = canvas.height;
  const cx   = W / 2;
  const cy   = H / 2;
  const r    = cx - 6;
  const n    = RW_SEGMENTS.length;
  const arc  = (2 * Math.PI) / n;

  let currentAngle = 0;
  let spinning     = false;
  let spinAngle    = 0; // accumulated rotation

  function drawWheel(rotation) {
    ctx.clearRect(0, 0, W, H);
    for (let i = 0; i < n; i++) {
      const start = rotation + i * arc - Math.PI / 2;
      const end   = start + arc;
      const seg   = RW_SEGMENTS[i];
      const color = seg.color || (i % 2 === 0 ? '#7c3aed' : '#a855f7');

      // Slice
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, start, end);
      ctx.closePath();
      ctx.fillStyle = color;
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth   = 2;
      ctx.stroke();

      // Label
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(start + arc / 2);
      ctx.textAlign    = 'right';
      ctx.fillStyle    = '#fff';
      ctx.font         = 'bold ' + (n > 8 ? '11' : '13') + 'px sans-serif';
      ctx.shadowColor  = 'rgba(0,0,0,.3)';
      ctx.shadowBlur   = 2;

      const label = seg.label.length > 12 ? seg.label.substring(0, 11) + '…' : seg.label;
      ctx.fillText(label, r - 10, 5);

      if (parseFloat(seg.reward_value) > 0) {
        ctx.font      = (n > 8 ? '10' : '11') + 'px sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,.85)';
        ctx.fillText('₹' + parseFloat(seg.reward_value).toLocaleString('en-IN'), r - 10, 20);
      }
      ctx.restore();
    }

    // Center hub
    ctx.beginPath();
    ctx.arc(cx, cy, 22, 0, 2 * Math.PI);
    ctx.fillStyle = '#1e1b4b';
    ctx.fill();
    ctx.strokeStyle = '#fff';
    ctx.lineWidth   = 3;
    ctx.stroke();

    // Hub icon
    ctx.fillStyle = '#f59e0b';
    ctx.font = 'bold 14px sans-serif';
    ctx.textAlign    = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('✦', cx, cy);
  }

  drawWheel(0);

  // ── Spin animation ──────────────────────────────────────────────────────
  window.doSpin = async function() {
    if (spinning) return;
    const btn = document.getElementById('spinBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳&nbsp; Spinning...';
    spinning = true;

    try {
      const resp = await fetch(RW_API_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'spin'})
      });
      const data = await resp.json();

      if (!data.success) {
        alert(data.error || 'Spin failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '🎡&nbsp;SPIN NOW';
        spinning = false;
        return;
      }

      // Find segment index by id
      const segIdx = RW_SEGMENTS.findIndex(s => parseInt(s.id) === parseInt(data.segment.id));
      const targetIdx = segIdx >= 0 ? segIdx : 0;

      // Calculate target rotation: land on segment center
      const targetAngle = (2 * Math.PI / n) * targetIdx;
      const extraSpins  = (5 + Math.floor(Math.random() * 4)) * 2 * Math.PI;
      const totalSpin   = extraSpins + (2 * Math.PI - targetAngle % (2 * Math.PI));

      // Ease out animation
      const duration = 4500; // ms
      const startTime = performance.now();
      const startAngle = spinAngle;

      function easeOut(t) {
        return 1 - Math.pow(1 - t, 4);
      }

      function animate(now) {
        const elapsed  = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        spinAngle = startAngle + totalSpin * easeOut(progress);
        drawWheel(spinAngle);

        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          // Done
          spinning = false;
          showSpinResult(data);
          launchConfetti();
        }
      }
      requestAnimationFrame(animate);

    } catch (e) {
      btn.disabled = false;
      btn.innerHTML = '🎡&nbsp;SPIN NOW';
      spinning = false;
      alert('Connection error. Please try again.');
    }
  };
}

// ── Show Result ───────────────────────────────────────────────────────────
function showSpinResult(data) {
  const seg = data.segment;
  const isWin = parseFloat(seg.reward_value) > 0;

  document.getElementById('spinResultEmoji').textContent = isWin ? '🎉' : '😊';
  document.getElementById('spinResultTitle').textContent = isWin ? 'You Won!' : 'Better Luck Next Time!';
  document.getElementById('spinResultMsg').textContent   = data.message;

  if (isWin && parseFloat(seg.reward_value) > 0) {
    document.getElementById('spinResultAmount').textContent = '₹' + parseFloat(seg.reward_value).toLocaleString('en-IN') + ' Rewards!';
    document.getElementById('spinResultAmount').style.display = 'inline-block';
  } else {
    document.getElementById('spinResultAmount').style.display = 'none';
  }

  document.getElementById('spinResult').classList.add('show');
  document.querySelector('.spin-wheel-wrap') && (document.querySelector('.spin-wheel-wrap').style.opacity = '.4');

  // Update wallet balance display
  if (isWin) {
    fetch(RW_API_URL, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_balance'})})
      .then(r=>r.json()).then(d=>{
        if(d.success) document.getElementById('spinWalletBal').textContent = '₹' + parseFloat(d.balance).toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2});
      }).catch(()=>{});
  }
}

// ── Confetti ──────────────────────────────────────────────────────────────
function launchConfetti() {
  const cc = document.getElementById('confettiCanvas');
  if (!cc) return;
  cc.style.display = 'block';
  cc.width  = window.innerWidth;
  cc.height = window.innerHeight;
  const ctx = cc.getContext('2d');
  const pieces = Array.from({length: 90}, () => ({
    x: Math.random() * cc.width, y: -10,
    r: 4 + Math.random() * 6,
    d: 2 + Math.random() * 3,
    color: ['#7c3aed','#a855f7','#f59e0b','#10b981','#ef4444','#3b82f6'][Math.floor(Math.random()*6)],
    tilt: Math.random() * 10 - 5,
    tiltAngle: 0,
    tiltAngleIncremental: (Math.random() * 0.07) + 0.05
  }));
  let frame = 0;
  function draw() {
    ctx.clearRect(0, 0, cc.width, cc.height);
    pieces.forEach(p => {
      p.tiltAngle += p.tiltAngleIncremental;
      p.y += p.d; p.x += Math.sin(frame / 10) * 1.5;
      p.tilt = Math.sin(p.tiltAngle) * 12;
      ctx.beginPath();
      ctx.lineWidth = p.r / 2;
      ctx.strokeStyle = p.color;
      ctx.moveTo(p.x + p.tilt + p.r / 4, p.y);
      ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r / 4);
      ctx.stroke();
    });
    frame++;
    if (frame < 180) requestAnimationFrame(draw);
    else { cc.style.display = 'none'; }
  }
  requestAnimationFrame(draw);
}
</script>

<?php include __DIR__ . '/../includes/new-footer.php'; ?>
