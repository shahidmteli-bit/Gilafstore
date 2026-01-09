<?php
// Deep Cart Navigation Analysis - Comprehensive Debug Report
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Deep Cart Analysis</title>";
echo "<style>
body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.section { background: white; padding: 25px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.section h2 { color: #1a3c34; margin-top: 0; border-bottom: 3px solid #1a3c34; padding-bottom: 10px; }
.pass { color: #2E7D32; font-weight: bold; }
.fail { color: #D32F2F; font-weight: bold; }
.warn { color: #F57C00; font-weight: bold; }
pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #1a3c34; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
table td, table th { padding: 12px; border: 1px solid #ddd; text-align: left; }
table th { background: #1a3c34; color: white; font-weight: 600; }
.test-pass { background: #e8f5e9; }
.test-fail { background: #ffebee; }
.test-warn { background: #fff3e0; }
.code-block { background: #263238; color: #aed581; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; }
.highlight { background: yellow; padding: 2px 4px; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
.badge-success { background: #4caf50; color: white; }
.badge-error { background: #f44336; color: white; }
.badge-warning { background: #ff9800; color: white; }
</style></head><body>";

echo "<h1>🔬 Deep Cart Navigation Analysis</h1>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";

// ============================================
// SECTION 1: Environment Check
// ============================================
echo "<div class='section'>";
echo "<h2>1. Environment & Server Configuration</h2>";
echo "<table>";
echo "<tr><th>Parameter</th><th>Value</th><th>Status</th></tr>";

$checks = [
    'PHP Version' => PHP_VERSION,
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Script Filename' => __FILE__,
    'Current URL' => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? ''),
    'Session Status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'
];

foreach ($checks as $key => $value) {
    $status = '<span class="pass">✓</span>';
    echo "<tr><td><strong>$key</strong></td><td><code>$value</code></td><td>$status</td></tr>";
}
echo "</table>";
echo "</div>";

// ============================================
// SECTION 2: File System Analysis
// ============================================
echo "<div class='section'>";
echo "<h2>2. Critical Files Analysis</h2>";

$files = [
    'cart.php' => __DIR__ . '/cart.php',
    'includes/cart.php' => __DIR__ . '/includes/cart.php',
    'includes/functions.php' => __DIR__ . '/includes/functions.php',
    'includes/new-header.php' => __DIR__ . '/includes/new-header.php',
    'assets/css/new-design.css' => __DIR__ . '/assets/css/new-design.css',
    'assets/css/style.css' => __DIR__ . '/assets/css/style.css',
    'assets/js/new-main.js' => __DIR__ . '/assets/js/new-main.js'
];

echo "<table>";
echo "<tr><th>File</th><th>Exists</th><th>Readable</th><th>Size</th><th>Modified</th></tr>";

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    $size = $exists ? filesize($path) : 0;
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A';
    
    $existsStatus = $exists ? '<span class="pass">✓ YES</span>' : '<span class="fail">✗ NO</span>';
    $readableStatus = $readable ? '<span class="pass">✓ YES</span>' : '<span class="fail">✗ NO</span>';
    $class = ($exists && $readable) ? 'test-pass' : 'test-fail';
    
    echo "<tr class='$class'>";
    echo "<td><strong>$name</strong></td>";
    echo "<td>$existsStatus</td>";
    echo "<td>$readableStatus</td>";
    echo "<td>" . number_format($size) . " bytes</td>";
    echo "<td>$modified</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ============================================
// SECTION 3: Functions.php Analysis
// ============================================
echo "<div class='section'>";
echo "<h2>3. Functions.php - base_url() Analysis</h2>";

require_once __DIR__ . '/includes/functions.php';

if (function_exists('base_url')) {
    echo "<p><span class='pass'>✓ base_url() function EXISTS</span></p>";
    
    $testUrls = [
        'cart.php' => base_url('cart.php'),
        'index.php' => base_url('index.php'),
        'shop.php' => base_url('shop.php')
    ];
    
    echo "<table>";
    echo "<tr><th>Input</th><th>Generated URL</th><th>Valid</th></tr>";
    
    foreach ($testUrls as $input => $url) {
        $parsed = parse_url($url);
        $valid = $parsed && isset($parsed['path']) ? '<span class="pass">✓</span>' : '<span class="fail">✗</span>';
        echo "<tr><td><code>$input</code></td><td><code>$url</code></td><td>$valid</td></tr>";
    }
    echo "</table>";
    
    // Show base_url function code
    echo "<h3>base_url() Function Source:</h3>";
    $functionsContent = file_get_contents(__DIR__ . '/includes/functions.php');
    if (preg_match('/function base_url\([^}]+\}/s', $functionsContent, $matches)) {
        echo "<div class='code-block'>" . htmlspecialchars($matches[0]) . "</div>";
    }
} else {
    echo "<p><span class='fail'>✗ base_url() function NOT FOUND</span></p>";
}
echo "</div>";

// ============================================
// SECTION 4: Header File Analysis
// ============================================
echo "<div class='section'>";
echo "<h2>4. Header Cart Link - Source Code Analysis</h2>";

$headerPath = __DIR__ . '/includes/new-header.php';
if (file_exists($headerPath)) {
    $headerContent = file_get_contents($headerPath);
    
    // Find cart link
    echo "<h3>Cart Link HTML:</h3>";
    if (preg_match('/<a[^>]*cart\.php[^>]*>.*?<\/a>/s', $headerContent, $matches)) {
        echo "<div class='code-block'>" . htmlspecialchars($matches[0]) . "</div>";
        echo "<p><span class='pass'>✓ Cart link found in header</span></p>";
    } else {
        echo "<p><span class='fail'>✗ Cart link NOT found in header</span></p>";
    }
    
    // Check for onclick handlers
    echo "<h3>JavaScript Handlers Check:</h3>";
    if (preg_match('/shopping-bag[^>]*onclick/i', $headerContent)) {
        echo "<p><span class='warn'>⚠ onclick handler found on cart icon</span></p>";
    } else {
        echo "<p><span class='pass'>✓ No onclick handler on cart icon</span></p>";
    }
    
    // Extract cart-related section
    echo "<h3>Full Cart Icon Section (Lines 115-130):</h3>";
    $lines = explode("\n", $headerContent);
    $cartSection = array_slice($lines, 114, 16);
    echo "<pre>" . htmlspecialchars(implode("\n", $cartSection)) . "</pre>";
} else {
    echo "<p><span class='fail'>✗ Header file not found</span></p>";
}
echo "</div>";

// ============================================
// SECTION 5: CSS Deep Analysis
// ============================================
echo "<div class='section'>";
echo "<h2>5. CSS Files - Deep Analysis</h2>";

$cssFiles = [
    'new-design.css' => __DIR__ . '/assets/css/new-design.css',
    'style.css' => __DIR__ . '/assets/css/style.css',
    'layout-fixes.css' => __DIR__ . '/assets/css/layout-fixes.css'
];

foreach ($cssFiles as $name => $path) {
    if (!file_exists($path)) continue;
    
    echo "<h3>$name</h3>";
    $cssContent = file_get_contents($path);
    
    // Check for .user-actions rules
    if (preg_match_all('/\.user-actions[^{]*\{[^}]+\}/s', $cssContent, $matches)) {
        echo "<p><span class='badge badge-success'>" . count($matches[0]) . " .user-actions rules found</span></p>";
        foreach ($matches[0] as $rule) {
            echo "<div class='code-block'>" . htmlspecialchars($rule) . "</div>";
        }
    }
    
    // Check for pointer-events
    $pointerEventsCount = preg_match_all('/pointer-events\s*:\s*none/i', $cssContent);
    echo "<p><strong>pointer-events: none</strong> found: <span class='badge badge-warning'>$pointerEventsCount times</span></p>";
    
    // Check for ::after on user-actions
    if (preg_match('/\.user-actions[^}]*::after[^}]*\{[^}]+\}/s', $cssContent, $afterMatch)) {
        echo "<p><span class='warn'>⚠ ::after pseudo-element found on .user-actions</span></p>";
        echo "<div class='code-block'>" . htmlspecialchars($afterMatch[0]) . "</div>";
        
        if (strpos($afterMatch[0], 'pointer-events') !== false) {
            echo "<p><span class='pass'>✓ Has pointer-events: none</span></p>";
        } else {
            echo "<p><span class='fail'>✗ Missing pointer-events: none (BLOCKING CLICKS)</span></p>";
        }
    }
    
    // Check for position: relative
    if (preg_match('/\.user-actions[^}]*position\s*:\s*relative/s', $cssContent)) {
        echo "<p><span class='warn'>⚠ position: relative found (may cause issues)</span></p>";
    }
    
    // Check for z-index
    if (preg_match_all('/\.user-actions[^}]*z-index\s*:\s*(\d+)/s', $cssContent, $zMatches)) {
        echo "<p><strong>z-index values:</strong> " . implode(', ', $zMatches[1]) . "</p>";
    }
}
echo "</div>";

// ============================================
// SECTION 6: JavaScript Analysis
// ============================================
echo "<div class='section'>";
echo "<h2>6. JavaScript Event Listeners Analysis</h2>";

$jsFiles = [
    'new-main.js' => __DIR__ . '/assets/js/new-main.js',
    'main.js' => __DIR__ . '/assets/js/main.js'
];

$jsIssues = [];
foreach ($jsFiles as $name => $path) {
    if (!file_exists($path)) continue;
    
    echo "<h3>$name</h3>";
    $jsContent = file_get_contents($path);
    
    // Check for preventDefault
    if (preg_match_all('/preventDefault/i', $jsContent, $matches)) {
        $count = count($matches[0]);
        echo "<p><span class='badge badge-warning'>$count preventDefault() calls found</span></p>";
        
        // Check if it's on user-actions or cart
        if (preg_match('/(user-actions|shopping-bag|cart).*preventDefault/is', $jsContent)) {
            echo "<p><span class='fail'>✗ preventDefault found on cart-related elements (BLOCKING NAVIGATION)</span></p>";
            $jsIssues[] = "$name has preventDefault on cart elements";
        }
    }
    
    // Check for click event listeners
    if (preg_match_all('/addEventListener\s*\(\s*[\'"]click[\'"]/i', $jsContent, $matches)) {
        $count = count($matches[0]);
        echo "<p><span class='badge badge-warning'>$count click event listeners found</span></p>";
    }
    
    // Check for stopPropagation
    if (preg_match('/stopPropagation/i', $jsContent)) {
        echo "<p><span class='warn'>⚠ stopPropagation found (may block clicks)</span></p>";
        $jsIssues[] = "$name has stopPropagation";
    }
}

if (empty($jsIssues)) {
    echo "<p><span class='pass'>✓ No JavaScript issues detected</span></p>";
} else {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 4px;'>";
    echo "<strong>JavaScript Issues Found:</strong><ul>";
    foreach ($jsIssues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul></div>";
}
echo "</div>";

// ============================================
// SECTION 7: Session & Cart Data
// ============================================
echo "<div class='section'>";
echo "<h2>7. Session & Cart Data</h2>";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo "<table>";
echo "<tr><th>Property</th><th>Value</th></tr>";
echo "<tr><td><strong>Session ID</strong></td><td><code>" . session_id() . "</code></td></tr>";
echo "<tr><td><strong>Session Status</strong></td><td>" . (session_status() === PHP_SESSION_ACTIVE ? '<span class="pass">Active</span>' : '<span class="fail">Inactive</span>') . "</td></tr>";

if (isset($_SESSION['cart'])) {
    $cartCount = count($_SESSION['cart']);
    echo "<tr><td><strong>Cart Items</strong></td><td><span class='badge badge-success'>$cartCount items</span></td></tr>";
    if ($cartCount > 0) {
        echo "<tr><td><strong>Cart Contents</strong></td><td><pre>" . print_r($_SESSION['cart'], true) . "</pre></td></tr>";
    }
} else {
    echo "<tr><td><strong>Cart</strong></td><td><span class='warn'>Not initialized (empty cart)</span></td></tr>";
}
echo "</table>";
echo "</div>";

// ============================================
// SECTION 8: Direct Navigation Test
// ============================================
echo "<div class='section'>";
echo "<h2>8. Direct Navigation Tests</h2>";

$cartUrl = base_url('cart.php');
echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 4px; margin: 15px 0;'>";
echo "<h3>Test Links:</h3>";
echo "<p><a href='$cartUrl' style='display: inline-block; padding: 15px 30px; background: #1a3c34; color: white; text-decoration: none; border-radius: 8px; margin: 10px 0;'>→ Click here to navigate to Cart</a></p>";
echo "<p><strong>Expected URL:</strong> <code>$cartUrl</code></p>";
echo "<p>If this link works, the issue is with the header cart icon CSS/JS, not the cart.php file itself.</p>";
echo "</div>";
echo "</div>";

// ============================================
// SECTION 9: Root Cause Analysis
// ============================================
echo "<div class='section' style='background: #fff3e0; border-left: 5px solid #ff9800;'>";
echo "<h2>🎯 Root Cause Analysis & Diagnosis</h2>";

$issues = [];
$recommendations = [];

// Analyze findings
$headerPath = __DIR__ . '/includes/new-header.php';
if (file_exists($headerPath)) {
    $headerContent = file_get_contents($headerPath);
    if (!preg_match('/<a[^>]*cart\.php[^>]*>/i', $headerContent)) {
        $issues[] = "Cart link not found in header file";
        $recommendations[] = "Verify the cart link exists in includes/new-header.php";
    }
}

// Check CSS
$cssPath = __DIR__ . '/assets/css/new-design.css';
if (file_exists($cssPath)) {
    $cssContent = file_get_contents($cssPath);
    if (preg_match('/\.user-actions[^}]*::after/s', $cssContent) && !preg_match('/\.user-actions[^}]*::after[^}]*pointer-events\s*:\s*none/s', $cssContent)) {
        $issues[] = "::after pseudo-element on .user-actions without pointer-events: none";
        $recommendations[] = "Add 'pointer-events: none' to .user-actions ::after pseudo-element";
    }
}

if (empty($issues)) {
    echo "<p style='font-size: 18px;'><span class='pass'>✓ No critical issues detected</span></p>";
    echo "<p>The cart navigation should be working. If it's still not working:</p>";
    echo "<ol>";
    echo "<li>Clear browser cache completely (Ctrl+Shift+Delete)</li>";
    echo "<li>Try in incognito/private mode</li>";
    echo "<li>Check browser console (F12) for JavaScript errors</li>";
    echo "<li>Try clicking the test link above</li>";
    echo "</ol>";
} else {
    echo "<h3 style='color: #d32f2f;'>Issues Found:</h3>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li><strong>$issue</strong></li>";
    }
    echo "</ul>";
    
    echo "<h3 style='color: #1a3c34;'>Recommendations:</h3>";
    echo "<ol>";
    foreach ($recommendations as $rec) {
        echo "<li>$rec</li>";
    }
    echo "</ol>";
}
echo "</div>";

// ============================================
// SECTION 10: Browser Testing Instructions
// ============================================
echo "<div class='section' style='background: #e8f5e9;'>";
echo "<h2>📋 Browser Testing Checklist</h2>";
echo "<ol style='line-height: 2;'>";
echo "<li>Open browser DevTools (Press <kbd>F12</kbd>)</li>";
echo "<li>Go to <strong>Console</strong> tab</li>";
echo "<li>Navigate to homepage</li>";
echo "<li>Click the cart icon</li>";
echo "<li>Check for any red error messages in console</li>";
echo "<li>Go to <strong>Network</strong> tab</li>";
echo "<li>Click cart icon again</li>";
echo "<li>Look for any failed requests or redirects</li>";
echo "<li>Go to <strong>Elements</strong> tab</li>";
echo "<li>Right-click cart icon → Inspect</li>";
echo "<li>Check computed styles for pointer-events, z-index, position</li>";
echo "</ol>";
echo "</div>";

echo "<div style='text-align: center; padding: 30px; background: #1a3c34; color: white; margin-top: 20px;'>";
echo "<h2>Analysis Complete</h2>";
echo "<p>Review the sections above to identify the root cause of the cart navigation issue.</p>";
echo "</div>";

echo "</body></html>";
?>
