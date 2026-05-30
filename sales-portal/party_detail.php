<?php
/**
 * Sales Executive Portal - Party Detail View
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$currentPage = 'parties';

$partyId = (int)($_GET['id'] ?? 0);
if (!$partyId) {
    header('Location: ' . sales_base_url('parties.php'));
    exit;
}

$party = db_fetch('SELECT * FROM sales_parties WHERE id = ? AND created_by = ?', [$partyId, $execId]);
if (!$party) {
    $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Party not found.'];
    header('Location: ' . sales_base_url('parties.php'));
    exit;
}

$pageTitle = $party['shop_name'];

// Fetch orders for this party
$orders = db_fetch_all('SELECT * FROM sales_orders WHERE party_id = ? AND executive_id = ? ORDER BY created_at DESC LIMIT 20', [$partyId, $execId]);

// Fetch payment history
$payments = db_fetch_all('SELECT * FROM sales_payment_history WHERE party_id = ? ORDER BY created_at DESC LIMIT 10', [$partyId]);

// Credit usage percentage
$creditPct = $party['credit_limit'] > 0 ? min(100, ($party['outstanding_amount'] / $party['credit_limit']) * 100) : 0;
$creditExceeded = $party['credit_limit'] > 0 && $party['outstanding_amount'] >= $party['credit_limit'];

include __DIR__ . '/includes/header.php';
?>

<a href="<?= sales_base_url('parties.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm sp-mb-16">
    <i class="fas fa-arrow-left"></i> Back to Parties
</a>

<!-- Party Header -->
<div class="sp-card">
    <div class="sp-party-header">
        <div class="sp-party-avatar">
            <i class="fas fa-store"></i>
        </div>
        <div class="sp-party-info" style="flex:1;">
            <?php if (!empty($party['party_code'])): ?>
                <span class="sp-badge" style="background:rgba(26,60,52,0.08);color:#1A3C34;font-size:12px;padding:4px 12px;letter-spacing:1px;margin-bottom:6px;display:inline-block;"><?= htmlspecialchars($party['party_code']) ?></span>
            <?php endif; ?>
            <h2><?= htmlspecialchars($party['shop_name']) ?></h2>
            <p><i class="fas fa-user"></i> <?= htmlspecialchars($party['owner_name']) ?></p>
            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($party['phone']) ?></p>
            <?php if ($party['email']): ?>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($party['email']) ?></p>
            <?php endif; ?>
            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($party['address']) ?>, <?= htmlspecialchars($party['district']) ?></p>
            <?php if ($party['gst_number']): ?>
                <p><i class="fas fa-file-invoice"></i> GST: <?= htmlspecialchars($party['gst_number']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="sp-party-meta">
        <div class="sp-party-meta-item <?= $party['outstanding_amount'] > 0 ? 'danger' : 'success' ?>">
            <div class="value">₹<?= number_format($party['outstanding_amount'], 0) ?></div>
            <div class="label">Outstanding Amount</div>
        </div>
        <div class="sp-party-meta-item gold">
            <div class="value">₹<?= number_format($party['credit_limit'], 0) ?></div>
            <div class="label">Credit Limit</div>
        </div>
        <div class="sp-party-meta-item">
            <div class="value"><?= count($orders) ?></div>
            <div class="label">Total Orders</div>
        </div>
    </div>

    <?php if ($party['credit_limit'] > 0): ?>
    <div style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
            <span class="sp-text-muted">Credit Usage</span>
            <span class="sp-fw-600 <?= $creditExceeded ? 'sp-text-danger' : '' ?>"><?= round($creditPct) ?>%</span>
        </div>
        <div style="height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:<?= min(100, $creditPct) ?>%;background:<?= $creditExceeded ? 'var(--sp-danger)' : ($creditPct > 80 ? 'var(--sp-warning)' : 'var(--sp-success)') ?>;border-radius:4px;transition:width 0.3s;"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="sp-action-row">
        <a href="<?= sales_base_url('new_order.php?party_id=' . $party['id']) ?>" class="sp-btn sp-btn-primary">
            <i class="fas fa-cart-plus"></i> New Order
        </a>
        <a href="<?= sales_base_url('returns.php?party_id=' . $party['id'] . '&type=return') ?>" class="sp-btn sp-btn-outline">
            <i class="fas fa-undo-alt"></i> Return Request
        </a>
        <a href="<?= sales_base_url('returns.php?party_id=' . $party['id'] . '&type=credit_note') ?>" class="sp-btn sp-btn-outline">
            <i class="fas fa-file-invoice-dollar"></i> Credit Note
        </a>
        <a href="<?= sales_base_url('parties.php?action=edit&id=' . $party['id']) ?>" class="sp-btn sp-btn-outline">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button type="button" class="sp-btn sp-btn-outline" style="color:#dc2626;border-color:#dc2626;" onclick="confirmDeleteParty(<?= $party['id'] ?>, '<?= addslashes(htmlspecialchars($party['shop_name'])) ?>')">
            <i class="fas fa-trash-alt"></i> Delete
        </button>
    </div>
</div>

<!-- Order History -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-clipboard-list"></i> Order History</h3>
    </div>
    <?php if (empty($orders)): ?>
        <div class="sp-empty">
            <i class="fas fa-clipboard"></i>
            <h3>No orders yet</h3>
            <p>Place the first order for this party.</p>
        </div>
    <?php else: ?>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="sp-fw-600">
                            <a href="<?= sales_base_url('order_detail.php?id=' . $order['id']) ?>" style="color:var(--sp-primary);text-decoration:none;">
                                <?= htmlspecialchars($order['order_number']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($order['order_type'] === 'return'): ?>
                                <span class="sp-badge sp-badge-rejected">Return</span>
                            <?php elseif ($order['order_type'] === 'credit_note'): ?>
                                <span class="sp-badge sp-badge-dispatched">Credit Note</span>
                            <?php else: ?>
                                <span class="sp-badge sp-badge-approved">Order</span>
                            <?php endif; ?>
                        </td>
                        <td class="sp-fw-700">₹<?= number_format($order['total_amount'], 0) ?></td>
                        <td><span class="sp-badge sp-badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><span class="sp-badge sp-badge-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></td>
                        <td class="sp-text-muted sp-fs-sm"><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Payment History -->
<?php if (!empty($payments)): ?>
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-money-bill-wave"></i> Payment History</h3>
    </div>
    <div class="sp-table-wrap">
        <table class="sp-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Order #</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pay): 
                    // Fetch order number if order_id exists
                    $orderNum = '-';
                    if (!empty($pay['order_id'])) {
                        $orderData = db_fetch('SELECT order_number FROM sales_orders WHERE id = ?', [$pay['order_id']]);
                        $orderNum = $orderData['order_number'] ?? '-';
                    }
                ?>
                <tr>
                    <td class="sp-fs-sm"><?= date('d M Y', strtotime($pay['created_at'])) ?></td>
                    <td class="sp-fs-sm sp-fw-600"><?= htmlspecialchars($orderNum) ?></td>
                    <td><span class="sp-badge sp-badge-<?= $pay['payment_type'] === 'payment' ? 'approved' : 'dispatched' ?>"><?= ucfirst($pay['payment_type']) ?></span></td>
                    <td class="sp-fw-700 sp-text-success">₹<?= number_format($pay['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($pay['payment_method'] ?? '-') ?></td>
                    <td class="sp-fs-sm"><?= htmlspecialchars($pay['reference_number'] ?? '-') ?></td>
                    <td class="sp-text-muted sp-fs-sm"><?= htmlspecialchars($pay['notes'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function confirmDeleteParty(id, name) {
    if (confirm('Are you sure you want to delete party "' + name + '"?\n\nIf this party has orders, it will be deactivated instead of permanently deleted.')) {
        window.location.href = '<?= sales_base_url("parties.php") ?>?action=delete&id=' + id + '&confirm=1';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
