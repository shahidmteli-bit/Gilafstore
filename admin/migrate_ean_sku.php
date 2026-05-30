<?php
/**
 * One-time migration: Clean legacy EAN data + Add SKU column to product_weights
 *
 * What this script does:
 * 1. Adds `sku` column to product_weights if missing
 * 2. Strips legacy 'G' prefix from products.ean and product_weights.ean
 * 3. Normalizes 12-digit EANs to 13 digits (calculates check digit)
 * 4. Syncs SKU from barcode_inventory → product_weights based on (product_id, weight_value)
 *
 * SAFETY: Only touches `ean` and `sku` columns. NEVER modifies images, gallery, or other product data.
 *
 * Run once: http://localhost/Gilaf%20Ecommerce%20website/admin/migrate_ean_sku.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db_connection();
$results = [];

// EAN-13 check digit calculator
function ean13_calc_check(string $twelve): string {
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$twelve[$i] * (($i % 2 === 0) ? 1 : 3);
    }
    return (string)((10 - ($sum % 10)) % 10);
}

function normalize_ean(?string $raw): ?string {
    if ($raw === null || $raw === '') return null;
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') return null;
    if (strlen($digits) === 12) {
        $digits .= ean13_calc_check($digits);
    }
    return $digits;
}

// =========================================================================
// STEP 1: Add `sku` column to product_weights if missing
// =========================================================================
try {
    $check = $db->query("SHOW COLUMNS FROM product_weights LIKE 'sku'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE product_weights ADD COLUMN sku VARCHAR(50) NULL DEFAULT NULL AFTER ean");
        $results[] = "✅ Added `sku` column to product_weights table";
    } else {
        $results[] = "ℹ️ `sku` column already exists in product_weights";
    }
} catch (Exception $e) {
    $results[] = "❌ Error adding sku column: " . $e->getMessage();
}

// Also add sku to products table if missing (for default SKU at product level)
try {
    $check = $db->query("SHOW COLUMNS FROM products LIKE 'sku'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) NULL DEFAULT NULL");
        $results[] = "✅ Added `sku` column to products table";
    } else {
        $results[] = "ℹ️ `sku` column already exists in products";
    }
} catch (Exception $e) {
    $results[] = "❌ Error adding sku column to products: " . $e->getMessage();
}

// =========================================================================
// STEP 2: Clean legacy G-prefix and normalize EANs in product_weights.ean
// =========================================================================
try {
    $rows = $db->query("SELECT id, ean FROM product_weights WHERE ean IS NOT NULL AND ean != ''")->fetchAll(PDO::FETCH_ASSOC);
    $cleaned = 0;
    foreach ($rows as $row) {
        $original = $row['ean'];
        $normalized = normalize_ean($original);
        if ($normalized !== null && $normalized !== $original) {
            $upd = $db->prepare("UPDATE product_weights SET ean = ? WHERE id = ?");
            $upd->execute([$normalized, $row['id']]);
            $cleaned++;
        }
    }
    $results[] = "✅ Normalized $cleaned EAN values in product_weights (stripped G prefix, added check digits)";
} catch (Exception $e) {
    $results[] = "❌ Error cleaning product_weights.ean: " . $e->getMessage();
}

// =========================================================================
// STEP 3: Clean legacy G-prefix and normalize EANs in products.ean
// =========================================================================
try {
    $check = $db->query("SHOW COLUMNS FROM products LIKE 'ean'");
    if ($check->rowCount() > 0) {
        $rows = $db->query("SELECT id, ean FROM products WHERE ean IS NOT NULL AND ean != ''")->fetchAll(PDO::FETCH_ASSOC);
        $cleaned = 0;
        foreach ($rows as $row) {
            $original = $row['ean'];
            $normalized = normalize_ean($original);
            if ($normalized !== null && $normalized !== $original) {
                $upd = $db->prepare("UPDATE products SET ean = ? WHERE id = ?");
                $upd->execute([$normalized, $row['id']]);
                $cleaned++;
            }
        }
        $results[] = "✅ Normalized $cleaned EAN values in products (stripped G prefix, added check digits)";
    } else {
        $results[] = "ℹ️ products table has no `ean` column — skipped";
    }
} catch (Exception $e) {
    $results[] = "❌ Error cleaning products.ean: " . $e->getMessage();
}

// =========================================================================
// STEP 4: Sync SKU from barcode_inventory → product_weights
// =========================================================================
// Only sync where the barcode is "Used" and tied to a product
try {
    $rows = $db->query("
        SELECT product_id, weight_value, sku_code, barcode_number
        FROM barcode_inventory
        WHERE deleted_at IS NULL
          AND product_id IS NOT NULL
          AND status = 'Used'
          AND sku_code IS NOT NULL
          AND sku_code != ''
          AND sku_code != 'POOL'
    ")->fetchAll(PDO::FETCH_ASSOC);

    $syncedSku = 0;
    foreach ($rows as $row) {
        $pid = $row['product_id'];
        $wv = $row['weight_value'];
        $sku = $row['sku_code'];

        if ($wv !== null) {
            // Match by product_id + weight_value
            $upd = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND weight_value = ?");
            $upd->execute([$sku, $pid, $wv]);
            $syncedSku += $upd->rowCount();
        } else {
            // No weight specified — sync to any weight without SKU
            $upd = $db->prepare("UPDATE product_weights SET sku = ? WHERE product_id = ? AND (sku IS NULL OR sku = '') LIMIT 1");
            $upd->execute([$sku, $pid]);
            $syncedSku += $upd->rowCount();
        }

        // Also update products.sku if empty
        try {
            $upd2 = $db->prepare("UPDATE products SET sku = ? WHERE id = ? AND (sku IS NULL OR sku = '')");
            $upd2->execute([$sku, $pid]);
        } catch (Exception $e) { /* ignore */ }
    }
    $results[] = "✅ Synced $syncedSku SKU values from barcode_inventory → product_weights";
} catch (Exception $e) {
    $results[] = "❌ Error syncing SKU: " . $e->getMessage();
}

// =========================================================================
// Output results
// =========================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>EAN/SKU Migration</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; padding: 30px; max-width: 800px; margin: 0 auto; background: #f8fafc; }
        h1 { color: #1e293b; }
        .results { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .results p { margin: 8px 0; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 13px; }
        .results p:nth-child(odd) { background: #f1f5f9; }
        .back { display: inline-block; margin-top: 20px; padding: 8px 16px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; }
        .back:hover { background: #1d4ed8; }
        .note { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔧 EAN & SKU Data Migration</h1>
    <div class="note">
        <strong>Migration completed.</strong> This script only modifies <code>ean</code> and <code>sku</code> columns. Product images, descriptions, and other data are completely untouched.
    </div>
    <div class="results">
        <?php foreach ($results as $r): ?>
            <p><?= htmlspecialchars($r); ?></p>
        <?php endforeach; ?>
    </div>
    <a class="back" href="barcode_management.php">← Back to Barcode Management</a>
</body>
</html>
