<?php
/**
 * CLI Image Audit — run via: php admin/run_image_audit.php
 */
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();
$productsDir = __DIR__ . '/../assets/images/products/';

// 1. Collect DB images
$dbImages = [];
$cols = ['image'];
foreach (['image_1','image_2','image_3','image_4'] as $c) {
    $chk = $db->query("SHOW COLUMNS FROM products LIKE '{$c}'");
    if ($chk->rowCount() > 0) $cols[] = $c;
}
$rows = $db->query('SELECT id, name, ' . implode(', ', $cols) . ' FROM products')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    foreach ($cols as $c) {
        if (!empty($r[$c])) {
            $dbImages[basename($r[$c])] = $r['id'] . ' | ' . $r['name'] . ' | ' . $c;
        }
    }
}

// Variant images
$vc = [];
$chk = $db->query("SHOW COLUMNS FROM product_weights LIKE 'variant_image'");
if ($chk->rowCount() > 0) $vc[] = 'variant_image';
$chk = $db->query("SHOW COLUMNS FROM product_weights LIKE 'variant_image_back'");
if ($chk->rowCount() > 0) $vc[] = 'variant_image_back';

if (!empty($vc)) {
    $selectCols = implode(', ', array_map(function($c) { return "pw.{$c}"; }, $vc));
    $wRows = $db->query("SELECT pw.product_id, {$selectCols}, p.name FROM product_weights pw LEFT JOIN products p ON pw.product_id = p.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($wRows as $r) {
        foreach ($vc as $c) {
            if (!empty($r[$c])) {
                $dbImages[basename($r[$c])] = $r['product_id'] . ' | ' . $r['name'] . ' | pw.' . $c;
            }
        }
    }
}

// 2. Collect disk files
$diskFiles = [];
if (is_dir($productsDir)) {
    foreach (new DirectoryIterator($productsDir) as $f) {
        if ($f->isDot() || $f->isDir()) continue;
        $diskFiles[$f->getFilename()] = $f->getSize();
    }
}

// 3. Orphan files
echo "=== ORPHAN FILES (on disk, NOT in DB - safe to delete) ===\n";
$orphanCount = 0;
$orphanSize = 0;
$orphanList = [];
foreach ($diskFiles as $name => $size) {
    if (!isset($dbImages[$name])) {
        echo "  " . str_pad($name, 55) . round($size / 1024, 1) . " KB\n";
        $orphanCount++;
        $orphanSize += $size;
        $orphanList[] = $name;
    }
}
echo "--- Total: {$orphanCount} orphan files, " . round($orphanSize / 1048576, 1) . " MB ---\n\n";

// 4. Missing files
echo "=== MISSING FILES (in DB, NOT on disk - broken images) ===\n";
$missingCount = 0;
foreach ($dbImages as $name => $info) {
    if (!isset($diskFiles[$name])) {
        echo "  " . str_pad($name, 55) . $info . "\n";
        $missingCount++;
    }
}
echo "--- Total: {$missingCount} missing files ---\n\n";

// 5. Active matched
$activeCount = 0;
foreach ($dbImages as $name => $info) {
    if (isset($diskFiles[$name])) $activeCount++;
}

// 6. Duplicate detection (same size)
echo "=== POTENTIAL DUPLICATES (same file size > 1KB) ===\n";
$sizeGroups = [];
foreach ($diskFiles as $name => $size) {
    if ($size > 1024) {
        $sizeGroups[$size][] = $name;
    }
}
$dupeCount = 0;
foreach ($sizeGroups as $size => $names) {
    if (count($names) > 1) {
        $dupeCount++;
        echo "  Size: " . round($size / 1024, 1) . " KB\n";
        foreach ($names as $n) {
            $status = isset($dbImages[$n]) ? 'ACTIVE' : 'ORPHAN';
            echo "    - {$n} [{$status}]\n";
        }
    }
}
if ($dupeCount === 0) echo "  None found.\n";
echo "\n";

// 7. Summary
echo "========== SUMMARY ==========\n";
echo "Files on disk:        " . count($diskFiles) . "\n";
echo "DB references:        " . count($dbImages) . "\n";
echo "Active (matched):     {$activeCount}\n";
echo "Orphan (deletable):   {$orphanCount} (" . round($orphanSize / 1048576, 1) . " MB)\n";
echo "Missing (broken):     {$missingCount}\n";
echo "Duplicate groups:     {$dupeCount}\n";
echo "=============================\n";
