<?php
/**
 * GST Tax Invoice - Flipkart-style Professional Layout
 * A4 Portrait, Print-ready, Black & White
 * Access: Admin only
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/invoice_functions.php';

// Admin check
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = get_db_connection();

// Get order ID
$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
    die('Order ID is required. Usage: ?order_id=123');
}

// Fetch order
$order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
if (!$order) {
    die('Order #' . $orderId . ' not found.');
}

// Fetch user
$user = db_fetch('SELECT * FROM users WHERE id = ?', [$order['user_id']]);

// Fetch order items with product details
$items = db_fetch_all(
    'SELECT oi.*, p.name as product_name, p.image, p.sku, p.hsn_code,
            pw.display_weight
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.price = oi.price
     WHERE oi.order_id = ?',
    [$orderId]
);

// Get or create invoice
$invoice = db_fetch('SELECT * FROM invoices WHERE order_id = ?', [$orderId]);
if (!$invoice) {
    $invoice = create_invoice($orderId);
}
$invoiceNumber = $invoice['invoice_number'] ?? ('INV-' . date('Ymd') . '-' . $orderId);
$invoiceDate = isset($invoice['invoice_date']) ? date('d-m-Y', strtotime($invoice['invoice_date'])) : date('d-m-Y');

// Company details
$company = get_company_details();

// Parse shipping address
$billTo = ['name' => '', 'address' => '', 'phone' => ''];
$shipTo = ['name' => '', 'address' => '', 'phone' => ''];

if (!empty($order['shipping_address'])) {
    $addrData = json_decode($order['shipping_address'], true);
    if (is_array($addrData)) {
        $name = $addrData['full_name'] ?? ($user['name'] ?? 'Customer');
        $addrParts = array_filter([
            $addrData['address_line1'] ?? '',
            $addrData['address_line2'] ?? '',
            $addrData['city'] ?? '',
            $addrData['state'] ?? '',
            ($addrData['pincode'] ?? ($addrData['zip_code'] ?? ''))
        ]);
        $phone = $addrData['phone'] ?? '';
        $billTo = ['name' => $name, 'address' => implode(', ', $addrParts), 'phone' => $phone];
        $shipTo = $billTo;
    } else {
        $billTo = ['name' => $user['name'] ?? 'Customer', 'address' => $order['shipping_address'], 'phone' => ''];
        $shipTo = $billTo;
    }
} else {
    // Fallback to user address
    $addr = db_fetch('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1', [$order['user_id']]);
    if ($addr) {
        $name = $addr['full_name'] ?? ($user['name'] ?? 'Customer');
        $addrParts = array_filter([
            $addr['address_line1'] ?? '',
            $addr['address_line2'] ?? '',
            $addr['city'] ?? '',
            $addr['state'] ?? '',
            $addr['zip_code'] ?? ''
        ]);
        $phone = $addr['phone'] ?? '';
        $billTo = ['name' => $name, 'address' => implode(', ', $addrParts), 'phone' => $phone];
        $shipTo = $billTo;
    } else {
        $billTo = ['name' => $user['name'] ?? 'Customer', 'address' => 'Address not available', 'phone' => ''];
        $shipTo = $billTo;
    }
}

// Calculate GST (assuming 18% IGST for now - can be configured)
$gstRate = 18.0;
$orderDate = date('d-m-Y', strtotime($order['created_at']));
$paymentMethod = strtoupper($order['payment_method'] ?? 'N/A');
$totalAmount = (float)$order['total_amount'];
$upiDiscount = (float)($order['upi_discount'] ?? 0);
$promoDiscount = (float)($order['promo_discount'] ?? 0);
$totalDiscount = $upiDiscount + $promoDiscount;

// Number to words for Indian currency
function numberToWords($number) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $number = (int)round($number);
    if ($number == 0) return 'Zero';

    $words = '';
    if ($number >= 10000000) {
        $words .= numberToWords(floor($number / 10000000)) . ' Crore ';
        $number %= 10000000;
    }
    if ($number >= 100000) {
        $words .= numberToWords(floor($number / 100000)) . ' Lakh ';
        $number %= 100000;
    }
    if ($number >= 1000) {
        $words .= numberToWords(floor($number / 1000)) . ' Thousand ';
        $number %= 1000;
    }
    if ($number >= 100) {
        $words .= $ones[floor($number / 100)] . ' Hundred ';
        $number %= 100;
    }
    if ($number >= 20) {
        $words .= $tens[floor($number / 10)] . ' ';
        $number %= 10;
    }
    if ($number > 0) {
        $words .= $ones[$number] . ' ';
    }
    return trim($words);
}

$amountInWords = 'Rupees ' . numberToWords(floor($totalAmount)) . ' Only';
$paise = round(($totalAmount - floor($totalAmount)) * 100);
if ($paise > 0) {
    $amountInWords = 'Rupees ' . numberToWords(floor($totalAmount)) . ' and ' . numberToWords($paise) . ' Paise Only';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?= htmlspecialchars($invoiceNumber) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            background: #f5f5f5;
            line-height: 1.4;
        }
        .invoice-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 12mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
        }
        @media print {
            body { background: #fff; }
            .invoice-page { box-shadow: none; margin: 0; padding: 8mm; width: 100%; }
            .no-print { display: none !important; }
        }

        /* Header */
        .inv-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 0;
        }
        .inv-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        /* Seller Info Row */
        .seller-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #000;
        }
        .seller-left {
            flex: 1;
        }
        .seller-left .company-name {
            font-weight: 700;
            font-size: 12px;
        }
        .seller-left .addr {
            font-size: 10px;
            color: #333;
            margin-top: 2px;
        }
        .seller-left .gstin-line {
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
        }
        .seller-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }
        .qr-placeholder {
            width: 80px;
            height: 80px;
            border: 1px solid #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #999;
            background: #fafafa;
        }
        .inv-number-box {
            border: 2px solid #000;
            padding: 4px 10px;
            font-weight: 700;
            font-size: 11px;
            white-space: nowrap;
        }

        /* Info Grid */
        .info-grid {
            display: flex;
            border-bottom: 1px solid #000;
        }
        .info-col {
            padding: 8px;
            border-right: 1px solid #000;
            font-size: 10px;
        }
        .info-col:last-child {
            border-right: none;
        }
        .info-col.col-order { width: 22%; }
        .info-col.col-bill { width: 26%; }
        .info-col.col-ship { width: 26%; }
        .info-col.col-note { width: 26%; }
        .info-col .label {
            font-weight: 700;
            font-size: 10px;
            margin-bottom: 2px;
        }
        .info-col .value {
            font-size: 10px;
            color: #333;
        }
        .info-col .row-item {
            margin-bottom: 4px;
        }
        .info-col .row-item .lbl {
            font-weight: 700;
        }

        /* Items Table */
        .items-section {
            margin-top: 0;
        }
        .total-items {
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 700;
            border-bottom: 1px solid #000;
            background: #f9f9f9;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background: #fff;
            font-weight: 700;
            font-size: 10px;
            padding: 6px 5px;
            border-bottom: 2px solid #000;
            border-right: 1px solid #ccc;
            text-align: center;
            vertical-align: bottom;
        }
        .items-table th:last-child { border-right: none; }
        .items-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            border-right: 1px solid #eee;
            font-size: 10px;
            vertical-align: top;
        }
        .items-table td:last-child { border-right: none; }
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }

        .items-table .prod-name { font-weight: 700; font-size: 10px; }
        .items-table .prod-meta { font-size: 9px; color: #555; margin-top: 1px; }

        /* Total Row */
        .items-table .total-row td {
            font-weight: 700;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 8px 5px;
            background: #f9f9f9;
        }

        /* Grand Total */
        .grand-total-section {
            display: flex;
            justify-content: flex-end;
            padding: 12px 0 6px;
            border-bottom: 1px solid #000;
        }
        .grand-total-label {
            font-size: 14px;
            font-weight: 400;
            margin-right: 20px;
        }
        .grand-total-amount {
            font-size: 18px;
            font-weight: 700;
        }
        .amount-words {
            padding: 6px 0;
            font-size: 10px;
            font-style: italic;
            border-bottom: 1px solid #000;
        }

        /* Signature */
        .signature-section {
            display: flex;
            justify-content: space-between;
            padding: 20px 0 10px;
            min-height: 100px;
        }
        .sig-left {
            font-size: 10px;
            color: #555;
        }
        .sig-right {
            text-align: right;
        }
        .sig-company {
            font-weight: 700;
            font-size: 11px;
            margin-bottom: 40px;
        }
        .sig-line {
            font-size: 10px;
            color: #555;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        /* Footer */
        .inv-footer {
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 9px;
            color: #666;
            text-align: center;
            margin-top: 10px;
        }

        /* Print Button Bar */
        .print-bar {
            max-width: 210mm;
            margin: 15px auto;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .print-bar button, .print-bar a {
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print {
            background: #1A3C34;
            color: #fff;
        }
        .btn-print:hover { background: #15302a; }
        .btn-back {
            background: #e5e7eb;
            color: #333;
        }
        .btn-back:hover { background: #d1d5db; }
    </style>
</head>
<body>

<!-- Print Controls -->
<div class="print-bar no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
    <a class="btn-back" href="javascript:history.back()">← Back</a>
</div>

<div class="invoice-page">

    <!-- Header -->
    <div class="inv-header">
        <div class="inv-title">Tax Invoice</div>
    </div>

    <!-- Seller Info + QR + Invoice Number -->
    <div class="seller-row">
        <div class="seller-left">
            <div class="company-name">Sold By: <?= htmlspecialchars($company['company_name']) ?></div>
            <div class="addr">
                <strong>Ship-from Address:</strong> <?= htmlspecialchars($company['address']) ?>,
                <?= htmlspecialchars($company['state']) ?> – <?= htmlspecialchars($company['pincode']) ?>, India
            </div>
            <div class="gstin-line">GSTIN – <?= htmlspecialchars($company['gstin']) ?></div>
        </div>
        <div class="seller-right">
            <div class="qr-placeholder">
                QR Code
            </div>
            <div class="inv-number-box">
                Invoice Number # <?= htmlspecialchars($invoiceNumber) ?>
            </div>
        </div>
    </div>

    <!-- Order Info / Bill To / Ship To -->
    <div class="info-grid">
        <!-- Order Details Column -->
        <div class="info-col col-order">
            <div class="row-item">
                <span class="lbl">Order ID:</span><br>
                <span><?= htmlspecialchars($orderId) ?></span>
            </div>
            <div class="row-item">
                <span class="lbl">Order Date:</span><br>
                <span><?= $orderDate ?></span>
            </div>
            <div class="row-item">
                <span class="lbl">Invoice Date:</span><br>
                <span><?= $invoiceDate ?></span>
            </div>
            <div class="row-item">
                <span class="lbl">PAN:</span><br>
                <span><?= htmlspecialchars($company['pan']) ?></span>
            </div>
        </div>

        <!-- Bill To Column -->
        <div class="info-col col-bill">
            <div class="label">Bill To</div>
            <div class="value">
                <strong><?= htmlspecialchars($billTo['name']) ?></strong><br>
                <?= htmlspecialchars($billTo['address']) ?>
                <?php if ($billTo['phone']): ?>
                    <br>Phone: <?= htmlspecialchars($billTo['phone']) ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ship To Column -->
        <div class="info-col col-ship">
            <div class="label">Ship To</div>
            <div class="value">
                <strong><?= htmlspecialchars($shipTo['name']) ?></strong><br>
                <?= htmlspecialchars($shipTo['address']) ?>
                <?php if ($shipTo['phone']): ?>
                    <br>Phone: <?= htmlspecialchars($shipTo['phone']) ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Note Column -->
        <div class="info-col col-note" style="font-style:italic;color:#555;font-size:9px;display:flex;align-items:center;justify-content:center;text-align:center;">
            *Keep this invoice and<br>manufacturer box for<br>warranty purposes.
        </div>
    </div>

    <!-- Items Section -->
    <div class="items-section">
        <div class="total-items">Total Items: <?= count($items) ?></div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:18%;">Product</th>
                    <th style="width:22%;">Title</th>
                    <th style="width:6%;">Qty</th>
                    <th style="width:12%;">Gross<br>Amount ₹</th>
                    <th style="width:12%;">Discounts<br>/Coupons ₹</th>
                    <th style="width:12%;">Taxable<br>Value ₹</th>
                    <th style="width:9%;">IGST ₹</th>
                    <th style="width:9%;">Total ₹</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalQty = 0;
                $totalGross = 0;
                $totalDiscounts = 0;
                $totalTaxable = 0;
                $totalIGST = 0;
                $totalFinal = 0;

                foreach ($items as $item):
                    $qty = (int)$item['quantity'];
                    $unitPrice = (float)$item['price'];
                    $grossAmount = $unitPrice * $qty;

                    // Proportional discount for this item
                    $itemDiscount = 0;
                    if ($totalAmount > 0 && $totalDiscount > 0) {
                        $itemDiscount = round(($grossAmount / ($totalAmount + $totalDiscount)) * $totalDiscount, 2);
                    }

                    // Taxable = Gross - Discount, then reverse-calculate tax
                    $afterDiscount = $grossAmount - $itemDiscount;
                    $taxableValue = round($afterDiscount / (1 + ($gstRate / 100)), 2);
                    $igstAmount = round($afterDiscount - $taxableValue, 2);
                    $lineTotal = round($taxableValue + $igstAmount, 2);

                    $totalQty += $qty;
                    $totalGross += $grossAmount;
                    $totalDiscounts += $itemDiscount;
                    $totalTaxable += $taxableValue;
                    $totalIGST += $igstAmount;
                    $totalFinal += $lineTotal;

                    $productName = htmlspecialchars($item['product_name'] ?? 'Product');
                    $weight = !empty($item['display_weight']) ? htmlspecialchars($item['display_weight']) : '';
                    $sku = htmlspecialchars($item['sku'] ?? '');
                    $hsn = htmlspecialchars($item['hsn_code'] ?? '');
                ?>
                <tr>
                    <td>
                        <?php if ($sku): ?>
                            <div class="prod-meta">SKU: <?= $sku ?></div>
                        <?php endif; ?>
                        <?php if ($hsn): ?>
                            <div class="prod-meta">HSN/SAC: <?= $hsn ?></div>
                        <?php endif; ?>
                        <div class="prod-meta"><strong>IGST:</strong> <?= number_format($gstRate, 1) ?> %</div>
                    </td>
                    <td>
                        <div class="prod-name"><?= $productName ?></div>
                        <?php if ($weight): ?>
                            <div class="prod-meta"><?= $weight ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= $qty ?></td>
                    <td class="text-right"><?= number_format($grossAmount, 2) ?></td>
                    <td class="text-right"><?= $itemDiscount > 0 ? '-' . number_format($itemDiscount, 2) : '0.00' ?></td>
                    <td class="text-right"><?= number_format($taxableValue, 2) ?></td>
                    <td class="text-right"><?= number_format($igstAmount, 2) ?></td>
                    <td class="text-right"><?= number_format($lineTotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="2" class="text-center" style="font-size:11px;">Total</td>
                    <td class="text-center"><?= $totalQty ?></td>
                    <td class="text-right"><?= number_format($totalGross, 2) ?></td>
                    <td class="text-right"><?= $totalDiscounts > 0 ? '-' . number_format($totalDiscounts, 2) : '0.00' ?></td>
                    <td class="text-right"><?= number_format($totalTaxable, 2) ?></td>
                    <td class="text-right"><?= number_format($totalIGST, 2) ?></td>
                    <td class="text-right"><?= number_format($totalFinal, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Grand Total -->
    <div class="grand-total-section">
        <span class="grand-total-label">Grand Total</span>
        <span class="grand-total-amount">₹ <?= number_format($totalAmount, 2) ?></span>
    </div>

    <!-- Amount in Words -->
    <div class="amount-words">
        <strong>Amount in Words:</strong> <?= $amountInWords ?>
    </div>

    <!-- Signature -->
    <div class="signature-section">
        <div class="sig-left">
            <strong>Payment Method:</strong> <?= $paymentMethod ?><br>
            <?php if (!empty($order['razorpay_payment_id'])): ?>
                <strong>Transaction ID:</strong> <?= htmlspecialchars($order['razorpay_payment_id']) ?><br>
            <?php endif; ?>
            <br>
            <strong>Terms & Conditions:</strong><br>
            1. Goods once sold will not be taken back.<br>
            2. Subject to Sopore jurisdiction.<br>
            3. E. & O.E.
        </div>
        <div class="sig-right">
            <div class="sig-company"><?= htmlspecialchars($company['company_name']) ?></div>
            <div class="sig-line">Authorized Signatory</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <?= htmlspecialchars($company['company_name']) ?> &nbsp;|&nbsp;
        GSTIN: <?= htmlspecialchars($company['gstin']) ?> &nbsp;|&nbsp;
        <?= htmlspecialchars($company['address']) ?>, <?= htmlspecialchars($company['state']) ?> – <?= htmlspecialchars($company['pincode']) ?> &nbsp;|&nbsp;
        Phone: <?= htmlspecialchars($company['phone']) ?> &nbsp;|&nbsp;
        Email: <?= htmlspecialchars($company['email']) ?> &nbsp;|&nbsp;
        Web: <?= htmlspecialchars($company['website']) ?>
        <br>
        <em>This is a computer-generated invoice and does not require a physical signature.</em>
    </div>

</div>

</body>
</html>
