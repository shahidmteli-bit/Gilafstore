<?php
/**
 * USER PORTAL - MY SUPPORT TICKETS
 * View and manage personal support tickets
 */

$pageTitle = 'My Support Tickets — Gilaf Store';
$activePage = 'support';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_functions.php';

// Require user to be logged in
if (!is_logged_in()) {
    redirect_with_message('/user/login.php', 'Please login to view your tickets', 'info');
}

$userId = $_SESSION['user']['id'];
$userName = $_SESSION['user']['name'];
$userEmail = $_SESSION['user']['email'];

// Get specific ticket if requested
$viewTicketId = $_GET['ticket'] ?? '';
$viewTicket = null;
if ($viewTicketId) {
    $viewTicket = get_ticket_by_id($viewTicketId, $userId, $userEmail);
    if ($viewTicket) {
        $ticketComments = get_ticket_comments($viewTicketId, false);
    }
}

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $ticketId = $_POST['ticket_id'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    
    if (!empty($comment) && !empty($ticketId)) {
        $result = add_ticket_comment($ticketId, $userId, $userName, $userEmail, $comment, false, false);
        if ($result['success']) {
            redirect_with_message("my_tickets.php?ticket=$ticketId", 'Your response has been added', 'success');
        }
    }
}

// Get user's tickets
$filters = ['status' => $_GET['status'] ?? ''];
$userTickets = get_user_tickets($userId, $filters, $userEmail);

$ticketCounts = [
    'total' => count($userTickets),
    'new' => 0,
    'acknowledged' => 0,
    'in_progress' => 0,
    'on_hold' => 0,
    'resolved' => 0,
];
foreach ($userTickets as $t) {
    $status = $t['status'] ?? '';
    if (isset($ticketCounts[$status])) {
        $ticketCounts[$status]++;
    }
}

include __DIR__ . '/../includes/new-header.php';
?>

<style>
    .tickets-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .page-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .page-header h1 {
        font-family: 'Playfair Display', serif;
        color: #1A3C34;
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: #7f8c8d;
        font-size: 1.1rem;
    }
    
    .create-ticket-btn {
        display: inline-block;
        padding: 14px 30px;
        background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: transform 0.2s;
        margin-bottom: 30px;
    }
    
    .create-ticket-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(26, 60, 52, 0.3);
    }
    
    .filters {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .filters select {
        padding: 10px 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .ticket-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    
    .ticket-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    }
    
    .ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    
    .ticket-id {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #3498db;
        font-size: 0.9rem;
    }
    
    .ticket-subject {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 10px 0;
    }
    
    .ticket-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 0.9rem;
        color: #7f8c8d;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-new { background: #3498db; color: white; }
    .badge-open { background: #3498db; color: white; }
    .badge-acknowledged { background: #9b59b6; color: white; }
    .badge-in-progress { background: #f39c12; color: white; }
    .badge-on-hold { background: #e67e22; color: white; }
    .badge-resolved { background: #27ae60; color: white; }
    .badge-closed { background: #95a5a6; color: white; }
    
    .ticket-detail-view {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .detail-header {
        background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    
    .description-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #3498db;
        margin: 20px 0;
        line-height: 1.8;
    }
    
    .comment {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #3498db;
    }
    
    .comment.admin {
        background: #e8f8f5;
        border-left-color: #27ae60;
    }
    
    .comment-header {
        display: flex;
        justify-content: space-between;
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
        background: #1A3C34;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2d5a4d;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #7f8c8d;
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    
    .back-link {
        display: inline-block;
        color: #3498db;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 20px;
    }
    
    .back-link:hover {
        text-decoration: underline;
    }

    /* --- Modern Glass / Cinematic Overrides (List View) --- */
    .tickets-shell {
        min-height: 70vh;
        padding: 60px 0 30px 0;
        background:
            radial-gradient(900px 500px at 15% 10%, rgba(197, 160, 89, 0.14), rgba(0,0,0,0) 60%),
            radial-gradient(900px 500px at 85% 20%, rgba(26, 60, 52, 0.18), rgba(0,0,0,0) 60%),
            linear-gradient(135deg, rgba(26, 60, 52, 0.03) 0%, rgba(197, 160, 89, 0.03) 100%);
    }

    .glass {
        background: rgba(255, 255, 255, 0.78);
        border: 1px solid rgba(255, 255, 255, 0.55);
        border-radius: 18px;
        box-shadow: 0 18px 60px rgba(15, 23, 42, 0.12);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }

    .tickets-hero {
        padding: 26px;
        margin-bottom: 18px;
        position: relative;
        overflow: hidden;
    }

    .tickets-hero::before {
        content: '';
        position: absolute;
        inset: -120px;
        background: radial-gradient(circle, rgba(197, 160, 89, 0.18) 0%, rgba(197, 160, 89, 0) 55%);
        transform: translate3d(-20px, -10px, 0);
        pointer-events: none;
    }

    .tickets-hero-top {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-start;
        position: relative;
        z-index: 1;
    }

    .tickets-hero-title {
        margin: 0;
        font-family: 'Playfair Display', serif;
        color: #1A3C34;
        font-size: 2.2rem;
        line-height: 1.1;
    }

    .tickets-hero-subtitle {
        margin: 10px 0 0 0;
        color: rgba(44, 62, 80, 0.72);
        font-size: 1.02rem;
    }

    .btn-glass {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 14px;
        text-decoration: none;
        font-weight: 800;
        border: 1px solid rgba(26, 60, 52, 0.14);
        background: rgba(255, 255, 255, 0.65);
        color: #1A3C34;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.10);
    }

    .btn-glass:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
    }

    .btn-glass.primary {
        background: linear-gradient(135deg, rgba(26, 60, 52, 0.98) 0%, rgba(45, 90, 77, 0.95) 100%);
        border-color: rgba(26, 60, 52, 0.2);
        color: #fff;
    }

    .tickets-chips {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px;
        position: relative;
        z-index: 1;
    }

    .tickets-chip {
        padding: 12px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.55);
        border: 1px solid rgba(26, 60, 52, 0.10);
    }

    .tickets-chip .label {
        font-size: 11px;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        color: rgba(44, 62, 80, 0.66);
        font-weight: 900;
    }

    .tickets-chip .value {
        margin-top: 6px;
        font-size: 18px;
        font-weight: 900;
        color: #1A3C34;
    }

    @media (max-width: 1024px) {
        .tickets-chips { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 640px) {
        .tickets-chips { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .tickets-hero-title { font-size: 1.9rem; }
    }

    .filters.glass {
        padding: 14px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(255, 255, 255, 0.55);
    }

    .ticket-card.glass {
        border-radius: 18px;
    }

    .verified-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin-right: 8px;
        vertical-align: -4px;
    }
</style>

<section class="tickets-shell">
    <div class="tickets-container">
        <?php if ($viewTicket): ?>
            <!-- Single Ticket Detail View -->
            <a href="my_tickets.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to All Tickets
            </a>
            
            <div class="detail-header">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 5px;">
                            Ticket Number: <?= htmlspecialchars($viewTicket['ticket_id']) ?>
                        </div>
                        <h2 style="margin: 0; font-size: 2rem;"><?= htmlspecialchars($viewTicket['subject']) ?></h2>
                    </div>
                    <div>
                        <span class="badge badge-<?= str_replace('_', '-', $viewTicket['status']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $viewTicket['status'])) ?>
                        </span>
                    </div>
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2); display: flex; gap: 30px; flex-wrap: wrap; font-size: 0.9rem;">
                    <div>
                        <strong>Issue Type:</strong> <?= ucfirst(str_replace('_', ' ', $viewTicket['issue_type'])) ?>
                    </div>
                    <div>
                        <strong>Priority:</strong> <?= ucfirst($viewTicket['priority']) ?>
                    </div>
                    <div>
                        <strong>Created:</strong> <?= date('M d, Y - h:i A', strtotime($viewTicket['created_at'])) ?>
                    </div>
                    <div>
                        <strong>Last Updated:</strong> <?= date('M d, Y - h:i A', strtotime($viewTicket['updated_at'])) ?>
                    </div>
                </div>
            </div>
            
            <div class="ticket-detail-view">
                <h3 style="color: #2c3e50; margin-bottom: 20px;"><i class="fas fa-file-alt"></i> Your Request</h3>
                <div class="description-box">
                    <?= nl2br(htmlspecialchars($viewTicket['description'])) ?>
                </div>
            </div>
            
            <div class="ticket-detail-view">
                <h3 style="color: #2c3e50; margin-bottom: 20px;"><i class="fas fa-comments"></i> Conversation</h3>
                
                <?php if (empty($ticketComments)): ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">
                        No responses yet. Our support team will respond shortly.
                    </p>
                <?php else: ?>
                    <?php foreach ($ticketComments as $comment): ?>
                        <div class="comment <?= $comment['is_admin'] ? 'admin' : '' ?>">
                            <div class="comment-header">
                                <span class="comment-author">
                                    <?= htmlspecialchars($comment['commenter_name']) ?>
                                    <?php if ($comment['is_admin']): ?>
                                        <span class="badge" style="background: #27ae60; color: white; margin-left: 5px; font-size: 10px;">Support Team</span>
                                    <?php endif; ?>
                                </span>
                                <span class="comment-time"><?= date('M d, Y - h:i A', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <div style="color: #555; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!in_array($viewTicket['status'], ['resolved', 'closed'])): ?>
                    <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #e9ecef;">
                        <h4 style="color: #2c3e50; margin-bottom: 20px;"><i class="fas fa-reply"></i> Add Your Response</h4>
                        <form method="POST">
                            <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($viewTicket['ticket_id']) ?>">
                            <div class="form-group">
                                <label class="form-label">Your Message</label>
                                <textarea name="comment" class="form-control" placeholder="Type your message here..." required></textarea>
                            </div>
                            <button type="submit" name="add_comment" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="background: #d4edda; border-left: 4px solid #27ae60; padding: 15px; border-radius: 8px; margin-top: 20px; color: #155724;">
                        <strong>
                            <span class="verified-badge" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" role="img" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2l2.2 1.4 2.6-.2 1.2 2.3 2.4 1.1-.2 2.6L22 12l-1.4 2.2.2 2.6-2.3 1.2-1.1 2.4-2.6-.2L12 22l-2.2-1.4-2.6.2-1.2-2.3-2.4-1.1.2-2.6L2 12l1.4-2.2-.2-2.6L5.5 6l1.1-2.4 2.6.2L12 2z" fill="#3B82F6"/>
                                    <path d="M10.1 14.6l-2.2-2.2a1 1 0 10-1.4 1.4l2.9 2.9a1 1 0 001.4 0l6.3-6.3a1 1 0 10-1.4-1.4l-5.6 5.6z" fill="#FFFFFF"/>
                                </svg>
                            </span>
                            This ticket has been <?= $viewTicket['status'] ?>.
                        </strong>
                        <p style="margin: 10px 0 0 0;">If you need further assistance, please create a new ticket.</p>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- Ticket List View -->
            <div class="tickets-hero glass">
                <div class="tickets-hero-top">
                    <div>
                        <h1 class="tickets-hero-title">My Support Tickets</h1>
                        <p class="tickets-hero-subtitle">Track updates, reply instantly, and keep everything in one place.</p>
                    </div>
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                        <a href="create_ticket.php" class="btn-glass primary">
                            <i class="fas fa-plus"></i> Create Ticket
                        </a>
                        <a href="shop.php" class="btn-glass">
                            <i class="fas fa-store"></i> Continue Shopping
                        </a>
                    </div>
                </div>

                <div class="tickets-chips">
                    <div class="tickets-chip">
                        <div class="label">Total</div>
                        <div class="value"><?= (int)($ticketCounts['total'] ?? 0) ?></div>
                    </div>
                    <div class="tickets-chip">
                        <div class="label">New</div>
                        <div class="value"><?= (int)($ticketCounts['new'] ?? 0) ?></div>
                    </div>
                    <div class="tickets-chip">
                        <div class="label">Acknowledged</div>
                        <div class="value"><?= (int)($ticketCounts['acknowledged'] ?? 0) ?></div>
                    </div>
                    <div class="tickets-chip">
                        <div class="label">In Progress</div>
                        <div class="value"><?= (int)($ticketCounts['in_progress'] ?? 0) ?></div>
                    </div>
                    <div class="tickets-chip">
                        <div class="label">On Hold</div>
                        <div class="value"><?= (int)($ticketCounts['on_hold'] ?? 0) ?></div>
                    </div>
                    <div class="tickets-chip">
                        <div class="label">Resolved</div>
                        <div class="value"><?= (int)($ticketCounts['resolved'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <div class="filters glass">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="window.location.href='my_tickets.php?status=' + this.value">
                    <option value="">All Tickets</option>
                    <option value="new" <?= $filters['status'] === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="acknowledged" <?= $filters['status'] === 'acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                    <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="on_hold" <?= $filters['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="resolved" <?= $filters['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
            </div>
            
            <?php if (empty($userTickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Tickets Found</h3>
                    <p>You haven't created any support tickets yet.</p>
                    <a href="create_ticket.php" class="create-ticket-btn" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Create Your First Ticket
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($userTickets as $ticket): ?>
                    <div class="ticket-card glass" onclick="window.location.href='my_tickets.php?ticket=<?= urlencode($ticket['ticket_id']) ?>'">
                        <div class="ticket-header">
                            <div>
                                <div class="ticket-id">Ticket Number: <?= htmlspecialchars($ticket['ticket_id']) ?></div>
                                <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>
                            </div>
                            <span class="badge badge-<?= str_replace('_', '-', $ticket['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                            </span>
                        </div>
                        <div style="color: #555; line-height: 1.6;">
                            <?= mb_substr(htmlspecialchars($ticket['description']), 0, 150) ?>...
                        </div>
                        <div class="ticket-meta">
                            <span><i class="fas fa-tag"></i> <?= ucfirst(str_replace('_', ' ', $ticket['issue_type'])) ?></span>
                            <span><i class="fas fa-flag"></i> <?= ucfirst($ticket['priority']) ?> Priority</span>
                            <span><i class="fas fa-clock"></i> Created <?= date('M d, Y', strtotime($ticket['created_at'])) ?></span>
                            <span><i class="fas fa-sync-alt"></i> Updated <?= date('M d, Y', strtotime($ticket['updated_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/new-footer.php'; ?>
