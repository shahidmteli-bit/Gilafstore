<?php
/**
 * PDF Invoice Generator
 * Generates professional PDF invoices using TCPDF library
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/invoice_functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Check if user is logged in
require_login();

// Get invoice ID from URL
$invoiceId = (int)($_GET['id'] ?? 0);

if (!$invoiceId) {
    die('Invalid invoice ID');
}

// Check if user has access to this invoice
if (!user_can_access_invoice($invoiceId, $_SESSION['user']['id'])) {
    die('Access denied');
}

// Get invoice details
$data = get_invoice_details($invoiceId);
if (!$data) {
    die('Invoice not found');
}

$invoice = $data['invoice'];
$order = $data['order'];
$items = $data['items'];
$user = $data['user'];
$company = get_company_details();

// Log invoice download
log_invoice_action($invoiceId, 'downloaded', $_SESSION['user']['id']);

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Gilaf Store');
$pdf->SetAuthor('Gilaf Store');
$pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
$pdf->SetSubject('Invoice');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Build HTML content
$html = '
<style>
    body { font-family: helvetica; }
    .header { text-align: center; margin-bottom: 20px; }
    .company-name { font-size: 24px; font-weight: bold; color: #1A3C34; }
    .invoice-title { font-size: 20px; font-weight: bold; color: #C5A059; margin-top: 10px; }
    .section { margin-bottom: 15px; }
    .label { font-weight: bold; color: #333; }
    .value { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background-color: #1A3C34; color: white; padding: 10px; text-align: left; font-weight: bold; }
    td { padding: 8px; border-bottom: 1px solid #ddd; }
    .text-right { text-align: right; }
    .total-row { background-color: #f8f9fa; font-weight: bold; }
    .grand-total { background-color: #1A3C34; color: white; font-size: 14px; }
    .footer { margin-top: 30px; padding-top: 20px; border-top: 2px solid #1A3C34; font-size: 9px; color: #666; }
    .terms { margin-top: 20px; font-size: 9px; color: #666; }
    .status-badge { padding: 5px 10px; border-radius: 3px; font-weight: bold; display: inline-block; }
    .status-paid { background-color: #d4edda; color: #155724; }
    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-failed { background-color: #f8d7da; color: #721c24; }
</style>

<div class="header">
    <div class="company-name">' . htmlspecialchars($company['name']) . '</div>
    <div style="font-size: 10px; color: #666; margin-top: 5px;">
        ' . htmlspecialchars($company['address']) . ', ' . htmlspecialchars($company['city']) . ', ' . htmlspecialchars($company['state']) . ' - ' . htmlspecialchars($company['pincode']) . '<br>
        Phone: ' . htmlspecialchars($company['phone']) . ' | Email: ' . htmlspecialchars($company['email']) . '<br>
        Website: ' . htmlspecialchars($company['website']) . '
    </div>
    <div class="invoice-title">TAX INVOICE</div>
</div>

<table style="width: 100%; margin-bottom: 20px; border: none;">
    <tr>
        <td style="width: 50%; border: none; vertical-align: top;">
            <div class="section">
                <div class="label">BILL TO:</div>
                <div style="margin-top: 5px;">
                    <strong>' . htmlspecialchars($user['name']) . '</strong><br>
                    ' . htmlspecialchars($user['email']) . '<br>';

// Add billing address if available
if (!empty($order['shipping_address'])) {
    $address = json_decode($order['shipping_address'], true);
    if ($address) {
        $html .= htmlspecialchars($address['address_line1'] ?? '') . '<br>';
        if (!empty($address['address_line2'])) {
            $html .= htmlspecialchars($address['address_line2']) . '<br>';
        }
        $html .= htmlspecialchars($address['city'] ?? '') . ', ' . htmlspecialchars($address['state'] ?? '') . ' - ' . htmlspecialchars($address['zip_code'] ?? '') . '<br>';
        if (!empty($address['phone'])) {
            $html .= 'Phone: ' . htmlspecialchars($address['phone']) . '<br>';
        }
    }
}

$html .= '
                </div>
            </div>
        </td>
        <td style="width: 50%; border: none; vertical-align: top; text-align: right;">
            <div class="section">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="border: none; text-align: right;"><span class="label">Invoice Number:</span></td>
                        <td style="border: none; text-align: right;">' . htmlspecialchars($invoice['invoice_number']) . '</td>
                    </tr>
                    <tr>
                        <td style="border: none; text-align: right;"><span class="label">Order ID:</span></td>
                        <td style="border: none; text-align: right;">#' . htmlspecialchars($order['id']) . '</td>
                    </tr>
                    <tr>
                        <td style="border: none; text-align: right;"><span class="label">Invoice Date:</span></td>
                        <td style="border: none; text-align: right;">' . date('d M Y', strtotime($invoice['invoice_date'])) . '</td>
                    </tr>
                    <tr>
                        <td style="border: none; text-align: right;"><span class="label">Payment Status:</span></td>
                        <td style="border: none; text-align: right;">
                            <span class="status-badge status-' . strtolower($invoice['payment_status']) . '">' . strtoupper($invoice['payment_status']) . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; text-align: right;"><span class="label">Payment Method:</span></td>
                        <td style="border: none; text-align: right;">' . strtoupper($invoice['payment_method']) . '</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 10%;">SKU</th>
                <th style="width: 45%;">Item Description</th>
                <th style="width: 10%;" class="text-right">Qty</th>
                <th style="width: 15%;" class="text-right">Unit Price</th>
                <th style="width: 15%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>';

$itemNumber = 1;
foreach ($items as $item) {
    $lineTotal = $item['quantity'] * $item['price'];
    $html .= '
            <tr>
                <td>' . $itemNumber++ . '</td>
                <td>' . htmlspecialchars($item['sku'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($item['name']) . '</td>
                <td class="text-right">' . $item['quantity'] . '</td>
                <td class="text-right">' . format_invoice_currency($item['price']) . '</td>
                <td class="text-right">' . format_invoice_currency($lineTotal) . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
</div>

<table style="width: 100%; margin-top: 20px;">
    <tr>
        <td style="width: 60%; border: none;"></td>
        <td style="width: 40%; border: none;">
            <table style="width: 100%;">
                <tr>
                    <td style="border: none; padding: 5px;"><strong>Subtotal:</strong></td>
                    <td style="border: none; padding: 5px; text-align: right;">' . format_invoice_currency($invoice['subtotal']) . '</td>
                </tr>';

if ($invoice['tax_amount'] > 0) {
    $html .= '
                <tr>
                    <td style="border: none; padding: 5px;"><strong>Tax (GST):</strong></td>
                    <td style="border: none; padding: 5px; text-align: right;">' . format_invoice_currency($invoice['tax_amount']) . '</td>
                </tr>';
}

if ($invoice['shipping_amount'] > 0) {
    $html .= '
                <tr>
                    <td style="border: none; padding: 5px;"><strong>Shipping:</strong></td>
                    <td style="border: none; padding: 5px; text-align: right;">' . format_invoice_currency($invoice['shipping_amount']) . '</td>
                </tr>';
}

if ($invoice['discount_amount'] > 0) {
    $html .= '
                <tr>
                    <td style="border: none; padding: 5px;"><strong>Discount:</strong></td>
                    <td style="border: none; padding: 5px; text-align: right;">-' . format_invoice_currency($invoice['discount_amount']) . '</td>
                </tr>';
}

$html .= '
                <tr class="grand-total">
                    <td style="padding: 10px;"><strong>GRAND TOTAL:</strong></td>
                    <td style="padding: 10px; text-align: right;"><strong>' . format_invoice_currency($invoice['total_amount']) . '</strong></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="terms">
    <strong>Terms & Conditions:</strong><br>';

foreach (get_invoice_terms() as $term) {
    $html .= '• ' . htmlspecialchars($term) . '<br>';
}

$html .= '
</div>

<div class="footer">
    <div style="text-align: center;">
        <strong>Thank you for your business!</strong><br>
        This is a computer-generated invoice and does not require a signature.<br>
        For any queries, please contact us at ' . htmlspecialchars($company['email']) . ' or call ' . htmlspecialchars($company['phone']) . '
    </div>
</div>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$filename = 'Invoice_' . $invoice['invoice_number'] . '.pdf';
$pdf->Output($filename, 'D'); // D = force download
exit;
