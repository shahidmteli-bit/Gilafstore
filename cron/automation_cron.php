<?php
/**
 * Cron Job for Automation Tasks
 * Add to crontab: */15 * * * * php /path/to/automation_cron.php
 * Runs every 15 minutes
 */

require_once __DIR__ . '/../includes/automation_tasks.php';

// Run automation tasks
$results = AutomationTasks::runAll();

// Log results
$logFile = __DIR__ . '/../logs/cron_automation.log';
$logEntry = sprintf(
    "[%s] Automation completed - Cache: %s, Promo: %s, Currency: %s, Health: %s\n",
    date('Y-m-d H:i:s'),
    $results['cache_cleanup']['success'] ? 'OK' : 'FAIL',
    $results['promo_cache_refresh']['success'] ? 'OK' : 'FAIL',
    $results['currency_cache_refresh']['success'] ? 'OK' : 'FAIL',
    $results['health_check']['success'] ? 'OK' : 'FAIL'
);

file_put_contents($logFile, $logEntry, FILE_APPEND);

echo "Automation tasks completed successfully\n";
