<?php
/**
 * Shipping Invoice Print Page
 * Professional, print-optimized shipping invoice
 * Supports: A4/A5 paper, 1/2/4 invoices per page, bulk printing
 * Access: Admin only (inside website security)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/invoice_functions.php';

require_admin();

$db = get_db_connection();

// Parse parameters
$ids    = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
$paper  = in_array($_GET['paper'] ?? 'A4', ['A4', 'A5']) ? $_GET['paper'] : 'A4';
$layout = in_array((int)($_GET['layout'] ?? 1), [1, 2, 4]) ? (int)$_GET['layout'] : 1;

if (empty($ids)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">No order IDs provided. Usage: ?ids=1,2,3&paper=A4&layout=2</h3>');
}

// Fetch orders with details
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$orderStmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id IN ($placeholders)
    ORDER BY o.created_at DESC
");
$orderStmt->execute($ids);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">No orders found for the given IDs.</h3>');
}

// Fetch items for each order
foreach ($orders as &$order) {
    $itemStmt = $db->prepare("
        SELECT oi.*, p.name AS product_name, pw.display_weight
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.price = oi.price
        WHERE oi.order_id = ?
    ");
    $itemStmt->execute([$order['id']]);
    $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse shipping address
    $order['parsed_address'] = ['name' => '', 'address' => '', 'city' => '', 'state' => '', 'pincode' => '', 'phone' => ''];
    if (!empty($order['shipping_address'])) {
        $addrData = json_decode($order['shipping_address'], true);
        if (is_array($addrData)) {
            $order['parsed_address'] = [
                'name'    => $addrData['full_name'] ?? ($order['customer_name'] ?? 'Customer'),
                'address' => implode(', ', array_filter([$addrData['address_line1'] ?? '', $addrData['address_line2'] ?? '', $addrData['landmark'] ?? ''])),
                'city'    => $addrData['city'] ?? '',
                'state'   => $addrData['state'] ?? '',
                'pincode' => $addrData['pincode'] ?? ($addrData['zip_code'] ?? ''),
                'phone'   => $addrData['phone'] ?? ($order['customer_phone'] ?? '')
            ];
        } else {
            $order['parsed_address'] = [
                'name'    => $order['customer_name'] ?? 'Customer',
                'address' => $order['shipping_address'],
                'city' => '', 'state' => '', 'pincode' => '',
                'phone'   => $order['customer_phone'] ?? ''
            ];
        }
    } else {
        // Fallback to user_addresses
        $addr = db_fetch('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1', [$order['user_id']]);
        if ($addr) {
            $order['parsed_address'] = [
                'name'    => $order['customer_name'] ?? 'Customer',
                'address' => implode(', ', array_filter([$addr['flat_number'] ?? '', $addr['address_line1'] ?? '', $addr['address_line2'] ?? '', $addr['landmark'] ?? ''])),
                'city'    => $addr['city'] ?? '',
                'state'   => $addr['state'] ?? '',
                'pincode' => $addr['zip_code'] ?? '',
                'phone'   => $addr['phone'] ?? ($order['customer_phone'] ?? '')
            ];
        } else {
            $order['parsed_address']['name'] = $order['customer_name'] ?? 'Customer';
            $order['parsed_address']['phone'] = $order['customer_phone'] ?? '';
        }
    }
}
unset($order);

$company = get_company_details();

// Paper dimensions
$paperStyles = [
    'A4' => ['width' => '210mm', 'height' => '297mm'],
    'A5' => ['width' => '148mm', 'height' => '210mm'],
];
$pw = $paperStyles[$paper];

// Layout grid
$gridCols = $layout >= 2 ? 2 : 1;
$gridRows = $layout >= 4 ? 2 : 1;
$invoiceScale = $layout === 1 ? 1 : ($layout === 2 ? 0.48 : 0.47);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shipping Invoice — <?= count($orders); ?> Order(s)</title>
<style>
/* ─── Base Reset ─── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #e9ecef; color: #1a1a1a; font-size: 11px; line-height: 1.4; }

/* ─── Screen Controls (hidden on print) ─── */
.screen-controls {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    background: linear-gradient(135deg, #1e3a5f, #1a3c34); color: #fff;
    padding: 12px 24px; display: flex; align-items: center; gap: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,.2);
}
.screen-controls label { font-size: 12px; font-weight: 600; }
.screen-controls select { padding: 4px 8px; border-radius: 6px; border: none; font-size: 12px; }
.screen-controls .btn-print {
    background: #22c55e; color: #fff; border: none; padding: 8px 20px;
    border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px;
}
.screen-controls .btn-print:hover { background: #16a34a; }
.screen-controls .btn-back {
    background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.3);
    padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; text-decoration: none;
}
.screen-controls .info { margin-left: auto; font-size: 12px; opacity: .8; }

.print-area { margin-top: 70px; padding: 20px; }

/* ─── Print Page Setup ─── */
@page {
    size: <?= $pw['width']; ?> <?= $pw['height']; ?>;
    margin: 6mm;
}

/* ─── Invoice Grid Per Page ─── */
.invoice-page {
    width: <?= $pw['width']; ?>;
    min-height: <?= $pw['height']; ?>;
    background: #fff;
    margin: 0 auto 20px;
    padding: 8mm;
    display: grid;
    grid-template-columns: repeat(<?= $gridCols; ?>, 1fr);
    grid-template-rows: repeat(<?= $gridRows; ?>, 1fr);
    gap: <?= $layout > 1 ? '6mm' : '0'; ?>;
    page-break-after: always;
    box-shadow: 0 2px 16px rgba(0,0,0,.1);
}
.invoice-page:last-child { page-break-after: auto; }

/* ─── Single Invoice Card ─── */
.invoice-card {
    border: <?= $layout > 1 ? '1px solid #ccc' : 'none'; ?>;
    border-radius: <?= $layout > 1 ? '4px' : '0'; ?>;
    padding: <?= $layout > 1 ? '4mm' : '4mm 2mm'; ?>;
    display: flex; flex-direction: column;
    font-size: <?= $layout === 1 ? '11px' : ($layout === 2 ? '9px' : '7.5px'); ?>;
    overflow: hidden;
}

/* ─── Invoice Header ─── */
.inv-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    border-bottom: 2px solid #1a3c34; padding-bottom: 6px; margin-bottom: 6px;
}
.inv-brand { display: flex; align-items: center; gap: 8px; }
.inv-brand-icon {
    width: <?= $layout === 1 ? '40px' : '28px'; ?>; height: <?= $layout === 1 ? '40px' : '28px'; ?>;
    background: linear-gradient(135deg, #1a3c34, #22c55e); color: #fff;
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: <?= $layout === 1 ? '16px' : '12px'; ?>;
}
.inv-brand-text { line-height: 1.2; }
.inv-brand-name { font-weight: 800; color: #1a3c34; font-size: <?= $layout === 1 ? '15px' : ($layout === 2 ? '11px' : '9px'); ?>; }
.inv-brand-sub { color: #64748b; font-size: <?= $layout === 1 ? '9px' : '7px'; ?>; }
.inv-title-block { text-align: right; }
.inv-title { font-size: <?= $layout === 1 ? '14px' : ($layout === 2 ? '10px' : '8px'); ?>; font-weight: 800; color: #1a3c34; text-transform: uppercase; letter-spacing: 1px; }
.inv-order-num { font-size: <?= $layout === 1 ? '11px' : '8px'; ?>; color: #64748b; margin-top: 2px; }

/* ─── Address Row ─── */
.inv-addresses { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 6px; }
.inv-addr-box { background: #f8fafb; border-radius: 4px; padding: 6px 8px; }
.inv-addr-label { font-size: <?= $layout === 1 ? '8px' : '6.5px'; ?>; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; font-weight: 700; margin-bottom: 3px; }
.inv-addr-name { font-weight: 700; font-size: <?= $layout === 1 ? '11px' : ($layout === 2 ? '9px' : '7.5px'); ?>; }
.inv-addr-line { color: #475569; }
.inv-addr-phone { color: #1a3c34; font-weight: 600; margin-top: 2px; }

/* ─── Items Table ─── */
.inv-items { width: 100%; border-collapse: collapse; margin-bottom: 6px; flex: 1; }
.inv-items th {
    background: #1a3c34; color: #fff; padding: 4px 6px;
    font-size: <?= $layout === 1 ? '9px' : '7px'; ?>; text-transform: uppercase; letter-spacing: .5px;
    text-align: left;
}
.inv-items th:first-child { border-radius: 4px 0 0 0; }
.inv-items th:last-child { border-radius: 0 4px 0 0; text-align: right; }
.inv-items td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
.inv-items tr:last-child td { border-bottom: none; }
.inv-items .text-right { text-align: right; }
.inv-items .item-name { font-weight: 600; }
.inv-items .item-variant { color: #64748b; font-size: <?= $layout === 1 ? '9px' : '7px'; ?>; }

/* ─── Totals ─── */
.inv-totals { margin-top: auto; }
.inv-totals-row { display: flex; justify-content: flex-end; gap: 30px; padding: 2px 0; }
.inv-totals-label { color: #64748b; text-align: right; min-width: 80px; }
.inv-totals-val { font-weight: 700; min-width: 80px; text-align: right; }
.inv-grand-total {
    background: linear-gradient(135deg, #1a3c34, #22c55e); color: #fff;
    border-radius: 4px; padding: 6px 10px; margin-top: 4px;
    display: flex; justify-content: space-between; align-items: center;
    font-size: <?= $layout === 1 ? '12px' : ($layout === 2 ? '10px' : '8px'); ?>;
}
.inv-grand-total .label { font-weight: 600; }
.inv-grand-total .amount { font-weight: 800; font-size: <?= $layout === 1 ? '14px' : ($layout === 2 ? '11px' : '9px'); ?>; }

/* ─── Footer ─── */
.inv-footer {
    border-top: 1px dashed #cbd5e1; padding-top: 4px; margin-top: 6px;
    display: flex; justify-content: space-between; align-items: center;
    font-size: <?= $layout === 1 ? '8px' : '6.5px'; ?>; color: #94a3b8;
}

/* ─── Print Overrides ─── */
@media print {
    .screen-controls { display: none !important; }
    .print-area { margin-top: 0; padding: 0; }
    body { background: #fff; }
    .invoice-page { box-shadow: none; margin: 0; }
}
</style>
</head>
<body>

<!-- Screen Controls -->
<div class="screen-controls">
    <a href="shipping_invoices.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
    <label>Paper:
        <select id="ctrlPaper" onchange="reloadWithSettings()">
            <option value="A4" <?= $paper === 'A4' ? 'selected' : ''; ?>>A4</option>
            <option value="A5" <?= $paper === 'A5' ? 'selected' : ''; ?>>A5</option>
        </select>
    </label>
    <label>Layout:
        <select id="ctrlLayout" onchange="reloadWithSettings()">
            <option value="1" <?= $layout === 1 ? 'selected' : ''; ?>>1 Invoice / Page</option>
            <option value="2" <?= $layout === 2 ? 'selected' : ''; ?>>2 Invoices / Page</option>
            <option value="4" <?= $layout === 4 ? 'selected' : ''; ?>>4 Invoices / Page</option>
        </select>
    </label>
    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Now</button>
    <span class="info"><?= count($orders); ?> invoice(s) &middot; <?= $paper; ?> &middot; <?= $layout; ?>/page</span>
</div>

<div class="print-area">
<?php
// Group invoices into pages based on layout
$chunks = array_chunk($orders, $layout);
foreach ($chunks as $chunk):
?>
<div class="invoice-page">
<?php foreach ($chunk as $order):
    $addr = $order['parsed_address'];
    $items = $order['items'];
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $shipping = floatval($order['shipping_cost'] ?? ($order['shipping_charge'] ?? 0));
    $discount = floatval($order['discount_amount'] ?? ($order['discount'] ?? 0));
    $total = floatval($order['total_amount']);
?>
<div class="invoice-card">
    <!-- Header -->
    <div class="inv-header">
        <div class="inv-brand">
            <?php if (!empty($company['logo'])): ?>
            <img src="<?= htmlspecialchars($company['logo']); ?>" class="inv-brand-icon" style="object-fit:contain;border-radius:8px;" alt="Logo">
            <?php else: ?>
            <div class="inv-brand-icon">G</div>
            <?php endif; ?>
            <div class="inv-brand-text">
                <div class="inv-brand-name"><?= htmlspecialchars($company['company_name']); ?></div>
                <div class="inv-brand-sub"><?= htmlspecialchars($company['address'] . ', ' . $company['city'] . ', ' . $company['state'] . ' - ' . $company['pincode']); ?></div>
                <div class="inv-brand-sub">Ph: <?= htmlspecialchars($company['phone']); ?> | GSTIN: <?= htmlspecialchars($company['gstin']); ?></div>
            </div>
        </div>
        <div class="inv-title-block">
            <div class="inv-title">Shipping Invoice</div>
            <div class="inv-order-num">Order #<?= (int)$order['id']; ?></div>
            <div class="inv-order-num"><?= date('d M Y, h:i A', strtotime($order['created_at'])); ?></div>
        </div>
    </div>

    <!-- Addresses -->
    <div class="inv-addresses">
        <div class="inv-addr-box">
            <div class="inv-addr-label">Ship From</div>
            <div class="inv-addr-name"><?= htmlspecialchars($company['company_name']); ?></div>
            <div class="inv-addr-line"><?= htmlspecialchars($company['address'] . ', ' . $company['city']); ?></div>
            <div class="inv-addr-line"><?= htmlspecialchars($company['state'] . ' - ' . $company['pincode']); ?></div>
            <div class="inv-addr-phone">Ph: <?= htmlspecialchars($company['phone']); ?></div>
        </div>
        <div class="inv-addr-box">
            <div class="inv-addr-label">Ship To</div>
            <div class="inv-addr-name"><?= htmlspecialchars($addr['name']); ?></div>
            <div class="inv-addr-line"><?= htmlspecialchars($addr['address']); ?></div>
            <?php if ($addr['city'] || $addr['state']): ?>
            <div class="inv-addr-line"><?= htmlspecialchars(implode(', ', array_filter([$addr['city'], $addr['state']]))); ?> <?= $addr['pincode'] ? '- ' . htmlspecialchars($addr['pincode']) : ''; ?></div>
            <?php endif; ?>
            <?php if ($addr['phone']): ?>
            <div class="inv-addr-phone">Ph: <?= htmlspecialchars($addr['phone']); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <table class="inv-items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Description</th>
                <th>Qty</th>
                <th style="text-align:right;">Price</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 0; foreach ($items as $item): $sn++; $lineTotal = $item['price'] * $item['quantity']; ?>
            <tr>
                <td><?= $sn; ?></td>
                <td>
                    <div class="item-name"><?= htmlspecialchars($item['product_name'] ?? 'Product'); ?></div>
                    <?php if (!empty($item['display_weight'])): ?>
                    <div class="item-variant"><?= htmlspecialchars($item['display_weight']); ?></div>
                    <?php endif; ?>
                </td>
                <td><?= (int)$item['quantity']; ?></td>
                <td class="text-right">₹<?= number_format($item['price'], 2); ?></td>
                <td class="text-right">₹<?= number_format($lineTotal, 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="inv-totals">
        <div class="inv-totals-row">
            <span class="inv-totals-label">Subtotal:</span>
            <span class="inv-totals-val">₹<?= number_format($subtotal, 2); ?></span>
        </div>
        <?php if ($shipping > 0): ?>
        <div class="inv-totals-row">
            <span class="inv-totals-label">Shipping:</span>
            <span class="inv-totals-val">₹<?= number_format($shipping, 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($discount > 0): ?>
        <div class="inv-totals-row">
            <span class="inv-totals-label">Discount:</span>
            <span class="inv-totals-val" style="color:#dc2626;">-₹<?= number_format($discount, 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="inv-grand-total">
            <span class="label">TOTAL PAYABLE</span>
            <span class="amount">₹<?= number_format($total, 2); ?></span>
        </div>
    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <span>Payment: <?= strtoupper($order['payment_method'] ?? 'N/A'); ?> — <?= ucfirst($order['payment_status'] ?? 'Pending'); ?></span>
        <span><?= htmlspecialchars($company['website']); ?></span>
    </div>
</div>
<?php endforeach; ?>

<?php
// Fill empty slots in last page if layout > 1
$remaining = $layout - count($chunk);
for ($r = 0; $r < $remaining; $r++):
?>
<div class="invoice-card" style="border:1px dashed #ddd; opacity:.3; display:flex; align-items:center; justify-content:center;">
    <span style="font-size:14px; color:#ccc;">— Empty Slot —</span>
</div>
<?php endfor; ?>

</div><!-- .invoice-page -->
<?php endforeach; ?>
</div><!-- .print-area -->

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<script>
function reloadWithSettings() {
    const ids = '<?= implode(',', $ids); ?>';
    const paper = document.getElementById('ctrlPaper').value;
    const layout = document.getElementById('ctrlLayout').value;
    window.location.href = 'shipping_invoice_print.php?ids=' + ids + '&paper=' + paper + '&layout=' + layout;
}
</script>
</body>
</html>
