<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        $pdo = get_db_connection();
        
        // Create hero_banner_slides table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hero_banner_slides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image_path VARCHAR(500) NOT NULL,
                heading_text VARCHAR(255) DEFAULT NULL,
                sub_text TEXT DEFAULT NULL,
                cta_text VARCHAR(100) DEFAULT NULL,
                cta_link VARCHAR(500) DEFAULT NULL,
                cta2_text VARCHAR(100) DEFAULT NULL,
                cta2_link VARCHAR(500) DEFAULT NULL,
                display_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create hero_banner_settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hero_banner_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert default settings
        $pdo->exec("
            INSERT IGNORE INTO hero_banner_settings (setting_key, setting_value) VALUES
            ('slider_enabled', '0'),
            ('slider_timer', '5')
        ");
        
        // Create uploads directory
        $uploadDir = __DIR__ . '/../assets/uploads/hero_banner/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $message = 'Hero Banner tables created successfully!';
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$adminPage = 'hero_banner_setup';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-content">
    <div class="content-header">
        <h1>Hero Banner Setup</h1>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?>" style="padding: 15px; margin: 20px 0; border-radius: 4px; background: <?= $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?= $messageType === 'success' ? '#155724' : '#721c24'; ?>;">
        <?= htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px;">
        <h3 style="margin-bottom: 20px; color: #1A3C34;">Database Setup</h3>
        <p style="margin-bottom: 20px; color: #666;">This will create the necessary database tables for the Hero Banner system:</p>
        <ul style="margin-bottom: 25px; color: #666; padding-left: 20px;">
            <li><strong>hero_banner_slides</strong> - Stores slide images and content</li>
            <li><strong>hero_banner_settings</strong> - Stores slider settings (on/off, timer)</li>
        </ul>
        
        <form method="POST">
            <button type="submit" name="setup" style="background: #1A3C34; color: #fff; border: none; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 1rem;">
                Run Setup
            </button>
        </form>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="manage_hero_banner.php" style="color: #1A3C34; text-decoration: none;">← Go to Hero Banner Management</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
