<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Suggestion Details';
$adminPage = 'suggestions';

// Get suggestion ID
$suggestionId = (int)($_GET['id'] ?? 0);

if (!$suggestionId) {
    $_SESSION['flash_message'] = 'Invalid suggestion ID';
    $_SESSION['flash_type'] = 'error';
    header('Location: suggestions_center.php');
    exit;
}

// Fetch suggestion details
$query = "SELECT s.*, 
          u.name as user_name, u.email as user_email,
          r.name as reviewer_name,
          (SELECT COUNT(*) FROM suggestion_rewards WHERE suggestion_id = s.id) as reward_count
          FROM suggestions s
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN users r ON s.reviewed_by = r.id
          WHERE s.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $suggestionId);
$stmt->execute();
$result = $stmt->get_result();
$suggestion = $result->fetch_assoc();

if (!$suggestion) {
    $_SESSION['flash_message'] = 'Suggestion not found';
    $_SESSION['flash_type'] = 'error';
    header('Location: suggestions_center.php');
    exit;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
:root {
    --color-primary: #0d6efd;
    --color-success: #198754;
    --color-warning: #ffc107;
    --color-danger: #dc3545;
    --color-info: #0dcaf0;
    --color-gold: #C5A059;
    --color-gray-50: #f8f9fa;
    --color-gray-100: #e9ecef;
    --color-gray-600: #6c757d;
    --color-gray-900: #212529;
}

/* Compact single-screen layout */
.suggestion-details-page {
    padding: 1rem 1.5rem;
    max-width: 100%;
    height: calc(100vh - 120px);
    overflow: hidden;
}

.details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--color-gray-100);
}

.details-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-gray-900);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.back-button {
    padding: 0.5rem 1rem;
    background: white;
    color: var(--color-primary);
    border: 2px solid var(--color-primary);
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.back-button:hover {
    background: var(--color-primary);
    color: white;
}

/* Single row layout */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1rem;
    height: calc(100% - 60px);
}

@media (max-width: 1200px) {
    .details-grid {
        grid-template-columns: 1fr 300px;
    }
}

.details-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    overflow-y: auto;
    height: 100%;
}

.card-header {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-gray-900);
    margin: 0 0 1rem 0;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--color-gray-100);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.info-item {
    padding: 0.75rem;
    background: var(--color-gray-50);
    border-radius: 6px;
    border-left: 3px solid var(--color-primary);
}

.info-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 0.9rem;
    color: var(--color-gray-900);
    font-weight: 600;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-block;
}

.status-new { background: rgba(13, 202, 240, 0.15); color: #0dcaf0; }
.status-under_review { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
.status-accepted { background: rgba(25, 135, 84, 0.15); color: #198754; }
.status-rejected { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
.status-implemented { background: rgba(13, 110, 253, 0.15); color: #0d6efd; }

.description-section {
    margin-top: 1rem;
}

.description-header {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-gray-900);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.description-box {
    background: var(--color-gray-50);
    padding: 1rem;
    border-radius: 6px;
    border-left: 3px solid var(--color-primary);
    line-height: 1.6;
    font-size: 0.9rem;
    color: var(--color-gray-900);
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 300px;
    overflow-y: auto;
}

/* Sidebar cards */
.sidebar-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.sidebar-card:last-child {
    margin-bottom: 0;
}

.sidebar-header {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-gray-900);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--color-gray-100);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sidebar-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--color-gray-100);
    font-size: 0.85rem;
}

.sidebar-row:last-child {
    border-bottom: none;
}

.sidebar-label {
    font-weight: 600;
    color: var(--color-gray-600);
}

.sidebar-value {
    color: var(--color-gray-900);
    text-align: right;
    word-break: break-word;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-direction: column;
}

.btn-action {
    padding: 0.625rem 1rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-manage {
    background: var(--color-success);
    color: white;
}

.btn-manage:hover {
    background: #157347;
}

.best-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    background: linear-gradient(135deg, #fff9e6 0%, #ffe6b3 100%);
    border: 2px solid var(--color-gold);
    border-radius: 6px;
    color: var(--color-gold);
    font-weight: 700;
    font-size: 0.8rem;
}

/* Scrollbar styling */
.details-card::-webkit-scrollbar,
.description-box::-webkit-scrollbar {
    width: 6px;
}

.details-card::-webkit-scrollbar-track,
.description-box::-webkit-scrollbar-track {
    background: var(--color-gray-100);
    border-radius: 3px;
}

.details-card::-webkit-scrollbar-thumb,
.description-box::-webkit-scrollbar-thumb {
    background: var(--color-gray-600);
    border-radius: 3px;
}

.details-card::-webkit-scrollbar-thumb:hover,
.description-box::-webkit-scrollbar-thumb:hover {
    background: var(--color-gray-900);
}
</style>

<div class="suggestion-details-page">
    <!-- Compact Header -->
    <div class="details-header">
        <h1 class="details-title">
            <i class="fas fa-lightbulb" style="color: var(--color-warning);"></i>
            Suggestion Details
        </h1>
        <a href="suggestions_center.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to List
        </a>
    </div>

    <!-- Single-Screen Grid Layout -->
    <div class="details-grid">
        <!-- Main Content Column -->
        <div class="details-card">
            <h2 class="card-header">
                <?= htmlspecialchars($suggestion['subject']); ?>
                <?php if ($suggestion['is_best_suggestion']): ?>
                    <span class="best-badge">
                        <i class="fas fa-trophy"></i>
                        Best
                    </span>
                <?php endif; ?>
            </h2>
            
            <!-- Compact Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Submission ID</div>
                    <div class="info-value"><?= htmlspecialchars($suggestion['submission_id']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Category</div>
                    <div class="info-value">
                        <span class="status-badge" style="background: var(--color-gray-100); color: var(--color-gray-900);">
                            <?= htmlspecialchars($suggestion['category']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php
                        $statusClass = 'status-' . $suggestion['status'];
                        $statusText = ucwords(str_replace('_', ' ', $suggestion['status']));
                        ?>
                        <span class="status-badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Submitted</div>
                    <div class="info-value"><?= date('M d, Y h:i A', strtotime($suggestion['submitted_at'])); ?></div>
                </div>
            </div>
            
            <!-- Description Section -->
            <div class="description-section">
                <div class="description-header">
                    <i class="fas fa-align-left"></i>
                    Full Description
                </div>
                <div class="description-box">
                    <?= nl2br(htmlspecialchars($suggestion['description'])); ?>
                </div>
            </div>
        </div>

        <!-- Compact Sidebar -->
        <div style="overflow-y: auto; height: 100%;">
            <!-- Submitter Info -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fas fa-user"></i>
                    Submitted By
                </div>
                
                <div class="sidebar-row">
                    <span class="sidebar-label">Type:</span>
                    <span class="sidebar-value">
                        <?php if ($suggestion['is_guest']): ?>
                            <i class="fas fa-user"></i> Guest
                        <?php else: ?>
                            <i class="fas fa-user-check" style="color: var(--color-success);"></i> User
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="sidebar-row">
                    <span class="sidebar-label">Name:</span>
                    <span class="sidebar-value"><?= htmlspecialchars($suggestion['user_name'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="sidebar-row">
                    <span class="sidebar-label">Email:</span>
                    <span class="sidebar-value">
                        <a href="mailto:<?= htmlspecialchars($suggestion['user_email']); ?>" style="color: var(--color-primary); font-size: 0.8rem;">
                            <?= htmlspecialchars($suggestion['user_email']); ?>
                        </a>
                    </span>
                </div>
                
                <div class="sidebar-row">
                    <span class="sidebar-label">Source:</span>
                    <span class="sidebar-value"><?= ucfirst(htmlspecialchars($suggestion['source'] ?? 'website')); ?></span>
                </div>
            </div>

            <!-- Review Info -->
            <?php if ($suggestion['reviewed_by']): ?>
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fas fa-clipboard-check"></i>
                    Review Info
                </div>
                
                <div class="sidebar-row">
                    <span class="sidebar-label">Reviewed By:</span>
                    <span class="sidebar-value"><?= htmlspecialchars($suggestion['reviewer_name']); ?></span>
                </div>
                
                <div class="sidebar-row">
                    <span class="sidebar-label">Reviewed At:</span>
                    <span class="sidebar-value"><?= date('M d, Y', strtotime($suggestion['reviewed_at'])); ?></span>
                </div>
                
                <?php if ($suggestion['admin_notes']): ?>
                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--color-gray-100);">
                    <div class="sidebar-label" style="margin-bottom: 0.5rem;">Admin Notes:</div>
                    <div style="font-size: 0.85rem; color: var(--color-gray-900); line-height: 1.5;">
                        <?= nl2br(htmlspecialchars($suggestion['admin_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fas fa-cog"></i>
                    Actions
                </div>
                
                <div class="action-buttons">
                    <a href="suggestion_manage.php?id=<?= $suggestion['id']; ?>" class="btn-action btn-manage">
                        <i class="fas fa-edit"></i>
                        Manage Suggestion
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
