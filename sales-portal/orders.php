<?php
/**
 * Sales Executive Portal - My Orders
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'My Orders';
$currentPage = 'orders';

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = 'SELECT so.*, sp.shop_name, sp.owner_name, sp.phone as party_phone, sp.party_code FROM sales_orders so JOIN sales_parties sp ON so.party_id = sp.id WHERE so.executive_id = ?';
$params = [$execId];

if ($statusFilter) {
    $sql .= ' AND so.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $sql .= ' AND (so.order_number LIKE ? OR sp.shop_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY so.created_at DESC';
$orders = db_fetch_all($sql, $params);

// Status counts
$counts = [];
$countRows = db_fetch_all('SELECT status, COUNT(*) as cnt FROM sales_orders WHERE executive_id = ? GROUP BY status', [$execId]);
foreach ($countRows as $r) $counts[$r['status']] = $r['cnt'];
$totalCount = array_sum($counts);

include __DIR__ . '/includes/header.php';
?>

<!-- Status Filter Tabs (horizontally scrollable) -->
<div class="sp-orders-tabs">
    <a href="<?= sales_base_url('orders.php') ?>" class="sp-orders-tab <?= !$statusFilter ? 'active' : '' ?>">
        All <span class="sp-orders-tab-count"><?= $totalCount ?></span>
    </a>
    <?php foreach (['pending','approved','dispatched','delivered','rejected','cancelled'] as $s): ?>
        <a href="<?= sales_base_url('orders.php?status=' . $s) ?>" class="sp-orders-tab <?= $statusFilter === $s ? 'active' : '' ?>">
            <?= ucfirst($s) ?> <span class="sp-orders-tab-count"><?= $counts[$s] ?? 0 ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<div class="sp-search-bar">
    <i class="fas fa-search"></i>
    <form method="GET" style="width:100%;">
        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
        <input type="text" name="search" placeholder="Search order # or party..." value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
    </form>
</div>

<?php if (empty($orders)): ?>
    <div class="sp-card">
        <div class="sp-empty">
            <i class="fas fa-clipboard-list"></i>
            <h3>No orders found</h3>
            <p><?= $search || $statusFilter ? 'Try different filters.' : 'Start by creating a new order.' ?></p>
            <a href="<?= sales_base_url('new_order.php') ?>" class="sp-btn sp-btn-primary"><i class="fas fa-cart-plus"></i> New Order</a>
        </div>
    </div>
<?php else: ?>
    <div class="sp-orders-list">
        <?php
        $statusMap = ['pending' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'clock'], 'approved' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'check'], 'dispatched' => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'icon' => 'truck'], 'delivered' => ['bg' => '#d1fae5', 'color' => '#047857', 'icon' => 'check-double'], 'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'times'], 'cancelled' => ['bg' => '#f3f4f6', 'color' => '#4b5563', 'icon' => 'ban']];
        foreach ($orders as $order):
            $sm = $statusMap[$order['status']] ?? ['bg' => '#f3f4f6', 'color' => '#6b7280', 'icon' => 'circle'];
        ?>
        <a href="<?= sales_base_url('order_detail.php?id=' . $order['id']) ?>" class="sp-order-card">
            <div class="sp-order-card-top">
                <div class="sp-order-card-party">
                    <div class="sp-order-card-shop"><?= htmlspecialchars($order['shop_name']) ?></div>
                    <div class="sp-order-card-meta"><?= $order['order_number'] ?> · <?= date('d M Y', strtotime($order['created_at'])) ?></div>
                </div>
                <div class="sp-order-card-amount">₹<?= number_format($order['total_amount'], 0) ?></div>
            </div>
            <div class="sp-order-card-bottom">
                <div class="sp-order-card-badges">
                    <span class="sp-order-card-badge" style="background:<?= $sm['bg'] ?>;color:<?= $sm['color'] ?>;">
                        <i class="fas fa-<?= $sm['icon'] ?>"></i> <?= ucfirst($order['status']) ?>
                    </span>
                    <?php if ($order['order_type'] === 'return'): ?>
                        <span class="sp-order-card-badge" style="background:#fee2e2;color:#991b1b;">Return</span>
                    <?php elseif ($order['order_type'] === 'credit_note'): ?>
                        <span class="sp-order-card-badge" style="background:#ede9fe;color:#5b21b6;">Credit Note</span>
                    <?php endif; ?>
                    <?php
                    $payBg = $order['payment_status'] === 'received' ? '#d1fae5' : '#fef3c7';
                    $payColor = $order['payment_status'] === 'received' ? '#065f46' : '#92400e';
                    ?>
                    <span class="sp-order-card-badge" style="background:<?= $payBg ?>;color:<?= $payColor ?>;">
                        <?= ucfirst($order['payment_status']) ?>
                    </span>
                </div>
                <span class="sp-order-share-btn" id="orderShareBtn<?= $order['id'] ?>" onclick="event.preventDefault();event.stopPropagation();shareOrderWhatsApp(<?= $order['id'] ?>,'<?= htmlspecialchars(addslashes($order['shop_name'])) ?>','<?= htmlspecialchars($order['party_code'] ?? '') ?>','<?= $order['order_number'] ?>','<?= date('d M Y', strtotime($order['created_at'])) ?>','<?= number_format($order['total_amount'], 0) ?>','<?= htmlspecialchars($order['party_phone'] ?? '') ?>')" title="Share on WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </span>
                <i class="fas fa-chevron-right sp-order-card-arrow"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function shareOrderWhatsApp(orderId, partyName, partyId, orderNo, orderDate, orderAmount, partyPhone) {
    var msg = '🛒 *ORDER CONFIRMATION*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n\n'
        + 'Dear *' + partyName + '*,\n\n'
        + 'Thank you for your order! We have received it successfully and it is being processed.\n\n'
        + '📋 *Order Details:*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '🆔 Party ID: ' + partyId + '\n'
        + '📄 Order No: ' + orderNo + '\n'
        + '📅 Order Date: ' + orderDate + '\n'
        + '💰 Order Amount: *₹' + orderAmount + '*\n\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '💵 *Total Amount: ₹' + orderAmount + '*\n\n'
        + 'Your order will be dispatched soon.\n\n'
        + 'Thank you for choosing *Gilaf*.\n'
        + 'Your satisfaction is our priority! 🙏\n\n'
        + '_For any queries, feel free to contact us._';

    var url;
    if (partyPhone) {
        var phone = partyPhone.replace(/[^0-9]/g, '');
        if (phone.length === 10) phone = '91' + phone;
        url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
    } else {
        url = 'https://wa.me/?text=' + encodeURIComponent(msg);
    }
    window.open(url, '_blank');

    var btn = document.getElementById('orderShareBtn' + orderId);
    if (btn) { btn.style.background = '#6b7280'; }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
