<?php
/**
 * Admin Panel - Sales Visit Schedules Management
 * Assign district/area schedules for each salesperson by weekday.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Visit Schedules';
$adminPage = 'sales_visit_schedules';

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Fetch all active executives
$executives = db_fetch_all('SELECT id, name, designation, district, phone FROM sales_executives WHERE is_active = 1 ORDER BY name ASC');

// Selected executive
$selectedExecId = (int)($_GET['exec_id'] ?? ($executives[0]['id'] ?? 0));

// Selected executive details
$selectedExec = null;
if ($selectedExecId > 0) {
    $selectedExec = db_fetch('SELECT * FROM sales_executives WHERE id = ?', [$selectedExecId]);
}

// Handle save schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $execId = (int)($_POST['executive_id'] ?? 0);
    if ($execId <= 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid executive selected.'];
    } else {
        try {
            // Delete existing schedule for this exec
            db_query('DELETE FROM sales_visit_schedules WHERE executive_id = ?', [$execId]);

            // Insert new schedule for each day
            for ($d = 0; $d < 7; $d++) {
                $isOff = isset($_POST['is_off'][$d]) ? 1 : 0;
                $district = trim($_POST['district'][$d] ?? '');
                $areaName = trim($_POST['area_name'][$d] ?? '');

                if ($isOff || !empty($district)) {
                    db_query('INSERT INTO sales_visit_schedules (executive_id, day_of_week, district, area_name, is_off, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [
                        $execId, $d, $isOff ? '' : $district, $isOff ? '' : $areaName, $isOff, $d
                    ]);
                }
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Weekly schedule saved successfully for ' . ($selectedExec['name'] ?? 'executive') . '.'];
            header('Location: ' . base_url('admin/sales_visit_schedules.php?exec_id=' . $execId));
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Handle copy schedule from one exec to another
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_schedule'])) {
    $fromExec = (int)($_POST['from_exec_id'] ?? 0);
    $toExec = (int)($_POST['to_exec_id'] ?? 0);
    if ($fromExec > 0 && $toExec > 0 && $fromExec !== $toExec) {
        try {
            $sourceSchedule = db_fetch_all('SELECT * FROM sales_visit_schedules WHERE executive_id = ?', [$fromExec]);
            db_query('DELETE FROM sales_visit_schedules WHERE executive_id = ?', [$toExec]);
            foreach ($sourceSchedule as $s) {
                db_query('INSERT INTO sales_visit_schedules (executive_id, day_of_week, district, area_name, is_off, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [
                    $toExec, $s['day_of_week'], $s['district'], $s['area_name'], $s['is_off'], $s['sort_order']
                ]);
            }
            $toExecName = db_fetch('SELECT name FROM sales_executives WHERE id = ?', [$toExec])['name'] ?? '';
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Schedule copied to ' . $toExecName . ' successfully.'];
            header('Location: ' . base_url('admin/sales_visit_schedules.php?exec_id=' . $toExec));
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Fetch current schedule for selected exec
$currentSchedule = [];
if ($selectedExecId > 0) {
    try {
        $rows = db_fetch_all('SELECT * FROM sales_visit_schedules WHERE executive_id = ? ORDER BY day_of_week ASC', [$selectedExecId]);
        foreach ($rows as $r) {
            $currentSchedule[(int)$r['day_of_week']] = $r;
        }
    } catch (PDOException $e) { /* safe */ }
}

// Fetch all distinct districts used in parties for suggestion
$allDistricts = [];
try {
    $allDistricts = db_fetch_all('SELECT DISTINCT district FROM sales_parties WHERE district != "" ORDER BY district ASC');
} catch (PDOException $e) { /* safe */ }

// Overview: all executives schedules summary
$allSchedules = [];
try {
    $allSchedules = db_fetch_all('SELECT svs.*, se.name as exec_name FROM sales_visit_schedules svs JOIN sales_executives se ON svs.executive_id = se.id WHERE se.is_active = 1 ORDER BY se.name, svs.day_of_week');
} catch (PDOException $e) { /* safe */ }
$schedOverview = [];
foreach ($allSchedules as $as) {
    $schedOverview[$as['executive_id']]['name'] = $as['exec_name'];
    $schedOverview[$as['executive_id']]['days'][(int)$as['day_of_week']] = $as;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-route me-2 text-primary"></i>Visit Schedules</h2>
            <p class="text-muted mb-0">Assign weekly district/area schedules for each salesperson</p>
        </div>
    </div>

    <!-- Executive Selector -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-user-tie me-2 text-primary"></i>Select Sales Executive</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Sales Executive</label>
                    <select id="execSelector" class="form-select form-select-lg" onchange="window.location.href='<?= base_url('admin/sales_visit_schedules.php') ?>?exec_id='+this.value">
                        <option value="">— Select Executive —</option>
                        <?php foreach ($executives as $ex): ?>
                            <option value="<?= $ex['id'] ?>" <?= $ex['id'] == $selectedExecId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['name']) ?> — <?= htmlspecialchars($ex['designation'] ?? 'Sales Executive') ?> (<?= htmlspecialchars($ex['district']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedExec): ?>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded">
                        <div class="fw-bold"><?= htmlspecialchars($selectedExec['name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($selectedExec['phone']) ?> · <?= htmlspecialchars($selectedExec['district']) ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($selectedExec): ?>
    <!-- Schedule Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-calendar-week me-2 text-success"></i>Weekly Schedule — <?= htmlspecialchars($selectedExec['name']) ?></h5>
            <span class="badge bg-primary"><?= count($currentSchedule) ?> days configured</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="save_schedule" value="1">
                <input type="hidden" name="executive_id" value="<?= $selectedExecId ?>">

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:130px;">Day</th>
                                <th>District *</th>
                                <th>Area / Route Name</th>
                                <th style="width:90px;" class="text-center">Week Off</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($d = 0; $d < 7; $d++):
                                $sch = $currentSchedule[$d] ?? null;
                                $isOff = $sch ? (int)$sch['is_off'] : ($d === 0 ? 1 : 0);
                                $dist = $sch ? ($sch['district'] ?? '') : '';
                                $area = $sch ? ($sch['area_name'] ?? '') : '';
                            ?>
                            <tr class="<?= $d === (int)date('w') ? 'table-success' : '' ?>" id="day-row-<?= $d ?>">
                                <td>
                                    <div class="fw-bold"><?= $dayNames[$d] ?></div>
                                    <?php if ($d === (int)date('w')): ?>
                                        <small class="text-success fw-semibold">Today</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" name="district[<?= $d ?>]" id="district-<?= $d ?>" class="form-control" value="<?= htmlspecialchars($dist) ?>" list="districtList" placeholder="e.g. Sopore, Baramulla..." <?= $isOff ? 'disabled' : '' ?>>
                                </td>
                                <td>
                                    <input type="text" name="area_name[<?= $d ?>]" id="area-<?= $d ?>" class="form-control" value="<?= htmlspecialchars($area) ?>" placeholder="Optional sub-area or route" <?= $isOff ? 'disabled' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" name="is_off[<?= $d ?>]" value="1" id="off-<?= $d ?>" <?= $isOff ? 'checked' : '' ?> onchange="toggleDayOff(<?= $d ?>, this.checked)">
                                    </div>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <datalist id="districtList">
                    <?php foreach ($allDistricts as $ad): ?>
                        <option value="<?= htmlspecialchars($ad['district']) ?>">
                    <?php endforeach; ?>
                </datalist>

                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Save Schedule
                    </button>
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>Schedule is applied every week automatically.
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Copy Schedule -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fas fa-copy me-2 text-info"></i>Copy Schedule to Another Executive</h6>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="copy_schedule" value="1">
                <input type="hidden" name="from_exec_id" value="<?= $selectedExecId ?>">
                <div class="col-md-6">
                    <label class="form-label">Copy <strong><?= htmlspecialchars($selectedExec['name']) ?></strong>'s schedule to:</label>
                    <select name="to_exec_id" class="form-select" required>
                        <option value="">— Select Target Executive —</option>
                        <?php foreach ($executives as $ex): ?>
                            <?php if ($ex['id'] != $selectedExecId): ?>
                                <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?> (<?= htmlspecialchars($ex['district']) ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-info" onclick="return confirm('This will overwrite the target executive\'s entire schedule. Continue?')">
                        <i class="fas fa-copy me-1"></i>Copy Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Overview: All Executives -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-th me-2 text-info"></i>All Executives — Weekly Overview</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" style="white-space:nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th>Executive</th>
                            <?php for ($d = 0; $d < 7; $d++): ?>
                                <th class="text-center <?= $d === (int)date('w') ? 'table-success' : '' ?>"><?= substr($dayNames[$d], 0, 3) ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedOverview)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No schedules configured yet. Select an executive above to assign their weekly schedule.</td></tr>
                        <?php else: ?>
                            <?php foreach ($schedOverview as $eId => $ov): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('admin/sales_visit_schedules.php?exec_id=' . $eId) ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($ov['name']) ?></a>
                                </td>
                                <?php for ($d = 0; $d < 7; $d++):
                                    $ds = $ov['days'][$d] ?? null;
                                ?>
                                <td class="text-center <?= $d === (int)date('w') ? 'table-success' : '' ?>">
                                    <?php if ($ds && $ds['is_off']): ?>
                                        <span class="badge bg-secondary">Off</span>
                                    <?php elseif ($ds && !empty($ds['district'])): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($ds['district']) ?></span>
                                        <?php if (!empty($ds['area_name'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($ds['area_name']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDayOff(day, isOff) {
    document.getElementById('district-' + day).disabled = isOff;
    document.getElementById('area-' + day).disabled = isOff;
    if (isOff) {
        document.getElementById('district-' + day).value = '';
        document.getElementById('area-' + day).value = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
