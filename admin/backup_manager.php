<?php
/**
 * Backup & Layout Manager — Enterprise Edition
 * ─────────────────────────────────────────────────────────
 * Full/Incremental backups with compression, deduplication,
 * database dumps, restore chains, layout locks per device,
 * auto-cleanup retention, checksum verification, audit log.
 * Admin-only with CSRF protection.
 */

// Capture fatal errors that try-catch cannot handle (memory, timeouts, etc.)
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<div style="font-family:sans-serif;max-width:600px;margin:40px auto;padding:20px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;">';
        echo '<h2 style="color:#991b1b;margin:0 0 10px;">⚠ Backup Manager Error</h2>';
        echo '<p style="color:#991b1b;"><strong>Type:</strong> ' . $err['type'] . '</p>';
        echo '<p style="color:#991b1b;"><strong>Message:</strong> ' . htmlspecialchars($err['message']) . '</p>';
        echo '<p style="color:#991b1b;"><strong>File:</strong> ' . htmlspecialchars(basename($err['file'])) . ':' . $err['line'] . '</p>';
        echo '<p style="margin-top:12px;"><a href="backup_manager.php" style="color:#1e40af;">← Back to Backup Manager</a></p>';
        echo '</div>';
    }
});

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/backup_engine.php';
require_admin();

$pageTitle = 'Backup & Layout Manager';
$adminPage = 'backup_manager';
$db = get_db_connection();
$engine = new BackupEngine($db);
$flash = '';
$flashType = 'success';

// Pick up flash from session (post-redirect-get for layout actions)
if (!empty($_SESSION['bm_flash'])) {
    $flash = $_SESSION['bm_flash'];
    $flashType = $_SESSION['bm_flash_type'] ?? 'success';
    unset($_SESSION['bm_flash'], $_SESSION['bm_flash_type']);
}

// Active tab from URL (e.g. ?tab=layout after lock/unlock redirect)
$activeTab = $_GET['tab'] ?? 'backup';

// Admin name helper
$adminName = $_SESSION['user']['name'] ?? $_SESSION['admin']['name'] ?? 'Admin';

// ─── AJAX endpoint for status polling ───
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'status' && isset($_GET['id'])) {
        echo json_encode($engine->getBackup($_GET['id']) ?: ['status'=>'not_found']);
    } elseif ($_GET['ajax'] === 'verify' && isset($_GET['id'])) {
        echo json_encode($engine->verifyChecksum($_GET['id']));
    } elseif ($_GET['ajax'] === 'disk') {
        echo json_encode($engine->getDiskUsage());
    } elseif ($_GET['ajax'] === 'dedup') {
        echo json_encode($engine->getDeduplicationStats());
    }
    exit;
}

// ─── Handle POST actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_full') {
        $name = trim($_POST['backup_name'] ?? '') ?: 'Full Backup ' . date('M j, Y');
        $desc = trim($_POST['description'] ?? '');
        // On Windows (XAMPP) use background spawn; on Linux (Hostinger) always run synchronous
        if (PHP_OS_FAMILY === 'Windows') {
            $cronScript = __DIR__ . DIRECTORY_SEPARATOR . 'backup_cron.php';
            $args = "--type=full --name=\"" . addslashes($name) . "\" --desc=\"" . addslashes($desc) . "\" --admin=\"" . addslashes($adminName) . "\"";
            bm_spawn_background($cronScript, $args);
            $flash = 'Full backup started in background. It will appear in the history when complete.';
        } else {
            $result = $engine->createFullBackup($name, $desc, $adminName);
            $flash = $result['success']
                ? 'Full backup created successfully! ID: <strong>' . htmlspecialchars($result['backup_id'] ?? '') . '</strong>'
                : 'Backup failed: ' . htmlspecialchars($result['error'] ?? 'Unknown error');
            $flashType = $result['success'] ? 'success' : 'danger';
        }

    } elseif ($action === 'create_incremental') {
        $name = trim($_POST['backup_name'] ?? '') ?: 'Incremental ' . date('M j, Y');
        $desc = trim($_POST['description'] ?? '');
        if (PHP_OS_FAMILY === 'Windows') {
            $cronScript = __DIR__ . DIRECTORY_SEPARATOR . 'backup_cron.php';
            $args = "--type=incremental --name=\"" . addslashes($name) . "\" --desc=\"" . addslashes($desc) . "\" --admin=\"" . addslashes($adminName) . "\"";
            bm_spawn_background($cronScript, $args);
            $flash = 'Incremental backup started in background.';
        } else {
            $result = $engine->createIncrementalBackup($name, $desc, $adminName);
            $flash = $result['success']
                ? 'Incremental backup created! ID: <strong>' . htmlspecialchars($result['backup_id'] ?? '') . '</strong>'
                : 'Backup failed: ' . htmlspecialchars($result['error'] ?? 'Unknown error');
            $flashType = $result['success'] ? 'success' : 'danger';
        }

    } elseif ($action === 'restore_backup') {
        $bid = $_POST['backup_id'] ?? '';
        $result = $engine->restoreBackup($bid, $adminName);
        if ($result['success']) {
            $flash = "Restored successfully! Pre-restore snapshot: <strong>{$result['pre_restore_id']}</strong>";
        } else {
            $flash = 'Restore failed: ' . ($result['error'] ?? 'Unknown error');
            $flashType = 'danger';
        }

    } elseif ($action === 'delete_backup') {
        $bid = $_POST['backup_id'] ?? '';
        $result = $engine->deleteBackup($bid, $adminName);
        $flash = $result['success'] ? 'Backup deleted.' : ('Delete failed: ' . ($result['error'] ?? ''));
        $flashType = $result['success'] ? 'success' : 'danger';

    } elseif ($action === 'lock_layout' || $action === 'unlock_layout') {
        $cat = $_POST['category'] ?? '';
        $result = ($action === 'lock_layout')
            ? $engine->lockLayout($cat, $adminName)
            : $engine->unlockLayout($cat, $adminName);
        $label = ucfirst($cat);
        $verb = ($action === 'lock_layout') ? 'locked' : 'unlocked';
        $msg = $result['success']
            ? "{$label} layout <strong>{$verb}</strong>. {$result['affected']} files affected."
            : "Failed: " . ($result['error'] ?? '');
        $ft = $result['success'] ? 'success' : 'danger';
        $_SESSION['bm_flash'] = $msg;
        $_SESSION['bm_flash_type'] = $ft;
        header('Location: backup_manager.php?tab=layout');
        exit;

    } elseif ($action === 'run_cleanup') {
        $result = $engine->autoCleanup();
        $flash = "Cleanup complete: {$result['archived']} backups archived, {$result['freed_mb']} MB freed.";
    }
  } catch (\Throwable $e) {
    $flash = 'Error: ' . htmlspecialchars($e->getMessage()) . ' in ' . htmlspecialchars(basename($e->getFile())) . ':' . $e->getLine();
    $flashType = 'danger';
  }
}

// ─── Import legacy backups ───
$engine->importLegacyBackups();

// ─── Fetch display data ───
$backups   = $engine->getAllBackups();
$locks     = $engine->getLayoutLockState();
$auditLog  = $engine->getAuditLog(50);
$diskUsage = $engine->getDiskUsage();
$dedupStats = $engine->getDeduplicationStats();
$csrfToken = generate_csrf_token();

// CSS file info helper
function bm_file_info(string $cssDir, string $projectRoot, string $file): array {
    $p = str_ends_with($file, '.css') ? $cssDir . $file : $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!file_exists($p)) return ['exists'=>false,'file'=>$file,'size'=>0,'modified'=>0,'writable'=>false];
    return ['exists'=>true,'file'=>$file,'size'=>filesize($p),'modified'=>filemtime($p),'writable'=>is_writable($p)];
}

/**
 * Spawn a background PHP process, OS-aware.
 * Windows: start /B, Linux: nohup ... &
 */
function bm_spawn_background(string $script, string $args): void {
    $phpBin = PHP_BINARY; // auto-detects current PHP binary on any OS
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "start /B \"\" \"{$phpBin}\" \"{$script}\" {$args}";
    } else {
        $cmd = "nohup " . escapeshellarg($phpBin) . " " . escapeshellarg($script) . " {$args} > /dev/null 2>&1 &";
    }
    pclose(popen($cmd, 'r'));
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ─── Tabs ─── */
.bm-tabs{display:flex;gap:4px;border-bottom:2px solid #e2e8f0;margin-bottom:24px;flex-wrap:wrap}
.bm-tab{padding:10px 20px;border:none;background:none;font-weight:600;font-size:13px;color:#64748b;cursor:pointer;border-bottom:3px solid transparent;transition:.2s}
.bm-tab:hover{color:#1e293b}.bm-tab.active{color:#1a3c34;border-bottom-color:#1a3c34}
.bm-panel{display:none}.bm-panel.active{display:block}

/* ─── Cards ─── */
.bm-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:16px}
.bm-card h3{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:14px;display:flex;align-items:center;gap:10px}

/* ─── Dashboard Stats ─── */
.bm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px}
.bm-stat{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center}
.bm-stat-value{font-size:24px;font-weight:800;color:#1a3c34}
.bm-stat-label{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}

/* ─── Forms ─── */
.bm-form-row{display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-bottom:10px;align-items:start}
.bm-form-row label{font-weight:600;font-size:13px;color:#475569;padding-top:8px}
.bm-form-row input,.bm-form-row textarea,.bm-form-row select{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px}
.bm-form-row textarea{resize:vertical;min-height:50px}

/* ─── Buttons ─── */
.bm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:.2s}
.bm-btn-primary{background:#1a3c34;color:#fff}.bm-btn-primary:hover{background:#16a34a}
.bm-btn-danger{background:#ef4444;color:#fff}.bm-btn-danger:hover{background:#dc2626}
.bm-btn-warning{background:#f59e0b;color:#fff}.bm-btn-warning:hover{background:#d97706}
.bm-btn-blue{background:#3b82f6;color:#fff}.bm-btn-blue:hover{background:#2563eb}
.bm-btn-sm{padding:5px 10px;font-size:11px}
.bm-btn-outline{background:#fff;color:#475569;border:1px solid #d1d5db}.bm-btn-outline:hover{background:#f1f5f9}

/* ─── Table ─── */
.bm-table{width:100%;border-collapse:collapse;font-size:12px}
.bm-table th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;letter-spacing:.5px;border-bottom:2px solid #e2e8f0}
.bm-table td{padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.bm-table tr:hover td{background:#f8fafc}

/* ─── Badges ─── */
.bm-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700}
.bm-badge-full{background:#eff6ff;color:#1e40af}.bm-badge-incremental{background:#f0fdf4;color:#166534}
.bm-badge-restore{background:#fef2f2;color:#991b1b}.bm-badge-manual{background:#fefce8;color:#854d0e}
.bm-badge-pre_restore{background:#faf5ff;color:#6b21a8}
.bm-badge-active{background:#f0fdf4;color:#166534}.bm-badge-in_progress{background:#fef3c7;color:#92400e}
.bm-badge-failed{background:#fef2f2;color:#991b1b}.bm-badge-archived{background:#f1f5f9;color:#64748b}
.bm-badge-restored{background:#ede9fe;color:#5b21b6}

/* ─── Device Tabs ─── */
.bm-dtabs{display:flex;gap:8px;margin-bottom:16px}
.bm-dtab{flex:1;padding:14px;border:2px solid #e2e8f0;border-radius:10px;text-align:center;cursor:pointer;transition:.2s;background:#fff}
.bm-dtab:hover{border-color:#94a3b8}.bm-dtab.active{border-color:var(--c);background:color-mix(in srgb,var(--c) 8%,#fff)}
.bm-dtab i{font-size:24px;display:block;margin-bottom:4px}.bm-dtab span{font-weight:700;font-size:12px}
.bm-dpanel{display:none}.bm-dpanel.active{display:block}

/* ─── Lock Banner ─── */
.bm-lock{padding:12px 16px;border-radius:10px;display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px}
.bm-lock.locked{background:#fef2f2;border:1px solid #fecaca}
.bm-lock.unlocked{background:#f0fdf4;border:1px solid #bbf7d0}
.bm-lock-info{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600}
.bm-lock.locked .bm-lock-info{color:#991b1b}.bm-lock.unlocked .bm-lock-info{color:#166534}

/* ─── File Grid ─── */
.bm-fgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px}
.bm-fcard{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;display:flex;align-items:center;gap:8px}
.bm-fcard.locked{opacity:.7;background:#fef2f2;border-color:#fecaca}
.bm-ficon{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.bm-fname{font-weight:600;font-size:11px;color:#1e293b;word-break:break-all}
.bm-fmeta{font-size:10px;color:#94a3b8}
.bm-fstatus{margin-left:auto;font-size:13px;flex-shrink:0}

/* ─── Misc ─── */
.bm-mono{font-family:Consolas,monospace;font-size:11px}
.bm-flash{padding:12px 18px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:500}
.bm-flash-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.bm-flash-danger{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.bm-empty{text-align:center;padding:30px;color:#94a3b8;font-size:13px}

/* ─── Confirm overlay ─── */
.bm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.bm-overlay.show{display:flex}
.bm-cbox{background:#fff;border-radius:14px;padding:24px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center}
.bm-cbox h4{font-size:16px;margin-bottom:6px}.bm-cbox p{font-size:12px;color:#64748b;margin-bottom:16px}
.bm-cactions{display:flex;gap:8px;justify-content:center}

.bm-progress{height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;margin-top:6px}
.bm-progress-bar{height:100%;background:#22c55e;border-radius:2px;animation:bm-pulse 1.5s infinite}
@keyframes bm-pulse{0%,100%{opacity:1}50%{opacity:.5}}
</style>

<?php if ($flash): ?>
<div class="bm-flash bm-flash-<?= $flashType; ?>"><?= $flash; ?></div>
<?php endif; ?>

<!-- ═══ DASHBOARD STATS ═══ -->
<div class="bm-stats">
    <div class="bm-stat">
        <div class="bm-stat-value"><?= $diskUsage['active_count'] ?? 0; ?></div>
        <div class="bm-stat-label">Active Backups</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-value"><?= $diskUsage['full_count'] ?? 0; ?></div>
        <div class="bm-stat-label">Full Backups</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-value"><?= $diskUsage['inc_count'] ?? 0; ?></div>
        <div class="bm-stat-label">Incremental</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-value"><?= round(($diskUsage['total_size'] ?? 0)/1048576, 1); ?> MB</div>
        <div class="bm-stat-label">Total Compressed</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-value"><?= round(($dedupStats['saved_bytes'] ?? 0)/1048576, 1); ?> MB</div>
        <div class="bm-stat-label">Saved (Dedup)</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-value" style="font-size:14px;">
            <?php
            $lockedCount = 0;
            foreach ($locks as $l) if ($l['is_locked']) $lockedCount++;
            echo $lockedCount . '/3';
            ?>
        </div>
        <div class="bm-stat-label">Layouts Locked</div>
    </div>
</div>

<!-- ═══ MAIN TABS ═══ -->
<div class="bm-tabs">
    <button class="bm-tab <?= $activeTab==='backup'?'active':''; ?>" onclick="bmTab('backup')"><i class="fas fa-database me-1"></i>Backup & Restore</button>
    <button class="bm-tab <?= $activeTab==='layout'?'active':''; ?>" onclick="bmTab('layout')"><i class="fas fa-layer-group me-1"></i>Layout Management</button>
    <button class="bm-tab <?= $activeTab==='audit'?'active':''; ?>" onclick="bmTab('audit')"><i class="fas fa-history me-1"></i>Audit Log</button>
    <button class="bm-tab <?= $activeTab==='settings'?'active':''; ?>" onclick="bmTab('settings')"><i class="fas fa-cog me-1"></i>Retention & Cleanup</button>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- TAB 1: BACKUP & RESTORE                               -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="bm-panel <?= $activeTab==='backup'?'active':''; ?>" id="panel-backup">

    <!-- Create Backup -->
    <div class="bm-card">
        <h3><i class="fas fa-plus-circle" style="color:#22c55e"></i>Create New Backup</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <!-- Full Backup -->
            <form method="post" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="action" value="create_full">
                <h4 style="font-size:14px;margin-bottom:10px;color:#1e40af;"><i class="fas fa-box me-1"></i>Full Backup</h4>
                <p style="font-size:11px;color:#64748b;margin-bottom:10px;">Complete snapshot: all files + database + layout configs. Compressed with ZIP. Runs weekly or on-demand.</p>
                <div class="bm-form-row" style="grid-template-columns:1fr;">
                    <input type="text" name="backup_name" placeholder="Backup name (optional)">
                </div>
                <div class="bm-form-row" style="grid-template-columns:1fr;">
                    <textarea name="description" placeholder="Description / notes (optional)" rows="2"></textarea>
                </div>
                <button type="submit" class="bm-btn bm-btn-primary" style="width:100%;justify-content:center;"><i class="fas fa-save"></i>Create Full Backup</button>
            </form>

            <!-- Incremental Backup -->
            <form method="post" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="action" value="create_incremental">
                <h4 style="font-size:14px;margin-bottom:10px;color:#166534;"><i class="fas fa-layer-group me-1"></i>Incremental Backup</h4>
                <p style="font-size:11px;color:#64748b;margin-bottom:10px;">Only changed/added/deleted files since last full backup. Uses SHA-256 diff. Runs daily or on-demand.</p>
                <div class="bm-form-row" style="grid-template-columns:1fr;">
                    <input type="text" name="backup_name" placeholder="Backup name (optional)">
                </div>
                <div class="bm-form-row" style="grid-template-columns:1fr;">
                    <textarea name="description" placeholder="Description / notes (optional)" rows="2"></textarea>
                </div>
                <button type="submit" class="bm-btn bm-btn-primary" style="width:100%;justify-content:center;background:#166534;"><i class="fas fa-save"></i>Create Incremental</button>
            </form>
        </div>
    </div>

    <!-- Backup History -->
    <div class="bm-card">
        <h3><i class="fas fa-clock-rotate-left" style="color:#3b82f6"></i>Backup History
            <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400;"><?= count($backups); ?> total</span>
        </h3>
        <?php if (empty($backups)): ?>
            <div class="bm-empty"><i class="fas fa-box-open fa-2x d-block mb-2"></i>No backups yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="bm-table">
            <thead><tr>
                <th>Date</th><th>Backup ID</th><th>Name</th><th>Type</th><th>Status</th>
                <th>Files</th><th>Original</th><th>Compressed</th><th>DB</th><th>Admin</th><th style="text-align:right;">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
            <tr id="row-<?= htmlspecialchars($b['backup_id']); ?>">
                <td style="white-space:nowrap;font-size:11px;"><?= date('M j, Y g:i A', strtotime($b['created_at'])); ?></td>
                <td class="bm-mono"><?= htmlspecialchars(substr($b['backup_id'],0,30)); ?></td>
                <td>
                    <strong style="font-size:12px;"><?= htmlspecialchars($b['backup_name']); ?></strong>
                    <?php if ($b['description']): ?><br><small style="color:#94a3b8;"><?= htmlspecialchars(mb_strimwidth($b['description'],0,60,'...')); ?></small><?php endif; ?>
                    <?php if ($b['is_restore']): ?><br><small style="color:#ef4444;">↩ From: <?= htmlspecialchars($b['restored_from']); ?></small><?php endif; ?>
                    <?php if ($b['parent_backup_id']): ?><br><small style="color:#8b5cf6;">↑ Parent: <?= htmlspecialchars(substr($b['parent_backup_id'],0,25)); ?></small><?php endif; ?>
                </td>
                <td><span class="bm-badge bm-badge-<?= $b['backup_type']; ?>"><?= ucfirst(str_replace('_',' ',$b['backup_type'])); ?></span></td>
                <td><span class="bm-badge bm-badge-<?= $b['status']; ?>"><?= ucfirst(str_replace('_',' ',$b['status'])); ?></span>
                    <?php if ($b['status']==='in_progress'): ?><div class="bm-progress"><div class="bm-progress-bar" style="width:60%"></div></div><?php endif; ?>
                </td>
                <td>
                    <?= number_format($b['file_count']); ?>
                    <?php if ($b['files_added'] || $b['files_changed'] || $b['files_deleted']): ?>
                        <br><small style="color:#64748b;">+<?= $b['files_added']; ?> ~<?= $b['files_changed']; ?> -<?= $b['files_deleted']; ?></small>
                    <?php endif; ?>
                </td>
                <td><?= $b['size_bytes'] ? round($b['size_bytes']/1048576,1).'MB' : '—'; ?></td>
                <td><?= $b['size_compressed'] ? round($b['size_compressed']/1048576,1).'MB' : '—'; ?></td>
                <td><?= $b['includes_database'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>'; ?></td>
                <td style="font-size:11px;"><?= htmlspecialchars($b['admin_user']); ?></td>
                <td style="text-align:right;white-space:nowrap;">
                    <?php if ($b['status']==='active' || $b['status']==='restored'): ?>
                    <button class="bm-btn bm-btn-sm bm-btn-outline" onclick="bmVerify('<?= $b['backup_id']; ?>')" title="Verify checksum"><i class="fas fa-shield-alt"></i></button>
                    <button class="bm-btn bm-btn-sm bm-btn-warning" onclick="bmConfirm('restore','<?= $b['backup_id']; ?>','<?= htmlspecialchars(addslashes($b['backup_name'])); ?>')"><i class="fas fa-undo"></i></button>
                    <?php endif; ?>
                    <?php if ($b['backup_type'] !== 'manual'): ?>
                    <button class="bm-btn bm-btn-sm bm-btn-danger" onclick="bmConfirm('delete','<?= $b['backup_id']; ?>','<?= htmlspecialchars(addslashes($b['backup_name'])); ?>')"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- TAB 2: LAYOUT MANAGEMENT                              -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="bm-panel <?= $activeTab==='layout'?'active':''; ?>" id="panel-layout">
    <div class="bm-card" style="margin-bottom:14px;">
        <h3><i class="fas fa-shield-alt" style="color:#ef4444"></i>Layout Lock System</h3>
        <p style="font-size:12px;color:#64748b;margin:0;">Lock layout files per device to prevent any modifications. Locked files become read-only at filesystem level. Only admin can unlock. Lock state is saved with every backup and restored during restore operations.</p>
    </div>

    <!-- Device Tabs -->
    <div class="bm-dtabs">
    <?php
    $catMeta = [
        'desktop' => ['label'=>'Laptop & Desktop','icon'=>'fa-desktop','color'=>'#3b82f6'],
        'tablet'  => ['label'=>'Tablet & iPad','icon'=>'fa-tablet-alt','color'=>'#8b5cf6'],
        'mobile'  => ['label'=>'Mobile Phone','icon'=>'fa-mobile-alt','color'=>'#22c55e']
    ];
    foreach ($catMeta as $key => $meta): $lk = $locks[$key] ?? ['is_locked'=>false]; ?>
        <div class="bm-dtab <?= $key==='desktop'?'active':''; ?>" style="--c:<?= $meta['color']; ?>" onclick="bmDTab('<?= $key; ?>')">
            <i class="fas <?= $meta['icon']; ?>" style="color:<?= $meta['color']; ?>"></i>
            <span><?= $meta['label']; ?></span>
            <div style="margin-top:4px;">
                <?php if ($lk['is_locked']): ?>
                    <span class="bm-badge" style="background:#fef2f2;color:#991b1b;"><i class="fas fa-lock"></i> Locked</span>
                <?php else: ?>
                    <span class="bm-badge" style="background:#f0fdf4;color:#166534;"><i class="fas fa-lock-open"></i> Open</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- Device Panels -->
    <?php foreach ($catMeta as $key => $meta):
        $lk = $locks[$key] ?? ['is_locked'=>false,'locked_by'=>'','locked_at'=>null];
        $isLocked = (bool)$lk['is_locked'];
        $layoutFiles = array_unique(array_merge(
            BackupEngine::LAYOUT_MAP[$key]['css'] ?? [],
            BackupEngine::LAYOUT_MAP[$key]['php'] ?? []
        ));
    ?>
    <div class="bm-dpanel <?= $key==='desktop'?'active':''; ?>" id="dev-<?= $key; ?>">
        <!-- Lock Banner -->
        <div class="bm-lock <?= $isLocked ? 'locked' : 'unlocked'; ?>">
            <div class="bm-lock-info">
                <i class="fas <?= $isLocked ? 'fa-lock' : 'fa-lock-open'; ?>" style="font-size:16px;"></i>
                <?php if ($isLocked): ?>
                    LOCKED by <strong><?= htmlspecialchars($lk['locked_by'] ?? 'Admin'); ?></strong>
                    <?php if ($lk['locked_at']): ?> on <?= date('M j g:i A', strtotime($lk['locked_at'])); ?><?php endif; ?>
                    — <em>No modifications allowed</em>
                <?php else: ?>
                    UNLOCKED — Files are writable
                <?php endif; ?>
            </div>
            <form method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="category" value="<?= $key; ?>">
                <?php if ($isLocked): ?>
                    <input type="hidden" name="action" value="unlock_layout">
                    <button type="submit" class="bm-btn bm-btn-primary bm-btn-sm" onclick="return confirm('Unlock <?= $meta['label']; ?>?')"><i class="fas fa-unlock"></i> Unlock</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="lock_layout">
                    <button type="submit" class="bm-btn bm-btn-danger bm-btn-sm" onclick="return confirm('Lock <?= $meta['label']; ?>? All files become read-only.')"><i class="fas fa-lock"></i> Lock Layout</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- File Grid -->
        <div class="bm-card">
            <h3 style="font-size:13px;"><i class="fas fa-file-code" style="color:<?= $meta['color']; ?>"></i><?= $meta['label']; ?> Files (<?= count($layoutFiles); ?>)</h3>
            <div class="bm-fgrid">
            <?php foreach ($layoutFiles as $file):
                $info = bm_file_info($engine->getCssDir(), $engine->getProjectRoot(), $file);
                $isShared = in_array($file, ['layout-fixes.css','adaptive-structure.css','includes/new-header.php','includes/new-footer.php']);
            ?>
                <div class="bm-fcard <?= ($isLocked && $info['exists']) ? 'locked' : ''; ?>">
                    <div class="bm-ficon" style="background:<?= $isShared ? '#fef3c7' : 'color-mix(in srgb,'.$meta['color'].' 12%,#fff)'; ?>;color:<?= $isShared ? '#92400e' : $meta['color']; ?>">
                        <i class="fas <?= str_ends_with($file,'.php') ? 'fa-file-code' : 'fa-palette'; ?>"></i>
                    </div>
                    <div>
                        <div class="bm-fname"><?= htmlspecialchars($file); ?><?= $isShared ? ' <span style="font-size:9px;color:#92400e;">(shared)</span>' : ''; ?></div>
                        <?php if ($info['exists']): ?>
                            <div class="bm-fmeta"><?= round($info['size']/1024,1); ?>KB · <?= date('M j g:i A', $info['modified']); ?></div>
                        <?php else: ?>
                            <div class="bm-fmeta" style="color:#ef4444;">Missing</div>
                        <?php endif; ?>
                    </div>
                    <div class="bm-fstatus">
                        <?php if (!$info['exists']): ?><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
                        <?php elseif (!$info['writable']): ?><i class="fas fa-lock" style="color:#ef4444;"></i>
                        <?php else: ?><i class="fas fa-check-circle" style="color:#22c55e;"></i><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- TAB 3: AUDIT LOG                                      -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="bm-panel <?= $activeTab==='audit'?'active':''; ?>" id="panel-audit">
    <div class="bm-card">
        <h3><i class="fas fa-scroll" style="color:#8b5cf6"></i>Security Audit Log <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400;">Last 50</span></h3>
        <?php if (empty($auditLog)): ?>
            <div class="bm-empty">No entries yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="bm-table">
            <thead><tr><th>Date</th><th>Action</th><th>Category</th><th>Backup ID</th><th>Admin</th><th>Details</th></tr></thead>
            <tbody>
            <?php
            $actionIcons = [
                'create_backup'=>'fa-plus text-success','backup_failed'=>'fa-times text-danger',
                'restore_start'=>'fa-play text-warning','restore_complete'=>'fa-check text-success',
                'restore_failed'=>'fa-times text-danger','delete_backup'=>'fa-trash text-danger',
                'lock_layout'=>'fa-lock text-danger','unlock_layout'=>'fa-unlock text-success',
                'auto_cleanup'=>'fa-broom text-info'
            ];
            foreach ($auditLog as $a): $ic = $actionIcons[$a['action']] ?? 'fa-circle text-muted'; ?>
            <tr>
                <td style="white-space:nowrap;font-size:11px;"><?= date('M j g:i A', strtotime($a['created_at'])); ?></td>
                <td><i class="fas <?= $ic; ?> me-1"></i><?= ucwords(str_replace('_',' ',$a['action'])); ?></td>
                <td><?= $a['device_category'] ? ucfirst($a['device_category']) : '—'; ?></td>
                <td class="bm-mono"><?= $a['backup_id'] ? htmlspecialchars(substr($a['backup_id'],0,25)) : '—'; ?></td>
                <td><?= htmlspecialchars($a['admin_user']); ?></td>
                <td style="max-width:280px;"><small><?= htmlspecialchars($a['details'] ?? ''); ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ -->
<!-- TAB 4: RETENTION & CLEANUP                            -->
<!-- ═══════════════════════════════════════════════════════ -->
<div class="bm-panel <?= $activeTab==='settings'?'active':''; ?>" id="panel-settings">
    <div class="bm-card">
        <h3><i class="fas fa-calendar-check" style="color:#f59e0b"></i>Auto-Cleanup Retention Policy</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
            <div style="background:#eff6ff;padding:14px;border-radius:10px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#1e40af;">7</div>
                <div style="font-size:11px;color:#1e40af;">Daily Incrementals Kept</div>
            </div>
            <div style="background:#f0fdf4;padding:14px;border-radius:10px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#166534;">4</div>
                <div style="font-size:11px;color:#166534;">Weekly Full Backups Kept</div>
            </div>
            <div style="background:#faf5ff;padding:14px;border-radius:10px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#6b21a8;">6</div>
                <div style="font-size:11px;color:#6b21a8;">Monthly Archives Kept</div>
            </div>
        </div>
        <p style="font-size:12px;color:#64748b;margin-bottom:14px;">Older backups are automatically archived (files deleted, record kept). Full backups with active incremental children are protected from cleanup.</p>
        <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="run_cleanup">
            <button type="submit" class="bm-btn bm-btn-warning" onclick="return confirm('Run auto-cleanup now? Excess backups will be archived.')"><i class="fas fa-broom"></i> Run Cleanup Now</button>
        </form>
    </div>

    <div class="bm-card">
        <h3><i class="fas fa-clock" style="color:#3b82f6"></i>Scheduled Backups</h3>
        <?php if (PHP_OS_FAMILY === 'Windows'): ?>
        <p style="font-size:12px;color:#64748b;margin-bottom:12px;"><strong>Windows Task Scheduler</strong> — add these commands:</p>
        <div style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:8px;font-family:Consolas;font-size:11px;margin-bottom:10px;word-break:break-all;">
            <div style="color:#94a3b8;margin-bottom:4px;"># Daily incremental (2:00 AM):</div>
            <div>"<?= PHP_BINARY; ?>" "<?= str_replace('/','\\', __DIR__); ?>\backup_cron.php" --type=incremental --name="Daily Auto" --admin="System"</div>
            <br>
            <div style="color:#94a3b8;margin-bottom:4px;"># Weekly full (Sunday 3:00 AM):</div>
            <div>"<?= PHP_BINARY; ?>" "<?= str_replace('/','\\', __DIR__); ?>\backup_cron.php" --type=full --name="Weekly Auto" --admin="System"</div>
            <br>
            <div style="color:#94a3b8;margin-bottom:4px;"># Auto cleanup (daily 4:00 AM):</div>
            <div>"<?= PHP_BINARY; ?>" "<?= str_replace('/','\\', __DIR__); ?>\backup_cron.php" --type=cleanup --admin="System"</div>
        </div>
        <?php else: ?>
        <p style="font-size:12px;color:#64748b;margin-bottom:12px;"><strong>Linux Cron Jobs</strong> — add via <code>crontab -e</code>:</p>
        <div style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:8px;font-family:Consolas;font-size:11px;margin-bottom:10px;word-break:break-all;">
            <div style="color:#94a3b8;margin-bottom:4px;"># Daily incremental (2:00 AM):</div>
            <div>0 2 * * * <?= PHP_BINARY; ?> <?= __DIR__; ?>/backup_cron.php --type=incremental --name="Daily Auto" --admin="System"</div>
            <br>
            <div style="color:#94a3b8;margin-bottom:4px;"># Weekly full (Sunday 3:00 AM):</div>
            <div>0 3 * * 0 <?= PHP_BINARY; ?> <?= __DIR__; ?>/backup_cron.php --type=full --name="Weekly Auto" --admin="System"</div>
            <br>
            <div style="color:#94a3b8;margin-bottom:4px;"># Auto cleanup (daily 4:00 AM):</div>
            <div>0 4 * * * <?= PHP_BINARY; ?> <?= __DIR__; ?>/backup_cron.php --type=cleanup --admin="System"</div>
        </div>
        <?php endif; ?>
    </div>

    <div class="bm-card">
        <h3><i class="fas fa-chart-pie" style="color:#22c55e"></i>Deduplication Stats</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            <div style="text-align:center;padding:10px;">
                <div style="font-size:22px;font-weight:800;color:#1a3c34;"><?= number_format($dedupStats['total_entries'] ?? 0); ?></div>
                <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;">Total File Records</div>
            </div>
            <div style="text-align:center;padding:10px;">
                <div style="font-size:22px;font-weight:800;color:#22c55e;"><?= number_format($dedupStats['deduplicated'] ?? 0); ?></div>
                <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;">Deduplicated (References)</div>
            </div>
            <div style="text-align:center;padding:10px;">
                <div style="font-size:22px;font-weight:800;color:#3b82f6;"><?= round(($dedupStats['saved_bytes'] ?? 0)/1048576, 1); ?> MB</div>
                <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;">Storage Saved</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Confirm Dialog ═══ -->
<div class="bm-overlay" id="bmOverlay">
    <div class="bm-cbox">
        <h4 id="bmCTitle">Confirm</h4>
        <p id="bmCMsg"></p>
        <form method="post" id="bmCForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" id="bmCAction">
            <input type="hidden" name="backup_id" id="bmCBid">
            <div class="bm-cactions">
                <button type="button" class="bm-btn bm-btn-outline" onclick="bmClose()">Cancel</button>
                <button type="submit" class="bm-btn" id="bmCBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Verify Result Toast ═══ -->
<div id="bmToast" style="display:none;position:fixed;bottom:30px;right:30px;padding:14px 22px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,.15);"></div>

<script>
// Tab navigation
function bmTab(n){
    document.querySelectorAll('.bm-tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.bm-panel').forEach(p=>p.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('panel-'+n).classList.add('active');
}
function bmDTab(k){
    document.querySelectorAll('.bm-dtab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.bm-dpanel').forEach(p=>p.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('dev-'+k).classList.add('active');
}

// Confirm dialogs
function bmConfirm(type, bid, name){
    var t = document.getElementById('bmCTitle');
    var m = document.getElementById('bmCMsg');
    var b = document.getElementById('bmCBtn');
    document.getElementById('bmCBid').value = bid;

    if(type==='restore'){
        t.textContent='Restore Backup?';
        m.innerHTML='Revert the entire website to <strong>'+name+'</strong>?<br>A pre-restore snapshot is saved automatically.';
        document.getElementById('bmCAction').value='restore_backup';
        b.className='bm-btn bm-btn-warning';
        b.innerHTML='<i class="fas fa-undo"></i> Restore Now';
    } else {
        t.textContent='Delete Backup?';
        m.innerHTML='Permanently delete <strong>'+name+'</strong>?';
        document.getElementById('bmCAction').value='delete_backup';
        b.className='bm-btn bm-btn-danger';
        b.innerHTML='<i class="fas fa-trash"></i> Delete';
    }
    document.getElementById('bmOverlay').classList.add('show');
}
function bmClose(){document.getElementById('bmOverlay').classList.remove('show');}
document.getElementById('bmOverlay').addEventListener('click',function(e){if(e.target===this)bmClose();});

// Verify checksum
function bmVerify(bid){
    var toast = document.getElementById('bmToast');
    toast.style.display='block';
    toast.style.background='#fef3c7';
    toast.style.color='#92400e';
    toast.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i> Verifying checksum...';

    fetch('?ajax=verify&id='+encodeURIComponent(bid))
        .then(r=>r.json())
        .then(d=>{
            if(d.valid){
                toast.style.background='#f0fdf4';toast.style.color='#166534';
                toast.innerHTML='<i class="fas fa-shield-alt me-1"></i> Checksum VALID — backup integrity confirmed';
            } else {
                toast.style.background='#fef2f2';toast.style.color='#991b1b';
                toast.innerHTML='<i class="fas fa-exclamation-triangle me-1"></i> Checksum FAILED: '+(d.error||'Mismatch');
            }
            setTimeout(function(){toast.style.display='none';},5000);
        })
        .catch(function(){
            toast.style.background='#fef2f2';toast.style.color='#991b1b';
            toast.innerHTML='Verification request failed';
            setTimeout(function(){toast.style.display='none';},3000);
        });
}

// Poll in-progress backups
(function(){
    var rows = document.querySelectorAll('.bm-badge-in_progress');
    if(!rows.length) return;
    var interval = setInterval(function(){
        rows.forEach(function(badge){
            var tr = badge.closest('tr');
            if(!tr) return;
            var bid = tr.id.replace('row-','');
            fetch('?ajax=status&id='+encodeURIComponent(bid))
                .then(function(r){return r.json();})
                .then(function(d){
                    if(d.status && d.status!=='in_progress'){
                        clearInterval(interval);
                        location.reload();
                    }
                });
        });
    }, 5000);
})();
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
