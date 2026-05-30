<?php
/**
 * Email Settings API - CRUD operations for email configurations and task assignments
 * Admin-only endpoint
 */
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Admin check
if (!isset($_SESSION['admin']) && !isset($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$db = get_db_connection();

// Auto-create tables if not exist
$db->exec("
    CREATE TABLE IF NOT EXISTS email_configurations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email_address VARCHAR(255) NOT NULL,
        display_name VARCHAR(255) DEFAULT '',
        smtp_host VARCHAR(255) DEFAULT 'smtp.gmail.com',
        smtp_port INT DEFAULT 587,
        smtp_encryption ENUM('tls','ssl','none') DEFAULT 'tls',
        smtp_username VARCHAR(255) DEFAULT '',
        smtp_password VARCHAR(255) DEFAULT '',
        provider ENUM('gmail','outlook','custom','domain') DEFAULT 'gmail',
        is_active TINYINT(1) DEFAULT 1,
        is_verified TINYINT(1) DEFAULT 0,
        last_tested_at DATETIME NULL,
        test_status ENUM('untested','success','failed') DEFAULT 'untested',
        notes TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_email (email_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->exec("
    CREATE TABLE IF NOT EXISTS email_task_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_key VARCHAR(100) NOT NULL,
        task_label VARCHAR(255) NOT NULL,
        task_group VARCHAR(100) DEFAULT 'general',
        email_config_id INT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_task (task_key),
        INDEX idx_email (email_config_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Seed default tasks if empty
$taskCount = (int)$db->query("SELECT COUNT(*) FROM email_task_assignments")->fetchColumn();
if ($taskCount === 0) {
    $defaultTasks = [
        ['order_confirmation', 'Order Confirmation', 'orders'],
        ['order_cancellation', 'Order Cancellation', 'orders'],
        ['order_shipped', 'Order Shipped', 'orders'],
        ['order_delivered', 'Order Delivered', 'orders'],
        ['order_refund', 'Refund Notification', 'orders'],
        ['password_reset', 'Password Reset', 'security'],
        ['welcome_email', 'Welcome Email', 'general'],
        ['helpdesk', 'Help Desk', 'support'],
        ['ticket_confirmation', 'Ticket Confirmation', 'support'],
        ['ticket_reply', 'Ticket Reply', 'support'],
        ['newsletter', 'Newsletter', 'marketing'],
        ['promotional', 'Promotional Offers', 'marketing'],
        ['account_verification', 'Account Verification', 'security'],
        ['payment_receipt', 'Payment Receipt', 'orders'],
        ['callback_confirmation', 'Callback Confirmation', 'support'],
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO email_task_assignments (task_key, task_label, task_group) VALUES (?, ?, ?)");
    foreach ($defaultTasks as $t) {
        $stmt->execute($t);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ─── EMAIL CONFIGURATIONS ──────────────────────
    case 'list_emails':
        $emails = $db->query("SELECT * FROM email_configurations ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $emails]);
        break;

    case 'get_email':
        $id = (int)($_GET['id'] ?? 0);
        $email = $db->prepare("SELECT * FROM email_configurations WHERE id = ?");
        $email->execute([$id]);
        $data = $email->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            // Mask password for display
            $data['smtp_password_masked'] = $data['smtp_password'] ? str_repeat('•', 12) : '';
        }
        echo json_encode(['success' => (bool)$data, 'data' => $data ?: null]);
        break;

    case 'save_email':
        $id = (int)($_POST['id'] ?? 0);
        $emailAddr = trim($_POST['email_address'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $smtpHost = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpEnc = in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none']) ? $_POST['smtp_encryption'] : 'tls';
        $smtpUser = trim($_POST['smtp_username'] ?? '');
        $smtpPass = trim($_POST['smtp_password'] ?? '');
        $provider = in_array($_POST['provider'] ?? '', ['gmail', 'outlook', 'custom', 'domain']) ? $_POST['provider'] : 'gmail';
        $isActive = (int)($_POST['is_active'] ?? 1);
        $notes = trim($_POST['notes'] ?? '');

        if (!$emailAddr || !filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            break;
        }

        try {
            if ($id > 0) {
                // Update existing
                $sql = "UPDATE email_configurations SET email_address=?, display_name=?, smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, provider=?, is_active=?, notes=?";
                $params = [$emailAddr, $displayName, $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $provider, $isActive, $notes];

                // Only update password if provided (not the masked placeholder)
                if ($smtpPass && strpos($smtpPass, '•') === false) {
                    $sql .= ", smtp_password=?";
                    $params[] = $smtpPass;
                }
                $sql .= " WHERE id=?";
                $params[] = $id;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => 'Email configuration updated']);
            } else {
                // Insert new
                $stmt = $db->prepare("INSERT INTO email_configurations (email_address, display_name, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, provider, is_active, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$emailAddr, $displayName, $smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPass, $provider, $isActive, $notes]);
                echo json_encode(['success' => true, 'message' => 'Email configuration added', 'id' => $db->lastInsertId()]);
            }
        } catch (PDOException $e) {
            $msg = strpos($e->getMessage(), 'Duplicate') !== false ? 'This email address already exists' : 'Database error: ' . $e->getMessage();
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        break;

    case 'delete_email':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            break;
        }
        // Unassign tasks first
        $db->prepare("UPDATE email_task_assignments SET email_config_id = NULL WHERE email_config_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM email_configurations WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Email configuration deleted']);
        break;

    case 'toggle_email':
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE email_configurations SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Status toggled']);
        break;

    case 'test_email':
        $id = (int)($_POST['id'] ?? 0);
        $config = $db->prepare("SELECT * FROM email_configurations WHERE id = ?");
        $config->execute([$id]);
        $cfg = $config->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            echo json_encode(['success' => false, 'message' => 'Email config not found']);
            break;
        }

        // Try sending a test email using PHPMailer
        try {
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['smtp_username'] ?: $cfg['email_address'];
            $mail->Password   = $cfg['smtp_password'];
            $mail->Port       = $cfg['smtp_port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 15;

            if ($cfg['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($cfg['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom($cfg['email_address'], $cfg['display_name'] ?: 'Gilaf Store');
            $mail->addAddress($cfg['email_address']); // Send test to self
            $mail->isHTML(true);
            $mail->Subject = 'Gilaf Store - SMTP Test (' . date('H:i:s') . ')';
            $mail->Body = '<div style="font-family:Arial;padding:20px;"><h2 style="color:#1A3C34;">✅ SMTP Connection Successful</h2><p>This test email was sent from the Gilaf Store Admin Panel Email Settings.</p><p><strong>Email:</strong> ' . htmlspecialchars($cfg['email_address']) . '<br><strong>Host:</strong> ' . htmlspecialchars($cfg['smtp_host']) . ':' . $cfg['smtp_port'] . '<br><strong>Time:</strong> ' . date('d M Y, h:i:s A') . '</p></div>';

            $mail->send();

            $db->prepare("UPDATE email_configurations SET is_verified=1, last_tested_at=NOW(), test_status='success' WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $cfg['email_address']]);
        } catch (Exception $e) {
            $db->prepare("UPDATE email_configurations SET is_verified=0, last_tested_at=NOW(), test_status='failed' WHERE id=?")->execute([$id]);
            echo json_encode(['success' => false, 'message' => 'SMTP test failed: ' . $e->getMessage()]);
        }
        break;

    // ─── TASK ASSIGNMENTS ──────────────────────
    case 'list_tasks':
        $tasks = $db->query("
            SELECT t.*, e.email_address, e.display_name, e.is_active as email_active, e.is_verified, e.test_status
            FROM email_task_assignments t
            LEFT JOIN email_configurations e ON t.email_config_id = e.id
            ORDER BY t.task_group, t.task_label
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $tasks]);
        break;

    case 'assign_task':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $emailConfigId = $_POST['email_config_id'] ?? null;
        $emailConfigId = ($emailConfigId === '' || $emailConfigId === null) ? null : (int)$emailConfigId;

        $stmt = $db->prepare("UPDATE email_task_assignments SET email_config_id = ? WHERE id = ?");
        $stmt->execute([$emailConfigId, $taskId]);
        echo json_encode(['success' => true, 'message' => 'Task assignment updated']);
        break;

    case 'add_task':
        $taskKey = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['task_key'] ?? '')));
        $taskLabel = trim($_POST['task_label'] ?? '');
        $taskGroup = trim($_POST['task_group'] ?? 'general');

        if (!$taskKey || !$taskLabel) {
            echo json_encode(['success' => false, 'message' => 'Task key and label are required']);
            break;
        }

        try {
            $stmt = $db->prepare("INSERT INTO email_task_assignments (task_key, task_label, task_group) VALUES (?, ?, ?)");
            $stmt->execute([$taskKey, $taskLabel, $taskGroup]);
            echo json_encode(['success' => true, 'message' => 'Task added', 'id' => $db->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Task key already exists']);
        }
        break;

    case 'delete_task':
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM email_task_assignments WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Task deleted']);
        break;

    // ─── HELPER: Get email for a task (used by email_config.php) ──────
    case 'get_email_for_task':
        $taskKey = trim($_GET['task_key'] ?? '');
        $stmt = $db->prepare("
            SELECT e.* FROM email_configurations e
            JOIN email_task_assignments t ON t.email_config_id = e.id
            WHERE t.task_key = ? AND e.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$taskKey]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => (bool)$data, 'data' => $data ?: null]);
        break;

    // ─── EMAIL SEND LOGS ──────────────────────────────────────────
    case 'get_email_logs':
        require_once __DIR__ . '/../includes/email_helper.php';
        $filter = $_GET['filter'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        $logs = get_email_send_logs($limit, $filter ?: null);
        $stats = get_email_send_stats();
        echo json_encode(['success' => true, 'logs' => $logs, 'stats' => $stats]);
        break;

    case 'clear_email_logs':
        try {
            $db->exec("DELETE FROM email_send_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $count = $db->query("SELECT ROW_COUNT()")->fetchColumn();
            echo json_encode(['success' => true, 'message' => "Cleared $count old log entries (30+ days)"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
