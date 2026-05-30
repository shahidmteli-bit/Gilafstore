<?php
/**
 * PROMO SYSTEM DEBUG TOOL
 * Diagnoses why setup_promo_system.php and debug_promo_system.php are not working
 */

// Disable error suppression to see all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo System Debug Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        .subtitle { color: #7f8c8d; margin-bottom: 30px; }
        .section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #34495e; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .check-item { padding: 12px; margin: 8px 0; border-radius: 6px; display: flex; align-items: center; gap: 10px; }
        .check-item.pass { background: #d4edda; border-left: 4px solid #28a745; }
        .check-item.fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .check-item.warn { background: #fff3cd; border-left: 4px solid #ffc107; }
        .icon { font-weight: bold; font-size: 18px; }
        .pass .icon { color: #28a745; }
        .fail .icon { color: #dc3545; }
        .warn .icon { color: #ffc107; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; margin: 10px 0; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; margin: 5px; transition: all 0.3s; border: none; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; }
        .fix-section { background: #e7f3ff; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Promo System Debug Tool</h1>
        <p class="subtitle">Comprehensive diagnostic for promotional system setup</p>

        <?php
        // 1. FILE EXISTENCE CHECK
        echo '<div class="section">';
        echo '<h2>📁 File Existence Check</h2>';
        
        $requiredFiles = [
            'setup_promo_system.php' => __DIR__ . '/setup_promo_system.php',
            'debug_promo_system.php' => __DIR__ . '/debug_promo_system.php',
            'manage_promotions.php' => __DIR__ . '/manage_promotions.php',
            'includes/functions.php' => __DIR__ . '/../includes/functions.php',
            'includes/auth.php' => __DIR__ . '/../includes/auth.php',
            'includes/db_connect.php' => __DIR__ . '/../includes/db_connect.php',
        ];
        
        $missingFiles = [];
        foreach ($requiredFiles as $name => $path) {
            $exists = file_exists($path);
            $class = $exists ? 'pass' : 'fail';
            $icon = $exists ? '✓' : '✗';
            echo "<div class='check-item $class'>";
            echo "<span class='icon'>$icon</span>";
            echo "<div><strong>$name:</strong> " . ($exists ? 'Found' : 'MISSING') . "<br><small>$path</small></div>";
            echo "</div>";
            if (!$exists) $missingFiles[] = $name;
        }
        echo '</div>';

        // 2. INCLUDE PATH TEST
        echo '<div class="section">';
        echo '<h2>🔗 Include Path Test</h2>';
        
        $includeTests = [
            'functions.php' => __DIR__ . '/../includes/functions.php',
            'auth.php' => __DIR__ . '/../includes/auth.php',
        ];
        
        foreach ($includeTests as $name => $path) {
            if (file_exists($path)) {
                try {
                    require_once $path;
                    echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>$name:</strong> Successfully included</div></div>";
                } catch (Exception $e) {
                    echo "<div class='check-item fail'><span class='icon'>✗</span><div><strong>$name:</strong> Include failed<br><small>" . htmlspecialchars($e->getMessage()) . "</small></div></div>";
                }
            } else {
                echo "<div class='check-item fail'><span class='icon'>✗</span><div><strong>$name:</strong> File not found</div></div>";
            }
        }
        echo '</div>';

        // 3. DATABASE CONNECTION TEST
        echo '<div class="section">';
        echo '<h2>🗄️ Database Connection Test</h2>';
        
        try {
            if (function_exists('get_db_connection')) {
                $db = get_db_connection();
                echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>Database Connection:</strong> Success</div></div>";
                
                // Check if promo tables exist
                $tables = ['promo_codes', 'promo_code_usage', 'promotions', 'promo_analytics', 'user_promo_interactions'];
                foreach ($tables as $table) {
                    $result = $db->query("SHOW TABLES LIKE '$table'");
                    $exists = $result->rowCount() > 0;
                    $class = $exists ? 'pass' : 'warn';
                    $icon = $exists ? '✓' : '⚠';
                    echo "<div class='check-item $class'><span class='icon'>$icon</span><div><strong>Table '$table':</strong> " . ($exists ? 'Exists' : 'Not created yet') . "</div></div>";
                }
            } else {
                echo "<div class='check-item fail'><span class='icon'>✗</span><div><strong>Database Connection:</strong> get_db_connection() function not found</div></div>";
            }
        } catch (Exception $e) {
            echo "<div class='check-item fail'><span class='icon'>✗</span><div><strong>Database Connection:</strong> Failed<br><small>" . htmlspecialchars($e->getMessage()) . "</small></div></div>";
        }
        echo '</div>';

        // 4. AUTHENTICATION TEST
        echo '<div class="section">';
        echo '<h2>🔐 Authentication Test</h2>';
        
        if (function_exists('require_admin')) {
            echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>require_admin() function:</strong> Available</div></div>";
        } else {
            echo "<div class='check-item fail'><span class='icon'>✗</span><div><strong>require_admin() function:</strong> Not found</div></div>";
        }
        
        if (function_exists('is_admin')) {
            $isAdmin = is_admin();
            $class = $isAdmin ? 'pass' : 'warn';
            $icon = $isAdmin ? '✓' : '⚠';
            echo "<div class='check-item $class'><span class='icon'>$icon</span><div><strong>Admin Status:</strong> " . ($isAdmin ? 'Logged in as admin' : 'Not logged in as admin') . "</div></div>";
        } else {
            echo "<div class='check-item fail'><span class='icon'>✗</span><div><strong>is_admin() function:</strong> Not found</div></div>";
        }
        echo '</div>';

        // 5. PHP ENVIRONMENT
        echo '<div class="section">';
        echo '<h2>⚙️ PHP Environment</h2>';
        echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>PHP Version:</strong> " . phpversion() . "</div></div>";
        echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</div></div>";
        echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</div></div>";
        echo "<div class='check-item pass'><span class='icon'>✓</span><div><strong>Current Script:</strong> " . __FILE__ . "</div></div>";
        echo '</div>';

        // 6. URL ACCESS TEST
        echo '<div class="section">';
        echo '<h2>🌐 URL Access Test</h2>';
        
        $baseUrl = 'https://gilafstore.com';
        $urls = [
            'setup_promo_system.php' => $baseUrl . '/admin/setup_promo_system.php',
            'debug_promo_system.php' => $baseUrl . '/admin/debug_promo_system.php',
            'manage_promotions.php' => $baseUrl . '/admin/manage_promotions.php',
            'promo_system_debug.php' => $baseUrl . '/admin/promo_system_debug.php',
        ];
        
        echo "<table>";
        echo "<tr><th>File</th><th>URL</th><th>Status</th></tr>";
        foreach ($urls as $file => $url) {
            $exists = file_exists(__DIR__ . '/' . $file);
            $status = $exists ? '<span style="color: #28a745;">✓ File exists</span>' : '<span style="color: #dc3545;">✗ File missing</span>';
            echo "<tr><td>$file</td><td><a href='$url' target='_blank'>$url</a></td><td>$status</td></tr>";
        }
        echo "</table>";
        echo '</div>';

        // 7. FIX RECOMMENDATIONS
        if (!empty($missingFiles)) {
            echo '<div class="section">';
            echo '<h2>🔧 Fix Recommendations</h2>';
            echo '<div class="fix-section">';
            echo '<h3 style="margin-bottom: 10px;">Missing Files Detected</h3>';
            echo '<p>The following files are missing and need to be uploaded:</p>';
            echo '<ul style="margin: 10px 0; padding-left: 20px;">';
            foreach ($missingFiles as $file) {
                echo "<li><strong>$file</strong></li>";
            }
            echo '</ul>';
            echo '<p style="margin-top: 10px;"><strong>Action:</strong> Upload these files via FileZilla to <code>/public_html/admin/</code></p>';
            echo '</div>';
            echo '</div>';
        }

        // 8. QUICK ACTIONS
        echo '<div class="section">';
        echo '<h2>⚡ Quick Actions</h2>';
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        
        if (file_exists(__DIR__ . '/setup_promo_system.php')) {
            echo '<a href="setup_promo_system.php" class="btn btn-success">Run Database Setup</a>';
        } else {
            echo '<button class="btn btn-danger" disabled>Setup File Missing</button>';
        }
        
        if (file_exists(__DIR__ . '/manage_promotions.php')) {
            echo '<a href="manage_promotions.php" class="btn">Manage Promotions</a>';
        } else {
            echo '<button class="btn btn-danger" disabled>Manage File Missing</button>';
        }
        
        if (file_exists(__DIR__ . '/manage_promo_codes.php')) {
            echo '<a href="manage_promo_codes.php" class="btn">Manage Promo Codes</a>';
        }
        
        echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="btn">Refresh Diagnostics</a>';
        echo '</div>';
        echo '</div>';

        // 9. DETAILED ERROR LOG
        if (file_exists(__DIR__ . '/setup_promo_system.php')) {
            echo '<div class="section">';
            echo '<h2>📄 Setup File Preview</h2>';
            echo '<p>First 20 lines of setup_promo_system.php:</p>';
            echo '<div class="code">';
            $lines = file(__DIR__ . '/setup_promo_system.php');
            echo htmlspecialchars(implode('', array_slice($lines, 0, 20)));
            echo '</div>';
            echo '</div>';
        }
        ?>

    </div>
</body>
</html>
