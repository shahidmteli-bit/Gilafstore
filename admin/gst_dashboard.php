<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/gst_calculator.php';

// Check if admin is logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('Location: admin_login.php');
    exit();
}

$adminId = $_SESSION['user']['id'];

// Initialize GST Calculator
$gstCalculator = new GSTCalculator($conn);

// Get date range
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

// Get dashboard data
$gstSummary = $gstCalculator->getGSTSummary($fromDate, $toDate);
$stateWiseGST = $gstCalculator->getStateWiseGST($fromDate, $toDate);
$productWiseGST = $gstCalculator->getProductWiseGST($fromDate, $toDate, 10);

// Get monthly GST trends
$monthlyTrendsQuery = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          COUNT(*) as order_count,
                          SUM(taxable_amount) as taxable_amount,
                          SUM(total_gst_amount) as gst_amount
                        FROM gst_orders 
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month ASC";
$monthlyTrends = $conn->query($monthlyTrendsQuery)->fetch_all(MYSQLI_ASSOC);

// Get daily GST for last 30 days
$dailyTrendsQuery = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as order_count,
                        SUM(total_gst_amount) as gst_amount
                     FROM gst_orders 
                     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     GROUP BY DATE(created_at)
                     ORDER BY date ASC";
$dailyTrends = $conn->query($dailyTrendsQuery)->fetch_all(MYSQLI_ASSOC);

// Get GST configuration summary
$configQuery = "SELECT 
                  entity_type,
                  COUNT(*) as count,
                  AVG(gst_slab) as avg_gst_slab
                FROM gst_configuration 
                WHERE status = 'active'
                GROUP BY entity_type";
$configSummary = $conn->query($configQuery)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'GST Dashboard';
include '../includes/admin_header.php';
?>

<!-- Premium GST Dashboard -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">GST Dashboard</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#dateRangeModal">
                        <i class="fas fa-calendar me-2"></i>Date Range
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Display -->
    <div class="alert alert-info d-flex align-items-center mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <div>
            Showing data from <strong><?php echo date('d M Y', strtotime($fromDate)); ?></strong> to 
            <strong><?php echo date('d M Y', strtotime($toDate)); ?></strong>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo number_format($gstSummary['total_orders'] ?? 0); ?></h4>
                            <p class="mb-0">Total Orders</p>
                            <small class="opacity-75">
                                <?php echo date('M j', strtotime($fromDate)); ?> - <?php echo date('M j', strtotime($toDate)); ?>
                            </small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">₹<?php echo number_format($gstSummary['total_taxable_amount'] ?? 0, 2); ?></h4>
                            <p class="mb-0">Taxable Amount</p>
                            <small class="opacity-75">
                                <?php echo number_format(($gstSummary['total_taxable_amount'] ?? 0) / max($gstSummary['total_orders'] ?? 1, 1), 2); ?> avg/order
                            </small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">₹<?php echo number_format($gstSummary['total_gst_amount'] ?? 0, 2); ?></h4>
                            <p class="mb-0">Total GST Collected</p>
                            <small class="opacity-75">
                                <?php 
                                $taxableAmount = $gstSummary['total_taxable_amount'] ?? 0;
                                $gstAmount = $gstSummary['total_gst_amount'] ?? 0;
                                $effectiveRate = $taxableAmount > 0 ? ($gstAmount / $taxableAmount) * 100 : 0;
                                echo number_format($effectiveRate, 2); ?>% effective rate
                            </small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-coins fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">₹<?php echo number_format($gstSummary['total_grand_total'] ?? 0, 2); ?></h4>
                            <p class="mb-0">Grand Total</p>
                            <small class="opacity-75">
                                Including GST
                            </small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-receipt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Type Distribution -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Type Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h3 class="text-success"><?php echo number_format($gstSummary['intra_state_orders'] ?? 0); ?></h3>
                                <p class="mb-0">Intra-State Orders</p>
                                <div class="progress mb-2" style="height: 8px;">
                                    <?php 
                                    $totalOrders = max($gstSummary['total_orders'] ?? 1, 1);
                                    $intraPercentage = ($gstSummary['intra_state_orders'] ?? 0) / $totalOrders * 100;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $intraPercentage; ?>%"></div>
                                </div>
                                <small><?php echo number_format($intraPercentage, 1); ?>%</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h3 class="text-info"><?php echo number_format($gstSummary['inter_state_orders'] ?? 0); ?></h3>
                                <p class="mb-0">Inter-State Orders</p>
                                <div class="progress mb-2" style="height: 8px;">
                                    <?php 
                                    $interPercentage = ($gstSummary['inter_state_orders'] ?? 0) / $totalOrders * 100;
                                    ?>
                                    <div class="progress-bar bg-info" style="width: <?php echo $interPercentage; ?>%"></div>
                                </div>
                                <small><?php echo number_format($interPercentage, 1); ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">GST Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <div class="text-center">
                                <h4 class="text-primary">₹<?php echo number_format($gstSummary['total_cgst_amount'] ?? 0, 2); ?></h4>
                                <p class="mb-0">CGST</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h4 class="text-success">₹<?php echo number_format($gstSummary['total_sgst_amount'] ?? 0, 2); ?></h4>
                                <p class="mb-0">SGST</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h4 class="text-warning">₹<?php echo number_format($gstSummary['total_igst_amount'] ?? 0, 2); ?></h4>
                                <p class="mb-0">IGST</p>
                            </div>
                        </div>
                    </div>
                    <?php if ($gstSummary['total_cess_amount'] > 0): ?>
                        <hr>
                        <div class="text-center">
                            <h5 class="text-danger">₹<?php echo number_format($gstSummary['total_cess_amount'], 2); ?></h5>
                            <p class="mb-0">CESS Amount</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Monthly GST Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyGSTChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">State-wise Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="stateGSTChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products & States Tables -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Products by GST</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">GST Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($productWiseGST, 0, 5) as $product): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-end"><?php echo number_format($product['total_quantity']); ?></td>
                                        <td class="text-end">₹<?php echo number_format($product['gst_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top States by GST</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>State</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end">GST Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($stateWiseGST, 0, 5) as $state): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($state['customer_state']); ?></strong>
                                        </td>
                                        <td class="text-end"><?php echo number_format($state['order_count']); ?></td>
                                        <td class="text-end">₹<?php echo number_format($state['gst_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Trends Chart -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Daily GST Collection (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyGSTChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Date Range</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly GST Chart
const monthlyCtx = document.getElementById('monthlyGSTChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthlyTrends); ?>;
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: monthlyData.map(d => new Date(d.month + '-01').toLocaleDateString('en-US', { month: 'short', year: 'numeric' })),
        datasets: [{
            label: 'GST Amount',
            data: monthlyData.map(d => parseFloat(d.gst_amount)),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.4
        }, {
            label: 'Taxable Amount',
            data: monthlyData.map(d => parseFloat(d.taxable_amount)),
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString('en-IN');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' + value.toLocaleString('en-IN');
                    }
                }
            }
        }
    }
});

// State GST Chart
const stateCtx = document.getElementById('stateGSTChart').getContext('2d');
const stateData = <?php echo json_encode(array_slice($stateWiseGST, 0, 8)); ?>;
new Chart(stateCtx, {
    type: 'doughnut',
    data: {
        labels: stateData.map(d => d.customer_state),
        datasets: [{
            data: stateData.map(d => parseFloat(d.gst_amount)),
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40',
                '#FF6384',
                '#C9CBCF'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ₹' + context.parsed.toLocaleString('en-IN') + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Daily GST Chart
const dailyCtx = document.getElementById('dailyGSTChart').getContext('2d');
const dailyData = <?php echo json_encode($dailyTrends); ?>;
new Chart(dailyCtx, {
    type: 'bar',
    data: {
        labels: dailyData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
        datasets: [{
            label: 'GST Amount',
            data: dailyData.map(d => parseFloat(d.gst_amount)),
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'GST: ₹' + context.parsed.y.toLocaleString('en-IN');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' + value.toLocaleString('en-IN');
                    }
                }
            }
        }
    }
});

// Refresh Dashboard
function refreshDashboard() {
    location.reload();
}

// Auto-refresh every 5 minutes
setInterval(refreshDashboard, 300000);
</script>
