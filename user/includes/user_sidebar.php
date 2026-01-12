<?php
/**
 * Shared User Account Sidebar Component
 * This sidebar remains fixed across all user account pages
 */

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    return;
}

$user = $_SESSION['user'];
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Profile Sidebar -->
<div class="profile-sidebar">
    <div class="profile-header">
        <div class="profile-avatar">
            <?= strtoupper(substr($user['name'], 0, 1)); ?>
        </div>
        <div class="profile-welcome">Welcome back</div>
        <div class="profile-name"><?= htmlspecialchars($user['name']); ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']); ?></div>
    </div>
    
    <nav class="profile-nav">
        <a href="<?= base_url('user/profile.php'); ?>" class="profile-nav-item <?= $currentPage === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            Profile
        </a>
        <a href="<?= base_url('user/orders.php'); ?>" class="profile-nav-item <?= $currentPage === 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i>
            My Orders
        </a>
        <a href="<?= base_url('user/manage_addresses.php'); ?>" class="profile-nav-item <?= $currentPage === 'manage_addresses.php' ? 'active' : ''; ?>">
            <i class="fas fa-map-marker-alt"></i>
            Addresses
        </a>
        <a href="<?= base_url('user/logout.php'); ?>" class="profile-nav-item">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </nav>
</div>

<style>
/* Shared Sidebar Styles */
.profile-sidebar {
    background: var(--signup-color-card, #1A3C34);
    border-radius: var(--signup-radius-lg, 12px);
    box-shadow: var(--signup-shadow-lg, 0 4px 6px rgba(0, 0, 0, 0.1));
    overflow: hidden;
    position: sticky;
    top: 20px;
    height: fit-content;
}

.profile-header {
    background: linear-gradient(135deg, #1A3C34 0%, #0f2820 100%);
    padding: 2rem;
    text-align: center;
    position: relative;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 20%, rgba(197, 160, 137, 0.15) 0%, transparent 50%);
    pointer-events: none;
}

.profile-avatar {
    width: 64px;
    height: 64px;
    background: rgba(197, 160, 137, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #C5A059;
    position: relative;
}

.profile-welcome {
    color: rgba(197, 160, 89, 0.9);
    font-size: 0.8125rem;
    font-weight: 400;
    margin-bottom: 0.25rem;
    letter-spacing: 0.5px;
}

.profile-name {
    color: white;
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.profile-email {
    color: rgba(255,255,255,0.8);
    font-size: 0.875rem;
}

.profile-nav {
    padding: 1rem;
}

.profile-nav-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #1A3C34;
    text-decoration: none;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
    font-size: 0.9375rem;
    font-weight: 500;
}

.profile-nav-item:hover {
    background: #F8F5F2;
    color: #C5A059;
}

.profile-nav-item.active {
    background: #C5A059;
    color: white;
}

.profile-nav-item i {
    width: 20px;
    margin-right: 0.75rem;
}

/* Responsive Design */
@media (max-width: 968px) {
    .profile-sidebar {
        position: static;
        margin-bottom: 1.5rem;
    }
}
</style>
