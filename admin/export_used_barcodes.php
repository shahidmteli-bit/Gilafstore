<?php
ob_start();
set_time_limit(600);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
ini_set('display_errors', 0);
require_admin();

$format = $_GET['format'] ?? 'excel';
$db = get_db_connection();

// Accept filter parameters
$filterCategory = $_GET['category_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

$where = ["bi.deleted_at IS NULL"];
$params = [];

if ($filterCategory !== '' && $filterCategory != '0') {
    $where[] = "bi.category_id = ?";
    $params[] = $filterCategory;
}
if ($filterStatus !== '') {
    $where[] = "bi.status = ?";
    $params[] = $filterStatus;
} else {
    // Default: only Used if no status filter and coming from old links
    if ($format === 'pdf' && empty($filterCategory) && empty($searchQuery)) {
        $where[] = "bi.status = 'Used'";
    }
}
if ($searchQuery !== '') {
    $where[] = "(bi.sku_code LIKE ? OR bi.barcode_number LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$sql = "SELECT 
        bi.barcode_number, bi.sku_code, bi.status, bi.created_at,
        bi.weight_value,
        c.name AS category_name,
        p.name AS product_name
    FROM barcode_inventory bi
    LEFT JOIN categories c ON bi.category_id = c.id
    LEFT JOIN products   p ON bi.product_id  = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.name ASC, p.name ASC, bi.sku_code ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'excel') {
    // ---- HTML-based Excel Export with barcode images ----
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Barcode_Report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Barcodes</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>td{mso-number-format:\@;border:1px solid #ddd;padding:6px 10px;font-family:Segoe UI,Arial;font-size:11px;vertical-align:middle;} th{background:#1e3a5f;color:#fff;font-weight:bold;padding:8px 10px;border:1px solid #1e3a5f;font-family:Segoe UI,Arial;font-size:11px;text-align:left;} tr:nth-child(even) td{background:#f8fafc;} .mono{font-family:Consolas,monospace;} .ctr{text-align:center;}</style>';
    echo '</head><body>';
    echo '<table cellspacing="0" cellpadding="0">';
    echo '<tr><th>#</th><th>SKU Code</th><th>Product Name</th><th>Category</th><th>Barcode Number</th><th>Barcode Image</th><th>Weight</th><th>Status</th><th>Created Date</th></tr>';

    $n = 0;
    foreach ($rows as $r) {
        $n++;
        $weight = $r['weight_value'] > 0 ? $r['weight_value'] . 'g' : 'N/A';
        $stColor = '#166534';
        if ($r['status'] === 'Used') $stColor = '#1e40af';
        elseif ($r['status'] === 'Archived') $stColor = '#6b7280';

        // Generate inline barcode image
        $bcImg = generateSmallBarcodePNG($r['barcode_number']);
        $b64 = base64_encode($bcImg);

        echo '<tr>';
        echo '<td class="ctr">' . $n . '</td>';
        echo '<td class="mono" style="font-weight:bold;color:#1e3a5f;">' . htmlspecialchars($r['sku_code']) . '</td>';
        echo '<td>' . htmlspecialchars($r['product_name'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($r['category_name'] ?? 'N/A') . '</td>';
        echo '<td class="mono">' . htmlspecialchars($r['barcode_number']) . '</td>';
        echo '<td class="ctr"><img src="data:image/png;base64,' . $b64 . '" width="180" height="50"></td>';
        echo '<td class="ctr">' . $weight . '</td>';
        echo '<td style="color:' . $stColor . ';font-weight:bold;">' . htmlspecialchars($r['status']) . '</td>';
        echo '<td>' . date('d M Y', strtotime($r['created_at'])) . '</td>';
        echo '</tr>';
    }

    if ($n === 0) {
        echo '<tr><td colspan="9" class="ctr" style="padding:20px;color:#999;">No barcodes found matching the current filters.</td></tr>';
    }

    echo '</table></body></html>';
    exit;
}

// Barcode image generator for Excel
function generateSmallBarcodePNG($code) {
    $imgWidth = 360; $imgHeight = 100; $barWidth = 2; $barHeight = 60;
    $img = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    $gray = imagecolorallocate($img, 80, 80, 80);
    imagefill($img, 0, 0, $white);

    $encoded = encodeCode128B($code);
    $totalBarWidth = count($encoded) * $barWidth;
    $startX = (int)(($imgWidth - $totalBarWidth) / 2);
    $x = $startX;
    foreach ($encoded as $bar) {
        if ($bar) imagefilledrectangle($img, $x, 8, $x + $barWidth - 1, 8 + $barHeight, $black);
        $x += $barWidth;
    }
    $font = 3;
    $tw = imagefontwidth($font) * strlen($code);
    imagestring($img, $font, (int)(($imgWidth - $tw) / 2), 8 + $barHeight + 6, $code, $gray);

    ob_start();
    imagepng($img, null, 6);
    $data = ob_get_clean();
    imagedestroy($img);
    return $data;
}

function encodeCode128B($data) {
    $p = ['11011001100','11001101100','11001100110','10010011000','10010001100','10001001100','10011001000','10011000100','10001100100','11001001000','11001000100','11000100100','10110011100','10011011100','10011001110','10111001100','10011101100','10011100110','11001110010','11001011100','11001001110','11011100100','11001110100','11101101110','11101001100','11100101100','11100100110','11101100100','11100110100','11100110010','11011011000','11011000110','11000110110','10100011000','10001011000','10001000110','10110001000','10001101000','10001100010','11010001000','11000101000','11000100010','10110111000','10110001110','10001101110','10111011000','10111000110','10001110110','11101110110','11010001110','11000101110','11011101000','11011100010','11011101110','11101011000','11101000110','11100010110','11101101000','11101100010','11100011010','11101111010','11001000010','11110001010','10100110000','10100001100','10010110000','10010000110','10000101100','10000100110','10110010000','10110000100','10011010000','10011000010','10000110100','10000110010','11000010010','11001010000','11110111010','11000010100','10001111010','10100111100','10010111100','10010011110','10111100100','10011110100','10011110010','11110100100','11110010100','11110010010','11011011110','11011110110','11110110110','10101111000','10100011110','10001011110','10111101000','10111100010','11110101000','11110100010','10111011110','10111101110','11101011110','11110101110','11010000100','11010010000','11010011100','1100011101011'];
    $result = $p[104]; $checksum = 104;
    for ($i = 0; $i < strlen($data); $i++) {
        $v = ord($data[$i]) - 32;
        if ($v < 0 || $v > 94) $v = 0;
        $result .= $p[$v];
        $checksum += $v * ($i + 1);
    }
    $result .= $p[$checksum % 103];
    $result .= $p[106];
    return array_map('intval', str_split($result));
}

// ---- PDF Export (print-ready HTML) ----
if (ob_get_length()) ob_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Used Barcodes — Gilaf Foods & Spices</title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background:#fff; color:#1a1a1a; }
    .page-header { background: linear-gradient(135deg,#1A3C34,#2d5a4d); color:#fff; padding:28px 32px; display:flex; justify-content:space-between; align-items:center; }
    .page-header h1 { font-size:1.5rem; font-weight:700; letter-spacing:0.5px; }
    .page-header .meta { font-size:0.82rem; opacity:0.8; margin-top:4px; }
    .brand { font-size:0.85rem; opacity:0.9; text-align:right; }
    .export-actions { padding:16px 32px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:12px; }
    .export-actions button { padding:8px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-size:0.85rem; }
    .btn-print { background:#1A3C34; color:#fff; }
    .btn-close-pg { background:#e2e8f0; color:#374151; }
    .summary { padding:16px 32px 12px; display:flex; gap:24px; flex-wrap:wrap; }
    .summary-card { background:#f0fdf4; border-left:4px solid #10b981; border-radius:8px; padding:10px 18px; min-width:130px; }
    .summary-card .num { font-size:1.5rem; font-weight:700; color:#065f46; }
    .summary-card .lbl { font-size:0.72rem; text-transform:uppercase; color:#6b7280; letter-spacing:0.5px; }

    /* Group by category */
    .cat-section { margin:0 32px 24px; }
    .cat-title { font-size:0.9rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#1A3C34; padding:10px 0 6px; border-bottom:2px solid #1A3C34; margin-bottom:12px; }

    /* Barcode cards grid */
    .cards-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:14px; }
    .barcode-card { border:1px solid #e2e8f0; border-radius:10px; padding:14px 12px; text-align:center; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,0.06); page-break-inside:avoid; }
    .barcode-card svg { max-width:100%; height:55px; }
    .barcode-card .sku  { font-size:0.8rem; font-weight:700; color:#1A3C34; margin-top:6px; font-family:monospace; }
    .barcode-card .prod { font-size:0.72rem; color:#374151; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .barcode-card .num  { font-size:0.68rem; color:#6b7280; font-family:monospace; margin-top:2px; }
    .barcode-card .wt   { display:inline-block; background:#dbeafe; color:#1e40af; border-radius:999px; font-size:0.65rem; font-weight:600; padding:2px 8px; margin-top:4px; }
    .no-data { text-align:center; padding:60px; color:#9ca3af; font-size:1rem; }

    @media print {
        .export-actions { display:none !important; }
        body { background:#fff; }
        .cat-section { margin:0 16px 18px; }
        .cards-grid { gap:10px; }
        .barcode-card { border:1px solid #ccc; box-shadow:none; }
    }
</style>
</head>
<body>

<div class="page-header">
    <div>
        <h1>📊 Used Barcodes Report</h1>
        <div class="meta">Generated on <?= date('d F Y, h:i A'); ?> &nbsp;|&nbsp; For designer reference</div>
    </div>
    <div class="brand">
        <strong>Gilaf Foods &amp; Spices</strong><br>
        <span style="font-size:0.75rem;">Barcode Inventory System</span>
    </div>
</div>

<div class="export-actions no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <button class="btn-close-pg" onclick="window.close()">✕ Close</button>
    <span style="font-size:0.82rem;color:#6b7280;">Use browser Print → Save as PDF for best results</span>
</div>

<?php
// Stats
$catCounts = [];
foreach ($rows as $r) {
    $cat = $r['category_name'] ?? 'Uncategorised';
    $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
}
?>
<div class="summary">
    <div class="summary-card">
        <div class="num"><?= count($rows); ?></div>
        <div class="lbl">Total Used</div>
    </div>
    <div class="summary-card">
        <div class="num"><?= count($catCounts); ?></div>
        <div class="lbl">Categories</div>
    </div>
</div>

<?php if (empty($rows)): ?>
<div class="no-data">No Used barcodes found.</div>
<?php else: ?>

<?php
// Group by category
$grouped = [];
foreach ($rows as $r) {
    $cat = $r['category_name'] ?? 'Uncategorised';
    $grouped[$cat][] = $r;
}
$i = 0;
foreach ($grouped as $catName => $items):
?>
<div class="cat-section">
    <div class="cat-title"><?= htmlspecialchars($catName); ?> <span style="font-weight:400;font-size:0.78rem;color:#6b7280;">(<?= count($items); ?> barcodes)</span></div>
    <div class="cards-grid">
        <?php foreach ($items as $item): $i++; ?>
        <div class="barcode-card">
            <svg id="bc<?= $i; ?>"></svg>
            <div class="sku"><?= htmlspecialchars($item['sku_code']); ?></div>
            <div class="prod"><?= htmlspecialchars($item['product_name'] ?? 'N/A'); ?></div>
            <?php if ($item['weight_value'] > 0): ?>
            <span class="wt"><?= $item['weight_value']; ?>g</span>
            <?php endif; ?>
            <div class="num"><?= htmlspecialchars($item['barcode_number']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; endif; ?>

<script>
const barcodes = <?= json_encode(array_values(array_map(fn($r) => $r['barcode_number'], $rows))); ?>;
barcodes.forEach((code, i) => {
    try {
        JsBarcode('#bc' + (i + 1), code, {
            format: 'CODE128', lineColor: '#1a1a1a',
            width: 1.6, height: 50, displayValue: false, margin: 4
        });
    } catch(e) {}
});
</script>
</body>
</html>
<?php
exit;
