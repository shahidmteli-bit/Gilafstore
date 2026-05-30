<?php
/**
 * Shipping Label Print — 4x6 Thermal Format
 * Amazon/Flipkart style shipping label with QR, barcodes, auto-fetched data
 * 
 * Usage: shipping_label_print.php?ids=123 or ?ids=123,456,789
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/company_profile_functions.php';

require_admin();

$db = get_db_connection();

// Parse order IDs + layout
$rawIds = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $rawIds)));
$layout = in_array((int)($_GET['layout'] ?? 1), [1, 2, 4]) ? (int)$_GET['layout'] : 1;
if (empty($ids)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">No order IDs provided. Usage: ?ids=1,2,3&layout=2</h3>');
}

// Fetch orders with customer info
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$orderStmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id IN ({$placeholders})
    ORDER BY o.created_at DESC
");
$orderStmt->execute($ids);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">No orders found for the given IDs.</h3>');
}

// Fetch item count + shipment data per order
foreach ($orders as &$order) {
    $countStmt = $db->prepare("SELECT SUM(quantity) AS total_items FROM order_items WHERE order_id = ?");
    $countStmt->execute([$order['id']]);
    $order['item_count'] = (int)($countStmt->fetchColumn() ?: 0);

    // Fetch order items with product details (HSN, dimensions, weight) for product table
    try {
        $itemStmt = $db->prepare("
            SELECT oi.*, p.name AS product_name, p.hsn_code,
                   p.shipping_length, p.shipping_width, p.shipping_height, p.shipping_weight,
                   p.gst_rate
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback: columns may not exist on production yet
        $itemStmt = $db->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $itemStmt->execute([$order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate aggregate dimensions & weight for the order (max L/W/H, sum weight)
    $maxL = 20; $maxW = 15; $maxH = 10; $totalWeight = 0;
    foreach ($order['items'] as $item) {
        if (!empty($item['shipping_length']) && $item['shipping_length'] > $maxL) $maxL = (float)$item['shipping_length'];
        if (!empty($item['shipping_width'])  && $item['shipping_width']  > $maxW) $maxW = (float)$item['shipping_width'];
        if (!empty($item['shipping_height']) && $item['shipping_height'] > $maxH) $maxH = (float)$item['shipping_height'];
        $totalWeight += ((float)($item['shipping_weight'] ?? 0.5)) * (int)$item['quantity'];
    }
    if ($totalWeight < 0.5) $totalWeight = 0.5;
    $order['pkg_length'] = $maxL;
    $order['pkg_width']  = $maxW;
    $order['pkg_height'] = $maxH;
    $order['pkg_weight'] = $totalWeight;

    // Auto-fill from order_shipments (latest shipment)
    try {
        $shipStmt = $db->prepare("SELECT * FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $shipStmt->execute([$order['id']]);
        $shipment = $shipStmt->fetch(PDO::FETCH_ASSOC);
        if ($shipment) {
            if (empty($order['courier_company']) && !empty($shipment['shipping_partner'])) {
                $order['courier_company'] = $shipment['shipping_partner'];
            }
            if (empty($order['tracking_id']) && !empty($shipment['awb_or_tracking'])) {
                $order['tracking_id'] = $shipment['awb_or_tracking'];
            }
            if (empty($order['dispatch_mode']) && !empty($shipment['dispatch_mode'])) {
                $order['dispatch_mode'] = $shipment['dispatch_mode'];
            }
            $order['shipment_type'] = $shipment['shipping_type'] ?? 'manual';
        }
    } catch (Exception $e) { /* order_shipments table may not exist yet */ }

    // Parse shipping address
    $order['parsed_address'] = ['name' => '', 'address' => '', 'city' => '', 'state' => '', 'pincode' => '', 'phone' => ''];
    if (!empty($order['shipping_address'])) {
        $addrData = json_decode($order['shipping_address'], true);
        if (is_array($addrData)) {
            $order['parsed_address'] = [
                'name'    => $addrData['name'] ?? $addrData['full_name'] ?? ($order['customer_name'] ?? ''),
                'address' => trim(($addrData['address_line1'] ?? '') . ' ' . ($addrData['address_line2'] ?? '')),
                'city'    => $addrData['city'] ?? '',
                'state'   => $addrData['state'] ?? '',
                'pincode' => $addrData['pincode'] ?? ($addrData['zip_code'] ?? ''),
                'phone'   => $addrData['phone'] ?? ($order['customer_phone'] ?? ''),
            ];
        } else {
            $order['parsed_address']['address'] = $order['shipping_address'];
            $order['parsed_address']['name'] = $order['customer_name'] ?? '';
            $order['parsed_address']['phone'] = $order['customer_phone'] ?? '';
        }
    } else {
        // Fallback: load from user_addresses table
        $addrStmt = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1");
        $addrStmt->execute([$order['user_id']]);
        $addr = $addrStmt->fetch(PDO::FETCH_ASSOC);
        if ($addr) {
            $order['parsed_address'] = [
                'name'    => $order['customer_name'] ?? 'Customer',
                'address' => implode(', ', array_filter([$addr['flat_number'] ?? '', $addr['address_line1'] ?? '', $addr['address_line2'] ?? '', $addr['landmark'] ?? ''])),
                'city'    => $addr['city'] ?? '',
                'state'   => $addr['state'] ?? '',
                'pincode' => $addr['zip_code'] ?? ($addr['pincode'] ?? ''),
                'phone'   => $addr['phone'] ?? ($order['customer_phone'] ?? ''),
            ];
        }
    }
    if (empty($order['parsed_address']['name'])) {
        $order['parsed_address']['name'] = $order['customer_name'] ?? 'Customer';
    }
    if (empty($order['parsed_address']['phone'])) {
        $order['parsed_address']['phone'] = $order['customer_phone'] ?? '';
    }
}
unset($order);

// Company profile
$company = get_company_profile_for_documents();

// Determine payment type
function label_payment_badge($order): array {
    $method = strtolower($order['payment_method'] ?? '');
    if (strpos($method, 'cod') !== false || strpos($method, 'cash') !== false) {
        return ['text' => 'COD', 'class' => 'badge-cod', 'amount' => $order['total_amount']];
    }
    return ['text' => 'PREPAID', 'class' => 'badge-prepaid', 'amount' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shipping Labels — <?= count($orders); ?> Order(s)</title>
<style>
/* === Shipping Label — Multi-Layout === */
<?php if ($layout === 1): ?>
@page { size: 4in 6in; margin: 0; }
<?php elseif ($layout === 2): ?>
@page { size: A4; margin: 8mm; }
<?php else: ?>
@page { size: A4; margin: 0; }
<?php endif; ?>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Arial', 'Helvetica Neue', sans-serif; background: #f0f0f0; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

<?php if ($layout === 1): ?>
.label {
    width: 4in; max-width: 420px; background: #fff; border: 1px solid #ccc;
    margin: 10px auto; page-break-after: always; page-break-inside: avoid;
    display: flex; flex-direction: column; overflow: hidden;
}
<?php elseif ($layout === 2): ?>
.label-page {
    width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff;
    display: flex; flex-direction: column; align-items: center;
    gap: 8mm; padding: 10mm 15mm;
    page-break-after: always; page-break-inside: avoid;
}
.label {
    width: 180mm; height: 130mm; background: #fff; border: 1px solid #ccc;
    display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0;
}
<?php else: /* layout=4 */ ?>
.label-page {
    width: 210mm; height: 297mm; margin: 0 auto; background: #fff;
    display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr;
    gap: 2mm; padding: 3mm;
    page-break-after: always; page-break-inside: avoid;
    box-sizing: border-box;
}
.label {
    background: #fff; border: 1px solid #333;
    display: flex; flex-direction: column; overflow: hidden;
    height: 100%; width: 100%;
}
.label .lbl-barcode-section { flex-grow: 1; justify-content: flex-end; }
<?php endif; ?>

/* --- Header: Company + Payment Badge --- */
.lbl-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 14px 8px;
    border-bottom: 2px solid #000;
}
.lbl-company { display: flex; align-items: center; gap: 8px; }
.lbl-logo { width: 36px; height: 36px; object-fit: contain; }
.lbl-company-info { font-size: 8px; line-height: 1.3; }
.lbl-company-name { font-weight: 900; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.lbl-company-sub { color: #444; }
.badge-payment { font-size: 13px; font-weight: 900; padding: 4px 12px; border-radius: 4px; letter-spacing: 1px; text-transform: uppercase; border: 2px solid; }
.badge-cod { background: #000; color: #fff; border-color: #000; }
.badge-prepaid { background: #fff; color: #000; border-color: #000; }

/* --- Ship To --- */
.lbl-shipto {
    padding: 10px 14px 8px;
    border-bottom: 1.5px dashed #999;
    flex-shrink: 0;
}
.lbl-section-title { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 3px; }
.lbl-customer-name { font-size: 13px; font-weight: 800; margin-bottom: 2px; }
.lbl-address { font-size: 10px; line-height: 1.4; color: #222; }
.lbl-phone { font-size: 10px; font-weight: 600; margin-top: 3px; }
.lbl-pin-row { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.lbl-pin { font-size: 22px; font-weight: 900; letter-spacing: 2px; }
.lbl-city-state { font-size: 10px; font-weight: 600; color: #333; }

/* --- Return + Tracking --- */
.lbl-middle {
    display: flex;
    border-bottom: 1.5px solid #000;
    flex-shrink: 0;
}
.lbl-return {
    flex: 1;
    padding: 8px 14px;
    border-right: 1px dashed #999;
    font-size: 8px;
    line-height: 1.3;
}
.lbl-return-name { font-weight: 700; font-size: 9px; }
.lbl-tracking { flex: 1; padding: 8px 14px; }
.lbl-tracking-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #666; margin-bottom: 2px; }
.lbl-tracking-val { font-size: 11px; font-weight: 800; letter-spacing: 0.5px; word-break: break-all; }
.lbl-courier { font-size: 9px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; color: #333; }
.lbl-ship-date { font-size: 8px; color: #555; margin-top: 6px; }
.lbl-ship-date strong { color: #000; }

/* --- QR + Order Info --- */
.lbl-qr-section {
    display: flex;
    align-items: center;
    padding: 8px 14px;
    border-bottom: 1.5px solid #000;
    gap: 12px;
    flex-shrink: 0;
}
.lbl-qr canvas, .lbl-qr img { width: 72px !important; height: 72px !important; }
.lbl-order-meta { flex: 1; font-size: 9px; line-height: 1.5; }
.lbl-order-meta strong { font-size: 10px; }
.lbl-order-id { font-size: 14px; font-weight: 900; letter-spacing: 0.5px; }
.lbl-ship-info {
    flex: 1;
    font-size: 8px;
    line-height: 1.6;
    padding-left: 10px;
    border-left: 1px dashed #999;
}
.lbl-ship-info strong { font-weight: 700; }
.lbl-routing { font-size: 8px; color: #555; margin-top: 2px; }

/* --- Product Table --- */
.lbl-products {
    border-bottom: 1.5px solid #000;
    flex-shrink: 0;
}
.lbl-products table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8px;
}
.lbl-products th {
    background: #e8e8e8;
    font-weight: 700;
    font-size: 7px;
    text-transform: uppercase;
    padding: 3px 4px;
    border: 0.5px solid #999;
    text-align: center;
}
.lbl-products td {
    padding: 3px 4px;
    border: 0.5px solid #ccc;
    text-align: center;
    font-size: 7.5px;
    vertical-align: top;
}
.lbl-sku { font-size: 6.5px; color: #666; }

/* --- Barcode Footer --- */
.lbl-barcode-section {
    padding: 4px 14px 4px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.lbl-barcode-section svg { max-width: 100%; height: 40px; }
.lbl-barcode-details { font-size: 7px; color: #444; margin-top: 2px; letter-spacing: 0.3px; }

/* --- Helpline Strip --- */
.lbl-helpline {
    text-align: center;
    font-size: 8px;
    color: #333;
    padding: 3px 0;
    border-top: 1px dashed #ccc;
    font-weight: 600;
}

/* --- COD Amount Strip --- */
.lbl-cod-strip {
    background: #000;
    color: #fff;
    text-align: center;
    font-size: 12px;
    font-weight: 900;
    padding: 5px 0;
    letter-spacing: 1px;
}

/* --- Print Toolbar --- */
.print-toolbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: linear-gradient(135deg, #1e3a5f, #1a3c34);
    color: #fff;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 999;
    box-shadow: 0 2px 12px rgba(0,0,0,.3);
}
.print-toolbar button { background: #fff; color: #1e3a5f; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 700; font-size: 14px; cursor: pointer; }
.print-toolbar button:hover { background: #e0e0e0; }
.print-toolbar .tb-info { font-size: 13px; opacity: .9; }

@media print {
    .print-toolbar { display: none !important; }
    body { background: #fff; margin: 0; padding: 0; }
    div[style*="padding-top"] { padding-top: 0 !important; }
    <?php if ($layout === 1): ?>
    .label { border: none; margin: 0; box-shadow: none; }
    <?php elseif ($layout === 4): ?>
    .label-page { margin: 0; padding: 3mm; }
    .label { border: 1px solid #000; }
    <?php endif; ?>
}
</style>
</head>
<body>

<!-- Print Toolbar -->
<div class="print-toolbar">
    <button onclick="window.print()"><i class="fas fa-print"></i> Print Labels</button>
    <span class="tb-info"><?= count($orders); ?> label(s)</span>
    <select id="layoutSelect" onchange="changeLayout(this.value)" style="padding:4px 8px;border-radius:6px;border:none;font-size:12px;">
        <option value="1" <?= $layout===1?'selected':''; ?>>1 per page (4×6)</option>
        <option value="2" <?= $layout===2?'selected':''; ?>>2 per page (A4)</option>
        <option value="4" <?= $layout===4?'selected':''; ?>>4 per page (A4)</option>
    </select>
    <button onclick="window.close()" style="margin-left:auto; background:rgba(255,255,255,.2); color:#fff;">Close</button>
</div>

<div style="padding-top: 60px;">
<?php
$labelsPerPage = $layout;
$labelIndex = 0;
$totalLabels = count($orders);
foreach ($orders as $order):
    if ($layout > 1 && $labelIndex % $labelsPerPage === 0): ?>
<div class="label-page">
<?php endif;
    $addr = $order['parsed_address'];
    $payment = label_payment_badge($order);
    $courier = $order['courier_company'] ?? ($company['default_courier'] ?? '');
    $awb = $order['tracking_id'] ?? '';
    $shipDate = !empty($order['picked_up_at']) ? date('d M Y', strtotime($order['picked_up_at'])) : date('d M Y');
    $orderId = (int)$order['id'];
    $orderNo = 'GLAF-' . $orderId;
    $invoiceNo = 'GF' . str_pad($orderId, 5, '0', STR_PAD_LEFT);
    $invoiceDate = date('d-m-Y', strtotime($order['created_at']));
    // QR: public tracking URL if AWB exists, otherwise site tracking page
    if (!empty($awb)) {
        $qrData = "https://shiprocket.co/tracking/{$awb}";
    } else {
        $qrData = base_url("track-shipment.php?tracking={$orderId}");
    }
?>
<div class="label">

    <!-- Header -->
    <div class="lbl-header">
        <div class="lbl-company">
            <?php if (!empty($company['logo'])): ?>
            <img src="<?= htmlspecialchars($company['logo']); ?>" class="lbl-logo" alt="Logo">
            <?php endif; ?>
            <div class="lbl-company-info">
                <div class="lbl-company-name"><?= htmlspecialchars($company['brand_name'] ?: $company['company_name']); ?></div>
                <div class="lbl-company-sub"><?= htmlspecialchars($company['company_name']); ?></div>
            </div>
        </div>
        <div class="badge-payment <?= $payment['class']; ?>"><?= $payment['text']; ?></div>
    </div>

    <!-- Ship To -->
    <div class="lbl-shipto">
        <div class="lbl-section-title">Ship To</div>
        <div class="lbl-customer-name"><?= htmlspecialchars($addr['name']); ?></div>
        <div class="lbl-address"><?= htmlspecialchars($addr['address']); ?></div>
        <?php if (!empty($addr['phone'])): ?>
        <div class="lbl-phone"><i class="fas fa-phone-alt" style="font-size:8px;"></i> <?= htmlspecialchars($addr['phone']); ?></div>
        <?php endif; ?>
        <div class="lbl-pin-row">
            <div class="lbl-pin"><?= htmlspecialchars($addr['pincode']); ?></div>
            <div class="lbl-city-state"><?= htmlspecialchars(implode(', ', array_filter([$addr['city'], $addr['state']]))); ?></div>
        </div>
    </div>

    <!-- Return + Tracking -->
    <div class="lbl-middle">
        <div class="lbl-return">
            <div class="lbl-section-title">Return</div>
            <?php if (!empty($company['return_address'])): ?>
            <div class="lbl-return-name"><?= htmlspecialchars($company['brand_name'] ?: $company['company_name']); ?></div>
            <div><?= htmlspecialchars($company['return_address']); ?></div>
            <div><?= htmlspecialchars(implode(', ', array_filter([$company['return_city'], $company['return_state'], $company['return_pincode']]))); ?></div>
            <?php if (!empty($company['return_phone'])): ?>
            <div>Ph: <?= htmlspecialchars($company['return_phone']); ?></div>
            <?php endif; ?>
            <?php else: ?>
            <div class="lbl-return-name"><?= htmlspecialchars($company['brand_name']); ?></div>
            <div><?= htmlspecialchars($company['address']); ?></div>
            <div><?= htmlspecialchars(implode(', ', array_filter([$company['city'], $company['state'], $company['pincode']]))); ?></div>
            <?php endif; ?>
        </div>
        <div class="lbl-tracking">
            <?php if (!empty($courier)): ?>
            <div class="lbl-courier"><?= htmlspecialchars($courier); ?></div>
            <?php endif; ?>
            <div class="lbl-tracking-label">Tracking / AWB</div>
            <div class="lbl-tracking-val"><?= htmlspecialchars($awb ?: 'Pending'); ?></div>
            <?php if (!empty($awb)): ?>
            <div class="lbl-routing">Routing Code: SRI/PAM</div>
            <?php endif; ?>
            <div class="lbl-ship-date">Ship Date: <strong><?= $shipDate; ?></strong></div>
        </div>
    </div>

    <!-- QR + Order Info -->
    <div class="lbl-qr-section">
        <div class="lbl-qr" id="qr-<?= $orderId; ?>"></div>
        <div class="lbl-order-meta">
            <div class="lbl-section-title">Order Details</div>
            <div class="lbl-order-id">#<?= $orderNo; ?></div>
            <div><strong>Invoice No:</strong> <?= $invoiceNo; ?></div>
            <div><strong>Invoice Date:</strong> <?= $invoiceDate; ?></div>
            <div><strong>Items:</strong> <?= $order['item_count']; ?></div>
            <div><strong>Weight:</strong> <?= number_format($order['pkg_weight'], 2); ?> kg</div>
            <div><strong>Amount:</strong> ₹<?= number_format($order['total_amount'], 2); ?> (<?= $payment['text']; ?>)</div>
        </div>
        <div class="lbl-ship-info">
            <div>Dimensions: <strong><?= number_format($order['pkg_length'],2); ?>*<?= number_format($order['pkg_width'],2); ?>*<?= number_format($order['pkg_height'],2); ?>(cm)</strong></div>
            <div>Payment: <strong><?= $payment['text']; ?></strong></div>
            <div>ORDER TOTAL: <strong><?= number_format($order['total_amount'], 2); ?> INR</strong></div>
            <div>Weight: <strong><?= number_format($order['pkg_weight'], 2); ?> kg</strong></div>
            <div>eWaybill No.: <strong>N/A</strong></div>
        </div>
    </div>

    <!-- Order Barcode -->
    <div class="lbl-barcode-section">
        <svg id="barcode-order-<?= $orderId; ?>"></svg>
        <div class="lbl-barcode-details">
            <?= $orderNo; ?> | <?= $invoiceNo; ?> | <?= $invoiceDate; ?> | Items: <?= $order['item_count']; ?> | <?= number_format($order['pkg_weight'], 2); ?>kg | ₹<?= number_format($order['total_amount'], 2); ?> (<?= $payment['text']; ?>)
        </div>
    </div>

    <!-- Product Table -->
    <?php if (!empty($order['items'])): ?>
    <div class="lbl-products">
        <table>
            <thead>
                <tr>
                    <th style="width:32%;">Product Name &amp; SKU</th>
                    <th>HSN</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Taxable Value</th>
                    <th>CGST</th>
                    <th>SGST</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item):
                $qty = (int)$item['quantity'];
                $unitPrice = (float)$item['price'];
                $taxableValue = $unitPrice * $qty;
                $gstRate = (float)($item['gst_rate'] ?? 0);
                $cgst = $taxableValue * ($gstRate / 2) / 100;
                $sgst = $taxableValue * ($gstRate / 2) / 100;
                $lineTotal = $taxableValue + $cgst + $sgst;
            ?>
                <tr>
                    <td style="text-align:left;"><?= htmlspecialchars($item['product_name'] ?? 'Product'); ?><br><span class="lbl-sku">SKU: SKU-<?= (int)$item['product_id']; ?></span></td>
                    <td><?= htmlspecialchars($item['hsn_code'] ?? ''); ?></td>
                    <td><?= $qty; ?></td>
                    <td><?= number_format($unitPrice, 2); ?></td>
                    <td><?= number_format($taxableValue, 2); ?></td>
                    <td><?= number_format($cgst, 2); ?></td>
                    <td><?= number_format($sgst, 2); ?></td>
                    <td><?= number_format($lineTotal, 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($company['phone'])): ?>
    <div class="lbl-helpline">Helpline: <?= htmlspecialchars($company['phone']); ?></div>
    <?php endif; ?>

    <!-- COD Strip (only for COD orders) -->
    <?php if ($payment['text'] === 'COD'): ?>
    <div class="lbl-cod-strip">COD — Collect ₹<?= number_format($payment['amount'], 2); ?></div>
    <?php endif; ?>

</div>
<?php
    $labelIndex++;
    if ($layout > 1 && ($labelIndex % $labelsPerPage === 0 || $labelIndex === $totalLabels)):
?>
</div><!-- /label-page -->
<?php endif; ?>
<?php endforeach; ?>
</div>

<!-- Font Awesome (for icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- JsBarcode for Code128 barcodes -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<!-- QR Code generator -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($orders as $order):
        $oid = (int)$order['id'];
        $awbVal = $order['tracking_id'] ?? '';
        if (!empty($awbVal)) {
            $qrUrl = "https://shiprocket.co/tracking/{$awbVal}";
        } else {
            $qrUrl = base_url("track-shipment.php?tracking={$oid}");
        }
    ?>
    // Order barcode (footer — always shown with GLAF order number)
    try {
        JsBarcode("#barcode-order-<?= $oid; ?>", "GLAF-<?= $oid; ?>", {
            format: "CODE128", width: 1.8, height: 40, displayValue: true,
            fontSize: 10, fontOptions: "bold", margin: 2
        });
    } catch(e) { console.warn('Barcode error for Order', e); }

    // QR Code
    (function() {
        var qrDiv = document.getElementById('qr-<?= $oid; ?>');
        if (!qrDiv) return;
        var qr = qrcode(0, 'M');
        qr.addData("<?= addslashes($qrUrl); ?>");
        qr.make();
        qrDiv.innerHTML = qr.createImgTag(3, 0);
    })();
    <?php endforeach; ?>
});

function changeLayout(val) {
    var url = new URL(window.location.href);
    url.searchParams.set('layout', val);
    window.location.href = url.toString();
}
</script>

</body>
</html>
