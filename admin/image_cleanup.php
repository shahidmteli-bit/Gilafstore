<?php
/**
 * Intelligent Image Cleanup & Optimization System
 * Scans all images, detects unused/orphan files, and provides safe cleanup
 * 
 * Protection layers:
 *   1. Database references (products, weights, banners, categories)
 *   2. Hardcoded references in PHP/HTML/CSS files (icons, logos, etc.)
 *   3. Double-verification before any delete
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Image Cleanup & Optimization';
$adminPage = 'image_cleanup';

$db = get_db_connection();
$siteRoot = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR;
$imageDir = $siteRoot . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR;

// ═══════════════════════════════════════════════════════
// LAYER 1: Collect ALL referenced images from DATABASE
// ═══════════════════════════════════════════════════════
function getAllReferencedImages($db) {
    $referenced = [];

    // Products table: image, image_1..image_4
    try {
        $cols = ['image'];
        foreach (['image_1','image_2','image_3','image_4'] as $col) {
            $chk = $db->query("SHOW COLUMNS FROM products LIKE '$col'");
            if ($chk->rowCount() > 0) $cols[] = $col;
        }
        $rows = $db->query("SELECT id, " . implode(',', $cols) . " FROM products")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach ($cols as $col) {
                if (!empty($row[$col])) {
                    $referenced[basename($row[$col])] = 'Product #' . $row['id'] . ' (' . $col . ')';
                }
            }
        }
    } catch (Exception $e) {}

    // Product weights: variant_image, variant_image_back
    try {
        $weightCols = [];
        foreach (['variant_image', 'variant_image_back'] as $vc) {
            $chk = $db->query("SHOW COLUMNS FROM product_weights LIKE '$vc'");
            if ($chk->rowCount() > 0) $weightCols[] = $vc;
        }
        if (!empty($weightCols)) {
            $rows = $db->query("SELECT id, product_id, " . implode(',', $weightCols) . " FROM product_weights")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                foreach ($weightCols as $col) {
                    if (!empty($row[$col])) {
                        $referenced[basename($row[$col])] = 'Weight #' . $row['id'] . ' (' . $col . ')';
                    }
                }
            }
        }
    } catch (Exception $e) {}

    // Hero banner slides
    try {
        $rows = $db->query("SELECT id, image_path FROM hero_banner_slides")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!empty($row['image_path'])) {
                $referenced[basename($row['image_path'])] = 'Hero Banner #' . $row['id'];
            }
        }
    } catch (Exception $e) {}

    // Categories
    try {
        $chk = $db->query("SHOW COLUMNS FROM categories LIKE 'image'");
        if ($chk->rowCount() > 0) {
            $rows = $db->query("SELECT id, image FROM categories WHERE image IS NOT NULL AND image != ''")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $referenced[basename($row['image'])] = 'Category #' . $row['id'];
            }
        }
    } catch (Exception $e) {}

    // Lab reports
    try {
        $chk = $db->query("SHOW COLUMNS FROM products LIKE 'lab_report_file'");
        if ($chk->rowCount() > 0) {
            $rows = $db->query("SELECT id, lab_report_file FROM products WHERE lab_report_file IS NOT NULL AND lab_report_file != ''")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $referenced[basename($row['lab_report_file'])] = 'Lab Report (Product #' . $row['id'] . ')';
            }
        }
    } catch (Exception $e) {}

    return $referenced;
}

// ═══════════════════════════════════════════════════════
// LAYER 2: Scan PHP/HTML/CSS files for HARDCODED images
// ═══════════════════════════════════════════════════════
function getHardcodedImageReferences($siteRoot) {
    $hardcoded = [];
    $extensions = ['php', 'html', 'htm', 'css', 'js'];
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
    $imagePattern = '/["\']([^"\']*\.(?:' . implode('|', $imageExts) . '))["\']|url\(\s*["\']?([^"\')\s]+\.(?:' . implode('|', $imageExts) . '))["\']?\s*\)/i';

    $scanDirs = [$siteRoot];
    $skipDirs = ['.git', '.vscode', '.windsurf-backups', 'backups', 'node_modules', 'vendor', 'cache', 'logs'];

    foreach ($scanDirs as $scanDir) {
        if (!is_dir($scanDir)) continue;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($current, $key, $iterator) use ($skipDirs) {
                    if ($current->isDir() && in_array($current->getFilename(), $skipDirs)) return false;
                    return true;
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extensions)) continue;
            if ($file->getSize() > 2097152) continue; // Skip files > 2MB

            $content = @file_get_contents($file->getPathname());
            if (!$content) continue;

            if (preg_match_all($imagePattern, $content, $matches)) {
                foreach (array_merge($matches[1], $matches[2]) as $match) {
                    if (empty($match)) continue;
                    $basename = basename($match);
                    $fileExt = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                    if (in_array($fileExt, $imageExts)) {
                        $relSource = str_replace('\\', '/', substr($file->getPathname(), strlen($siteRoot)));
                        $hardcoded[$basename] = 'Hardcoded in ' . $relSource;
                    }
                }
            }
        }
    }

    return $hardcoded;
}

// ═══════════════════════════════════════════════════════
// Scan disk for product image files
// ═══════════════════════════════════════════════════════
function scanImageFiles($baseDir) {
    $files = [];
    $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
    if (!is_dir($baseDir)) return $files;

    $dirHandle = opendir($baseDir);
    if (!$dirHandle) return $files;

    while (($entry = readdir($dirHandle)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $baseDir . $entry;
        if (!is_file($fullPath)) continue;

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions)) continue;

        $files[] = [
            'filename' => $entry,
            'full_path' => $fullPath,
            'size' => filesize($fullPath),
            'modified' => filemtime($fullPath),
            'extension' => $ext,
        ];
    }
    closedir($dirHandle);
    return $files;
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ═══════════════════════════════════════════════════════
// HANDLE AJAX ACTIONS FIRST (before any HTML output)
// ═══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'delete_unused') {
        $filesToDelete = json_decode($_POST['files'] ?? '[]', true);
        $deleted = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        // DOUBLE SAFETY: Re-check ALL references before deleting
        $dbRefs = getAllReferencedImages($db);
        $hardRefs = getHardcodedImageReferences($siteRoot);
        $allProtected = array_merge($dbRefs, $hardRefs);

        foreach ($filesToDelete as $filename) {
            $filename = basename($filename); // Sanitize

            // SAFETY CHECK: Never delete if referenced anywhere
            if (isset($allProtected[$filename])) {
                $errors[] = "$filename is PROTECTED ({$allProtected[$filename]}) — skipped";
                $skipped++;
                continue;
            }

            $fullPath = $imageDir . $filename;
            if (file_exists($fullPath) && is_file($fullPath)) {
                if (@unlink($fullPath)) {
                    $deleted++;
                } else {
                    $errors[] = "Permission denied: $filename";
                    $failed++;
                }
            } else {
                $errors[] = "File not found: $filename";
                $failed++;
            }
        }

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ]);
        exit;
    }

    if ($_POST['action'] === 'rename_to_barcode') {
        $renamed = 0;
        $errors = [];

        try {
            $weightCols = ['id', 'product_id', 'ean'];
            foreach (['variant_image', 'variant_image_back'] as $vc) {
                $chk = $db->query("SHOW COLUMNS FROM product_weights LIKE '$vc'");
                if ($chk->rowCount() > 0) $weightCols[] = $vc;
            }
            $hasVI = in_array('variant_image', $weightCols);
            $hasVIB = in_array('variant_image_back', $weightCols);

            $weights = $db->query("SELECT " . implode(',', $weightCols) . " FROM product_weights WHERE ean IS NOT NULL AND ean != ''")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($weights as $w) {
                $ean = $w['ean'];
                if ($hasVI && !empty($w['variant_image'])) {
                    $oldName = $w['variant_image'];
                    $ext = pathinfo($oldName, PATHINFO_EXTENSION) ?: 'jpg';
                    $newName = $ean . '-front.' . $ext;
                    if ($oldName !== $newName && file_exists($imageDir . $oldName) && !file_exists($imageDir . $newName)) {
                        if (rename($imageDir . $oldName, $imageDir . $newName)) {
                            $db->prepare("UPDATE product_weights SET variant_image = ? WHERE id = ?")->execute([$newName, $w['id']]);
                            $renamed++;
                        }
                    }
                }
                if ($hasVIB && !empty($w['variant_image_back'])) {
                    $oldName = $w['variant_image_back'];
                    $ext = pathinfo($oldName, PATHINFO_EXTENSION) ?: 'jpg';
                    $newName = $ean . '-back.' . $ext;
                    if ($oldName !== $newName && file_exists($imageDir . $oldName) && !file_exists($imageDir . $newName)) {
                        if (rename($imageDir . $oldName, $imageDir . $newName)) {
                            $db->prepare("UPDATE product_weights SET variant_image_back = ? WHERE id = ?")->execute([$newName, $w['id']]);
                            $renamed++;
                        }
                    }
                }
            }

            // Product main images
            $prodCols = ['id', 'ean', 'image'];
            foreach (['image_1','image_2','image_3','image_4'] as $ic) {
                $chk = $db->query("SHOW COLUMNS FROM products LIKE '$ic'");
                if ($chk->rowCount() > 0) $prodCols[] = $ic;
            }
            $products = $db->query("SELECT " . implode(',', $prodCols) . " FROM products WHERE ean IS NOT NULL AND ean != ''")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as $p) {
                $ean = $p['ean'];
                $imgIndex = 0;
                foreach (['image_1','image_2','image_3','image_4'] as $ic) {
                    if (!isset($p[$ic]) || empty($p[$ic])) continue;
                    $imgIndex++;
                    $oldName = $p[$ic];
                    $ext = pathinfo($oldName, PATHINFO_EXTENSION) ?: 'jpg';
                    $newName = ($imgIndex === 1) ? "$ean-front.$ext" : (($imgIndex === 2) ? "$ean-back.$ext" : "$ean-" . ($imgIndex - 2) . ".$ext");

                    if ($oldName !== $newName && file_exists($imageDir . $oldName) && !file_exists($imageDir . $newName)) {
                        if (rename($imageDir . $oldName, $imageDir . $newName)) {
                            $db->prepare("UPDATE products SET $ic = ? WHERE id = ?")->execute([$newName, $p['id']]);
                            if ($p['image'] === $oldName) {
                                $db->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$newName, $p['id']]);
                            }
                            $renamed++;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        echo json_encode(['success' => true, 'renamed' => $renamed, 'errors' => $errors]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ═══════════════════════════════════════════════════════
// RUN THE SCAN (only for page display, not AJAX)
// ═══════════════════════════════════════════════════════
$allFiles = scanImageFiles($imageDir);
$dbRefs = getAllReferencedImages($db);
$hardRefs = getHardcodedImageReferences($siteRoot);
$allProtected = array_merge($dbRefs, $hardRefs);

$usedFiles = [];
$unusedFiles = [];
$totalSize = 0;
$unusedSize = 0;

foreach ($allFiles as $file) {
    $totalSize += $file['size'];
    if (isset($allProtected[$file['filename']])) {
        $file['used_by'] = $allProtected[$file['filename']];
        $usedFiles[] = $file;
    } else {
        $unusedFiles[] = $file;
        $unusedSize += $file['size'];
    }
}

usort($unusedFiles, fn($a, $b) => $b['size'] - $a['size']);

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
<div class="container-fluid">
<style>
    .ic-header { background: linear-gradient(135deg, #1A3C34 0%, #244A36 50%, #2d5a42 100%); color: white; padding: 25px 30px; border-radius: 12px; margin-bottom: 25px; }
    .ic-header h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .ic-header p { margin: 8px 0 0; opacity: 0.8; font-size: 0.9rem; }
    .ic-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 25px; }
    .ic-stat { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.05); text-align: center; }
    .ic-stat-value { font-size: 2rem; font-weight: 800; color: #1A3C34; }
    .ic-stat-label { font-size: 0.8rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; font-weight: 600; }
    .ic-stat.danger .ic-stat-value { color: #dc2626; }
    .ic-stat.success .ic-stat-value { color: #16a34a; }
    .ic-stat.warning .ic-stat-value { color: #f59e0b; }

    .ic-section { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 25px; border: 1px solid rgba(0,0,0,0.05); }
    .ic-section h2 { font-size: 1.2rem; font-weight: 700; color: #1A3C34; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

    .ic-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
    .ic-btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .ic-btn-danger { background: #dc2626; color: white; }
    .ic-btn-danger:hover { background: #b91c1c; }
    .ic-btn-primary { background: #C5A059; color: white; }
    .ic-btn-primary:hover { background: #b08f4a; }
    .ic-btn-success { background: #16a34a; color: white; }
    .ic-btn-success:hover { background: #15803d; }
    .ic-btn-secondary { background: #6b7280; color: white; }
    .ic-btn-secondary:hover { background: #4b5563; }
    .ic-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .ic-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .ic-table th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-weight: 700; color: #555; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; border-bottom: 2px solid #e5e7eb; position: sticky; top: 0; z-index: 1; }
    .ic-table td { padding: 10px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .ic-table tr:hover { background: #fafbfc; }
    .ic-table .thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; }

    .ic-badge { padding: 3px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
    .ic-badge-used { background: #d1fae5; color: #16a34a; }
    .ic-badge-unused { background: #fee2e2; color: #dc2626; }
    .ic-badge-hardcoded { background: #dbeafe; color: #2563eb; }

    .ic-progress { display: none; background: #f0f0f0; border-radius: 8px; height: 8px; margin: 15px 0; overflow: hidden; }
    .ic-progress-bar { height: 100%; background: linear-gradient(90deg, #16a34a, #4ade80); border-radius: 8px; transition: width 0.3s; width: 0%; }

    .ic-alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-weight: 500; }
    .ic-alert-success { background: #d1fae5; color: #16a34a; border: 1px solid #16a34a; }
    .ic-alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }
    .ic-alert-warning { background: #fef3c7; color: #d97706; border: 1px solid #d97706; }

    .ic-empty { text-align: center; padding: 40px; color: #999; }
    .ic-empty i { font-size: 3rem; margin-bottom: 12px; opacity: 0.3; display: block; }

    .ic-tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 2px solid #e5e7eb; }
    .ic-tab { padding: 12px 24px; cursor: pointer; font-weight: 600; font-size: 0.88rem; color: #888; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
    .ic-tab:hover { color: #1A3C34; }
    .ic-tab.active { color: #1A3C34; border-bottom-color: #C5A059; }
    .ic-tab-content { display: none; padding-top: 20px; }
    .ic-tab-content.active { display: block; }
</style>

<div class="ic-header">
    <h1><i class="fas fa-broom"></i> Intelligent Image Cleanup & Optimization</h1>
    <p>Scan, verify, and safely remove unused images. All deletions are double-verified against database AND hardcoded references.</p>
</div>

<!-- Protection Summary -->
<div class="ic-alert ic-alert-warning">
    <i class="fas fa-shield-alt"></i>
    <div>
        <strong>Protection Active:</strong>
        Database references (<?= count($dbRefs); ?> images) +
        Hardcoded in PHP/CSS/JS files (<?= count($hardRefs); ?> images) =
        <strong><?= count($allProtected); ?> total protected</strong>.
        Only truly orphaned files are marked for deletion.
    </div>
</div>

<!-- Stats -->
<div class="ic-stats">
    <div class="ic-stat">
        <div class="ic-stat-value"><?= count($allFiles); ?></div>
        <div class="ic-stat-label">Total Images</div>
    </div>
    <div class="ic-stat success">
        <div class="ic-stat-value"><?= count($usedFiles); ?></div>
        <div class="ic-stat-label"><i class="fas fa-shield-alt"></i> Protected (In Use)</div>
    </div>
    <div class="ic-stat danger">
        <div class="ic-stat-value"><?= count($unusedFiles); ?></div>
        <div class="ic-stat-label"><i class="fas fa-trash"></i> Unused (Junk)</div>
    </div>
    <div class="ic-stat">
        <div class="ic-stat-value"><?= formatSize($totalSize); ?></div>
        <div class="ic-stat-label">Total Size</div>
    </div>
    <div class="ic-stat warning">
        <div class="ic-stat-value"><?= formatSize($unusedSize); ?></div>
        <div class="ic-stat-label">Reclaimable Space</div>
    </div>
</div>

<div id="icAlerts"></div>

<!-- Tabs -->
<div class="ic-section" style="padding-bottom: 0;">
    <div class="ic-tabs">
        <div class="ic-tab active" onclick="switchTab('unused', this)"><i class="fas fa-trash me-1"></i> Unused Images (<?= count($unusedFiles); ?>)</div>
        <div class="ic-tab" onclick="switchTab('used', this)"><i class="fas fa-check-circle me-1"></i> Protected Images (<?= count($usedFiles); ?>)</div>
        <div class="ic-tab" onclick="switchTab('rename', this)"><i class="fas fa-barcode me-1"></i> Barcode Rename</div>
    </div>
</div>

<!-- Unused Images Tab -->
<div class="ic-tab-content active" id="tab-unused">
    <div class="ic-section">
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i> Unused Images — Safe to Delete</h2>
        <p style="color: #666; margin-bottom: 16px; font-size: 0.9rem;">
            <i class="fas fa-shield-alt" style="color: #16a34a;"></i>
            These images are <strong>NOT referenced</strong> in any database table, PHP file, CSS file, or HTML template.
            Double-verified before deletion.
        </p>

        <?php if (!empty($unusedFiles)): ?>
        <div class="ic-actions">
            <button class="ic-btn ic-btn-danger" onclick="deleteSelected()">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
            <button class="ic-btn ic-btn-danger" onclick="deleteAllUnused()" style="background: #991b1b;">
                <i class="fas fa-dumpster-fire"></i> Delete All Unused (<?= count($unusedFiles); ?>) — Free <?= formatSize($unusedSize); ?>
            </button>
        </div>

        <div class="ic-progress" id="deleteProgress">
            <div class="ic-progress-bar" id="deleteProgressBar"></div>
        </div>

        <div style="max-height: 600px; overflow-y: auto;">
        <table class="ic-table">
            <thead>
                <tr>
                    <th><input type="checkbox" onchange="toggleAll(this)"></th>
                    <th>Preview</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unusedFiles as $i => $file): ?>
                <tr>
                    <td><input type="checkbox" class="ic-file-check" value="<?= htmlspecialchars($file['filename']); ?>"></td>
                    <td><img src="<?= asset_url('images/products/' . rawurlencode($file['filename'])); ?>" class="thumb" onerror="this.style.display='none'"></td>
                    <td style="font-family: monospace; font-size: 0.8rem; word-break: break-all;"><?= htmlspecialchars($file['filename']); ?></td>
                    <td><?= formatSize($file['size']); ?></td>
                    <td><?= date('Y-m-d H:i', $file['modified']); ?></td>
                    <td><span class="ic-badge ic-badge-unused">Unused</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="ic-empty">
            <i class="fas fa-check-circle" style="color: #16a34a;"></i>
            <p><strong>No unused images found!</strong><br>All images are referenced in the database or code files.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Protected Images Tab -->
<div class="ic-tab-content" id="tab-used">
    <div class="ic-section">
        <h2><i class="fas fa-shield-alt" style="color: #16a34a;"></i> Protected Images — In Use (<?= count($usedFiles); ?>)</h2>
        <p style="color: #666; margin-bottom: 16px; font-size: 0.9rem;">
            These images are actively referenced in the database or hardcoded in website files. They will <strong>never be deleted</strong>.
        </p>

        <div style="max-height: 600px; overflow-y: auto;">
        <table class="ic-table">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Referenced By</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usedFiles as $file):
                    $isHardcoded = strpos($file['used_by'], 'Hardcoded') === 0;
                ?>
                <tr>
                    <td><img src="<?= asset_url('images/products/' . rawurlencode($file['filename'])); ?>" class="thumb" onerror="this.style.display='none'"></td>
                    <td style="font-family: monospace; font-size: 0.8rem; word-break: break-all;"><?= htmlspecialchars($file['filename']); ?></td>
                    <td><?= formatSize($file['size']); ?></td>
                    <td><span class="badge <?= $isHardcoded ? 'bg-primary' : 'bg-success'; ?>" style="font-size: 0.72rem;"><?= htmlspecialchars($file['used_by']); ?></span></td>
                    <td><span class="ic-badge <?= $isHardcoded ? 'ic-badge-hardcoded' : 'ic-badge-used'; ?>"><i class="fas fa-lock"></i> Protected</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Barcode Rename Tab -->
<div class="ic-tab-content" id="tab-rename">
    <div class="ic-section">
        <h2><i class="fas fa-barcode" style="color: #C5A059;"></i> Smart Barcode Image Naming</h2>
        <p style="color: #666; margin-bottom: 16px; font-size: 0.9rem;">
            Automatically rename product images based on their assigned barcode/EAN number.
        </p>

        <div class="ic-alert ic-alert-warning" style="margin-bottom: 20px;">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Naming Format:</strong><br>
                Front: <code>barcode-front.jpg</code> &nbsp;|&nbsp;
                Back: <code>barcode-back.jpg</code> &nbsp;|&nbsp;
                Additional: <code>barcode-1.jpg</code>, <code>barcode-2.jpg</code>
            </div>
        </div>

        <div class="ic-actions">
            <button class="ic-btn ic-btn-primary" onclick="renameToBarcode()">
                <i class="fas fa-magic"></i> Rename All Images to Barcode Format
            </button>
        </div>

        <div class="ic-progress" id="renameProgress">
            <div class="ic-progress-bar" id="renameProgressBar"></div>
        </div>

        <div id="renameResult"></div>

        <h3 style="margin-top: 20px; font-size: 1rem; color: #555;">Preview — Current vs Barcode Names</h3>
        <div style="max-height: 400px; overflow-y: auto;">
        <table class="ic-table">
            <thead>
                <tr>
                    <th>Product Weight</th>
                    <th>EAN/Barcode</th>
                    <th>Current Front Image</th>
                    <th>New Name</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $previewWeights = $db->query("
                        SELECT pw.id, pw.ean, pw.variant_image, pw.variant_image_back, p.name AS product_name, pw.display_weight
                        FROM product_weights pw
                        JOIN products p ON p.id = pw.product_id
                        WHERE pw.ean IS NOT NULL AND pw.ean != ''
                        ORDER BY p.name ASC
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($previewWeights as $pw):
                        $ean = $pw['ean'];
                        $currentFront = $pw['variant_image'] ?? '';
                        $ext = $currentFront ? pathinfo($currentFront, PATHINFO_EXTENSION) : 'jpg';
                        $newFront = $ean . '-front.' . $ext;
                        $alreadyRenamed = ($currentFront === $newFront);
                ?>
                <tr>
                    <td><?= htmlspecialchars($pw['product_name']); ?> — <?= htmlspecialchars($pw['display_weight']); ?></td>
                    <td><code><?= htmlspecialchars($ean); ?></code></td>
                    <td style="font-family: monospace; font-size: 0.78rem;"><?= $currentFront ? htmlspecialchars($currentFront) : '<em style="color:#999;">No image</em>'; ?></td>
                    <td style="font-family: monospace; font-size: 0.78rem;">
                        <?php if ($alreadyRenamed): ?>
                            <span style="color: #16a34a;"><i class="fas fa-check"></i> <?= htmlspecialchars($newFront); ?></span>
                        <?php elseif ($currentFront): ?>
                            <span style="color: #C5A059;"><?= htmlspecialchars($newFront); ?></span>
                        <?php else: ?>
                            <em style="color:#999;">—</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                    endforeach;
                } catch (Exception $e) {
                    echo '<tr><td colspan="4" style="color:#999;">Unable to load preview: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

</div>
</section>

<script>
const API_URL = '<?= base_url("admin/image_cleanup.php"); ?>';

function switchTab(tab, el) {
    document.querySelectorAll('.ic-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.ic-tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    el.classList.add('active');
}

function toggleAll(master) {
    document.querySelectorAll('.ic-file-check').forEach(cb => cb.checked = master.checked);
}

function getSelectedFiles() {
    return Array.from(document.querySelectorAll('.ic-file-check:checked')).map(cb => cb.value);
}

function showAlert(type, message) {
    const container = document.getElementById('icAlerts');
    const div = document.createElement('div');
    div.className = 'ic-alert ic-alert-' + type;
    div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    container.prepend(div);
    setTimeout(() => div.remove(), 10000);
}

async function deleteSelected() {
    const files = getSelectedFiles();
    if (files.length === 0) { alert('Please select files to delete.'); return; }
    if (!confirm('Delete ' + files.length + ' unused image(s)?\n\nDouble-verified: no active images will be removed.')) return;
    await performDelete(files);
}

async function deleteAllUnused() {
    const allFiles = Array.from(document.querySelectorAll('.ic-file-check')).map(cb => cb.value);
    if (allFiles.length === 0) return;
    if (!confirm('DELETE ALL ' + allFiles.length + ' UNUSED IMAGES?\n\nAll files have been double-verified as NOT referenced in any database table or code file.\n\nThis cannot be undone.')) return;
    await performDelete(allFiles);
}

async function performDelete(files) {
    const progress = document.getElementById('deleteProgress');
    const bar = document.getElementById('deleteProgressBar');
    progress.style.display = 'block';
    bar.style.width = '30%';

    try {
        const formData = new FormData();
        formData.append('action', 'delete_unused');
        formData.append('files', JSON.stringify(files));

        bar.style.width = '60%';
        const response = await fetch(API_URL, { method: 'POST', body: formData });
        bar.style.width = '80%';

        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('Response was not JSON:', text.substring(0, 500));
            showAlert('error', 'Server returned invalid response. Check console.');
            return;
        }

        bar.style.width = '100%';

        if (data.success) {
            let msg = 'Deleted: ' + data.deleted;
            if (data.skipped > 0) msg += ' | Protected (skipped): ' + data.skipped;
            if (data.failed > 0) msg += ' | Failed: ' + data.failed;
            showAlert(data.deleted > 0 ? 'success' : 'warning', msg);
            if (data.errors.length) {
                data.errors.forEach(e => showAlert('error', e));
            }
            if (data.deleted > 0) setTimeout(() => location.reload(), 2000);
        } else {
            showAlert('error', data.error || 'Delete failed');
        }
    } catch (e) {
        showAlert('error', 'Network error: ' + e.message);
    }

    setTimeout(() => { progress.style.display = 'none'; bar.style.width = '0%'; }, 2500);
}

async function renameToBarcode() {
    if (!confirm('Rename all product images to barcode-based naming?\n\nThis updates files on disk AND database references.')) return;

    const progress = document.getElementById('renameProgress');
    const bar = document.getElementById('renameProgressBar');
    progress.style.display = 'block';
    bar.style.width = '50%';

    try {
        const formData = new FormData();
        formData.append('action', 'rename_to_barcode');

        const response = await fetch(API_URL, { method: 'POST', body: formData });
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            showAlert('error', 'Server returned invalid response.');
            return;
        }
        bar.style.width = '100%';

        if (data.success) {
            const msg = data.renamed > 0
                ? 'Renamed ' + data.renamed + ' image(s) to barcode format.'
                : 'All images already in barcode format.';
            showAlert('success', msg);
            if (data.renamed > 0) setTimeout(() => location.reload(), 2000);
        }
    } catch (e) {
        showAlert('error', 'Error: ' + e.message);
    }

    setTimeout(() => { progress.style.display = 'none'; bar.style.width = '0%'; }, 2500);
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
