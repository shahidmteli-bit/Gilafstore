<?php
$pageTitle = 'Google Tag Manager — Admin';
$adminPage = 'google_tag_manager';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

require_once '../includes/admin_header.php';

/* ── helpers ───────────────────────────────────────────────── */
$config_file = __DIR__ . '/../google_tag_config.json';

function load_gtag_config($path) {
    if (!file_exists($path)) return ['enabled' => 0, 'tags' => []];
    $c = @json_decode(@file_get_contents($path), true);
    if (!is_array($c)) return ['enabled' => 0, 'tags' => []];
    if (!isset($c['tags'])) {
        $legacy = [
            'name'        => 'Google Tag',
            'type'        => 'custom',
            'tag_id'      => '',
            'script'      => $c['script'] ?? '',
            'enabled'     => !empty($c['enabled']) ? 1 : 0,
            'pages'       => $c['pages'] ?? ['all'],
            'custom_urls' => $c['custom_urls'] ?? [],
        ];
        $c = ['enabled' => $legacy['enabled'], 'tags' => [$legacy]];
    }
    return $c;
}

$tag_types = [
    'ga4'        => ['label' => 'Google Analytics 4',      'icon' => 'fa-chart-line',     'color' => '#4285F4', 'id_placeholder' => 'G-XXXXXXXXXX',   'id_label' => 'Measurement ID'],
    'gtm'        => ['label' => 'Google Tag Manager',       'icon' => 'fa-tags',           'color' => '#00897B', 'id_placeholder' => 'GTM-XXXXXXX',    'id_label' => 'Container ID'],
    'gads'       => ['label' => 'Google Ads',               'icon' => 'fa-ad',             'color' => '#FBBC04', 'id_placeholder' => 'AW-XXXXXXXXX',   'id_label' => 'Conversion ID'],
    'meta_pixel' => ['label' => 'Meta (Facebook) Pixel',    'icon' => 'fa-facebook',       'color' => '#1877F2', 'id_placeholder' => '123456789012345', 'id_label' => 'Pixel ID'],
    'custom'     => ['label' => 'Custom Script',            'icon' => 'fa-code',           'color' => '#6c757d', 'id_placeholder' => '',                'id_label' => ''],
];

$page_options = [
    'all'       => 'All Pages',
    'homepage'  => 'Homepage',
    'product'   => 'Product Pages',
    'shop'      => 'Shop / Category',
    'cart'      => 'Cart Page',
    'checkout'  => 'Checkout Page',
    'thank_you' => 'Thank You Page',
    'blog'      => 'Blog Pages',
    'offers'    => 'Offers Page',
    'contact'   => 'Contact Page',
];

$page_url_map = [
    'all'       => '/* (every page)',
    'homepage'  => '/',
    'product'   => '/product/*',
    'shop'      => '/shop/*',
    'cart'      => '/cart',
    'checkout'  => '/checkout',
    'thank_you' => '/thank-you, /order_success',
    'blog'      => '/blog/*',
    'offers'    => '/offers/*',
    'contact'   => '/contact',
];

/* ── handle POST ───────────────────────────────────────────── */
$flash = '';
$flash_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $config = load_gtag_config($config_file);

    if ($action === 'toggle_master') {
        $config['enabled'] = isset($_POST['master_enabled']) ? 1 : 0;
        $flash = $config['enabled'] ? 'All tags ENABLED.' : 'All tags DISABLED.';
    }

    if ($action === 'save_tag') {
        $idx          = (int)($_POST['tag_index'] ?? -1);
        $tag_name     = trim($_POST['tag_name']   ?? 'Untitled');
        $tag_type     = $_POST['tag_type']         ?? 'custom';
        $tag_id       = trim($_POST['tag_id']      ?? '');
        $tag_script   = trim($_POST['tag_script']  ?? '');
        $tag_enabled  = isset($_POST['tag_enabled']) ? 1 : 0;
        $tag_pages    = $_POST['tag_pages']        ?? ['all'];
        $tag_custom   = trim($_POST['tag_custom_urls'] ?? '');
        $custom_arr   = array_values(array_filter(array_map('trim', explode("\n", $tag_custom))));

        $tag_data = [
            'name'        => $tag_name,
            'type'        => $tag_type,
            'tag_id'      => $tag_id,
            'script'      => $tag_script,
            'enabled'     => $tag_enabled,
            'pages'       => $tag_pages,
            'custom_urls' => $custom_arr,
        ];

        if ($idx >= 0 && isset($config['tags'][$idx])) {
            $config['tags'][$idx] = $tag_data;
            $flash = 'Tag "' . htmlspecialchars($tag_name) . '" replaced / updated successfully.';
        } else {
            $config['tags'][] = $tag_data;
            $flash = 'New tag "' . htmlspecialchars($tag_name) . '" added successfully.';
        }
    }

    if ($action === 'toggle_tag') {
        $idx = (int)($_POST['tag_index'] ?? -1);
        if ($idx >= 0 && isset($config['tags'][$idx])) {
            $config['tags'][$idx]['enabled'] = empty($config['tags'][$idx]['enabled']) ? 1 : 0;
            $name = $config['tags'][$idx]['name'] ?? 'Tag';
            $state = $config['tags'][$idx]['enabled'] ? 'enabled' : 'disabled';
            $flash = 'Tag "' . htmlspecialchars($name) . '" ' . $state . '.';
        }
    }

    if ($action === 'delete_tag') {
        $idx = (int)($_POST['tag_index'] ?? -1);
        if ($idx >= 0 && isset($config['tags'][$idx])) {
            $deleted_name = $config['tags'][$idx]['name'] ?? 'Tag';
            array_splice($config['tags'], $idx, 1);
            $flash = 'Tag "' . htmlspecialchars($deleted_name) . '" deleted.';
            $flash_type = 'warning';
        }
    }

    @file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/* ── load config for display ───────────────────────────────── */
$config = load_gtag_config($config_file);
$tags   = $config['tags'] ?? [];
$has_tags = count($tags) > 0;
$active_count = count(array_filter($tags, fn($t) => !empty($t['enabled'])));

// Check if edit mode via URL param
$edit_idx = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
?>

<div class="admin-content">

    <!-- Header -->
    <div class="content-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1><i class="fas fa-code"></i> Google Tag Manager</h1>
            <p class="text-muted mb-0">Manage tracking tags — GA4, Google Ads, Meta Pixel &amp; more</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <a href="google_tag_test.php" class="btn btn-outline-info btn-sm" target="_blank"><i class="fas fa-vial"></i> Test Tags</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> <?= $flash ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Master Toggle -->
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-1">Master Switch</h5>
                <small class="text-muted">Turn all tracking tags on or off site-wide</small>
            </div>
            <form method="POST" class="d-flex align-items-center gap-2">
                <input type="hidden" name="action" value="toggle_master">
                <div class="form-check form-switch">
                    <input class="form-check-input gtag-master-switch" type="checkbox" name="master_enabled" id="masterSwitch"
                           <?= !empty($config['enabled']) ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label fw-bold" for="masterSwitch">
                        <?= !empty($config['enabled']) ? 'ENABLED' : 'DISABLED' ?>
                    </label>
                </div>
            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         EXISTING TAGS  —  or  —  NO TAGS EMPTY STATE
         ════════════════════════════════════════════════════════ -->

    <?php if ($has_tags): ?>

    <!-- Section Header with Add New button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-tags"></i> Your Tags (<?= count($tags) ?>)</h4>
        <button class="btn btn-success btn-sm" onclick="resetForm(); document.getElementById('tagFormCard').style.display='block'; document.getElementById('tagFormCard').scrollIntoView({behavior:'smooth'})">
            <i class="fas fa-plus"></i> Add New Tag
        </button>
    </div>

    <!-- Tag Detail Cards -->
    <?php foreach ($tags as $i => $t):
        $meta = $tag_types[$t['type'] ?? 'custom'] ?? $tag_types['custom'];
        $pg   = $t['pages'] ?? [];
        $cu   = $t['custom_urls'] ?? [];
        $is_active = !empty($t['enabled']) && !empty($config['enabled']);
        $has_id    = !empty($t['tag_id']);
        $has_script = !empty($t['script']);
    ?>
    <div class="card mb-3 gtag-card <?= $is_active ? 'gtag-card-active' : 'gtag-card-inactive' ?>">
        <div class="card-body">
            <!-- Top Row: Name, Type, Status, Actions -->
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="gtag-icon-circle" style="background:<?= $meta['color'] ?>15; color:<?= $meta['color'] ?>">
                        <i class="fas <?= $meta['icon'] ?>"></i>
                    </div>
                    <div>
                        <h5 class="mb-0"><?= htmlspecialchars($t['name'] ?? 'Untitled') ?></h5>
                        <span class="badge" style="background:<?= $meta['color'] ?>;color:#fff;font-size:11px"><?= $meta['label'] ?></span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($t['enabled'])): ?>
                        <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary px-3 py-2"><i class="fas fa-pause-circle"></i> Disabled</span>
                    <?php endif; ?>
                    <!-- Toggle ON/OFF -->
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle_tag">
                        <input type="hidden" name="tag_index" value="<?= $i ?>">
                        <button class="btn btn-sm <?= !empty($t['enabled']) ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= !empty($t['enabled']) ? 'Disable' : 'Enable' ?>">
                            <i class="fas <?= !empty($t['enabled']) ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tag ID / Script Info -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="gtag-detail-box">
                        <label class="gtag-detail-label"><?= $has_id ? $meta['id_label'] : 'Script' ?></label>
                        <?php if ($has_id): ?>
                            <code class="gtag-detail-value"><?= htmlspecialchars($t['tag_id']) ?></code>
                        <?php elseif ($has_script): ?>
                            <div class="gtag-script-preview"><?= htmlspecialchars(substr($t['script'], 0, 120)) ?><?= strlen($t['script']) > 120 ? '…' : '' ?></div>
                        <?php else: ?>
                            <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> No tag ID or script configured</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gtag-detail-box">
                        <label class="gtag-detail-label">Injection Location</label>
                        <span class="gtag-detail-value"><code>&lt;head&gt;</code> section — before <code>&lt;/head&gt;</code></span>
                    </div>
                </div>
            </div>

            <!-- Pages & URLs where this tag loads -->
            <div class="gtag-detail-box mb-3">
                <label class="gtag-detail-label"><i class="fas fa-file-alt"></i> Active on Pages & URLs</label>
                <div class="gtag-pages-list">
                    <?php if (in_array('all', $pg)): ?>
                        <div class="gtag-page-row gtag-page-all">
                            <span class="badge bg-info me-2">All Pages</span>
                            <code class="text-muted">/* (every page on the website)</code>
                        </div>
                    <?php else: ?>
                        <?php if (empty($pg) && empty($cu)): ?>
                            <div class="text-danger"><i class="fas fa-exclamation-triangle"></i> No pages selected — this tag will not fire anywhere</div>
                        <?php endif; ?>
                        <?php foreach ($pg as $p): ?>
                            <div class="gtag-page-row">
                                <span class="badge bg-primary me-2"><?= ucfirst(str_replace('_', ' ', $p)) ?></span>
                                <code class="text-muted"><?= $page_url_map[$p] ?? '/' . $p ?></code>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($cu)): ?>
                        <?php foreach ($cu as $u): ?>
                            <div class="gtag-page-row">
                                <span class="badge bg-dark me-2">Custom URL</span>
                                <code><?= htmlspecialchars($u) ?></code>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 pt-2 border-top">
                <button class="btn btn-primary btn-sm" onclick="editTag(<?= $i ?>)">
                    <i class="fas fa-exchange-alt"></i> Replace / Edit
                </button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this tag?')">
                    <input type="hidden" name="action" value="delete_tag">
                    <input type="hidden" name="tag_index" value="<?= $i ?>">
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>

    <!-- ═══ NO TAGS — EMPTY STATE ═══ -->
    <div class="card mb-4">
        <div class="card-body text-center py-5">
            <div class="gtag-empty-icon mb-3">
                <i class="fas fa-tags"></i>
            </div>
            <h4>No Tracking Tags Configured</h4>
            <p class="text-muted mb-4">You haven't added any Google Analytics, Ads, or tracking tags yet.<br>Add your first tag to start tracking visitors.</p>
            <button class="btn btn-success btn-lg" onclick="document.getElementById('tagFormCard').style.display='block'; document.getElementById('tagFormCard').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-plus-circle"></i> Add Your First Tag
            </button>
        </div>
    </div>

    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════════
         ADD / EDIT TAG FORM  (hidden by default if tags exist)
         ════════════════════════════════════════════════════════ -->
    <div class="card mb-4" id="tagFormCard" style="<?= ($has_tags && $edit_idx < 0) ? 'display:none' : '' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0" id="formTitle"><i class="fas fa-plus-circle"></i> Add New Tag</h5>
            <?php if ($has_tags): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('tagFormCard').style.display='none'"><i class="fas fa-times"></i> Close</button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" id="tagForm">
                <input type="hidden" name="action" value="save_tag">
                <input type="hidden" name="tag_index" id="tagIndex" value="-1">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tag Name</label>
                        <input type="text" name="tag_name" id="tagName" class="form-control" placeholder="e.g. My GA4 Tag" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tag Type</label>
                        <select name="tag_type" id="tagType" class="form-select" onchange="onTypeChange()">
                            <?php foreach ($tag_types as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="tag_enabled" id="tagEnabled" checked>
                            <label class="form-check-label" for="tagEnabled">Enabled</label>
                        </div>
                    </div>
                </div>

                <!-- Tag ID (for structured types) -->
                <div class="row mb-3" id="tagIdRow">
                    <div class="col-md-8">
                        <label class="form-label fw-bold" id="tagIdLabel">Measurement ID</label>
                        <input type="text" name="tag_id" id="tagId" class="form-control" placeholder="G-XXXXXXXXXX">
                        <small class="text-muted" id="tagIdHint">Enter your Google Analytics 4 Measurement ID</small>
                    </div>
                </div>

                <!-- Custom Script (for custom type) -->
                <div class="row mb-3" id="tagScriptRow" style="display:none">
                    <div class="col-12">
                        <label class="form-label fw-bold">Custom Script</label>
                        <textarea name="tag_script" id="tagScript" rows="10" class="form-control font-monospace"
                                  placeholder="Paste your complete tracking script here including <script> tags..."></textarea>
                        <small class="text-muted">Paste the full script including &lt;script&gt; tags</small>
                    </div>
                </div>

                <!-- Page Targeting -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Load on Pages</label>
                        <div class="gtag-page-grid">
                            <?php foreach ($page_options as $val => $lbl): ?>
                            <label class="gtag-page-option">
                                <input type="checkbox" name="tag_pages[]" value="<?= $val ?>"
                                       class="form-check-input page-cb" <?= $val === 'all' ? 'checked' : '' ?>>
                                <span><?= $lbl ?></span>
                                <code class="gtag-page-url-hint"><?= $page_url_map[$val] ?? '' ?></code>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Custom URLs <small class="text-muted">(one per line)</small></label>
                        <textarea name="tag_custom_urls" id="tagCustomUrls" rows="5" class="form-control"
                                  placeholder="/blog&#10;/offers&#10;/landing-page"></textarea>
                        <small class="text-muted">Partial match — <code>/blog</code> matches <code>/blog/my-post</code> too</small>
                    </div>
                </div>

                <div class="d-flex gap-2 pt-3 border-top">
                    <button type="submit" class="btn btn-primary" id="btnSave"><i class="fas fa-save"></i> <span id="btnSaveText">Save Tag</span></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()"><i class="fas fa-undo"></i> Reset</button>
                    <?php if ($has_tags): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('tagFormCard').style.display='none'"><i class="fas fa-times"></i> Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Setup Guide -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle"></i> Quick Setup Guide</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-chart-line" style="color:#4285F4"></i> Google Analytics 4</h6>
                    <ol class="small">
                        <li>Go to <a href="https://analytics.google.com" target="_blank">Google Analytics</a></li>
                        <li>Admin → Data Streams → Select stream</li>
                        <li>Copy your <strong>Measurement ID</strong> (starts with <code>G-</code>)</li>
                        <li>Paste it here and save</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-tags" style="color:#00897B"></i> Google Tag Manager</h6>
                    <ol class="small">
                        <li>Go to <a href="https://tagmanager.google.com" target="_blank">Tag Manager</a></li>
                        <li>Copy your <strong>Container ID</strong> (starts with <code>GTM-</code>)</li>
                        <li>Paste it here and save</li>
                    </ol>
                </div>
                <div class="col-md-6 mt-3">
                    <h6><i class="fas fa-ad" style="color:#FBBC04"></i> Google Ads</h6>
                    <ol class="small">
                        <li>Go to <a href="https://ads.google.com" target="_blank">Google Ads</a></li>
                        <li>Tools → Conversions → Copy <strong>Conversion ID</strong> (starts with <code>AW-</code>)</li>
                        <li>Paste it here and save</li>
                    </ol>
                </div>
                <div class="col-md-6 mt-3">
                    <h6><i class="fab fa-facebook" style="color:#1877F2"></i> Meta (Facebook) Pixel</h6>
                    <ol class="small">
                        <li>Go to <a href="https://business.facebook.com/events_manager" target="_blank">Events Manager</a></li>
                        <li>Data Sources → Copy <strong>Pixel ID</strong> (numeric)</li>
                        <li>Paste it here and save</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Summary -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> Status Summary</h5></div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="p-3 rounded" style="background:#f0f4ff">
                        <h3 class="mb-1"><?= count($tags) ?></h3>
                        <small class="text-muted">Total Tags</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded" style="background:#e8f5e9">
                        <h3 class="mb-1 text-success"><?= $active_count ?></h3>
                        <small class="text-muted">Active Tags</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded" style="background:#fff3e0">
                        <h3 class="mb-1" style="color:#e65100"><?= !empty($config['enabled']) ? 'ON' : 'OFF' ?></h3>
                        <small class="text-muted">Master Switch</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded" style="background:#f3e5f5">
                        <h3 class="mb-1" style="color:#7b1fa2">
                            <?= file_exists($config_file) ? date('M j', filemtime($config_file)) : '—' ?>
                        </h3>
                        <small class="text-muted">Last Updated</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tag data for JS editing -->
<script id="tagsData" type="application/json"><?= json_encode($tags, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script id="tagTypesData" type="application/json"><?= json_encode($tag_types, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<script>
const tagsData  = JSON.parse(document.getElementById('tagsData').textContent);
const typesMeta = JSON.parse(document.getElementById('tagTypesData').textContent);

function onTypeChange() {
    const type = document.getElementById('tagType').value;
    const idRow     = document.getElementById('tagIdRow');
    const scriptRow = document.getElementById('tagScriptRow');
    const idLabel   = document.getElementById('tagIdLabel');
    const idInput   = document.getElementById('tagId');
    const idHint    = document.getElementById('tagIdHint');
    const meta      = typesMeta[type] || typesMeta['custom'];

    if (type === 'custom') {
        idRow.style.display     = 'none';
        scriptRow.style.display = '';
    } else {
        idRow.style.display     = '';
        scriptRow.style.display = 'none';
        idLabel.textContent     = meta.id_label;
        idInput.placeholder     = meta.id_placeholder;
        idHint.textContent      = 'Enter your ' + meta.label + ' ' + meta.id_label;
    }
}

function editTag(idx) {
    const tag = tagsData[idx];
    if (!tag) return;

    document.getElementById('tagFormCard').style.display = 'block';
    document.getElementById('tagIndex').value   = idx;
    document.getElementById('tagName').value    = tag.name || '';
    document.getElementById('tagType').value    = tag.type || 'custom';
    document.getElementById('tagId').value      = tag.tag_id || '';
    document.getElementById('tagScript').value  = tag.script || '';
    document.getElementById('tagEnabled').checked = !!tag.enabled;
    document.getElementById('tagCustomUrls').value = (tag.custom_urls || []).join('\n');
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-exchange-alt"></i> Replace / Edit Tag: <strong>' + (tag.name || 'Untitled') + '</strong>';
    document.getElementById('btnSaveText').textContent = 'Replace Tag';

    const pages = tag.pages || [];
    document.querySelectorAll('.page-cb').forEach(cb => {
        cb.checked = pages.includes(cb.value);
    });

    onTypeChange();
    document.getElementById('tagFormCard').scrollIntoView({behavior:'smooth'});
}

function resetForm() {
    document.getElementById('tagIndex').value = -1;
    document.getElementById('tagName').value  = '';
    document.getElementById('tagType').value  = 'ga4';
    document.getElementById('tagId').value    = '';
    document.getElementById('tagScript').value = '';
    document.getElementById('tagEnabled').checked = true;
    document.getElementById('tagCustomUrls').value = '';
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Tag';
    document.getElementById('btnSaveText').textContent = 'Save Tag';
    document.querySelectorAll('.page-cb').forEach(cb => cb.checked = cb.value === 'all');
    onTypeChange();
}

// Handle "All Pages" checkbox logic
document.querySelectorAll('.page-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        if (this.value === 'all' && this.checked) {
            document.querySelectorAll('.page-cb').forEach(c => { if (c.value !== 'all') c.checked = false; });
        } else if (this.value !== 'all' && this.checked) {
            document.querySelector('.page-cb[value="all"]').checked = false;
        }
    });
});

// Auto-edit if ?edit=N in URL
<?php if ($edit_idx >= 0 && isset($tags[$edit_idx])): ?>
document.addEventListener('DOMContentLoaded', () => editTag(<?= $edit_idx ?>));
<?php endif; ?>

onTypeChange();
</script>

<style>
/* Master switch */
.gtag-master-switch { width: 3rem; height: 1.5rem; cursor: pointer; }

/* Tag cards */
.gtag-card { border-left: 4px solid #dee2e6; transition: all .2s; }
.gtag-card-active { border-left-color: #28a745; }
.gtag-card-inactive { border-left-color: #6c757d; opacity: .85; }
.gtag-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }

.gtag-icon-circle {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}

/* Detail boxes */
.gtag-detail-box {
    background: #f8f9fb; border-radius: 8px; padding: 10px 14px;
}
.gtag-detail-label {
    display: block; font-size: 11px; text-transform: uppercase;
    letter-spacing: .5px; color: #888; margin-bottom: 4px; font-weight: 600;
}
.gtag-detail-value { font-size: 14px; }
.gtag-script-preview {
    font-family: 'Courier New', monospace; font-size: 11px;
    color: #555; white-space: pre-wrap; word-break: break-all;
}

/* Pages list */
.gtag-pages-list { display: flex; flex-direction: column; gap: 6px; margin-top: 6px; }
.gtag-page-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.gtag-page-row code { font-size: 12px; }
.gtag-page-all { background: #e3f2fd; border-radius: 6px; padding: 6px 10px; }

/* Empty state */
.gtag-empty-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: #f0f4ff; display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 32px; color: #4285F4;
}

/* Form page grid */
.gtag-page-grid { display: flex; flex-direction: column; gap: 8px; }
.gtag-page-option {
    display: flex; align-items: center; gap: 8px;
    font-weight: normal; font-size: 14px; cursor: pointer;
    padding: 4px 8px; border-radius: 6px; transition: background .15s;
}
.gtag-page-option:hover { background: #f0f4ff; }
.gtag-page-url-hint { font-size: 11px; color: #999; margin-left: auto; }

.font-monospace { font-family: 'Courier New', Courier, monospace; font-size: 13px; }

.alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 6px; }
.alert-success { color: #155724; background: #d4edda; border-color: #c3e6cb; }
.alert-warning { color: #856404; background: #fff3cd; border-color: #ffeaa7; }
</style>

<?php require_once '../includes/admin_footer.php'; ?>
