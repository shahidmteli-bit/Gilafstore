<?php
/**
 * Google Tag Test Page
 * Uses file-based config (google_tag_config.json).
 * Has its OWN <head> so the tag scripts actually fire in the browser.
 */

// Load the injector functions
require_once __DIR__ . '/../includes/google_tag_simple.php';

$config_file = __DIR__ . '/../google_tag_config.json';
$cfg = _gtag_read_config() ?: ['enabled' => 0, 'tags' => []];

// Migrate legacy single-tag format for display
if (!isset($cfg['tags'])) {
    $cfg = ['enabled' => !empty($cfg['enabled']) ? 1 : 0, 'tags' => [[
        'name'   => 'Legacy Tag', 'type' => 'custom', 'tag_id' => '',
        'script' => $cfg['script'] ?? '', 'enabled' => !empty($cfg['enabled']) ? 1 : 0,
        'pages'  => $cfg['pages'] ?? [], 'custom_urls' => $cfg['custom_urls'] ?? [],
    ]]];
}

$tags       = $cfg['tags'] ?? [];
$master_on  = !empty($cfg['enabled']);
$page_type  = _gtag_current_page_type();
$current_url = $_SERVER['REQUEST_URI'] ?? '/';

// Check each tag
$tag_results = [];
foreach ($tags as $i => $t) {
    $has_content = !empty($t['tag_id']) || !empty($t['script']);
    $would_fire  = $master_on && !empty($t['enabled']) && _gtag_page_match($t) && $has_content;
    $tag_results[] = [
        'name'       => $t['name'] ?? 'Tag ' . ($i + 1),
        'type'       => $t['type'] ?? 'custom',
        'tag_id'     => $t['tag_id'] ?? '',
        'enabled'    => !empty($t['enabled']),
        'page_match' => _gtag_page_match($t),
        'would_fire' => $would_fire,
        'pages'      => $t['pages'] ?? [],
    ];
}
$total_firing = count(array_filter($tag_results, fn($r) => $r['would_fire']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Tag Test — Gilaf Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php
    // ===== INJECT TAGS INTO <head> so they actually load =====
    if ($master_on) {
        foreach ($tags as $t) {
            if (empty($t['enabled'])) continue;
            $script = _gtag_build_script_for_tag($t);
            if ($script) echo $script . "\n";
        }
    }
    ?>
</head>
<body style="background:#f5f6fa;font-family:'Segoe UI',sans-serif;">

<div class="container py-4" style="max-width:960px">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="fas fa-vial text-info"></i> Google Tag Test</h2>
            <small class="text-muted">Live verification of all configured tracking tags</small>
        </div>
        <a href="google_tag_simple.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <!-- Overall Status -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="p-2">
                        <div class="fs-4 fw-bold <?= $master_on ? 'text-success' : 'text-danger' ?>"><?= $master_on ? 'ON' : 'OFF' ?></div>
                        <small class="text-muted">Master Switch</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2">
                        <div class="fs-4 fw-bold"><?= count($tags) ?></div>
                        <small class="text-muted">Total Tags</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2">
                        <div class="fs-4 fw-bold text-success"><?= $total_firing ?></div>
                        <small class="text-muted">Firing Now</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2">
                        <div class="fs-6 fw-bold"><code><?= htmlspecialchars($page_type) ?></code></div>
                        <small class="text-muted">Page Type</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════ TAG-BY-TAG DETAIL ════════ -->
    <?php if (empty($tag_results)): ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div style="width:70px;height:70px;border-radius:50%;background:#fff3e0;display:inline-flex;align-items:center;justify-content:center;font-size:28px;color:#e65100" class="mb-3">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h4>No Tags Configured</h4>
            <p class="text-muted mb-3">There are no tracking tags set up yet. Nothing will be injected into your website.</p>
            <a href="google_tag_simple.php" class="btn btn-success"><i class="fas fa-plus-circle"></i> Add Your First Tag</a>
        </div>
    </div>
    <?php else: ?>

    <?php
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
    ?>

    <?php foreach ($tag_results as $idx => $r):
        $t = $tags[$idx] ?? [];
        $pg = $r['pages'] ?? [];
        $cu = $t['custom_urls'] ?? [];
    ?>
    <div class="card mb-3 border-0 shadow-sm" style="border-left:4px solid <?= $r['would_fire'] ? '#28a745' : '#dc3545' ?> !important">
        <div class="card-body">
            <!-- Header row -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0"><?= htmlspecialchars($r['name']) ?></h5>
                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $r['type'])) ?></span>
                    <?php if ($r['tag_id']): ?>
                        <code class="ms-2"><?= htmlspecialchars($r['tag_id']) ?></code>
                    <?php endif; ?>
                </div>
                <div>
                    <?= $r['would_fire']
                        ? '<span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle"></i> FIRING</span>'
                        : '<span class="badge bg-danger px-3 py-2"><i class="fas fa-times-circle"></i> NOT FIRING</span>' ?>
                </div>
            </div>

            <!-- Status Grid -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="p-2 rounded text-center" style="background:#f8f9fb">
                        <small class="text-muted d-block">Tag Enabled</small>
                        <?= $r['enabled'] ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-danger fw-bold">No</span>' ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded text-center" style="background:#f8f9fb">
                        <small class="text-muted d-block">Master Switch</small>
                        <?= $master_on ? '<span class="text-success fw-bold">ON</span>' : '<span class="text-danger fw-bold">OFF</span>' ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded text-center" style="background:#f8f9fb">
                        <small class="text-muted d-block">Page Match</small>
                        <?= $r['page_match'] ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-warning fw-bold">No</span>' ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded text-center" style="background:#f8f9fb">
                        <small class="text-muted d-block">Has ID/Script</small>
                        <?= (!empty($r['tag_id']) || !empty($t['script'])) ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-danger fw-bold">No</span>' ?>
                    </div>
                </div>
            </div>

            <!-- Pages & URLs where this tag is active -->
            <div class="p-3 rounded mb-2" style="background:#f8f9fb">
                <strong class="d-block mb-2" style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#666">
                    <i class="fas fa-sitemap"></i> Active on Pages & URLs
                </strong>
                <?php if (in_array('all', $pg)): ?>
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#e3f2fd">
                        <span class="badge bg-info">All Pages</span>
                        <code class="text-muted" style="font-size:12px">/* (every page on the website)</code>
                    </div>
                <?php elseif (empty($pg) && empty($cu)): ?>
                    <div class="text-danger"><i class="fas fa-exclamation-triangle"></i> No pages selected — tag will not fire</div>
                <?php else: ?>
                    <?php foreach ($pg as $p): ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-primary" style="min-width:100px"><?= ucfirst(str_replace('_', ' ', $p)) ?></span>
                        <code style="font-size:12px"><?= $page_url_map[$p] ?? '/' . $p ?></code>
                        <?php if ($p === $page_type): ?>
                            <span class="badge bg-success ms-auto"><i class="fas fa-map-marker-alt"></i> Current page</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($cu)): ?>
                    <?php foreach ($cu as $u): ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-dark" style="min-width:100px">Custom URL</span>
                        <code style="font-size:12px"><?= htmlspecialchars($u) ?></code>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!$r['would_fire']): ?>
            <div class="alert alert-warning mb-0 mt-2 py-2" style="font-size:13px">
                <i class="fas fa-info-circle"></i>
                <strong>Why not firing?</strong>
                <?php if (!$master_on): ?> Master switch is OFF.
                <?php elseif (!$r['enabled']): ?> This tag is disabled.
                <?php elseif (!$r['page_match']): ?> Current page (<code><?= htmlspecialchars($page_type) ?></code>) doesn't match any selected pages.
                <?php elseif (empty($r['tag_id']) && empty($t['script'])): ?> No tag ID or script configured.
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- Live JS Test -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Live Test Results</h5>
            <button onclick="runTests()" class="btn btn-primary btn-sm"><i class="fas fa-play"></i> Run Test Again</button>
        </div>
        <div class="card-body">
            <div id="test-results">
                <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Running tests…</div>
            </div>
        </div>
    </div>

    <!-- Debug -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Debug Info</h5></div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tr><td class="fw-bold" style="width:180px">Current URL</td><td><code><?= htmlspecialchars($current_url) ?></code></td></tr>
                <tr><td class="fw-bold">Detected Page Type</td><td><code><?= htmlspecialchars($page_type) ?></code></td></tr>
                <tr><td class="fw-bold">Config File</td><td><?= file_exists($config_file) ? '<span class="text-success">Exists</span> (' . number_format(filesize($config_file)) . ' bytes)' : '<span class="text-danger">Not found</span>' ?></td></tr>
                <tr><td class="fw-bold">Last Modified</td><td><?= file_exists($config_file) ? date('M j, Y g:i:s a', filemtime($config_file)) : '—' ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Raw Config -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Raw Configuration</h5></div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded mb-0" style="max-height:300px;overflow:auto;font-size:12px"><code><?= htmlspecialchars(json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
        </div>
    </div>
</div>

<script>
function runTests() {
    const el = document.getElementById('test-results');
    el.innerHTML = '<div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Running tests…</div>';

    setTimeout(() => {
        const checks = [];

        // 1. gtag
        if (typeof gtag === 'function') checks.push({pass:true, msg:'gtag() function is available'});
        else checks.push({pass:false, msg:'gtag() function NOT found'});

        // 2. dataLayer
        if (window.dataLayer && Array.isArray(window.dataLayer)) {
            checks.push({pass:true, msg:'dataLayer exists with ' + window.dataLayer.length + ' entries'});
        } else {
            checks.push({pass:false, msg:'dataLayer NOT found'});
        }

        // 3. fbq
        if (typeof fbq === 'function') checks.push({pass:true, msg:'Meta Pixel fbq() is available'});

        // 4. Script tags
        let found = [];
        document.querySelectorAll('script[src]').forEach(s => {
            if (s.src.includes('googletagmanager.com')) found.push('Google Tag Manager: ' + s.src.split('?')[1]);
            if (s.src.includes('google-analytics.com')) found.push('Google Analytics: ' + s.src);
            if (s.src.includes('connect.facebook.net')) found.push('Meta Pixel SDK loaded');
        });
        if (found.length) found.forEach(f => checks.push({pass:true, msg:f}));
        else checks.push({pass:false, msg:'No external tracking scripts detected in DOM'});

        // 5. Network
        if (window.performance && performance.getEntriesByType) {
            const res = performance.getEntriesByType('resource');
            let hits = [];
            res.forEach(r => {
                if (r.name.includes('googletagmanager.com') || r.name.includes('google-analytics.com') || r.name.includes('connect.facebook.net'))
                    hits.push(r.name.split('?')[0].split('/').pop());
            });
            if (hits.length) checks.push({pass:true, msg:'Network requests detected: ' + [...new Set(hits)].join(', ')});
        }

        const allPass = checks.every(c => c.pass);
        const anyPass = checks.some(c => c.pass);
        let html = '<div class="list-group list-group-flush">';
        checks.forEach(c => {
            const icon = c.pass ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>';
            html += '<div class="list-group-item border-0 px-0">' + icon + ' ' + c.msg + '</div>';
        });
        html += '</div>';

        if (allPass) html = '<div class="alert alert-success mb-3"><i class="fas fa-check-circle"></i> <strong>All checks passed!</strong> Tags are firing correctly.</div>' + html;
        else if (anyPass) html = '<div class="alert alert-warning mb-3"><i class="fas fa-exclamation-triangle"></i> <strong>Some checks failed.</strong> Review below.</div>' + html;
        else html = '<div class="alert alert-danger mb-3"><i class="fas fa-times-circle"></i> <strong>No tags detected.</strong> Make sure master switch is ON and at least one tag is enabled.</div>' + html;

        el.innerHTML = html;
    }, 2000);
}

window.addEventListener('load', () => setTimeout(runTests, 1500));
</script>
</body>
</html>
