<?php
/**
 * WhatsApp CRM Analytics Dashboard
 * Real-time metrics and performance tracking
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/crm_engine.php';

require_admin();

$crm = CRMEngine::getInstance();
$stats = $crm->getStats();
$pageTitle = 'CRM Analytics — Dashboard';
$adminPage = 'crm_analytics';

global $pdo;

// Date range
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['to'] ?? date('Y-m-d');

// Fetch analytics data
$notificationStats = $pdo->prepare("
    SELECT event_type, status, COUNT(*) as count
    FROM crm_order_notifications
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY event_type, status
");
$notificationStats->execute([$dateFrom, $dateTo]);
$notifData = $notificationStats->fetchAll(PDO::FETCH_ASSOC);

// Daily message volume
$dailyVolume = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM crm_order_notifications
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$dailyVolume->execute([$dateFrom, $dateTo]);
$volumeData = $dailyVolume->fetchAll(PDO::FETCH_ASSOC);

// Cart recovery stats
$cartStats = $pdo->query("
    SELECT 
        COUNT(*) as total_abandoned,
        SUM(CASE WHEN recovery_status = 'recovered' THEN 1 ELSE 0 END) as recovered,
        SUM(CASE WHEN recovery_status = 'recovered' THEN cart_total ELSE 0 END) as recovered_revenue,
        SUM(cart_total) as total_value
    FROM crm_abandoned_carts
    WHERE abandoned_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

// OTP stats
$otpStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM crm_whatsapp_otp
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

// Top notification types
$topTypes = $pdo->prepare("
    SELECT event_type, COUNT(*) as count
    FROM crm_order_notifications
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY event_type
    ORDER BY count DESC
    LIMIT 5
");
$topTypes->execute([$dateFrom, $dateTo]);
$topTypesData = $topTypes->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px; }
.stat-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.stat-card .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 16px; }
.stat-card .stat-value { font-size: 2rem; font-weight: 700; color: #1f2937; line-height: 1.1; }
.stat-card .stat-label { color: #6b7280; font-size: 0.9rem; margin-top: 4px; }
.stat-card .stat-change { font-size: 0.85rem; margin-top: 8px; }
.stat-card .stat-change.positive { color: #10b981; }
.stat-card .stat-change.negative { color: #ef4444; }

.chart-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 24px; }
.chart-card h3 { font-size: 1.1rem; font-weight: 600; color: #1f2937; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.chart-card h3 i { color: #25D366; }

.date-filter { background: #fff; border-radius: 12px; padding: 16px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 24px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.date-filter label { font-weight: 500; color: #6b7280; }
.date-filter input { padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; }
.date-filter button { padding: 8px 20px; background: #1A3C34; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }

.mini-table { width: 100%; }
.mini-table th { text-align: left; padding: 10px; color: #6b7280; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
.mini-table td { padding: 12px 10px; border-bottom: 1px solid #f3f4f6; }
.mini-table tr:last-child td { border-bottom: none; }

.progress-bar { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
.progress-bar .progress { height: 100%; background: linear-gradient(90deg, #25D366, #128C7E); border-radius: 4px; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:linear-gradient(135deg,#25D366,#128C7E);">
                <i class="fas fa-chart-line text-white" style="font-size:20px;"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold">CRM Analytics</h4>
                <small class="text-muted">WhatsApp engagement metrics</small>
            </div>
        </div>
        <a href="<?= base_url('admin/crm_integration.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>

    <form class="date-filter" method="get">
        <label>From:</label>
        <input type="date" name="from" value="<?= $dateFrom ?>">
        <label>To:</label>
        <input type="date" name="to" value="<?= $dateTo ?>">
        <button type="submit"><i class="fas fa-filter"></i> Apply</button>
    </form>

    <div class="analytics-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-paper-plane"></i></div>
            <div class="stat-value"><?= number_format($stats['notifications_sent_today'] ?? 0) ?></div>
            <div class="stat-label">Messages Sent Today</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= number_format($stats['total_synced_customers'] ?? 0) ?></div>
            <div class="stat-label">Synced Customers</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?= number_format($cartStats['recovered'] ?? 0) ?></div>
            <div class="stat-label">Carts Recovered (30d)</div>
            <div class="stat-change positive">₹<?= number_format($cartStats['recovered_revenue'] ?? 0) ?> revenue</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-key"></i></div>
            <div class="stat-value"><?= number_format($stats['otps_sent_today'] ?? 0) ?></div>
            <div class="stat-label">OTPs Sent Today</div>
            <div class="stat-change"><?= $otpStats['verified'] ?? 0 ?> verified (7d)</div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="chart-card">
                <h3><i class="fas fa-chart-area"></i> Message Volume</h3>
                <canvas id="volumeChart" height="100"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Message Types</h3>
                <canvas id="typesChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="chart-card">
                <h3><i class="fas fa-shopping-cart"></i> Cart Recovery</h3>
                <div class="row text-center mb-4">
                    <div class="col-4">
                        <div style="font-size:1.8rem;font-weight:700;color:#ef4444;"><?= $cartStats['total_abandoned'] ?? 0 ?></div>
                        <div class="text-muted small">Abandoned</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.8rem;font-weight:700;color:#10b981;"><?= $cartStats['recovered'] ?? 0 ?></div>
                        <div class="text-muted small">Recovered</div>
                    </div>
                    <div class="col-4">
                        <?php $rate = ($cartStats['total_abandoned'] > 0) ? round(($cartStats['recovered'] / $cartStats['total_abandoned']) * 100, 1) : 0; ?>
                        <div style="font-size:1.8rem;font-weight:700;color:#2563eb;"><?= $rate ?>%</div>
                        <div class="text-muted small">Recovery Rate</div>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress" style="width:<?= $rate ?>%;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <h3><i class="fas fa-fire"></i> Top Notification Types</h3>
                <table class="mini-table">
                    <thead><tr><th>Type</th><th>Count</th><th>%</th></tr></thead>
                    <tbody>
                    <?php 
                    $totalNotifs = array_sum(array_column($topTypesData, 'count'));
                    foreach ($topTypesData as $t): 
                        $pct = $totalNotifs > 0 ? round(($t['count'] / $totalNotifs) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><strong><?= ucfirst(str_replace('_', ' ', $t['event_type'])) ?></strong></td>
                            <td><?= number_format($t['count']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress-bar" style="width:80px;"><div class="progress" style="width:<?= $pct ?>%;"></div></div>
                                    <?= $pct ?>%
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <h3><i class="fas fa-clock"></i> System Health</h3>
        <div class="row">
            <div class="col-md-3 text-center">
                <div style="font-size:2rem;font-weight:700;color:#10b981;"><?= $stats['webhook_success_rate'] ?? 100 ?>%</div>
                <div class="text-muted">Webhook Success</div>
            </div>
            <div class="col-md-3 text-center">
                <div style="font-size:2rem;font-weight:700;color:#f59e0b;"><?= $stats['pending_events'] ?? 0 ?></div>
                <div class="text-muted">Pending Events</div>
            </div>
            <div class="col-md-3 text-center">
                <div style="font-size:2rem;font-weight:700;color:#ef4444;"><?= $stats['failed_events'] ?? 0 ?></div>
                <div class="text-muted">Failed Events</div>
            </div>
            <div class="col-md-3 text-center">
                <div style="font-size:2rem;font-weight:700;color:#6366f1;"><?= $stats['active_abandoned_carts'] ?? 0 ?></div>
                <div class="text-muted">Active Carts</div>
            </div>
        </div>
    </div>
</div>

<script>
// Volume Chart
const volumeCtx = document.getElementById('volumeChart').getContext('2d');
new Chart(volumeCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($volumeData, 'date')) ?>,
        datasets: [{
            label: 'Messages',
            data: <?= json_encode(array_column($volumeData, 'count')) ?>,
            borderColor: '#25D366',
            backgroundColor: 'rgba(37, 211, 102, 0.1)',
            fill: true,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true },
            x: { grid: { display: false } }
        }
    }
});

// Types Chart
const typesCtx = document.getElementById('typesChart').getContext('2d');
new Chart(typesCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($t) { return ucfirst(str_replace('_', ' ', $t['event_type'])); }, $topTypesData)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($topTypesData, 'count')) ?>,
            backgroundColor: ['#25D366', '#128C7E', '#075E54', '#34B7F1', '#ECE5DD'],
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
