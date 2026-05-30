<?php
/**
 * Security Maintenance Cron Job
 * ==============================
 * Run this periodically (every 15-30 minutes) via cron or Task Scheduler.
 * 
 * Cron example: */15 * * * * php /path/to/cron/security_maintenance.php
 * 
 * Tasks:
 * - Clean up expired rate limit records
 * - Check file integrity of critical files
 * - Check for database anomalies
 * - Clean up expired password reset tokens
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    http_response_code(403);
    die('Access denied');
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/security.php';

$startTime = microtime(true);
$log = [];

// 1. Clean up expired rate limit records
try {
    rate_limit_cleanup();
    $log[] = "[OK] Rate limit cleanup completed";
} catch (Exception $e) {
    $log[] = "[ERROR] Rate limit cleanup: " . $e->getMessage();
}

// 2. File integrity check on critical files
$criticalFiles = [
    __DIR__ . '/../includes/auth.php',
    __DIR__ . '/../includes/db_connect.php',
    __DIR__ . '/../includes/functions.php',
    __DIR__ . '/../includes/security.php',
    __DIR__ . '/../.htaccess',
    __DIR__ . '/../admin/admin_login.php',
    __DIR__ . '/../admin/batch_actions.php',
    __DIR__ . '/../admin/batch_actions_lifecycle.php',
    __DIR__ . '/../checkout.php',
    __DIR__ . '/../razorpay_verify.php',
    __DIR__ . '/../razorpay_webhook.php',
    __DIR__ . '/../razorpay_create_order.php',
    __DIR__ . '/../process_payment.php',
];

try {
    // On first run, establish baseline
    $db = get_db_connection();
    $stmt = $db->query("SELECT COUNT(*) FROM security_file_hashes");
    $count = (int)$stmt->fetchColumn();

    if ($count === 0) {
        file_integrity_baseline($criticalFiles);
        $log[] = "[OK] File integrity baseline established for " . count($criticalFiles) . " files";
    } else {
        $changes = file_integrity_check();
        if (empty($changes)) {
            $log[] = "[OK] File integrity check passed — no changes detected";
        } else {
            $log[] = "[ALERT] File integrity: " . count($changes) . " file(s) changed!";
            foreach ($changes as $c) {
                $log[] = "  - {$c['file']} ({$c['status']})";
            }
            // Re-establish baseline after alerting
            file_integrity_baseline($criticalFiles);
        }
    }
} catch (Exception $e) {
    $log[] = "[ERROR] File integrity check: " . $e->getMessage();
}

// 3. Database anomaly check
try {
    // We need session for tracking counts, start a minimal one
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $anomalies = check_database_anomalies();
    if (empty($anomalies)) {
        $log[] = "[OK] Database anomaly check passed";
    } else {
        $log[] = "[ALERT] Database anomalies detected: " . count($anomalies);
    }
} catch (Exception $e) {
    $log[] = "[ERROR] Database anomaly check: " . $e->getMessage();
}

// 4. Clean up expired password reset tokens
try {
    $db = get_db_connection();
    $stmt = $db->exec("DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1");
    $log[] = "[OK] Expired password reset tokens cleaned up";
} catch (Exception $e) {
    $log[] = "[ERROR] Token cleanup: " . $e->getMessage();
}

// 5. Clean up old security audit logs (keep last 90 days)
try {
    $db = get_db_connection();
    $db->exec("DELETE FROM security_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $log[] = "[OK] Old audit logs cleaned up (90+ days)";
} catch (Exception $e) {
    $log[] = "[ERROR] Audit log cleanup: " . $e->getMessage();
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);
$log[] = "[DONE] Security maintenance completed in {$elapsed}ms";

// Output log
$output = date('Y-m-d H:i:s') . " — Security Maintenance\n" . implode("\n", $log) . "\n\n";

// Write to security log file
$logFile = __DIR__ . '/../logs/security_maintenance.log';
file_put_contents($logFile, $output, FILE_APPEND);

// Output to console if running from CLI
if (php_sapi_name() === 'cli') {
    echo $output;
}
