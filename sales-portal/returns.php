<?php
/**
 * Sales Executive Portal - Returns & Credit Notes
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Returns & Credit Notes';
$currentPage = 'returns';

$type = $_GET['type'] ?? '';
$partyId = (int)($_GET['party_id'] ?? 0);

// If creating a return/credit note, redirect to new_order with type
if ($type && $partyId) {
    // Show the new order form but with return/credit_note type
}

// Fetch return & credit note orders
$orders = db_fetch_all('SELECT so.*, sp.shop_name, sp.owner_name FROM sales_orders so JOIN sales_parties sp ON so.party_id = sp.id WHERE so.executive_id = ? AND so.order_type IN ("return","credit_note") ORDER BY so.created_at DESC', [$execId]);

include __DIR__ . '/includes/header.php';
?>

<div class="sp-flex-between sp-mb-24">
    <div></div>
    <div class="sp-flex sp-gap-8">
        <a href="<?= sales_base_url('new_return.php?type=return') ?>" class="sp-btn sp-btn-primary">
            <i class="fas fa-undo-alt"></i> New Return
        </a>
        <a href="<?= sales_base_url('new_return.php?type=credit_note') ?>" class="sp-btn sp-btn-gold">
            <i class="fas fa-file-invoice-dollar"></i> New Credit Note
        </a>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div class="sp-card">
        <div class="sp-empty">
            <i class="fas fa-undo-alt"></i>
            <h3>No returns or credit notes</h3>
            <p>Return requests and credit notes will appear here.</p>
        </div>
    </div>
<?php else: ?>
    <div class="sp-card">
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Party</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="sp-fw-600"><?= htmlspecialchars($order['order_number']) ?></td>
                        <td><?= htmlspecialchars($order['shop_name']) ?></td>
                        <td>
                            <?php if ($order['order_type'] === 'return'): ?>
                                <span class="sp-badge sp-badge-rejected">Return</span>
                            <?php else: ?>
                                <span class="sp-badge sp-badge-dispatched">Credit Note</span>
                            <?php endif; ?>
                        </td>
                        <td class="sp-fw-700">₹<?= number_format($order['total_amount'], 0) ?></td>
                        <td><span class="sp-badge sp-badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td class="sp-text-muted sp-fs-sm"><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                        <td>
                            <a href="<?= sales_base_url('order_detail.php?id=' . $order['id']) ?>" class="sp-btn sp-btn-outline sp-btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
