<?php
/**
 * Sales Executive Portal - Header Template
 */
if (!isset($pageTitle)) $pageTitle = 'Sales Portal — Gilaf Store';
$exec = sales_get_executive();
$currentPage = $currentPage ?? '';
require_once __DIR__ . '/../../includes/company_profile_functions.php';
$_spLogoUrl = get_company_logo_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#7c3aed">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gilaf Sales">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="manifest" href="<?= sales_base_url('manifest.json') ?>">
    <link rel="apple-touch-icon" href="<?= sales_base_url('assets/icons/icon-192x192.png') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= sales_base_url('assets/icons/icon-192x192.png') ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= sales_base_url('assets/icons/icon-512x512.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $portalCssPath = __DIR__ . '/../assets/css/portal.css'; ?>
    <link rel="stylesheet" href="<?= sales_base_url('assets/css/portal.css') ?>?v=<?= file_exists($portalCssPath) ? filemtime($portalCssPath) : '20260224' ?>">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sp-sidebar" id="spSidebar">
        <div class="sp-sidebar-header">
            <div class="sp-brand">
                <div class="sp-brand-icon" style="background:none;padding:0;overflow:hidden;border-radius:10px;">
                    <img src="<?= $_spLogoUrl ?>" alt="Gilaf Store" style="width:38px;height:38px;object-fit:contain;border-radius:10px;">
                </div>
                <div class="sp-brand-text">
                    <h2>GILAF STORE</h2>
                    <span>Sales Portal</span>
                </div>
            </div>
            <button class="sp-sidebar-close" id="spSidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sp-exec-card">
            <div class="sp-exec-avatar">
                <?= strtoupper(substr($exec['name'], 0, 1)) ?>
            </div>
            <div class="sp-exec-info">
                <h4><?= htmlspecialchars($exec['name']) ?></h4>
                <span class="sp-exec-designation"><?= htmlspecialchars($exec['designation'] ?? 'Sales Executive') ?></span>
                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($exec['district']) ?></span>
            </div>
        </div>

        <nav class="sp-nav">
            <a href="<?= sales_base_url('index.php') ?>" class="sp-nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= sales_base_url('parties.php') ?>" class="sp-nav-item <?= $currentPage === 'parties' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Parties</span>
            </a>
            <a href="<?= sales_base_url('scan_party.php') ?>" class="sp-nav-item <?= $currentPage === 'scan_party' ? 'active' : '' ?>">
                <i class="fas fa-qrcode"></i>
                <span>Scan Party</span>
            </a>
            <a href="<?= sales_base_url('new_order.php') ?>" class="sp-nav-item <?= $currentPage === 'new_order' ? 'active' : '' ?>">
                <i class="fas fa-cart-plus"></i>
                <span>New Order</span>
            </a>
            <a href="<?= sales_base_url('orders.php') ?>" class="sp-nav-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>My Orders</span>
            </a>
            <a href="<?= sales_base_url('returns.php') ?>" class="sp-nav-item <?= $currentPage === 'returns' ? 'active' : '' ?>">
                <i class="fas fa-undo-alt"></i>
                <span>Returns</span>
            </a>
            <a href="<?= sales_base_url('collect_payment.php') ?>" class="sp-nav-item <?= $currentPage === 'collect_payment' ? 'active' : '' ?>">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Collect Payment</span>
            </a>
            <a href="<?= sales_base_url('outstanding.php') ?>" class="sp-nav-item <?= $currentPage === 'outstanding' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-circle"></i>
                <span>Outstanding</span>
            </a>
            <a href="<?= sales_base_url('gifts.php') ?>" class="sp-nav-item <?= $currentPage === 'gifts' ? 'active' : '' ?>">
                <i class="fas fa-gift"></i>
                <span>Gifts & Promos</span>
            </a>
            <a href="<?= sales_base_url('visit_schedule.php') ?>" class="sp-nav-item <?= $currentPage === 'visit_schedule' ? 'active' : '' ?>">
                <i class="fas fa-route"></i>
                <span>Visit Schedule</span>
            </a>
            <a href="<?= sales_base_url('attendance.php') ?>" class="sp-nav-item <?= $currentPage === 'attendance' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="<?= sales_base_url('leaves.php') ?>" class="sp-nav-item <?= $currentPage === 'leaves' ? 'active' : '' ?>">
                <i class="fas fa-calendar-times"></i>
                <span>Leaves</span>
            </a>
            <a href="<?= sales_base_url('expenses.php') ?>" class="sp-nav-item <?= $currentPage === 'expenses' ? 'active' : '' ?>">
                <i class="fas fa-receipt"></i>
                <span>Expenses</span>
            </a>
            <a href="<?= sales_base_url('profile.php') ?>" class="sp-nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <i class="fas fa-user-cog"></i>
                <span>My Profile</span>
            </a>
        </nav>

        <div class="sp-sidebar-footer">
            <a href="<?= sales_base_url('logout.php') ?>" class="sp-nav-item sp-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="sp-main" id="spMain">
        <!-- Top Bar -->
        <header class="sp-topbar">
            <button class="sp-menu-toggle" id="spMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="sp-topbar-title">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <div class="sp-topbar-actions">
                <a href="<?= sales_base_url('notifications.php') ?>" class="sp-topbar-icon" id="spNotifBtn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="sp-notif-badge" id="spNotifBadge" style="display:none;">0</span>
                </a>
                <span class="sp-district-badge">
                    <i class="fas fa-map-pin"></i> <?= htmlspecialchars($exec['district']) ?>
                </span>
            </div>
        </header>

        <div class="sp-content">
