<?php
/**
 * Health & Cache Management API
 * Handles all health monitoring and cache management requests
 */

session_start();
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/health_monitor.php';
require_once __DIR__ . '/../../includes/issue_detector.php';
require_once __DIR__ . '/../../includes/cache_manager.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$adminId = $_SESSION['user']['id'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, we'll catch them

try {
    switch ($action) {
        case 'get_health_metrics':
            try {
                $metrics = HealthMonitor::getHealthMetrics();
                $score = HealthMonitor::calculateEfficiencyScore();
                
                echo json_encode([
                    'success' => true,
                    'metrics' => $metrics,
                    'efficiency_score' => $score
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error getting health metrics: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'get_issues':
            try {
                $issues = IssueDetector::detectAllIssues();
                
                echo json_encode([
                    'success' => true,
                    'issues' => $issues,
                    'count' => count($issues)
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error detecting issues: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'execute_fix':
            $fixAction = $_POST['fix_action'] ?? '';
            
            if (empty($fixAction)) {
                throw new Exception('Fix action is required');
            }
            
            // Rate limiting check
            if (!checkRateLimit($adminId, 'fix_action')) {
                throw new Exception('Too many fix actions. Please wait before trying again.');
            }
            
            $result = IssueDetector::executeFix($fixAction, $adminId);
            
            echo json_encode($result);
            break;
            
        case 'clear_cache':
            $cacheType = $_POST['cache_type'] ?? 'all';
            
            // Rate limiting
            if (!checkRateLimit($adminId, 'clear_cache')) {
                throw new Exception('Too many cache clear requests. Please wait.');
            }
            
            switch ($cacheType) {
                case 'all':
                    $cleared = CacheManager::clearAllCache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                case 'frontend':
                    $cleared = CacheManager::clearFrontendCache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                case 'admin':
                    $cleared = CacheManager::clearAdminCache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                case 'api':
                    $cleared = CacheManager::clearAPICache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                case 'promo':
                    $cleared = CacheManager::clearPromoCache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                case 'language':
                    $cleared = CacheManager::clearLanguageCache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                case 'currency':
                    $cleared = CacheManager::clearCurrencyCache($adminId);
                    $result = ['success' => true, 'files_cleared' => $cleared, 'timestamp' => date('Y-m-d H:i:s')];
                    break;
                default:
                    throw new Exception('Invalid cache type');
            }
            
            echo json_encode($result);
            break;
            
        case 'get_cache_stats':
            try {
                $stats = CacheManager::getCacheStats();
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error getting cache stats: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'get_cache_logs':
            $limit = (int)($_GET['limit'] ?? 50);
            $logs = CacheManager::getRecentLogs($limit);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;
            
        case 'get_performance_metrics':
            $performance = HealthMonitor::getPerformanceMetrics();
            
            echo json_encode([
                'success' => true,
                'metrics' => $performance
            ]);
            break;
            
        case 'get_server_health':
            $server = HealthMonitor::getServerHealth();
            
            echo json_encode([
                'success' => true,
                'health' => $server
            ]);
            break;
            
        case 'get_database_health':
            $database = HealthMonitor::getDatabaseHealth();
            
            echo json_encode([
                'success' => true,
                'health' => $database
            ]);
            break;
            
        case 'get_application_health':
            $application = HealthMonitor::getApplicationHealth();
            
            echo json_encode([
                'success' => true,
                'health' => $application
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Rate limiting for admin actions
 */
function checkRateLimit($adminId, $actionType) {
    $key = "rate_limit_{$actionType}_{$adminId}";
    $limit = 10; // Max 10 actions per minute
    $window = 60; // 1 minute
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if window expired
    if (time() > $data['reset_time']) {
        $_SESSION[$key] = ['count' => 1, 'reset_time' => time() + $window];
        return true;
    }
    
    // Check limit
    if ($data['count'] >= $limit) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}
