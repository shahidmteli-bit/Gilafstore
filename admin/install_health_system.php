<?php
/**
 * Health System Installation Script
 * Run this once to create all required database tables
 */

session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Check admin authentication
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    die('Unauthorized access');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Health System - Gilaf Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f5f7fa; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #1A3C34; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1rem; }
        .btn-primary { background: #C5A059; color: white; }
        .btn-primary:hover { background: #b08f4a; }
        .btn-secondary { background: #6c757d; color: white; margin-left: 10px; }
        .status { padding: 15px; border-radius: 6px; margin: 10px 0; }
        .status-success { background: #d1fae5; color: #16a34a; border: 1px solid #16a34a; }
        .status-error { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }
        .status-info { background: #dbeafe; color: #2563eb; border: 1px solid #2563eb; }
        .progress { margin: 20px 0; }
        .progress-item { padding: 10px; margin: 5px 0; border-left: 3px solid #ddd; padding-left: 15px; }
        .progress-item.done { border-color: #16a34a; color: #16a34a; }
        .progress-item.error { border-color: #dc2626; color: #dc2626; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-heartbeat"></i> Health System Installation</h1>
            <p class="subtitle">This will create all required database tables for the Website Health & Cache Management System.</p>
            
            <div id="status"></div>
            <div id="progress"></div>
            
            <button class="btn btn-primary" onclick="installSystem()">
                <i class="fas fa-download"></i> Install Health System
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='health_dashboard.php'">
                <i class="fas fa-arrow-right"></i> Go to Dashboard
            </button>
        </div>
    </div>

    <script>
        async function installSystem() {
            const statusDiv = document.getElementById('status');
            const progressDiv = document.getElementById('progress');
            
            statusDiv.innerHTML = '<div class="status status-info"><i class="fas fa-spinner fa-spin"></i> Installing health system tables...</div>';
            progressDiv.innerHTML = '';
            
            try {
                const response = await fetch('install_health_system.php?action=install', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.innerHTML = '<div class="status status-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    
                    let progressHtml = '<div class="progress">';
                    data.tables.forEach(table => {
                        const icon = table.success ? 'check' : 'times';
                        const className = table.success ? 'done' : 'error';
                        progressHtml += `<div class="progress-item ${className}"><i class="fas fa-${icon}"></i> ${table.name}: ${table.message}</div>`;
                    });
                    progressHtml += '</div>';
                    
                    progressDiv.innerHTML = progressHtml;
                    
                    setTimeout(() => {
                        window.location.href = 'health_dashboard.php';
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<div class="status status-error"><i class="fas fa-exclamation-circle"></i> ' + data.error + '</div>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<div class="status status-error"><i class="fas fa-exclamation-circle"></i> Installation failed: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>

<?php
// Handle installation request
if (isset($_GET['action']) && $_GET['action'] === 'install') {
    header('Content-Type: application/json');
    
    global $conn;
    $results = [];
    
    // SQL for creating tables
    $tables = [
        'cache_logs' => "CREATE TABLE IF NOT EXISTS cache_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            admin_id INT DEFAULT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'cache_stats' => "CREATE TABLE IF NOT EXISTS cache_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(255) NOT NULL,
            hit TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_cache_key (cache_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'error_logs' => "CREATE TABLE IF NOT EXISTS error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            error_type VARCHAR(100),
            error_message TEXT,
            file_path VARCHAR(255),
            line_number INT,
            user_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_error_type (error_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'api_logs' => "CREATE TABLE IF NOT EXISTS api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(255),
            method VARCHAR(10),
            status VARCHAR(20),
            response_time FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'query_logs' => "CREATE TABLE IF NOT EXISTS query_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            query_text TEXT,
            execution_time FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_execution_time (execution_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255),
            success TINYINT(1) DEFAULT 0,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'promo_usage_logs' => "CREATE TABLE IF NOT EXISTS promo_usage_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            promo_code VARCHAR(50),
            user_id INT,
            status VARCHAR(20),
            order_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_status (status),
            INDEX idx_promo_code (promo_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'fix_actions_log' => "CREATE TABLE IF NOT EXISTS fix_actions_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            admin_id INT DEFAULT NULL,
            success TINYINT(1) DEFAULT 1,
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'user_cache_logs' => "CREATE TABLE IF NOT EXISTS user_cache_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            user_id INT DEFAULT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'admin_alerts' => "CREATE TABLE IF NOT EXISTS admin_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50),
            severity VARCHAR(20),
            title VARCHAR(255),
            message TEXT,
            read_status TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_read_status (read_status),
            INDEX idx_severity (severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'automation_logs' => "CREATE TABLE IF NOT EXISTS automation_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            results JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'user_sessions' => "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            session_id VARCHAR(255),
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'user_cache' => "CREATE TABLE IF NOT EXISTS user_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            cache_key VARCHAR(255),
            cache_value TEXT,
            expires_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at),
            INDEX idx_cache_key (cache_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    // Create each table
    foreach ($tables as $tableName => $sql) {
        try {
            $conn->query($sql);
            $results[] = [
                'name' => $tableName,
                'success' => true,
                'message' => 'Created successfully'
            ];
        } catch (Exception $e) {
            $results[] = [
                'name' => $tableName,
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Create cache directories
    $cacheDir = __DIR__ . '/../cache/';
    $dirs = ['frontend', 'admin', 'api', 'promo', 'language', 'currency'];
    
    foreach ($dirs as $dir) {
        $path = $cacheDir . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
    
    // Create logs directory
    $logsDir = __DIR__ . '/../logs/';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Health system installed successfully! Redirecting to dashboard...',
        'tables' => $results
    ]);
    exit;
}
?>
