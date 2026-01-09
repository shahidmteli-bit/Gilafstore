<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Business QR Payments';
$adminPage = 'payments';

// Handle verification action for Business QR / UPI manual payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $orderId = intval($_POST['order_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($orderId > 0) {
        try {
            $db = get_db_connection();
            
            // Fetch existing order status for audit logging
            $statusStmt = $db->prepare("SELECT order_status, payment_status FROM orders WHERE id = ?");
            $statusStmt->execute([$orderId]);
            $orderRow = $statusStmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderRow) {
                throw new RuntimeException('Order not found');
            }

            $previousStatus = $orderRow['order_status'] ?: 'pending';
            $currentPaymentStatus = $orderRow['payment_status'] ?: 'pending';

            if ($currentPaymentStatus !== 'pending') {
                $_SESSION['flash_message'] = 'This payment is no longer pending or has already been processed.';
                $_SESSION['flash_type'] = 'error';
            } elseif ($action === 'approve') {
                // Update order to accepted and mark payment as completed (only if still pending)
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET payment_status = 'completed', 
                        order_status = 'accepted',
                        verified_at = NOW(),
                        verified_by = ?
                    WHERE id = ? AND payment_status = 'pending'
                ");
                $stmt->execute([$_SESSION['user']['id'], $orderId]);

                if ($stmt->rowCount() === 0) {
                    $_SESSION['flash_message'] = 'This payment is no longer pending or has already been processed.';
                    $_SESSION['flash_type'] = 'error';
                } else {
                    // Audit log: record status change
                    $historyStmt = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
                    $adminId = $_SESSION['user']['id'] ?? null;
                    $historyStmt->execute([$orderId, $previousStatus, 'accepted', $adminId, 'Business QR payment approved']);

                    $_SESSION['flash_message'] = 'Payment verified and order confirmed successfully!';
                    $_SESSION['flash_type'] = 'success';
                }

            } elseif ($action === 'reject') {
                $rejectReason = trim($_POST['reject_reason'] ?? '');
                if ($rejectReason === '') {
                    $rejectReason = 'Payment confirmation failed';
                }
                
                // Update order to failed (only if still pending)
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET payment_status = 'failed', 
                        order_status = 'cancelled',
                        verified_at = NOW(),
                        verified_by = ?,
                        admin_notes = ?
                    WHERE id = ? AND payment_status = 'pending'
                ");
                $stmt->execute([$_SESSION['user']['id'], $rejectReason, $orderId]);

                if ($stmt->rowCount() === 0) {
                    $_SESSION['flash_message'] = 'This payment is no longer pending or has already been processed.';
                    $_SESSION['flash_type'] = 'error';
                } else {
                    // Audit log: record status change with reason
                    $historyStmt = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
                    $adminId = $_SESSION['user']['id'] ?? null;
                    $historyStmt->execute([$orderId, $previousStatus, 'cancelled', $adminId, 'Business QR payment rejected: ' . $rejectReason]);

                    $_SESSION['flash_message'] = 'Payment rejected and order cancelled.';
                    $_SESSION['flash_type'] = 'error';
                }
            }
            
            header('Location: payment_verification.php');
            exit;
            
        } catch (Exception $e) {
            error_log("Payment verification error: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Error processing verification: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }
}

// Fetch Business QR / UPI manual payments
$db = get_db_connection();

$filter = $_GET['filter'] ?? 'pending';
$search = trim($_GET['search'] ?? '');

// Optional date range filter (YYYY-MM-DD)
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

if ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = '';
}
if ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = '';
}

$whereClause = "o.payment_method = 'upi'";

if ($filter === 'pending') {
    $whereClause .= " AND o.payment_status = 'pending'";
} elseif ($filter === 'verified') {
    $whereClause .= " AND o.payment_status = 'completed'";
} elseif ($filter === 'rejected') {
    $whereClause .= " AND o.payment_status = 'failed'";
}

if (!empty($search)) {
    // Allow search by order ID, transaction ID, customer name, or email
    $whereClause .= " AND (CAST(o.id AS CHAR) LIKE :search OR o.transaction_id LIKE :search OR u.name LIKE :search OR u.email LIKE :search)";
}

// Apply date range filter if both dates are provided
if ($fromDate && $toDate) {
    $whereClause .= " AND DATE(o.created_at) BETWEEN '" . $fromDate . "' AND '" . $toDate . "'";
}

$query = "
    SELECT 
        o.id,
        o.user_id,
        o.total_amount,
        o.payment_method,
        o.transaction_id,
        o.payment_status,
        o.order_status,
        o.created_at,
        o.verified_at,
        o.admin_notes,
        u.name as user_name,
        u.email as user_email,
        v.name as verified_by_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN users v ON o.verified_by = v.id
    WHERE {$whereClause}
    ORDER BY o.created_at DESC
";

$stmt = $db->prepare($query);
if (!empty($search)) {
    $searchParam = "%{$search}%";
    $stmt->bindParam(':search', $searchParam);
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (respecting date range if provided)
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_verified_amount
    FROM orders
    WHERE payment_method = 'upi'";

if ($fromDate && $toDate) {
    $statsQuery .= " AND DATE(created_at) BETWEEN '" . $fromDate . "' AND '" . $toDate . "'";
}

$stats = $db->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.verification-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: #1f2937;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
}

.stat-card.pending .stat-value { color: #f59e0b; }
.stat-card.verified .stat-value { color: #10b981; }
.stat-card.rejected .stat-value { color: #ef4444; }

.filters-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.filter-tab {
    padding: 8px 16px;
    border: 1px solid #d1d5db;
    background: white;
    color: #6b7280;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
}

.filter-tab.active {
    background: #7c3aed;
    color: white;
    border-color: #7c3aed;
}

.search-box {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.btn-search {
    padding: 10px 20px;
    background: #7c3aed;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.payments-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f9fafb;
}

th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 16px;
    border-top: 1px solid #e5e7eb;
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-pending { background: #fef3c7; color: #92400e; }
.badge-verified { background: #d1fae5; color: #065f46; }
.badge-rejected { background: #fee2e2; color: #991b1b; }

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.btn-approve {
    background: #10b981;
    color: white;
}

.btn-reject {
    background: #ef4444;
    color: white;
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
    color: #374151;
}

.form-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn-cancel {
    flex: 1;
    padding: 10px;
    background: #e5e7eb;
    color: #374151;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-submit {
    flex: 1;
    padding: 10px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
</style>

<div class="verification-container">
    <div class="page-header">
        <h1 class="page-title">Business QR Payments</h1>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type']; ?>" style="margin-bottom: 20px; padding: 12px 16px; border-radius: 6px; background: <?= $_SESSION['flash_type'] === 'success' ? '#d1fae5' : '#fee2e2'; ?>; color: <?= $_SESSION['flash_type'] === 'success' ? '#065f46' : '#991b1b'; ?>;">
        <?= htmlspecialchars($_SESSION['flash_message']); ?>
    </div>
    <?php 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    endif; 
    ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total UPI Orders</div>
            <div class="stat-value"><?= number_format($stats['total']); ?></div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">Pending Verification</div>
            <div class="stat-value"><?= number_format($stats['pending']); ?></div>
        </div>
        <div class="stat-card verified">
            <div class="stat-label">Verified</div>
            <div class="stat-value"><?= number_format($stats['verified']); ?></div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-label">Rejected</div>
            <div class="stat-value"><?= number_format($stats['rejected']); ?></div>
        </div>
        <div class="stat-card verified">
            <div class="stat-label">Total Verified Amount</div>
            <div class="stat-value">₹<?= number_format($stats['total_verified_amount'], 2); ?></div>
        </div>
    </div>

    <div class="filters-section">
        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : ''; ?>">
                Pending (<?= $stats['pending']; ?>)
            </a>
            <a href="?filter=verified" class="filter-tab <?= $filter === 'verified' ? 'active' : ''; ?>">
                Verified (<?= $stats['verified']; ?>)
            </a>
            <a href="?filter=rejected" class="filter-tab <?= $filter === 'rejected' ? 'active' : ''; ?>">
                Rejected (<?= $stats['rejected']; ?>)
            </a>
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">
                All Orders
            </a>
        </div>

        <form method="GET" class="search-box">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
            <input type="date" name="from_date" class="search-input" value="<?= htmlspecialchars($fromDate); ?>" placeholder="From date">
            <input type="date" name="to_date" class="search-input" value="<?= htmlspecialchars($toDate); ?>" placeholder="To date">
            <input type="text" name="search" class="search-input" placeholder="Search by Order ID, Transaction ID, Customer Name, or Email..." value="<?= htmlspecialchars($search); ?>">
            <button type="submit" class="btn-search">Filter</button>
        </form>
    </div>

    <div class="payments-table">
        <?php if (count($payments) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Payment Method</th>
                    <th>Transaction ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Verified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td>
                        <a href="order_details.php?order_id=<?= $payment['id']; ?>" style="text-decoration: none; font-weight: 600; color: #111827;">
                            #<?= $payment['id']; ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($payment['user_name']); ?></div>
                        <div style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($payment['user_email']); ?></div>
                    </td>
                    <td>
                        <span style="font-size: 13px; font-weight: 600; color: #374151;">
                            <?= htmlspecialchars(strtoupper($payment['payment_method'] ?? 'UPI')); ?>
                        </span>
                    </td>
                    <td>
                        <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 13px;">
                            <?= htmlspecialchars($payment['transaction_id']); ?>
                        </code>
                    </td>
                    <td>
                        <strong>₹<?= number_format($payment['total_amount'], 2); ?></strong>
                    </td>
                    <td>
                        <?php
                        $statusClass = 'badge-pending';
                        $statusText = 'Pending';
                        if ($payment['payment_status'] === 'completed') {
                            $statusClass = 'badge-verified';
                            $statusText = 'Verified';
                        } elseif ($payment['payment_status'] === 'failed') {
                            $statusClass = 'badge-rejected';
                            $statusText = 'Rejected';
                        }
                        ?>
                        <span class="badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                        <?php if (!empty($payment['admin_notes'])): ?>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                Note: <?= htmlspecialchars($payment['admin_notes']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                    <td>
                        <?php if ($payment['verified_at']): ?>
                            <div><?= date('M d, Y H:i', strtotime($payment['verified_at'])); ?></div>
                            <?php if ($payment['verified_by_name']): ?>
                            <div style="font-size: 12px; color: #6b7280;">by <?= htmlspecialchars($payment['verified_by_name']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #9ca3af;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="order_details.php?order_id=<?= $payment['id']; ?>" class="btn-action btn-view">
                                View Order
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
            <h3>No payments found</h3>
            <p>There are no <?= $filter !== 'all' ? $filter : ''; ?> UPI payments at the moment.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h3 class="modal-title">Reject Payment</h3>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="order_id" id="rejectOrderId">
            
            <div class="form-group">
                <label class="form-label">Reason for Rejection</label>
                <textarea name="reject_reason" class="form-input" rows="4" required placeholder="Enter reason for rejecting this payment..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn-submit">Reject Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function approvePayment(orderId) {
    if (confirm('Are you sure you want to approve this payment and confirm the order?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="order_id" value="${orderId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showRejectModal(orderId) {
    document.getElementById('rejectOrderId').value = orderId;
    document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
