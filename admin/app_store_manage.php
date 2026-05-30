<?php
/**
 * Gilaf App Store — Admin Management
 * CRUD for apps, role groups, email-based access control
 * Access: Admin only (inside website security)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pageTitle = 'Gilaf App Store — Admin';
$adminPage = 'app_store';

$db = get_db_connection();

// ─── Auto-migrate: create tables if missing ───
try {
    $db->exec("CREATE TABLE IF NOT EXISTS app_store_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS app_store_group_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        email VARCHAR(200) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_group_email (group_id, email),
        FOREIGN KEY (group_id) REFERENCES app_store_groups(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS app_store_apps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_name VARCHAR(200) NOT NULL,
        package_name VARCHAR(200),
        version VARCHAR(50),
        description TEXT,
        icon_path VARCHAR(500),
        apk_path VARCHAR(500) NOT NULL,
        apk_size BIGINT DEFAULT 0,
        visibility ENUM('public','restricted') DEFAULT 'restricted',
        is_active TINYINT(1) DEFAULT 1,
        download_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS app_store_app_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_id INT NOT NULL,
        group_id INT NOT NULL,
        UNIQUE KEY unique_app_group (app_id, group_id),
        FOREIGN KEY (app_id) REFERENCES app_store_apps(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES app_store_groups(id) ON DELETE CASCADE
    )");

    // Seed default groups if empty
    $groupCount = (int)$db->query("SELECT COUNT(*) FROM app_store_groups")->fetchColumn();
    if ($groupCount === 0) {
        $db->exec("INSERT INTO app_store_groups (group_name, description) VALUES 
            ('General Users', 'All registered users — can see general consumer apps'),
            ('Management', 'Management team — access to management-only apps')
        ");
    }

    // ─── Auto-seed Gilaf Sales App if not yet registered ───
    $salesAppExists = (int)$db->query("SELECT COUNT(*) FROM app_store_apps WHERE app_name = 'Gilaf Sales App'")->fetchColumn();
    if ($salesAppExists === 0) {
        $baseDir = dirname(__DIR__);
        $ds = DIRECTORY_SEPARATOR;
        $apkRelPath = 'uploads/app_store/apks/gilaf-sales.apk';
        $apkTarget = $baseDir . $ds . str_replace('/', $ds, $apkRelPath);
        $apkSource = $baseDir . $ds . 'sales-portal' . $ds . 'downloads' . $ds . 'gilaf-sales.apk';

        // Auto-copy APK from sales-portal if target doesn't exist but source does
        if (!file_exists($apkTarget) && file_exists($apkSource)) {
            $apkDir = dirname($apkTarget);
            if (!is_dir($apkDir)) mkdir($apkDir, 0755, true);
            copy($apkSource, $apkTarget);
        }

        // Seed if APK file exists, or seed without file check so admin can upload later
        $apkSize = file_exists($apkTarget) ? filesize($apkTarget) : 0;
        $stmt = $db->prepare("INSERT INTO app_store_apps (app_name, package_name, version, description, icon_path, apk_path, apk_size, visibility, is_active, download_count) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            'Gilaf Sales App',
            'com.gilaf.sales',
            '1.0.0',
            'Official Gilaf Sales Portal app for the sales team. Manage orders, scan barcodes, track collections, and more.',
            '',
            $apkRelPath,
            $apkSize,
            'restricted',
            1,
            0
        ]);
        $seedAppId = (int)$db->lastInsertId();

        // Assign to Management group
        $mgmtGroup = $db->query("SELECT id FROM app_store_groups WHERE group_name = 'Management' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($mgmtGroup) {
            $db->prepare("INSERT IGNORE INTO app_store_app_groups (app_id, group_id) VALUES (?, ?)")->execute([$seedAppId, $mgmtGroup['id']]);
        }
    }

    // ─── Auto-seed Gilaf Store PWA if not yet registered ───
    $pwaExists = (int)$db->query("SELECT COUNT(*) FROM app_store_apps WHERE package_name = 'pwa.gilafstore.com'")->fetchColumn();
    if ($pwaExists === 0) {
        $stmt = $db->prepare("INSERT INTO app_store_apps (app_name, package_name, version, description, icon_path, apk_path, apk_size, visibility, is_active, download_count) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            'Gilaf Store',
            'pwa.gilafstore.com',
            '1.0.0',
            'Official Gilaf Store app — Shop premium organic products, track orders, explore curated collections, and get exclusive offers. Taste . Culture . Craft.',
            'assets/icons/icon-512x512.png',
            'pwa',
            0,
            'public',
            1,
            0
        ]);
    }
} catch (PDOException $e) {
    // Tables may already exist
}

// ─── Handle POST Actions ───
$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();
    $action = $_POST['action'];

    try {
        switch ($action) {

            // ── Create/Update App ──
            case 'save_app':
                $appId       = (int)($_POST['app_id'] ?? 0);
                $appName     = trim($_POST['app_name'] ?? '');
                $packageName = trim($_POST['package_name'] ?? '');
                $version     = trim($_POST['version'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $visibility  = in_array($_POST['visibility'] ?? '', ['public','restricted']) ? $_POST['visibility'] : 'restricted';
                $isActive    = isset($_POST['is_active']) ? 1 : 0;
                $groupIds    = array_map('intval', $_POST['group_ids'] ?? []);

                if (!$appName) throw new Exception('App name is required.');

                // Handle icon upload
                $iconPath = $_POST['existing_icon'] ?? '';
                if (!empty($_FILES['icon_file']['name'])) {
                    $iconDir = __DIR__ . '/../uploads/app_store/icons/';
                    if (!is_dir($iconDir)) mkdir($iconDir, 0755, true);
                    $iconExt = strtolower(pathinfo($_FILES['icon_file']['name'], PATHINFO_EXTENSION));
                    if (!in_array($iconExt, ['png','jpg','jpeg','svg','webp'])) throw new Exception('Invalid icon format.');
                    $iconFilename = 'icon_' . time() . '_' . mt_rand(1000,9999) . '.' . $iconExt;
                    move_uploaded_file($_FILES['icon_file']['tmp_name'], $iconDir . $iconFilename);
                    $iconPath = 'uploads/app_store/icons/' . $iconFilename;
                }

                // Handle APK upload
                $apkPath = $_POST['existing_apk'] ?? '';
                $apkSize = (int)($_POST['existing_apk_size'] ?? 0);
                $newApkUploaded = false;
                if (!empty($_FILES['apk_file']['name'])) {
                    $apkDir = __DIR__ . '/../uploads/app_store/apks/';
                    if (!is_dir($apkDir)) mkdir($apkDir, 0755, true);
                    $apkFilename = 'app_' . time() . '_' . mt_rand(1000,9999) . '.apk';
                    move_uploaded_file($_FILES['apk_file']['tmp_name'], $apkDir . $apkFilename);
                    $apkPath = 'uploads/app_store/apks/' . $apkFilename;
                    $apkSize = $_FILES['apk_file']['size'];
                    $newApkUploaded = true;
                }
                if (!$apkPath && !$appId) throw new Exception('APK file is required for new apps.');

                // Auto-increment version when new APK is uploaded
                if ($newApkUploaded && $appId > 0) {
                    $oldApp = db_fetch('SELECT version FROM app_store_apps WHERE id = ?', [$appId]);
                    $oldVer = $oldApp['version'] ?? '1.0.0';
                    if (!$version || $version === $oldVer) {
                        $parts = explode('.', $oldVer);
                        $major = (int)($parts[0] ?? 1);
                        $minor = (int)($parts[1] ?? 0);
                        $patch = (int)($parts[2] ?? 0);
                        $version = $major . '.' . $minor . '.' . ($patch + 1);
                    }
                }
                if (!$version) $version = '1.0.0';

                if ($appId > 0) {
                    // Update
                    $stmt = $db->prepare("UPDATE app_store_apps SET app_name=?, package_name=?, version=?, description=?, icon_path=?, visibility=?, is_active=?, updated_at=NOW()" . ($apkPath !== ($_POST['existing_apk'] ?? '') ? ", apk_path=?, apk_size=?" : "") . " WHERE id=?");
                    $params = [$appName, $packageName, $version, $description, $iconPath, $visibility, $isActive];
                    if ($apkPath !== ($_POST['existing_apk'] ?? '')) {
                        $params[] = $apkPath;
                        $params[] = $apkSize;
                    }
                    $params[] = $appId;
                    $stmt->execute($params);
                } else {
                    // Insert
                    $stmt = $db->prepare("INSERT INTO app_store_apps (app_name, package_name, version, description, icon_path, apk_path, apk_size, visibility, is_active) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$appName, $packageName, $version, $description, $iconPath, $apkPath, $apkSize, $visibility, $isActive]);
                    $appId = (int)$db->lastInsertId();
                }

                // Update group assignments
                $db->prepare("DELETE FROM app_store_app_groups WHERE app_id = ?")->execute([$appId]);
                if ($visibility === 'restricted' && !empty($groupIds)) {
                    $igStmt = $db->prepare("INSERT INTO app_store_app_groups (app_id, group_id) VALUES (?, ?)");
                    foreach ($groupIds as $gid) {
                        $igStmt->execute([$appId, $gid]);
                    }
                }
                $flash = $appId ? 'App saved successfully.' : 'App created successfully.';
                break;

            // ── Delete App ──
            case 'delete_app':
                $appId = (int)($_POST['app_id'] ?? 0);
                if ($appId) {
                    // Delete files
                    $app = db_fetch('SELECT icon_path, apk_path FROM app_store_apps WHERE id = ?', [$appId]);
                    if ($app) {
                        $base = __DIR__ . '/../';
                        if ($app['icon_path'] && file_exists($base . $app['icon_path'])) @unlink($base . $app['icon_path']);
                        if ($app['apk_path'] && file_exists($base . $app['apk_path'])) @unlink($base . $app['apk_path']);
                    }
                    $db->prepare("DELETE FROM app_store_apps WHERE id = ?")->execute([$appId]);
                    $flash = 'App deleted.';
                }
                break;

            // ── Save Group ──
            case 'save_group':
                $groupId   = (int)($_POST['group_id'] ?? 0);
                $groupName = trim($_POST['group_name'] ?? '');
                $groupDesc = trim($_POST['group_description'] ?? '');
                $emails    = array_filter(array_map('trim', explode("\n", $_POST['emails'] ?? '')));

                if (!$groupName) throw new Exception('Group name is required.');

                if ($groupId > 0) {
                    $db->prepare("UPDATE app_store_groups SET group_name=?, description=? WHERE id=?")->execute([$groupName, $groupDesc, $groupId]);
                } else {
                    $db->prepare("INSERT INTO app_store_groups (group_name, description) VALUES (?,?)")->execute([$groupName, $groupDesc]);
                    $groupId = (int)$db->lastInsertId();
                }

                // Replace emails
                $db->prepare("DELETE FROM app_store_group_emails WHERE group_id = ?")->execute([$groupId]);
                if (!empty($emails)) {
                    $eStmt = $db->prepare("INSERT IGNORE INTO app_store_group_emails (group_id, email) VALUES (?, ?)");
                    foreach ($emails as $email) {
                        $email = strtolower(trim($email));
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $eStmt->execute([$groupId, $email]);
                        }
                    }
                }
                $flash = 'Group saved successfully.';
                break;

            // ── Delete Group ──
            case 'delete_group':
                $groupId = (int)($_POST['group_id'] ?? 0);
                if ($groupId) {
                    $db->prepare("DELETE FROM app_store_groups WHERE id = ?")->execute([$groupId]);
                    $flash = 'Group deleted.';
                }
                break;
        }
    } catch (Exception $e) {
        $flash = $e->getMessage();
        $flashType = 'danger';
    }
}

// ─── Fetch Data ───
$apps = $db->query("
    SELECT a.*, GROUP_CONCAT(ag.group_id) AS group_ids
    FROM app_store_apps a
    LEFT JOIN app_store_app_groups ag ON ag.app_id = a.id
    GROUP BY a.id
    ORDER BY a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$groups = $db->query("SELECT g.*, (SELECT COUNT(*) FROM app_store_group_emails ge WHERE ge.group_id = g.id) AS email_count FROM app_store_groups g ORDER BY g.group_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch emails per group for editing
$groupEmails = [];
foreach ($groups as $g) {
    $eStmt = $db->prepare("SELECT email FROM app_store_group_emails WHERE group_id = ? ORDER BY email ASC");
    $eStmt->execute([$g['id']]);
    $groupEmails[$g['id']] = $eStmt->fetchAll(PDO::FETCH_COLUMN);
}

$activeTab = $_GET['tab'] ?? 'apps';

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.as-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
.as-tab { padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; border: 2px solid #e2e8f0; background: #fff; color: #64748b; text-decoration: none; transition: all .2s; }
.as-tab:hover { border-color: #1a3c34; color: #1a3c34; }
.as-tab.active { background: #1a3c34; color: #fff; border-color: #1a3c34; }

.as-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 16px; }
.as-app-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.as-app-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; gap: 14px; align-items: flex-start; transition: box-shadow .2s; }
.as-app-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.as-app-icon { width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #1a3c34, #22c55e); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; flex-shrink: 0; overflow: hidden; }
.as-app-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.as-app-name { font-weight: 700; font-size: 15px; color: #1a1a1a; }
.as-app-ver { font-size: 11px; color: #64748b; }
.as-app-desc { font-size: 12px; color: #475569; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.as-app-meta { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.as-app-badge { padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
.as-app-badge.public { background: #dcfce7; color: #16a34a; }
.as-app-badge.restricted { background: #fef3c7; color: #d97706; }
.as-app-badge.active { background: #dbeafe; color: #2563eb; }
.as-app-badge.inactive { background: #fee2e2; color: #dc2626; }
.as-app-badge.group { background: #f1f5f9; color: #475569; }
.as-app-actions { margin-top: 10px; display: flex; gap: 6px; }

.as-group-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
.as-group-name { font-weight: 700; font-size: 15px; }
.as-group-emails { font-size: 12px; color: #64748b; margin-top: 4px; }

.as-empty { text-align: center; padding: 40px; color: #94a3b8; }
.as-empty i { font-size: 48px; display: block; margin-bottom: 12px; }
</style>

<section class="py-4">
<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-semibold mb-0"><i class="fas fa-store text-primary me-2"></i>Gilaf App Store</h4>
            <p class="text-muted mb-0">Manage apps, role groups, and email-based access control.</p>
        </div>
        <a href="<?= base_url('app_store.php'); ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt"></i> View Public Store</a>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flashType; ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="as-tabs">
        <a href="?tab=apps" class="as-tab <?= $activeTab === 'apps' ? 'active' : ''; ?>"><i class="fas fa-mobile-alt me-1"></i> Apps (<?= count($apps); ?>)</a>
        <a href="?tab=groups" class="as-tab <?= $activeTab === 'groups' ? 'active' : ''; ?>"><i class="fas fa-users me-1"></i> Role Groups (<?= count($groups); ?>)</a>
    </div>

    <?php if ($activeTab === 'apps'): ?>
    <!-- ═══════════ APPS TAB ═══════════ -->

    <!-- Add App Button -->
    <button class="btn btn-primary mb-3" onclick="openAppModal()"><i class="fas fa-plus"></i> Add New App</button>

    <?php if (empty($apps)): ?>
    <div class="as-empty"><i class="fas fa-mobile-alt"></i>No apps added yet. Click "Add New App" to get started.</div>
    <?php else: ?>
    <div class="as-app-grid">
        <?php foreach ($apps as $app):
            $appGroups = array_filter(explode(',', $app['group_ids'] ?? ''));
            $appGroupNames = [];
            foreach ($appGroups as $gid) {
                foreach ($groups as $g) {
                    if ((int)$g['id'] === (int)$gid) $appGroupNames[] = $g['group_name'];
                }
            }
        ?>
        <div class="as-app-item">
            <div class="as-app-icon">
                <?php if ($app['icon_path'] && file_exists(__DIR__ . '/../' . $app['icon_path'])): ?>
                    <img src="<?= base_url($app['icon_path']); ?>" alt="icon">
                <?php else: ?>
                    <?= strtoupper(substr($app['app_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:0;">
                <div class="as-app-name"><?= htmlspecialchars($app['app_name']); ?></div>
                <div class="as-app-ver">
                    v<?= htmlspecialchars($app['version'] ?: '1.0'); ?>
                    <?php if ($app['package_name']): ?> &middot; <?= htmlspecialchars($app['package_name']); ?><?php endif; ?>
                    &middot; <?= $app['apk_size'] > 0 ? round($app['apk_size'] / 1048576, 1) . ' MB' : '—'; ?>
                    &middot; <i class="fas fa-download"></i> <?= (int)$app['download_count']; ?>
                </div>
                <?php if ($app['description']): ?>
                <div class="as-app-desc"><?= htmlspecialchars($app['description']); ?></div>
                <?php endif; ?>
                <div class="as-app-meta">
                    <span class="as-app-badge <?= $app['visibility']; ?>"><?= $app['visibility']; ?></span>
                    <span class="as-app-badge <?= $app['is_active'] ? 'active' : 'inactive'; ?>"><?= $app['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    <?php foreach ($appGroupNames as $gn): ?>
                    <span class="as-app-badge group"><i class="fas fa-users"></i> <?= htmlspecialchars($gn); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="as-app-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick='openAppModal(<?= json_encode($app); ?>)'><i class="fas fa-edit"></i> Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this app and its APK file?');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_app">
                        <input type="hidden" name="app_id" value="<?= (int)$app['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($activeTab === 'groups'): ?>
    <!-- ═══════════ GROUPS TAB ═══════════ -->

    <button class="btn btn-primary mb-3" onclick="openGroupModal()"><i class="fas fa-plus"></i> Add New Group</button>

    <?php if (empty($groups)): ?>
    <div class="as-empty"><i class="fas fa-users"></i>No groups yet.</div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($groups as $g): ?>
        <div class="col-md-6 col-lg-4">
            <div class="as-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="as-group-name"><i class="fas fa-users text-primary me-1"></i> <?= htmlspecialchars($g['group_name']); ?></div>
                        <?php if ($g['description']): ?>
                        <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($g['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-primary"><?= (int)$g['email_count']; ?> emails</span>
                </div>
                <?php if (!empty($groupEmails[$g['id']])): ?>
                <div class="as-group-emails mt-2" style="max-height:80px; overflow-y:auto;">
                    <?php foreach ($groupEmails[$g['id']] as $em): ?>
                    <div><i class="fas fa-envelope text-muted me-1" style="font-size:10px;"></i><?= htmlspecialchars($em); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick='openGroupModal(<?= json_encode(["id"=>$g["id"],"group_name"=>$g["group_name"],"description"=>$g["description"],"emails"=>$groupEmails[$g["id"]]??[]]); ?>)'><i class="fas fa-edit"></i> Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this group? Apps using it will lose this group assignment.');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="group_id" value="<?= (int)$g['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>
</section>

<!-- ═══════════ APP MODAL ═══════════ -->
<div class="modal fade" id="appModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form method="POST" enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="save_app">
    <input type="hidden" name="app_id" id="appId" value="0">
    <input type="hidden" name="existing_icon" id="appExistingIcon">
    <input type="hidden" name="existing_apk" id="appExistingApk">
    <input type="hidden" name="existing_apk_size" id="appExistingApkSize">
    <div class="modal-header">
        <h5 class="modal-title" id="appModalTitle"><i class="fas fa-mobile-alt me-2"></i>Add New App</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">App Name *</label>
                <input type="text" name="app_name" id="appName" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Version</label>
                <input type="text" name="version" id="appVersion" class="form-control" placeholder="1.0.0">
            </div>
            <div class="col-md-6">
                <label class="form-label">Package Name</label>
                <input type="text" name="package_name" id="appPackage" class="form-control" placeholder="com.gilaf.app">
            </div>
            <div class="col-md-6">
                <label class="form-label">Visibility *</label>
                <select name="visibility" id="appVisibility" class="form-select" onchange="toggleGroupSelect()">
                    <option value="restricted">Restricted (Role-based)</option>
                    <option value="public">Public (All Users)</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" id="appDesc" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">App Icon (PNG/JPG/SVG)</label>
                <input type="file" name="icon_file" class="form-control" accept=".png,.jpg,.jpeg,.svg,.webp">
                <div id="appCurrentIcon" class="mt-1" style="font-size:12px; color:#64748b;"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">APK File * <span id="apkRequired" class="text-danger">(required)</span></label>
                <input type="file" name="apk_file" id="apkFile" class="form-control" accept=".apk">
                <div id="appCurrentApk" class="mt-1" style="font-size:12px; color:#64748b;"></div>
            </div>
            <div class="col-md-6" id="groupSelectContainer">
                <label class="form-label">Allowed Groups</label>
                <div class="border rounded p-2" style="max-height:140px; overflow-y:auto;">
                    <?php foreach ($groups as $g): ?>
                    <div class="form-check">
                        <input class="form-check-input app-group-check" type="checkbox" name="group_ids[]" value="<?= (int)$g['id']; ?>" id="appGrp<?= $g['id']; ?>">
                        <label class="form-check-label" for="appGrp<?= $g['id']; ?>"><?= htmlspecialchars($g['group_name']); ?> (<?= (int)$g['email_count']; ?> emails)</label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_active" id="appActive" checked>
                    <label class="form-check-label" for="appActive">App is Active (visible in store)</label>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save App</button>
    </div>
</form>
</div>
</div>
</div>

<!-- ═══════════ GROUP MODAL ═══════════ -->
<div class="modal fade" id="groupModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="POST">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="save_group">
    <input type="hidden" name="group_id" id="groupId" value="0">
    <div class="modal-header">
        <h5 class="modal-title" id="groupModalTitle"><i class="fas fa-users me-2"></i>Add New Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Group Name *</label>
            <input type="text" name="group_name" id="groupName" class="form-control" required placeholder="e.g. Management">
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="group_description" id="groupDescription" class="form-control" placeholder="Brief description">
        </div>
        <div class="mb-3">
            <label class="form-label">Email Addresses (one per line)</label>
            <textarea name="emails" id="groupEmailsInput" class="form-control" rows="6" placeholder="user1@example.com&#10;user2@example.com&#10;user3@example.com"></textarea>
            <small class="text-muted">Only these email addresses will have access to apps assigned to this group.</small>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Group</button>
    </div>
</form>
</div>
</div>
</div>

<script>
function openAppModal(app) {
    document.getElementById('appId').value = app ? app.id : 0;
    document.getElementById('appName').value = app ? app.app_name : '';
    document.getElementById('appVersion').value = app ? (app.version || '') : '';
    document.getElementById('appPackage').value = app ? (app.package_name || '') : '';
    document.getElementById('appDesc').value = app ? (app.description || '') : '';
    document.getElementById('appVisibility').value = app ? app.visibility : 'restricted';
    document.getElementById('appActive').checked = app ? !!app.is_active : true;
    document.getElementById('appExistingIcon').value = app ? (app.icon_path || '') : '';
    document.getElementById('appExistingApk').value = app ? (app.apk_path || '') : '';
    document.getElementById('appExistingApkSize').value = app ? (app.apk_size || 0) : 0;
    document.getElementById('appModalTitle').innerHTML = '<i class="fas fa-mobile-alt me-2"></i>' + (app ? 'Edit App' : 'Add New App');

    // Current file info
    document.getElementById('appCurrentIcon').textContent = app && app.icon_path ? 'Current: ' + app.icon_path.split('/').pop() : '';
    document.getElementById('appCurrentApk').textContent = app && app.apk_path ? 'Current: ' + app.apk_path.split('/').pop() : '';

    // APK required only for new apps
    document.getElementById('apkRequired').style.display = app ? 'none' : 'inline';
    document.getElementById('apkFile').required = !app;

    // Group checkboxes
    const gids = app && app.group_ids ? app.group_ids.split(',') : [];
    document.querySelectorAll('.app-group-check').forEach(cb => {
        cb.checked = gids.includes(cb.value);
    });

    toggleGroupSelect();
    new bootstrap.Modal(document.getElementById('appModal')).show();
}

function toggleGroupSelect() {
    const vis = document.getElementById('appVisibility').value;
    document.getElementById('groupSelectContainer').style.display = vis === 'restricted' ? 'block' : 'none';
}

function openGroupModal(group) {
    document.getElementById('groupId').value = group ? group.id : 0;
    document.getElementById('groupName').value = group ? group.group_name : '';
    document.getElementById('groupDescription').value = group ? (group.description || '') : '';
    document.getElementById('groupEmailsInput').value = group && group.emails ? group.emails.join('\n') : '';
    document.getElementById('groupModalTitle').innerHTML = '<i class="fas fa-users me-2"></i>' + (group ? 'Edit Group' : 'Add New Group');
    new bootstrap.Modal(document.getElementById('groupModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
