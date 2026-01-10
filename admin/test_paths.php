<?php
/**
 * Server Path Diagnostic Tool
 * This file helps diagnose path configuration issues on the live server
 */

session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Check admin authentication
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    die('Unauthorized access');
}

header('Content-Type: text/plain; charset=utf-8');

echo "===========================================\n";
echo "SERVER PATH DIAGNOSTIC REPORT\n";
echo "===========================================\n\n";

echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

echo "-------------------------------------------\n";
echo "1. SERVER VARIABLES\n";
echo "-------------------------------------------\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NOT SET') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n\n";

echo "-------------------------------------------\n";
echo "2. PHP MAGIC CONSTANTS\n";
echo "-------------------------------------------\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "__DIR__: " . __DIR__ . "\n\n";

echo "-------------------------------------------\n";
echo "3. CALCULATED PATHS (Relative)\n";
echo "-------------------------------------------\n";
echo "Cache Dir (relative): " . __DIR__ . '/../cache/' . "\n";
echo "Logs Dir (relative): " . __DIR__ . '/../logs/' . "\n";
echo "Includes Dir (relative): " . __DIR__ . '/../includes/' . "\n";
echo "Assets Dir (relative): " . __DIR__ . '/../assets/' . "\n";
echo "Uploads Dir (relative): " . __DIR__ . '/../uploads/' . "\n\n";

echo "-------------------------------------------\n";
echo "4. RESOLVED PATHS (Real Paths)\n";
echo "-------------------------------------------\n";
$paths = [
    'Cache' => __DIR__ . '/../cache/',
    'Logs' => __DIR__ . '/../logs/',
    'Includes' => __DIR__ . '/../includes/',
    'Assets' => __DIR__ . '/../assets/',
    'Uploads' => __DIR__ . '/../uploads/',
    'Admin' => __DIR__,
    'Root' => __DIR__ . '/..'
];

foreach ($paths as $name => $path) {
    $realPath = realpath($path);
    $exists = $realPath !== false;
    $readable = $exists && is_readable($realPath);
    $writable = $exists && is_writable($realPath);
    
    echo "$name:\n";
    echo "  Relative: $path\n";
    echo "  Real: " . ($realPath ?: 'NOT FOUND') . "\n";
    echo "  Exists: " . ($exists ? 'YES' : 'NO') . "\n";
    echo "  Readable: " . ($readable ? 'YES' : 'NO') . "\n";
    echo "  Writable: " . ($writable ? 'YES' : 'NO') . "\n\n";
}

echo "-------------------------------------------\n";
echo "5. CACHE DIRECTORY CONTENTS\n";
echo "-------------------------------------------\n";
$cacheDir = realpath(__DIR__ . '/../cache/');
if ($cacheDir && is_dir($cacheDir)) {
    echo "Cache directory exists at: $cacheDir\n";
    $subdirs = glob($cacheDir . '/*', GLOB_ONLYDIR);
    if ($subdirs) {
        echo "Subdirectories:\n";
        foreach ($subdirs as $dir) {
            $count = count(glob($dir . '/*'));
            echo "  - " . basename($dir) . " ($count files)\n";
        }
    } else {
        echo "No subdirectories found\n";
    }
} else {
    echo "Cache directory NOT FOUND\n";
}
echo "\n";

echo "-------------------------------------------\n";
echo "6. LOGS DIRECTORY CONTENTS\n";
echo "-------------------------------------------\n";
$logsDir = realpath(__DIR__ . '/../logs/');
if ($logsDir && is_dir($logsDir)) {
    echo "Logs directory exists at: $logsDir\n";
    $logFiles = glob($logsDir . '/*.log');
    if ($logFiles) {
        echo "Log files:\n";
        foreach ($logFiles as $file) {
            $size = filesize($file);
            $modified = date('Y-m-d H:i:s', filemtime($file));
            echo "  - " . basename($file) . " (" . formatBytes($size) . ", modified: $modified)\n";
        }
    } else {
        echo "No log files found\n";
    }
} else {
    echo "Logs directory NOT FOUND\n";
}
echo "\n";

echo "-------------------------------------------\n";
echo "7. DATABASE CONNECTION\n";
echo "-------------------------------------------\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "Connection Status: ";
try {
    global $conn;
    if ($conn && $conn->ping()) {
        echo "CONNECTED\n";
    } else {
        echo "DISCONNECTED\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "-------------------------------------------\n";
echo "8. PHP CONFIGURATION\n";
echo "-------------------------------------------\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "GD Library: " . (extension_loaded('gd') ? 'ENABLED' : 'DISABLED') . "\n";
echo "OPcache: " . (function_exists('opcache_get_status') ? 'ENABLED' : 'DISABLED') . "\n\n";

echo "-------------------------------------------\n";
echo "9. DISK SPACE\n";
echo "-------------------------------------------\n";
$diskTotal = disk_total_space(__DIR__);
$diskFree = disk_free_space(__DIR__);
$diskUsed = $diskTotal - $diskFree;
$diskPercent = round(($diskUsed / $diskTotal) * 100, 2);

echo "Total: " . formatBytes($diskTotal) . "\n";
echo "Used: " . formatBytes($diskUsed) . " ($diskPercent%)\n";
echo "Free: " . formatBytes($diskFree) . "\n\n";

echo "-------------------------------------------\n";
echo "10. HEALTH SYSTEM FILES\n";
echo "-------------------------------------------\n";
$healthFiles = [
    'cache_manager.php' => __DIR__ . '/../includes/cache_manager.php',
    'health_monitor.php' => __DIR__ . '/../includes/health_monitor.php',
    'issue_detector.php' => __DIR__ . '/../includes/issue_detector.php',
    'health_api.php' => __DIR__ . '/api/health_api.php',
    'health_dashboard.php' => __DIR__ . '/health_dashboard.php'
];

foreach ($healthFiles as $name => $path) {
    $exists = file_exists($path);
    $readable = $exists && is_readable($path);
    $size = $exists ? filesize($path) : 0;
    
    echo "$name:\n";
    echo "  Path: $path\n";
    echo "  Exists: " . ($exists ? 'YES' : 'NO') . "\n";
    echo "  Readable: " . ($readable ? 'YES' : 'NO') . "\n";
    echo "  Size: " . ($exists ? formatBytes($size) : 'N/A') . "\n\n";
}

echo "===========================================\n";
echo "END OF DIAGNOSTIC REPORT\n";
echo "===========================================\n";

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
