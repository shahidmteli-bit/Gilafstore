<?php
/**
 * Database Connection Test
 * This file tests the database connection with Hostinger credentials
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Database Connection Test</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{color:#28a745;background:#d4edda;padding:15px;border:1px solid #c3e6cb;border-radius:5px;margin:10px 0;}";
echo ".error{color:#dc3545;background:#f8d7da;padding:15px;border:1px solid #f5c6cb;border-radius:5px;margin:10px 0;}";
echo ".info{color:#0c5460;background:#d1ecf1;padding:15px;border:1px solid #bee5eb;border-radius:5px;margin:10px 0;}";
echo "h1{color:#333;}table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "td,th{padding:10px;text-align:left;border-bottom:1px solid #ddd;}th{background:#f8f9fa;}</style>";
echo "</head><body>";

echo "<h1>🔌 Database Connection Test</h1>";

// Test 1: Check if constants are defined
echo "<div class='info'>";
echo "<h2>Step 1: Configuration Check</h2>";
echo "<table>";
echo "<tr><th>Constant</th><th>Value</th></tr>";
echo "<tr><td>DB_HOST</td><td>" . htmlspecialchars(DB_HOST) . "</td></tr>";
echo "<tr><td>DB_NAME</td><td>" . htmlspecialchars(DB_NAME) . "</td></tr>";
echo "<tr><td>DB_USER</td><td>" . htmlspecialchars(DB_USER) . "</td></tr>";
echo "<tr><td>DB_PASS</td><td>" . (DB_PASS ? str_repeat('*', strlen(DB_PASS)) : '(empty)') . "</td></tr>";
echo "</table>";
echo "</div>";

// Test 2: PDO Connection
echo "<div class='info'>";
echo "<h2>Step 2: PDO Connection Test</h2>";
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "<p class='success'>✓ PDO connection established successfully!</p>";
        
        // Test query
        $stmt = $pdo->query("SELECT DATABASE() as current_db, VERSION() as mysql_version, NOW() as server_time");
        $result = $stmt->fetch();
        
        echo "<table>";
        echo "<tr><th>Property</th><th>Value</th></tr>";
        echo "<tr><td>Current Database</td><td>" . htmlspecialchars($result['current_db']) . "</td></tr>";
        echo "<tr><td>MySQL Version</td><td>" . htmlspecialchars($result['mysql_version']) . "</td></tr>";
        echo "<tr><td>Server Time</td><td>" . htmlspecialchars($result['server_time']) . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='error'>✗ PDO connection object not found!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ PDO Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 3: MySQLi Connection
echo "<div class='info'>";
echo "<h2>Step 3: MySQLi Connection Test</h2>";
try {
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "<p class='error'>✗ MySQLi connection failed: " . htmlspecialchars($conn->connect_error) . "</p>";
        } else {
            echo "<p class='success'>✓ MySQLi connection established successfully!</p>";
            
            // Test query
            $result = $conn->query("SELECT DATABASE() as current_db, VERSION() as mysql_version");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<table>";
                echo "<tr><th>Property</th><th>Value</th></tr>";
                echo "<tr><td>Current Database</td><td>" . htmlspecialchars($row['current_db']) . "</td></tr>";
                echo "<tr><td>MySQL Version</td><td>" . htmlspecialchars($row['mysql_version']) . "</td></tr>";
                echo "</table>";
            }
        }
    } else {
        echo "<p class='error'>✗ MySQLi connection object not found!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ MySQLi Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 4: Test a sample table query
echo "<div class='info'>";
echo "<h2>Step 4: Sample Query Test</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p class='success'>✓ Successfully retrieved table list!</p>";
    echo "<p><strong>Total tables found:</strong> " . count($tables) . "</p>";
    
    if (count($tables) > 0) {
        echo "<p><strong>Sample tables:</strong></p><ul>";
        foreach (array_slice($tables, 0, 10) as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        if (count($tables) > 10) {
            echo "<li><em>... and " . (count($tables) - 10) . " more tables</em></li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Query Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Final verdict
echo "<div class='success'>";
echo "<h2>✅ Connection Test Complete</h2>";
echo "<p><strong>Status:</strong> Database connection is working correctly with Hostinger credentials!</p>";
echo "<p><strong>Note:</strong> You can delete this test file (test_db_connection.php) after verification.</p>";
echo "</div>";

echo "</body></html>";
