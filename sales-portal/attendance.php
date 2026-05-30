<?php
/**
 * Sales Portal - Attendance Management
 *
 * Shift Rules:
 *   Summer (1 Mar – 31 Oct): 9:00 AM – 5:00 PM  (9 hrs shift)
 *   Winter (1 Nov – 28/29 Feb): 10:00 AM – 6:00 PM  (9 hrs shift, 1hr lunch)
 *
 * Auto-status on checkout:
 *   Worked < 3 hrs   → absent (not counted)
 *   Worked < 4.5 hrs → half_day
 *   Worked >= 4.5 hrs → present
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Attendance';
$currentPage = 'attendance';

// ── Shift timing helper ──
function get_shift_info($dateStr = null) {
    $date = $dateStr ? new DateTime($dateStr) : new DateTime();
    $month = (int)$date->format('n');
    // Summer: March(3) to October(10), Winter: November(11) to February(2)
    $isSummer = ($month >= 3 && $month <= 10);
    return [
        'season'     => $isSummer ? 'Summer' : 'Winter',
        'start'      => $isSummer ? '09:00 AM' : '10:00 AM',
        'end'        => $isSummer ? '05:00 PM' : '06:00 PM',
        'start_24'   => $isSummer ? '09:00' : '10:00',
        'end_24'     => $isSummer ? '17:00' : '18:00',
        'total_hrs'  => 9,
    ];
}

// Calculate worked hours from check-in/out times
function calc_worked_hours($checkIn, $checkOut) {
    if (!$checkIn || !$checkOut) return 0;
    $in = strtotime($checkIn);
    $out = strtotime($checkOut);
    if ($out <= $in) return 0;
    return round(($out - $in) / 3600, 2);
}

// Determine status based on worked hours
function auto_status_from_hours($hours) {
    if ($hours < 3) return 'absent';
    if ($hours < 4.5) return 'half_day';
    return 'present';
}

$todayShift = get_shift_info();

// Handle check-in / check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['attendance_action'] ?? '';
    $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $lng = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $mapsUrl = trim($_POST['google_maps_url'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    try {
        if ($action === 'check_in') {
            $existing = db_fetch('SELECT id FROM sales_attendance WHERE executive_id = ? AND attendance_date = CURDATE()', [$execId]);
            if ($existing) {
                $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Already checked in today.'];
            } else {
                db_query('INSERT INTO sales_attendance (executive_id, attendance_date, check_in_time, status, latitude, longitude, google_maps_url, notes) VALUES (?, CURDATE(), CURTIME(), "present", ?, ?, ?, ?)', [
                    $execId, $lat, $lng, $mapsUrl, $notes
                ]);
                $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Checked in at ' . date('h:i A') . ' | Shift: ' . $todayShift['start'] . ' – ' . $todayShift['end'] . ' (' . $todayShift['season'] . ')'];
            }
        } elseif ($action === 'check_out') {
            // Fetch today's record to calculate hours
            $rec = db_fetch('SELECT * FROM sales_attendance WHERE executive_id = ? AND attendance_date = CURDATE()', [$execId]);
            if ($rec) {
                $workedHrs = calc_worked_hours($rec['check_in_time'], date('H:i:s'));
                $autoStatus = auto_status_from_hours($workedHrs);
                // Don't override on_leave status
                if ($rec['status'] === 'on_leave') $autoStatus = 'on_leave';

                db_query('UPDATE sales_attendance SET check_out_time = CURTIME(), status = ? WHERE executive_id = ? AND attendance_date = CURDATE()', [
                    $autoStatus, $execId
                ]);

                $hrsDisplay = floor($workedHrs) . 'h ' . round(($workedHrs - floor($workedHrs)) * 60) . 'm';
                $statusLabel = ucfirst(str_replace('_', ' ', $autoStatus));
                $_SESSION['sp_flash'] = ['type' => $autoStatus === 'absent' ? 'error' : ($autoStatus === 'half_day' ? 'warning' : 'success'), 'message' => 'Checked out at ' . date('h:i A') . ' | Worked: ' . $hrsDisplay . ' | Status: ' . $statusLabel];
            }
        }
    } catch (PDOException $e) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Location: ' . sales_base_url('attendance.php'));
    exit;
}

// Today's attendance
$todayRecord = null;
try {
    $todayRecord = db_fetch('SELECT * FROM sales_attendance WHERE executive_id = ? AND attendance_date = CURDATE()', [$execId]);
} catch (PDOException $e) { /* table may not exist */ }

// Calculate today's worked hours if checked in
$todayWorkedHrs = 0;
if ($todayRecord && $todayRecord['check_in_time']) {
    $outTime = $todayRecord['check_out_time'] ?: date('H:i:s');
    $todayWorkedHrs = calc_worked_hours($todayRecord['check_in_time'], $outTime);
}

// This month's attendance history
$monthRecords = [];
try {
    $monthRecords = db_fetch_all('SELECT * FROM sales_attendance WHERE executive_id = ? AND MONTH(attendance_date) = MONTH(NOW()) AND YEAR(attendance_date) = YEAR(NOW()) ORDER BY attendance_date DESC', [$execId]);
} catch (PDOException $e) { /* table may not exist */ }

// Stats
$presentDays = 0;
$halfDays = 0;
$absentDays = 0;
$leaveDays = 0;
$totalWorkedHrs = 0;
foreach ($monthRecords as $r) {
    if ($r['status'] === 'present') $presentDays++;
    elseif ($r['status'] === 'half_day') $halfDays++;
    elseif ($r['status'] === 'absent') $absentDays++;
    elseif ($r['status'] === 'on_leave') $leaveDays++;
    if ($r['check_in_time'] && $r['check_out_time']) {
        $totalWorkedHrs += calc_worked_hours($r['check_in_time'], $r['check_out_time']);
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Shift Info Banner -->
<div class="sp-att-shift-banner <?= $todayShift['season'] === 'Summer' ? 'sp-att-summer' : 'sp-att-winter' ?>">
    <i class="fas fa-<?= $todayShift['season'] === 'Summer' ? 'sun' : 'snowflake' ?>"></i>
    <div>
        <div class="sp-att-shift-title"><?= $todayShift['season'] ?> Shift</div>
        <div class="sp-att-shift-time"><?= $todayShift['start'] ?> – <?= $todayShift['end'] ?> (9 hrs)</div>
    </div>
    <?php if ($todayRecord && $todayRecord['check_in_time'] && !$todayRecord['check_out_time']): ?>
        <div class="sp-att-shift-countdown sp-att-shift-active" id="shiftCountdown" data-checkin="<?= $todayRecord['check_in_time'] ?>" data-shiftend="<?= $todayShift['end_24'] ?>">--:--:--</div>
    <?php elseif ($todayRecord && $todayRecord['check_out_time']): ?>
        <div class="sp-att-shift-countdown sp-att-shift-done"><i class="fas fa-check"></i> Done</div>
    <?php else: ?>
        <div class="sp-att-shift-countdown sp-att-shift-idle">Not In</div>
    <?php endif; ?>
</div>

<div class="sp-att-rules">
    <span>≥4.5h = <strong>Present</strong></span>
    <span class="sp-att-rules-dot">·</span>
    <span>3–4.5h = <strong>Half Day</strong></span>
    <span class="sp-att-rules-dot">·</span>
    <span>&lt;3h = <strong>Absent</strong></span>
</div>

<!-- Today's Attendance -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-calendar-check"></i> Today</h3>
        <span class="sp-text-muted sp-fs-sm"><?= date('d M Y') ?></span>
    </div>

    <?php if ($todayRecord): ?>
        <?php
        $hrsDisplay = floor($todayWorkedHrs) . 'h ' . round(($todayWorkedHrs - floor($todayWorkedHrs)) * 60) . 'm';
        $currentAutoStatus = auto_status_from_hours($todayWorkedHrs);
        $statusColorMap = ['present' => '#059669', 'half_day' => '#d97706', 'absent' => '#dc2626', 'on_leave' => '#2563eb'];
        $displayStatus = $todayRecord['check_out_time'] ? $todayRecord['status'] : $currentAutoStatus;
        $statusColor = $statusColorMap[$displayStatus] ?? '#6b7280';
        ?>
        <div class="sp-att-today-card">
            <div class="sp-att-check-icon sp-att-checked-in">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="sp-att-status-text">Checked In</div>
            <div class="sp-att-times">
                <span><i class="fas fa-sign-in-alt"></i> <?= date('h:i A', strtotime($todayRecord['check_in_time'])) ?></span>
                <?php if ($todayRecord['check_out_time']): ?>
                    <span><i class="fas fa-sign-out-alt"></i> <?= date('h:i A', strtotime($todayRecord['check_out_time'])) ?></span>
                <?php endif; ?>
            </div>

            <div class="sp-att-metrics">
                <div class="sp-att-metric">
                    <div class="sp-att-metric-val"><?= $hrsDisplay ?></div>
                    <div class="sp-att-metric-lbl"><?= $todayRecord['check_out_time'] ? 'Total Worked' : 'Working...' ?></div>
                </div>
                <div class="sp-att-metric" style="background:<?= $statusColor ?>12;border:1px solid <?= $statusColor ?>30;">
                    <div class="sp-att-metric-val" style="color:<?= $statusColor ?>;"><?= ucfirst(str_replace('_', ' ', $displayStatus)) ?></div>
                    <div class="sp-att-metric-lbl"><?= $todayRecord['check_out_time'] ? 'Final' : 'Current' ?></div>
                </div>
            </div>

            <?php if ($todayRecord['google_maps_url']): ?>
                <a href="<?= htmlspecialchars($todayRecord['google_maps_url']) ?>" target="_blank" class="sp-att-location-link">
                    <i class="fas fa-map-marker-alt"></i> View Location
                </a>
            <?php endif; ?>

            <?php if (!$todayRecord['check_out_time']): ?>
                <form method="POST" class="sp-att-checkout-form">
                    <input type="hidden" name="attendance_action" value="check_out">
                    <button type="submit" class="sp-btn sp-btn-outline sp-btn-block">
                        <i class="fas fa-sign-out-alt"></i> Check Out
                    </button>
                    <div class="sp-att-hint">Status auto-calculated from worked hours</div>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="sp-att-today-card">
            <div class="sp-att-check-icon sp-att-not-in">
                <i class="fas fa-clock"></i>
            </div>
            <div class="sp-att-status-text sp-att-status-out">Not Checked In</div>
            <div class="sp-att-times">
                <span>Shift: <?= $todayShift['start'] ?> – <?= $todayShift['end'] ?> (<?= $todayShift['season'] ?>)</span>
            </div>

            <form method="POST" class="sp-att-checkin-form">
                <input type="hidden" name="attendance_action" value="check_in">
                <input type="hidden" name="latitude" id="attLat">
                <input type="hidden" name="longitude" id="attLng">
                <input type="hidden" name="google_maps_url" id="attMapsUrl">
                <div class="sp-form-group">
                    <input type="text" name="notes" class="sp-input" placeholder="Notes (optional)">
                </div>
                <button type="button" class="sp-btn sp-btn-primary sp-btn-block sp-btn-lg" onclick="checkInWithLocation()" id="checkInBtn">
                    <i class="fas fa-map-marker-alt"></i> Check In with Location
                </button>
                <div id="checkInStatus" class="sp-att-hint"></div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Monthly Stats -->
<div class="sp-att-month-stats">
    <div class="sp-att-month-stat">
        <div class="sp-att-ms-val sp-color-green"><?= $presentDays ?></div>
        <div class="sp-att-ms-lbl">Present</div>
    </div>
    <div class="sp-att-month-stat">
        <div class="sp-att-ms-val" style="color:#d97706;"><?= $halfDays ?></div>
        <div class="sp-att-ms-lbl">Half Day</div>
    </div>
    <div class="sp-att-month-stat">
        <div class="sp-att-ms-val sp-color-red"><?= $absentDays ?></div>
        <div class="sp-att-ms-lbl">Absent</div>
    </div>
    <div class="sp-att-month-stat">
        <div class="sp-att-ms-val" style="color:#2563eb;"><?= $leaveDays ?></div>
        <div class="sp-att-ms-lbl">Leave</div>
    </div>
    <div class="sp-att-month-stat">
        <div class="sp-att-ms-val sp-color-purple"><?= floor($totalWorkedHrs) ?>h</div>
        <div class="sp-att-ms-lbl"><?= date('M') ?> Hrs</div>
    </div>
</div>

<!-- Attendance History (mobile card list) -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> This Month</h3>
    </div>
    <?php if (empty($monthRecords)): ?>
        <div class="sp-empty">
            <i class="fas fa-calendar"></i>
            <h3>No records yet</h3>
            <p>Start by checking in today.</p>
        </div>
    <?php else: ?>
        <div class="sp-att-history">
            <?php foreach ($monthRecords as $rec):
                $recShift = get_shift_info($rec['attendance_date']);
                $recHrs = calc_worked_hours($rec['check_in_time'], $rec['check_out_time']);
                $recHrsDisplay = $rec['check_out_time'] ? (floor($recHrs) . 'h ' . round(($recHrs - floor($recHrs)) * 60) . 'm') : '—';
                $statusColors = ['present' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'absent' => ['bg' => '#fee2e2', 'color' => '#991b1b'], 'half_day' => ['bg' => '#fef3c7', 'color' => '#92400e'], 'on_leave' => ['bg' => '#dbeafe', 'color' => '#1e40af']];
                $sc = $statusColors[$rec['status']] ?? ['bg' => '#f3f4f6', 'color' => '#6b7280'];
            ?>
            <div class="sp-att-history-item">
                <div class="sp-att-hist-date">
                    <div class="sp-att-hist-day"><?= date('d', strtotime($rec['attendance_date'])) ?></div>
                    <div class="sp-att-hist-month"><?= date('M', strtotime($rec['attendance_date'])) ?></div>
                    <div class="sp-att-hist-dow"><?= date('D', strtotime($rec['attendance_date'])) ?></div>
                </div>
                <div class="sp-att-hist-info">
                    <div class="sp-att-hist-times">
                        <?= $rec['check_in_time'] ? date('h:i A', strtotime($rec['check_in_time'])) : '—' ?>
                        →
                        <?= $rec['check_out_time'] ? date('h:i A', strtotime($rec['check_out_time'])) : '<span style="color:#d97706;">Active</span>' ?>
                    </div>
                    <div class="sp-att-hist-detail">
                        <span class="sp-att-hist-hrs"><?= $recHrsDisplay ?></span>
                        <?php if ($rec['google_maps_url']): ?>
                            <a href="<?= htmlspecialchars($rec['google_maps_url']) ?>" target="_blank" class="sp-att-hist-loc"><i class="fas fa-map-marker-alt"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sp-att-hist-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
                    <?= ucfirst(str_replace('_', ' ', $rec['status'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Shift Timer — counts time left until shift end, only when checked in
(function() {
    var el = document.getElementById('shiftCountdown');
    if (!el || !el.dataset.checkin) return;

    var endParts = el.dataset.shiftend.split(':');
    var endH = parseInt(endParts[0]), endM = parseInt(endParts[1]);

    function tick() {
        var now = new Date();
        var end = new Date();
        end.setHours(endH, endM, 0, 0);
        var diff = end - now;
        if (diff <= 0) {
            el.textContent = 'Shift Over';
            el.classList.add('sp-att-shift-over');
            el.classList.remove('sp-att-shift-active');
            return;
        }
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        el.textContent = (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }
    tick();
    setInterval(tick, 1000);
})();

function checkInWithLocation() {
    var btn = document.getElementById('checkInBtn');
    var statusDiv = document.getElementById('checkInStatus');

    if (!navigator.geolocation) {
        statusDiv.innerHTML = '<span style="color:#dc2626;">Geolocation not supported. Checking in without location...</span>';
        btn.closest('form').submit();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting Location...';
    statusDiv.innerHTML = '<span style="color:#0284c7;"><i class="fas fa-info-circle"></i> Requesting location...</span>';

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            document.getElementById('attLat').value = lat.toFixed(8);
            document.getElementById('attLng').value = lng.toFixed(8);
            document.getElementById('attMapsUrl').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
            statusDiv.innerHTML = '<span style="color:#059669;"><i class="fas fa-check-circle"></i> Location captured!</span>';
            btn.closest('form').submit();
        },
        function(err) {
            statusDiv.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> Location unavailable. Checking in without GPS...</span>';
            setTimeout(function() { btn.closest('form').submit(); }, 1000);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
