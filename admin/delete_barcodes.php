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

    // Ensure deleted_at column exists
    try { $db->exec("ALTER TABLE barcode_inventory ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE barcode_inventory ADD COLUMN deleted_by VARCHAR(100) NULL DEFAULT NULL"); } catch(Exception $e) {}

    $now = date('Y-m-d H:i:s');
    $adminUser = $_SESSION['admin']['username'] ?? 'admin';

    if ($action === 'delete_all') {
        // Clear EAN/SKU from products/product_weights for all used barcodes BEFORE soft-deleting
        $usedBarcodes = $db->query("SELECT barcode_number, sku_code, product_id FROM barcode_inventory WHERE deleted_at IS NULL AND product_id IS NOT NULL AND status = 'Used'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($usedBarcodes as $ub) {
            $bn  = $ub['barcode_number'];
            $sku = $ub['sku_code'];
            $pid = $ub['product_id'];
            // Pure numeric version (strip G prefix) + 13-digit normalized version
            $bnClean = (str_starts_with($bn, 'G')) ? substr($bn, 1) : $bn;
            $bn13 = $bnClean;
            if (strlen($bn13) === 12) {
                $sum = 0;
                for ($i = 0; $i < 12; $i++) { $sum += (int)$bn13[$i] * (($i % 2 === 0) ? 1 : 3); }
                $bn13 .= (string)((10 - ($sum % 10)) % 10);
            }
            // Clear EAN from product_weights — match all 3 variants (G-prefixed, 12-digit, 13-digit)
            $db->prepare("UPDATE product_weights SET ean = NULL WHERE product_id = ? AND (ean = ? OR ean = ? OR ean = ?)")->execute([$pid, $bn, $bnClean, $bn13]);
            try { $db->prepare("UPDATE products SET ean = NULL WHERE id = ? AND (ean = ? OR ean = ? OR ean = ?)")->execute([$pid, $bn, $bnClean, $bn13]); } catch(Exception $e) {}
            // Clear SKU (column-safe) — only touches sku column
            try { $db->prepare("UPDATE product_weights SET sku = NULL WHERE product_id = ? AND sku = ?")->execute([$pid, $sku]); } catch(Exception $e) {}
            try { $db->prepare("UPDATE products SET sku = NULL WHERE id = ? AND sku = ?")->execute([$pid, $sku]); } catch(Exception $e) {}
        }
        // Soft delete all active barcodes
        $db->exec("UPDATE barcode_inventory SET deleted_at = '$now', deleted_by = '$adminUser' WHERE deleted_at IS NULL");
        echo json_encode(['success' => true, 'message' => 'All barcodes moved to Recycle Bin. EAN & SKU cleared from products.']);

    } elseif ($action === 'delete_selected') {
        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) { echo json_encode(['success' => false, 'error' => 'No barcodes selected.']); exit; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Clear EAN/SKU from products for selected barcodes BEFORE soft-deleting
        $selStmt = $db->prepare("SELECT barcode_number, sku_code, product_id FROM barcode_inventory WHERE id IN ($placeholders) AND product_id IS NOT NULL AND deleted_at IS NULL");
        $selStmt->execute(array_map('intval', $ids));
        foreach ($selStmt->fetchAll(PDO::FETCH_ASSOC) as $ub) {
            $bn  = $ub['barcode_number'];
            $sku = $ub['sku_code'];
            $pid = $ub['product_id'];
            $bnClean = (str_starts_with($bn, 'G')) ? substr($bn, 1) : $bn;
            $bn13 = $bnClean;
            if (strlen($bn13) === 12) {
                $sum = 0;
                for ($i = 0; $i < 12; $i++) { $sum += (int)$bn13[$i] * (($i % 2 === 0) ? 1 : 3); }
                $bn13 .= (string)((10 - ($sum % 10)) % 10);
            }
            $db->prepare("UPDATE product_weights SET ean = NULL WHERE product_id = ? AND (ean = ? OR ean = ? OR ean = ?)")->execute([$pid, $bn, $bnClean, $bn13]);
            try { $db->prepare("UPDATE products SET ean = NULL WHERE id = ? AND (ean = ? OR ean = ? OR ean = ?)")->execute([$pid, $bn, $bnClean, $bn13]); } catch(Exception $e) {}
            try { $db->prepare("UPDATE product_weights SET sku = NULL WHERE product_id = ? AND sku = ?")->execute([$pid, $sku]); } catch(Exception $e) {}
            try { $db->prepare("UPDATE products SET sku = NULL WHERE id = ? AND sku = ?")->execute([$pid, $sku]); } catch(Exception $e) {}
        }
        $params = array_merge([$now, $adminUser], array_map('intval', $ids));
        $stmt = $db->prepare("UPDATE barcode_inventory SET deleted_at = ?, deleted_by = ? WHERE id IN ($placeholders) AND deleted_at IS NULL");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => $stmt->rowCount() . ' barcode(s) moved to Recycle Bin. EAN & SKU cleared from products.']);

    } elseif ($action === 'delete_one') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid barcode ID.']); exit; }
        // Clear EAN/SKU from product tables BEFORE soft-deleting (only touches ean & sku columns, nothing else)
        $bRow = $db->prepare("SELECT barcode_number, sku_code, product_id FROM barcode_inventory WHERE id = ? AND deleted_at IS NULL");
        $bRow->execute([$id]);
        $bData = $bRow->fetch(PDO::FETCH_ASSOC);
        if ($bData && $bData['product_id']) {
            $bn  = $bData['barcode_number'];
            $sku = $bData['sku_code'];
            $pid = $bData['product_id'];
            $bnClean = (str_starts_with($bn, 'G')) ? substr($bn, 1) : $bn;
            $bn13 = $bnClean;
            if (strlen($bn13) === 12) {
                $sum = 0;
                for ($i = 0; $i < 12; $i++) { $sum += (int)$bn13[$i] * (($i % 2 === 0) ? 1 : 3); }
                $bn13 .= (string)((10 - ($sum % 10)) % 10);
            }
            // Clear EAN from product_weights — match all 3 variants (G-prefixed, 12-digit, 13-digit)
            $db->prepare("UPDATE product_weights SET ean = NULL WHERE product_id = ? AND (ean = ? OR ean = ? OR ean = ?)")->execute([$pid, $bn, $bnClean, $bn13]);
            try { $db->prepare("UPDATE products SET ean = NULL WHERE id = ? AND (ean = ? OR ean = ? OR ean = ?)")->execute([$pid, $bn, $bnClean, $bn13]); } catch(Exception $e) {}
            // Clear SKU (column-safe) — only touches sku column
            try { $db->prepare("UPDATE product_weights SET sku = NULL WHERE product_id = ? AND sku = ?")->execute([$pid, $sku]); } catch(Exception $e) {}
            try { $db->prepare("UPDATE products SET sku = NULL WHERE id = ? AND sku = ?")->execute([$pid, $sku]); } catch(Exception $e) {}
        }
        $stmt = $db->prepare("UPDATE barcode_inventory SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$now, $adminUser, $id]);
        echo json_encode(['success' => true, 'message' => 'Barcode moved to Recycle Bin. EAN & SKU cleared from product.']);

    } elseif ($action === 'restore_one') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid barcode ID.']); exit; }
        $stmt = $db->prepare("UPDATE barcode_inventory SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Barcode restored successfully.']);

    } elseif ($action === 'restore_all') {
        $db->exec("UPDATE barcode_inventory SET deleted_at = NULL, deleted_by = NULL WHERE deleted_at IS NOT NULL");
        echo json_encode(['success' => true, 'message' => 'All barcodes restored from Recycle Bin.']);

    } elseif ($action === 'purge_one') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid barcode ID.']); exit; }
        $stmt = $db->prepare("DELETE FROM barcode_inventory WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Barcode permanently deleted.']);

    } elseif ($action === 'purge_all') {
        $db->exec("DELETE FROM barcode_inventory WHERE deleted_at IS NOT NULL");
        echo json_encode(['success' => true, 'message' => 'Recycle Bin emptied permanently.']);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
