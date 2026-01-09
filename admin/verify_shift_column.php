<?php
// Verification script to check if shift column exists
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shift Column Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f3f4f6; }
        pre { background: #1f2937; color: #10b981; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Shift Column Verification</h1>
    
    <?php
    try {
        $db = get_db_connection();
        
        // Check 1: Current Database
        echo '<div class="box">';
        echo '<h2>✓ Check 1: Current Database</h2>';
        $stmt = $db->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch();
        echo '<p><strong>Connected to database:</strong> <span class="success">' . htmlspecialchars($result['db_name']) . '</span></p>';
        echo '</div>';
        
        // Check 2: Table exists
        echo '<div class="box">';
        echo '<h2>✓ Check 2: batch_codes Table</h2>';
        $stmt = $db->query("SHOW TABLES LIKE 'batch_codes'");
        if ($stmt->rowCount() > 0) {
            echo '<p class="success">✓ batch_codes table EXISTS</p>';
        } else {
            echo '<p class="error">✗ batch_codes table DOES NOT EXIST</p>';
            echo '</div></body></html>';
            exit;
        }
        echo '</div>';
        
        // Check 3: All columns in batch_codes
        echo '<div class="box">';
        echo '<h2>✓ Check 3: All Columns in batch_codes</h2>';
        $stmt = $db->query("SHOW COLUMNS FROM batch_codes");
        $columns = $stmt->fetchAll();
        
        echo '<table>';
        echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>';
        
        $shiftExists = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'shift') {
                $shiftExists = true;
                echo '<tr style="background: #d1fae5;">';
            } else {
                echo '<tr>';
            }
            echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
            echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Check 4: Shift column status
        echo '<div class="box">';
        echo '<h2>✓ Check 4: Shift Column Status</h2>';
        if ($shiftExists) {
            echo '<p class="success">✓✓✓ SHIFT COLUMN EXISTS! ✓✓✓</p>';
            echo '<p>The column is in the database. The error might be a cache issue.</p>';
        } else {
            echo '<p class="error">✗✗✗ SHIFT COLUMN MISSING! ✗✗✗</p>';
            echo '<p>You need to run the SQL to add the column.</p>';
            echo '<h3>Run this SQL in phpMyAdmin:</h3>';
            echo '<pre>USE ' . htmlspecialchars($result['db_name']) . ';
ALTER TABLE batch_codes 
ADD COLUMN shift VARCHAR(1) DEFAULT NULL 
COMMENT \'Production shift: M=Morning, A=Afternoon, E=Evening, N=Night\';</pre>';
        }
        echo '</div>';
        
        // Check 5: Test INSERT query
        if ($shiftExists) {
            echo '<div class="box">';
            echo '<h2>✓ Check 5: Test Query</h2>';
            try {
                $testStmt = $db->prepare("SELECT shift FROM batch_codes LIMIT 1");
                $testStmt->execute();
                echo '<p class="success">✓ SELECT query works - column is accessible</p>';
            } catch (Exception $e) {
                echo '<p class="error">✗ SELECT query failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            echo '</div>';
        }
        
        // Final recommendation
        echo '<div class="box">';
        echo '<h2>🎯 Recommendation</h2>';
        if ($shiftExists) {
            echo '<p class="success"><strong>Column exists!</strong> Try these steps:</p>';
            echo '<ol>';
            echo '<li>Restart Apache in XAMPP Control Panel</li>';
            echo '<li>Clear browser cache (Ctrl+Shift+Delete)</li>';
            echo '<li>Try the form again in incognito/private window</li>';
            echo '</ol>';
        } else {
            echo '<p class="error"><strong>Column missing!</strong> You MUST run the SQL above.</p>';
        }
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="box">';
        echo '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>
    
    <div class="box">
        <p><a href="manage_batches.php">← Back to Batch Management</a></p>
    </div>
</body>
</html>
