<?php
/**
 * Admin Panel - Sales Order Detail
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment_adjustment_helper.php';
require_once __DIR__ . '/../includes/order_deletion_helper.php';
require_admin();

$pageTitle = 'Sales Order Detail';
$adminPage = 'sales_orders';

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: ' . base_url('admin/sales_orders.php'));
    exit;
}

$order = db_fetch('SELECT so.*, sp.shop_name, sp.owner_name, sp.phone as party_phone, sp.address as party_address, sp.district as party_district, sp.email as party_email, sp.outstanding_amount, sp.credit_limit, sp.gst_number, se.name as exec_name, se.email as exec_email, se.phone as exec_phone, se.district as exec_district, se.location as exec_location, se.reporting_manager FROM sales_orders so JOIN sales_parties sp ON so.party_id = sp.id JOIN sales_executives se ON so.executive_id = se.id WHERE so.id = ?', [$orderId]);

if (!$order) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Order not found.'];
    header('Location: ' . base_url('admin/sales_orders.php'));
    exit;
}

$items = db_fetch_all('SELECT * FROM sales_order_items WHERE order_id = ?', [$orderId]);

// Handle action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    switch ($action) {
        case 'approve':
            if ($order['status'] !== 'pending') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order already processed (status: ' . $order['status'] . ').']; break; }
            db_query('UPDATE sales_orders SET status="approved", approved_at=NOW(), admin_notes=? WHERE id=? AND status="pending"', [$adminNotes, $orderId]);
            recalculate_party_outstanding((int)$order['party_id']);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order approved.'];
            break;
        case 'reject':
            if ($order['status'] !== 'pending') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order already processed (status: ' . $order['status'] . ').']; break; }
            db_query('UPDATE sales_orders SET status="rejected", approved_at=NOW(), admin_notes=? WHERE id=? AND status="pending"', [$adminNotes, $orderId]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order rejected.'];
            break;
        case 'dispatch':
            if ($order['status'] !== 'approved') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order must be approved before dispatching.']; break; }
            db_query('UPDATE sales_orders SET status="dispatched", dispatched_at=NOW(), admin_notes=? WHERE id=? AND status="approved"', [$adminNotes, $orderId]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order dispatched.'];
            break;
        case 'deliver':
            if ($order['status'] !== 'dispatched') { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Order must be dispatched before delivering.']; break; }
            db_query('UPDATE sales_orders SET status="delivered", delivered_at=NOW(), admin_notes=? WHERE id=? AND status="dispatched"', [$adminNotes, $orderId]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Order delivered.'];
            break;
        case 'cancel':
            if (in_array($order['status'], ['cancelled', 'delivered'])) { $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Cannot cancel a ' . $order['status'] . ' order.']; break; }
            db_query('UPDATE sales_orders SET status="cancelled", admin_notes=? WHERE id=?', [$adminNotes, $orderId]);
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
                'Full payment received - admin',
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
            db_query('UPDATE sales_orders SET payment_status="pending", payment_amount=0 WHERE id=?', [$orderId]);
            recalculate_party_outstanding((int)$order['party_id']);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment reset to pending.'];
            break;
        case 'delete':
            $result = delete_order_cascade($orderId);
            if ($result['success']) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => $result['message']];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
            }
            header('Location: ' . base_url('admin/sales_orders.php'));
            exit;
    }
    header('Location: ' . base_url('admin/sales_order_detail.php?id=' . $orderId));
    exit;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- v2-delete-enabled -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="<?= base_url('admin/sales_orders.php') ?>" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left me-1"></i>Back to Sales Orders</a>
            <h2 class="fw-bold mb-0 mt-1"><?= htmlspecialchars($order['order_number']) ?></h2>
        </div>
        <div class="d-flex gap-2">
            <?php
            $statusBg = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','dispatched'=>'info','delivered'=>'success','cancelled'=>'secondary'];
            $payBg = ['pending'=>'warning','partial'=>'info','received'=>'success'];
            ?>
            <span class="badge bg-<?= $statusBg[$order['status']] ?? 'secondary' ?> fs-6 px-3 py-2"><?= ucfirst($order['status']) ?></span>
            <span class="badge bg-<?= $payBg[$order['payment_status']] ?? 'secondary' ?> fs-6 px-3 py-2">Payment: <?= ucfirst($order['payment_status']) ?></span>
            <form method="POST" class="d-inline ms-2">
                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                <button name="action" value="delete" class="btn btn-danger px-3 py-2" onclick="return confirm('PERMANENTLY DELETE this order?\n\nOrder: <?= htmlspecialchars($order['order_number']) ?>\nParty: <?= htmlspecialchars(addslashes($order['shop_name'])) ?>\nAmount: ₹<?= number_format($order['total_amount'], 0) ?>\nStatus: <?= ucfirst($order['status']) ?>\n\nThis will remove the order from EVERYWHERE (admin + sales associate).\nThis action CANNOT be undone.')">
                    <i class="fas fa-trash-alt me-1"></i> Delete Order
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Info Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small mb-3"><i class="fas fa-user-tie text-primary me-2"></i>Sales Executive</h6>
                            <p class="fw-bold mb-1"><?= htmlspecialchars($order['exec_name']) ?></p>
                            <p class="text-muted small mb-1"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['exec_phone']) ?></p>
                            <p class="text-muted small mb-1"><i class="fas fa-map-pin me-1"></i><?= htmlspecialchars($order['exec_district']) ?>, <?= htmlspecialchars($order['exec_location']) ?></p>
                            <p class="text-muted small mb-0"><i class="fas fa-user me-1"></i>Reports to: <?= htmlspecialchars($order['reporting_manager'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small mb-3"><i class="fas fa-store text-primary me-2"></i>Party Details</h6>
                            <p class="fw-bold mb-1"><?= htmlspecialchars($order['shop_name']) ?></p>
                            <p class="text-muted small mb-1"><i class="fas fa-user me-1"></i><?= htmlspecialchars($order['owner_name']) ?></p>
                            <p class="text-muted small mb-1"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['party_phone']) ?></p>
                            <p class="text-muted small mb-0"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($order['party_district']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small mb-3"><i class="fas fa-wallet text-primary me-2"></i>Financial</h6>
                            <p class="mb-1"><span class="text-muted small">Outstanding:</span> <span class="fw-bold text-danger">₹<?= number_format($order['outstanding_amount'], 0) ?></span></p>
                            <p class="mb-1"><span class="text-muted small">Credit Limit:</span> <span class="fw-bold">₹<?= number_format($order['credit_limit'], 0) ?></span></p>
                            <?php if ($order['gst_number']): ?>
                            <p class="mb-0"><span class="text-muted small">GST:</span> <span class="fw-bold"><?= htmlspecialchars($order['gst_number']) ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-boxes text-primary me-2"></i>Order Items (<?= count($items) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $i => $item): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></span>
                                        <?php if (!empty($item['is_custom_price'])): ?>
                                        <br><span class="badge bg-purple text-white" style="font-size:10px;background:#7c3aed !important;">
                                            <i class="fas fa-tag"></i> Custom Price
                                            <?php if (!empty($item['original_price']) && (float)$item['original_price'] != (float)$item['price']): ?>
                                                <span style="text-decoration:line-through;opacity:0.7;">₹<?= number_format($item['original_price'], 2) ?></span>
                                                → ₹<?= number_format($item['price'], 2) ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($item['sku'] ?: '-') ?></td>
                                    <td>₹<?= number_format($item['price'], 2) ?></td>
                                    <td class="fw-bold"><?= $item['quantity'] ?></td>
                                    <td class="fw-bold">₹<?= number_format($item['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4"></td>
                                    <td class="fw-bold">Subtotal</td>
                                    <td class="fw-bold">₹<?= number_format($order['subtotal'], 2) ?></td>
                                </tr>
                                <?php if ($order['discount_amount'] > 0): ?>
                                <tr class="table-light">
                                    <td colspan="4"></td>
                                    <td class="fw-bold text-success">Discount</td>
                                    <td class="fw-bold text-success">-₹<?= number_format($order['discount_amount'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-primary">
                                    <td colspan="4"></td>
                                    <td class="fw-bold fs-5">Total</td>
                                    <td class="fw-bold fs-5">₹<?= number_format($order['total_amount'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if ($order['executive_notes']): ?>
            <div class="alert alert-warning">
                <strong><i class="fas fa-sticky-note me-1"></i>Executive Notes:</strong>
                <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($order['executive_notes'])) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($order['admin_notes']): ?>
            <div class="alert alert-info">
                <strong><i class="fas fa-comment-dots me-1"></i>Admin Notes:</strong>
                <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($order['admin_notes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Actions -->
        <div class="col-lg-4">
            <!-- Timeline -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-history text-primary me-2"></i>Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start mb-3">
                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width:28px;height:28px;flex-shrink:0;font-size:12px;"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="fw-semibold small">Order Placed</div>
                            <div class="text-muted" style="font-size:11px;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php if ($order['approved_at']): ?>
                    <div class="d-flex align-items-start mb-3">
                        <div class="bg-<?= $order['status'] === 'rejected' ? 'danger' : 'success' ?> rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width:28px;height:28px;flex-shrink:0;font-size:12px;"><i class="fas fa-<?= $order['status'] === 'rejected' ? 'times' : 'check' ?>"></i></div>
                        <div>
                            <div class="fw-semibold small"><?= $order['status'] === 'rejected' ? 'Rejected' : 'Approved' ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= date('d M Y, h:i A', strtotime($order['approved_at'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['dispatched_at']): ?>
                    <div class="d-flex align-items-start mb-3">
                        <div class="bg-info rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width:28px;height:28px;flex-shrink:0;font-size:12px;"><i class="fas fa-truck"></i></div>
                        <div>
                            <div class="fw-semibold small">Dispatched</div>
                            <div class="text-muted" style="font-size:11px;"><?= date('d M Y, h:i A', strtotime($order['dispatched_at'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['delivered_at']): ?>
                    <div class="d-flex align-items-start">
                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center text-white me-3" style="width:28px;height:28px;flex-shrink:0;font-size:12px;"><i class="fas fa-check-double"></i></div>
                        <div>
                            <div class="fw-semibold small">Delivered</div>
                            <div class="text-muted" style="font-size:11px;"><?= date('d M Y, h:i A', strtotime($order['delivered_at'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-cogs text-primary me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Admin Notes</label>
                            <textarea name="admin_notes" class="form-control" rows="2" placeholder="Optional notes..."><?= htmlspecialchars($order['admin_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <?php if ($order['status'] === 'pending'): ?>
                                <button name="action" value="approve" class="btn btn-success" onclick="return confirm('Approve this order?')"><i class="fas fa-check me-2"></i>Approve Order</button>
                                <button name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this order?')"><i class="fas fa-times me-2"></i>Reject Order</button>
                            <?php endif; ?>
                            <?php if ($order['status'] === 'approved'): ?>
                                <button name="action" value="dispatch" class="btn btn-info text-white"><i class="fas fa-truck me-2"></i>Mark Dispatched</button>
                            <?php endif; ?>
                            <?php if ($order['status'] === 'dispatched'): ?>
                                <button name="action" value="deliver" class="btn btn-success"><i class="fas fa-check-double me-2"></i>Mark Delivered</button>
                            <?php endif; ?>
                            <?php if ($order['payment_status'] === 'pending' && in_array($order['status'], ['approved','dispatched','delivered'])): ?>
                                <button name="action" value="payment_received" class="btn btn-outline-success"><i class="fas fa-rupee-sign me-2"></i>Mark Payment Received</button>
                            <?php endif; ?>
                            <?php if ($order['payment_status'] === 'received'): ?>
                                <button name="action" value="payment_pending" class="btn btn-outline-warning"><i class="fas fa-undo me-2"></i>Reset Payment to Pending</button>
                            <?php endif; ?>
                            <?php if (!in_array($order['status'], ['cancelled','delivered'])): ?>
                                <button name="action" value="cancel" class="btn btn-outline-danger" onclick="return confirm('Cancel this order?')"><i class="fas fa-ban me-2"></i>Cancel Order</button>
                            <?php endif; ?>
                            <hr class="my-2">
                            <button name="action" value="delete" class="btn btn-danger" onclick="return confirm('PERMANENTLY DELETE this order?\n\nOrder: <?= htmlspecialchars($order['order_number']) ?>\nParty: <?= htmlspecialchars(addslashes($order['shop_name'])) ?>\nAmount: ₹<?= number_format($order['total_amount'], 0) ?>\nStatus: <?= ucfirst($order['status']) ?>\n\nThis will remove the order from EVERYWHERE (admin + sales associate).\nThis action CANNOT be undone.')"><i class="fas fa-trash-alt me-2"></i>Delete Order Permanently</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
