<?php
/**
 * ADMIN PANEL - SUPPORT CENTER & ESCALATIONS
 * Main ticket management interface for administrators
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_functions.php';

require_admin();

$pageTitle = 'Support Center & Escalations — Admin';
$adminPage = 'support';

// Get filters from request
$filters = [
    'status' => $_GET['status'] ?? '',
    'issue_type' => $_GET['issue_type'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_filter' => $_GET['date_filter'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Calculate date range based on filter
if (!empty($filters['date_filter']) && $filters['date_filter'] !== 'custom') {
    $today = new DateTime();
    switch ($filters['date_filter']) {
        case 'today':
            $filters['date_from'] = $today->format('Y-m-d');
            $filters['date_to'] = $today->format('Y-m-d');
            break;
        case 'week':
            $filters['date_from'] = $today->modify('monday this week')->format('Y-m-d');
            $filters['date_to'] = (new DateTime())->format('Y-m-d');
            break;
        case 'month':
            $filters['date_from'] = $today->modify('first day of this month')->format('Y-m-d');
            $filters['date_to'] = (new DateTime())->format('Y-m-d');
            break;
        case 'last_month':
            $filters['date_from'] = $today->modify('first day of last month')->format('Y-m-d');
            $filters['date_to'] = $today->modify('last day of last month')->format('Y-m-d');
            break;
        case 'last_2_months':
            $filters['date_from'] = $today->modify('-2 months')->modify('first day of this month')->format('Y-m-d');
            $filters['date_to'] = (new DateTime())->format('Y-m-d');
            break;
        case 'last_3_months':
            $filters['date_from'] = $today->modify('-3 months')->modify('first day of this month')->format('Y-m-d');
            $filters['date_to'] = (new DateTime())->format('Y-m-d');
            break;
    }
}

// Get tickets
$tickets = get_all_tickets($filters);
$stats = get_ticket_statistics();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $ticketId = $_POST['ticket_id'] ?? '';
    $adminName = $_SESSION['user']['name'] ?? 'Admin';
    $adminId = $_SESSION['user']['id'] ?? null;
    
    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? null;
            $result = update_ticket_status($ticketId, $newStatus, $adminId, $adminName, $notes);
            echo json_encode($result);
            exit;
            
        case 'add_comment':
            $comment = $_POST['comment'] ?? '';
            $isInternal = isset($_POST['is_internal']) && $_POST['is_internal'] === '1';
            $result = add_ticket_comment($ticketId, $adminId, $adminName, $_SESSION['user']['email'] ?? '', $comment, $isInternal, true);
            echo json_encode($result);
            exit;
            
        case 'assign_ticket':
            $agentId = $_POST['agent_id'] ?? null;
            $result = assign_ticket($ticketId, $agentId, $adminName);
            echo json_encode($result);
            exit;
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
    .support-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        border-left: 4px solid #3498db;
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    
    .stat-card.urgent {
        border-left-color: #e74c3c;
    }
    
    .stat-card.active {
        border-left-color: #f39c12;
    }
    
    .stat-card.resolved {
        border-left-color: #27ae60;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 10px 0;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filters-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .filter-group {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .filter-group select,
    .filter-group input {
        padding: 10px 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    
    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .tickets-table {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .tickets-table table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .tickets-table th {
        background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .tickets-table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        font-size: 14px;
    }
    
    .tickets-table tr:hover {
        background: #f8f9fa;
    }
    
    .ticket-id {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #3498db;
        cursor: pointer;
    }
    
    .ticket-id:hover {
        text-decoration: underline;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .badge-new {
        background: #3498db;
        color: white;
    }
    
    .badge-open {
        background: #3498db;
        color: white;
    }
    
    .badge-acknowledged {
        background: #9b59b6;
        color: white;
    }
    
    .badge-in-progress {
        background: #f39c12;
        color: white;
    }
    
    .badge-on-hold {
        background: #e67e22;
        color: white;
    }
    
    .badge-resolved {
        background: #27ae60;
        color: white;
    }
    
    .badge-closed {
        background: #95a5a6;
        color: white;
    }
    
    .badge-urgent {
        background: #e74c3c;
        color: white;
    }
    
    .badge-high {
        background: #f39c12;
        color: white;
    }
    
    .badge-medium {
        background: #3498db;
        color: white;
    }
    
    .badge-low {
        background: #95a5a6;
        color: white;
    }
    
    .btn-action {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
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
</style>

<section class="py-4">
    <div class="container-fluid px-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-family: 'Playfair Display', serif; color: #1A3C34; margin: 0;">
                    <i class="fas fa-headset"></i> Support Center & Escalations
                </h2>
                <p style="color: #7f8c8d; margin: 5px 0 0 0;">Manage customer support tickets and escalations</p>
            </div>
            <button class="btn-action btn-primary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="support-stats">
            <div class="stat-card">
                <div class="stat-label">Total Tickets</div>
                <div class="stat-value"><?= number_format($stats['total_tickets'] ?? 0) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">New Tickets</div>
                <div class="stat-value"><?= number_format($stats['new_tickets'] ?? 0) ?></div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-label">Active Tickets</div>
                <div class="stat-value"><?= number_format($stats['active_tickets'] ?? 0) ?></div>
            </div>
            
            <div class="stat-card resolved">
                <div class="stat-label">Resolved</div>
                <div class="stat-value"><?= number_format($stats['resolved_tickets'] ?? 0) ?></div>
            </div>
        </div>
        
        <!-- Filters Bar -->
        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filter-group">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="new" <?= $filters['status'] === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="acknowledged" <?= $filters['status'] === 'acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                        <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="on_hold" <?= $filters['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="resolved" <?= $filters['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                    
                    <select name="issue_type" onchange="this.form.submit()">
                        <option value="">All Issue Types</option>
                        <option value="order" <?= $filters['issue_type'] === 'order' ? 'selected' : '' ?>>Order Issues</option>
                        <option value="product" <?= $filters['issue_type'] === 'product' ? 'selected' : '' ?>>Product Questions</option>
                        <option value="payment" <?= $filters['issue_type'] === 'payment' ? 'selected' : '' ?>>Payment Issues</option>
                        <option value="shipping" <?= $filters['issue_type'] === 'shipping' ? 'selected' : '' ?>>Shipping & Delivery</option>
                        <option value="account" <?= $filters['issue_type'] === 'account' ? 'selected' : '' ?>>Account Support</option>
                        <option value="technical" <?= $filters['issue_type'] === 'technical' ? 'selected' : '' ?>>Technical Issues</option>
                        <option value="other" <?= $filters['issue_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                    
                    <select name="priority" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        <option value="urgent" <?= $filters['priority'] === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                    
                    <select name="date_filter" id="dateFilter" onchange="toggleCustomDates(this.value)">
                        <option value="">All Time</option>
                        <option value="today" <?= $filters['date_filter'] === 'today' ? 'selected' : '' ?>>Current Day</option>
                        <option value="week" <?= $filters['date_filter'] === 'week' ? 'selected' : '' ?>>Current Week</option>
                        <option value="month" <?= $filters['date_filter'] === 'month' ? 'selected' : '' ?>>Current Month</option>
                        <option value="last_month" <?= $filters['date_filter'] === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="last_2_months" <?= $filters['date_filter'] === 'last_2_months' ? 'selected' : '' ?>>Last 2 Months</option>
                        <option value="last_3_months" <?= $filters['date_filter'] === 'last_3_months' ? 'selected' : '' ?>>Last 3 Months</option>
                        <option value="custom" <?= $filters['date_filter'] === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>
                    
                    <div id="customDateRange" style="display: <?= $filters['date_filter'] === 'custom' ? 'flex' : 'none' ?>; gap: 10px; align-items: center;">
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" placeholder="From Date" style="padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px;">
                        <span style="color: #7f8c8d;">to</span>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" placeholder="To Date" style="padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px;">
                    </div>
                    
                    <input type="text" name="search" placeholder="Search tickets..." value="<?= htmlspecialchars($filters['search']) ?>" style="flex: 1; min-width: 250px;">
                    
                    <button type="submit" class="btn-action btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <script>
                    function toggleCustomDates(value) {
                        const customDateRange = document.getElementById('customDateRange');
                        if (value === 'custom') {
                            customDateRange.style.display = 'flex';
                        } else {
                            customDateRange.style.display = 'none';
                            if (value !== '') {
                                document.querySelector('form').submit();
                            }
                        }
                    }
                    </script>
                    
                    <?php if (array_filter($filters)): ?>
                        <a href="support_tickets.php" class="btn-action" style="background: #95a5a6; color: white; text-decoration: none;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Tickets Table -->
        <div class="tickets-table">
            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Tickets Found</h3>
                    <p>There are no support tickets matching your criteria.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Issue Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>
                                    <a href="support_ticket_detail.php?ticket=<?= urlencode($ticket['ticket_id']) ?>" class="ticket-id">
                                        <?= htmlspecialchars($ticket['ticket_id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($ticket['user_name']) ?></strong><br>
                                    <small style="color: #7f8c8d;"><?= htmlspecialchars($ticket['user_email']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($ticket['subject']) ?></strong>
                                </td>
                                <td>
                                    <?= ucfirst(str_replace('_', ' ', $ticket['issue_type'])) ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $ticket['priority'] ?>">
                                        <?= ucfirst($ticket['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= str_replace('_', '-', $ticket['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($ticket['created_at'])) ?><br>
                                    <small style="color: #7f8c8d;"><?= date('h:i A', strtotime($ticket['created_at'])) ?></small>
                                </td>
                                <td>
                                    <a href="support_ticket_detail.php?ticket=<?= urlencode($ticket['ticket_id']) ?>" class="btn-action btn-primary" style="text-decoration: none;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
