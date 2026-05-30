<?php
/**
 * Sales Executive Portal - My Profile
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'My Profile';
$currentPage = 'profile';

// Fetch full profile from DB
$profile = db_fetch('SELECT * FROM sales_executives WHERE id = ?', [$execId]);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($newPass) || empty($confirm)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'All password fields are required.'];
    } elseif ($newPass !== $confirm) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'New passwords do not match.'];
    } elseif (strlen($newPass) < 6) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Password must be at least 6 characters.'];
    } elseif (!password_verify($current, $profile['password'])) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Current password is incorrect.'];
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        db_query('UPDATE sales_executives SET password = ? WHERE id = ?', [$hashed, $execId]);
        $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Password changed successfully.'];
        header('Location: ' . sales_base_url('profile.php'));
        exit;
    }
}

// Stats
$totalOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ?', [$execId])['cnt'] ?? 0;
$totalParties = db_fetch('SELECT COUNT(*) as cnt FROM sales_parties WHERE created_by = ?', [$execId])['cnt'] ?? 0;
$totalSales = db_fetch('SELECT COALESCE(SUM(total_amount),0) as total FROM sales_orders WHERE executive_id = ? AND status IN ("approved","dispatched","delivered")', [$execId])['total'] ?? 0;

include __DIR__ . '/includes/header.php';
?>

<!-- Profile Card -->
<div class="sp-card sp-mb-16">
    <div class="sp-profile-header">
        <div class="sp-profile-avatar">
            <?= strtoupper(substr($profile['name'], 0, 1)) ?>
        </div>
        <div class="sp-profile-name">
            <h3><?= htmlspecialchars($profile['name']) ?></h3>
            <span><?= htmlspecialchars($profile['designation'] ?? 'Sales Executive') ?> · <?= htmlspecialchars($profile['district']) ?></span>
        </div>
    </div>

    <!-- Performance Stats -->
    <div class="sp-profile-stats">
        <div class="sp-profile-stat-item">
            <div class="sp-profile-stat-val"><?= $totalOrders ?></div>
            <div class="sp-profile-stat-lbl">Orders</div>
        </div>
        <div class="sp-profile-stat-divider"></div>
        <div class="sp-profile-stat-item">
            <div class="sp-profile-stat-val sp-color-green">₹<?= number_format($totalSales, 0) ?></div>
            <div class="sp-profile-stat-lbl">Sales</div>
        </div>
        <div class="sp-profile-stat-divider"></div>
        <div class="sp-profile-stat-item">
            <div class="sp-profile-stat-val sp-color-gold"><?= $totalParties ?></div>
            <div class="sp-profile-stat-lbl">Parties</div>
        </div>
    </div>
</div>

<!-- Profile Details -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-user"></i> Details</h3>
    </div>
    <div class="sp-profile-details">
        <div class="sp-profile-row">
            <i class="fas fa-envelope"></i>
            <div>
                <div class="sp-profile-row-label">Email</div>
                <div class="sp-profile-row-value"><?= htmlspecialchars($profile['email']) ?></div>
            </div>
        </div>
        <div class="sp-profile-row">
            <i class="fas fa-phone"></i>
            <div>
                <div class="sp-profile-row-label">Phone</div>
                <div class="sp-profile-row-value"><?= htmlspecialchars($profile['phone']) ?></div>
            </div>
        </div>
        <div class="sp-profile-row">
            <i class="fas fa-map-marker-alt"></i>
            <div>
                <div class="sp-profile-row-label">District / Location</div>
                <div class="sp-profile-row-value"><?= htmlspecialchars($profile['district']) ?>, <?= htmlspecialchars($profile['location']) ?></div>
            </div>
        </div>
        <?php if (!empty($profile['assigned_area'])): ?>
        <div class="sp-profile-row">
            <i class="fas fa-map"></i>
            <div>
                <div class="sp-profile-row-label">Assigned Area</div>
                <div class="sp-profile-row-value"><?= htmlspecialchars($profile['assigned_area']) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="sp-profile-row">
            <i class="fas fa-user-tie"></i>
            <div>
                <div class="sp-profile-row-label">Reporting Manager</div>
                <div class="sp-profile-row-value"><?= htmlspecialchars($profile['reporting_manager'] ?? 'N/A') ?></div>
            </div>
        </div>
        <div class="sp-profile-row">
            <i class="fas fa-clock"></i>
            <div>
                <div class="sp-profile-row-label">Last Login</div>
                <div class="sp-profile-row-value"><?= $profile['last_login'] ? date('d M Y, h:i A', strtotime($profile['last_login'])) : 'N/A' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-lock"></i> Change Password</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="change_password" value="1">
        <div class="sp-form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" class="sp-input" required placeholder="Enter current password">
        </div>
        <div class="sp-form-group">
            <label>New Password</label>
            <input type="password" name="new_password" class="sp-input" required placeholder="Min 6 characters" minlength="6">
        </div>
        <div class="sp-form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="sp-input" required placeholder="Repeat new password">
        </div>
        <button type="submit" class="sp-btn sp-btn-primary sp-btn-block">
            <i class="fas fa-key"></i> Update Password
        </button>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
