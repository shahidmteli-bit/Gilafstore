<?php
/**
 * Lightweight image thumbnail generator
 * Generates WebP thumbnails on first request, caches them for future requests.
 * 
 * Usage: thumb_url('images/products/photo.jpg', 300)
 * Returns: URL to a 300px-wide WebP thumbnail
 */

function thumb_url($imagePath, $width = 300) {
    // Build cache filename
    $hash = md5($imagePath . '_' . $width);
    $ext = 'webp';
    $cacheDir = 'assets/cache/thumbs/';
    $cachePath = $cacheDir . $hash . '.' . $ext;
    $fullCachePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $cachePath;
    $fullSourcePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/assets/' . $imagePath;
    
    // Return cached version if exists and source hasn't changed
    if (file_exists($fullCachePath) && file_exists($fullSourcePath) && filemtime($fullCachePath) >= filemtime($fullSourcePath)) {
        return base_url($cachePath);
    }
    
    // Ensure cache directory exists
    $cacheAbsDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $cacheDir;
    if (!is_dir($cacheAbsDir)) {
        @mkdir($cacheAbsDir, 0755, true);
    }
    
    // Generate thumbnail
    if (!file_exists($fullSourcePath)) {
        return base_url('assets/' . $imagePath); // Fallback to original
    }
    
    $info = @getimagesize($fullSourcePath);
    if (!$info) {
        return base_url('assets/' . $imagePath);
    }
    
    $srcImage = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $srcImage = @imagecreatefromjpeg($fullSourcePath); break;
        case IMAGETYPE_PNG:  $srcImage = @imagecreatefrompng($fullSourcePath); break;
        case IMAGETYPE_WEBP: $srcImage = @imagecreatefromwebp($fullSourcePath); break;
    }
    
    if (!$srcImage) {
        return base_url('assets/' . $imagePath);
    }
    
    $ow = imagesx($srcImage);
    $oh = imagesy($srcImage);
    
    // Only resize if source is larger
    if ($ow <= $width) {
        imagedestroy($srcImage);
        return base_url('assets/' . $imagePath);
    }
    
    $nw = $width;
    $nh = intval($oh * ($width / $ow));
    
    $resized = imagecreatetruecolor($nw, $nh);
    
    // Preserve transparency for PNG
    if ($info[2] === IMAGETYPE_PNG) {
        imagepalettetotruecolor($resized);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }
    
    imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
    imagedestroy($srcImage);
    
    // Save as WebP (quality 88 = crisp for desktop/retina)
    imagewebp($resized, $fullCachePath, 88);
    imagedestroy($resized);
    
    return base_url($cachePath);
}
