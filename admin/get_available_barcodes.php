<?php
/**
 * API: Get available (unassigned) barcodes for a product weight
 * 
 * Parameters:
 *   category_id - Category to fetch barcodes from
 *   product_id  - Current product ID (to check if barcode already assigned)
 *   weight_id   - Current weight ID (to check if barcode already assigned)
 * 
 * Returns JSON:
 *   assigned_barcode - The barcode already assigned to this weight (if any)
 *   available_barcodes - Array of unassigned barcodes from the category
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

header('Content-Type: application/json');

$categoryId = (int)($_GET['category_id'] ?? 0);
$productId  = (int)($_GET['product_id'] ?? 0);
$weightId   = (int)($_GET['weight_id'] ?? 0);

try {
    $db = get_db_connection();
    
    $result = [
        'success' => true,
        'assigned_barcode' => null,
        'available_barcodes' => []
    ];
    
    // 1. Check if this product weight already has an EAN assigned
    if ($weightId && $productId) {
        $stmt = $db->prepare("SELECT ean FROM product_weights WHERE id = ? AND product_id = ?");
        $stmt->execute([$weightId, $productId]);
        $currentEan = $stmt->fetchColumn();
        
        if (!empty($currentEan)) {
            // Check if this EAN exists in barcode_inventory
            $bStmt = $db->prepare("SELECT id, barcode_number, sku_code, status FROM barcode_inventory WHERE barcode_number = ? AND deleted_at IS NULL LIMIT 1");
            $bStmt->execute([$currentEan]);
            $barcodeRow = $bStmt->fetch(PDO::FETCH_ASSOC);
            
            $result['assigned_barcode'] = [
                'barcode_number' => $currentEan,
                'sku_code' => $barcodeRow['sku_code'] ?? '',
                'status' => $barcodeRow['status'] ?? 'Used',
                'from_inventory' => !empty($barcodeRow)
            ];
        }
    }
    
    // 2. Fetch unassigned barcodes from the same category (status = 'Unused')
    if ($categoryId > 0) {
        $stmt = $db->prepare("
            SELECT bi.id, bi.barcode_number, bi.sku_code 
            FROM barcode_inventory bi 
            WHERE bi.category_id = ? 
              AND bi.status = 'Unused' 
              AND bi.product_id IS NULL 
              AND bi.deleted_at IS NULL
            ORDER BY bi.barcode_number ASC
            LIMIT 200
        ");
        $stmt->execute([$categoryId]);
        $result['available_barcodes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Also include pool barcodes (no category) as fallback
    $poolStmt = $db->prepare("
        SELECT bi.id, bi.barcode_number, bi.sku_code 
        FROM barcode_inventory bi 
        WHERE bi.category_id IS NULL 
          AND bi.status = 'Unused' 
          AND bi.product_id IS NULL 
          AND bi.deleted_at IS NULL
        ORDER BY bi.barcode_number ASC
        LIMIT 50
    ");
    $poolStmt->execute();
    $result['pool_barcodes'] = $poolStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
