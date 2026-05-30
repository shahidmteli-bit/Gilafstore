<?php
/**
 * Image Audit Tool — Cross-references DB product images with files on disk.
 * Identifies: orphan files, missing files, duplicates, old unused images.
 * 
 * Run via browser: /admin/image_audit.php
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$adminPage = 'optimize_images';
$pageTitle = 'Image Audit';

$db = get_db_connection();
$productsDir = realpath(__DIR__ . '/../assets/images/products') . DIRECTORY_SEPARATOR;

// ========================================================
// 1. Collect ALL image filenames referenced in the database
// ========================================================
$dbImages = [];

// From products table: image, image_1..image_4
$cols = ['image'];
foreach (['image_1', 'image_2', 'image_3', 'image_4'] as $col) {
    $check = $db->query("SHOW COLUMNS FROM products LIKE '{$col}'");
    if ($check->rowCount() > 0) {
        $cols[] = $col;
    }
}

$rows = $db->query("SELECT id, name, " . implode(', ', $cols) . " FROM products")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    foreach ($cols as $col) {
        if (!empty($row[$col])) {
            $filename = basename($row[$col]);
            $dbImages[$filename] = [
                'source' => "products.{$col}",
                'product_id' => $row['id'],
                'product_name' => $row['name'],
            ];
        }
    }
}

// From product_weights table: variant_image, variant_image_back
$variantCols = [];
$check = $db->query("SHOW COLUMNS FROM product_weights LIKE 'variant_image'");
if ($check->rowCount() > 0) $variantCols[] = 'variant_image';
$check = $db->query("SHOW COLUMNS FROM product_weights LIKE 'variant_image_back'");
if ($check->rowCount() > 0) $variantCols[] = 'variant_image_back';

if (!empty($variantCols)) {
    $wRows = $db->query("SELECT pw.id as weight_id, pw.product_id, pw.display_weight, " . implode(', ', array_map(fn($c) => "pw.{$c}", $variantCols)) . ", p.name as product_name FROM product_weights pw LEFT JOIN products p ON pw.product_id = p.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($wRows as $row) {
        foreach ($variantCols as $col) {
            if (!empty($row[$col])) {
                $filename = basename($row[$col]);
                $dbImages[$filename] = [
                    'source' => "product_weights.{$col}",
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'] . ' (' . $row['display_weight'] . ')',
                ];
            }
        }
    }
}

// ========================================================
// 2. Collect ALL files on disk in products/ folder
// ========================================================
$diskFiles = [];
$duplicateCheck = []; // group by file size to detect content duplicates
if (is_dir($productsDir)) {
    $iterator = new DirectoryIterator($productsDir);
    foreach ($iterator as $file) {
        if ($file->isDot() || $file->isDir()) continue;
        $name = $file->getFilename();
        $size = $file->getSize();
        $diskFiles[$name] = [
            'size' => $size,
            'modified' => $file->getMTime(),
        ];
        $sizeKey = $size;
        $duplicateCheck[$sizeKey][] = $name;
    }
}

// ========================================================
// 3. Cross-reference
// ========================================================

// Orphan files: on disk but NOT in DB
$orphanFiles = [];
foreach ($diskFiles as $filename => $info) {
    if (!isset($dbImages[$filename])) {
        $orphanFiles[$filename] = $info;
    }
}

// Missing files: in DB but NOT on disk
$missingFiles = [];
foreach ($dbImages as $filename => $info) {
    if (!isset($diskFiles[$filename])) {
        $missingFiles[$filename] = $info;
    }
}

// Active files: in both DB and disk
$activeFiles = [];
foreach ($dbImages as $filename => $info) {
    if (isset($diskFiles[$filename])) {
        $activeFiles[$filename] = array_merge($info, $diskFiles[$filename]);
    }
}

// Potential duplicates: same file size (possible content dupes)
$potentialDuplicates = [];
foreach ($duplicateCheck as $size => $names) {
    if (count($names) > 1 && $size > 1000) { // ignore tiny files
        $potentialDuplicates[$size] = $names;
    }
}

// ========================================================
// 4. Stats
// ========================================================
$totalDiskFiles = count($diskFiles);
$totalDbRefs = count($dbImages);
$totalOrphan = count($orphanFiles);
$totalMissing = count($missingFiles);
$totalActive = count($activeFiles);
$orphanSize = array_sum(array_column($orphanFiles, 'size'));

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.audit-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
.audit-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
.audit-stat { background: #f8fafc; border-radius: 10px; padding: 20px; text-align: center; border: 1px solid #e2e8f0; }
.audit-stat-value { font-size: 2rem; font-weight: 700; }
.audit-stat-label { font-size: 0.85rem; color: #64748b; margin-top: 4px; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.audit-table th { background: #1A3C34; color: white; padding: 10px 12px; text-align: left; position: sticky; top: 0; }
.audit-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.audit-table tr:hover { background: #f8fafc; }
.badge-orphan { background: #fef2f2; color: #dc2626; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.badge-missing { background: #fefce8; color: #ca8a04; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.badge-active { background: #f0fdf4; color: #16a34a; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.badge-dupe { background: #eff6ff; color: #2563eb; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.section-title { font-size: 1.2rem; font-weight: 700; color: #1A3C34; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; }
</style>

<h1 style="font-size: 1.5rem; margin-bottom: 20px;"><i class="fas fa-search" style="color: #1A3C34;"></i> Image Audit Report</h1>

<!-- Stats -->
<div class="audit-stats">
    <div class="audit-stat">
        <div class="audit-stat-value" style="color: #1A3C34;"><?= $totalDiskFiles ?></div>
        <div class="audit-stat-label">Files on Disk</div>
    </div>
    <div class="audit-stat">
        <div class="audit-stat-value" style="color: #2563eb;"><?= $totalDbRefs ?></div>
        <div class="audit-stat-label">DB References</div>
    </div>
    <div class="audit-stat">
        <div class="audit-stat-value" style="color: #16a34a;"><?= $totalActive ?></div>
        <div class="audit-stat-label">Active (Matched)</div>
    </div>
    <div class="audit-stat">
        <div class="audit-stat-value" style="color: #dc2626;"><?= $totalOrphan ?></div>
        <div class="audit-stat-label">Orphan Files</div>
    </div>
    <div class="audit-stat">
        <div class="audit-stat-value" style="color: #ca8a04;"><?= $totalMissing ?></div>
        <div class="audit-stat-label">Missing from Disk</div>
    </div>
    <div class="audit-stat">
        <div class="audit-stat-value" style="color: #dc2626;"><?= number_format($orphanSize / 1048576, 1) ?> MB</div>
        <div class="audit-stat-label">Orphan Disk Space</div>
    </div>
</div>

<?php if ($totalOrphan > 0): ?>
<!-- Orphan Files -->
<div class="audit-card">
    <div class="section-title"><i class="fas fa-ghost" style="color: #dc2626;"></i> Orphan Files (on disk but NOT in database) — Safe to Delete</div>
    <p style="color: #666; font-size: 0.85rem; margin-bottom: 12px;">These files exist in <code>assets/images/products/</code> but are <strong>not referenced by any product</strong>. They are leftover from deleted or replaced products.</p>
    <div style="max-height: 500px; overflow-y: auto;">
    <table class="audit-table">
        <thead>
            <tr><th>Preview</th><th>Filename</th><th>Size</th><th>Last Modified</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($orphanFiles as $name => $info): ?>
            <tr>
                <td><img src="<?= base_url('assets/images/products/' . rawurlencode($name)) ?>" class="thumb" loading="lazy" onerror="this.style.display='none'"></td>
                <td style="font-family: monospace; font-size: 0.78rem; word-break: break-all;"><?= htmlspecialchars($name) ?></td>
                <td><?= number_format($info['size'] / 1024, 1) ?> KB</td>
                <td><?= date('d M Y', $info['modified']) ?></td>
                <td><span class="badge-orphan">ORPHAN</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if ($totalMissing > 0): ?>
<!-- Missing Files -->
<div class="audit-card">
    <div class="section-title"><i class="fas fa-exclamation-triangle" style="color: #ca8a04;"></i> Missing Files (in database but NOT on disk)</div>
    <p style="color: #666; font-size: 0.85rem; margin-bottom: 12px;">These filenames are referenced in the database but the <strong>actual file doesn't exist</strong> on disk. These products will show broken images.</p>
    <div style="max-height: 500px; overflow-y: auto;">
    <table class="audit-table">
        <thead>
            <tr><th>Filename</th><th>Product</th><th>Source Column</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($missingFiles as $name => $info): ?>
            <tr>
                <td style="font-family: monospace; font-size: 0.78rem; word-break: break-all;"><?= htmlspecialchars($name) ?></td>
                <td><a href="<?= base_url('admin/product_edit.php?id=' . $info['product_id']) ?>"><?= htmlspecialchars($info['product_name']) ?></a> (ID: <?= $info['product_id'] ?>)</td>
                <td><code><?= $info['source'] ?></code></td>
                <td><span class="badge-missing">MISSING</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($potentialDuplicates)): ?>
<!-- Potential Duplicates -->
<div class="audit-card">
    <div class="section-title"><i class="fas fa-clone" style="color: #2563eb;"></i> Potential Duplicates (same file size)</div>
    <p style="color: #666; font-size: 0.85rem; margin-bottom: 12px;">Files with identical sizes that <em>may</em> be duplicates. Review manually.</p>
    <div style="max-height: 400px; overflow-y: auto;">
    <table class="audit-table">
        <thead>
            <tr><th>Size</th><th>Files</th><th>In DB?</th></tr>
        </thead>
        <tbody>
            <?php foreach ($potentialDuplicates as $size => $names): ?>
            <tr>
                <td><?= number_format($size / 1024, 1) ?> KB</td>
                <td>
                    <?php foreach ($names as $n): ?>
                        <div style="font-family: monospace; font-size: 0.78rem;"><?= htmlspecialchars($n) ?> <?= isset($dbImages[$n]) ? '<span class="badge-active">ACTIVE</span>' : '<span class="badge-orphan">ORPHAN</span>' ?></div>
                    <?php endforeach; ?>
                </td>
                <td><?= count(array_filter($names, fn($n) => isset($dbImages[$n]))) ?> / <?= count($names) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- Active Files Summary -->
<div class="audit-card">
    <div class="section-title"><i class="fas fa-check-circle" style="color: #16a34a;"></i> Active Files (<?= $totalActive ?> matched)</div>
    <p style="color: #666; font-size: 0.85rem;">These files are on disk AND referenced in the database. They are currently in use.</p>
</div>

<!-- JSON export for programmatic use -->
<div class="audit-card">
    <div class="section-title"><i class="fas fa-download"></i> Export</div>
    <details>
        <summary style="cursor: pointer; color: #2563eb;">View orphan filenames (copy-paste for deletion)</summary>
        <pre style="background: #f1f5f9; padding: 12px; border-radius: 8px; max-height: 300px; overflow: auto; font-size: 0.78rem; margin-top: 8px;"><?php
            foreach ($orphanFiles as $name => $info) {
                echo $name . "\n";
            }
        ?></pre>
    </details>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
