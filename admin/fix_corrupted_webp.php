<?php
/**
 * Fix Corrupted WebP Files
 * Re-converts the 2 corrupted 0-byte WebP files
 */

session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$adminPage = 'optimize_images';
include __DIR__ . '/../includes/admin_header.php';

$fixed = [];
$errors = [];

// Files to fix
$corruptedFiles = [
    __DIR__ . '/../assets/Images/products/product_6953e6439d2ef6.58040564.png',
    __DIR__ . '/../assets/Images/products/product_6953e73fb1e574.19674956.png'
];

foreach ($corruptedFiles as $sourcePath) {
    if (!file_exists($sourcePath)) {
        $errors[] = "Source file not found: " . basename($sourcePath);
        continue;
    }
    
    $webpPath = preg_replace('/\.png$/i', '.webp', $sourcePath);
    
    // Delete corrupted WebP if exists
    if (file_exists($webpPath)) {
        unlink($webpPath);
    }
    
    // Load PNG image
    $image = @imagecreatefrompng($sourcePath);
    
    if (!$image) {
        $errors[] = "Failed to load: " . basename($sourcePath);
        continue;
    }
    
    // Convert palette to true color if needed
    if (!imageistruecolor($image)) {
        $width = imagesx($image);
        $height = imagesy($image);
        $trueColorImage = imagecreatetruecolor($width, $height);
        
        imagealphablending($trueColorImage, false);
        imagesavealpha($trueColorImage, true);
        $transparent = imagecolorallocatealpha($trueColorImage, 0, 0, 0, 127);
        imagefill($trueColorImage, 0, 0, $transparent);
        imagealphablending($trueColorImage, true);
        imagecopy($trueColorImage, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);
        $image = $trueColorImage;
    }
    
    // Convert to WebP
    $success = @imagewebp($image, $webpPath, 80);
    imagedestroy($image);
    
    if ($success && file_exists($webpPath) && filesize($webpPath) > 0) {
        $fixed[] = [
            'file' => basename($sourcePath),
            'size' => filesize($webpPath)
        ];
    } else {
        $errors[] = "Failed to create WebP: " . basename($sourcePath);
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Fix Corrupted WebP Files</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="optimize_images.php">Image Optimization</a></li>
        <li class="breadcrumb-item active">Fix Corrupted</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-wrench me-1"></i>
            Repair Results
        </div>
        <div class="card-body">
            <?php if (!empty($fixed)): ?>
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle"></i> Successfully Fixed!</h5>
                <ul class="mb-0">
                    <?php foreach ($fixed as $file): ?>
                        <li>
                            <strong><?= htmlspecialchars($file['file']); ?></strong> 
                            - WebP created (<?= round($file['size'] / 1024, 2); ?> KB)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-triangle"></i> Errors</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <a href="optimize_images.php" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Back to Image Optimization
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
