<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Suggestions Analytics';
$adminPage = 'suggestions';

// Date range
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');

// Get comprehensive analytics
$analyticsQuery = "SELECT 
    COUNT(*) as total_suggestions,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN is_best_suggestion = 1 THEN 1 ELSE 0 END) as best_count,
    SUM(CASE WHEN is_guest = 1 THEN 1 ELSE 0 END) as guest_count,
    SUM(CASE WHEN is_guest = 0 THEN 1 ELSE 0 END) as registered_count,
    AVG(CASE WHEN reviewed_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, submitted_at, reviewed_at) 
        ELSE NULL END) as avg_review_time_hours
FROM suggestions
WHERE DATE(submitted_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($analyticsQuery);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$analytics = $stmt->get_result()->fetch_assoc();

// Category breakdown
$categoryQuery = "SELECT category, 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
    SUM(CASE WHEN is_best_suggestion = 1 THEN 1 ELSE 0 END) as best
FROM suggestions
WHERE DATE(submitted_at) BETWEEN ? AND ?
GROUP BY category
ORDER BY total DESC";

$catStmt = $conn->prepare($categoryQuery);
$catStmt->bind_param('ss', $fromDate, $toDate);
$catStmt->execute();
$categoryData = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top contributors
$contributorsQuery = "SELECT 
    user_email,
    user_name,
    COUNT(*) as suggestion_count,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
    SUM(CASE WHEN is_best_suggestion = 1 THEN 1 ELSE 0 END) as best_count
FROM suggestions
WHERE DATE(submitted_at) BETWEEN ? AND ?
AND user_email IS NOT NULL
GROUP BY user_email, user_name
HAVING suggestion_count > 0
ORDER BY suggestion_count DESC
LIMIT 10";

$contStmt = $conn->prepare($contributorsQuery);
$contStmt->bind_param('ss', $fromDate, $toDate);
$contStmt->execute();
$contributors = $contStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Timeline data (last 30 days)
$timelineQuery = "SELECT 
    DATE(submitted_at) as date,
    COUNT(*) as count
FROM suggestions
WHERE DATE(submitted_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
GROUP BY DATE(submitted_at)
ORDER BY date ASC";

$timeline = $conn->query($timelineQuery)->fetch_all(MYSQLI_ASSOC);

// Rewards statistics
$rewardsQuery = "SELECT 
    COUNT(*) as total_rewards,
    SUM(reward_value) as total_value,
    AVG(reward_value) as avg_value
FROM suggestion_rewards
WHERE DATE(assigned_at) BETWEEN ? AND ?";

$rewStmt = $conn->prepare($rewardsQuery);
$rewStmt->bind_param('ss', $fromDate, $toDate);
$rewStmt->execute();
$rewardsStats = $rewStmt->get_result()->fetch_assoc();

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1"><i class="fas fa-chart-bar text-primary me-2"></i>Suggestions Analytics</h1>
                    <p class="text-muted mb-0">Insights and performance metrics</p>
                </div>
                <a href="suggestions_center.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Suggestions
                </a>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $fromDate; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $toDate; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync-alt me-2"></i>Update Analytics
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-inbox fa-3x text-primary mb-3"></i>
                    <h2 class="mb-1"><?= number_format($analytics['total_suggestions']); ?></h2>
                    <p class="text-muted mb-0">Total Suggestions</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h2 class="mb-1"><?= number_format($analytics['accepted_count']); ?></h2>
                    <p class="text-muted mb-0">Accepted</p>
                    <small class="text-success">
                        <?= $analytics['total_suggestions'] > 0 ? round(($analytics['accepted_count'] / $analytics['total_suggestions']) * 100, 1) : 0; ?>% acceptance rate
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-trophy fa-3x text-warning mb-3"></i>
                    <h2 class="mb-1"><?= number_format($analytics['best_count']); ?></h2>
                    <p class="text-muted mb-0">Best Ideas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-3x text-info mb-3"></i>
                    <h2 class="mb-1"><?= round($analytics['avg_review_time_hours'] ?? 0, 1); ?>h</h2>
                    <p class="text-muted mb-0">Avg Review Time</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Category Breakdown -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Category Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Accepted</th>
                                    <th class="text-center">Best</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryData as $cat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat['category']); ?></td>
                                    <td class="text-center"><?= $cat['total']; ?></td>
                                    <td class="text-center"><span class="badge bg-success"><?= $cat['accepted']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning"><?= $cat['best']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Contributors -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Top Contributors</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Contributor</th>
                                    <th class="text-center">Suggestions</th>
                                    <th class="text-center">Accepted</th>
                                    <th class="text-center">Best</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contributors as $contributor): ?>
                                <tr>
                                    <td>
                                        <div><?= htmlspecialchars($contributor['user_name']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($contributor['user_email']); ?></small>
                                    </td>
                                    <td class="text-center"><?= $contributor['suggestion_count']; ?></td>
                                    <td class="text-center"><span class="badge bg-success"><?= $contributor['accepted_count']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning"><?= $contributor['best_count']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rewards Statistics -->
    <?php if ($rewardsStats['total_rewards'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-gift text-warning me-2"></i>Rewards Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h3 class="text-primary"><?= number_format($rewardsStats['total_rewards']); ?></h3>
                            <p class="text-muted mb-0">Total Rewards Issued</p>
                        </div>
                        <div class="col-md-4">
                            <h3 class="text-success">₹<?= number_format($rewardsStats['total_value'], 2); ?></h3>
                            <p class="text-muted mb-0">Total Value</p>
                        </div>
                        <div class="col-md-4">
                            <h3 class="text-info">₹<?= number_format($rewardsStats['avg_value'], 2); ?></h3>
                            <p class="text-muted mb-0">Average Value</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- User Type Distribution -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">User Type Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="p-3">
                                <i class="fas fa-user-check fa-3x text-success mb-2"></i>
                                <h3><?= number_format($analytics['registered_count']); ?></h3>
                                <p class="text-muted mb-0">Registered Users</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3">
                                <i class="fas fa-user fa-3x text-secondary mb-2"></i>
                                <h3><?= number_format($analytics['guest_count']); ?></h3>
                                <p class="text-muted mb-0">Guest Users</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Status Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="p-2">
                                <h4 class="text-info"><?= $analytics['new_count']; ?></h4>
                                <small class="text-muted">New</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2">
                                <h4 class="text-success"><?= $analytics['accepted_count']; ?></h4>
                                <small class="text-muted">Accepted</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2">
                                <h4 class="text-danger"><?= $analytics['rejected_count']; ?></h4>
                                <small class="text-muted">Rejected</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
