<?php
/**
 * Sales Executive Portal - Order Detail
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$currentPage = 'orders';

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: ' . sales_base_url('orders.php'));
    exit;
}

// Handle order deletion (only pending orders)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    $delOrder = db_fetch('SELECT id, order_number, status FROM sales_orders WHERE id = ? AND executive_id = ?', [$orderId, $execId]);
    if ($delOrder && $delOrder['status'] === 'pending') {
        try {
            $db = get_db_connection();
            $db->beginTransaction();
            db_query('DELETE FROM sales_order_items WHERE order_id = ?', [$orderId]);
            db_query('DELETE FROM sales_orders WHERE id = ? AND executive_id = ? AND status = ?', [$orderId, $execId, 'pending']);
            $db->commit();
            $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Order ' . $delOrder['order_number'] . ' deleted permanently.'];
        } catch (Exception $eDel) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Failed to delete order: ' . $eDel->getMessage()];
        }
    } else {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Only pending orders can be deleted.'];
    }
    header('Location: ' . sales_base_url('orders.php'));
    exit;
}

$order = db_fetch('SELECT so.*, sp.shop_name, sp.owner_name, sp.phone as party_phone, sp.party_code, sp.address as party_address, sp.district as party_district, sp.outstanding_amount, sp.credit_limit FROM sales_orders so JOIN sales_parties sp ON so.party_id = sp.id WHERE so.id = ? AND so.executive_id = ?', [$orderId, $execId]);

if (!$order) {
    $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Order not found.'];
    header('Location: ' . sales_base_url('orders.php'));
    exit;
}

$pageTitle = 'Order ' . $order['order_number'];
$items = db_fetch_all('SELECT * FROM sales_order_items WHERE order_id = ?', [$orderId]);

// Fetch GST rate from admin settings
$gstRate = 5; // default
try {
    $gstSetting = db_fetch("SELECT setting_value FROM settings WHERE setting_key = 'gst_rate'");
    if ($gstSetting) $gstRate = (float)$gstSetting['setting_value'];
} catch (PDOException $e) { /* use default */ }

// Compute base price and GST for each item
$orderGstTotal = 0;
$orderBaseTotal = 0;
foreach ($items as &$itm) {
    if ((float)($itm['gst_amount'] ?? 0) > 0) {
        $orderGstTotal += (float)$itm['gst_amount'] * (int)$itm['quantity'];
        $orderBaseTotal += (float)$itm['base_price'] * (int)$itm['quantity'];
    } else {
        // Calculate from price: price is inclusive, derive base
        $itemBase = round((float)$itm['price'] / (1 + $gstRate / 100), 2);
        $itemGst = round((float)$itm['price'] - $itemBase, 2);
        $itm['base_price'] = $itemBase;
        $itm['gst_amount'] = $itemGst;
        $itm['gst_rate'] = $gstRate;
        $orderGstTotal += $itemGst * (int)$itm['quantity'];
        $orderBaseTotal += $itemBase * (int)$itm['quantity'];
    }
}
unset($itm);

$statusColors = [
    'pending' => '#f59e0b',
    'approved' => '#10b981',
    'rejected' => '#ef4444',
    'dispatched' => '#8b5cf6',
    'delivered' => '#047857',
    'cancelled' => '#6b7280',
];
$statusColor = $statusColors[$order['status']] ?? '#6b7280';

include __DIR__ . '/includes/header.php';
?>

<a href="<?= sales_base_url('orders.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm sp-mb-16">
    <i class="fas fa-arrow-left"></i> Back to Orders
</a>

<!-- Order Header -->
<div class="sp-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
        <div>
            <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?= htmlspecialchars($order['order_number']) ?></h2>
            <p class="sp-text-muted sp-fs-sm">
                Placed on <?= date('d F Y, h:i A', strtotime($order['created_at'])) ?>
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <span class="sp-badge sp-badge-<?= $order['status'] ?>" style="font-size:13px;padding:6px 16px;">
                <i class="fas fa-<?= $order['status'] === 'pending' ? 'clock' : ($order['status'] === 'approved' ? 'check' : ($order['status'] === 'dispatched' ? 'truck' : ($order['status'] === 'delivered' ? 'check-double' : ($order['status'] === 'rejected' ? 'times' : 'ban')))) ?>"></i>
                <?= ucfirst($order['status']) ?>
            </span>
            <span class="sp-badge sp-badge-<?= $order['payment_status'] ?>" style="font-size:13px;padding:6px 16px;">
                Payment: <?= ucfirst($order['payment_status']) ?>
            </span>
            <button type="button" onclick="shareOrderWhatsApp()" style="background:#25D366;color:#fff;border:none;border-radius:8px;padding:6px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <i class="fab fa-whatsapp" style="font-size:16px;"></i> Share
            </button>
            <?php if ($order['status'] === 'pending'): ?>
            <button type="button" onclick="confirmDeleteOrder()" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:8px;padding:6px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-trash-alt" style="font-size:14px;"></i> Delete
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Party + Executive Info -->
    <div class="sp-detail-grid">
        <div style="background:#f9fafb;border-radius:10px;padding:16px;">
            <h4 style="font-size:13px;color:var(--sp-text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
                <i class="fas fa-store" style="color:var(--sp-gold);margin-right:6px;"></i> Party Details
            </h4>
            <p style="font-weight:700;font-size:15px;margin-bottom:4px;"><?= htmlspecialchars($order['shop_name']) ?></p>
            <p class="sp-text-muted sp-fs-sm"><i class="fas fa-user" style="width:14px;"></i> <?= htmlspecialchars($order['owner_name']) ?></p>
            <p class="sp-text-muted sp-fs-sm"><i class="fas fa-phone" style="width:14px;"></i> <?= htmlspecialchars($order['party_phone']) ?></p>
            <p class="sp-text-muted sp-fs-sm"><i class="fas fa-map-marker-alt" style="width:14px;"></i> <?= htmlspecialchars($order['party_address']) ?>, <?= htmlspecialchars($order['party_district']) ?></p>
        </div>
        <div style="background:#f9fafb;border-radius:10px;padding:16px;">
            <h4 style="font-size:13px;color:var(--sp-text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
                <i class="fas fa-user-tie" style="color:var(--sp-gold);margin-right:6px;"></i> Executive Details
            </h4>
            <p style="font-weight:700;font-size:15px;margin-bottom:4px;"><?= htmlspecialchars($exec['name']) ?></p>
            <p class="sp-text-muted sp-fs-sm"><i class="fas fa-map-pin" style="width:14px;"></i> <?= htmlspecialchars($order['district'] ?? $exec['district']) ?>, <?= htmlspecialchars($order['location'] ?? $exec['location']) ?></p>
            <?php if ($order['order_type'] !== 'new_order'): ?>
                <p style="margin-top:6px;">
                    <span class="sp-badge sp-badge-<?= $order['order_type'] === 'return' ? 'rejected' : 'dispatched' ?>">
                        <?= $order['order_type'] === 'return' ? 'Return Request' : 'Credit Note Request' ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($order['executive_notes']): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-bottom:20px;">
        <strong style="font-size:12px;color:#92400e;">Executive Notes:</strong>
        <p style="font-size:13px;color:#78350f;margin-top:4px;"><?= nl2br(htmlspecialchars($order['executive_notes'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($order['admin_notes']): ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:20px;">
        <strong style="font-size:12px;color:#1e40af;">Admin Notes:</strong>
        <p style="font-size:13px;color:#1e3a8a;margin-top:4px;"><?= nl2br(htmlspecialchars($order['admin_notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Order Items -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-boxes"></i> Order Items (<?= count($items) ?>)</h3>
    </div>
    <div class="sp-order-items-list">
        <?php foreach ($items as $i => $item): ?>
        <div class="sp-order-item">
            <div class="sp-order-item-main">
                <div class="sp-order-item-num"><?= $i + 1 ?></div>
                <div class="sp-order-item-info">
                    <div class="sp-order-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                    <?php if ($item['sku']): ?><div class="sp-order-item-sku"><?= htmlspecialchars($item['sku']) ?></div><?php endif; ?>
                    <?php if (!empty($item['is_custom_price'])): ?>
                    <span style="display:inline-block;font-size:10px;background:#f3e8ff;color:#7c3aed;padding:2px 8px;border-radius:6px;font-weight:600;margin-top:2px;">
                        <i class="fas fa-tag"></i> Custom Price
                        <?php if (!empty($item['original_price']) && (float)$item['original_price'] != (float)$item['price']): ?>
                            <span style="text-decoration:line-through;opacity:0.7;margin-left:4px;">₹<?= number_format($item['original_price'], 2) ?></span>
                            → ₹<?= number_format($item['price'], 2) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="sp-order-item-total">₹<?= number_format($item['total'], 2) ?></div>
            </div>
            <div class="sp-order-item-detail">
                <span>₹<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></span>
                <span style="margin-left:8px;font-size:11px;color:#6b7280;">
                    (Base: ₹<?= number_format($item['base_price'], 2) ?> + GST <?= number_format($item['gst_rate'] ?? $gstRate, 0) ?>%: ₹<?= number_format($item['gst_amount'], 2) ?>)
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="sp-order-totals">
        <div class="sp-order-total-row">
            <span>Base Amount</span>
            <span class="sp-fw-600">₹<?= number_format($orderBaseTotal, 2) ?></span>
        </div>
        <div class="sp-order-total-row">
            <span>GST (<?= number_format($gstRate, 0) ?>%)</span>
            <span class="sp-fw-600">₹<?= number_format($orderGstTotal, 2) ?></span>
        </div>
        <div class="sp-order-total-row">
            <span>Subtotal</span>
            <span class="sp-fw-600">₹<?= number_format($order['subtotal'], 2) ?></span>
        </div>
        <?php if ($order['discount_amount'] > 0): ?>
        <div class="sp-order-total-row sp-text-success">
            <span>Discount</span>
            <span class="sp-fw-600">-₹<?= number_format($order['discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="sp-order-total-row sp-order-total-final">
            <span>Total</span>
            <span>₹<?= number_format($order['total_amount'], 2) ?></span>
        </div>
    </div>
</div>

<!-- Timeline -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> Order Timeline</h3>
    </div>
    <div style="padding-left:20px;border-left:2px solid var(--sp-border);">
        <div style="position:relative;padding-bottom:16px;padding-left:20px;">
            <div style="position:absolute;left:-9px;top:2px;width:16px;height:16px;border-radius:50%;background:var(--sp-success);border:2px solid white;box-shadow:0 0 0 2px var(--sp-success);"></div>
            <div class="sp-fw-600">Order Placed</div>
            <div class="sp-text-muted sp-fs-sm"><?= date('d F Y, h:i A', strtotime($order['created_at'])) ?></div>
        </div>
        <?php if ($order['approved_at']): ?>
        <div style="position:relative;padding-bottom:16px;padding-left:20px;">
            <div style="position:absolute;left:-9px;top:2px;width:16px;height:16px;border-radius:50%;background:<?= $order['status'] === 'rejected' ? 'var(--sp-danger)' : 'var(--sp-success)' ?>;border:2px solid white;box-shadow:0 0 0 2px <?= $order['status'] === 'rejected' ? 'var(--sp-danger)' : 'var(--sp-success)' ?>;"></div>
            <div class="sp-fw-600"><?= $order['status'] === 'rejected' ? 'Order Rejected' : 'Order Approved' ?></div>
            <div class="sp-text-muted sp-fs-sm"><?= date('d F Y, h:i A', strtotime($order['approved_at'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($order['dispatched_at']): ?>
        <div style="position:relative;padding-bottom:16px;padding-left:20px;">
            <div style="position:absolute;left:-9px;top:2px;width:16px;height:16px;border-radius:50%;background:var(--sp-info);border:2px solid white;box-shadow:0 0 0 2px var(--sp-info);"></div>
            <div class="sp-fw-600">Dispatched</div>
            <div class="sp-text-muted sp-fs-sm"><?= date('d F Y, h:i A', strtotime($order['dispatched_at'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($order['delivered_at']): ?>
        <div style="position:relative;padding-bottom:0;padding-left:20px;">
            <div style="position:absolute;left:-9px;top:2px;width:16px;height:16px;border-radius:50%;background:var(--sp-success);border:2px solid white;box-shadow:0 0 0 2px var(--sp-success);"></div>
            <div class="sp-fw-600">Delivered</div>
            <div class="sp-text-muted sp-fs-sm"><?= date('d F Y, h:i A', strtotime($order['delivered_at'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!$order['approved_at'] && $order['status'] === 'pending'): ?>
        <div style="position:relative;padding-left:20px;">
            <div style="position:absolute;left:-9px;top:2px;width:16px;height:16px;border-radius:50%;background:#e5e7eb;border:2px solid white;box-shadow:0 0 0 2px #e5e7eb;"></div>
            <div class="sp-text-muted">Awaiting Admin Approval</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function shareOrderWhatsApp() {
    var partyName = <?= json_encode($order['shop_name']) ?>;
    var partyId = <?= json_encode($order['party_code'] ?? '') ?>;
    var orderNo = <?= json_encode($order['order_number']) ?>;
    var orderDate = <?= json_encode(date('d M Y', strtotime($order['created_at']))) ?>;
    var orderAmount = <?= json_encode(number_format($order['total_amount'], 0)) ?>;
    var partyPhone = <?= json_encode($order['party_phone'] ?? '') ?>;
    var orderStatus = <?= json_encode($order['status']) ?>;
    
    // Build product list
    var orderItems = <?= json_encode($items) ?>;
    var productList = '';
    orderItems.forEach(function(item, index) {
        productList += (index + 1) + '. *' + item.product_name + '*\n';
        productList += '   Qty: ' + item.quantity + ' | Price: ₹' + parseFloat(item.price).toFixed(2) + ' | Total: ₹' + parseFloat(item.total).toFixed(2);
        if (item.is_custom_price && item.original_price && parseFloat(item.original_price) !== parseFloat(item.price)) {
            productList += ' _(Custom)_';
        }
        productList += '\n';
    });
    
    // Dynamic message based on order status
    var statusMessage = '';
    var statusEmoji = '';
    
    if (orderStatus === 'pending') {
        statusEmoji = '🛒';
        statusMessage = 'Thank you for your order! We have received it successfully and it is being processed.';
    } else if (orderStatus === 'approved') {
        statusEmoji = '✅';
        statusMessage = 'Great news! Your order has been approved and confirmed by our team.';
    } else if (orderStatus === 'dispatched') {
        statusEmoji = '🚚';
        statusMessage = 'Your order has been dispatched and is on its way to you!';
    } else if (orderStatus === 'delivered') {
        statusEmoji = '📦';
        statusMessage = 'Your order has been successfully delivered. Thank you for your business!';
    } else if (orderStatus === 'rejected') {
        statusEmoji = '❌';
        statusMessage = 'We regret to inform you that your order could not be processed.';
    } else if (orderStatus === 'cancelled') {
        statusEmoji = '🚫';
        statusMessage = 'Your order has been cancelled as requested.';
    } else {
        statusEmoji = '🛒';
        statusMessage = 'Thank you for your order! We have received it successfully and it is being processed.';
    }

    var msg = statusEmoji + ' *ORDER ' + orderStatus.toUpperCase() + '*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n\n'
        + 'Dear *' + partyName + '*,\n\n'
        + statusMessage + '\n\n'
        + '📋 *Order Details:*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '🆔 Party ID: ' + partyId + '\n'
        + '📄 Order No: ' + orderNo + '\n'
        + '📅 Order Date: ' + orderDate + '\n'
        + '💰 Order Amount: *₹' + orderAmount + '*\n\n'
        + '📦 *Items Ordered:*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + productList + '\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '💵 *Total Amount: ₹' + orderAmount + '*\n\n'
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
}

function confirmDeleteOrder() {
    if (confirm('Are you sure you want to permanently delete this order?\n\nOrder: <?= $order['order_number'] ?>\nParty: <?= addslashes(htmlspecialchars($order['shop_name'])) ?>\nAmount: ₹<?= number_format($order['total_amount'], 0) ?>\n\nThis action cannot be undone.')) {
        window.location.href = '<?= sales_base_url('order_detail.php?id=' . $order['id'] . '&action=delete&confirm=1') ?>';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
