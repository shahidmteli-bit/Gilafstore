<?php
/**
 * Admin Panel - Sales Orders Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_deletion_helper.php';
require_once __DIR__ . '/../includes/payment_adjustment_helper.php';
require_admin();

$pageTitle = 'Sales Orders';
$adminPage = 'sales_orders';

// Auto-fix: recalculate outstanding for all parties on every page load
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    recalculate_all_parties_outstanding();
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($orderId && $action) {
        $order = db_fetch('SELECT * FROM sales_orders WHERE id = ?', [$orderId]);
        if ($order) {
            switch ($action) {
                case 'approve':
                    if ($order['status'] !== 'pending') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order already processed (status: ' . $order['status'] . ').']; break; }
                    db_query('UPDATE sales_orders SET status = "approved", approved_at = NOW(), admin_notes = ? WHERE id = ? AND status = "pending"', [$adminNotes, $orderId]);
                    recalculate_party_outstanding((int)$order['party_id']);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order approved.'];
                    break;
                case 'reject':
                    if ($order['status'] !== 'pending') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order already processed (status: ' . $order['status'] . ').']; break; }
                    db_query('UPDATE sales_orders SET status = "rejected", approved_at = NOW(), admin_notes = ? WHERE id = ? AND status = "pending"', [$adminNotes, $orderId]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order rejected.'];
                    break;
                case 'dispatch':
                    if ($order['status'] !== 'approved') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order must be approved before dispatching.']; break; }
                    db_query('UPDATE sales_orders SET status = "dispatched", dispatched_at = NOW(), admin_notes = ? WHERE id = ? AND status = "approved"', [$adminNotes, $orderId]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order marked as dispatched.'];
                    break;
                case 'deliver':
                    if ($order['status'] !== 'dispatched') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order must be dispatched before delivering.']; break; }
                    db_query('UPDATE sales_orders SET status = "delivered", delivered_at = NOW(), admin_notes = ? WHERE id = ? AND status = "dispatched"', [$adminNotes, $orderId]);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order marked as delivered.'];
                    break;
                case 'cancel':
                    if (in_array($order['status'], ['cancelled', 'delivered'])) { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Cannot cancel a ' . $order['status'] . ' order.']; break; }
                    db_query('UPDATE sales_orders SET status = "cancelled", admin_notes = ? WHERE id = ?', [$adminNotes, $orderId]);
                    recalculate_party_outstanding((int)$order['party_id']);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order cancelled.'];
                    break;
                case 'payment_received':
                    if ($order['payment_status'] === 'received') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Payment already marked as received.']; break; }
                    
                    // Check for duplicate payment voucher
                    $refNum = 'ADMIN-PAY-' . $order['order_number'];
                    if (is_duplicate_payment_voucher((int)$order['party_id'], $refNum)) {
                        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Payment voucher already exists for this order. Duplicate prevented.'];
                        break;
                    }
                    
                    // Use payment adjustment helper to prevent duplicates
                    $result = adjust_payment_to_orders(
                        (int)$order['party_id'],
                        (float)$order['total_amount'],
                        'admin_direct',
                        $refNum,
                        'Full payment received - admin marked',
                        $_SESSION['admin_id'] ?? 0,
                        null
                    );
                    
                    if ($result['success']) {
                        recalculate_party_outstanding((int)$order['party_id']);
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment marked as received. Adjusted: ₹' . number_format($result['total_adjusted'], 2)];
                    } else {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Payment adjustment failed: ' . ($result['error'] ?? 'Unknown error')];
                    }
                    break;
                case 'payment_pending':
                    if ($order['payment_status'] === 'pending') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Payment is already pending.']; break; }
                    db_query('UPDATE sales_orders SET payment_status = "pending", payment_amount = 0 WHERE id = ?', [$orderId]);
                    recalculate_party_outstanding((int)$order['party_id']);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment status reset to pending.'];
                    break;
                case 'delete':
                    $result = delete_order_cascade($orderId);
                    if ($result['success']) {
                        $_SESSION['flash'] = ['type' => 'success', 'message' => $result['message']];
                    } else {
                        $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
                    }
                    break;
            }
        }
        header('Location: ' . base_url('admin/sales_orders.php?status=' . ($_GET['status'] ?? '')));
        exit;
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = 'SELECT so.*, sp.shop_name, sp.owner_name, se.name as exec_name, se.district as exec_district FROM sales_orders so JOIN sales_parties sp ON so.party_id = sp.id JOIN sales_executives se ON so.executive_id = se.id WHERE 1=1';
$params = [];

if ($statusFilter) {
    $sql .= ' AND so.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $sql .= ' AND (so.order_number LIKE ? OR sp.shop_name LIKE ? OR se.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY so.created_at DESC';
$orders = db_fetch_all($sql, $params);

// Status counts
$counts = [];
$countRows = db_fetch_all('SELECT status, COUNT(*) as cnt FROM sales_orders GROUP BY status');
foreach ($countRows as $r) $counts[$r['status']] = $r['cnt'];
$totalCount = array_sum($counts);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- v2-delete-enabled -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Orders</h2>
            <p class="text-muted mb-0">Manage orders from field sales executives</p>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="<?= base_url('admin/sales_orders.php') ?>" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">All (<?= $totalCount ?>)</a>
        <?php foreach (['pending','approved','rejected','dispatched','delivered','cancelled'] as $s): ?>
            <a href="<?= base_url('admin/sales_orders.php?status=' . $s) ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= ucfirst($s) ?> (<?= $counts[$s] ?? 0 ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
        <div class="input-group" style="max-width:400px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search order #, party, executive..." value="<?= htmlspecialchars($search) ?>">
        </div>
    </form>

    <!-- Orders Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Executive</th>
                            <th>Party</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th style="min-width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="fas fa-clipboard-list fa-3x mb-3 d-block"></i>No orders found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <a href="<?= base_url('admin/sales_order_detail.php?id=' . $order['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                    <?= htmlspecialchars($order['order_number']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($order['exec_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($order['exec_district']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($order['shop_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($order['owner_name']) ?></small>
                            </td>
                            <td>
                                <?php if ($order['order_type'] === 'return'): ?>
                                    <span class="badge bg-warning text-dark">Return</span>
                                <?php elseif ($order['order_type'] === 'credit_note'): ?>
                                    <span class="badge bg-info">Credit Note</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Order</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold">₹<?= number_format($order['total_amount'], 0) ?></td>
                            <td>
                                <?php
                                $statusBg = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','dispatched'=>'info','delivered'=>'success','cancelled'=>'secondary'];
                                ?>
                                <span class="badge bg-<?= $statusBg[$order['status']] ?? 'secondary' ?>"><?= ucfirst($order['status']) ?></span>
                            </td>
                            <td>
                                <?php
                                $payBg = ['pending'=>'warning','partial'=>'info','received'=>'success'];
                                ?>
                                <span class="badge bg-<?= $payBg[$order['payment_status']] ?? 'secondary' ?>"><?= ucfirst($order['payment_status']) ?></span>
                            </td>
                            <td class="text-muted small"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="<?= base_url('admin/sales_order_detail.php?id=' . $order['id']) ?>" class="btn btn-outline-primary btn-sm" title="View"><i class="fas fa-eye"></i></a>

                                    <?php if ($order['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="approve">
                                            <button class="btn btn-success btn-sm" title="Approve" onclick="return confirm('Approve this order?')"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="reject">
                                            <button class="btn btn-danger btn-sm" title="Reject" onclick="return confirm('Reject this order?')"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($order['status'] === 'approved'): ?>
                                        <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="dispatch">
                                            <button class="btn btn-info btn-sm text-white" title="Mark Dispatched"><i class="fas fa-truck"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($order['status'] === 'dispatched'): ?>
                                        <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="deliver">
                                            <button class="btn btn-success btn-sm" title="Mark Delivered"><i class="fas fa-check-double"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($order['payment_status'] === 'pending' && in_array($order['status'], ['approved','dispatched','delivered'])): ?>
                                        <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="payment_received">
                                            <button class="btn btn-outline-success btn-sm" title="Mark Payment Received"><i class="fas fa-rupee-sign"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!in_array($order['status'], ['cancelled','delivered'])): ?>
                                        <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="cancel">
                                            <button class="btn btn-outline-danger btn-sm" title="Cancel" onclick="return confirm('Cancel this order?')"><i class="fas fa-ban"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="d-inline"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><input type="hidden" name="action" value="delete">
                                        <button class="btn btn-danger btn-sm" title="Delete Permanently" onclick="return confirm('PERMANENTLY DELETE this order?\n\nOrder: <?= htmlspecialchars($order['order_number']) ?>\nParty: <?= htmlspecialchars(addslashes($order['shop_name'])) ?>\nAmount: ₹<?= number_format($order['total_amount'], 0) ?>\n\nThis will remove the order from EVERYWHERE (admin + sales associate). This action CANNOT be undone.')"><i class="fas fa-trash-alt"></i></button>
                                    </form>
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

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
