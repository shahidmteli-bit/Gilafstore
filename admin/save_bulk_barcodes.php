<?php
ob_start(); // Buffer output

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get Input
$mode = $_POST['mode'] ?? 'pool'; // 'pool' = pool-only, 'sku' = SKU-linked
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$productId  = !empty($_POST['product_id'])  ? (int)$_POST['product_id']  : null;
$weightStr  = $_POST['weight'] ?? '';
$quantity   = (int)($_POST['quantity'] ?? 0);
$skuBase    = trim($_POST['sku_base'] ?? 'POOL');

// Validation
if ($quantity <= 0) {
    echo json_encode(['success' => false, 'error' => 'Quantity must be at least 1']);
    exit;
}

if ($mode === 'sku' && (!$categoryId || !$productId || empty($weightStr))) {
    echo json_encode(['success' => false, 'error' => 'SKU mode requires Category, Product, and Weight']);
    exit;
}

if ($quantity > 999) {
    echo json_encode(['success' => false, 'error' => 'Max quantity per batch is 999']);
    exit;
}

try {
    $db = get_db_connection();
    
    // Auto-create table if not exists (Seamless setup)
    $db->exec("CREATE TABLE IF NOT EXISTS barcode_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NULL DEFAULT NULL,
        product_id INT NULL,
        weight_value DECIMAL(10,2) NULL,
        barcode_number VARCHAR(20) NOT NULL UNIQUE,
        sku_code VARCHAR(50) NOT NULL,
        status ENUM('Unused', 'Used', 'Added to Product Design', 'Planned for Use', 'Archived') DEFAULT 'Unused',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category_id),
        INDEX idx_product (product_id),
        INDEX idx_status (status),
        INDEX idx_barcode (barcode_number),
        INDEX idx_sku (sku_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Allow NULL category_id in existing table
    try { $db->exec("ALTER TABLE barcode_inventory MODIFY COLUMN category_id INT NULL DEFAULT NULL"); } catch(Exception $e) {}

    $generatedCount = 0;
    $barcodes = [];
    $weightValue = $mode === 'sku' ? floatval($weightStr) : null;
    $finalCategory = $mode === 'sku' ? $categoryId : null;
    $finalProduct  = $mode === 'sku' ? $productId  : null;
    $finalSku      = $mode === 'sku' ? $skuBase : 'POOL';

    $stmt = $db->prepare("INSERT INTO barcode_inventory (category_id, product_id, weight_value, barcode_number, sku_code, status) VALUES (?, ?, ?, ?, ?, 'Unused')");

    for ($i = 0; $i < $quantity; $i++) {
        $barcode = generateUniqueEAN13($db);
        try {
            $stmt->execute([$finalCategory, $finalProduct, $weightValue, $barcode, $finalSku]);
            $barcodes[] = $barcode;
            $generatedCount++;
        } catch (PDOException $e) {
            error_log("Barcode collision or error: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true, 
        'count' => $generatedCount, 
        'message' => "Successfully generated $generatedCount barcodes for SKU $skuBase"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Generate a random unique EAN-13 style barcode
 * Starts with '20' (internal use) to avoid conflict with real retail items if possible,
 * or just random. Let's use '890' (India) or similar if requested, but '200' is standard for in-store.
 * Let's generate a full random 12 digit string + check digit.
 */
function generateUniqueEAN13($db) {
    $maxRetries = 10;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        // Generate barcode: 'G' + 12 random digits = 13 chars total (EAN-13 length)
        $randomDigits = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $barcode = 'G' . $randomDigits;
        
        // Check DB for uniqueness
        $stmt = $db->prepare("SELECT COUNT(*) FROM barcode_inventory WHERE barcode_number = ?");
        $stmt->execute([$barcode]);
        if ($stmt->fetchColumn() == 0) {
            return $barcode;
        }
    }
    throw new Exception("Failed to generate unique barcode after $maxRetries attempts");
}

function calculateEAN13CheckDigit($digits) {
    // Digits should be 12 characters
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int)$digits[$i];
        if ($i % 2 === 0) {
            // Even index (0, 2, 4...) -> Odd position (1st, 3rd...) -> Weight 1
            $sum += $digit * 1;
        } else {
            // Odd index (1, 3, 5...) -> Even position (2nd, 4th...) -> Weight 3
            $sum += $digit * 3;
        }
    }
    $nextTen = ceil($sum / 10) * 10;
    return $nextTen - $sum;
}
?>
