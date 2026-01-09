<?php
// Debug script to identify cart navigation issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Cart Navigation Debug</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.debug-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.debug-section h2 { color: #1a3c34; margin-top: 0; }
.success { color: #2E7D32; font-weight: bold; }
.error { color: #D32F2F; font-weight: bold; }
.warning { color: #F57C00; font-weight: bold; }
pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
table td, table th { padding: 8px; border: 1px solid #ddd; text-align: left; }
table th { background: #1a3c34; color: white; }
.test-pass { background: #e8f5e9; }
.test-fail { background: #ffebee; }
</style></head><body>";

echo "<h1>🔍 Cart Navigation Debug Report</h1>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Check if files exist
echo "<div class='debug-section'>";
echo "<h2>Test 1: File Existence Check</h2>";
echo "<table>";
echo "<tr><th>File</th><th>Status</th><th>Path</th></tr>";

$files = [
    'cart.php' => __DIR__ . '/cart.php',
    'includes/cart.php' => __DIR__ . '/includes/cart.php',
    'includes/functions.php' => __DIR__ . '/includes/functions.php',
    'includes/new-header.php' => __DIR__ . '/includes/new-header.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $class = $exists ? 'test-pass' : 'test-fail';
    $status = $exists ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>';
    echo "<tr class='$class'><td>$name</td><td>$status</td><td><code>$path</code></td></tr>";
}
echo "</table>";
echo "</div>";

// Test 2: Check functions.php for base_url
echo "<div class='debug-section'>";
echo "<h2>Test 2: base_url() Function Check</h2>";
require_once __DIR__ . '/includes/functions.php';

if (function_exists('base_url')) {
    echo "<p class='success'>✓ base_url() function EXISTS</p>";
    $testUrl = base_url('cart.php');
    echo "<p><strong>Test URL:</strong> <code>$testUrl</code></p>";
    
    // Check if URL is valid
    $parsed = parse_url($testUrl);
    if ($parsed && isset($parsed['path'])) {
        echo "<p class='success'>✓ URL is valid</p>";
        echo "<pre>" . print_r($parsed, true) . "</pre>";
    } else {
        echo "<p class='error'>✗ URL is invalid</p>";
    }
} else {
    echo "<p class='error'>✗ base_url() function NOT FOUND</p>";
}
echo "</div>";

// Test 3: Session check
echo "<div class='debug-section'>";
echo "<h2>Test 3: Session Status</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='success'>✓ Session is ACTIVE</p>";
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    
    if (isset($_SESSION['cart'])) {
        echo "<p class='success'>✓ Cart session exists</p>";
        echo "<p><strong>Cart items:</strong> " . count($_SESSION['cart']) . "</p>";
        if (!empty($_SESSION['cart'])) {
            echo "<pre>" . print_r($_SESSION['cart'], true) . "</pre>";
        }
    } else {
        echo "<p class='warning'>⚠ Cart session NOT set (empty cart)</p>";
    }
} else {
    echo "<p class='error'>✗ Session is NOT active</p>";
    echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";
}
echo "</div>";

// Test 4: Check cart.php accessibility
echo "<div class='debug-section'>";
echo "<h2>Test 4: Cart.php Accessibility</h2>";
$cartPath = __DIR__ . '/cart.php';
if (file_exists($cartPath)) {
    echo "<p class='success'>✓ cart.php exists</p>";
    
    // Check if readable
    if (is_readable($cartPath)) {
        echo "<p class='success'>✓ cart.php is readable</p>";
        
        // Check file size
        $size = filesize($cartPath);
        echo "<p><strong>File size:</strong> " . number_format($size) . " bytes</p>";
        
        // Check first few lines
        $content = file_get_contents($cartPath, false, null, 0, 500);
        echo "<p><strong>First 500 characters:</strong></p>";
        echo "<pre>" . htmlspecialchars($content) . "</pre>";
    } else {
        echo "<p class='error'>✗ cart.php is NOT readable</p>";
    }
} else {
    echo "<p class='error'>✗ cart.php does NOT exist</p>";
}
echo "</div>";

// Test 5: Check for PHP errors in cart.php
echo "<div class='debug-section'>";
echo "<h2>Test 5: PHP Syntax Check</h2>";
$output = [];
$return_var = 0;
exec('php -l "' . $cartPath . '" 2>&1', $output, $return_var);
if ($return_var === 0) {
    echo "<p class='success'>✓ No syntax errors in cart.php</p>";
} else {
    echo "<p class='error'>✗ Syntax errors found:</p>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
}
echo "</div>";

// Test 6: Check header location
echo "<div class='debug-section'>";
echo "<h2>Test 6: Header Cart Link Analysis</h2>";
$headerPath = __DIR__ . '/includes/new-header.php';
if (file_exists($headerPath)) {
    $headerContent = file_get_contents($headerPath);
    
    // Find cart link
    if (preg_match('/<a[^>]*href=["\']([^"\']*cart\.php[^"\']*)["\']/i', $headerContent, $matches)) {
        echo "<p class='success'>✓ Cart link found in header</p>";
        echo "<p><strong>Link pattern:</strong> <code>" . htmlspecialchars($matches[0]) . "</code></p>";
        echo "<p><strong>URL:</strong> <code>" . htmlspecialchars($matches[1]) . "</code></p>";
    } else {
        echo "<p class='error'>✗ Cart link NOT found in header</p>";
    }
    
    // Check for onclick handlers
    if (preg_match('/shopping-bag[^>]*onclick/i', $headerContent)) {
        echo "<p class='warning'>⚠ onclick handler found on cart icon (may intercept click)</p>";
    } else {
        echo "<p class='success'>✓ No onclick handler on cart icon</p>";
    }
} else {
    echo "<p class='error'>✗ Header file not found</p>";
}
echo "</div>";

// Test 7: CSS Issues Check
echo "<div class='debug-section'>";
echo "<h2>Test 7: CSS Potential Issues</h2>";
$cssFiles = [
    'assets/css/new-design.css',
    'assets/css/style.css',
    'assets/css/layout-fixes.css'
];

foreach ($cssFiles as $cssFile) {
    $cssPath = __DIR__ . '/' . $cssFile;
    if (file_exists($cssPath)) {
        $cssContent = file_get_contents($cssPath);
        
        echo "<h3>$cssFile</h3>";
        
        // Check for pointer-events
        if (preg_match('/\.user-actions.*pointer-events\s*:\s*none/s', $cssContent)) {
            echo "<p class='warning'>⚠ pointer-events: none found on .user-actions (may block clicks)</p>";
        } else {
            echo "<p class='success'>✓ No pointer-events: none on .user-actions</p>";
        }
        
        // Check for z-index issues
        if (preg_match_all('/\.user-actions[^}]*z-index\s*:\s*(\d+)/s', $cssContent, $matches)) {
            echo "<p><strong>z-index values found:</strong> " . implode(', ', $matches[1]) . "</p>";
        }
        
        // Check for ::after pseudo-elements
        if (preg_match('/\.user-actions.*::after/s', $cssContent)) {
            if (preg_match('/\.user-actions[^}]*::after[^}]*pointer-events\s*:\s*none/s', $cssContent)) {
                echo "<p class='success'>✓ ::after has pointer-events: none</p>";
            } else {
                echo "<p class='error'>✗ ::after does NOT have pointer-events: none (may block clicks)</p>";
            }
        }
    }
}
echo "</div>";

// Test 8: JavaScript Check
echo "<div class='debug-section'>";
echo "<h2>Test 8: JavaScript Event Listeners</h2>";
$jsFiles = [
    'assets/js/main.js',
    'assets/js/new-main.js'
];

$foundIssues = false;
foreach ($jsFiles as $jsFile) {
    $jsPath = __DIR__ . '/' . $jsFile;
    if (file_exists($jsPath)) {
        $jsContent = file_get_contents($jsPath);
        
        echo "<h3>$jsFile</h3>";
        
        // Check for preventDefault on links
        if (preg_match('/\.user-actions.*preventDefault|shopping-bag.*preventDefault/s', $jsContent)) {
            echo "<p class='error'>✗ preventDefault found (may block navigation)</p>";
            $foundIssues = true;
        } else {
            echo "<p class='success'>✓ No preventDefault on cart link</p>";
        }
        
        // Check for click handlers
        if (preg_match('/\.user-actions.*addEventListener.*click|shopping-bag.*addEventListener.*click/s', $jsContent)) {
            echo "<p class='warning'>⚠ Click event listener found (may intercept clicks)</p>";
            $foundIssues = true;
        } else {
            echo "<p class='success'>✓ No click event listeners on cart</p>";
        }
    }
}

if (!$foundIssues) {
    echo "<p class='success'>✓ No JavaScript issues detected</p>";
}
echo "</div>";

// Test 9: Direct navigation test
echo "<div class='debug-section'>";
echo "<h2>Test 9: Direct Navigation Test</h2>";
$cartUrl = base_url('cart.php');
echo "<p><strong>Cart URL:</strong> <a href='$cartUrl' target='_blank'>$cartUrl</a></p>";
echo "<p>Click the link above to test direct navigation to cart.php</p>";
echo "<p>If this works, the issue is with the header link or CSS/JS blocking clicks.</p>";
echo "</div>";

// Summary
echo "<div class='debug-section' style='background: #e3f2fd;'>";
echo "<h2>🎯 Summary & Recommendations</h2>";
echo "<ol>";
echo "<li>Check if cart.php loads when accessed directly via the link above</li>";
echo "<li>If direct link works, the issue is CSS or JS blocking the header cart icon</li>";
echo "<li>Clear browser cache completely (Ctrl+Shift+Delete)</li>";
echo "<li>Try in incognito/private browsing mode</li>";
echo "<li>Check browser console for JavaScript errors (F12)</li>";
echo "<li>Inspect element on cart icon to see computed styles</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
