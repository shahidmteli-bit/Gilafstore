<?php
/**
 * Batch Lifecycle Management Functions
 * Handles batch status transitions, validations, and automated rules
 */

// Batch status constants
define('BATCH_STATUS_PRODUCTION', 'production');
define('BATCH_STATUS_QUALITY_TESTING', 'quality_testing');
define('BATCH_STATUS_QUALITY_APPROVED', 'quality_approved');
define('BATCH_STATUS_REJECTED', 'rejected');
define('BATCH_STATUS_RELEASED_FOR_SALE', 'released_for_sale');
define('BATCH_STATUS_IN_DISTRIBUTION', 'in_distribution');
define('BATCH_STATUS_SOLD_OUT', 'sold_out');
define('BATCH_STATUS_EXPIRED', 'expired');
define('BATCH_STATUS_RECALLED', 'recalled');
define('BATCH_STATUS_BLOCKED', 'blocked');
define('BATCH_STATUS_ARCHIVED', 'archived');

// Alert types
define('ALERT_EXPIRY_30_DAYS', 'expiry_30_days');
define('ALERT_EXPIRY_60_DAYS', 'expiry_60_days');
define('ALERT_STOCK_LOW', 'stock_low');
define('ALERT_RECALLED_VERIFIED', 'recalled_verified');
define('ALERT_REPEATED_VERIFICATION', 'repeated_verification');
define('ALERT_SUSPICIOUS_ACTIVITY', 'suspicious_activity');

/**
 * Get batch status color badge
 */
function get_batch_status_badge($status) {
    $badges = [
        'production' => '<span class="badge bg-secondary">🔧 Production</span>',
        'quality_testing' => '<span class="badge bg-warning">🧪 Quality Testing</span>',
        'quality_approved' => '<span class="badge bg-success">🟢 Approved</span>',
        'rejected' => '<span class="badge bg-danger">🔴 Rejected</span>',
        'released_for_sale' => '<span class="badge bg-primary">🔵 Released for Sale</span>',
        'in_distribution' => '<span class="badge bg-info">🔵 In Distribution</span>',
        'sold_out' => '<span class="badge bg-dark">⚫ Sold Out</span>',
        'expired' => '<span class="badge bg-danger">🔴 Expired</span>',
        'recalled' => '<span class="badge bg-danger">🚫 Recalled</span>',
        'blocked' => '<span class="badge bg-danger">🔴 Blocked</span>',
        'archived' => '<span class="badge bg-secondary">📦 Archived</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Get batch optional badges
 */
function get_batch_optional_badges($batch) {
    $badges = [];
    
    if ($batch['is_lab_tested']) {
        $badges[] = '<span class="badge bg-info">🧪 Lab Tested</span>';
    }
    
    if ($batch['is_organic']) {
        $badges[] = '<span class="badge bg-success">🌱 Organic</span>';
    }
    
    return implode(' ', $badges);
}

/**
 * Check if batch is expired and auto-update status
 */
function check_and_update_expired_batches() {
    $db = get_db_connection();
    
    $sql = "UPDATE batch_codes 
            SET status = 'expired', 
                auto_expired = 1, 
                last_status_change = NOW(),
                status_change_reason = 'Auto-expired after expiry date'
            WHERE expiry_date < CURDATE() 
            AND status NOT IN ('expired', 'archived', 'recalled')
            AND auto_expired = 0";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        error_log("Auto-expired $count batch(es)");
    }
    
    return $count;
}

/**
 * Create batch alert
 */
function create_batch_alert($batchCodeId, $batchCode, $alertType, $severity, $message, $alertData = null) {
    $db = get_db_connection();
    
    $sql = "INSERT INTO batch_alerts 
            (batch_code_id, batch_code, alert_type, severity, message, alert_data) 
            VALUES (:batch_code_id, :batch_code, :alert_type, :severity, :message, :alert_data)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':batch_code_id' => $batchCodeId,
        ':batch_code' => $batchCode,
        ':alert_type' => $alertType,
        ':severity' => $severity,
        ':message' => $message,
        ':alert_data' => $alertData ? json_encode($alertData) : null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Check for expiring batches and create alerts
 */
function check_expiring_batches() {
    $db = get_db_connection();
    
    // Check for batches expiring in 30 days
    $sql30 = "SELECT id, batch_code, product_name, expiry_date 
              FROM batch_codes 
              WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND status IN ('released_for_sale', 'in_distribution')
              AND id NOT IN (
                  SELECT batch_code_id FROM batch_alerts 
                  WHERE alert_type = 'expiry_30_days' 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
              )";
    
    $stmt = $db->query($sql30);
    $batches30 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($batches30 as $batch) {
        $daysLeft = (strtotime($batch['expiry_date']) - time()) / (60 * 60 * 24);
        $message = "Batch {$batch['batch_code']} ({$batch['product_name']}) expires in " . ceil($daysLeft) . " days";
        create_batch_alert($batch['id'], $batch['batch_code'], ALERT_EXPIRY_30_DAYS, 'medium', $message);
    }
    
    // Check for batches expiring in 60 days
    $sql60 = "SELECT id, batch_code, product_name, expiry_date 
              FROM batch_codes 
              WHERE expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
              AND status IN ('released_for_sale', 'in_distribution')
              AND id NOT IN (
                  SELECT batch_code_id FROM batch_alerts 
                  WHERE alert_type = 'expiry_60_days' 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
              )";
    
    $stmt = $db->query($sql60);
    $batches60 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($batches60 as $batch) {
        $daysLeft = (strtotime($batch['expiry_date']) - time()) / (60 * 60 * 24);
        $message = "Batch {$batch['batch_code']} ({$batch['product_name']}) expires in " . ceil($daysLeft) . " days";
        create_batch_alert($batch['id'], $batch['batch_code'], ALERT_EXPIRY_60_DAYS, 'low', $message);
    }
    
    return count($batches30) + count($batches60);
}

/**
 * Log batch verification
 */
function log_batch_verification($batchCodeId, $batchCode, $method = 'manual_entry') {
    try {
        $db = get_db_connection();
        
        // Create batch_verifications table if it doesn't exist
        $db->exec("CREATE TABLE IF NOT EXISTS batch_verifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            batch_code_id INT NOT NULL,
            batch_code VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            verification_method VARCHAR(50) DEFAULT 'manual_entry',
            language VARCHAR(10) DEFAULT 'en',
            country VARCHAR(100),
            city VARCHAR(100),
            verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch_code (batch_code),
            INDEX idx_batch_code_id (batch_code_id),
            INDEX idx_verified_at (verified_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
        $language = substr($language, 0, 2);
        
        // Get geolocation (simplified - you can integrate with IP geolocation API)
        $country = null;
        $city = null;
        
        $sql = "INSERT INTO batch_verifications 
                (batch_code_id, batch_code, ip_address, user_agent, verification_method, language, country, city) 
                VALUES (:batch_code_id, :batch_code, :ip_address, :user_agent, :method, :language, :country, :city)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':batch_code_id' => $batchCodeId,
            ':batch_code' => $batchCode,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':method' => $method,
            ':language' => $language,
            ':country' => $country,
            ':city' => $city
        ]);
        
        $verificationId = $db->lastInsertId();
        
        // Check for suspicious activity (wrapped in try-catch to prevent failures)
        try {
            check_suspicious_verification($batchCodeId, $batchCode, $ipAddress);
        } catch (Exception $e) {
            error_log("Suspicious verification check failed: " . $e->getMessage());
        }
        
        return $verificationId;
    } catch (Exception $e) {
        error_log("Batch verification logging error: " . $e->getMessage());
        // Return null but don't throw - logging is non-critical
        return null;
    }
}

/**
 * Check for suspicious verification patterns
 */
function check_suspicious_verification($batchCodeId, $batchCode, $ipAddress) {
    $db = get_db_connection();
    
    // Check for repeated verifications from same IP in last hour
    $sql = "SELECT COUNT(*) as count 
            FROM batch_verifications 
            WHERE batch_code_id = :batch_code_id 
            AND ip_address = :ip_address 
            AND verified_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':batch_code_id' => $batchCodeId, ':ip_address' => $ipAddress]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 5) {
        // Mark as suspicious
        $updateSql = "UPDATE batch_verifications 
                      SET is_suspicious = 1, 
                          suspicion_reason = 'Repeated verification from same IP'
                      WHERE batch_code_id = :batch_code_id 
                      AND ip_address = :ip_address 
                      AND verified_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute([':batch_code_id' => $batchCodeId, ':ip_address' => $ipAddress]);
        
        // Create alert
        $message = "Suspicious activity: Batch $batchCode verified {$result['count']} times from IP $ipAddress in last hour";
        create_batch_alert($batchCodeId, $batchCode, ALERT_REPEATED_VERIFICATION, 'high', $message, [
            'ip_address' => $ipAddress,
            'count' => $result['count']
        ]);
    }
    
    // Check if recalled/expired batch is being verified
    $batchSql = "SELECT status FROM batch_codes WHERE id = :id";
    $batchStmt = $db->prepare($batchSql);
    $batchStmt->execute([':id' => $batchCodeId]);
    $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($batch && in_array($batch['status'], ['recalled', 'expired', 'blocked'])) {
        $message = "Alert: {$batch['status']} batch $batchCode is still being verified - possible counterfeit circulation";
        create_batch_alert($batchCodeId, $batchCode, ALERT_RECALLED_VERIFIED, 'critical', $message, [
            'status' => $batch['status'],
            'ip_address' => $ipAddress
        ]);
    }
}

/**
 * Log batch audit trail
 */
function log_batch_audit($batchCodeId, $batchCode, $action, $oldStatus, $newStatus, $userId, $userName, $notes = null, $actionDetails = null) {
    $db = get_db_connection();
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $sql = "INSERT INTO batch_audit_trail 
            (batch_code_id, batch_code, action, old_status, new_status, performed_by, performer_name, action_details, notes, ip_address) 
            VALUES (:batch_code_id, :batch_code, :action, :old_status, :new_status, :performed_by, :performer_name, :action_details, :notes, :ip_address)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':batch_code_id' => $batchCodeId,
        ':batch_code' => $batchCode,
        ':action' => $action,
        ':old_status' => $oldStatus,
        ':new_status' => $newStatus,
        ':performed_by' => $userId,
        ':performer_name' => $userName,
        ':action_details' => $actionDetails ? json_encode($actionDetails) : null,
        ':notes' => $notes,
        ':ip_address' => $ipAddress
    ]);
    
    return $db->lastInsertId();
}

/**
 * Get next batch using FIFO logic
 */
function get_next_batch_fifo($productId) {
    $db = get_db_connection();
    
    $sql = "SELECT * FROM batch_codes 
            WHERE product_id = :product_id 
            AND status = 'released_for_sale'
            AND units_remaining > 0
            AND expiry_date > CURDATE()
            ORDER BY manufacturing_date ASC, id ASC
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':product_id' => $productId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update batch status
 */
function update_batch_status($batchId, $newStatus, $userId, $userName, $reason = null) {
    $db = get_db_connection();
    
    // Get current batch
    $stmt = $db->prepare("SELECT * FROM batch_codes WHERE id = :id");
    $stmt->execute([':id' => $batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        throw new Exception('Batch not found');
    }
    
    $oldStatus = $batch['status'];
    
    // Update status
    $updateSql = "UPDATE batch_codes 
                  SET status = :status, 
                      last_status_change = NOW(),
                      status_change_reason = :reason
                  WHERE id = :id";
    
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([
        ':status' => $newStatus,
        ':reason' => $reason,
        ':id' => $batchId
    ]);
    
    // Log audit trail
    log_batch_audit($batchId, $batch['batch_code'], 'status_change', $oldStatus, $newStatus, $userId, $userName, $reason);
    
    return true;
}
