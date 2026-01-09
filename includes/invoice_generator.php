<?php
/**
 * Invoice Generator for Gilaf Store
 * Generates professional HTML invoices (print to PDF)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/settings.php';

function generateInvoice($orderId)
{
    $db = get_db_connection();
    
    // Fetch order details
    $stmt = $db->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception("Order not found");
    }
    
    // Fetch order items with product details
    $stmt = $db->prepare("
        SELECT oi.*, p.name, p.ean, p.weight
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse delivery address - check if field exists first
    $deliveryAddress = isset($order['delivery_address']) ? $order['delivery_address'] : null;
    $address = [];
    
    if ($deliveryAddress) {
        $decoded = json_decode($deliveryAddress, true);
        if (is_array($decoded)) {
            $address = $decoded;
        }
    }
    
    // Set default values for missing fields
    $address = array_merge([
        'name' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'pincode' => '',
        'phone' => '',
        'customer_phone' => ''
    ], $address);
    
    // Calculate totals
    $subtotal = 0;
    $totalGST = 0;
    $totalQuantity = 0;
    
    foreach ($items as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $gstRate = $item['gst_rate'] ?? 5;
        $itemGST = ($itemTotal * $gstRate) / (100 + $gstRate);
        $itemSubtotal = $itemTotal - $itemGST;
        
        $subtotal += $itemSubtotal;
        $totalGST += $itemGST;
        $totalQuantity += $item['quantity'];
    }
    
    $amountInWords = convertNumberToWords($order['total_amount']);
    $qrData = "upi://pay?pa=gilaffoods@paytm&pn=GILAF FOODS & SPICES&am=" . $order['total_amount'] . "&cu=INR&tn=Order" . $orderId;
    
    // Return HTML invoice
    return generateInvoiceHTML($order, $items, $address, $amountInWords, $qrData, $totalQuantity);
}

function generateInvoiceHTML($order, $items, $address, $amountInWords, $qrData, $totalQuantity)
{
    $invoiceNo = 'NGST/' . str_pad($order['id'], 3, '0', STR_PAD_LEFT) . '/25';
    $invoiceDate = date('d-M-y', strtotime($order['created_at']));
    $printDate = date('d-M-y \a\t H:i');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice #<?= $order['id'] ?></title>
        <style>
            @media print {
                body { margin: 0; padding: 0; }
                .no-print { display: none !important; }
                @page { 
                    size: A4;
                    margin: 8mm;
                }
            }
            * {
                box-sizing: border-box;
            }
            body {
                font-family: 'Arial', 'Helvetica', sans-serif;
                font-size: 10px;
                line-height: 1.3;
                color: #000;
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto;
                padding: 8mm;
                background: white;
            }
            .invoice-header {
                text-align: center;
                margin-bottom: 8px;
                border-bottom: 2px solid #1A3C34;
                padding-bottom: 6px;
            }
            .invoice-header h1 {
                margin: 0 0 3px 0;
                font-size: 18px;
                color: #1A3C34;
                letter-spacing: 1px;
            }
            .invoice-header p {
                margin: 0;
                font-size: 9px;
                color: #666;
            }
            .invoice-title {
                text-align: center;
                font-size: 14px;
                font-weight: bold;
                margin: 8px 0;
                text-decoration: underline;
            }
            .print-date {
                text-align: right;
                font-size: 8px;
                font-style: italic;
                margin-bottom: 6px;
                color: #666;
            }
            .two-column {
                display: table;
                width: 100%;
                margin-bottom: 8px;
                table-layout: fixed;
            }
            .column {
                display: table-cell;
                vertical-align: top;
                width: 50%;
                padding: 0 2px;
            }
            .company-details {
                border: 1px solid #333;
                padding: 6px;
                font-size: 9px;
                line-height: 1.4;
            }
            .company-details strong {
                font-size: 10px;
            }
            .invoice-details {
                border: 1px solid #333;
            }
            .invoice-details table {
                width: 100%;
                border-collapse: collapse;
            }
            .invoice-details td {
                border: 1px solid #333;
                padding: 3px 5px;
                font-size: 8.5px;
            }
            .invoice-details td:first-child {
                background: #f5f5f5;
                font-weight: bold;
                width: 48%;
            }
            .section-title {
                font-weight: bold;
                margin: 6px 0 3px 0;
                font-size: 10px;
                background: #f0f0f0;
                padding: 2px 4px;
                border-left: 3px solid #1A3C34;
            }
            .customer-details {
                border: 1px solid #333;
                padding: 5px;
                margin-bottom: 6px;
                font-size: 9px;
                line-height: 1.4;
            }
            .customer-details strong {
                font-size: 9.5px;
            }
            table.items {
                width: 100%;
                border-collapse: collapse;
                margin: 8px 0;
            }
            table.items th {
                background: #e8e8e8;
                border: 1px solid #333;
                padding: 4px 3px;
                font-size: 8.5px;
                text-align: center;
                font-weight: bold;
            }
            table.items td {
                border: 1px solid #333;
                padding: 3px;
                font-size: 9px;
            }
            table.items td.center { text-align: center; }
            table.items td.right { text-align: right; }
            table.items tbody tr:last-child {
                background: #f5f5f5;
                font-weight: bold;
            }
            .amount-words {
                margin: 6px 0;
                padding: 5px 8px;
                background: #f9f9f9;
                border: 1px solid #ccc;
                font-size: 9px;
                position: relative;
            }
            .amount-words strong {
                font-size: 10px;
            }
            .eoe {
                position: absolute;
                right: 8px;
                top: 5px;
                font-size: 8px;
            }
            .footer-section {
                display: table;
                width: 100%;
                margin-top: 8px;
                table-layout: fixed;
            }
            .footer-column {
                display: table-cell;
                vertical-align: top;
                width: 50%;
                padding: 0 3px;
            }
            .qr-code {
                text-align: center;
                padding: 5px;
                border: 1px solid #ddd;
            }
            .qr-code img {
                width: 100px;
                height: 100px;
            }
            .qr-code div {
                margin-top: 3px;
                font-size: 8px;
                font-weight: bold;
            }
            .bank-details {
                padding: 5px 8px;
                font-size: 8.5px;
                line-height: 1.5;
                border: 1px solid #ddd;
            }
            .bank-details strong {
                font-size: 9px;
            }
            .declaration {
                margin-top: 8px;
                font-size: 8px;
                line-height: 1.4;
                padding: 5px;
                background: #fafafa;
                border: 1px solid #e0e0e0;
            }
            .declaration strong {
                font-size: 9px;
            }
            .signatures {
                display: table;
                width: 100%;
                margin-top: 12px;
                table-layout: fixed;
            }
            .signature-box {
                display: table-cell;
                text-align: center;
                border-top: 1px solid #333;
                padding-top: 4px;
                font-size: 8.5px;
                width: 33.33%;
            }
            .computer-generated {
                text-align: center;
                font-size: 8px;
                font-style: italic;
                margin-top: 10px;
                color: #888;
                border-top: 1px dashed #ccc;
                padding-top: 5px;
            }
            .no-print {
                text-align: center;
                margin: 15px 0;
                padding: 15px;
                background: #f0f0f0;
                border-radius: 8px;
            }
            .btn-print {
                background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
                color: white;
                padding: 12px 28px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                margin: 0 8px;
                box-shadow: 0 2px 8px rgba(26, 60, 52, 0.3);
                transition: all 0.3s ease;
            }
            .btn-print:hover {
                background: linear-gradient(135deg, #0f2820 0%, #1A3C34 100%);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(26, 60, 52, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
            <button class="btn-print" onclick="window.close()">✖ Close</button>
        </div>
        
        <div class="invoice-header">
            <h1>GILAF STORE</h1>
            <p>TASTE • CULTURE • CRAFT</p>
        </div>
        
        <div class="print-date">Printed on <?= $printDate ?></div>
        
        <div class="invoice-title">INVOICE</div>
        
        <div class="two-column">
            <div class="column">
                <div class="company-details">
                    <strong style="font-size: 12px;">GILAF FOODS & SPICES</strong><br>
                    TAKIYARAI, ARAMPORA, SOPORE<br>
                    <strong>GSTIN/UIN:</strong> 01ABGFG2385F1ZU<br>
                    <strong>State Name:</strong> Jammu & Kashmir, Code: 01<br>
                    <strong>Contact:</strong> +91 8825041655<br>
                    <strong>E-Mail:</strong> Gilaffoods@gmail.com<br>
                    <strong>Website:</strong> www.gilafstore.com
                </div>
            </div>
            <div class="column">
                <div class="invoice-details">
                    <table>
                        <tr><td>Invoice No.</td><td><?= $invoiceNo ?></td></tr>
                        <tr><td>Dated</td><td><?= $invoiceDate ?></td></tr>
                        <tr><td>Delivery Note</td><td>Mode/Terms of Payment</td></tr>
                        <tr><td>Reference No. & Date</td><td>Order #<?= $order['id'] ?></td></tr>
                        <tr><td>Buyer's Order No.</td><td><?= $invoiceDate ?></td></tr>
                        <tr><td>Dispatch Doc No.</td><td>Delivery Note Date</td></tr>
                        <tr><td>Dispatched through</td><td>Destination</td></tr>
                        <tr><td>Terms of Delivery</td><td></td></tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="section-title">Consignee (Ship to)</div>
        <div class="customer-details">
            <strong><?= htmlspecialchars($address['name'] ?? $order['customer_name']) ?></strong><br>
            <?= htmlspecialchars($address['address'] ?? 'N/A') ?><br>
            <?= htmlspecialchars($address['city'] ?? '') ?>, <?= htmlspecialchars($address['state'] ?? '') ?>, Code: <?= htmlspecialchars($address['pincode'] ?? '') ?><br>
            <strong>Phone:</strong> <?= htmlspecialchars($address['phone'] ?? $order['customer_phone']) ?>
        </div>
        
        <div class="section-title">Buyer (Bill to)</div>
        <div class="customer-details">
            <strong><?= htmlspecialchars($address['name'] ?? $order['customer_name']) ?></strong><br>
            <?= htmlspecialchars($address['address'] ?? 'N/A') ?><br>
            <strong>State Name:</strong> <?= htmlspecialchars($address['state'] ?? 'Jammu & Kashmir') ?>, Code: 01<br>
            <strong>Place of Supply:</strong> Jammu & Kashmir
        </div>
        
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">Sl No.</th>
                    <th style="width: 35%;">Description of Goods</th>
                    <th style="width: 15%;">EAN</th>
                    <th style="width: 10%;">GST Rate</th>
                    <th style="width: 10%;">Quantity</th>
                    <th style="width: 12%;">Rate</th>
                    <th style="width: 13%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $slNo = 1;
                foreach ($items as $item): 
                    $itemTotal = $item['price'] * $item['quantity'];
                    $gstRate = $item['gst_rate'] ?? 5;
                ?>
                <tr>
                    <td class="center"><?= $slNo++ ?></td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="center"><?= htmlspecialchars($item['ean'] ?? 'N/A') ?></td>
                    <td class="center"><?= $gstRate ?>%</td>
                    <td class="center"><?= $item['quantity'] ?> pcs</td>
                    <td class="right">₹<?= number_format($item['price'], 2) ?></td>
                    <td class="right">₹<?= number_format($itemTotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Get GST rate from settings
        $gstRate = get_gst_rate();
        
        // Calculate GST breakdown
        $totalAmount = $order['total_amount'];
        $gstMultiplier = 1 + ($gstRate / 100);
        $taxableAmount = $totalAmount / $gstMultiplier;
        $totalGSTAmount = $totalAmount - $taxableAmount;
        $cgst = $totalGSTAmount / 2; // Half of GST
        $sgst = $totalGSTAmount / 2; // Half of GST
        $halfGstRate = $gstRate / 2;
        ?>
        
        <table style="width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9px;">
            <tr>
                <td colspan="5" style="text-align: right; padding: 3px; border: 1px solid #333; background: #f5f5f5; font-weight: bold;">Taxable Amount:</td>
                <td style="text-align: right; padding: 3px; border: 1px solid #333; font-weight: bold;">₹<?= number_format($taxableAmount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align: right; padding: 3px; border: 1px solid #333;">CGST @ <?= number_format($halfGstRate, 2) ?>%:</td>
                <td style="text-align: right; padding: 3px; border: 1px solid #333;">₹<?= number_format($cgst, 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align: right; padding: 3px; border: 1px solid #333;">SGST @ <?= number_format($halfGstRate, 2) ?>%:</td>
                <td style="text-align: right; padding: 3px; border: 1px solid #333;">₹<?= number_format($sgst, 2) ?></td>
            </tr>
            <tr style="font-weight: bold; background: #e8e8e8;">
                <td colspan="5" style="text-align: right; padding: 3px; border: 1px solid #333; font-size: 10px;">Total Amount (Incl. GST):</td>
                <td style="text-align: right; padding: 3px; border: 1px solid #333; font-size: 10px;">₹<?= number_format($totalAmount, 2) ?></td>
            </tr>
        </table>
        
        <div class="amount-words">
            <strong>Amount Chargeable (in words):</strong><br>
            <strong>INR <?= $amountInWords ?> Only</strong>
            <span class="eoe">E. & O.E</span>
        </div>
        
        <div class="footer-section">
            <div class="footer-column">
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode($qrData) ?>" alt="UPI QR Code" style="width: 120px; height: 120px;">
                    <div style="margin-top: 5px; font-size: 9px;">Scan to pay</div>
                </div>
            </div>
            <div class="footer-column">
                <div class="bank-details">
                    <strong>Company's Bank Details</strong><br>
                    <strong>A/c Holder's Name:</strong> GILAF FOODS & SPICES<br>
                    <strong>Bank Name:</strong> J&K Grameen Bank A/c<br>
                    <strong>A/c No.:</strong> 35590101000032585<br>
                    <strong>Branch & IFS Code:</strong> J&K Grameen Bank, NewColony & JAKA0GRAMEN<br>
                    for GILAF FOODS & SPICES
                </div>
            </div>
        </div>
        
        <div class="declaration">
            <strong>Declaration</strong><br>
            We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.<br><br>
            <strong>Customer's Seal and Signature</strong>
        </div>
        
        <div class="signatures">
            <div class="signature-box">Prepared by</div>
            <div class="signature-box">Verified by</div>
            <div class="signature-box">Authorised Signatory</div>
        </div>
        
        <div class="computer-generated">This is a Computer Generated Invoice</div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function convertNumberToWords($number)
{
    $number = (int)$number;
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );
    
    if ($number < 21) {
        return $words[$number];
    } elseif ($number < 100) {
        $tens = ((int)($number / 10)) * 10;
        $units = $number % 10;
        return $words[$tens] . ($units ? ' ' . $words[$units] : '');
    } elseif ($number < 1000) {
        $hundreds = (int)($number / 100);
        $remainder = $number % 100;
        return $words[$hundreds] . ' Hundred' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    } elseif ($number < 100000) {
        $thousands = (int)($number / 1000);
        $remainder = $number % 1000;
        return convertNumberToWords($thousands) . ' Thousand' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    } else {
        $lakhs = (int)($number / 100000);
        $remainder = $number % 100000;
        return convertNumberToWords($lakhs) . ' Lakh' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    }
}
?>
