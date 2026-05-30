<?php
/**
 * CRM Background Worker
 * Processes event queue, cart recovery reminders, and customer sync.
 * 
 * Run via cron every minute:
 *   * * * * * php /path/to/cron/crm_worker.php
 * 
 * Or via Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\Gilaf Ecommerce website\cron\crm_worker.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRM_WORKER_ALLOWED')) {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/crm_engine.php';

$crm = CRMEngine::getInstance();

if (!$crm->isEnabled()) {
    echo "[CRM Worker] Integration disabled. Exiting.\n";
    exit(0);
}

$startTime = microtime(true);
echo "[CRM Worker] Started at " . date('Y-m-d H:i:s') . "\n";

// 1. Process event queue
$processed = $crm->processQueue(50);
echo "[CRM Worker] Processed $processed queued events.\n";

// 2. Process cart recovery reminders
$reminders = $crm->processCartRecovery();
echo "[CRM Worker] Sent $reminders cart recovery reminders.\n";

// 3. Auto-sync customers that need syncing
if ($crm->getSetting('customer_sync_enabled')) {
    $pendingSync = db_fetch_all(
        "SELECT local_user_id FROM crm_customer_sync WHERE sync_status = 'pending' LIMIT 20"
    );
    $synced = 0;
    foreach ($pendingSync as $row) {
        $result = $crm->syncCustomer((int)$row['local_user_id']);
        if ($result['success']) $synced++;
    }
    echo "[CRM Worker] Synced $synced customers.\n";
}

// 4. Expire old OTPs
try {
    $pdo->exec("UPDATE crm_whatsapp_otp SET status = 'expired' WHERE status IN ('pending','sent') AND expires_at < NOW()");
    echo "[CRM Worker] Expired old OTPs.\n";
} catch (\PDOException $e) {
    echo "[CRM Worker] OTP cleanup error: " . $e->getMessage() . "\n";
}

// 5. Clean old rate limit records (older than 2 hours)
try {
    $pdo->exec("DELETE FROM crm_otp_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
} catch (\PDOException $e) {
    // Ignore
}

// 6. Expire old abandoned carts (older than 7 days)
try {
    $pdo->exec("UPDATE crm_abandoned_carts SET recovery_status = 'expired' WHERE recovery_status IN ('active','reminded') AND expires_at < NOW()");
} catch (\PDOException $e) {
    // Ignore
}

$duration = round((microtime(true) - $startTime) * 1000);
echo "[CRM Worker] Completed in {$duration}ms.\n";
