<?php
/**
 * Admin Panel - Sales Executive Attendance
 *
 * Shift Rules:
 *   Summer (1 Mar – 31 Oct): 9:00 AM – 5:00 PM  (9 hrs)
 *   Winter (1 Nov – 28/29 Feb): 10:00 AM – 6:00 PM  (9 hrs)
 *   Worked < 3 hrs   → absent (not counted)
 *   Worked < 4.5 hrs → half_day
 *   Worked >= 4.5 hrs → present
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Attendance';
$adminPage = 'sales_attendance';

// Shift helper
function admin_get_shift_info($dateStr = null) {
    $date = $dateStr ? new DateTime($dateStr) : new DateTime();
    $month = (int)$date->format('n');
    $isSummer = ($month >= 3 && $month <= 10);
    return [
        'season'   => $isSummer ? 'Summer' : 'Winter',
        'start'    => $isSummer ? '09:00 AM' : '10:00 AM',
        'end'      => $isSummer ? '05:00 PM' : '06:00 PM',
    ];
}
function admin_calc_worked_hours($checkIn, $checkOut) {
    if (!$checkIn || !$checkOut) return 0;
    $in = strtotime($checkIn); $out = strtotime($checkOut);
    if ($out <= $in) return 0;
    return round(($out - $in) / 3600, 2);
}

$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterExec = (int)($_GET['exec'] ?? 0);

// Fetch executives - handle missing assigned_area column gracefully
try {
    $executives = db_fetch_all('SELECT id, name, district, assigned_area FROM sales_executives ORDER BY name ASC');
} catch (PDOException $e) {
    $executives = db_fetch_all('SELECT id, name, district FROM sales_executives ORDER BY name ASC');
}

// Fetch attendance records - handle missing assigned_area
$hasArea = false;
try { db_fetch('SELECT assigned_area FROM sales_executives LIMIT 1'); $hasArea = true; } catch (PDOException $e) {}
$areaCol = $hasArea ? ', se.assigned_area' : '';
$sql = 'SELECT sa.*, se.name as exec_name, se.district' . $areaCol . ' FROM sales_attendance sa JOIN sales_executives se ON sa.executive_id = se.id WHERE 1=1';
$params = [];

if ($filterDate) {
    $sql .= ' AND sa.attendance_date = ?';
    $params[] = $filterDate;
}
if ($filterExec > 0) {
    $sql .= ' AND sa.executive_id = ?';
    $params[] = $filterExec;
}
$sql .= ' ORDER BY sa.attendance_date DESC, se.name ASC';
$records = db_fetch_all($sql, $params);

// Summary for the selected date
$totalExecs = count($executives);
$presentToday = 0;
$halfDayToday = 0;
$absentToday = 0;
if ($filterDate) {
    $presentToday = db_fetch('SELECT COUNT(*) as cnt FROM sales_attendance WHERE attendance_date = ? AND status = "present"', [$filterDate])['cnt'] ?? 0;
    $halfDayToday = db_fetch('SELECT COUNT(*) as cnt FROM sales_attendance WHERE attendance_date = ? AND status = "half_day"', [$filterDate])['cnt'] ?? 0;
    $absentToday = $totalExecs - $presentToday - $halfDayToday;
}

$shiftForDate = admin_get_shift_info($filterDate);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Attendance</h2>
            <p class="text-muted mb-0">Track daily attendance of all sales executives</p>
        </div>
        <div class="text-end">
            <span class="badge bg-<?= $shiftForDate['season'] === 'Summer' ? 'warning text-dark' : 'primary' ?> fs-6">
                <i class="fas fa-<?= $shiftForDate['season'] === 'Summer' ? 'sun' : 'snowflake' ?> me-1"></i>
                <?= $shiftForDate['season'] ?> Shift: <?= $shiftForDate['start'] ?> – <?= $shiftForDate['end'] ?>
            </span>
            <div class="text-muted small mt-1">9 hrs | &ge;4.5h = Present | 3–4.5h = Half Day | &lt;3h = Absent</div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card shadow-sm text-center p-3">
                <div class="fw-bold fs-3 text-primary"><?= $totalExecs ?></div>
                <div class="text-muted small">Total Executives</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm text-center p-3 border-start border-success border-4">
                <div class="fw-bold fs-3 text-success"><?= $presentToday ?></div>
                <div class="text-muted small">Present</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm text-center p-3 border-start border-warning border-4">
                <div class="fw-bold fs-3 text-warning"><?= $halfDayToday ?></div>
                <div class="text-muted small">Half Day</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm text-center p-3 border-start border-danger border-4">
                <div class="fw-bold fs-3 text-danger"><?= $absentToday ?></div>
                <div class="text-muted small">Absent / Not Marked</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm text-center p-3">
                <div class="fw-bold fs-3 text-info"><?= $totalExecs > 0 ? round(($presentToday / $totalExecs) * 100) : 0 ?>%</div>
                <div class="text-muted small">Attendance Rate</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold small">Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">Executive</label>
                <select name="exec" class="form-select">
                    <option value="0">All Executives</option>
                    <?php foreach ($executives as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filterExec == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= base_url('admin/sales_attendance.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <!-- Attendance Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Executive</th>
                            <th>Area</th>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Worked</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-calendar-check fa-3x mb-3 d-block"></i>No attendance records found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($records as $rec):
                            $recShift = admin_get_shift_info($rec['attendance_date']);
                            $recHrs = admin_calc_worked_hours($rec['check_in_time'], $rec['check_out_time']);
                            $recHrsDisplay = $rec['check_out_time'] ? (floor($recHrs) . 'h ' . round(($recHrs - floor($recHrs)) * 60) . 'm') : '—';
                            // Color code worked hours
                            $hrsColor = '';
                            if ($rec['check_out_time']) {
                                if ($recHrs < 3) $hrsColor = 'text-danger fw-bold';
                                elseif ($recHrs < 4.5) $hrsColor = 'text-warning fw-bold';
                                else $hrsColor = 'text-success fw-bold';
                            }
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($rec['exec_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($rec['assigned_area'] ?? $rec['district'] ?? '') ?></td>
                            <td><?= date('d M Y, D', strtotime($rec['attendance_date'])) ?></td>
                            <td class="small">
                                <span class="badge bg-<?= $recShift['season'] === 'Summer' ? 'warning text-dark' : 'primary' ?>" style="font-size:10px;">
                                    <?= $recShift['season'] ?>
                                </span>
                                <br><span class="text-muted" style="font-size:10px;"><?= $recShift['start'] ?>–<?= $recShift['end'] ?></span>
                            </td>
                            <td><?= $rec['check_in_time'] ? date('h:i A', strtotime($rec['check_in_time'])) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $rec['check_out_time'] ? date('h:i A', strtotime($rec['check_out_time'])) : '<span class="text-muted">—</span>' ?></td>
                            <td class="<?= $hrsColor ?>"><?= $recHrsDisplay ?></td>
                            <td>
                                <?php
                                $badgeMap = ['present' => 'bg-success', 'absent' => 'bg-danger', 'half_day' => 'bg-warning text-dark', 'on_leave' => 'bg-info'];
                                ?>
                                <span class="badge <?= $badgeMap[$rec['status']] ?? 'bg-secondary' ?>"><?= ucfirst(str_replace('_', ' ', $rec['status'])) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($rec['google_maps_url'])): ?>
                                    <a href="<?= htmlspecialchars($rec['google_maps_url']) ?>" target="_blank" class="btn btn-outline-info btn-sm" title="<?= $rec['latitude'] ?>, <?= $rec['longitude'] ?>">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($rec['notes'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
