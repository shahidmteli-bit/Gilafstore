<?php
/**
 * CANONICAL DOMAIN VALIDATION REPORT
 * Scans entire codebase and database for non-canonical URL references
 * Validates redirect configuration and provides actionable report
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$canonicalDomain = 'https://gilafstore.com';
$issues = [];
$warnings = [];
$passed = [];

// 1. Check .htaccess redirect rules
$htaccessPath = __DIR__ . '/../.htaccess';
if (file_exists($htaccessPath)) {
    $htaccess = file_get_contents($htaccessPath);
    
    if (strpos($htaccess, 'RewriteCond %{HTTPS} off') !== false) {
        $passed[] = '.htaccess: HTTPS redirect rule found';
    } else {
        $issues[] = '.htaccess: Missing HTTPS redirect rule';
    }
    
    if (strpos($htaccess, 'www\.gilafstore\.com') !== false && strpos($htaccess, 'https://gilafstore.com') !== false) {
        $passed[] = '.htaccess: WWW removal redirect found';
    } else {
        $issues[] = '.htaccess: Missing WWW removal redirect';
    }
    
    if (strpos($htaccess, 'https://www.gilafstore.com') !== false) {
        $issues[] = '.htaccess: Contains reference to www domain (should be non-www)';
    }
} else {
    $issues[] = '.htaccess file not found';
}

// 2. Scan PHP files for hardcoded URLs
$phpFiles = [
    'includes/seo_functions.php',
    'includes/new-header.php',
    'product.php',
    'shop.php',
    'product-sitemap.php',
    'category-sitemap.php',
    'merchant-feed.php',
    'blogs.php',
    'admin/product_edit.php'
];

foreach ($phpFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        if (strpos($content, 'https://www.gilafstore.com') !== false) {
            $issues[] = "$file: Contains www.gilafstore.com references";
        } elseif (strpos($content, 'http://gilafstore.com') !== false) {
            $issues[] = "$file: Contains http:// references (should be https://)";
        } elseif (strpos($content, 'http://www.gilafstore.com') !== false) {
            $issues[] = "$file: Contains http://www references";
        } else {
            $passed[] = "$file: Clean (no non-canonical URLs)";
        }
    } else {
        $warnings[] = "$file: File not found";
    }
}

// 3. Check database for stored URLs
$db = get_db_connection();
$dbIssues = [];

try {
    // Products table
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM products WHERE canonical_override LIKE '%www.gilafstore.com%' OR canonical_override LIKE 'http://gilafstore.com%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['cnt'] > 0) {
        $dbIssues[] = "Products table: {$result['cnt']} rows with non-canonical URLs in canonical_override";
    } else {
        $passed[] = "Products canonical_override: Clean";
    }
    
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM products WHERE og_image_url LIKE '%www.gilafstore.com%' OR og_image_url LIKE 'http://gilafstore.com%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['cnt'] > 0) {
        $dbIssues[] = "Products table: {$result['cnt']} rows with non-canonical URLs in og_image_url";
    } else {
        $passed[] = "Products og_image_url: Clean";
    }
    
    // Categories table
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM categories WHERE canonical_override LIKE '%www.gilafstore.com%' OR canonical_override LIKE 'http://gilafstore.com%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['cnt'] > 0) {
        $dbIssues[] = "Categories table: {$result['cnt']} rows with non-canonical URLs";
    } else {
        $passed[] = "Categories canonical_override: Clean";
    }
    
    // SEO redirects table
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM seo_redirects WHERE old_path LIKE '%www.gilafstore.com%' OR new_path LIKE '%www.gilafstore.com%' OR old_path LIKE 'http://gilafstore.com%' OR new_path LIKE 'http://gilafstore.com%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['cnt'] > 0) {
        $dbIssues[] = "SEO redirects table: {$result['cnt']} rows with non-canonical URLs";
    } else {
        $passed[] = "SEO redirects: Clean";
    }
    
} catch (Exception $e) {
    $warnings[] = "Database check error: " . $e->getMessage();
}

if (!empty($dbIssues)) {
    $issues = array_merge($issues, $dbIssues);
}

// 4. Security headers check
if (strpos($htaccess, 'Strict-Transport-Security') !== false) {
    $passed[] = 'HSTS header configured in .htaccess';
} else {
    $warnings[] = 'HSTS header not found (recommended for HTTPS enforcement)';
}

// Calculate status
$totalChecks = count($passed) + count($issues) + count($warnings);
$passRate = $totalChecks > 0 ? round((count($passed) / $totalChecks) * 100) : 0;
$status = count($issues) === 0 ? 'PASSED' : 'FAILED';
$statusColor = $status === 'PASSED' ? '#10b981' : '#ef4444';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canonical Domain Validation - Gilaf Store</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 40px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: #fff; padding: 40px; border-radius: 12px 12px 0 0; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header h1 { font-size: 32px; color: #1a1a1a; margin-bottom: 10px; }
        .header .subtitle { color: #666; font-size: 14px; margin-bottom: 20px; }
        .status-badge { display: inline-block; padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 14px; color: #fff; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .stat-card { background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 36px; font-weight: 700; margin-bottom: 5px; }
        .stat-label { color: #6b7280; font-size: 14px; }
        .stat-card.passed .stat-value { color: #10b981; }
        .stat-card.issues .stat-value { color: #ef4444; }
        .stat-card.warnings .stat-value { color: #f59e0b; }
        .section { background: #fff; padding: 30px 40px; border-top: 1px solid #e5e7eb; }
        .section:last-child { border-radius: 0 0 12px 12px; }
        .section h2 { font-size: 20px; color: #1a1a1a; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .issue-list, .warning-list, .passed-list { list-style: none; }
        .issue-list li, .warning-list li, .passed-list li { padding: 12px; margin: 8px 0; border-radius: 6px; display: flex; align-items: flex-start; gap: 10px; }
        .issue-list li { background: #fee2e2; color: #991b1b; }
        .warning-list li { background: #fef3c7; color: #92400e; }
        .passed-list li { background: #d1fae5; color: #065f46; }
        .issue-list li::before { content: '✗'; font-weight: bold; color: #ef4444; }
        .warning-list li::before { content: '⚠'; font-weight: bold; color: #f59e0b; }
        .passed-list li::before { content: '✓'; font-weight: bold; color: #10b981; }
        .action-box { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .action-box h3 { color: #075985; margin-bottom: 15px; font-size: 16px; }
        .action-box ol { margin-left: 20px; color: #0c4a6e; }
        .action-box li { margin: 10px 0; line-height: 1.6; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 20px; margin-right: 10px; }
        .btn:hover { background: #5568d3; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: 'Courier New', monospace; color: #1f2937; }
        .canonical-domain { font-size: 18px; font-weight: 700; color: #667eea; background: #f0f9ff; padding: 12px 20px; border-radius: 8px; display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 Canonical Domain Validation</h1>
        <p class="subtitle">Comprehensive audit of URL canonicalization across codebase and database</p>
        <span class="status-badge" style="background: <?= $statusColor ?>;"><?= $status ?></span>
        
        <div class="canonical-domain">
            Canonical Domain: <?= htmlspecialchars($canonicalDomain) ?>
        </div>
        
        <div class="stats">
            <div class="stat-card passed">
                <div class="stat-value"><?= count($passed) ?></div>
                <div class="stat-label">Passed Checks</div>
            </div>
            <div class="stat-card issues">
                <div class="stat-value"><?= count($issues) ?></div>
                <div class="stat-label">Issues Found</div>
            </div>
            <div class="stat-card warnings">
                <div class="stat-value"><?= count($warnings) ?></div>
                <div class="stat-label">Warnings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #667eea;"><?= $passRate ?>%</div>
                <div class="stat-label">Pass Rate</div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($issues)): ?>
    <div class="section">
        <h2>❌ Issues Found (<?= count($issues) ?>)</h2>
        <ul class="issue-list">
            <?php foreach ($issues as $issue): ?>
                <li><?= htmlspecialchars($issue) ?></li>
            <?php endforeach; ?>
        </ul>
        
        <div class="action-box">
            <h3>🔧 Required Actions</h3>
            <ol>
                <li><strong>Run Database Update:</strong> Visit <code>update_canonical_domain.php</code> to fix all database URLs</li>
                <li><strong>Upload .htaccess:</strong> Ensure the updated .htaccess file is on the live server</li>
                <li><strong>Clear Cache:</strong> Delete sitemap cache files in <code>/cache/</code> folder</li>
                <li><strong>Re-run Validation:</strong> Refresh this page to verify all issues are resolved</li>
            </ol>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($warnings)): ?>
    <div class="section">
        <h2>⚠️ Warnings (<?= count($warnings) ?>)</h2>
        <ul class="warning-list">
            <?php foreach ($warnings as $warning): ?>
                <li><?= htmlspecialchars($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>✅ Passed Checks (<?= count($passed) ?>)</h2>
        <ul class="passed-list">
            <?php foreach ($passed as $pass): ?>
                <li><?= htmlspecialchars($pass) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="section">
        <h2>📋 Redirect Test Commands</h2>
        <p style="margin-bottom: 15px; color: #6b7280;">Test these URLs to verify single-hop 301 redirects:</p>
        <pre style="background: #1f2937; color: #10b981; padding: 20px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.8;">curl -I http://gilafstore.com
# Expected: 301 → https://gilafstore.com

curl -I http://www.gilafstore.com
# Expected: 301 → https://gilafstore.com

curl -I https://www.gilafstore.com
# Expected: 301 → https://gilafstore.com

curl -I https://gilafstore.com
# Expected: 200 OK (final destination)</pre>
    </div>
    
    <div class="section">
        <?php if (count($issues) > 0): ?>
            <a href="update_canonical_domain.php" class="btn btn-success">🔧 Fix Database URLs</a>
        <?php endif; ?>
        <a href="<?= base_url('admin/dashboard.php') ?>" class="btn">← Back to Dashboard</a>
        <a href="?refresh=1" class="btn" style="background: #6b7280;">🔄 Refresh Validation</a>
    </div>
</div>
</body>
</html>
