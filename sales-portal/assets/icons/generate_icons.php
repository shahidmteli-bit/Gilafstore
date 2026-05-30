<?php
/**
 * Generate PWA icons for Gilaf Sales Portal
 * Uses real company logo from company_profile DB table
 * Run this once: http://localhost/Gilaf%20Ecommerce%20website/sales-portal/assets/icons/generate_icons.php
 */

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/company_profile_functions.php';

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$logoSource = null;
$logoPath = null;

// Try to load company logo from DB
$profile = get_company_profile();
$logoFile = $profile['logo_print'] ?: ($profile['logo_web'] ?: '');
if ($logoFile) {
    $logoPath = realpath(__DIR__ . '/../../../assets/images/' . $logoFile);
}
// Fallback: check main site icons
if (!$logoPath || !file_exists($logoPath)) {
    $logoPath = realpath(__DIR__ . '/../../../assets/icons/icon-512x512.png');
}

// Try to load the source image
if ($logoPath && file_exists($logoPath)) {
    $mime = mime_content_type($logoPath);
    if ($mime === 'image/png') {
        $logoSource = imagecreatefrompng($logoPath);
    } elseif ($mime === 'image/jpeg') {
        $logoSource = imagecreatefromjpeg($logoPath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $logoSource = imagecreatefromwebp($logoPath);
    } elseif ($mime === 'image/gif') {
        $logoSource = imagecreatefromgif($logoPath);
    }
}

if ($logoSource) {
    echo "<p style='color:green;font-weight:bold;'>✅ Using company logo: " . htmlspecialchars(basename($logoPath)) . "</p>";
    $origW = imagesx($logoSource);
    $origH = imagesy($logoSource);

    foreach ($sizes as $size) {
        $img = imagecreatetruecolor($size, $size);

        // Transparent background
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);

        // White rounded background
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $white);

        // Calculate logo placement (centered, minimal padding for zoom)
        $padding = max(1, intval($size * 0.02));
        $maxW = $size - ($padding * 2);
        $maxH = $size - ($padding * 2);

        $ratio = min($maxW / $origW, $maxH / $origH);
        $newW = intval($origW * $ratio);
        $newH = intval($origH * $ratio);
        $x = intval(($size - $newW) / 2);
        $y = intval(($size - $newH) / 2);

        imagecopyresampled($img, $logoSource, $x, $y, 0, 0, $newW, $newH, $origW, $origH);

        $filename = __DIR__ . "/icon-{$size}x{$size}.png";
        imagepng($img, $filename, 6);
        imagedestroy($img);
        echo "✅ Created: icon-{$size}x{$size}.png (from logo)<br>";
    }
    imagedestroy($logoSource);
} else {
    // Fallback: generate simple G icons
    echo "<p style='color:orange;font-weight:bold;'>⚠️ No company logo found. Generating placeholder icons.</p>";

    foreach ($sizes as $size) {
        $img = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($img, 26, 60, 52); // #1A3C34
        imagefill($img, 0, 0, $bg);
        $white = imagecolorallocate($img, 255, 255, 255);
        $fontNum = 5;
        $text = 'GS';
        $charWidth = imagefontwidth($fontNum);
        $charHeight = imagefontheight($fontNum);
        $textWidth = $charWidth * strlen($text);
        $x = intval(($size - $textWidth) / 2);
        $y = intval(($size - $charHeight) / 2);
        imagestring($img, $fontNum, $x, $y, $text, $white);
        $filename = __DIR__ . "/icon-{$size}x{$size}.png";
        imagepng($img, $filename);
        imagedestroy($img);
        echo "Created: icon-{$size}x{$size}.png (placeholder)<br>";
    }
}

echo "<br><strong>All icons generated!</strong>";
echo "<br><a href='../../index.php'>Go to Sales Portal</a>";
?>
