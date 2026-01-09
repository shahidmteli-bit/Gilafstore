<?php
/**
 * Automation & Self-Healing Background Tasks
 * Runs periodic maintenance and optimization tasks
 */

require_once __DIR__ . '/cache_manager.php';
require_once __DIR__ . '/health_monitor.php';
require_once __DIR__ . '/issue_detector.php';

class AutomationTasks {
    
    /**
     * Run all automated tasks
     */
    public static function runAll() {
        $results = [
            'cache_cleanup' => self::autoCleanExpiredCache(),
            'promo_cache_refresh' => self::autoRefreshPromoCache(),
            'currency_cache_refresh' => self::autoRefreshCurrencyCache(),
            'health_check' => self::runHealthCheck(),
            'performance_optimization' => self::runPerformanceOptimization()
        ];
        
        self::logAutomationRun($results);
        
        return $results;
    }
    
    /**
     * Auto-clear expired cache (runs hourly)
     */
    public static function autoCleanExpiredCache() {
        try {
            $cleared = CacheManager::clearExpiredCache(86400); // 24 hours
            
            return [
                'success' => true,
                'files_cleared' => $cleared,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Auto-refresh promo cache when rules change
     */
    public static function autoRefreshPromoCache() {
        try {
            // Check if promo codes have been updated recently
            $result = db_query("
                SELECT COUNT(*) as count 
                FROM promo_codes 
                WHERE updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            if ($result && $row = $result->fetch_assoc()) {
                if ($row['count'] > 0) {
                    // Promo codes were updated, clear cache
                    CacheManager::clearPromoCache();
                    
                    return [
                        'success' => true,
                        'action' => 'cache_cleared',
                        'reason' => 'Promo codes updated',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            return [
                'success' => true,
                'action' => 'no_action_needed',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Auto-refresh currency cache (runs daily)
     */
    public static function autoRefreshCurrencyCache() {
        try {
            // Check if exchange rates are older than 24 hours
            $result = db_query("
                SELECT COUNT(*) as count 
                FROM exchange_rates 
                WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            
            if ($result && $row = $result->fetch_assoc()) {
                if ($row['count'] > 0) {
                    // Rates are old, refresh them
                    require_once __DIR__ . '/currency_converter.php';
                    update_all_exchange_rates();
                    
                    return [
                        'success' => true,
                        'action' => 'rates_updated',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            return [
                'success' => true,
                'action' => 'rates_current',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Run daily health check scan
     */
    public static function runHealthCheck() {
        try {
            $issues = IssueDetector::detectAllIssues();
            $criticalIssues = array_filter($issues, function($issue) {
                return $issue['severity'] === 'critical';
            });
            
            // Auto-fix critical issues if safe
            $autoFixed = [];
            foreach ($criticalIssues as $issue) {
                if (self::isSafeToAutoFix($issue['fix_action'])) {
                    $result = IssueDetector::executeFix($issue['fix_action'], null);
                    if ($result['success']) {
                        $autoFixed[] = $issue['id'];
                    }
                }
            }
            
            // Send alert if critical issues remain
            if (count($criticalIssues) > count($autoFixed)) {
                self::sendCriticalAlert($criticalIssues);
            }
            
            return [
                'success' => true,
                'total_issues' => count($issues),
                'critical_issues' => count($criticalIssues),
                'auto_fixed' => count($autoFixed),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Run weekly performance optimization
     */
    public static function runPerformanceOptimization() {
        try {
            // Clear old logs
            self::clearOldLogs(30); // 30 days
            
            // Optimize database tables
            self::optimizeDatabase();
            
            // Clear expired sessions
            db_query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            
            // Clear old analytics data (keep 90 days)
            db_query("DELETE FROM page_views WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            
            return [
                'success' => true,
                'action' => 'optimization_completed',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if fix action is safe to auto-execute
     */
    private static function isSafeToAutoFix($fixAction) {
        $safeActions = [
            'clear_expired_cache',
            'clear_memory',
            'clear_api_cache',
            'clear_promo_cache'
        ];
        
        return in_array($fixAction, $safeActions);
    }
    
    /**
     * Send critical alert to admins
     */
    private static function sendCriticalAlert($issues) {
        try {
            // Store alert in database for admin dashboard
            foreach ($issues as $issue) {
                db_query(
                    "INSERT INTO admin_alerts (type, severity, title, message, created_at) VALUES (?, ?, ?, ?, NOW())",
                    ['health_issue', $issue['severity'], $issue['title'], $issue['description']]
                );
            }
            
            // Future: Send email notification
            
            return true;
        } catch (Exception $e) {
            error_log("Alert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimize database tables
     */
    private static function optimizeDatabase() {
        try {
            global $conn;
            
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch_array()) {
                $table = $row[0];
                $conn->query("OPTIMIZE TABLE `$table`");
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Clear old log files
     */
    private static function clearOldLogs($days = 30) {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            return 0;
        }
        
        $cleared = 0;
        $cutoff = time() - ($days * 86400);
        
        $files = glob($logDir . '*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $cleared++;
                }
            }
        }
        
        return $cleared;
    }
    
    /**
     * Log automation run
     */
    private static function logAutomationRun($results) {
        try {
            db_query(
                "INSERT INTO automation_logs (results, created_at) VALUES (?, NOW())",
                [json_encode($results)]
            );
        } catch (Exception $e) {
            error_log("Automation log error: " . $e->getMessage());
        }
    }
}

// If called directly via cron
if (php_sapi_name() === 'cli') {
    $results = AutomationTasks::runAll();
    echo "Automation tasks completed:\n";
    print_r($results);
}
