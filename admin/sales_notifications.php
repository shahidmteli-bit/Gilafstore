<?php
/**
 * Admin — Sales Portal Notifications Management
 * Send notifications to sales executives, distributors, and manage reminders
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Notifications — Admin';
$adminPage = 'sales_notifications';

// Ensure notifications table exists
$db = get_db_connection();
$db->exec("CREATE TABLE IF NOT EXISTS sales_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    executive_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exec_read (executive_id, is_read),
    INDEX idx_created (created_at)
)");

// Ensure login_reminders table
$db->exec("CREATE TABLE IF NOT EXISTS sales_login_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reminder_type ENUM('login','logout') NOT NULL,
    reminder_time TIME NOT NULL,
    days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    is_active TINYINT(1) DEFAULT 1,
    message VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
// Add days_of_week column if missing (upgrade path)
try { $db->exec("ALTER TABLE sales_login_reminders ADD COLUMN days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5' AFTER reminder_time"); } catch (PDOException $e) {}

// PRG: Read flash from session if redirected after POST
$flash = '';
$flashType = 'success';
if (!empty($_SESSION['sn_flash'])) {
    $flash = $_SESSION['sn_flash']['msg'];
    $flashType = $_SESSION['sn_flash']['type'] ?? 'success';
    unset($_SESSION['sn_flash']);
}

// Handle form submissions — redirect after to prevent resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectTab = '';

    if ($action === 'send_notification') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $target = $_POST['target'] ?? 'all';
        $link = trim($_POST['link'] ?? '') ?: null;

        if (empty($title) || empty($message)) {
            $_SESSION['sn_flash'] = ['msg' => 'Title and message are required.', 'type' => 'danger'];
        } else {
            if ($target === 'all') {
                $executives = db_fetch_all('SELECT id FROM sales_executives WHERE is_active = 1');
            } elseif ($target === 'specific') {
                $execIds = $_POST['executive_ids'] ?? [];
                $executives = [];
                foreach ($execIds as $eid) {
                    $executives[] = ['id' => (int)$eid];
                }
            } else {
                $district = $_POST['target_district'] ?? '';
                $executives = db_fetch_all('SELECT id FROM sales_executives WHERE is_active = 1 AND district = ?', [$district]);
            }

            $count = 0;
            foreach ($executives as $exec) {
                db_query(
                    'INSERT INTO sales_notifications (executive_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)',
                    [(int)$exec['id'], $type, $title, $message, $link]
                );
                $count++;
            }

            $_SESSION['sn_flash'] = ['msg' => "Notification sent to {$count} executive(s) successfully!", 'type' => 'success'];
        }
        $redirectTab = '#historyPanel';
    }

    if ($action === 'save_reminder') {
        $rType = $_POST['reminder_type'] ?? 'login';
        $rAmpm = $_POST['reminder_ampm'] ?? 'AM';
        $rHour = (int)($_POST['reminder_hour'] ?? 9);
        $rMin = (int)($_POST['reminder_minute'] ?? 0);
        if ($rAmpm === 'PM' && $rHour < 12) $rHour += 12;
        if ($rAmpm === 'AM' && $rHour === 12) $rHour = 0;
        $rTime = sprintf('%02d:%02d:00', $rHour, $rMin);
        $rDays = $_POST['reminder_days'] ?? [];
        $rDaysStr = !empty($rDays) ? implode(',', $rDays) : '1,2,3,4,5';
        $rMsg = trim($_POST['reminder_message'] ?? '');
        $rActive = isset($_POST['reminder_active']) ? 1 : 0;
        $rId = (int)($_POST['reminder_id'] ?? 0);

        if (empty($rMsg)) {
            $rMsg = $rType === 'login' ? 'Time to start your day! Please login to the Sales Portal.' : 'End of day! Please logout and submit your daily report.';
        }

        if ($rId) {
            db_query('UPDATE sales_login_reminders SET reminder_type=?, reminder_time=?, days_of_week=?, message=?, is_active=? WHERE id=?',
                [$rType, $rTime, $rDaysStr, $rMsg, $rActive, $rId]);
            $_SESSION['sn_flash'] = ['msg' => 'Reminder updated successfully!', 'type' => 'success'];
        } else {
            db_query('INSERT INTO sales_login_reminders (reminder_type, reminder_time, days_of_week, message, is_active) VALUES (?, ?, ?, ?, ?)',
                [$rType, $rTime, $rDaysStr, $rMsg, $rActive]);
            $_SESSION['sn_flash'] = ['msg' => 'Reminder created successfully!', 'type' => 'success'];
        }
        $redirectTab = '#remindersPanel';
    }

    if ($action === 'delete_reminder') {
        $rId = (int)($_POST['reminder_id'] ?? 0);
        if ($rId) {
            db_query('DELETE FROM sales_login_reminders WHERE id = ?', [$rId]);
            $_SESSION['sn_flash'] = ['msg' => 'Reminder deleted.', 'type' => 'success'];
        }
        $redirectTab = '#remindersPanel';
    }

    if ($action === 'delete_notification') {
        $nId = (int)($_POST['notification_id'] ?? 0);
        if ($nId) {
            db_query('DELETE FROM sales_notifications WHERE id = ?', [$nId]);
            $_SESSION['sn_flash'] = ['msg' => 'Notification deleted.', 'type' => 'success'];
        }
        $redirectTab = '#historyPanel';
    }

    // PRG redirect
    header('Location: ' . base_url('admin/sales_notifications.php') . ($redirectTab ? '?tab=' . urlencode($redirectTab) : ''));
    exit;
}

// Fetch data
$executives = db_fetch_all('SELECT id, name, email, district, phone FROM sales_executives WHERE is_active = 1 ORDER BY name');
$districts = array_unique(array_column($executives, 'district'));
sort($districts);

$reminders = db_fetch_all('SELECT * FROM sales_login_reminders ORDER BY reminder_time');

// Recent notifications sent (last 50)
$recentNotifs = db_fetch_all(
    'SELECT sn.*, se.name as exec_name, se.district as exec_district 
     FROM sales_notifications sn 
     JOIN sales_executives se ON sn.executive_id = se.id 
     WHERE sn.type IN ("admin_announcement","admin_alert","admin_reminder","info","reminder","alert") 
     ORDER BY sn.created_at DESC LIMIT 50'
);

// Group recent notifs by batch (same title + created_at minute)
$notifGroups = [];
foreach ($recentNotifs as $n) {
    $key = $n['title'] . '|' . substr($n['created_at'], 0, 16);
    if (!isset($notifGroups[$key])) {
        $notifGroups[$key] = [
            'title' => $n['title'],
            'message' => $n['message'],
            'type' => $n['type'],
            'created_at' => $n['created_at'],
            'recipients' => [],
            'read_count' => 0,
            'total' => 0,
            'ids' => [],
        ];
    }
    $notifGroups[$key]['recipients'][] = $n['exec_name'];
    $notifGroups[$key]['total']++;
    $notifGroups[$key]['ids'][] = $n['id'];
    if ($n['is_read']) $notifGroups[$key]['read_count']++;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-bell text-primary me-2"></i>Sales Notifications</h2>
            <p class="text-muted mb-0">Send notifications to sales executives and manage login/logout reminders.</p>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="notifTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="send-tab" data-bs-toggle="tab" data-bs-target="#sendPanel" type="button" role="tab">
                <i class="fas fa-paper-plane me-1"></i> Send Notification
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reminders-tab" data-bs-toggle="tab" data-bs-target="#remindersPanel" type="button" role="tab">
                <i class="fas fa-clock me-1"></i> Login/Logout Reminders
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#historyPanel" type="button" role="tab">
                <i class="fas fa-history me-1"></i> Sent History
            </button>
        </li>
    </ul>

    <div class="tab-content" id="notifTabContent">

        <!-- ═══════════════════════════════════════════
             TAB 1: SEND NOTIFICATION
             ═══════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="sendPanel" role="tabpanel">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 fw-semibold"><i class="fas fa-paper-plane text-primary me-2"></i>Compose Notification</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="sendNotifForm">
                                <input type="hidden" name="action" value="send_notification">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Notification Type</label>
                                        <select name="type" class="form-select" id="notifType">
                                            <option value="admin_announcement">📢 Announcement</option>
                                            <option value="admin_alert">🚨 Alert</option>
                                            <option value="admin_reminder">⏰ Reminder</option>
                                            <option value="info">ℹ️ Information</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Send To</label>
                                        <select name="target" class="form-select" id="notifTarget" onchange="toggleTargetOptions()">
                                            <option value="all">All Sales Executives</option>
                                            <option value="district">By District</option>
                                            <option value="specific">Specific Executives</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- District selector -->
                                <div class="mb-3 d-none" id="districtSelector">
                                    <label class="form-label fw-semibold">Select District</label>
                                    <select name="target_district" class="form-select">
                                        <?php foreach ($districts as $d): ?>
                                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Specific executives selector -->
                                <div class="mb-3 d-none" id="execSelector">
                                    <label class="form-label fw-semibold">Select Executives</label>
                                    <div class="border rounded p-3" style="max-height:200px;overflow-y:auto;">
                                        <?php foreach ($executives as $ex): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="executive_ids[]" value="<?= $ex['id'] ?>" id="exec_<?= $ex['id'] ?>">
                                            <label class="form-check-label" for="exec_<?= $ex['id'] ?>">
                                                <strong><?= htmlspecialchars($ex['name']) ?></strong>
                                                <small class="text-muted ms-1">(<?= htmlspecialchars($ex['district']) ?>)</small>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title *</label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. New pricing update" required maxlength="255">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Message *</label>
                                    <textarea name="message" class="form-control" rows="4" placeholder="Write your notification message..." required></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Link (Optional)</label>
                                    <input type="text" name="link" class="form-control" placeholder="e.g. orders.php or parties.php">
                                    <small class="text-muted">Page link within the sales portal (optional)</small>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-1"></i> Send Notification
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i> Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quick Templates -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0 fw-semibold"><i class="fas fa-bolt text-warning me-2"></i>Quick Templates</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="#" class="list-group-item list-group-item-action" onclick="useTemplate('Meeting Reminder','Team meeting scheduled. Please join on time.','admin_reminder');return false;">
                                    <div class="fw-semibold"><i class="fas fa-users text-info me-2"></i>Meeting Reminder</div>
                                    <small class="text-muted">Team meeting scheduled...</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action" onclick="useTemplate('Price Update','Product prices have been updated. Please check the latest pricing in the app.','admin_announcement');return false;">
                                    <div class="fw-semibold"><i class="fas fa-tags text-success me-2"></i>Price Update</div>
                                    <small class="text-muted">Product prices updated...</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action" onclick="useTemplate('Target Reminder','Monthly target deadline approaching. Please ensure all orders are submitted.','admin_alert');return false;">
                                    <div class="fw-semibold"><i class="fas fa-bullseye text-danger me-2"></i>Target Reminder</div>
                                    <small class="text-muted">Monthly target deadline...</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action" onclick="useTemplate('New Product Launch','New products have been added to the catalog. Check them out!','admin_announcement');return false;">
                                    <div class="fw-semibold"><i class="fas fa-box-open text-primary me-2"></i>New Product Launch</div>
                                    <small class="text-muted">New products added...</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action" onclick="useTemplate('Holiday Notice','Office will remain closed on the upcoming holiday. Plan your visits accordingly.','info');return false;">
                                    <div class="fw-semibold"><i class="fas fa-calendar-alt text-secondary me-2"></i>Holiday Notice</div>
                                    <small class="text-muted">Holiday announcement...</small>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action" onclick="useTemplate('Daily Report Reminder','Please submit your daily visit report before end of day.','admin_reminder');return false;">
                                    <div class="fw-semibold"><i class="fas fa-file-alt text-warning me-2"></i>Daily Report</div>
                                    <small class="text-muted">Submit daily report...</small>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="card shadow-sm border-0 mt-3">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Quick Stats</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Active Executives</span>
                                <strong><?= count($executives) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Districts</span>
                                <strong><?= count($districts) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Active Reminders</span>
                                <strong><?= count(array_filter($reminders, fn($r) => $r['is_active'])) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════
             TAB 2: LOGIN/LOGOUT REMINDERS
             ═══════════════════════════════════════════ -->
        <div class="tab-pane fade" id="remindersPanel" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body p-0">
                            <form method="POST" id="reminderForm">
                                <input type="hidden" name="action" value="save_reminder">
                                <input type="hidden" name="reminder_id" id="reminderId" value="">

                                <!-- Header bar -->
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="background:#f8f9fa;">
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none text-dark" onclick="resetReminderForm()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <h6 class="mb-0 fw-bold" id="reminderFormTitle">Add Schedule</h6>
                                    <button type="submit" class="btn btn-sm btn-link text-decoration-none fw-bold text-primary">Save</button>
                                </div>

                                <!-- Scroll Wheel Time Picker -->
                                <div class="sched-picker-wrap" style="background:linear-gradient(135deg,#4ecdc4,#44b09e);padding:24px 0 20px;position:relative;">
                                    <!-- Selection highlight bar -->
                                    <div style="position:absolute;left:10%;right:10%;top:50%;transform:translateY(-50%);height:44px;background:rgba(255,255,255,0.2);border-radius:10px;pointer-events:none;z-index:1;"></div>
                                    <div class="d-flex justify-content-center align-items-center gap-2" style="position:relative;z-index:2;">
                                        <!-- AM/PM wheel -->
                                        <div class="sched-wheel" id="wheelAmpm" data-field="reminder_ampm">
                                            <div class="sched-wheel-inner">
                                                <div class="sched-wheel-item" data-value="AM">AM</div>
                                                <div class="sched-wheel-item" data-value="PM">PM</div>
                                            </div>
                                        </div>
                                        <!-- Hour wheel -->
                                        <div class="sched-wheel" id="wheelHour" data-field="reminder_hour">
                                            <div class="sched-wheel-inner">
                                                <?php for ($h = 1; $h <= 12; $h++): ?>
                                                <div class="sched-wheel-item" data-value="<?= $h ?>"><?= $h ?></div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <!-- Minute wheel -->
                                        <div class="sched-wheel" id="wheelMinute" data-field="reminder_minute">
                                            <div class="sched-wheel-inner">
                                                <?php for ($m = 0; $m <= 59; $m++): ?>
                                                <div class="sched-wheel-item" data-value="<?= $m ?>"><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Hidden inputs -->
                                    <input type="hidden" name="reminder_ampm" id="inputAmpm" value="AM">
                                    <input type="hidden" name="reminder_hour" id="inputHour" value="9">
                                    <input type="hidden" name="reminder_minute" id="inputMinute" value="0">
                                </div>

                                <!-- Day of Week Selector -->
                                <div class="px-4 py-3 border-bottom" style="background:#fff;">
                                    <div class="text-center text-muted small mb-2">Select days for this reminder</div>
                                    <div class="d-flex justify-content-center gap-2" id="daySelector">
                                        <?php
                                        $dayLabels = ['S','M','T','W','T','F','S'];
                                        $dayValues = [0,1,2,3,4,5,6];
                                        foreach ($dayLabels as $i => $dl):
                                        ?>
                                        <label class="sched-day-btn">
                                            <input type="checkbox" name="reminder_days[]" value="<?= $dayValues[$i] ?>" <?= in_array($dayValues[$i], [1,2,3,4,5]) ? 'checked' : '' ?>>
                                            <span><?= $dl ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Reminder Type Toggle -->
                                <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center" style="background:#fff;">
                                    <div>
                                        <div class="fw-semibold" id="reminderTypeLabel">Login Reminder</div>
                                        <small class="text-muted">Remind executives to log in</small>
                                    </div>
                                    <div class="sched-toggle-wrap">
                                        <select name="reminder_type" class="form-select form-select-sm" id="reminderType" onchange="updateTypeLabel()" style="width:130px;">
                                            <option value="login">🟢 Login</option>
                                            <option value="logout">🔴 Logout</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Active Toggle -->
                                <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center" style="background:#fff;">
                                    <div class="fw-semibold">Active</div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="reminder_active" id="reminderActive" checked style="width:42px;height:22px;">
                                    </div>
                                </div>

                                <!-- Message -->
                                <div class="px-4 py-3" style="background:#fff;">
                                    <label class="form-label fw-semibold small text-muted">Custom Message (optional)</label>
                                    <textarea name="reminder_message" class="form-control" rows="2" id="reminderMsg" placeholder="Leave empty for default message" style="font-size:13px;"></textarea>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 fw-semibold"><i class="fas fa-list text-primary me-2"></i>Active Reminders</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($reminders)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-clock fa-3x mb-3 opacity-25"></i>
                                <p>No reminders configured yet.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Time</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $dayNames = ['Su','Mo','Tu','We','Th','Fr','Sa'];
                                        foreach ($reminders as $rem):
                                            $remDays = explode(',', $rem['days_of_week'] ?? '1,2,3,4,5');
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($rem['reminder_type'] === 'login'): ?>
                                                    <span class="badge bg-success"><i class="fas fa-sign-in-alt me-1"></i>Login</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-sign-out-alt me-1"></i>Logout</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold"><?= date('h:i A', strtotime($rem['reminder_time'])) ?></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php foreach ($dayNames as $di => $dn): ?>
                                                    <span class="badge rounded-circle d-flex align-items-center justify-content-center <?= in_array($di, $remDays) ? 'bg-primary' : 'bg-light text-muted' ?>" style="width:24px;height:24px;font-size:9px;"><?= $dn[0] ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($rem['is_active']): ?>
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editReminder(<?= htmlspecialchars(json_encode($rem)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this reminder?')">
                                                    <input type="hidden" name="action" value="delete_reminder">
                                                    <input type="hidden" name="reminder_id" value="<?= $rem['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
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

                    <!-- How it works -->
                    <div class="card shadow-sm border-0 mt-3">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-2"><i class="fas fa-info-circle text-info me-2"></i>How Reminders Work</h6>
                            <ul class="mb-0 small text-muted">
                                <li><strong>Login Reminders</strong> — Sent to all active executives at the configured time if they haven't logged in yet today.</li>
                                <li><strong>Logout Reminders</strong> — Sent to all executives who are still logged in at the configured time.</li>
                                <li>Reminders are checked automatically every time the app syncs (every 30 seconds).</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════
             TAB 3: SENT HISTORY
             ═══════════════════════════════════════════ -->
        <div class="tab-pane fade" id="historyPanel" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold"><i class="fas fa-history text-primary me-2"></i>Recently Sent Notifications</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifGroups)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                        <p>No notifications sent yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Title & Message</th>
                                    <th>Recipients</th>
                                    <th>Read</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifGroups as $ng):
                                    $typeLabels = [
                                        'admin_announcement' => ['label' => 'Announcement', 'class' => 'primary', 'icon' => 'bullhorn'],
                                        'admin_alert' => ['label' => 'Alert', 'class' => 'danger', 'icon' => 'exclamation-triangle'],
                                        'admin_reminder' => ['label' => 'Reminder', 'class' => 'warning', 'icon' => 'clock'],
                                        'info' => ['label' => 'Info', 'class' => 'info', 'icon' => 'info-circle'],
                                        'reminder' => ['label' => 'Auto Reminder', 'class' => 'secondary', 'icon' => 'robot'],
                                        'alert' => ['label' => 'System', 'class' => 'dark', 'icon' => 'cog'],
                                    ];
                                    $tl = $typeLabels[$ng['type']] ?? $typeLabels['info'];
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $tl['class'] ?>">
                                            <i class="fas fa-<?= $tl['icon'] ?> me-1"></i><?= $tl['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($ng['title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($ng['message'], 0, 80, '...')) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= $ng['total'] ?> person(s)</span>
                                        <br><small class="text-muted"><?= htmlspecialchars(implode(', ', array_slice($ng['recipients'], 0, 3))) ?><?= $ng['total'] > 3 ? '...' : '' ?></small>
                                    </td>
                                    <td>
                                        <div class="progress" style="height:6px;width:60px;">
                                            <div class="progress-bar bg-success" style="width:<?= $ng['total'] ? round($ng['read_count'] / $ng['total'] * 100) : 0 ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $ng['read_count'] ?>/<?= $ng['total'] ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= date('d M Y, h:i A', strtotime($ng['created_at'])) ?></small>
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

    </div>
</div>

<style>
/* ── Scroll Wheel Time Picker ── */
.sched-wheel {
    width: 72px;
    height: 132px;
    overflow: hidden;
    position: relative;
    cursor: grab;
    -webkit-user-select: none;
    user-select: none;
}
.sched-wheel-inner {
    transition: transform 0.15s ease-out;
}
.sched-wheel-item {
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
    color: rgba(255,255,255,0.35);
    transition: color 0.15s, font-size 0.15s;
}
.sched-wheel-item.active {
    color: #fff;
    font-size: 26px;
}
/* ── Day of Week Buttons ── */
.sched-day-btn {
    cursor: pointer;
    margin: 0;
}
.sched-day-btn input { display: none; }
.sched-day-btn span {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.15s;
    background: #fff;
}
.sched-day-btn input:checked + span {
    background: #4ecdc4;
    border-color: #4ecdc4;
    color: #fff;
}
.sched-day-btn:hover span {
    border-color: #4ecdc4;
}
</style>

<script>
// ── Notification form helpers ──
function toggleTargetOptions() {
    var target = document.getElementById('notifTarget').value;
    document.getElementById('districtSelector').classList.toggle('d-none', target !== 'district');
    document.getElementById('execSelector').classList.toggle('d-none', target !== 'specific');
}

function useTemplate(title, message, type) {
    document.querySelector('[name="title"]').value = title;
    document.querySelector('[name="message"]').value = message;
    document.getElementById('notifType').value = type;
    document.getElementById('sendPanel').scrollIntoView({behavior: 'smooth', block: 'start'});
}

function updateTypeLabel() {
    var t = document.getElementById('reminderType').value;
    var label = document.getElementById('reminderTypeLabel');
    if (t === 'login') {
        label.innerHTML = 'Login Reminder';
        label.nextElementSibling.textContent = 'Remind executives to log in';
    } else {
        label.innerHTML = 'Logout Reminder';
        label.nextElementSibling.textContent = 'Remind executives to log out';
    }
}

// ── Scroll Wheel Time Picker ──
(function() {
    var ITEM_H = 44;

    function initWheel(el) {
        var inner = el.querySelector('.sched-wheel-inner');
        var items = inner.querySelectorAll('.sched-wheel-item');
        var count = items.length;
        var currentIdx = 0;
        var startY = 0, startOffset = 0, offset = 0, isDragging = false;

        function setIndex(idx) {
            idx = Math.max(0, Math.min(count - 1, idx));
            currentIdx = idx;
            offset = -idx * ITEM_H;
            inner.style.transform = 'translateY(' + (offset + ITEM_H) + 'px)';
            items.forEach(function(it, i) {
                it.classList.toggle('active', i === idx);
            });
            // Update hidden input
            var field = el.dataset.field;
            var val = items[idx].dataset.value;
            var input = document.getElementById('input' + field.replace('reminder_','').charAt(0).toUpperCase() + field.replace('reminder_','').slice(1));
            if (input) input.value = val;
        }

        // Mouse/touch events
        function onStart(e) {
            isDragging = true;
            startY = e.touches ? e.touches[0].clientY : e.clientY;
            startOffset = offset;
            inner.style.transition = 'none';
            el.style.cursor = 'grabbing';
        }
        function onMove(e) {
            if (!isDragging) return;
            e.preventDefault();
            var y = e.touches ? e.touches[0].clientY : e.clientY;
            var delta = y - startY;
            var newOffset = startOffset + delta;
            inner.style.transform = 'translateY(' + (newOffset + ITEM_H) + 'px)';
        }
        function onEnd(e) {
            if (!isDragging) return;
            isDragging = false;
            inner.style.transition = 'transform 0.15s ease-out';
            el.style.cursor = 'grab';
            var y = e.changedTouches ? e.changedTouches[0].clientY : e.clientY;
            var delta = y - startY;
            var moved = Math.round(delta / ITEM_H);
            setIndex(currentIdx - moved);
        }

        el.addEventListener('mousedown', onStart);
        el.addEventListener('touchstart', onStart, {passive: true});
        document.addEventListener('mousemove', onMove);
        document.addEventListener('touchmove', onMove, {passive: false});
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchend', onEnd);

        // Mouse wheel
        el.addEventListener('wheel', function(e) {
            e.preventDefault();
            setIndex(currentIdx + (e.deltaY > 0 ? 1 : -1));
        }, {passive: false});

        // Click on item
        items.forEach(function(it, i) {
            it.addEventListener('click', function() { setIndex(i); });
        });

        // Init
        setIndex(0);

        // Expose setIndex
        el._setIndex = setIndex;
        el._getIndex = function() { return currentIdx; };
        el._items = items;
    }

    // Init all wheels
    document.querySelectorAll('.sched-wheel').forEach(initWheel);

    // Set default: 9:00 AM
    var wAmpm = document.getElementById('wheelAmpm');
    var wHour = document.getElementById('wheelHour');
    var wMin = document.getElementById('wheelMinute');
    if (wAmpm) wAmpm._setIndex(0); // AM
    if (wHour) wHour._setIndex(8); // 9 (index 8 since 1=0,2=1,...9=8)
    if (wMin) wMin._setIndex(0);   // 00

    // Expose for editReminder
    window._schedWheels = { ampm: wAmpm, hour: wHour, minute: wMin };
})();

// ── Edit Reminder ──
function editReminder(rem) {
    document.getElementById('reminderId').value = rem.id;
    document.getElementById('reminderFormTitle').textContent = 'Edit Schedule';
    document.getElementById('reminderType').value = rem.reminder_type;
    updateTypeLabel();
    document.getElementById('reminderMsg').value = rem.message || '';
    document.getElementById('reminderActive').checked = rem.is_active == 1;

    // Parse time (HH:MM:SS) to 12h
    var parts = (rem.reminder_time || '09:00:00').split(':');
    var h24 = parseInt(parts[0]);
    var min = parseInt(parts[1]);
    var ampm = h24 >= 12 ? 'PM' : 'AM';
    var h12 = h24 % 12 || 12;

    // Set wheels
    var w = window._schedWheels;
    if (w.ampm) w.ampm._setIndex(ampm === 'AM' ? 0 : 1);
    if (w.hour) w.hour._setIndex(h12 - 1);
    if (w.minute) w.minute._setIndex(min);

    // Set days
    var days = (rem.days_of_week || '1,2,3,4,5').split(',');
    document.querySelectorAll('#daySelector input[type=checkbox]').forEach(function(cb) {
        cb.checked = days.indexOf(cb.value) > -1;
    });

    // Switch to reminders tab and scroll
    var tab = document.getElementById('reminders-tab');
    if (tab) { var bsTab = new bootstrap.Tab(tab); bsTab.show(); }
    setTimeout(function() {
        document.getElementById('remindersPanel').scrollIntoView({behavior: 'smooth', block: 'start'});
    }, 200);
}

function resetReminderForm() {
    document.getElementById('reminderId').value = '';
    document.getElementById('reminderFormTitle').textContent = 'Add Schedule';
    document.getElementById('reminderType').value = 'login';
    updateTypeLabel();
    document.getElementById('reminderMsg').value = '';
    document.getElementById('reminderActive').checked = true;

    // Reset wheels to 9:00 AM
    var w = window._schedWheels;
    if (w.ampm) w.ampm._setIndex(0);
    if (w.hour) w.hour._setIndex(8);
    if (w.minute) w.minute._setIndex(0);

    // Reset days to Mon-Fri
    document.querySelectorAll('#daySelector input[type=checkbox]').forEach(function(cb) {
        cb.checked = ['1','2','3','4','5'].indexOf(cb.value) > -1;
    });
}

// Auto-select tab from URL ?tab= parameter (after PRG redirect)
(function() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    if (tab) {
        var mapping = {
            '#remindersPanel': 'reminders-tab',
            '#historyPanel': 'history-tab',
            '#sendPanel': 'send-tab'
        };
        var tabId = mapping[tab];
        if (tabId) {
            var tabEl = document.getElementById(tabId);
            if (tabEl) { var bsTab = new bootstrap.Tab(tabEl); bsTab.show(); }
        }
    }
})();
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
