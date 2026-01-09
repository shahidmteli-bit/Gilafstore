<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Admin Dashboard — Gilaf Store';
$adminPage = 'dashboard';
$stats = admin_get_stats();
$recentOrders = admin_get_recent_orders();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid px-4">
    <!-- Stats Grid - Premium Glass Cards -->
    <div class="stats-grid fade-in-up">
      <div class="stat-card">
        <div class="stat-card-header">
          <div>
            <div class="stat-card-label">Total Revenue</div>
            <div class="stat-card-value">₹<?= number_format($stats['revenue'], 2); ?></div>
            <div class="stat-card-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>12.5% from last month</span>
            </div>
          </div>
          <div class="stat-card-icon primary">
            <i class="fas fa-dollar-sign"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <div>
            <div class="stat-card-label">Total Users</div>
            <div class="stat-card-value"><?= (int)$stats['users']; ?></div>
            <div class="stat-card-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>8.2% from last month</span>
            </div>
          </div>
          <div class="stat-card-icon success">
            <i class="fas fa-users"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <div>
            <div class="stat-card-label">Total Products</div>
            <div class="stat-card-value"><?= (int)$stats['products']; ?></div>
            <div class="stat-card-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>5 new this week</span>
            </div>
          </div>
          <div class="stat-card-icon warning">
            <i class="fas fa-box"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <div>
            <div class="stat-card-label">Total Orders</div>
            <div class="stat-card-value"><?= (int)$stats['orders']; ?></div>
            <div class="stat-card-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>15.3% from last month</span>
            </div>
          </div>
          <div class="stat-card-icon info">
            <i class="fas fa-shopping-cart"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-header">
          <div>
            <div class="stat-card-label">Payment Received</div>
            <div class="stat-card-value">₹<?= number_format($stats['payment_received'], 2); ?></div>
            <div class="stat-card-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>From completed orders</span>
            </div>
          </div>
          <div class="stat-card-icon success">
            <i class="fas fa-money-bill-wave"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts and Tables Row -->
    <div class="row g-4 mt-1">
      <div class="col-xl-8">
        <div class="glass-card fade-in-up">
          <div class="glass-card-header">
            <h3 class="glass-card-title"><i class="fas fa-chart-line"></i> Sales Overview</h3>
            <span class="glass-card-badge primary">Real-time</span>
          </div>
          <div>
            <canvas id="salesChart" height="100"></canvas>
          </div>
        </div>
      </div>
      
      <div class="col-xl-4">
        <div class="glass-card fade-in-up">
          <div class="glass-card-header">
            <h3 class="glass-card-title"><i class="fas fa-clock"></i> Recent Orders</h3>
            <span class="glass-card-badge success"><?= count($recentOrders); ?> Orders</span>
          </div>
          <div>
            <?php if ($recentOrders): ?>
              <div class="table-responsive">
                <table class="table mb-0">
                  <thead>
                    <tr>
                      <th>OID</th>
                      <th>User</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                      <tr>
                        <td><strong>#<?= (int)$order['id']; ?></strong></td>
                        <td><?= htmlspecialchars($order['customer'] ?? 'Guest'); ?></td>
                        <td><strong>₹<?= number_format($order['total_amount'], 2); ?></strong></td>
                        <td>
                          <?php
                          $statusColors = [
                            'pending' => 'warning',
                            'accepted' => 'info',
                            'shipped' => 'primary',
                            'delivered' => 'success',
                            'cancelled' => 'danger'
                          ];
                          $orderStatus = $order['status'] ?? 'pending';
                          $color = $statusColors[$orderStatus] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?= $color; ?> status-badge"><?= ucfirst($orderStatus); ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No recent orders yet.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row g-4 mt-1">
      <div class="col-12">
        <div class="glass-card fade-in-up">
          <div class="glass-card-header">
            <h3 class="glass-card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
          </div>
          <div class="d-flex gap-3 flex-wrap">
            <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-primary">
              <i class="fas fa-plus-circle"></i> Add Product
            </a>
            <a href="<?= base_url('admin/manage_orders.php'); ?>" class="btn btn-success">
              <i class="fas fa-truck"></i> View Orders
            </a>
            <a href="<?= base_url('admin/manage_categories.php'); ?>" class="btn btn-warning">
              <i class="fas fa-tags"></i> Manage Categories
            </a>
            <a href="<?= base_url('admin/manage_batches.php'); ?>" class="btn btn-info">
              <i class="fas fa-barcode"></i> Batch Codes
            </a>
            <a href="<?= base_url('admin/shipping_management.php'); ?>" class="btn btn-secondary">
              <i class="fas fa-shipping-fast"></i> Shipping
            </a>
            <a href="<?= base_url('admin/manage_applications.php'); ?>" class="btn btn-primary">
              <i class="fas fa-handshake"></i> Applications
            </a>
            <a href="<?= base_url('index.php'); ?>" class="btn btn-outline-primary" target="_blank">
              <i class="fas fa-external-link-alt"></i> View Store
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
/* Equal width status badges in Recent Orders */
.status-badge {
  min-width: 100px;
  display: inline-block;
  text-align: center;
  font-weight: 600;
  padding: 6px 12px;
}

/* Recent Orders table header alignment */
.table thead th {
  vertical-align: middle;
  padding: 12px 8px;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #dee2e6;
}

.table tbody td {
  vertical-align: middle;
  padding: 12px 8px;
}

/* Specific column widths for better alignment */
.table thead th:nth-child(1),
.table tbody td:nth-child(1) {
  width: 15%;
}

.table thead th:nth-child(2),
.table tbody td:nth-child(2) {
  width: 30%;
}

.table thead th:nth-child(3),
.table tbody td:nth-child(3) {
  width: 20%;
}

.table thead th:nth-child(4),
.table tbody td:nth-child(4) {
  width: 35%;
  text-align: center;
}
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;
    
    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(26, 60, 52, 0.3)');
    gradient.addColorStop(1, 'rgba(26, 60, 52, 0.01)');
    
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [
          {
            label: 'Revenue (₹)',
            data: [1200, 1900, 1500, 2200, 2000, 2500, 2800, 2400, 2900, 3200, 3500, 3800],
            borderColor: '#1A3C34',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            backgroundColor: gradient,
            pointBackgroundColor: '#C5A059',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { 
            display: true,
            position: 'top',
            labels: {
              usePointStyle: true,
              padding: 20,
              font: {
                size: 13,
                weight: '600'
              }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(26, 60, 52, 0.9)',
            padding: 12,
            titleFont: { size: 14, weight: '600' },
            bodyFont: { size: 13 },
            borderColor: '#C5A059',
            borderWidth: 1
          }
        },
        scales: {
          y: { 
            beginAtZero: true,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)',
              drawBorder: false
            },
            ticks: {
              callback: function(value) {
                return '₹' + value;
              },
              font: { size: 12 }
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              font: { size: 12 }
            }
          }
        },
      },
    });
  });
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
