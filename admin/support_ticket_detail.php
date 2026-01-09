<?php
/**
 * ADMIN PANEL - TICKET DETAIL VIEW
 * View and manage individual support ticket
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_functions.php';

require_admin();

$pageTitle = 'Ticket Details — Admin';
$adminPage = 'support';

$ticketId = $_GET['ticket'] ?? '';
$ticket = get_ticket_by_id($ticketId);

if (!$ticket) {
    redirect_with_message('/admin/support_tickets.php', 'Ticket not found', 'error');
}

$comments = get_ticket_comments($ticketId, true);
$statusHistory = get_ticket_status_history($ticketId);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName = $_SESSION['user']['name'] ?? 'Admin';
    $adminId = $_SESSION['user']['id'] ?? null;
    $adminEmail = $_SESSION['user']['email'] ?? '';
    
    if (isset($_POST['add_comment'])) {
        $comment = trim($_POST['comment'] ?? '');
        $isInternal = isset($_POST['is_internal']);
        
        if (!empty($comment)) {
            $result = add_ticket_comment($ticketId, $adminId, $adminName, $adminEmail, $comment, $isInternal, true);
            if ($result['success']) {
                redirect_with_message("support_ticket_detail.php?ticket=$ticketId", 'Comment added successfully', 'success');
            }
        }
    }
    
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['new_status'] ?? '';
        $notes = trim($_POST['status_notes'] ?? '');
        
        if (!empty($newStatus)) {
            $result = update_ticket_status($ticketId, $newStatus, $adminId, $adminName, $notes);
            if ($result['success']) {
                redirect_with_message("/admin/support_ticket_detail.php?ticket=$ticketId", 'Status updated successfully', 'success');
            }
        }
    }
    
    if (isset($_POST['assign_ticket'])) {
        $agentId = $_POST['agent_id'] ?? null;
        
        if ($agentId) {
            $result = assign_ticket($ticketId, $agentId, $adminName);
            if ($result['success']) {
                redirect_with_message("/admin/support_ticket_detail.php?ticket=$ticketId", 'Ticket assigned successfully', 'success');
            }
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
    .ticket-detail-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .ticket-header {
        background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .ticket-header h2 {
        margin: 0 0 10px 0;
        font-size: 1.8rem;
    }
    
    .ticket-meta {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.2);
    }
    
    .meta-item {
        display: flex;
        flex-direction: column;
    }
    
    .meta-label {
        font-size: 0.85rem;
        opacity: 0.8;
        margin-bottom: 5px;
    }
    
    .meta-value {
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    
    .main-content {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .card-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0 0 20px 0;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .description-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #3498db;
        line-height: 1.8;
        color: #555;
    }
    
    .comment {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #3498db;
    }
    
    .comment.internal {
        background: #fff3cd;
        border-left-color: #f39c12;
    }
    
    .comment.admin {
        border-left-color: #27ae60;
    }
    
    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .comment-author {
        font-weight: 700;
        color: #2c3e50;
    }
    
    .comment-time {
        font-size: 0.85rem;
        color: #7f8c8d;
    }
    
    .comment-text {
        color: #555;
        line-height: 1.6;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.2s;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3498db;
    }
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2980b9;
    }
    
    .btn-success {
        background: #27ae60;
        color: white;
    }
    
    .btn-success:hover {
        background: #229954;
    }
    
    .btn-secondary {
        background: #95a5a6;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #7f8c8d;
    }
    
    .status-timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -23px;
        top: 8px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #3498db;
        border: 2px solid white;
        box-shadow: 0 0 0 2px #3498db;
    }
    
    .timeline-item::after {
        content: '';
        position: absolute;
        left: -19px;
        top: 18px;
        width: 2px;
        height: calc(100% - 8px);
        background: #e9ecef;
    }
    
    .timeline-item:last-child::after {
        display: none;
    }
    
    .timeline-time {
        font-size: 0.85rem;
        color: #7f8c8d;
    }
    
    .timeline-status {
        font-weight: 600;
        color: #2c3e50;
        margin: 5px 0;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-weight: normal;
    }
    
    @media (max-width: 992px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="py-4">
    <div class="container-fluid px-4">
        <div class="ticket-detail-container">
            <!-- Back Button -->
            <div class="mb-3">
                <a href="support_tickets.php" style="color: #3498db; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-arrow-left"></i> Back to All Tickets
                </a>
            </div>
            
            <!-- Ticket Header -->
            <div class="ticket-header">
                <h2><?= htmlspecialchars($ticket['subject']) ?></h2>
                <div class="ticket-meta">
                    <div class="meta-item">
                        <span class="meta-label">Ticket ID</span>
                        <span class="meta-value"><?= htmlspecialchars($ticket['ticket_id']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Status</span>
                        <span class="meta-value">
                            <span class="badge" style="background: white; color: #1A3C34;">
                                <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                            </span>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Priority</span>
                        <span class="meta-value">
                            <span class="badge" style="background: rgba(255,255,255,0.2); color: white;">
                                <?= ucfirst($ticket['priority']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Issue Type</span>
                        <span class="meta-value"><?= ucfirst(str_replace('_', ' ', $ticket['issue_type'])) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Created</span>
                        <span class="meta-value"><?= date('M d, Y - h:i A', strtotime($ticket['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Main Content -->
                <div class="main-content">
                    <!-- Original Description -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-file-alt"></i> Original Request</h3>
                        <div class="description-box">
                            <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                        </div>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef; color: #7f8c8d; font-size: 0.9rem;">
                            <strong>From:</strong> <?= htmlspecialchars($ticket['user_name']) ?> (<?= htmlspecialchars($ticket['user_email']) ?>)
                        </div>
                    </div>
                    
                    <!-- Comments/Conversation -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-comments"></i> Conversation History</h3>
                        
                        <?php if (empty($comments)): ?>
                            <p style="color: #7f8c8d; text-align: center; padding: 20px;">No comments yet. Be the first to respond!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment <?= $comment['is_internal'] ? 'internal' : '' ?> <?= $comment['is_admin'] ? 'admin' : '' ?>">
                                    <div class="comment-header">
                                        <span class="comment-author">
                                            <?= htmlspecialchars($comment['commenter_name']) ?>
                                            <?php if ($comment['is_admin']): ?>
                                                <span class="badge" style="background: #27ae60; color: white; margin-left: 5px;">Admin</span>
                                            <?php endif; ?>
                                            <?php if ($comment['is_internal']): ?>
                                                <span class="badge" style="background: #f39c12; color: white; margin-left: 5px;">Internal</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="comment-time"><?= date('M d, Y - h:i A', strtotime($comment['created_at'])) ?></span>
                                    </div>
                                    <div class="comment-text">
                                        <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add Comment Form -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-reply"></i> Add Response</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Your Response</label>
                                <textarea name="comment" class="form-control" placeholder="Type your response here..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_internal" value="1">
                                    <span>Internal Note (Not visible to customer)</span>
                                </label>
                            </div>
                            <button type="submit" name="add_comment" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Response
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Assign Ticket -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-user-tag"></i> Assign Ticket</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Assign to Support Agent</label>
                                <select name="agent_id" class="form-control" required>
                                    <option value="">Select Agent...</option>
                                    <?php
                                    try {
                                        $db = get_db_connection();
                                        $agents = $db->query("SELECT id, agent_name, specialization FROM support_agents WHERE is_active = 1 ORDER BY agent_name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($agents as $agent) {
                                            $selected = ($ticket['assigned_to'] == $agent['id']) ? 'selected' : '';
                                            echo '<option value="' . $agent['id'] . '" ' . $selected . '>';
                                            echo htmlspecialchars($agent['agent_name']);
                                            if ($agent['specialization']) {
                                                echo ' (' . htmlspecialchars($agent['specialization']) . ')';
                                            }
                                            echo '</option>';
                                        }
                                    } catch (Exception $e) {
                                        echo '<option value="">No agents available</option>';
                                    }
                                    ?>
                                </select>
                                <?php if (empty($agents)): ?>
                                    <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 6px; font-size: 13px;">
                                        <i class="fas fa-info-circle"></i> No support agents available. 
                                        <a href="manage_support_agents.php" style="color: #856404; font-weight: 600;">Add agents here</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="assign_ticket" class="btn btn-primary" style="width: 100%;" <?= empty($agents) ? 'disabled' : '' ?>>
                                <i class="fas fa-user-check"></i> Assign Ticket
                            </button>
                        </form>
                    </div>
                    
                    <!-- Update Status -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-tasks"></i> Update Status</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">New Status</label>
                                <select name="new_status" class="form-control" required>
                                    <option value="">Select Status...</option>
                                    <option value="acknowledged">Acknowledged</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="status_notes" class="form-control" placeholder="Add notes about this status change..." rows="3"></textarea>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-check"></i> Update Status
                            </button>
                        </form>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-user"></i> Customer Information</h3>
                        <div style="line-height: 2;">
                            <strong>Name:</strong><br>
                            <?= htmlspecialchars($ticket['user_name']) ?>
                            <br><br>
                            <strong>Email:</strong><br>
                            <a href="mailto:<?= htmlspecialchars($ticket['user_email']) ?>" style="color: #3498db;">
                                <?= htmlspecialchars($ticket['user_email']) ?>
                            </a>
                            <?php if ($ticket['user_id']): ?>
                                <br><br>
                                <strong>User ID:</strong><br>
                                #<?= $ticket['user_id'] ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Status Timeline -->
                    <div class="card">
                        <h3 class="card-title"><i class="fas fa-history"></i> Status History</h3>
                        <div class="status-timeline">
                            <?php foreach ($statusHistory as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time"><?= date('M d, Y - h:i A', strtotime($history['created_at'])) ?></div>
                                    <div class="timeline-status">
                                        <?php if ($history['old_status']): ?>
                                            <?= ucfirst(str_replace('_', ' ', $history['old_status'])) ?> → 
                                        <?php endif; ?>
                                        <?= ucfirst(str_replace('_', ' ', $history['new_status'])) ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #7f8c8d;">
                                        by <?= htmlspecialchars($history['changed_by_name']) ?>
                                    </div>
                                    <?php if ($history['notes']): ?>
                                        <div style="font-size: 0.85rem; color: #555; margin-top: 5px; font-style: italic;">
                                            <?= htmlspecialchars($history['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
