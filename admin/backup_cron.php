<?php
/**
 * Backup Cron Script — CLI Execution
 * ─────────────────────────────────────────────────────────
 * Runs backups in background (spawned from web UI or Task Scheduler).
 *
 * Usage:
 *   php backup_cron.php --type=full --name="Weekly Full" --admin="System"
 *   php backup_cron.php --type=incremental --name="Daily Auto" --admin="System"
 *   php backup_cron.php --type=cleanup --admin="System"
 *
 * Windows Task Scheduler:
 *   Daily 2:00 AM  → --type=incremental
 *   Sunday 3:00 AM → --type=full
 *   Daily 4:00 AM  → --type=cleanup
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse arguments
$args = [];
foreach ($argv as $arg) {
    if (preg_match('/^--(\w+)=(.*)$/', $arg, $m)) {
        $args[$m[1]] = trim($m[2], '"\'');
    }
}

$type  = $args['type']  ?? 'incremental';
$name  = $args['name']  ?? ucfirst($type) . ' Backup ' . date('M j, Y');
$desc  = $args['desc']  ?? 'Automated ' . $type . ' backup via scheduler';
$admin = $args['admin'] ?? 'System';

// Bootstrap
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/backup_engine.php';

$engine = new BackupEngine($pdo);

echo "[" . date('Y-m-d H:i:s') . "] Starting {$type} backup: {$name}\n";

$start = microtime(true);

switch ($type) {
    case 'full':
        $result = $engine->createFullBackup($name, $desc, $admin);
        break;

    case 'incremental':
        $result = $engine->createIncrementalBackup($name, $desc, $admin);
        break;

    case 'cleanup':
        $result = $engine->autoCleanup();
        echo "[" . date('Y-m-d H:i:s') . "] Cleanup done: {$result['archived']} archived, {$result['freed_mb']} MB freed\n";
        exit(0);

    default:
        echo "Unknown type: {$type}\n";
        echo "Valid types: full, incremental, cleanup\n";
        exit(1);
}

$elapsed = round(microtime(true) - $start, 2);

if ($result['success'] ?? false) {
    echo "[" . date('Y-m-d H:i:s') . "] SUCCESS — ID: {$result['backup_id']}\n";
    if (isset($result['files'])) echo "  Files: {$result['files']}\n";
    if (isset($result['size_compressed'])) echo "  Compressed: " . round($result['size_compressed']/1048576, 2) . " MB\n";
    if (isset($result['added'])) echo "  Added: {$result['added']}, Modified: {$result['modified']}, Deleted: {$result['deleted']}\n";
    echo "  Time: {$elapsed}s\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] FAILED — " . ($result['error'] ?? 'Unknown error') . "\n";
    exit(1);
}

exit(0);
