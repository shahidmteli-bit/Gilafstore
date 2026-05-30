<?php
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_admin();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    $db = get_db_connection();

    // Ensure category_id allows NULL
    $db->exec("ALTER TABLE barcode_inventory MODIFY COLUMN category_id INT NULL DEFAULT NULL");

    if ($action === 'generate_to_pool') {
        $qty = min((int)($_POST['quantity'] ?? 10), 999);
        if ($qty < 1) { echo json_encode(['success' => false, 'error' => 'Quantity must be at least 1']); exit; }

        $generated = 0;
        $stmt = $db->prepare("INSERT INTO barcode_inventory (category_id, product_id, weight_value, barcode_number, sku_code, status) VALUES (NULL, NULL, NULL, ?, 'POOL', 'Unused')");

        for ($i = 0; $i < $qty; $i++) {
            $barcode = generatePoolBarcode($db);
            $stmt->execute([$barcode]);
            $generated++;
        }

        $poolCount = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused'")->fetchColumn();
        echo json_encode(['success' => true, 'generated' => $generated, 'pool_total' => $poolCount,
            'message' => "$generated barcodes added to pool. Pool total: $poolCount"]);

    } elseif ($action === 'assign_to_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        if (!$categoryId || $qty < 1) { echo json_encode(['success' => false, 'error' => 'Invalid category or quantity']); exit; }

        // Check pool has enough
        $poolCount = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused'")->fetchColumn();
        if ($poolCount < $qty) { echo json_encode(['success' => false, 'error' => "Not enough barcodes in pool. Pool has $poolCount, requested $qty"]); exit; }

        // Get N unassigned barcodes (LIMIT must be embedded, not bound, in MariaDB)
        $stmt = $db->query("SELECT id FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused' LIMIT " . (int)$qty);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $update = $db->prepare("UPDATE barcode_inventory SET category_id = ? WHERE id = ?");
        foreach ($ids as $id) {
            $update->execute([$categoryId, $id]);
        }

        $catName = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $catName->execute([$categoryId]);
        $name = $catName->fetchColumn();
        $remaining = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused'")->fetchColumn();
        echo json_encode(['success' => true, 'assigned' => count($ids), 'pool_remaining' => $remaining,
            'message' => count($ids) . " barcodes assigned to '$name'. Pool remaining: $remaining"]);

    } elseif ($action === 'auto_distribute') {
        $perCategory = (int)($_POST['per_category'] ?? 25);
        if ($perCategory < 1) { echo json_encode(['success' => false, 'error' => 'Invalid per_category value']); exit; }

        $categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($categories)) { echo json_encode(['success' => false, 'error' => 'No categories found']); exit; }

        $needed = count($categories) * $perCategory;
        $poolCount = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused'")->fetchColumn();
        if ($poolCount < $needed) { echo json_encode(['success' => false, 'error' => "Pool has $poolCount barcodes, need $needed for distribution. Generate more first."]); exit; }

        $results = [];
        $update = $db->prepare("UPDATE barcode_inventory SET category_id = ? WHERE id = ?");
        foreach ($categories as $cat) {
            $perInt = (int)$perCategory;
            $stmt = $db->query("SELECT id FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused' LIMIT $perInt");
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) { $update->execute([$cat['id'], $id]); }
            $results[$cat['name']] = count($ids);
        }

        $remaining = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused'")->fetchColumn();
        echo json_encode(['success' => true, 'distributed' => $results, 'pool_remaining' => $remaining,
            'message' => "Distributed $perCategory barcodes to each of " . count($categories) . " categories."]);

    } elseif ($action === 'transfer') {
        $fromCat = $_POST['from_category'] ?? '';
        $toCat = (int)($_POST['to_category'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);

        if ($qty < 1 || !$toCat) { echo json_encode(['success' => false, 'error' => 'Invalid transfer parameters']); exit; }

        $qtyInt = (int)$qty;
        if ($fromCat === 'pool') {
            $stmt = $db->query("SELECT id FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused' LIMIT $qtyInt");
        } else {
            $fromCatId = (int)$fromCat;
            if (!$fromCatId) { echo json_encode(['success' => false, 'error' => 'Invalid source category']); exit; }
            $stmt = $db->prepare("SELECT id FROM barcode_inventory WHERE category_id = ? AND status = 'Unused' LIMIT $qtyInt");
            $stmt->execute([$fromCatId]);
        }
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) { echo json_encode(['success' => false, 'error' => 'No available barcodes to transfer from source']); exit; }

        $update = $db->prepare("UPDATE barcode_inventory SET category_id = ? WHERE id = ?");
        foreach ($ids as $id) { $update->execute([$toCat, $id]); }

        $toName = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $toName->execute([$toCat]);
        $toNameStr = $toName->fetchColumn();
        echo json_encode(['success' => true, 'transferred' => count($ids),
            'message' => count($ids) . " barcodes transferred to '$toNameStr'"]);

    } elseif ($action === 'return_to_pool') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        if (!$categoryId || $qty < 1) { echo json_encode(['success' => false, 'error' => 'Invalid parameters']); exit; }

        $qtyInt = (int)$qty;
        $stmt = $db->prepare("SELECT id FROM barcode_inventory WHERE category_id = ? AND status = 'Unused' LIMIT $qtyInt");
        $stmt->execute([$categoryId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $update = $db->prepare("UPDATE barcode_inventory SET category_id = NULL WHERE id = ?");
        foreach ($ids as $id) { $update->execute([$id]); }

        echo json_encode(['success' => true, 'returned' => count($ids),
            'message' => count($ids) . " barcodes returned to pool"]);

    } elseif ($action === 'assign_ean') {
        // ═══ NEW: Save official GS1 EAN/GTIN barcode with SKU ═══
        $ean       = trim($_POST['ean'] ?? '');
        $sku       = trim($_POST['sku'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $productId  = (int)($_POST['product_id'] ?? 0);
        $weightCode = trim($_POST['weight_code'] ?? '');

        // Validate EAN
        if (!$ean || !preg_match('/^\d{8,13}$/', $ean)) {
            echo json_encode(['success' => false, 'error' => 'Invalid EAN/GTIN. Must be 8-13 digits.']);
            exit;
        }
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Please select a product.']);
            exit;
        }
        if (!$sku || $sku === 'POOL') {
            echo json_encode(['success' => false, 'error' => 'SKU could not be generated. Please select category, product and weight.']);
            exit;
        }

        // Check for duplicate EAN in inventory
        $dupCheck = $db->prepare("SELECT id FROM barcode_inventory WHERE barcode_number = ? AND deleted_at IS NULL");
        $dupCheck->execute([$ean]);
        if ($dupCheck->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'This EAN/GTIN (' . $ean . ') already exists in the barcode inventory.']);
            exit;
        }

        // Parse numeric weight from weight code (e.g. "250G" → 250)
        $weightVal = null;
        if ($weightCode) {
            preg_match('/^([\d.]+)/', $weightCode, $m);
            $weightVal = isset($m[1]) ? floatval($m[1]) : null;
        }

        // Insert into barcode_inventory with real SKU and EAN
        $stmt = $db->prepare("INSERT INTO barcode_inventory (category_id, product_id, weight_value, barcode_number, sku_code, status) VALUES (?, ?, ?, ?, ?, 'Used')");
        $stmt->execute([
            $categoryId ?: null,
            $productId,
            $weightVal,
            $ean,
            $sku
        ]);

        // Sync EAN to product_weights and products tables
        if ($weightVal !== null) {
            $syncWeight = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND weight_value = ? AND (ean IS NULL OR ean = '')");
            $syncWeight->execute([$ean, $productId, $weightVal]);
            if ($syncWeight->rowCount() === 0) {
                $syncAny = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND (ean IS NULL OR ean = '') LIMIT 1");
                $syncAny->execute([$ean, $productId]);
            }
        } else {
            $syncAny = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND (ean IS NULL OR ean = '') LIMIT 1");
            $syncAny->execute([$ean, $productId]);
        }

        // Also update products.ean if empty
        try {
            $syncProduct = $db->prepare("UPDATE products SET ean = ? WHERE id = ? AND (ean IS NULL OR ean = '')");
            $syncProduct->execute([$ean, $productId]);
        } catch (Exception $e) { /* ean column may not exist */ }

        // Get product name for confirmation
        $pName = $db->prepare("SELECT name FROM products WHERE id = ?");
        $pName->execute([$productId]);
        $productName = $pName->fetchColumn() ?: 'Unknown';

        echo json_encode([
            'success' => true,
            'message' => "EAN {$ean} saved with SKU {$sku} and assigned to \"{$productName}\".",
            'ean' => $ean,
            'sku' => $sku,
            'product' => $productName
        ]);

    } elseif ($action === 'assign_product') {
        $barcodeId  = (int)($_POST['barcode_id']  ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $productId  = (int)($_POST['product_id']  ?? 0);
        $weightStr  = trim($_POST['weight']    ?? '');
        $skuBase    = trim($_POST['sku_base']  ?? '');

        if (!$barcodeId) { echo json_encode(['success' => false, 'error' => 'Invalid barcode ID']); exit; }

        $weightVal = $weightStr !== '' ? floatval($weightStr) : null;

        $stmt = $db->prepare("UPDATE barcode_inventory SET category_id = ?, product_id = ?, weight_value = ?, sku_code = ?, status = 'Used' WHERE id = ?");
        $stmt->execute([
            $categoryId ?: null,
            $productId  ?: null,
            $weightVal,
            $skuBase ?: 'POOL',
            $barcodeId
        ]);

        // Sync barcode_number + sku_code → product EAN/SKU fields
        if ($productId) {
            $barcodeRow = $db->prepare("SELECT barcode_number, sku_code FROM barcode_inventory WHERE id = ?");
            $barcodeRow->execute([$barcodeId]);
            $brData = $barcodeRow->fetch(PDO::FETCH_ASSOC);
            $barcodeNumber = $brData['barcode_number'] ?? null;
            $skuCode = $brData['sku_code'] ?? null;

            if ($barcodeNumber) {
                // Strip legacy G prefix and normalize to 13 digits with check digit
                $digits = preg_replace('/\D/', '', $barcodeNumber);
                if (strlen($digits) === 12) {
                    $sum = 0;
                    for ($i = 0; $i < 12; $i++) {
                        $sum += (int)$digits[$i] * (($i % 2 === 0) ? 1 : 3);
                    }
                    $digits .= (string)((10 - ($sum % 10)) % 10);
                }
                $barcodeNumber = $digits;

                // -------- Sync EAN --------
                if ($weightVal !== null) {
                    $syncWeight = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND weight_value = ? AND (ean IS NULL OR ean = '')");
                    $syncWeight->execute([$barcodeNumber, $productId, $weightVal]);
                    if ($syncWeight->rowCount() === 0) {
                        $syncAny = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND (ean IS NULL OR ean = '') LIMIT 1");
                        $syncAny->execute([$barcodeNumber, $productId]);
                    }
                } else {
                    $syncAny = $db->prepare("UPDATE product_weights SET ean = ? WHERE product_id = ? AND (ean IS NULL OR ean = '') LIMIT 1");
                    $syncAny->execute([$barcodeNumber, $productId]);
                }
                try {
                    $syncProduct = $db->prepare("UPDATE products SET ean = ? WHERE id = ? AND (ean IS NULL OR ean = '')");
                    $syncProduct->execute([$barcodeNumber, $productId]);
                } catch (Exception $e) { /* ean column may not exist */ }

                // -------- Sync SKU (column-safe) --------
                if (!empty($skuCode) && $skuCode !== 'POOL') {
                    try {
                        if ($weightVal !== null) {
                            $syncSkuWt = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND weight_value = ?");
                            $syncSkuWt->execute([$skuCode, $productId, $weightVal]);
                            if ($syncSkuWt->rowCount() === 0) {
                                $syncSkuAny = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND (sku IS NULL OR sku = '') LIMIT 1");
                                $syncSkuAny->execute([$skuCode, $productId]);
                            }
                        } else {
                            $syncSkuAny = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND (sku IS NULL OR sku = '') LIMIT 1");
                            $syncSkuAny->execute([$skuCode, $productId]);
                        }
                    } catch (Exception $e) { /* sku column may not exist yet — run migrate_ean_sku.php */ }

                    try {
                        $syncSkuProd = $db->prepare("UPDATE products SET sku = ? WHERE id = ? AND (sku IS NULL OR sku = '')");
                        $syncSkuProd->execute([$skuCode, $productId]);
                    } catch (Exception $e) { /* sku column may not exist */ }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Barcode assigned and synced to product successfully']);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generatePoolBarcode($db) {
    for ($i = 0; $i < 10; $i++) {
        $digits = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $barcode = 'G' . $digits;
        $stmt = $db->prepare("SELECT COUNT(*) FROM barcode_inventory WHERE barcode_number = ?");
        $stmt->execute([$barcode]);
        if ($stmt->fetchColumn() == 0) return $barcode;
    }
    throw new Exception("Failed to generate unique barcode");
}
