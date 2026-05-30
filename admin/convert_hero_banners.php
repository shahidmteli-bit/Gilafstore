<?php
/**
 * One-time migration: Convert existing hero banner PNG/JPG images to WebP
 * Run this ONCE after uploading to Hostinger, then delete this file.
 * 
 * Usage: Visit /admin/convert_hero_banners.php while logged in as admin
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$results = [];
$baseDir = __DIR__ . '/../';

try {
    $slides = db_fetch_all("SELECT id, image_path FROM hero_banner_slides WHERE is_active = 1");
    
    foreach ($slides as $slide) {
        $fullPath = $baseDir . $slide['image_path'];
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        
        // Skip if already WebP
        if ($ext === 'webp') {
            $size = file_exists($fullPath) ? round(filesize($fullPath) / 1024, 1) : 0;
            $results[] = "SKIP (already WebP): {$slide['image_path']} ({$size} KB)";
            continue;
        }
        
        if (!file_exists($fullPath)) {
            $results[] = "ERROR: File not found: {$slide['image_path']}";
            continue;
        }
        
        $originalSize = filesize($fullPath);
        
        // Load source image
        $srcImage = null;
        if ($ext === 'png') $srcImage = @imagecreatefrompng($fullPath);
        elseif (in_array($ext, ['jpg', 'jpeg'])) $srcImage = @imagecreatefromjpeg($fullPath);
        
        if (!$srcImage) {
            $results[] = "ERROR: Could not load image: {$slide['image_path']}";
            continue;
        }
        
        // Resize to max 1200px wide
        $ow = imagesx($srcImage);
        $oh = imagesy($srcImage);
        $maxW = 1200;
        if ($ow > $maxW) {
            $nw = $maxW;
            $nh = intval($oh * ($maxW / $ow));
        } else {
            $nw = $ow;
            $nh = $oh;
        }
        
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
        imagedestroy($srcImage);
        
        // Save as WebP
        $newFilename = pathinfo(basename($fullPath), PATHINFO_FILENAME) . '.webp';
        $newPath = dirname($fullPath) . '/' . $newFilename;
        $newDbPath = dirname($slide['image_path']) . '/' . $newFilename;
        
        $quality = 78;
        imagewebp($resized, $newPath, $quality);
        if (filesize($newPath) > 153600) {
            imagewebp($resized, $newPath, 65);
        }
        imagedestroy($resized);
        
        $newSize = filesize($newPath);
        $savedKB = round(($originalSize - $newSize) / 1024, 1);
        $savedPct = round((1 - $newSize / $originalSize) * 100, 0);
        
        // Update database
        db_query("UPDATE hero_banner_slides SET image_path = ? WHERE id = ?", [$newDbPath, $slide['id']]);
        
        // Delete old PNG
        unlink($fullPath);
        
        $results[] = "CONVERTED: {$slide['image_path']} → {$newDbPath} (saved {$savedKB} KB, {$savedPct}% smaller)";
    }
} catch (Exception $e) {
    $results[] = "DATABASE ERROR: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html><head><title>Hero Banner Conversion</title></head>
<body style="font-family: monospace; padding: 40px; background: #f5f5f5;">
<h2 style="color: #1A3C34;">Hero Banner PNG → WebP Conversion</h2>
<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
<?php foreach ($results as $r): ?>
<p style="margin: 8px 0; color: <?= strpos($r, 'ERROR') !== false ? '#dc3545' : (strpos($r, 'SKIP') !== false ? '#6c757d' : '#28a745'); ?>;">
    <?= htmlspecialchars($r); ?>
</p>
<?php endforeach; ?>
<?php if (empty($results)): ?>
<p style="color: #6c757d;">No hero banner slides found.</p>
<?php endif; ?>
</div>
<p style="margin-top: 20px; color: #666;">⚠️ Delete this file after running: <code>admin/convert_hero_banners.php</code></p>
<a href="manage_hero_banner.php" style="color: #1A3C34;">← Back to Hero Banner Management</a>
</body></html>
