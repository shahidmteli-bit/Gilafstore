<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    try {
        $db = get_db_connection();
        
        // Add is_freshly_harvested column if it doesn't exist
        $checkCol1 = $db->query("SHOW COLUMNS FROM products LIKE 'is_freshly_harvested'");
        if ($checkCol1->rowCount() === 0) {
            $db->exec("ALTER TABLE products ADD COLUMN is_freshly_harvested TINYINT(1) NOT NULL DEFAULT 0");
            $message .= "Added 'is_freshly_harvested' column. ";
        } else {
            $message .= "'is_freshly_harvested' column already exists. ";
        }
        
        // Add freshly_harvested_order column if it doesn't exist
        $checkCol2 = $db->query("SHOW COLUMNS FROM products LIKE 'freshly_harvested_order'");
        if ($checkCol2->rowCount() === 0) {
            $db->exec("ALTER TABLE products ADD COLUMN freshly_harvested_order INT DEFAULT 0");
            $message .= "Added 'freshly_harvested_order' column. ";
        } else {
            $message .= "'freshly_harvested_order' column already exists. ";
        }
        
        $messageType = 'success';
        $message = "Setup complete! " . $message;
        
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$adminPage = 'freshly_harvested';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-leaf me-2"></i>Freshly Harvested - Database Setup</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?> mb-4">
                            <?= htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="mb-4">This setup will add the required database columns for the "Freshly Harvested" feature:</p>
                    
                    <ul class="list-group mb-4">
                        <li class="list-group-item">
                            <strong>is_freshly_harvested</strong> - Toggle to show/hide product in Freshly Harvested section
                        </li>
                        <li class="list-group-item">
                            <strong>freshly_harvested_order</strong> - Custom display order (lower numbers appear first)
                        </li>
                    </ul>
                    
                    <form method="POST">
                        <button type="submit" name="run_setup" class="btn btn-success btn-lg">
                            <i class="fas fa-database me-2"></i>Run Setup
                        </button>
                        <a href="<?= base_url('admin/manage_freshly_harvested.php'); ?>" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-cog me-2"></i>Manage Products
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
