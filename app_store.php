<?php
/**
 * Gilaf App Store — User-Facing Page (Play Store Style)
 * Public browsing — no login required to view or download public apps
 * Restricted apps visible but require login + group membership to download
 * Tracks user downloads to show Update vs Download (logged-in users)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in (but don't force login)
$isLoggedIn = !empty($_SESSION['user']);
$userId     = $isLoggedIn ? (int)$_SESSION['user']['id'] : 0;
$userEmail  = $isLoggedIn ? strtolower(trim($_SESSION['user']['email'] ?? '')) : '';
$userName   = $isLoggedIn ? ($_SESSION['user']['name'] ?? 'User') : 'Guest';

$db = get_db_connection();

// ─── Check tables exist ───
try {
    $db->query("SELECT 1 FROM app_store_apps LIMIT 1");
} catch (PDOException $e) {
    $pageTitle = 'Gilaf App Store';
    include __DIR__ . '/includes/new-header.php';
    echo '<div style="max-width:600px;margin:80px auto;text-align:center;font-family:sans-serif;"><h2>App Store Coming Soon</h2><p style="color:#64748b;">The Gilaf App Store is being set up. Please check back later.</p><a href="' . base_url('index.php') . '" style="color:#1a3c34;">← Back to Store</a></div>';
    include __DIR__ . '/includes/new-footer.php';
    exit;
}

// ─── Auto-create user downloads tracking table ───
try {
    $db->exec("CREATE TABLE IF NOT EXISTS app_store_user_downloads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        app_id INT NOT NULL,
        downloaded_version VARCHAR(50),
        downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_app (user_id, app_id)
    )");
} catch (PDOException $e) { /* ignore */ }

// ─── Handle Download ───
if (isset($_GET['download'])) {
    $appId = (int)$_GET['download'];
    $app = db_fetch('SELECT * FROM app_store_apps WHERE id = ? AND is_active = 1', [$appId]);

    if (!$app) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'App not found or inactive.'];
        header('Location: app_store.php');
        exit;
    }

    // PWA apps: redirect to homepage with install trigger (no file download)
    if ($app['apk_path'] === 'pwa') {
        $db->prepare("UPDATE app_store_apps SET download_count = download_count + 1 WHERE id = ?")->execute([$appId]);
        if ($isLoggedIn) {
            $db->prepare("INSERT INTO app_store_user_downloads (user_id, app_id, downloaded_version, downloaded_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE downloaded_version = VALUES(downloaded_version), downloaded_at = NOW()")->execute([$userId, $appId, $app['version']]);
        }
        header('Location: app_store.php?pwa_install=1');
        exit;
    }

    // Check access
    $canDownload = false;
    if ($app['visibility'] === 'public') {
        $canDownload = true;
    } else {
        // Restricted apps require login
        if (!$isLoggedIn) {
            redirect_with_message(base_url('user/login.php?redirect=app_store'), 'Please login to download this app.', 'warning');
        }
        $accessStmt = $db->prepare("
            SELECT COUNT(*) FROM app_store_app_groups aag
            JOIN app_store_group_emails age ON age.group_id = aag.group_id
            WHERE aag.app_id = ? AND LOWER(age.email) = ?
        ");
        $accessStmt->execute([$appId, $userEmail]);
        $canDownload = (int)$accessStmt->fetchColumn() > 0;
    }

    if (!$canDownload) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'You do not have permission to download this app.'];
        header('Location: app_store.php');
        exit;
    }

    // Serve file
    $filePath = __DIR__ . '/' . $app['apk_path'];
    if (!file_exists($filePath)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'APK file not found on server.'];
        header('Location: app_store.php');
        exit;
    }

    // Increment download count
    $db->prepare("UPDATE app_store_apps SET download_count = download_count + 1 WHERE id = ?")->execute([$appId]);

    // Track user download if logged in (upsert)
    if ($isLoggedIn) {
        $db->prepare("INSERT INTO app_store_user_downloads (user_id, app_id, downloaded_version, downloaded_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE downloaded_version = VALUES(downloaded_version), downloaded_at = NOW()")->execute([$userId, $appId, $app['version']]);
    }

    // Send file
    $downloadName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $app['app_name']) . '_v' . ($app['version'] ?: '1.0') . '.apk';
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($filePath);
    exit;
}

// ─── Fetch ALL active apps (visible to everyone for browsing) ───
$apps = $db->query("SELECT * FROM app_store_apps WHERE is_active = 1 ORDER BY app_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ─── Determine download access per app ───
$appAccess = []; // app_id => true/false
foreach ($apps as $app) {
    if ($app['visibility'] === 'public') {
        $appAccess[(int)$app['id']] = true;
    } elseif ($isLoggedIn) {
        $accessStmt = $db->prepare("
            SELECT COUNT(*) FROM app_store_app_groups aag
            JOIN app_store_group_emails age ON age.group_id = aag.group_id
            WHERE aag.app_id = ? AND LOWER(age.email) = ?
        ");
        $accessStmt->execute([(int)$app['id'], $userEmail]);
        $appAccess[(int)$app['id']] = (int)$accessStmt->fetchColumn() > 0;
    } else {
        $appAccess[(int)$app['id']] = false;
    }
}

// ─── Fetch user's download history (logged-in only) ───
$userDownloads = [];
if ($isLoggedIn) {
    try {
        $dlStmt = $db->prepare("SELECT app_id, downloaded_version FROM app_store_user_downloads WHERE user_id = ?");
        $dlStmt->execute([$userId]);
        foreach ($dlStmt->fetchAll(PDO::FETCH_ASSOC) as $dl) {
            $userDownloads[(int)$dl['app_id']] = $dl['downloaded_version'];
        }
    } catch (PDOException $e) { /* table may not exist yet */ }
}

$pageTitle = 'Gilaf App Store';
include __DIR__ . '/includes/new-header.php';
?>

<style>
/* ══════════════ PLAY STORE STYLE ══════════════ */
:root {
    --gs-primary: #1a3c34;
    --gs-accent: #01875f;
    --gs-accent-light: #e6f4ea;
    --gs-text: #202124;
    --gs-text-secondary: #5f6368;
    --gs-border: #dadce0;
    --gs-bg: #f8f9fa;
    --gs-card: #ffffff;
    --gs-update: #01875f;
    --gs-download: #01875f;
}

.gs-page { background: var(--gs-bg); min-height: 80vh; }

/* ─── Top Bar ─── */
.gs-topbar {
    background: var(--gs-card);
    border-bottom: 1px solid var(--gs-border);
    padding: 16px 0;
    position: sticky; top: 0; z-index: 100;
}
.gs-topbar-inner {
    max-width: 1100px; margin: 0 auto; padding: 0 24px;
    display: flex; align-items: center; gap: 16px;
}
.gs-logo {
    display: flex; align-items: center; gap: 10px;
    font-size: 20px; font-weight: 700; color: var(--gs-text);
    text-decoration: none;
}
.gs-logo-icon {
    width: 36px; height: 36px; border-radius: 8px;
    background: linear-gradient(135deg, #1a3c34, #22c55e);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 16px;
}
.gs-search {
    flex: 1; max-width: 480px; position: relative;
}
.gs-search input {
    width: 100%; padding: 10px 16px 10px 42px; border: 1px solid var(--gs-border);
    border-radius: 24px; font-size: 14px; background: var(--gs-bg);
    outline: none; transition: border-color .2s, box-shadow .2s;
}
.gs-search input:focus { border-color: var(--gs-accent); box-shadow: 0 0 0 3px rgba(1,135,95,.12); }
.gs-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gs-text-secondary); font-size: 14px; }
.gs-user-badge {
    margin-left: auto; display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--gs-text-secondary);
}
.gs-user-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--gs-accent); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px;
}

/* ─── Content ─── */
.gs-content { max-width: 1100px; margin: 0 auto; padding: 24px 24px 60px; }

/* ─── Section Headers ─── */
.gs-section-title {
    font-size: 22px; font-weight: 700; color: var(--gs-text);
    margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
}

/* ─── Featured Banner ─── */
.gs-featured {
    background: linear-gradient(135deg, #1a3c34 0%, #0f2b24 50%, #166534 100%);
    border-radius: 16px; padding: 40px 32px; color: #fff;
    margin-bottom: 32px; position: relative; overflow: hidden;
}
.gs-featured::after {
    content: ''; position: absolute; right: -40px; top: -40px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(34,197,94,.15);
}
.gs-featured::before {
    content: ''; position: absolute; right: 60px; bottom: -60px;
    width: 160px; height: 160px; border-radius: 50%;
    background: rgba(34,197,94,.1);
}
.gs-featured-content { position: relative; z-index: 1; }
.gs-featured h2 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
.gs-featured p { font-size: 15px; opacity: .85; max-width: 550px; margin-bottom: 20px; line-height: 1.6; }
.gs-featured-stats { display: flex; gap: 32px; }
.gs-featured-stat { text-align: center; }
.gs-featured-stat-val { font-size: 24px; font-weight: 800; }
.gs-featured-stat-label { font-size: 11px; opacity: .7; text-transform: uppercase; letter-spacing: .5px; }

/* ─── App List (Play Store style) ─── */
.gs-app-list { display: flex; flex-direction: column; gap: 0; }

.gs-app-item {
    background: var(--gs-card);
    border: 1px solid var(--gs-border);
    border-radius: 12px;
    padding: 20px;
    display: flex; align-items: center; gap: 16px;
    transition: background .15s, box-shadow .2s;
    margin-bottom: 12px;
    cursor: default;
}
.gs-app-item:hover { background: #f8fffe; box-shadow: 0 2px 12px rgba(0,0,0,.06); }

.gs-app-icon {
    width: 64px; height: 64px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, #1a3c34, #22c55e);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 26px; font-weight: 800; overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.gs-app-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }

.gs-app-details { flex: 1; min-width: 0; }
.gs-app-name {
    font-size: 16px; font-weight: 600; color: var(--gs-text);
    margin-bottom: 2px; line-height: 1.3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.gs-app-publisher { font-size: 12px; color: var(--gs-accent); font-weight: 500; margin-bottom: 4px; }

.gs-app-rating { display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
.gs-app-rating .stars { color: #fbbc04; font-size: 12px; }
.gs-app-rating .rating-text { font-size: 11px; color: var(--gs-text-secondary); }

.gs-app-meta-row {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    font-size: 12px; color: var(--gs-text-secondary);
}
.gs-app-meta-item { display: flex; align-items: center; gap: 3px; }
.gs-app-meta-item i { font-size: 10px; }
.gs-meta-dot { width: 3px; height: 3px; border-radius: 50%; background: #dadce0; }

.gs-app-desc {
    font-size: 13px; color: var(--gs-text-secondary); line-height: 1.5;
    margin-top: 6px; display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}

/* ─── Action Buttons ─── */
.gs-app-actions { flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 6px; min-width: 110px; }

.gs-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 13px;
    border: none; cursor: pointer; transition: all .2s; text-decoration: none;
    min-width: 110px; text-align: center;
}
.gs-btn-download { background: var(--gs-download); color: #fff; }
.gs-btn-download:hover { background: #016b4f; color: #fff; box-shadow: 0 2px 8px rgba(1,135,95,.3); }

.gs-btn-update { background: var(--gs-accent-light); color: var(--gs-update); border: 1px solid var(--gs-update); }
.gs-btn-update:hover { background: var(--gs-update); color: #fff; }

.gs-btn-installed { background: #f1f3f4; color: var(--gs-text-secondary); cursor: default; border: 1px solid #dadce0; }

.gs-btn-size { font-size: 11px; color: var(--gs-text-secondary); }

/* ─── Verified Badge ─── */
.gs-verified {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; color: var(--gs-accent); font-weight: 600;
}
.gs-verified i { font-size: 12px; }

/* ─── Empty State ─── */
.gs-empty {
    text-align: center; padding: 80px 20px; color: var(--gs-text-secondary);
}
.gs-empty-icon { font-size: 64px; color: #dadce0; margin-bottom: 20px; }
.gs-empty h3 { font-size: 20px; font-weight: 600; color: var(--gs-text); margin-bottom: 8px; }
.gs-empty p { font-size: 14px; max-width: 400px; margin: 0 auto; }

/* ─── Flash Message ─── */
.gs-flash {
    max-width: 1100px; margin: 16px auto 0; padding: 0 24px;
}

/* ─── Responsive ─── */
@media (max-width: 768px) {
    .gs-topbar-inner { padding: 0 16px; }
    .gs-search { display: none; }
    .gs-content { padding: 16px 16px 40px; }
    .gs-featured { padding: 28px 20px; border-radius: 12px; }
    .gs-featured h2 { font-size: 22px; }
    .gs-featured-stats { gap: 20px; }
    .gs-app-item {
        flex-direction: column; align-items: flex-start;
        padding: 16px; gap: 12px;
    }
    .gs-app-item .gs-app-actions {
        flex-direction: row; width: 100%;
        justify-content: space-between; align-items: center;
    }
    .gs-app-icon { width: 52px; height: 52px; font-size: 22px; border-radius: 12px; }
    .gs-btn { min-width: 100px; padding: 9px 20px; }
    .gs-section-title { font-size: 18px; }
}

@media (max-width: 480px) {
    .gs-featured-stats { flex-direction: row; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: flex-start; }
    .gs-featured-stat { text-align: center; min-width: 0; }
    .gs-featured-stat-val { font-size: 18px; }
    .gs-featured-stat-label { font-size: 9px; }
    .gs-featured h2 { font-size: 20px; }
    .gs-featured p { font-size: 13px; }
    .gs-logo span { display: none; }
    .gs-app-name { font-size: 14px; white-space: normal; }
    .gs-app-meta-row { gap: 6px; }
    .gs-app-desc { -webkit-line-clamp: 1; }
}
</style>

<div class="gs-page">

<!-- ─── Top Bar ─── -->
<div class="gs-topbar">
    <div class="gs-topbar-inner">
        <a href="<?= base_url('app_store.php'); ?>" class="gs-logo">
            <div class="gs-logo-icon"><i class="fas fa-store"></i></div>
            <span>Gilaf App Store</span>
        </a>
        <div class="gs-search">
            <i class="fas fa-search"></i>
            <input type="text" id="gsSearchInput" placeholder="Search apps..." oninput="gsFilterApps()">
        </div>
        <div class="gs-user-badge">
            <div class="gs-user-avatar"><?= strtoupper(substr($userName, 0, 1)); ?></div>
            <span class="d-none d-md-inline"><?= htmlspecialchars($userName); ?></span>
        </div>
    </div>
</div>

<?php
// Show flash messages
if (!empty($_SESSION['flash'])):
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
<div class="gs-flash">
    <div class="alert alert-<?= htmlspecialchars($f['type']); ?> alert-dismissible fade show" role="alert" style="border-radius:12px;margin-top:16px;">
        <?= htmlspecialchars($f['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<div class="gs-content">

    <!-- ─── Featured Banner ─── -->
    <div class="gs-featured">
        <div class="gs-featured-content">
            <h2>Official Gilaf Apps</h2>
            <p>Download verified apps built by Gilaf. Secure, always up-to-date, and tailored for your role. Get the tools you need to work smarter.</p>
            <div class="gs-featured-stats">
                <div class="gs-featured-stat">
                    <div class="gs-featured-stat-val"><?= count($apps); ?></div>
                    <div class="gs-featured-stat-label">Apps Available</div>
                </div>
                <div class="gs-featured-stat">
                    <div class="gs-featured-stat-val"><?= array_sum(array_column($apps, 'download_count')); ?>+</div>
                    <div class="gs-featured-stat-label">Downloads</div>
                </div>
                <div class="gs-featured-stat">
                    <div class="gs-featured-stat-val"><i class="fas fa-shield-alt" style="font-size:20px;"></i></div>
                    <div class="gs-featured-stat-label">Verified & Secure</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Apps Section ─── -->
    <div class="gs-section-title">
        <i class="fas fa-th-large" style="color:var(--gs-accent);font-size:18px;"></i>
        Available for you
    </div>

    <?php if (empty($apps)): ?>
    <div class="gs-empty">
        <div class="gs-empty-icon"><i class="fas fa-mobile-alt"></i></div>
        <h3>No Apps Available</h3>
        <p>There are no apps available for your account at this time. New apps will appear here when they're assigned to your group.</p>
    </div>
    <?php else: ?>
    <div class="gs-app-list" id="gsAppList">
        <?php foreach ($apps as $app):
            $appVersion = $app['version'] ?: '1.0.0';
            $appSize = $app['apk_size'] > 0 ? round($app['apk_size'] / 1048576, 1) : 0;
            $canAccess = $appAccess[(int)$app['id']] ?? false;
            $hasDownloaded = $canAccess && isset($userDownloads[(int)$app['id']]);
            $downloadedVersion = $hasDownloaded ? $userDownloads[(int)$app['id']] : null;
            $hasUpdate = $hasDownloaded && version_compare($appVersion, $downloadedVersion, '>');
            $isUpToDate = $hasDownloaded && !$hasUpdate;
            $isRestricted = $app['visibility'] === 'restricted';
        ?>
        <div class="gs-app-item" data-appname="<?= htmlspecialchars(strtolower($app['app_name'])); ?>" data-pkg="<?= htmlspecialchars(strtolower($app['package_name'] ?? '')); ?>">
            <div class="gs-app-icon">
                <?php if ($app['icon_path'] && file_exists(__DIR__ . '/' . $app['icon_path'])): ?>
                    <img src="<?= base_url($app['icon_path']); ?>" alt="<?= htmlspecialchars($app['app_name']); ?>">
                <?php else: ?>
                    <?= strtoupper(substr($app['app_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>

            <div class="gs-app-details">
                <div class="gs-app-name"><?= htmlspecialchars($app['app_name']); ?></div>
                <div class="gs-app-publisher">
                    <span class="gs-verified"><i class="fas fa-check-circle"></i> Gilaf Official</span>
                </div>
                <div class="gs-app-rating">
                    <span class="stars">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                    </span>
                    <span class="rating-text">4.5 &middot; <?= number_format($app['download_count']); ?> downloads</span>
                </div>
                <?php $isPwa = ($app['apk_path'] === 'pwa'); ?>
                <div class="gs-app-meta-row">
                    <span class="gs-app-meta-item"><i class="fas fa-code-branch"></i> v<?= htmlspecialchars($appVersion); ?></span>
                    <span class="gs-meta-dot"></span>
                    <?php if ($isPwa): ?>
                    <span class="gs-app-meta-item"><i class="fas fa-globe"></i> Web App</span>
                    <?php else: ?>
                    <span class="gs-app-meta-item"><i class="fas fa-weight-hanging"></i> <?= $appSize ? $appSize . ' MB' : '—'; ?></span>
                    <?php if ($app['package_name']): ?>
                    <span class="gs-meta-dot"></span>
                    <span class="gs-app-meta-item"><i class="fab fa-android"></i> <?= htmlspecialchars($app['package_name']); ?></span>
                    <?php endif; ?>
                    <?php endif; ?>
                    <span class="gs-meta-dot"></span>
                    <span class="gs-app-meta-item"><i class="fas fa-calendar-alt"></i> <?= date('M j, Y', strtotime($app['updated_at'] ?? $app['created_at'])); ?></span>
                </div>
                <?php if ($app['description']): ?>
                <div class="gs-app-desc"><?= htmlspecialchars($app['description']); ?></div>
                <?php endif; ?>
            </div>

            <div class="gs-app-actions">
                <?php if ($isPwa): ?>
                    <button class="gs-btn gs-btn-download gs-pwa-install-btn" onclick="gsPwaInstall(<?= (int)$app['id']; ?>)">
                        <i class="fas fa-mobile-alt"></i> Install
                    </button>
                    <span class="gs-btn-size"><i class="fas fa-globe" style="font-size:9px;"></i> No download needed</span>
                <?php elseif ($canAccess): ?>
                    <?php if ($hasUpdate): ?>
                        <a href="?download=<?= (int)$app['id']; ?>" class="gs-btn gs-btn-update">
                            <i class="fas fa-arrow-up"></i> Update
                        </a>
                        <span class="gs-btn-size">v<?= htmlspecialchars($downloadedVersion); ?> → v<?= htmlspecialchars($appVersion); ?></span>
                    <?php elseif ($isUpToDate): ?>
                        <a href="?download=<?= (int)$app['id']; ?>" class="gs-btn gs-btn-installed" title="Re-download">
                            <i class="fas fa-check"></i> Installed
                        </a>
                        <span class="gs-btn-size">Up to date</span>
                    <?php else: ?>
                        <a href="?download=<?= (int)$app['id']; ?>" class="gs-btn gs-btn-download">
                            <i class="fas fa-download"></i> Install
                        </a>
                        <span class="gs-btn-size"><?= $appSize ? $appSize . ' MB' : ''; ?></span>
                    <?php endif; ?>
                <?php elseif (!$isLoggedIn && $isRestricted): ?>
                    <a href="<?= base_url('user/login.php?redirect=app_store'); ?>" class="gs-btn gs-btn-download">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <span class="gs-btn-size"><i class="fas fa-lock" style="font-size:9px;"></i> Requires login</span>
                <?php else: ?>
                    <span class="gs-btn gs-btn-installed" style="opacity:.5;">
                        <i class="fas fa-lock"></i> Restricted
                    </span>
                    <span class="gs-btn-size">Contact admin</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /.gs-content -->
</div><!-- /.gs-page -->

<!-- PWA Install Instructions Modal -->
<div id="gsPwaModal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;max-width:420px;width:100%;padding:32px 28px;text-align:center;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <button onclick="document.getElementById('gsPwaModal').style.display='none'" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:24px;color:#94a3b8;cursor:pointer;">&times;</button>
        <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#1a3c34,#22c55e);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;margin-bottom:16px;">G</div>
        <h3 style="font-size:20px;font-weight:700;color:#202124;margin-bottom:8px;">Install Gilaf Store</h3>
        <p id="gsPwaModalText" style="font-size:14px;color:#5f6368;line-height:1.6;margin-bottom:24px;">Add Gilaf Store to your home screen for the best app-like experience.</p>
        <div id="gsPwaModalSteps" style="text-align:left;font-size:13px;color:#374151;line-height:1.8;background:#f8f9fa;border-radius:10px;padding:16px 20px;margin-bottom:20px;">
            <div id="gsPwaStepsChrome" style="display:none;">
                <strong style="color:#01875f;">Chrome / Edge:</strong><br>
                1. Tap the <strong>menu</strong> (three dots) at top-right<br>
                2. Tap <strong>"Install app"</strong> or <strong>"Add to Home screen"</strong><br>
                3. Tap <strong>"Install"</strong> to confirm
            </div>
            <div id="gsPwaStepsSafari" style="display:none;">
                <strong style="color:#01875f;">Safari (iPhone/iPad):</strong><br>
                1. Tap the <strong>Share</strong> button <span style="font-size:16px;">&#x2191;</span> at the bottom<br>
                2. Scroll down and tap <strong>"Add to Home Screen"</strong><br>
                3. Tap <strong>"Add"</strong> to confirm
            </div>
            <div id="gsPwaStepsDesktop" style="display:none;">
                <strong style="color:#01875f;">Desktop:</strong><br>
                1. Look for the <strong>install icon</strong> in the address bar<br>
                2. Click <strong>"Install"</strong> to add as a desktop app
            </div>
        </div>
        <button onclick="document.getElementById('gsPwaModal').style.display='none'" style="background:#01875f;color:#fff;border:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;">Got it</button>
    </div>
</div>

<script>
function gsFilterApps() {
    const q = document.getElementById('gsSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.gs-app-item').forEach(item => {
        const name = item.getAttribute('data-appname') || '';
        const pkg = item.getAttribute('data-pkg') || '';
        item.style.display = (!q || name.includes(q) || pkg.includes(q)) ? '' : 'none';
    });
}

// PWA Install handler
var gsPwaEvent = null;
window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    gsPwaEvent = e;
});

function gsPwaInstall(appId) {
    // If browser supports install prompt (Chrome/Edge Android)
    if (gsPwaEvent) {
        gsPwaEvent.prompt();
        gsPwaEvent.userChoice.then(function(r) {
            if (r.outcome === 'accepted') {
                // Track the install
                window.location.href = '?download=' + appId;
            }
            gsPwaEvent = null;
        });
        return;
    }

    // If already installed as PWA
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        alert('Gilaf Store is already installed on your device!');
        return;
    }

    // Show instructions modal
    var modal = document.getElementById('gsPwaModal');
    var isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
    var isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);
    var isMobile = /Android|iPhone|iPad|iPod/.test(navigator.userAgent);

    document.getElementById('gsPwaStepsChrome').style.display = (!isIOS && !isSafari) ? 'block' : 'none';
    document.getElementById('gsPwaStepsSafari').style.display = (isIOS || isSafari) ? 'block' : 'none';
    document.getElementById('gsPwaStepsDesktop').style.display = (!isMobile) ? 'block' : 'none';

    modal.style.display = 'flex';
}

// Auto-show modal if redirected from ?pwa_install=1
if (new URLSearchParams(window.location.search).get('pwa_install') === '1') {
    window.addEventListener('DOMContentLoaded', function() {
        var btns = document.querySelectorAll('.gs-pwa-install-btn');
        if (btns.length) btns[0].click();
    });
    // Clean URL
    history.replaceState(null, '', window.location.pathname);
}
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
