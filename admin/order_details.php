<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Order Details';
$adminPage = 'payments';

$orderId = intval($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: payment_verification.php');
    exit;
}

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
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
            // Update order to accepted and mark payment as completed
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
                // Audit log
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
            
            // Update order to failed
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
                // Audit log
                $historyStmt = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
                $adminId = $_SESSION['user']['id'] ?? null;
                $historyStmt->execute([$orderId, $previousStatus, 'cancelled', $adminId, 'Business QR payment rejected: ' . $rejectReason]);

                $_SESSION['flash_message'] = 'Payment rejected and order cancelled.';
                $_SESSION['flash_type'] = 'error';
            }
        } elseif ($action === 'add_note') {
            $adminNote = trim($_POST['admin_note'] ?? '');
            if ($adminNote) {
                $stmt = $db->prepare("UPDATE orders SET admin_notes = ? WHERE id = ?");
                $stmt->execute([$adminNote, $orderId]);
                
                $_SESSION['flash_message'] = 'Admin note added successfully.';
                $_SESSION['flash_type'] = 'success';
            }
        }
        
        header('Location: order_details.php?order_id=' . $orderId);
        exit;
        
    } catch (Exception $e) {
        error_log("Order action error: " . $e->getMessage());
        $_SESSION['flash_message'] = 'Error processing action: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

// Fetch order details
$db = get_db_connection();
$orderQuery = "
    SELECT 
        o.*,
        u.name as customer_name,
        u.email as customer_email,
        v.name as verified_by_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN users v ON o.verified_by = v.id
    WHERE o.id = ?
";
$stmt = $db->prepare($orderQuery);
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: payment_verification.php');
    exit;
}

// Fetch order items
$itemsQuery = "
    SELECT 
        oi.*,
        p.name as product_name,
        p.price as product_price
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
";
$itemsStmt = $db->prepare($itemsQuery);
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.order-details-container {
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

.btn-back {
    padding: 10px 20px;
    background: #6b7280;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
}

.btn-back:hover {
    background: #4b5563;
    color: white;
}

.details-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.detail-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e7eb;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 14px;
}

.info-value {
    color: #1f2937;
    font-size: 14px;
    text-align: right;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-pending { background: #fef3c7; color: #92400e; }
.badge-accepted { background: #d1fae5; color: #065f46; }
.badge-processing { background: #dbeafe; color: #1e40af; }
.badge-shipped { background: #e0e7ff; color: #3730a3; }
.badge-delivered { background: #d1fae5; color: #065f46; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }
.badge-completed { background: #d1fae5; color: #065f46; }
.badge-failed { background: #fee2e2; color: #991b1b; }

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.items-table th {
    background: #f9fafb;
    padding: 12px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.items-table td {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.action-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-action {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.btn-approve {
    background: #10b981;
    color: white;
}

.btn-approve:hover {
    background: #059669;
}

.btn-reject {
    background: #ef4444;
    color: white;
}

.btn-reject:hover {
    background: #dc2626;
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
    font-family: inherit;
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

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.note-box {
    background: #f9fafb;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #7c3aed;
    margin-top: 15px;
}

.note-text {
    font-size: 14px;
    color: #374151;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="order-details-container">
    <div class="page-header">
        <h1 class="page-title">Order #<?= $order['id']; ?> Details</h1>
        <a href="payment_verification.php" class="btn-back">← Back to Payments</a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type']; ?>">
        <?= htmlspecialchars($_SESSION['flash_message']); ?>
    </div>
    <?php 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    endif; 
    ?>

    <div class="details-grid">
        <!-- Left Column: Order & Customer Info -->
        <div>
            <!-- Order Summary -->
            <div class="detail-card">
                <h2 class="card-title">Order Summary</h2>
                
                <div class="info-row">
                    <span class="info-label">Order Number</span>
                    <span class="info-value"><strong>#<?= $order['id']; ?></strong></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Order Date & Time</span>
                    <span class="info-value"><?= date('M d, Y H:i:s', strtotime($order['created_at'])); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Order Status</span>
                    <span class="info-value">
                        <?php
                        $orderStatus = $order['order_status'] ?? 'pending';
                        $statusClass = 'badge-' . strtolower($orderStatus);
                        ?>
                        <span class="badge <?= $statusClass; ?>"><?= ucfirst($orderStatus); ?></span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value"><strong><?= strtoupper($order['payment_method'] ?? 'N/A'); ?></strong></span>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="detail-card" style="margin-top: 20px;">
                <h2 class="card-title">Customer Information</h2>
                
                <div class="info-row">
                    <span class="info-label">Customer Name</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></span>
                </div>
                
                <?php if (!empty($order['shipping_address'])): ?>
                <div class="info-row">
                    <span class="info-label">Shipping Address</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($order['shipping_address'])); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Order Items -->
            <div class="detail-card" style="margin-top: 20px;">
                <h2 class="card-title">Order Items</h2>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?></td>
                            <td>₹<?= number_format($item['price'] ?? 0, 2); ?></td>
                            <td><?= $item['quantity']; ?></td>
                            <td><strong>₹<?= number_format(($item['price'] ?? 0) * $item['quantity'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column: Payment Details & Actions -->
        <div>
            <!-- Payment Details -->
            <div class="detail-card">
                <h2 class="card-title">Payment Details</h2>
                
                <div class="info-row">
                    <span class="info-label">Order Amount</span>
                    <span class="info-value"><strong>₹<?= number_format($order['total_amount'], 2); ?></strong></span>
                </div>
                
                <?php if (!empty($order['transaction_id'])): ?>
                <div class="info-row">
                    <span class="info-label">Transaction ID</span>
                    <span class="info-value">
                        <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 13px;">
                            <?= htmlspecialchars($order['transaction_id']); ?>
                        </code>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label">Payment Status</span>
                    <span class="info-value">
                        <?php
                        $paymentStatus = $order['payment_status'] ?? 'pending';
                        $paymentStatusClass = 'badge-' . strtolower($paymentStatus);
                        ?>
                        <span class="badge <?= $paymentStatusClass; ?>"><?= ucfirst($paymentStatus); ?></span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Submission Date</span>
                    <span class="info-value"><?= date('M d, Y H:i', strtotime($order['created_at'])); ?></span>
                </div>
                
                <?php if ($order['verified_at']): ?>
                <div class="info-row">
                    <span class="info-label">Verified Date</span>
                    <span class="info-value"><?= date('M d, Y H:i', strtotime($order['verified_at'])); ?></span>
                </div>
                
                <?php if ($order['verified_by_name']): ?>
                <div class="info-row">
                    <span class="info-label">Verified By</span>
                    <span class="info-value"><?= htmlspecialchars($order['verified_by_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($order['admin_notes'])): ?>
                <div class="note-box">
                    <strong style="font-size: 12px; color: #6b7280; text-transform: uppercase;">Admin Notes:</strong>
                    <p class="note-text"><?= nl2br(htmlspecialchars($order['admin_notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Admin Actions -->
            <?php if ($order['payment_status'] === 'pending'): ?>
            <div class="action-section">
                <h2 class="card-title">Admin Actions</h2>
                <p style="font-size: 14px; color: #6b7280; margin-bottom: 15px;">
                    Verify the payment details and approve or reject this order.
                </p>
                
                <div class="action-buttons">
                    <button class="btn-action btn-approve" onclick="approvePayment(<?= $order['id']; ?>)">
                        ✓ Approve Payment
                    </button>
                    <button class="btn-action btn-reject" onclick="showRejectModal(<?= $order['id']; ?>)">
                        ✗ Reject Payment
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Add Admin Note -->
            <div class="action-section">
                <h2 class="card-title">Add Admin Note</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    <div class="form-group">
                        <textarea name="admin_note" class="form-input" rows="4" placeholder="Add internal notes (not visible to customers)..."><?= htmlspecialchars($order['admin_notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn-action" style="background: #7c3aed; color: white; width: 100%;">
                        Save Note
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h3 class="modal-title">Reject Payment</h3>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            
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
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showRejectModal(orderId) {
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
