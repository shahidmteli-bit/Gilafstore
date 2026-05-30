<?php
/**
 * Batch Image to WebP Converter
 * Converts all JPG/JPEG/PNG product images to WebP format
 * Preserves originals, creates .webp alongside them
 * Quality: 82 (optimal balance of size/quality)
 * 
 * Usage: Run via CLI or browser (admin only)
 * CLI: php convert_images_webp.php
 */

// If run via web, check admin
if (php_sapi_name() !== 'cli') {
    session_start();
    require_once __DIR__ . '/../includes/functions.php';
    if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
        http_response_code(403);
        die('Admin access required');
    }
}

$quality = 82;
$directories = [
    __DIR__ . '/../assets/images/products/',
    __DIR__ . '/../assets/images/hero/',
    __DIR__ . '/../assets/images/website/',
];

$converted = 0;
$skipped = 0;
$errors = 0;
$totalSaved = 0;

header('Content-Type: text/plain; charset=utf-8');
echo "=== WebP Image Converter ===\n";
echo "Quality: {$quality}\n\n";

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        echo "SKIP: Directory not found: {$dir}\n";
        continue;
    }
    
    echo "Processing: {$dir}\n";
    $files = glob($dir . '*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
    
    foreach ($files as $file) {
        $webpFile = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
        
        // Skip if WebP already exists and is newer than source
        if (file_exists($webpFile) && filemtime($webpFile) >= filemtime($file)) {
            $skipped++;
            continue;
        }
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $image = null;
        
        try {
            if ($ext === 'png') {
                $image = @imagecreatefrompng($file);
                if ($image) {
                    // Preserve transparency by converting to true color with white bg
                    $width = imagesx($image);
                    $height = imagesy($image);
                    $bg = imagecreatetruecolor($width, $height);
                    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                    imagealphablending($bg, true);
                    imagecopy($bg, $image, 0, 0, 0, 0, $width, $height);
                    imagedestroy($image);
                    $image = $bg;
                }
            } else {
                $image = @imagecreatefromjpeg($file);
            }
            
            if (!$image) {
                echo "  ERROR: Cannot read: " . basename($file) . "\n";
                $errors++;
                continue;
            }
            
            $success = imagewebp($image, $webpFile, $quality);
            imagedestroy($image);
            
            if ($success && file_exists($webpFile)) {
                $originalSize = filesize($file);
                $webpSize = filesize($webpFile);
                $saved = $originalSize - $webpSize;
                $percent = round(($saved / $originalSize) * 100);
                $totalSaved += $saved;
                $converted++;
                echo "  OK: " . basename($file) . " → " . round($originalSize/1024) . "KB → " . round($webpSize/1024) . "KB (-{$percent}%)\n";
            } else {
                echo "  ERROR: Failed to write WebP: " . basename($file) . "\n";
                $errors++;
            }
        } catch (Exception $e) {
            echo "  ERROR: " . basename($file) . " - " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    echo "\n";
}

echo "=== COMPLETE ===\n";
echo "Converted: {$converted}\n";
echo "Skipped (already exist): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "Total space saved: " . round($totalSaved / 1024 / 1024, 2) . " MB\n";
