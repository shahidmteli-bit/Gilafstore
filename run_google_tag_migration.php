<?php
/**
 * Migration script for Google Tag Manager
 * Run this once to create the database table
 */

require_once 'includes/db_connect.php';

echo "<h2>Google Tag Manager Migration</h2>";

try {
    // Read and execute SQL
    $sql = file_get_contents('database_google_tag_manager.sql');
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "<p>Executing: " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";
            
            if ($conn->query($statement)) {
                echo "<p style='color: green;'>✅ Success</p>";
            } else {
                echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($conn->error) . "</p>";
            }
        }
    }
    
    echo "<h3 style='color: green;'>Migration Complete!</h3>";
    echo "<p><a href='admin/google_tag_manager.php'>Go to Google Tag Manager</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='index.php'>Back to Home</a></p>";
?>
