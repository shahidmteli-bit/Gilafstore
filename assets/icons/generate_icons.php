<?php
/**
 * Generate PWA icons for Gilaf Store
 * Uses the uploaded company logo from Admin → Company Profile.
 * If no logo is uploaded, shows a message.
 * 
 * Run: http://localhost/Gilaf%20Ecommerce%20website/assets/icons/generate_icons.php
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/company_profile_functions.php';

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Try to find the uploaded company logo (high-res print version preferred)
$profile = get_company_profile();
$logoFile = null;
$imagesDir = realpath(__DIR__ . '/../images') . DIRECTORY_SEPARATOR;

// Prefer print (high-res PNG), then web
foreach (['logo_print', 'logo_web'] as $field) {
    if (!empty($profile[$field])) {
        $candidate = $imagesDir . $profile[$field];
        if (file_exists($candidate)) {
            $logoFile = $candidate;
            break;
        }
    }
}

if (!$logoFile) {
    echo "<strong style='color:red;'>No company logo uploaded yet!</strong><br>";
    echo "Go to <a href='../../admin/company_profile.php'>Admin → Company Profile</a> and upload your Gilaf logo first.<br>";
    echo "Then come back here and run this script again.";
    exit;
}

// Load the source logo
$mime = mime_content_type($logoFile);
$srcImage = null;
switch ($mime) {
    case 'image/png':  $srcImage = imagecreatefrompng($logoFile); break;
    case 'image/jpeg': $srcImage = imagecreatefromjpeg($logoFile); break;
    case 'image/webp': $srcImage = imagecreatefromwebp($logoFile); break;
    default:
        echo "Unsupported logo format: {$mime}. Use PNG, JPG, or WebP.<br>";
        exit;
}

if (!$srcImage) {
    echo "Failed to load logo image.<br>";
    exit;
}

$srcW = imagesx($srcImage);
$srcH = imagesy($srcImage);

echo "<h3>Generating PWA icons from company logo...</h3>";
echo "Source: " . htmlspecialchars(basename($logoFile)) . " ({$srcW}×{$srcH}px)<br><br>";

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    // White background (clean for all contexts)
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);

    // Calculate logo placement — zoom to fill with minimal padding
    $padding = max(1, intval($size * 0.02));
    $availW = $size - ($padding * 2);
    $availH = $size - ($padding * 2);

    // Scale to fill the available area (cover, not contain)
    $scaleW = $availW / $srcW;
    $scaleH = $availH / $srcH;
    $scale = max($scaleW, $scaleH); // zoom to fill

    $drawW = intval($srcW * $scale);
    $drawH = intval($srcH * $scale);

    // Center
    $drawX = intval(($size - $drawW) / 2);
    $drawY = intval(($size - $drawH) / 2);

    imagealphablending($img, true);
    imagecopyresampled($img, $srcImage, $drawX, $drawY, 0, 0, $drawW, $drawH, $srcW, $srcH);

    $filename = __DIR__ . "/icon-{$size}x{$size}.png";
    imagepng($img, $filename, 2); // quality 2 (good balance)
    imagedestroy($img);
    echo "✅ Created: icon-{$size}x{$size}.png ({$size}×{$size})<br>";
}

imagedestroy($srcImage);

echo "<br><strong style='color:green;'>All Gilaf Store PWA icons regenerated from company logo!</strong>";
echo "<br>Clear browser cache to see new icons.";
echo "<br><a href='../../index.php'>← Go to Gilaf Store</a>";
?>
