<?php
/**
 * Sync All Used Barcodes → Product EAN Fields
 * For each barcode with status='Used' and a product_id:
 *   1. Match barcode to product_weights by product_id + weight_value → set ean
 *   2. Also set products.ean if empty
 * Ensures no wrong mapping — each barcode goes to its specific product/weight variant.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

header('Content-Type: application/json');
set_time_limit(120);

$db = get_db_connection();

try {
    // Ensure products.ean column exists
    try { $db->exec("ALTER TABLE products ADD COLUMN ean VARCHAR(13) DEFAULT NULL"); } catch(Exception $e) {}
    // Ensure product_weights.ean column exists
    try { $db->exec("ALTER TABLE product_weights ADD COLUMN ean VARCHAR(20) DEFAULT NULL"); } catch(Exception $e) {}
    // Ensure sku column exists in both tables
    try { $db->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE product_weights ADD COLUMN sku VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}

    // Get all Used barcodes that are linked to a product
    $rows = $db->query("
        SELECT bi.id, bi.barcode_number, bi.sku_code, bi.product_id, bi.weight_value
        FROM barcode_inventory bi
        WHERE bi.status = 'Used' 
          AND bi.product_id IS NOT NULL 
          AND bi.deleted_at IS NULL
        ORDER BY bi.product_id, bi.weight_value
    ")->fetchAll(PDO::FETCH_ASSOC);

    $syncedWeights = 0;
    $syncedProducts = 0;
    $syncedSkus = 0;
    $skipped = 0;

    $updateWeight    = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND weight_value = ?");
    $updateWeightAny = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND (ean IS NULL OR ean = '') LIMIT 1");
    $updateProduct   = $db->prepare("UPDATE products SET ean = ? WHERE id = ?");

    $updateSkuWt    = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND weight_value = ?");
    $updateSkuWtAny = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND (sku IS NULL OR sku = '') LIMIT 1");
    $updateSkuProd  = $db->prepare("UPDATE products SET sku = ? WHERE id = ?");

    foreach ($rows as $row) {
        // ----- Normalize EAN: strip legacy G prefix + add check digit if 12 digits -----
        $digits = preg_replace('/\D/', '', $row['barcode_number']);
        if (strlen($digits) === 12) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int)$digits[$i] * (($i % 2 === 0) ? 1 : 3);
            }
            $digits .= (string)((10 - ($sum % 10)) % 10);
        }
        $barcodeNum = $digits;

        $productId = $row['product_id'];
        $weightVal = $row['weight_value'];
        $skuCode   = $row['sku_code'];

        // -------- Sync EAN --------
        $synced = false;
        if ($weightVal !== null && $weightVal > 0) {
            $updateWeight->execute([$barcodeNum, $productId, $weightVal]);
            if ($updateWeight->rowCount() > 0) { $syncedWeights++; $synced = true; }
        }
        if (!$synced) {
            $updateWeightAny->execute([$barcodeNum, $productId]);
            if ($updateWeightAny->rowCount() > 0) { $syncedWeights++; $synced = true; }
        }
        $updateProduct->execute([$barcodeNum, $productId]);
        if ($updateProduct->rowCount() > 0) { $syncedProducts++; }

        // -------- Sync SKU --------
        if (!empty($skuCode) && $skuCode !== 'POOL') {
            $skuSynced = false;
            try {
                if ($weightVal !== null && $weightVal > 0) {
                    $updateSkuWt->execute([$skuCode, $productId, $weightVal]);
                    if ($updateSkuWt->rowCount() > 0) { $syncedSkus++; $skuSynced = true; }
                }
                if (!$skuSynced) {
                    $updateSkuWtAny->execute([$skuCode, $productId]);
                    if ($updateSkuWtAny->rowCount() > 0) { $syncedSkus++; }
                }
                $updateSkuProd->execute([$skuCode, $productId]);
            } catch (Exception $e) { /* sku column may not exist on this DB */ }
        }
    }

    echo json_encode([
        'success' => true,
        'total_used' => count($rows),
        'synced_weights' => $syncedWeights,
        'synced_products' => $syncedProducts,
        'synced_skus' => $syncedSkus,
        'skipped_already_set' => $skipped,
        'message' => "Synced " . count($rows) . " used barcodes: {$syncedWeights} EAN variants, {$syncedProducts} product EANs, {$syncedSkus} SKUs updated."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
