<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Idea & Suggestion Center';
$adminPage = 'suggestions';

// Get filter parameters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$isBest = isset($_GET['best']) ? 1 : 0;

// Build query
$whereConditions = [];
$params = [];
$types = '';

if ($status) {
    $whereConditions[] = "s.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($category) {
    $whereConditions[] = "s.category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($search) {
    $whereConditions[] = "(s.subject LIKE ? OR s.description LIKE ? OR s.submission_id LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if ($isBest) {
    $whereConditions[] = "s.is_best_suggestion = 1";
}

$whereConditions[] = "DATE(s.submitted_at) BETWEEN ? AND ?";
$params[] = $fromDate;
$params[] = $toDate;
$types .= 'ss';

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get suggestions
$query = "SELECT s.*, 
          u.name as user_name, u.email as user_email,
          r.name as reviewer_name,
          (SELECT COUNT(*) FROM suggestion_rewards WHERE suggestion_id = s.id) as reward_count
          FROM suggestions s
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN users r ON s.reviewed_by = r.id
          {$whereClause}
          ORDER BY s.submitted_at DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Query preparation failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$suggestions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN is_best_suggestion = 1 THEN 1 ELSE 0 END) as best_count
FROM suggestions
WHERE DATE(submitted_at) BETWEEN ? AND ?";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param('ss', $fromDate, $toDate);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Get category distribution
$categoryQuery = "SELECT category, COUNT(*) as count 
                  FROM suggestions 
                  WHERE DATE(submitted_at) BETWEEN ? AND ?
                  GROUP BY category 
                  ORDER BY count DESC";
$categoryStmt = $conn->prepare($categoryQuery);
$categoryStmt->bind_param('ss', $fromDate, $toDate);
$categoryStmt->execute();
$categoryStats = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ===== Responsive Typography System ===== */
:root {
    /* Base font size scales with viewport */
    --base-font-size: clamp(14px, 1vw, 16px);
    --heading-1: clamp(1.75rem, 2.5vw, 2.25rem);
    --heading-2: clamp(1.5rem, 2vw, 1.875rem);
    --heading-3: clamp(1.25rem, 1.75vw, 1.5rem);
    --heading-4: clamp(1.125rem, 1.5vw, 1.25rem);
    --body-text: clamp(0.875rem, 1vw, 1rem);
    --small-text: clamp(0.75rem, 0.875vw, 0.875rem);
    
    /* Responsive spacing */
    --spacing-xs: clamp(0.25rem, 0.5vw, 0.5rem);
    --spacing-sm: clamp(0.5rem, 1vw, 0.75rem);
    --spacing-md: clamp(0.75rem, 1.5vw, 1rem);
    --spacing-lg: clamp(1rem, 2vw, 1.5rem);
    --spacing-xl: clamp(1.5rem, 3vw, 2rem);
    --spacing-2xl: clamp(2rem, 4vw, 3rem);
    
    /* Colors */
    --color-primary: #0d6efd;
    --color-success: #198754;
    --color-warning: #ffc107;
    --color-danger: #dc3545;
    --color-info: #0dcaf0;
    --color-gold: #C5A059;
    --color-gray-50: #f8f9fa;
    --color-gray-100: #e9ecef;
    --color-gray-200: #dee2e6;
    --color-gray-600: #6c757d;
    --color-gray-900: #212529;
}

body {
    font-size: var(--body-text);
    line-height: 1.6;
}

/* ===== Responsive Page Container ===== */
.suggestions-page {
    padding: var(--spacing-lg) var(--spacing-md);
    max-width: 100%;
    margin: 0 auto;
}

@media (min-width: 1200px) {
    .suggestions-page {
        max-width: 1400px;
        padding: var(--spacing-xl) var(--spacing-lg);
    }
}

/* ===== Page Header ===== */
.page-header {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
}

@media (min-width: 768px) {
    .page-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.page-title {
    font-size: var(--heading-1);
    font-weight: 700;
    color: var(--color-gray-900);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    line-height: 1.2;
}

.page-subtitle {
    font-size: var(--body-text);
    color: var(--color-gray-600);
    margin: var(--spacing-xs) 0 0 0;
}

.header-actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.btn-refresh {
    padding: var(--spacing-sm) var(--spacing-lg);
    font-size: var(--body-text);
    border-radius: 8px;
    border: 2px solid var(--color-primary);
    background: white;
    color: var(--color-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-sm);
    white-space: nowrap;
}

.btn-refresh:hover {
    background: var(--color-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
}

/* ===== Responsive Stats Grid ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
}

@media (min-width: 640px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: var(--spacing-lg);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-left: 4px solid;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.stat-card-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--spacing-sm);
}

.stat-label {
    font-size: var(--small-text);
    color: var(--color-gray-600);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

.stat-value {
    font-size: var(--heading-2);
    font-weight: 700;
    margin: 0;
    line-height: 1;
}

.stat-icon {
    font-size: clamp(1.5rem, 2vw, 2rem);
    opacity: 0.5;
}

/* Stat card color variants */
.stat-card.total { border-color: var(--color-gray-600); }
.stat-card.new { border-color: var(--color-info); }
.stat-card.review { border-color: var(--color-warning); }
.stat-card.accepted { border-color: var(--color-success); }
.stat-card.rejected { border-color: var(--color-danger); }
.stat-card.best { 
    border-color: var(--color-gold);
    background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
}

/* ===== Responsive Filters ===== */
.filters-card {
    background: white;
    border-radius: 12px;
    padding: var(--spacing-lg);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: var(--spacing-xl);
}

.filters-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-md);
}

@media (min-width: 640px) {
    .filters-form {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 768px) {
    .filters-form {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .filters-form {
        grid-template-columns: repeat(6, 1fr);
    }
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.filter-label {
    font-size: var(--small-text);
    font-weight: 600;
    color: var(--color-gray-900);
    margin: 0;
}

.filter-input,
.filter-select {
    padding: var(--spacing-sm) var(--spacing-md);
    font-size: var(--body-text);
    border: 2px solid var(--color-gray-200);
    border-radius: 8px;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
}

.btn-filter {
    padding: var(--spacing-sm) var(--spacing-lg);
    font-size: var(--body-text);
    background: var(--color-primary);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    white-space: nowrap;
    align-self: flex-end;
}

.btn-filter:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
}

/* ===== Responsive Table Container ===== */
.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.table-header {
    padding: var(--spacing-lg);
    border-bottom: 2px solid var(--color-gray-100);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

@media (min-width: 768px) {
    .table-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.table-title {
    font-size: var(--heading-3);
    font-weight: 700;
    color: var(--color-gray-900);
    margin: 0;
}

.best-toggle {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--body-text);
    cursor: pointer;
}

/* ===== Responsive Table ===== */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.suggestions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--body-text);
}

.suggestions-table thead {
    background: var(--color-gray-50);
    position: sticky;
    top: 0;
    z-index: 10;
}

.suggestions-table th {
    padding: var(--spacing-md);
    text-align: left;
    font-weight: 700;
    font-size: var(--small-text);
    color: var(--color-gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    border-bottom: 2px solid var(--color-gray-200);
}

.suggestions-table td {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--color-gray-100);
    vertical-align: middle;
}

.suggestions-table tbody tr {
    transition: background-color 0.2s ease;
}

.suggestions-table tbody tr:hover {
    background: var(--color-gray-50);
}

/* Mobile-optimized table */
@media (max-width: 767px) {
    .suggestions-table thead {
        display: none;
    }
    
    .suggestions-table tbody tr {
        display: block;
        margin-bottom: var(--spacing-lg);
        border: 2px solid var(--color-gray-200);
        border-radius: 8px;
        padding: var(--spacing-md);
    }
    
    .suggestions-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-sm) 0;
        border: none;
    }
    
    .suggestions-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: var(--color-gray-600);
        font-size: var(--small-text);
        text-transform: uppercase;
    }
}

/* ===== Badges & Status ===== */
.badge {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: var(--small-text);
    font-weight: 600;
    border-radius: 6px;
    display: inline-block;
    white-space: nowrap;
}

.badge-info { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
.badge-warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.badge-success { background: rgba(25, 135, 84, 0.1); color: #198754; }
.badge-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.badge-primary { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
.badge-secondary { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
.badge-light { background: var(--color-gray-100); color: var(--color-gray-900); }

/* ===== Action Buttons ===== */
.action-buttons {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.btn-action {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: var(--small-text);
    border-radius: 6px;
    border: 2px solid;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.btn-action.view {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.btn-action.view:hover {
    background: var(--color-primary);
    color: white;
}

.btn-action.delete {
    border-color: var(--color-danger);
    color: var(--color-danger);
}

.btn-action.delete:hover {
    background: var(--color-danger);
    color: white;
}

/* ===== Empty State ===== */
.empty-state {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--color-gray-600);
}

.empty-icon {
    font-size: clamp(2rem, 4vw, 3rem);
    margin-bottom: var(--spacing-lg);
    opacity: 0.5;
}

/* ===== Utility Classes ===== */
.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.text-muted {
    color: var(--color-gray-600);
}

.fw-semibold {
    font-weight: 600;
}

.fw-bold {
    font-weight: 700;
}

/* ===== Accessibility ===== */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .stat-card,
    .filters-card,
    .table-card {
        border: 2px solid currentColor;
    }
}
</style>

<!-- Responsive Idea & Suggestion Center -->
<div class="suggestions-page">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-lightbulb" style="color: var(--color-warning);"></i>
                Idea & Suggestion Center
            </h1>
            <p class="page-subtitle">Manage and reward valuable improvement ideas</p>
        </div>
        <div class="header-actions">
            <button class="btn-refresh" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <p class="stat-label">Total</p>
            <div class="stat-card-content">
                <h3 class="stat-value"><?= number_format($stats['total']); ?></h3>
                <i class="fas fa-inbox stat-icon" style="color: var(--color-gray-600);"></i>
            </div>
        </div>
        
        <div class="stat-card new">
            <p class="stat-label">New</p>
            <div class="stat-card-content">
                <h3 class="stat-value" style="color: var(--color-info);"><?= number_format($stats['new_count']); ?></h3>
                <i class="fas fa-star stat-icon" style="color: var(--color-info);"></i>
            </div>
        </div>
        
        <div class="stat-card review">
            <p class="stat-label">Under Review</p>
            <div class="stat-card-content">
                <h3 class="stat-value" style="color: var(--color-warning);"><?= number_format($stats['under_review_count']); ?></h3>
                <i class="fas fa-search stat-icon" style="color: var(--color-warning);"></i>
            </div>
        </div>
        
        <div class="stat-card accepted">
            <p class="stat-label">Accepted</p>
            <div class="stat-card-content">
                <h3 class="stat-value" style="color: var(--color-success);"><?= number_format($stats['accepted_count']); ?></h3>
                <i class="fas fa-check-circle stat-icon" style="color: var(--color-success);"></i>
            </div>
        </div>
        
        <div class="stat-card rejected">
            <p class="stat-label">Rejected</p>
            <div class="stat-card-content">
                <h3 class="stat-value" style="color: var(--color-danger);"><?= number_format($stats['rejected_count']); ?></h3>
                <i class="fas fa-times-circle stat-icon" style="color: var(--color-danger);"></i>
            </div>
        </div>
        
        <div class="stat-card best">
            <p class="stat-label">Best Ideas</p>
            <div class="stat-card-content">
                <h3 class="stat-value" style="color: var(--color-gold);"><?= number_format($stats['best_count']); ?></h3>
                <i class="fas fa-trophy stat-icon" style="color: var(--color-gold);"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="new" <?= $status === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="under_review" <?= $status === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="accepted" <?= $status === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="implemented" <?= $status === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Category</label>
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <option value="UI/UX" <?= $category === 'UI/UX' ? 'selected' : ''; ?>>UI/UX</option>
                    <option value="Performance" <?= $category === 'Performance' ? 'selected' : ''; ?>>Performance</option>
                    <option value="Features" <?= $category === 'Features' ? 'selected' : ''; ?>>Features</option>
                    <option value="Payments" <?= $category === 'Payments' ? 'selected' : ''; ?>>Payments</option>
                    <option value="Security" <?= $category === 'Security' ? 'selected' : ''; ?>>Security</option>
                    <option value="Content" <?= $category === 'Content' ? 'selected' : ''; ?>>Content</option>
                    <option value="Other" <?= $category === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">From Date</label>
                <input type="date" name="from_date" class="filter-input" value="<?= $fromDate; ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">To Date</label>
                <input type="date" name="to_date" class="filter-input" value="<?= $toDate; ?>">
            </div>
            
            <div class="filter-group" style="grid-column: span 1;">
                <label class="filter-label">Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Search..." value="<?= htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Suggestions Table -->
    <div class="table-card">
        <div class="table-header">
            <h2 class="table-title">Suggestions List</h2>
            <label class="best-toggle">
                <input type="checkbox" id="showBestOnly" <?= $isBest ? 'checked' : ''; ?> 
                       onchange="window.location.href='?<?= http_build_query(array_merge($_GET, ['best' => $isBest ? '' : '1'])); ?>'">
                <i class="fas fa-trophy" style="color: var(--color-warning);"></i>
                <span>Best Ideas Only</span>
            </label>
        </div>
        <div class="table-responsive">
            <table class="suggestions-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SUBJECT</th>
                        <th>CATEGORY</th>
                        <th>SUBMITTED BY</th>
                        <th>STATUS</th>
                        <th>DATE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                        <?php if (empty($suggestions)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox empty-icon"></i>
                                    <p>No suggestions found</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($suggestions as $suggestion): ?>
                        <tr>
                            <td data-label="ID">
                                <span class="badge badge-secondary"><?= htmlspecialchars($suggestion['submission_id']); ?></span>
                                <?php if ($suggestion['is_best_suggestion']): ?>
                                <i class="fas fa-trophy" style="color: var(--color-warning);" title="Best Suggestion"></i>
                                <?php endif; ?>
                            </td>
                            <td data-label="Subject" style="max-width: 300px;">
                                <div class="fw-semibold" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($suggestion['subject']); ?></div>
                                <small class="text-muted" style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars(substr($suggestion['description'], 0, 80)); ?>...</small>
                            </td>
                            <td data-label="Category">
                                <span class="badge badge-light"><?= htmlspecialchars($suggestion['category']); ?></span>
                            </td>
                            <td data-label="Submitted By">
                                <?php if ($suggestion['is_guest']): ?>
                                <div><i class="fas fa-user text-muted"></i> <?= htmlspecialchars($suggestion['user_name']); ?></div>
                                <small class="text-muted"><?= htmlspecialchars($suggestion['user_email']); ?></small>
                                <?php else: ?>
                                <div><i class="fas fa-user-check" style="color: var(--color-success);"></i> <?= htmlspecialchars($suggestion['user_name'] ?? 'User'); ?></div>
                                <small class="text-muted"><?= htmlspecialchars($suggestion['user_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <?php
                                $statusColors = [
                                    'new' => 'info',
                                    'under_review' => 'warning',
                                    'accepted' => 'success',
                                    'rejected' => 'danger',
                                    'implemented' => 'primary'
                                ];
                                $statusColor = $statusColors[$suggestion['status']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $statusColor; ?>"><?= ucwords(str_replace('_', ' ', $suggestion['status'])); ?></span>
                            </td>
                            <td data-label="Date">
                                <small><?= date('M d, Y', strtotime($suggestion['submitted_at'])); ?></small>
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <button class="btn-action view" onclick="viewSuggestion(<?= $suggestion['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-action delete" onclick="deleteSuggestion(<?= $suggestion['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function viewSuggestion(id) {
    window.location.href = 'suggestion_details.php?id=' + id;
}

function deleteSuggestion(id) {
    if (confirm('Are you sure you want to delete this suggestion? This action cannot be undone.')) {
        // You can implement the delete functionality here
        // For now, redirect to manage page where delete can be handled
        window.location.href = 'suggestion_manage.php?id=' + id + '&action=delete';
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
