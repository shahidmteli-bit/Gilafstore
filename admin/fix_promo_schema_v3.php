<?php
// admin/fix_promo_schema_v3.php
// Robust schema fixer that handles existing columns gracefully

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Check admin permission
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    die("Access Denied: Admins only.");
}

echo "<!DOCTYPE html><html><head><title>Schema Fix V3</title><style>body{font-family:sans-serif;padding:20px;max-width:800px;margin:0 auto;line-height:1.6;} .success{color:green;background:#e8f5e9;padding:10px;border-radius:4px;margin:5px 0;} .error{color:red;background:#ffebee;padding:10px;border-radius:4px;margin:5px 0;} .info{color:blue;background:#e3f2fd;padding:10px;border-radius:4px;margin:5px 0;} code{background:#f5f5f5;padding:2px 5px;border-radius:3px;}</style></head><body>";

echo "<h1>🛠️ Promo Code Schema Fixer V3</h1>";

try {
    $db = get_db_connection();
    
    // 1. Get DB Info
    $stmt = $db->query("SELECT DATABASE(), USER(), VERSION()");
    $info = $stmt->fetch(PDO::FETCH_NUM);
    echo "<div class='info'><strong>Connected to:</strong><br>Database: {$info[0]}<br>User: {$info[1]}<br>Version: {$info[2]}</div>";

    // 2. Get Current Columns
    echo "<h3>📊 Analyzing 'promo_codes' table...</h3>";
    $columns = [];
    $stmt = $db->query("SHOW COLUMNS FROM promo_codes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[$row['Field']] = $row;
    }
    
    echo "Found columns: " . implode(", ", array_keys($columns)) . "<br><br>";

    // 3. Fix 'scope' column
    if (array_key_exists('scope', $columns)) {
        echo "<div class='success'>✅ Column 'scope' ALREADY EXISTS. Skipping add.</div>";
        // Optional: Modify to ensure correct enum values if needed
        // $db->exec("ALTER TABLE promo_codes MODIFY COLUMN scope ENUM('all', 'category', 'product') NOT NULL DEFAULT 'all'");
    } else {
        echo "<div class='info'>⚠️ Column 'scope' is MISSING. Attempting to ADD...</div>";
        try {
            $db->exec("ALTER TABLE promo_codes ADD COLUMN scope ENUM('all', 'category', 'product') NOT NULL DEFAULT 'all' AFTER description");
            echo "<div class='success'>✅ Column 'scope' ADDED successfully.</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Failed to add 'scope': " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // 4. Fix 'target_ids' column
    if (array_key_exists('target_ids', $columns)) {
        echo "<div class='success'>✅ Column 'target_ids' ALREADY EXISTS. Skipping add.</div>";
    } else {
        echo "<div class='info'>⚠️ Column 'target_ids' is MISSING. Attempting to ADD...</div>";
        try {
            // Add after 'scope' if it exists, otherwise after 'description'
            $after = array_key_exists('scope', $columns) || isset($db->query("SHOW COLUMNS FROM promo_codes LIKE 'scope'")->fetch()['Field']) ? 'scope' : 'description';
            
            $db->exec("ALTER TABLE promo_codes ADD COLUMN target_ids TEXT DEFAULT NULL AFTER $after");
            echo "<div class='success'>✅ Column 'target_ids' ADDED successfully.</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Failed to add 'target_ids': " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // 5. Final Verification
    echo "<h3>🔍 Final Structure Verification</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM promo_codes");
    echo "<table border='1' cellspacing='0' cellpadding='5' style='border-collapse:collapse;width:100%;'>";
    echo "<tr style='background:#f0f0f0'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = in_array($row['Field'], ['scope', 'target_ids']) ? "background-color:#e8f5e9;font-weight:bold;" : "";
        echo "<tr style='$highlight'>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='margin-top:20px;'><strong>Instructions:</strong><br>If you see the columns 'scope' and 'target_ids' highlighted in green above, the schema is correct.<br>You can now go back and try creating/editing a promo code.</p>";
    
    echo "<a href='manage_promo_codes.php' style='display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;'>Return to Promo Codes</a>";

} catch (Exception $e) {
    echo "<div class='error'><h1>FATAL ERROR</h1>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
