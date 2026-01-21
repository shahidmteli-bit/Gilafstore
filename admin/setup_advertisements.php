<?php
/**
 * Setup script for Advertisements & Highlights system
 * Run this once to create the required database table
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Setup Advertisements System';
$adminPage = 'advertisements';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        $db = get_db_connection();
        
        // Create advertisements_media table
        $sql = "CREATE TABLE IF NOT EXISTS advertisements_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            media_type ENUM('video', 'image') NOT NULL DEFAULT 'image',
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        
        // Create advertisements_settings table
        $sqlSettings = "CREATE TABLE IF NOT EXISTS advertisements_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sqlSettings);
        
        // Insert default settings
        $db->exec("INSERT IGNORE INTO advertisements_settings (setting_key, setting_value) VALUES 
            ('slider_enabled', '1'),
            ('autoplay_interval', '5000')
        ");
        
        $message = 'Advertisements system setup completed successfully! Tables created.';
        
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Check if tables exist
$tablesExist = false;
try {
    $db = get_db_connection();
    $result = $db->query("SHOW TABLES LIKE 'advertisements_media'");
    $tablesExist = $result->rowCount() > 0;
} catch (PDOException $e) {
    $error = 'Could not check database: ' . $e->getMessage();
}

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Setup Advertisements System</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Setup</h5>
            </div>
            <div class="card-body">
                <?php if ($tablesExist): ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>System Ready!</strong> The advertisements tables are already set up.
                        <br><br>
                        <a href="<?= base_url('admin/manage_advertisements.php'); ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i>Go to Advertisements Manager
                        </a>
                    </div>
                <?php else: ?>
                    <p>This will create the following database tables:</p>
                    <ul>
                        <li><code>advertisements_media</code> - Stores video and image files</li>
                        <li><code>advertisements_settings</code> - Stores slider settings</li>
                    </ul>
                    <form method="POST">
                        <button type="submit" name="setup" class="btn btn-primary">
                            <i class="fas fa-cog me-2"></i>Run Setup
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
