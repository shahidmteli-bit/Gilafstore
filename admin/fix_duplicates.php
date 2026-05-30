<?php
/**
 * Fix Duplicate Barcodes and SKUs
 * - Duplicate barcodes: regenerate new unique barcode numbers for duplicates (keeps the oldest, fixes newer ones)
 * - Duplicate SKUs: append a numeric suffix (-2, -3, etc.) to duplicate SKUs (keeps the oldest unchanged)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

header('Content-Type: application/json');

$db = get_db_connection();
$fixedBarcodes = 0;
$fixedSkus = 0;

try {
    $db->beginTransaction();

    // === FIX DUPLICATE BARCODES ===
    // Find barcode numbers that appear more than once
    $dupBarcodes = $db->query("
        SELECT barcode_number, GROUP_CONCAT(id ORDER BY created_at ASC) as ids
        FROM barcode_inventory 
        WHERE deleted_at IS NULL 
        GROUP BY barcode_number 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updateBarcode = $db->prepare("UPDATE barcode_inventory SET barcode_number = ? WHERE id = ?");

    foreach ($dupBarcodes as $dup) {
        $ids = explode(',', $dup['ids']);
        // Keep the first (oldest) one, regenerate for the rest
        array_shift($ids); // remove oldest
        foreach ($ids as $id) {
            $newBarcode = generateUniqueBarcode($db);
            $updateBarcode->execute([$newBarcode, $id]);
            $fixedBarcodes++;
        }
    }

    // === FIX DUPLICATE SKUs ===
    // Find SKU codes that appear more than once (excluding POOL which is intentionally shared)
    $dupSkus = $db->query("
        SELECT sku_code, GROUP_CONCAT(id ORDER BY created_at ASC) as ids, COUNT(*) as cnt
        FROM barcode_inventory 
        WHERE deleted_at IS NULL AND sku_code != 'POOL'
        GROUP BY sku_code 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updateSku = $db->prepare("UPDATE barcode_inventory SET sku_code = ? WHERE id = ?");

    foreach ($dupSkus as $dup) {
        $ids = explode(',', $dup['ids']);
        $baseSku = $dup['sku_code'];
        // Keep the first (oldest) one unchanged, suffix the rest
        array_shift($ids); // remove oldest
        $suffix = 2;
        foreach ($ids as $id) {
            $newSku = $baseSku . '-' . $suffix;
            // Make sure the new SKU doesn't already exist
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM barcode_inventory WHERE sku_code = ? AND deleted_at IS NULL");
            $checkStmt->execute([$newSku]);
            while ($checkStmt->fetchColumn() > 0) {
                $suffix++;
                $newSku = $baseSku . '-' . $suffix;
                $checkStmt->execute([$newSku]);
            }
            $updateSku->execute([$newSku, $id]);
            $fixedSkus++;
            $suffix++;
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'fixed_barcodes' => $fixedBarcodes,
        'fixed_skus' => $fixedSkus,
        'message' => "Fixed {$fixedBarcodes} duplicate barcode(s) and {$fixedSkus} duplicate SKU(s)."
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateUniqueBarcode($db) {
    $maxRetries = 20;
    for ($i = 0; $i < $maxRetries; $i++) {
        $randomDigits = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $barcode = 'G' . $randomDigits;
        $stmt = $db->prepare("SELECT COUNT(*) FROM barcode_inventory WHERE barcode_number = ?");
        $stmt->execute([$barcode]);
        if ($stmt->fetchColumn() == 0) {
            return $barcode;
        }
    }
    throw new Exception("Failed to generate unique barcode after $maxRetries attempts");
}
