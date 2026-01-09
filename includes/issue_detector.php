<?php
/**
 * Issue Detection Engine
 * Automatically detects problems and provides fix actions
 */

require_once __DIR__ . '/health_monitor.php';
require_once __DIR__ . '/cache_manager.php';

class IssueDetector {
    
    /**
     * Detect all issues across the system
     */
    public static function detectAllIssues() {
        $issues = [];
        
        $issues = array_merge($issues, self::detectCacheIssues());
        $issues = array_merge($issues, self::detectPerformanceIssues());
        $issues = array_merge($issues, self::detectServerIssues());
        $issues = array_merge($issues, self::detectDatabaseIssues());
        $issues = array_merge($issues, self::detectApplicationIssues());
        
        // Sort by severity
        usort($issues, function($a, $b) {
            $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return $severityOrder[$a['severity']] - $severityOrder[$b['severity']];
        });
        
        return $issues;
    }
    
    /**
     * Detect cache-related issues
     */
    private static function detectCacheIssues() {
        $issues = [];
        $cacheHealth = HealthMonitor::getCacheHealth();
        
        // High cache usage
        if ($cacheHealth['total_size'] > 500 * 1024 * 1024) { // 500MB
            $issues[] = [
                'id' => 'high_cache_usage',
                'title' => 'High Cache Usage Detected',
                'severity' => 'high',
                'description' => 'Cache size exceeds safe limit (' . $cacheHealth['total_size_formatted'] . '). This may slow down the website.',
                'fix_action' => 'clear_expired_cache',
                'fix_label' => 'Clear Expired Cache'
            ];
        }
        
        // Low cache hit ratio
        if ($cacheHealth['hit_ratio'] < 60 && $cacheHealth['hits'] + $cacheHealth['misses'] > 100) {
            $issues[] = [
                'id' => 'low_cache_hit_ratio',
                'title' => 'Low Cache Hit Ratio',
                'severity' => 'medium',
                'description' => 'Cache hit ratio is only ' . $cacheHealth['hit_ratio'] . '%. This indicates inefficient caching.',
                'fix_action' => 'rebuild_cache',
                'fix_label' => 'Rebuild Cache'
            ];
        }
        
        // Too many cache files
        if ($cacheHealth['total_files'] > 10000) {
            $issues[] = [
                'id' => 'too_many_cache_files',
                'title' => 'Excessive Cache Files',
                'severity' => 'medium',
                'description' => 'Found ' . number_format($cacheHealth['total_files']) . ' cache files. This may impact performance.',
                'fix_action' => 'clear_expired_cache',
                'fix_label' => 'Clean Up Cache'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Detect performance issues
     */
    private static function detectPerformanceIssues() {
        $issues = [];
        $performance = HealthMonitor::getPerformanceMetrics();
        
        // Slow average page load time
        if ($performance['avg_load_time'] > 3) {
            $issues[] = [
                'id' => 'slow_page_load',
                'title' => 'Slow Page Load Time',
                'severity' => 'high',
                'description' => 'Average page load time is ' . $performance['avg_load_time'] . 's. Target is under 2s.',
                'fix_action' => 'optimize_performance',
                'fix_label' => 'Optimize Performance'
            ];
        }
        
        // High TTFB
        if ($performance['ttfb'] > 500) {
            $issues[] = [
                'id' => 'high_ttfb',
                'title' => 'High Time to First Byte',
                'severity' => 'medium',
                'description' => 'TTFB is ' . $performance['ttfb'] . 'ms. Target is under 200ms.',
                'fix_action' => 'optimize_server',
                'fix_label' => 'Optimize Server'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Detect server issues
     */
    private static function detectServerIssues() {
        $issues = [];
        $server = HealthMonitor::getServerHealth();
        
        // High memory usage
        if ($server['memory_usage_percent'] > 90) {
            $issues[] = [
                'id' => 'high_memory_usage',
                'title' => 'Critical Memory Usage',
                'severity' => 'critical',
                'description' => 'Memory usage is at ' . $server['memory_usage_percent'] . '%. Server may crash soon.',
                'fix_action' => 'clear_memory',
                'fix_label' => 'Clear Memory Cache'
            ];
        } elseif ($server['memory_usage_percent'] > 75) {
            $issues[] = [
                'id' => 'high_memory_usage',
                'title' => 'High Memory Usage',
                'severity' => 'high',
                'description' => 'Memory usage is at ' . $server['memory_usage_percent'] . '%. Consider clearing cache.',
                'fix_action' => 'clear_memory',
                'fix_label' => 'Clear Memory Cache'
            ];
        }
        
        // High disk usage
        if ($server['disk_usage_percent'] > 90) {
            $issues[] = [
                'id' => 'high_disk_usage',
                'title' => 'Critical Disk Usage',
                'severity' => 'critical',
                'description' => 'Disk usage is at ' . $server['disk_usage_percent'] . '%. Free up space immediately.',
                'fix_action' => 'clear_logs_cache',
                'fix_label' => 'Clear Logs & Cache'
            ];
        } elseif ($server['disk_usage_percent'] > 80) {
            $issues[] = [
                'id' => 'high_disk_usage',
                'title' => 'High Disk Usage',
                'severity' => 'high',
                'description' => 'Disk usage is at ' . $server['disk_usage_percent'] . '%. Consider cleanup.',
                'fix_action' => 'clear_logs_cache',
                'fix_label' => 'Clear Old Files'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Detect database issues
     */
    private static function detectDatabaseIssues() {
        $issues = [];
        $database = HealthMonitor::getDatabaseHealth();
        
        if ($database['status'] === 'disconnected') {
            $issues[] = [
                'id' => 'database_disconnected',
                'title' => 'Database Connection Lost',
                'severity' => 'critical',
                'description' => 'Cannot connect to database. Website may be down.',
                'fix_action' => 'reconnect_database',
                'fix_label' => 'Reconnect Database'
            ];
            return $issues;
        }
        
        // Too many slow queries
        if ($database['slow_queries'] > 100) {
            $issues[] = [
                'id' => 'slow_queries',
                'title' => 'High Slow Query Count',
                'severity' => 'high',
                'description' => 'Found ' . $database['slow_queries'] . ' slow queries. This impacts performance.',
                'fix_action' => 'optimize_database',
                'fix_label' => 'Optimize Database'
            ];
        }
        
        // Too many active connections
        if ($database['active_connections'] > 100) {
            $issues[] = [
                'id' => 'high_db_connections',
                'title' => 'High Database Connections',
                'severity' => 'medium',
                'description' => 'Found ' . $database['active_connections'] . ' active connections. May cause bottlenecks.',
                'fix_action' => 'clear_db_cache',
                'fix_label' => 'Clear DB Cache'
            ];
        }
        
        // Large database size
        if ($database['database_size'] > 1024 * 1024 * 1024) { // 1GB
            $issues[] = [
                'id' => 'large_database',
                'title' => 'Large Database Size',
                'severity' => 'low',
                'description' => 'Database size is ' . $database['database_size_formatted'] . '. Consider archiving old data.',
                'fix_action' => 'archive_old_data',
                'fix_label' => 'Archive Old Data'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Detect application issues
     */
    private static function detectApplicationIssues() {
        $issues = [];
        $app = HealthMonitor::getApplicationHealth();
        
        // High error rate
        if ($app['error_rate'] > 5) {
            $issues[] = [
                'id' => 'high_error_rate',
                'title' => 'Critical Error Rate',
                'severity' => 'critical',
                'description' => 'Error rate is ' . $app['error_rate'] . '%. Multiple systems may be failing.',
                'fix_action' => 'emergency_fix',
                'fix_label' => 'Emergency Fix'
            ];
        } elseif ($app['error_rate'] > 2) {
            $issues[] = [
                'id' => 'high_error_rate',
                'title' => 'High Error Rate',
                'severity' => 'high',
                'description' => 'Error rate is ' . $app['error_rate'] . '%. Some features may be broken.',
                'fix_action' => 'clear_error_cache',
                'fix_label' => 'Clear Error Cache'
            ];
        }
        
        // Failed API calls
        if ($app['failed_api_calls'] > 50) {
            $issues[] = [
                'id' => 'failed_api_calls',
                'title' => 'High API Failure Rate',
                'severity' => 'high',
                'description' => 'Found ' . $app['failed_api_calls'] . ' failed API calls in the last hour.',
                'fix_action' => 'clear_api_cache',
                'fix_label' => 'Clear API Cache'
            ];
        }
        
        // Failed checkouts
        if ($app['failed_checkouts'] > 10) {
            $issues[] = [
                'id' => 'failed_checkouts',
                'title' => 'High Checkout Failure Rate',
                'severity' => 'critical',
                'description' => 'Found ' . $app['failed_checkouts'] . ' failed checkouts. Revenue is being lost!',
                'fix_action' => 'fix_checkout',
                'fix_label' => 'Fix Checkout System'
            ];
        }
        
        // Promo validation failures
        if ($app['promo_validation_failures'] > 20) {
            $issues[] = [
                'id' => 'promo_failures',
                'title' => 'Promo Code Validation Issues',
                'severity' => 'medium',
                'description' => 'Found ' . $app['promo_validation_failures'] . ' promo validation failures.',
                'fix_action' => 'clear_promo_cache',
                'fix_label' => 'Clear Promo Cache'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Execute fix action for an issue
     */
    public static function executeFix($fixAction, $adminId = null) {
        $result = ['success' => false, 'message' => 'Unknown fix action'];
        
        switch ($fixAction) {
            case 'clear_expired_cache':
                $cleared = CacheManager::clearExpiredCache();
                $result = [
                    'success' => true,
                    'message' => "Cleared $cleared expired cache files",
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'rebuild_cache':
                CacheManager::clearAllCache($adminId);
                $result = [
                    'success' => true,
                    'message' => 'Cache rebuilt successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'clear_memory':
                CacheManager::clearFrontendCache($adminId);
                CacheManager::clearAPICache($adminId);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                $result = [
                    'success' => true,
                    'message' => 'Memory cache cleared',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'clear_logs_cache':
                $cleared = CacheManager::clearExpiredCache();
                self::clearOldLogs();
                $result = [
                    'success' => true,
                    'message' => "Cleared $cleared files and old logs",
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'optimize_database':
                self::optimizeDatabase();
                $result = [
                    'success' => true,
                    'message' => 'Database optimized successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'clear_db_cache':
                try {
                    db_query("RESET QUERY CACHE");
                    $result = [
                        'success' => true,
                        'message' => 'Database cache cleared',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                } catch (Exception $e) {
                    $result = ['success' => false, 'message' => $e->getMessage()];
                }
                break;
                
            case 'clear_api_cache':
                CacheManager::clearAPICache($adminId);
                $result = [
                    'success' => true,
                    'message' => 'API cache cleared',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'clear_promo_cache':
                CacheManager::clearPromoCache($adminId);
                $result = [
                    'success' => true,
                    'message' => 'Promo cache cleared',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'clear_error_cache':
                CacheManager::clearFrontendCache($adminId);
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
                $result = [
                    'success' => true,
                    'message' => 'Error cache cleared and OPcache reset',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'optimize_performance':
                CacheManager::clearExpiredCache();
                self::optimizeDatabase();
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
                $result = [
                    'success' => true,
                    'message' => 'Performance optimization completed',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'emergency_fix':
                CacheManager::clearAllCache($adminId);
                self::optimizeDatabase();
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
                $result = [
                    'success' => true,
                    'message' => 'Emergency fix applied - all caches cleared',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
        }
        
        // Log the fix action
        self::logFixAction($fixAction, $adminId, $result);
        
        return $result;
    }
    
    /**
     * Optimize database tables
     */
    private static function optimizeDatabase() {
        try {
            global $conn;
            
            // Get all tables
            $result = $conn->query("SHOW TABLES");
            $tables = [];
            
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }
            
            // Optimize each table
            foreach ($tables as $table) {
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
    private static function clearOldLogs() {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            return 0;
        }
        
        $cleared = 0;
        $cutoff = time() - (30 * 86400); // 30 days
        
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
     * Log fix action
     */
    private static function logFixAction($action, $adminId, $result) {
        try {
            db_query(
                "INSERT INTO fix_actions_log (action, admin_id, success, message, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$action, $adminId, $result['success'] ? 1 : 0, $result['message']]
            );
        } catch (Exception $e) {
            // Silent fail
        }
    }
}
