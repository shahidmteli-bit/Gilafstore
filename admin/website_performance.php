<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Analytics & Insights';
$adminPage = 'analytics';

// Date range filters
$dateRange = $_GET['range'] ?? 'last_7_days';
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-7 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');

// Set date range based on selection
switch ($dateRange) {
    case 'today':
        $fromDate = $toDate = date('Y-m-d');
        break;
    case 'yesterday':
        $fromDate = $toDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'last_7_days':
        $fromDate = date('Y-m-d', strtotime('-7 days'));
        $toDate = date('Y-m-d');
        break;
    case 'last_30_days':
        $fromDate = date('Y-m-d', strtotime('-30 days'));
        $toDate = date('Y-m-d');
        break;
    case 'this_month':
        $fromDate = date('Y-m-01');
        $toDate = date('Y-m-d');
        break;
    case 'last_month':
        $fromDate = date('Y-m-01', strtotime('first day of last month'));
        $toDate = date('Y-m-t', strtotime('last day of last month'));
        break;
}

// Get visitor statistics
$visitorQuery = "SELECT 
    COUNT(DISTINCT visitor_id) as total_visitors,
    COUNT(DISTINCT CASE WHEN total_visits = 1 THEN visitor_id END) as new_visitors,
    COUNT(DISTINCT CASE WHEN total_visits > 1 THEN visitor_id END) as returning_visitors
FROM analytics_visitors
WHERE DATE(first_visit_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($visitorQuery);
if ($stmt === false) {
    error_log('Analytics Error - Visitor Query: ' . $conn->error);
    $visitorStats = ['total_visitors' => 0, 'new_visitors' => 0, 'returning_visitors' => 0];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $visitorStats = $result ? $result->fetch_assoc() : ['total_visitors' => 0, 'new_visitors' => 0, 'returning_visitors' => 0];
    $stmt->close();
}

// Get page view statistics
$pageViewQuery = "SELECT 
    COUNT(*) as total_page_views,
    COUNT(DISTINCT visitor_id) as unique_viewers,
    AVG(visit_duration) as avg_duration,
    SUM(clicks_count) as total_clicks
FROM analytics_page_views
WHERE DATE(viewed_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($pageViewQuery);
if ($stmt === false) {
    error_log('Analytics Error - Page View Query: ' . $conn->error);
    $pageViewStats = ['total_page_views' => 0, 'unique_viewers' => 0, 'avg_duration' => 0, 'total_clicks' => 0];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $pageViewStats = $result ? $result->fetch_assoc() : ['total_page_views' => 0, 'unique_viewers' => 0, 'avg_duration' => 0, 'total_clicks' => 0];
    $stmt->close();
}

// Get product engagement statistics
$productQuery = "SELECT 
    COUNT(CASE WHEN event_type = 'view' THEN 1 END) as product_views,
    COUNT(CASE WHEN event_type = 'click' THEN 1 END) as product_clicks,
    COUNT(CASE WHEN event_type = 'add_to_cart' THEN 1 END) as add_to_cart,
    COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) as purchases,
    COUNT(DISTINCT product_id) as unique_products_viewed
FROM analytics_product_events
WHERE DATE(event_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($productQuery);
if ($stmt === false) {
    error_log('Analytics Error - Product Query: ' . $conn->error);
    $productStats = ['product_views' => 0, 'product_clicks' => 0, 'add_to_cart' => 0, 'purchases' => 0, 'unique_products_viewed' => 0];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $productStats = $result ? $result->fetch_assoc() : ['product_views' => 0, 'product_clicks' => 0, 'add_to_cart' => 0, 'purchases' => 0, 'unique_products_viewed' => 0];
    $stmt->close();
}

// Get revenue statistics (only count successfully paid orders)
$revenueQuery = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value
FROM orders
WHERE DATE(created_at) BETWEEN ? AND ?
AND payment_status = 'completed'";

$stmt = $conn->prepare($revenueQuery);
if ($stmt === false) {
    error_log('Analytics Error - Revenue Query: ' . $conn->error);
    $revenueStats = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $revenueStats = $result ? $result->fetch_assoc() : ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
    $stmt->close();
}

// Calculate conversion rate
$conversionRate = $visitorStats['total_visitors'] > 0 
    ? ($revenueStats['total_orders'] / $visitorStats['total_visitors']) * 100 
    : 0;

// Get top products by clicks
$topProductsQuery = "SELECT 
    p.id, p.name, p.price,
    COUNT(CASE WHEN ape.event_type = 'click' THEN 1 END) as click_count,
    COUNT(CASE WHEN ape.event_type = 'view' THEN 1 END) as view_count,
    COUNT(CASE WHEN ape.event_type = 'add_to_cart' THEN 1 END) as cart_count,
    COUNT(CASE WHEN ape.event_type = 'purchase' THEN 1 END) as purchase_count
FROM analytics_product_events ape
JOIN products p ON ape.product_id = p.id
WHERE DATE(ape.event_at) BETWEEN ? AND ?
GROUP BY p.id, p.name, p.price
ORDER BY click_count DESC
LIMIT 10";

$stmt = $conn->prepare($topProductsQuery);
if ($stmt === false) {
    error_log('Analytics Error - Top Products Query: ' . $conn->error);
    $topProducts = [];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $topProducts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Get geographic distribution
$geoQuery = "SELECT 
    country,
    COUNT(DISTINCT visitor_id) as visitor_count,
    COUNT(*) as total_visits
FROM analytics_visitors
WHERE DATE(first_visit_at) BETWEEN ? AND ?
AND country IS NOT NULL
GROUP BY country
ORDER BY visitor_count DESC
LIMIT 10";

$stmt = $conn->prepare($geoQuery);
if ($stmt === false) {
    error_log('Analytics Error - Geographic Query: ' . $conn->error);
    $geoData = [];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $geoData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Get daily trend data
$trendQuery = "SELECT 
    DATE(viewed_at) as date,
    COUNT(DISTINCT visitor_id) as visitors,
    COUNT(*) as page_views
FROM analytics_page_views
WHERE DATE(viewed_at) BETWEEN ? AND ?
GROUP BY DATE(viewed_at)
ORDER BY date ASC";

$stmt = $conn->prepare($trendQuery);
if ($stmt === false) {
    error_log('Analytics Error - Trend Query: ' . $conn->error);
    $trendData = [];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $trendData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Get device distribution
$deviceQuery = "SELECT 
    device_type,
    COUNT(DISTINCT visitor_id) as count
FROM analytics_visitors
WHERE DATE(first_visit_at) BETWEEN ? AND ?
GROUP BY device_type";

$stmt = $conn->prepare($deviceQuery);
if ($stmt === false) {
    error_log('Analytics Error - Device Query: ' . $conn->error);
    $deviceData = [];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $deviceData = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Get top pages
$topPagesQuery = "SELECT 
    page_url,
    page_title,
    COUNT(*) as view_count,
    COUNT(DISTINCT visitor_id) as unique_visitors,
    AVG(visit_duration) as avg_duration
FROM analytics_page_views
WHERE DATE(viewed_at) BETWEEN ? AND ?
GROUP BY page_url, page_title
ORDER BY view_count DESC
LIMIT 10";

$stmt = $conn->prepare($topPagesQuery);
if ($stmt === false) {
    error_log('Analytics Error - Top Pages Query: ' . $conn->error);
    $topPages = [];
} else {
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $topPages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.analytics-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
.metric-value {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}
.metric-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.trend-up {
    color: #198754;
}
.trend-down {
    color: #dc3545;
}
.chart-container {
    position: relative;
    height: 300px;
}
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-chart-line text-primary me-2"></i>Analytics & Insights
                    </h1>
                    <p class="text-muted mb-0">Traffic Intelligence & Analytics Dashboard</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <a href="analytics_export.php?from=<?= $fromDate; ?>&to=<?= $toDate; ?>" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Export Data
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Quick Range</label>
                    <select name="range" class="form-select" onchange="this.form.submit()">
                        <option value="today" <?= $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?= $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="last_7_days" <?= $dateRange === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="last_30_days" <?= $dateRange === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="this_month" <?= $dateRange === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?= $dateRange === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?= $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $fromDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $toDate; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync-alt me-2"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Visitors -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="metric-label mb-2">Total Visitors</p>
                            <h2 class="metric-value text-primary mb-0"><?= number_format($visitorStats['total_visitors']); ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between text-sm">
                        <span class="text-success"><i class="fas fa-user-plus me-1"></i><?= number_format($visitorStats['new_visitors']); ?> New</span>
                        <span class="text-info"><i class="fas fa-redo me-1"></i><?= number_format($visitorStats['returning_visitors']); ?> Returning</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Views -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="metric-label mb-2">Page Views</p>
                            <h2 class="metric-value text-success mb-0"><?= number_format($pageViewStats['total_page_views']); ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-eye fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="text-muted">Avg. <?= $visitorStats['total_visitors'] > 0 ? number_format($pageViewStats['total_page_views'] / $visitorStats['total_visitors'], 1) : 0; ?> pages/visitor</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Clicks -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="metric-label mb-2">Total Clicks</p>
                            <h2 class="metric-value text-warning mb-0"><?= number_format($pageViewStats['total_clicks'] + $productStats['product_clicks']); ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-mouse-pointer fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="text-muted"><?= number_format($productStats['product_clicks']); ?> product clicks</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="metric-label mb-2">Total Revenue</p>
                            <h2 class="metric-value text-danger mb-0">₹<?= number_format($revenueStats['total_revenue'], 0); ?></h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="fas fa-rupee-sign fa-2x text-danger"></i>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="text-muted"><?= number_format($revenueStats['total_orders']); ?> orders • ₹<?= number_format($revenueStats['avg_order_value'], 0); ?> avg</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-shopping-cart fa-2x text-info mb-3"></i>
                    <h3 class="mb-1"><?= number_format($productStats['add_to_cart']); ?></h3>
                    <p class="text-muted mb-0 small">Add to Cart</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-percentage fa-2x text-success mb-3"></i>
                    <h3 class="mb-1"><?= number_format($conversionRate, 2); ?>%</h3>
                    <p class="text-muted mb-0 small">Conversion Rate</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-box fa-2x text-warning mb-3"></i>
                    <h3 class="mb-1"><?= number_format($productStats['unique_products_viewed']); ?></h3>
                    <p class="text-muted mb-0 small">Products Viewed</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-secondary mb-3"></i>
                    <h3 class="mb-1"><?= gmdate("i:s", $pageViewStats['avg_duration'] ?? 0); ?></h3>
                    <p class="text-muted mb-0 small">Avg. Session Duration</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Traffic Trend Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-chart-area text-primary me-2"></i>Traffic Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="trafficTrendChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Device Distribution -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-mobile-alt text-primary me-2"></i>Device Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Top Products -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-fire text-danger me-2"></i>Top Products by Engagement</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>PRODUCT</th>
                                    <th class="text-center">VIEWS</th>
                                    <th class="text-center">CLICKS</th>
                                    <th class="text-center">CART</th>
                                    <th class="text-center">SALES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($product['name']); ?></div>
                                        <small class="text-muted">₹<?= number_format($product['price'], 2); ?></small>
                                    </td>
                                    <td class="text-center"><?= number_format($product['view_count']); ?></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= number_format($product['click_count']); ?></span></td>
                                    <td class="text-center"><?= number_format($product['cart_count']); ?></td>
                                    <td class="text-center"><span class="badge bg-success"><?= number_format($product['purchase_count']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Geographic Distribution -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-globe text-info me-2"></i>Geographic Distribution</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>COUNTRY</th>
                                    <th class="text-center">VISITORS</th>
                                    <th class="text-center">VISITS</th>
                                    <th class="text-end">PERCENTAGE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalGeoVisitors = array_sum(array_column($geoData, 'visitor_count'));
                                foreach ($geoData as $geo): 
                                    $percentage = $totalGeoVisitors > 0 ? ($geo['visitor_count'] / $totalGeoVisitors) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($geo['country'] ?? 'Unknown'); ?></td>
                                    <td class="text-center"><?= number_format($geo['visitor_count']); ?></td>
                                    <td class="text-center"><?= number_format($geo['total_visits']); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="progress flex-grow-1 me-2" style="height: 8px; max-width: 100px;">
                                                <div class="progress-bar bg-info" style="width: <?= $percentage; ?>%"></div>
                                            </div>
                                            <span class="small"><?= number_format($percentage, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Pages -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-file-alt text-success me-2"></i>Most Visited Pages</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>PAGE</th>
                                    <th class="text-center">VIEWS</th>
                                    <th class="text-center">UNIQUE VISITORS</th>
                                    <th class="text-center">AVG. DURATION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topPages as $page): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($page['page_title'] ?? 'Untitled'); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($page['page_url']); ?></small>
                                    </td>
                                    <td class="text-center"><span class="badge bg-primary"><?= number_format($page['view_count']); ?></span></td>
                                    <td class="text-center"><?= number_format($page['unique_visitors']); ?></td>
                                    <td class="text-center"><?= gmdate("i:s", $page['avg_duration'] ?? 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Traffic Trend Chart
const trendCtx = document.getElementById('trafficTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trendData, 'date')); ?>,
        datasets: [{
            label: 'Visitors',
            data: <?= json_encode(array_column($trendData, 'visitors')); ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Page Views',
            data: <?= json_encode(array_column($trendData, 'page_views')); ?>,
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Device Distribution Chart
const deviceCtx = document.getElementById('deviceChart').getContext('2d');
new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($deviceData, 'device_type')); ?>,
        datasets: [{
            data: <?= json_encode(array_column($deviceData, 'count')); ?>,
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
