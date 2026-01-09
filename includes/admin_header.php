<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Admin — Gilaf Store';
$adminPage = $adminPage ?? '';
$userName = $_SESSION['user']['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" />
    <link rel="stylesheet" href="<?= asset_url('css/admin-premium.css'); ?>" />
  </head>
  <body>
    <div class="admin-layout d-flex">
      <aside class="admin-sidebar d-flex flex-column">
        <div class="brand d-flex align-items-center gap-2">
          <i class="fas fa-cube"></i>
          <span>Gilaf Admin</span>
        </div>
        <nav class="nav flex-column">
          <a class="nav-link <?= $adminPage === 'dashboard' ? 'active' : ''; ?>" href="<?= base_url('admin/index.php'); ?>"><i class="fas fa-chart-pie"></i>Dashboard</a>
          
          <!-- Catalog Section - Collapsible -->
          <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#catalogMenu" role="button" aria-expanded="false" aria-controls="catalogMenu">
            <span><i class="fas fa-boxes"></i>Catalog</span>
            <i class="fas fa-chevron-down"></i>
          </a>
          <div class="collapse" id="catalogMenu">
            <div class="nav-submenu">
              <a class="nav-link <?= $adminPage === 'products' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_products.php'); ?>"><i class="fas fa-box"></i>Products</a>
              <a class="nav-link <?= $adminPage === 'product_sections' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_product_sections.php'); ?>"><i class="fas fa-list-alt"></i>Product Sections</a>
              <a class="nav-link <?= $adminPage === 'categories' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_categories.php'); ?>"><i class="fas fa-tags"></i>Categories</a>
              <a class="nav-link <?= $adminPage === 'discounts' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_discounts.php'); ?>"><i class="fas fa-percent"></i>Product Discounts</a>
              <a class="nav-link <?= $adminPage === 'promo_codes' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_promo_codes.php'); ?>"><i class="fas fa-ticket-alt"></i>Promo Codes</a>
              <a class="nav-link <?= $adminPage === 'batches' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_batches.php'); ?>"><i class="fas fa-barcode"></i>Batch Codes</a>
              <a class="nav-link <?= $adminPage === 'batch_alerts' ? 'active' : ''; ?>" href="<?= base_url('admin/batch_alerts_dashboard.php'); ?>"><i class="fas fa-bell"></i>Batch Alerts</a>
            </div>
          </div>
          
          <a class="nav-link <?= $adminPage === 'shipping' ? 'active' : ''; ?>" href="<?= base_url('admin/shipping_settings.php'); ?>"><i class="fas fa-shipping-fast"></i>Shipping</a>
          <a class="nav-link <?= $adminPage === 'policies' ? 'active' : ''; ?>" href="<?= base_url('admin/policies_compliances.php'); ?>"><i class="fas fa-file-contract"></i>Policies</a>
          <a class="nav-link <?= $adminPage === 'content' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_content.php'); ?>"><i class="fas fa-file-alt"></i>Content Pages</a>
          <a class="nav-link <?= $adminPage === 'optimize_images' ? 'active' : ''; ?>" href="<?= base_url('admin/optimize_images.php'); ?>"><i class="fas fa-compress-alt"></i>Image Optimization</a>
          <a class="nav-link <?= $adminPage === 'applications' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_applications.php'); ?>"><i class="fas fa-handshake"></i>Applications</a>
          <a class="nav-link <?= $adminPage === 'orders' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_orders.php'); ?>"><i class="fas fa-receipt"></i>Orders</a>

          <!-- Payments - Business QR / Manual -->
          <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#paymentsMenu" role="button" aria-expanded="false" aria-controls="paymentsMenu">
            <span><i class="fas fa-credit-card"></i>Payments</span>
            <i class="fas fa-chevron-down"></i>
          </a>
          <div class="collapse" id="paymentsMenu">
            <div class="nav-submenu">
              <a class="nav-link <?= $adminPage === 'payments' ? 'active' : ''; ?>" href="<?= base_url('admin/payment_verification.php'); ?>"><i class="fas fa-qrcode"></i>Business QR Payments</a>
            </div>
          </div>

          <!-- GST Tax Compliance Module - Collapsible -->
          <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#gstComplianceMenu" role="button" aria-expanded="false" aria-controls="gstComplianceMenu">
            <span><i class="fas fa-file-invoice-dollar"></i>GST Compliance</span>
            <i class="fas fa-chevron-down"></i>
          </a>
          <div class="collapse" id="gstComplianceMenu">
            <div class="nav-submenu">
              <a class="nav-link <?= $adminPage === 'gst_dashboard' ? 'active' : ''; ?>" href="<?= base_url('admin/gst_dashboard.php'); ?>"><i class="fas fa-chart-line"></i>GST Dashboard</a>
              <a class="nav-link <?= $adminPage === 'gst_configuration' ? 'active' : ''; ?>" href="<?= base_url('admin/gst_configuration.php'); ?>"><i class="fas fa-cog"></i>GST Configuration</a>
              <a class="nav-link <?= $adminPage === 'gst_orders' ? 'active' : ''; ?>" href="<?= base_url('admin/gst_orders.php'); ?>"><i class="fas fa-file-invoice-dollar"></i>GST Orders</a>
              <a class="nav-link <?= $adminPage === 'gst_reports' ? 'active' : ''; ?>" href="<?= base_url('admin/gst_reports.php'); ?>"><i class="fas fa-file-alt"></i>GST Reports</a>
              <a class="nav-link <?= $adminPage === 'gst_audit' ? 'active' : ''; ?>" href="<?= base_url('admin/gst_audit.php'); ?>"><i class="fas fa-history"></i>GST Audit Trail</a>
            </div>
          </div>
          
          <!-- Users & Support Section - Collapsible -->
          <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#usersSupportMenu" role="button" aria-expanded="false" aria-controls="usersSupportMenu">
            <span><i class="fas fa-users-cog"></i>Users & Support</span>
            <i class="fas fa-chevron-down"></i>
          </a>
          <div class="collapse" id="usersSupportMenu">
            <div class="nav-submenu">
              <a class="nav-link <?= $adminPage === 'users' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_users.php'); ?>"><i class="fas fa-users"></i>Users</a>
              <a class="nav-link <?= $adminPage === 'support' ? 'active' : ''; ?>" href="<?= base_url('admin/support_tickets.php'); ?>"><i class="fas fa-headset"></i>Support Center</a>
              <a class="nav-link <?= $adminPage === 'support_agents' ? 'active' : ''; ?>" href="<?= base_url('admin/manage_support_agents.php'); ?>"><i class="fas fa-user-headset"></i>Support Agents</a>
            </div>
          </div>
          
          <a class="nav-link <?= $adminPage === 'analytics' ? 'active' : ''; ?>" href="<?= base_url('admin/website_performance.php'); ?>"><i class="fas fa-chart-line"></i>Analytics & Insights</a>
          <a class="nav-link <?= $adminPage === 'health' ? 'active' : ''; ?>" href="<?= base_url('admin/health_dashboard.php'); ?>"><i class="fas fa-heartbeat"></i>Website Health & Cache</a>
          <a class="nav-link <?= $adminPage === 'suggestions' ? 'active' : ''; ?>" href="<?= base_url('admin/suggestions_center.php'); ?>"><i class="fas fa-lightbulb"></i>Idea & Suggestion Center</a>
          <a class="nav-link <?= $adminPage === 'logs' ? 'active' : ''; ?>" href="<?= base_url('admin/error_logs.php'); ?>"><i class="fas fa-bug"></i>Error Logs</a>
          <a class="nav-link" href="<?= base_url('index.php'); ?>"><i class="fas fa-store"></i>View Store</a>
        </nav>
        <div class="mt-auto">
          <a class="nav-link" href="<?= base_url('user/logout.php'); ?>"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
      </aside>
      <div class="admin-content d-flex flex-column w-100">
        <header class="admin-topbar d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0 fw-semibold text-primary">Welcome back</h5>
            <small class="text-muted">Manage your store efficiently</small>
          </div>
          <div class="admin-user">
            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
              <?= strtoupper(substr($userName, 0, 1)); ?>
            </div>
            <div>
              <strong><?= htmlspecialchars($userName); ?></strong>
              <small class="d-block text-muted">Administrator</small>
            </div>
          </div>
        </header>
        <?php if (function_exists('display_flash')) { display_flash(); } ?>
        <main class="p-4 flex-grow-1">
