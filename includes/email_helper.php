<?php
/**
 * Dynamic Email Helper
 * Loads SMTP configuration from database based on task assignment
 * Includes fallback chain: Assigned Email → System Default → Hardcoded
 * Logs all attempts to email_send_log table for admin panel tracking
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Ensure email_send_log table exists (auto-create once)
 */
function ensure_email_log_table() {
    static $checked = false;
    if ($checked) return;
    try {
        $db = get_db_connection();
        $db->exec("CREATE TABLE IF NOT EXISTS email_send_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_key VARCHAR(100) DEFAULT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) DEFAULT '',
            assigned_email VARCHAR(255) DEFAULT NULL,
            sent_from_email VARCHAR(255) DEFAULT NULL,
            status ENUM('success','failed','fallback') NOT NULL DEFAULT 'success',
            error_message TEXT DEFAULT NULL,
            fallback_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_task (task_key),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $checked = true;
    } catch (Exception $e) {
        error_log("email_helper: Could not create email_send_log table: " . $e->getMessage());
    }
}

/**
 * Log an email send attempt to the database
 */
function log_email_send($taskKey, $to, $subject, $assignedEmail, $sentFrom, $status, $errorMsg = null, $fallbackReason = null) {
    try {
        ensure_email_log_table();
        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO email_send_log 
            (task_key, recipient_email, subject, assigned_email, sent_from_email, status, error_message, fallback_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $taskKey, $to, substr($subject, 0, 500),
            $assignedEmail, $sentFrom, $status,
            $errorMsg ? substr($errorMsg, 0, 2000) : null,
            $fallbackReason ? substr($fallbackReason, 0, 2000) : null
        ]);
    } catch (Exception $e) {
        error_log("email_helper: Failed to log email send: " . $e->getMessage());
    }
}

/**
 * Get the email configuration for a specific task
 */
function get_email_config_for_task($taskKey) {
    try {
        $db = get_db_connection();
        $tableCheck = $db->query("SHOW TABLES LIKE 'email_task_assignments'")->rowCount();
        if (!$tableCheck) return null;
        
        $stmt = $db->prepare("
            SELECT e.* FROM email_configurations e
            JOIN email_task_assignments t ON t.email_config_id = e.id
            WHERE t.task_key = ? AND e.is_active = 1 LIMIT 1
        ");
        $stmt->execute([$taskKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("email_helper: Failed to get config for task '$taskKey': " . $e->getMessage());
        return null;
    }
}

/**
 * Get the first active email config from DB as system default fallback
 */
function get_default_email_config() {
    try {
        $db = get_db_connection();
        $tableCheck = $db->query("SHOW TABLES LIKE 'email_configurations'")->rowCount();
        if (!$tableCheck) return null;
        
        $stmt = $db->query("SELECT * FROM email_configurations WHERE is_active = 1 AND test_status = 'success' ORDER BY id ASC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Send an email using the dynamically assigned email config for a task
 * Fallback chain: Assigned Email → System Default (first verified) → Hardcoded
 * Logs all attempts to DB for admin panel tracking
 */
function send_task_email($taskKey, $to, $subject, $body, $fallbackFromEmail = '', $fallbackFromName = 'Gilaf Store') {
    $assignedConfig = get_email_config_for_task($taskKey);
    $assignedEmail = $assignedConfig ? $assignedConfig['email_address'] : null;
    
    // ─── Attempt 1: Try the assigned email from DB ───
    if ($assignedConfig) {
        $result = send_email_with_config($assignedConfig, $to, $subject, $body);
        if ($result) {
            log_email_send($taskKey, $to, $subject, $assignedEmail, $assignedEmail, 'success');
            return true;
        }
        
        // Assigned email failed — try system default fallback
        $errorMsg = "Primary email {$assignedEmail} failed for task '{$taskKey}'";
        error_log("email_helper: $errorMsg — trying fallback...");
        
        // ─── Attempt 2: Try any other verified email as fallback ───
        $defaultConfig = get_default_email_config();
        if ($defaultConfig && $defaultConfig['email_address'] !== $assignedEmail) {
            $fallbackResult = send_email_with_config($defaultConfig, $to, $subject, $body);
            if ($fallbackResult) {
                $fallbackFrom = $defaultConfig['email_address'];
                log_email_send($taskKey, $to, $subject, $assignedEmail, $fallbackFrom, 'fallback',
                    $errorMsg, "Sent via fallback email {$fallbackFrom} because {$assignedEmail} failed");
                return true;
            }
        }
        
        // ─── Attempt 3: Try hardcoded default ───
        if ($fallbackFromEmail && function_exists('send_email')) {
            $hardcodedResult = send_email($to, $subject, $body, $fallbackFromEmail, $fallbackFromName);
            if ($hardcodedResult) {
                log_email_send($taskKey, $to, $subject, $assignedEmail, $fallbackFromEmail, 'fallback',
                    $errorMsg, "Sent via hardcoded fallback {$fallbackFromEmail} because {$assignedEmail} failed");
                return true;
            }
        }
        
        // All attempts failed
        log_email_send($taskKey, $to, $subject, $assignedEmail, null, 'failed',
            "ALL attempts failed. Primary: {$assignedEmail}. Fallback also failed.", null);
        return false;
    }
    
    // ─── No DB assignment: use hardcoded send_email() ───
    if (!function_exists('send_email')) {
        require_once __DIR__ . '/email_config.php';
    }
    
    $sentFrom = $fallbackFromEmail ?: 'gilaf.secure@gmail.com';
    $result = $fallbackFromEmail
        ? send_email($to, $subject, $body, $fallbackFromEmail, $fallbackFromName)
        : send_email($to, $subject, $body);
    
    $status = $result ? 'success' : 'failed';
    log_email_send($taskKey, $to, $subject, null, $sentFrom, $status,
        $result ? null : "No DB config for task '{$taskKey}', hardcoded sender also failed");
    return $result;
}

/**
 * Send email using a specific email_configurations record (full SMTP details from DB)
 */
function send_email_with_config($config, $to, $subject, $body) {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("send_email_with_config: Invalid recipient - $to");
        return false;
    }
    
    try {
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'] ?: $config['email_address'];
        $mail->Password   = $config['smtp_password'];
        $mail->Port       = (int)$config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;
        
        if ($config['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        
        $fromEmail = $config['email_address'];
        $fromName  = $config['display_name'] ?: 'Gilaf Store';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($fromEmail, $fromName);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        if ($mail->send()) {
            error_log("send_email_with_config: SUCCESS via {$fromEmail} to {$to}");
            return true;
        } else {
            error_log("send_email_with_config: FAILED via {$fromEmail}: {$mail->ErrorInfo}");
            return false;
        }
    } catch (Exception $e) {
        error_log("send_email_with_config: EXCEPTION via {$config['email_address']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent email send logs for admin panel
 */
function get_email_send_logs($limit = 50, $statusFilter = null) {
    try {
        ensure_email_log_table();
        $db = get_db_connection();
        $sql = "SELECT * FROM email_send_log";
        $params = [];
        if ($statusFilter) {
            $sql .= " WHERE status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get email send stats for admin panel
 */
function get_email_send_stats() {
    try {
        ensure_email_log_table();
        $db = get_db_connection();
        $stats = [];
        // Total today
        $stmt = $db->query("SELECT 
            COUNT(*) as total,
            SUM(status='success') as success,
            SUM(status='failed') as failed,
            SUM(status='fallback') as fallback
            FROM email_send_log WHERE DATE(created_at) = CURDATE()");
        $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC);
        // Total all time
        $stmt = $db->query("SELECT 
            COUNT(*) as total,
            SUM(status='success') as success,
            SUM(status='failed') as failed,
            SUM(status='fallback') as fallback
            FROM email_send_log");
        $stats['all_time'] = $stmt->fetch(PDO::FETCH_ASSOC);
        return $stats;
    } catch (Exception $e) {
        return ['today' => ['total'=>0,'success'=>0,'failed'=>0,'fallback'=>0],
                'all_time' => ['total'=>0,'success'=>0,'failed'=>0,'fallback'=>0]];
    }
}
