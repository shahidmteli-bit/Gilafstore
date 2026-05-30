<?php
/**
 * Orphan Image Cleanup — Deletes product images NOT referenced in the database.
 * 
 * Usage:
 *   CLI:     php admin/cleanup_orphan_images.php          (dry run)
 *   CLI:     php admin/cleanup_orphan_images.php --delete  (actually delete)
 *   Browser: /admin/cleanup_orphan_images.php              (dry run)
 *   Browser: /admin/cleanup_orphan_images.php?delete=1     (actually delete)
 */
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/';
}

require_once __DIR__ . '/../includes/db_connect.php';

if (!$isCli) {
    require_once __DIR__ . '/../includes/auth.php';
    require_admin();
}

$db = get_db_connection();
$productsDir = realpath(__DIR__ . '/../assets/images/products') . DIRECTORY_SEPARATOR;
$doDelete = $isCli ? in_array('--delete', $argv ?? []) : isset($_GET['delete']);

// 1. Collect ALL image filenames from database
$dbImages = [];

$cols = ['image'];
foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image_5', 'image_6'] as $c) {
    if ($db->query("SHOW COLUMNS FROM products LIKE '{$c}'")->rowCount() > 0) $cols[] = $c;
}
$rows = $db->query('SELECT ' . implode(', ', $cols) . ' FROM products')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    foreach ($cols as $c) {
        if (!empty($r[$c])) $dbImages[basename($r[$c])] = true;
    }
}

$variantCols = [];
if ($db->query("SHOW COLUMNS FROM product_weights LIKE 'variant_image'")->rowCount() > 0) $variantCols[] = 'variant_image';
if ($db->query("SHOW COLUMNS FROM product_weights LIKE 'variant_image_back'")->rowCount() > 0) $variantCols[] = 'variant_image_back';
if (!empty($variantCols)) {
    $sel = implode(', ', $variantCols);
    $wRows = $db->query("SELECT {$sel} FROM product_weights")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($wRows as $r) {
        foreach ($variantCols as $c) {
            if (!empty($r[$c])) $dbImages[basename($r[$c])] = true;
        }
    }
}

// 2. Find orphan files
$orphans = [];
$totalOrphanSize = 0;
$kept = 0;

foreach (new DirectoryIterator($productsDir) as $f) {
    if ($f->isDot() || $f->isDir()) continue;
    $name = $f->getFilename();
    if (isset($dbImages[$name])) {
        $kept++;
        continue;
    }
    $orphans[] = ['name' => $name, 'size' => $f->getSize(), 'path' => $f->getPathname()];
    $totalOrphanSize += $f->getSize();
}

// 3. Output
$output = function($msg) use ($isCli) {
    if ($isCli) echo $msg . "\n";
    else echo htmlspecialchars($msg) . "<br>\n";
};

if (!$isCli) echo "<html><head><title>Orphan Cleanup</title></head><body style='font-family:monospace;padding:20px;max-width:1000px;margin:auto;'>";

$output("========================================");
$output("  PRODUCT IMAGE ORPHAN CLEANUP");
$output("========================================");
$output("Products dir: {$productsDir}");
$output("DB image refs: " . count($dbImages));
$output("Active files kept: {$kept}");
$output("Orphan files found: " . count($orphans));
$output("Orphan disk space: " . round($totalOrphanSize / 1048576, 1) . " MB");
$output("Mode: " . ($doDelete ? "DELETE" : "DRY RUN (preview only)"));
$output("----------------------------------------");

if (empty($orphans)) {
    $output("No orphan files found. Everything is clean!");
} else {
    $deleted = 0;
    $failed = 0;
    foreach ($orphans as $o) {
        $sizeKb = round($o['size'] / 1024, 1);
        if ($doDelete) {
            if (@unlink($o['path'])) {
                $output("[DELETED] {$o['name']} ({$sizeKb} KB)");
                $deleted++;
            } else {
                $output("[FAILED]  {$o['name']} ({$sizeKb} KB)");
                $failed++;
            }
        } else {
            $output("[ORPHAN]  {$o['name']} ({$sizeKb} KB)");
        }
    }
    
    $output("----------------------------------------");
    if ($doDelete) {
        $output("Deleted: {$deleted} files");
        if ($failed > 0) $output("Failed: {$failed} files");
        $output("Freed: " . round($totalOrphanSize / 1048576, 1) . " MB");
    } else {
        $output("To actually delete these files:");
        if ($isCli) {
            $output("  php admin/cleanup_orphan_images.php --delete");
        } else {
            echo "<br><a href='?delete=1' style='background:#dc2626;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;' onclick=\"return confirm('Delete " . count($orphans) . " orphan files (" . round($totalOrphanSize/1048576,1) . " MB)? This cannot be undone.')\">Delete " . count($orphans) . " Orphan Files (" . round($totalOrphanSize/1048576,1) . " MB)</a><br><br>";
        }
    }
}

$output("");
$output("ACTIVE FILES (kept - referenced in DB):");
foreach ($dbImages as $name => $v) {
    $output("  [KEEP] {$name}");
}

if (!$isCli) echo "</body></html>";
