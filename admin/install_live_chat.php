<?php
/**
 * Live Chat System - Database Installation
 * Run this file once to create the required tables
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_admin();

$page_title = "Install Live Chat System";
include '../includes/admin_header.php';

$messages = [];
$errors = [];

// Create tables when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    
    // Table 1: live_chats - Stores chat sessions
    $sql_chats = "CREATE TABLE IF NOT EXISTS live_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL UNIQUE,
        customer_id INT NULL,
        customer_name VARCHAR(100) NULL,
        customer_email VARCHAR(255) NULL,
        customer_phone VARCHAR(20) NULL,
        assigned_admin_id INT NULL,
        status ENUM('bot', 'waiting', 'active', 'closed') DEFAULT 'bot',
        is_online TINYINT(1) DEFAULT 1,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        closed_at TIMESTAMP NULL,
        INDEX idx_session (session_id),
        INDEX idx_status (status),
        INDEX idx_customer (customer_id),
        INDEX idx_admin (assigned_admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 2: live_chat_messages - Stores all messages
    $sql_messages = "CREATE TABLE IF NOT EXISTS live_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        sender_type ENUM('user', 'bot', 'admin', 'system') NOT NULL,
        sender_id INT NULL,
        sender_name VARCHAR(100) NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chat (chat_id),
        INDEX idx_sender (sender_type),
        INDEX idx_read (is_read),
        INDEX idx_created (created_at),
        FOREIGN KEY (chat_id) REFERENCES live_chats(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 3: live_chat_call_requests - Stores call requests
    $sql_call_requests = "CREATE TABLE IF NOT EXISTS live_chat_call_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NULL,
        customer_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_email VARCHAR(255) NULL,
        status ENUM('pending', 'connected', 'completed', 'cancelled') DEFAULT 'pending',
        admin_id INT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        connected_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        INDEX idx_status (status),
        INDEX idx_customer (customer_id),
        INDEX idx_chat (chat_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 4: live_chat_admin_settings - Admin preferences
    $sql_admin_settings = "CREATE TABLE IF NOT EXISTS live_chat_admin_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL UNIQUE,
        sound_enabled TINYINT(1) DEFAULT 1,
        notification_enabled TINYINT(1) DEFAULT 1,
        auto_accept_chats TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Execute table creation
    $tables = [
        'live_chats' => $sql_chats,
        'live_chat_messages' => $sql_messages,
        'live_chat_call_requests' => $sql_call_requests,
        'live_chat_admin_settings' => $sql_admin_settings
    ];
    
    foreach ($tables as $table_name => $sql) {
        if ($conn->query($sql) === TRUE) {
            $messages[] = "Table '$table_name' created successfully!";
        } else {
            $errors[] = "Error creating table '$table_name': " . $conn->error;
        }
    }
}

// Check existing tables
$existing_tables = [];
$check_tables = ['live_chats', 'live_chat_messages', 'live_chat_call_requests', 'live_chat_admin_settings'];
foreach ($check_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $existing_tables[] = $table;
    }
}
?>

<style>
.install-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
}
.install-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 20px;
}
.install-card h2 {
    color: #1A3C34;
    margin: 0 0 20px 0;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.install-card h2 i {
    color: #C9A961;
}
.table-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}
.table-list li {
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.table-list li.exists {
    background: #d4edda;
    border-left: 4px solid #28a745;
}
.table-list li.missing {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
.status-badge {
    font-size: 0.8rem;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
}
.status-badge.exists {
    background: #28a745;
    color: white;
}
.status-badge.missing {
    background: #ffc107;
    color: #856404;
}
.install-btn {
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4e 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}
.install-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(26,60,52,0.3);
}
.install-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}
.feature-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
}
.feature-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}
.feature-item i {
    color: #C9A961;
    font-size: 1.2rem;
    margin-top: 2px;
}
.feature-item h4 {
    margin: 0 0 5px 0;
    color: #1A3C34;
    font-size: 0.95rem;
}
.feature-item p {
    margin: 0;
    color: #666;
    font-size: 0.85rem;
}
</style>

<div class="install-container">
    <div class="install-card">
        <h2><i class="fas fa-comments"></i> Live Chat System Installation</h2>
        
        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endforeach; ?>
        
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?>
            </div>
        <?php endforeach; ?>
        
        <p>This will create the required database tables for the real-time live chat system.</p>
        
        <h3 style="margin: 20px 0 10px; color: #1A3C34;">Required Tables:</h3>
        <ul class="table-list">
            <?php foreach ($check_tables as $table): ?>
                <?php $exists = in_array($table, $existing_tables); ?>
                <li class="<?= $exists ? 'exists' : 'missing' ?>">
                    <span><i class="fas fa-database"></i> <?= $table ?></span>
                    <span class="status-badge <?= $exists ? 'exists' : 'missing' ?>">
                        <?= $exists ? 'Installed' : 'Not Installed' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <form method="POST">
            <button type="submit" name="install" class="install-btn" <?= count($existing_tables) === 4 ? 'disabled' : '' ?>>
                <i class="fas fa-download"></i>
                <?= count($existing_tables) === 4 ? 'Already Installed' : 'Install Live Chat Tables' ?>
            </button>
        </form>
    </div>
    
    <div class="install-card">
        <h2><i class="fas fa-star"></i> Features Included</h2>
        <div class="feature-list">
            <div class="feature-item">
                <i class="fas fa-bolt"></i>
                <div>
                    <h4>Real-Time Messaging</h4>
                    <p>Instant message delivery with sound notifications</p>
                </div>
            </div>
            <div class="feature-item">
                <i class="fas fa-robot"></i>
                <div>
                    <h4>Bot to Human Handoff</h4>
                    <p>Seamless transition from AI to human agent</p>
                </div>
            </div>
            <div class="feature-item">
                <i class="fas fa-phone"></i>
                <div>
                    <h4>Call Request Management</h4>
                    <p>Track and manage customer call requests</p>
                </div>
            </div>
            <div class="feature-item">
                <i class="fas fa-bell"></i>
                <div>
                    <h4>Sound & Browser Notifications</h4>
                    <p>Never miss a customer message</p>
                </div>
            </div>
            <div class="feature-item">
                <i class="fas fa-user-circle"></i>
                <div>
                    <h4>Online/Offline Status</h4>
                    <p>See which customers are currently active</p>
                </div>
            </div>
            <div class="feature-item">
                <i class="fas fa-history"></i>
                <div>
                    <h4>Chat History</h4>
                    <p>Auto-cleanup after chat ends to save space</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (count($existing_tables) === 4): ?>
    <div class="install-card" style="background: #d4edda; border: 2px solid #28a745;">
        <h2 style="color: #155724;"><i class="fas fa-check-circle"></i> Installation Complete!</h2>
        <p style="margin: 0; color: #155724;">
            All tables are installed. You can now access the 
            <a href="live_chat_admin.php" style="color: #155724; font-weight: 600;">Live Chat Admin Panel</a>.
        </p>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/admin_footer.php'; ?>
