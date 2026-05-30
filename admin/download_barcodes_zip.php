<?php
ob_start();
set_time_limit(600);
ini_set('memory_limit', '512M');
/**
 * Download All Barcodes as ZIP — PNG images
 * Generates PNG barcode images using PHP GD library.
 * Each barcode PNG includes: barcode image, barcode number, SKU, product name, category.
 * Organized into folders by category.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db_connection();

// Accept filter parameters
$filterCategory = $_GET['category_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

// Build dynamic query
$where = ["bi.deleted_at IS NULL"];
$params = [];

if ($filterCategory !== '' && $filterCategory != '0') {
    $where[] = "bi.category_id = ?";
    $params[] = $filterCategory;
}
if ($filterStatus !== '') {
    $where[] = "bi.status = ?";
    $params[] = $filterStatus;
}
if ($searchQuery !== '') {
    $where[] = "(bi.sku_code LIKE ? OR bi.barcode_number LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$sql = "SELECT 
        bi.id, bi.barcode_number, bi.sku_code, bi.status,
        bi.weight_value, bi.created_at,
        c.name AS category_name,
        p.name AS product_name
    FROM barcode_inventory bi
    LEFT JOIN categories c ON bi.category_id = c.id
    LEFT JOIN products p ON bi.product_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.name ASC, p.name ASC, bi.sku_code ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    while (ob_get_level()) ob_end_clean();
    echo '<h3>No barcodes found.</h3><a href="barcode_management.php">Go back</a>';
    exit;
}

if (!class_exists('ZipArchive')) {
    while (ob_get_level()) ob_end_clean();
    echo '<h3>PHP ZipArchive extension not enabled.</h3>';
    exit;
}

if (!function_exists('imagecreatetruecolor')) {
    while (ob_get_level()) ob_end_clean();
    echo '<h3>PHP GD extension not enabled. Enable extension=gd in php.ini.</h3>';
    exit;
}

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'barcode_zip_' . uniqid();
@mkdir($tmpDir, 0777, true);

$zipFile = $tmpDir . DIRECTORY_SEPARATOR . 'Barcodes.zip';
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    while (ob_get_level()) ob_end_clean();
    echo '<h3>Could not create ZIP.</h3>';
    exit;
}

// Build Excel HTML content with embedded barcode images
$excel = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
$excel .= '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Barcodes</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
$excel .= '<style>td{mso-number-format:\@;border:1px solid #ddd;padding:6px 10px;font-family:Segoe UI,Arial;font-size:11px;vertical-align:middle;} th{background:#1e3a5f;color:#fff;font-weight:bold;padding:8px 10px;border:1px solid #1e3a5f;font-family:Segoe UI,Arial;font-size:11px;text-align:left;} tr:nth-child(even){background:#f8fafc;} .mono{font-family:Consolas,monospace;} .center{text-align:center;}</style>';
$excel .= '</head><body>';
$excel .= '<table cellspacing="0" cellpadding="0">';
$excel .= '<tr><th>#</th><th>SKU Code</th><th>Product Name</th><th>Category</th><th>Barcode Number</th><th>Barcode Image</th><th>Weight</th><th>Status</th><th>Created Date</th></tr>';

$rowNum = 0;

// Group by category
$grouped = [];
foreach ($rows as $r) {
    $cat = $r['category_name'] ?: 'Pool_Unassigned';
    $grouped[$cat][] = $r;
}

foreach ($grouped as $catName => $items) {
    $safeCat = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $catName);

    foreach ($items as $item) {
        $rowNum++;
        $barcode = $item['barcode_number'];
        $sku = $item['sku_code'];
        $product = $item['product_name'] ?? '';
        $weight = ($item['weight_value'] > 0) ? $item['weight_value'] . 'g' : '';
        $status = $item['status'];
        $created = date('d M Y', strtotime($item['created_at']));

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sku . '_' . $barcode);

        // Generate PNG barcode image with details (for reference)
        $pngData = generateBarcodePNG($barcode, $sku, $product, $catName, $weight, $status, $created);
        $zip->addFromString("{$safeCat}/{$safeName}.png", $pngData);

        // Generate HIGH-RES standalone barcode PNG for CorelDRAW
        $hiResPng = generateHiResBarcodePNG($barcode, $sku);
        $zip->addFromString("_CorelDRAW_Ready/{$safeCat}/{$safeName}_HIRES.png", $hiResPng);

        // Generate small barcode image for Excel embedding
        $smallBarcode = generateSmallBarcodePNG($barcode);
        $b64 = base64_encode($smallBarcode);

        // Status color
        $stColor = '#166534';
        if ($status === 'Used') $stColor = '#1e40af';
        elseif ($status === 'Archived') $stColor = '#6b7280';

        // Excel row
        $excel .= '<tr>';
        $excel .= '<td class="center">' . $rowNum . '</td>';
        $excel .= '<td class="mono" style="font-weight:bold;color:#1e3a5f;">' . htmlspecialchars($sku) . '</td>';
        $excel .= '<td>' . htmlspecialchars($product ?: 'N/A') . '</td>';
        $excel .= '<td>' . htmlspecialchars($catName) . '</td>';
        $excel .= '<td class="mono">' . htmlspecialchars($barcode) . '</td>';
        $excel .= '<td class="center"><img src="data:image/png;base64,' . $b64 . '" width="180" height="50"></td>';
        $excel .= '<td class="center">' . ($weight ?: 'N/A') . '</td>';
        $excel .= '<td style="color:' . $stColor . ';font-weight:bold;">' . htmlspecialchars($status) . '</td>';
        $excel .= '<td>' . $created . '</td>';
        $excel .= '</tr>';
    }
}

$excel .= '</table></body></html>';

$zip->addFromString('Barcode_Report.xls', $excel);

$readme = "BARCODE EXPORT - Gilaf Foods & Spices\r\n";
$readme .= "Generated: " . date('d F Y, h:i A') . "\r\n";
$readme .= "Total: " . count($rows) . " barcodes in " . count($grouped) . " categories\r\n\r\n";
$readme .= "FOLDER STRUCTURE:\r\n";
$readme .= "- [Category]/  = Detail PNG (barcode + product info, for reference)\r\n";
$readme .= "- _CorelDRAW_Ready/[Category]/  = HIGH-RES barcode PNG (1200x600px, clean, for CorelDRAW/design)\r\n";
$readme .= "- Barcode_Report.xls = Excel file with all details + barcode images\r\n\r\n";
$readme .= "CORELDRAW USAGE:\r\n";
$readme .= "- Import the _HIRES.png files from _CorelDRAW_Ready folder\r\n";
$readme .= "- Images are 1200x600px (equivalent to ~300 DPI at 4x2 inches)\r\n";
$readme .= "- Clean white background, black bars, barcode number + SKU below\r\n";
$readme .= "- Transparent-friendly (white bg can be removed in CorelDRAW)\r\n";
$zip->addFromString('_README.txt', $readme);

$zip->close();

// Clear all output buffers
while (ob_get_level()) ob_end_clean();

// Send file
$size = filesize($zipFile);
header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: binary');
header('Content-Disposition: attachment; filename="Barcodes_' . date('Y-m-d') . '.zip"');
header('Content-Length: ' . $size);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipFile);
flush();

@unlink($zipFile);
@rmdir($tmpDir);
exit;


// ===== Generate PNG barcode image with product details =====
function generateBarcodePNG($code, $sku, $product, $category, $weight, $status, $created) {
    // Image dimensions
    $imgWidth = 500;
    $imgHeight = 200;
    $barAreaWidth = 400;
    $barHeight = 80;
    $barWidth = 2; // width of each module

    $img = imagecreatetruecolor($imgWidth, $imgHeight);

    // Colors
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    $darkGray = imagecolorallocate($img, 51, 65, 85);
    $medGray = imagecolorallocate($img, 100, 116, 139);
    $lightGray = imagecolorallocate($img, 226, 232, 240);
    $blue = imagecolorallocate($img, 30, 58, 95);
    $green = imagecolorallocate($img, 22, 101, 52);

    imagefill($img, 0, 0, $white);

    // Draw border
    imagerectangle($img, 0, 0, $imgWidth - 1, $imgHeight - 1, $lightGray);

    // Encode Code128B
    $encoded = encodeCode128B($code);
    $totalBars = count($encoded);
    $totalBarWidth = $totalBars * $barWidth;

    // Center the barcode
    $startX = (int)(($imgWidth - $totalBarWidth) / 2);
    $startY = 15;

    // Draw barcode bars
    $x = $startX;
    foreach ($encoded as $bar) {
        if ($bar) {
            imagefilledrectangle($img, $x, $startY, $x + $barWidth - 1, $startY + $barHeight, $black);
        }
        $x += $barWidth;
    }

    // Text below barcode — barcode number
    $y = $startY + $barHeight + 14;
    $font = 3; // built-in font (medium)
    $fontSmall = 2;

    // Center barcode number text
    $textWidth = imagefontwidth($font) * strlen($code);
    $textX = (int)(($imgWidth - $textWidth) / 2);
    imagestring($img, $font, $textX, $y, $code, $darkGray);

    // Separator line
    $y += 18;
    imageline($img, 20, $y, $imgWidth - 20, $y, $lightGray);
    $y += 6;

    // SKU
    imagestring($img, $font, 20, $y, "SKU: " . $sku, $blue);

    // Status on the right
    $statusText = "Status: " . $status;
    $statusWidth = imagefontwidth($font) * strlen($statusText);
    imagestring($img, $font, $imgWidth - 20 - $statusWidth, $y, $statusText, $status === 'Used' ? $blue : $green);
    $y += 16;

    // Product name
    if ($product) {
        $prodText = "Product: " . $product;
        if (strlen($prodText) > 60) $prodText = substr($prodText, 0, 57) . '...';
        imagestring($img, $fontSmall, 20, $y, $prodText, $darkGray);
        $y += 14;
    }

    // Category + Weight + Created
    $infoLine = "Category: " . $category;
    if ($weight) $infoLine .= "  |  Weight: " . $weight;
    $infoLine .= "  |  " . $created;
    if (strlen($infoLine) > 70) $infoLine = substr($infoLine, 0, 67) . '...';
    imagestring($img, $fontSmall, 20, $y, $infoLine, $medGray);

    // Capture PNG to string
    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    imagedestroy($img);

    return $pngData;
}

// ===== Small barcode PNG for Excel embedding (360x100px) =====
function generateSmallBarcodePNG($code) {
    $imgWidth = 360;
    $imgHeight = 100;
    $barWidth = 2;
    $barHeight = 60;

    $img = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    $gray = imagecolorallocate($img, 80, 80, 80);

    imagefill($img, 0, 0, $white);

    $encoded = encodeCode128B($code);
    $totalBarWidth = count($encoded) * $barWidth;
    $startX = (int)(($imgWidth - $totalBarWidth) / 2);
    $startY = 8;

    $x = $startX;
    foreach ($encoded as $bar) {
        if ($bar) {
            imagefilledrectangle($img, $x, $startY, $x + $barWidth - 1, $startY + $barHeight, $black);
        }
        $x += $barWidth;
    }

    // Barcode number centered below
    $font = 3;
    $textWidth = imagefontwidth($font) * strlen($code);
    $textX = (int)(($imgWidth - $textWidth) / 2);
    imagestring($img, $font, $textX, $startY + $barHeight + 6, $code, $gray);

    ob_start();
    imagepng($img, null, 6);
    $data = ob_get_clean();
    imagedestroy($img);
    return $data;
}

// ===== HIGH-RES barcode PNG for CorelDRAW (1200x600px, ~300 DPI at 4x2 inches) =====
function generateHiResBarcodePNG($code, $sku) {
    $imgWidth = 1200;
    $imgHeight = 600;
    $barWidth = 4; // thick bars for high res
    $barHeight = 380;

    $img = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    $darkGray = imagecolorallocate($img, 40, 40, 40);
    $medGray = imagecolorallocate($img, 100, 100, 100);

    imagefill($img, 0, 0, $white);

    // Encode barcode
    $encoded = encodeCode128B($code);
    $totalBars = count($encoded);
    $totalBarWidth = $totalBars * $barWidth;

    // Center horizontally
    $startX = (int)(($imgWidth - $totalBarWidth) / 2);
    $startY = 40;

    // Draw barcode bars
    $x = $startX;
    foreach ($encoded as $bar) {
        if ($bar) {
            imagefilledrectangle($img, $x, $startY, $x + $barWidth - 1, $startY + $barHeight, $black);
        }
        $x += $barWidth;
    }

    // Barcode number below bars (large, centered)
    $font5 = 5; // largest built-in font
    $font4 = 4;

    // Barcode number
    $y = $startY + $barHeight + 30;
    $numWidth = imagefontwidth($font5) * strlen($code);
    $numX = (int)(($imgWidth - $numWidth) / 2);
    imagestring($img, $font5, $numX, $y, $code, $darkGray);

    // SKU below barcode number
    $y += 30;
    $skuWidth = imagefontwidth($font4) * strlen($sku);
    $skuX = (int)(($imgWidth - $skuWidth) / 2);
    imagestring($img, $font4, $skuX, $y, $sku, $medGray);

    // Capture PNG with maximum quality (no compression)
    ob_start();
    imagepng($img, null, 0); // 0 = no compression, highest quality
    $pngData = ob_get_clean();
    imagedestroy($img);

    return $pngData;
}

function encodeCode128B($data) {
    $patterns = [
        '11011001100','11001101100','11001100110','10010011000','10010001100',
        '10001001100','10011001000','10011000100','10001100100','11001001000',
        '11001000100','11000100100','10110011100','10011011100','10011001110',
        '10111001100','10011101100','10011100110','11001110010','11001011100',
        '11001001110','11011100100','11001110100','11101101110','11101001100',
        '11100101100','11100100110','11101100100','11100110100','11100110010',
        '11011011000','11011000110','11000110110','10100011000','10001011000',
        '10001000110','10110001000','10001101000','10001100010','11010001000',
        '11000101000','11000100010','10110111000','10110001110','10001101110',
        '10111011000','10111000110','10001110110','11101110110','11010001110',
        '11000101110','11011101000','11011100010','11011101110','11101011000',
        '11101000110','11100010110','11101101000','11101100010','11100011010',
        '11101111010','11001000010','11110001010','10100110000','10100001100',
        '10010110000','10010000110','10000101100','10000100110','10110010000',
        '10110000100','10011010000','10011000010','10000110100','10000110010',
        '11000010010','11001010000','11110111010','11000010100','10001111010',
        '10100111100','10010111100','10010011110','10111100100','10011110100',
        '10011110010','11110100100','11110010100','11110010010','11011011110',
        '11011110110','11110110110','10101111000','10100011110','10001011110',
        '10111101000','10111100010','11110101000','11110100010','10111011110',
        '10111101110','11101011110','11110101110','11010000100','11010010000',
        '11010011100','1100011101011'
    ];

    $result = $patterns[104]; // Start Code B
    $checksum = 104;

    for ($i = 0; $i < strlen($data); $i++) {
        $val = ord($data[$i]) - 32;
        if ($val < 0 || $val > 94) $val = 0;
        $result .= $patterns[$val];
        $checksum += $val * ($i + 1);
    }

    $result .= $patterns[$checksum % 103]; // Checksum
    $result .= $patterns[106]; // Stop

    return array_map('intval', str_split($result));
}
