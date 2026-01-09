<?php
/**
 * View PHP Error Log for Password Reset Debugging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>📋 PHP Error Log Viewer</h1>";
echo "<hr>";

// Possible error log locations
$logLocations = [
    'C:\xampp\php\logs\php_error_log',
    'C:\xampp\apache\logs\error.log',
    'C:\xampp\apache\logs\php_error_log',
    ini_get('error_log')
];

echo "<h2>Checking Error Log Locations:</h2>";
echo "<ul>";
foreach ($logLocations as $location) {
    if ($location && file_exists($location)) {
        echo "<li style='color: green;'>✅ Found: " . htmlspecialchars($location) . "</li>";
    } else {
        echo "<li style='color: gray;'>❌ Not found: " . htmlspecialchars($location) . "</li>";
    }
}
echo "</ul>";

// Find the first existing log file
$logFile = null;
foreach ($logLocations as $location) {
    if ($location && file_exists($location)) {
        $logFile = $location;
        break;
    }
}

if (!$logFile) {
    echo "<p style='color: red;'>❌ No error log file found!</p>";
    echo "<p>Current error_log setting: " . htmlspecialchars(ini_get('error_log')) . "</p>";
    exit;
}

echo "<hr>";
echo "<h2>Reading Error Log: " . htmlspecialchars($logFile) . "</h2>";

// Read last 100 lines
$lines = [];
$handle = fopen($logFile, 'r');
if ($handle) {
    // Get file size
    fseek($handle, 0, SEEK_END);
    $fileSize = ftell($handle);
    
    // Read from end
    $bufferSize = min($fileSize, 50000); // Read last 50KB
    fseek($handle, -$bufferSize, SEEK_END);
    $content = fread($handle, $bufferSize);
    fclose($handle);
    
    $lines = explode("\n", $content);
    $lines = array_filter($lines); // Remove empty lines
    $lines = array_slice($lines, -100); // Last 100 lines
}

// Filter for password reset related logs
$resetLogs = [];
foreach ($lines as $line) {
    if (stripos($line, 'RESET PASSWORD') !== false || 
        stripos($line, 'password_resets') !== false ||
        stripos($line, 'reset_password.php') !== false) {
        $resetLogs[] = $line;
    }
}

if (empty($resetLogs)) {
    echo "<p style='color: orange;'>⚠️ No password reset logs found in last 100 lines</p>";
    echo "<p>Showing last 20 lines of error log:</p>";
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;'>";
    foreach (array_slice($lines, -20) as $line) {
        echo htmlspecialchars($line) . "<br>";
    }
    echo "</div>";
} else {
    echo "<p style='color: green;'>✅ Found " . count($resetLogs) . " password reset related log entries</p>";
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 600px; overflow-y: auto;'>";
    foreach ($resetLogs as $log) {
        // Highlight different log types
        if (stripos($log, 'FAILED') !== false || stripos($log, 'ERROR') !== false) {
            echo "<div style='color: red; margin: 5px 0;'>" . htmlspecialchars($log) . "</div>";
        } elseif (stripos($log, 'SUCCESS') !== false) {
            echo "<div style='color: green; margin: 5px 0;'>" . htmlspecialchars($log) . "</div>";
        } else {
            echo "<div style='margin: 5px 0;'>" . htmlspecialchars($log) . "</div>";
        }
    }
    echo "</div>";
}

echo "<hr>";
echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Request a new password reset from the forgot password page</li>";
echo "<li>Click the reset link in your email</li>";
echo "<li>Refresh this page to see the debug logs</li>";
echo "<li>The logs will show exactly why the token is failing</li>";
echo "</ol>";

echo "<p><a href='?' style='background: #1A3C34; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Refresh Logs</a></p>";
?>
