<?php
// admin/schema_fix_v2.php
// Force disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/auth.php';

echo "<h1>Schema Diagnostic & Fix</h1>";

try {
    $db = get_db_connection();
    
    // Get DB Info
    $stmt = $db->query("SELECT DATABASE(), USER(), VERSION()");
    $info = $stmt->fetch(PDO::FETCH_NUM);
    echo "<p><strong>Database:</strong> {$info[0]}<br><strong>User:</strong> {$info[1]}<br><strong>Version:</strong> {$info[2]}</p>";

    // Check Columns
    echo "<h3>Checking Columns in 'promo_codes'...</h3>";
    $columns = [];
    $stmt = $db->query("SHOW COLUMNS FROM promo_codes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    echo "Current columns: " . implode(", ", $columns) . "<br><br>";
    
    $missingScope = !in_array('scope', $columns);
    $missingTarget = !in_array('target_ids', $columns);
    
    if ($missingScope) {
        echo "⚠️ 'scope' is MISSING. Adding it now...<br>";
        $db->exec("ALTER TABLE promo_codes ADD COLUMN scope ENUM('all', 'category', 'product') NOT NULL DEFAULT 'all' AFTER description");
        echo "✅ 'scope' added.<br>";
    } else {
        echo "✅ 'scope' exists.<br>";
    }
    
    if ($missingTarget) {
        echo "⚠️ 'target_ids' is MISSING. Adding it now...<br>";
        $db->exec("ALTER TABLE promo_codes ADD COLUMN target_ids TEXT DEFAULT NULL AFTER scope");
        echo "✅ 'target_ids' added.<br>";
    } else {
        echo "✅ 'target_ids' exists.<br>";
    }
    
    // Verify again
    echo "<h3>Final Verification</h3>";
    $stmt = $db->query("SHOW COLUMNS FROM promo_codes");
    echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bg = in_array($row['Field'], ['scope', 'target_ids']) ? "style='background-color: #d1e7dd;'" : "";
        echo "<tr $bg><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>
