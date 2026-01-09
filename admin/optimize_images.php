<?php
/**
 * Image Optimization - Working Version
 * Shows clear feedback every time
 */

session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$adminPage = 'optimize_images';

// Initialize variables
$results = [];
$totalSaved = 0;
$processedCount = 0;
$totalFiles = 0;
$skippedFiles = 0;
$maxImages = 100;

// Check GD library
$gdError = false;
if (!extension_loaded('gd')) {
    $gdError = 'GD library is not enabled in PHP.';
} elseif (!function_exists('imagewebp')) {
    $gdError = 'WebP support is not available in your GD library.';
}

// Process form submission
$showResults = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize'])) {
    $showResults = true;
    
    if (!$gdError) {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        
        $directories = [
            __DIR__ . '/../assets/Images/products',
            __DIR__ . '/../uploads/blog',
            __DIR__ . '/../assets/Images'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir) || $processedCount >= $maxImages) continue;
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($files as $file) {
                if ($processedCount >= $maxImages) break;
                if (!$file->isFile()) continue;
                
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;
                
                $totalFiles++;
                $sourcePath = $file->getPathname();
                $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $sourcePath);
                
                // Skip if WebP already exists
                if (file_exists($webpPath)) {
                    $skippedFiles++;
                    continue;
                }
                
                $processedCount++;
                $originalSize = filesize($sourcePath);
                
                // Load image
                if ($ext === 'png') {
                    $image = @imagecreatefrompng($sourcePath);
                    if ($image && !imageistruecolor($image)) {
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
                } else {
                    $image = @imagecreatefromjpeg($sourcePath);
                }
                
                if ($image) {
                    $success = @imagewebp($image, $webpPath, 80);
                    imagedestroy($image);
                    
                    if ($success && file_exists($webpPath)) {
                        $webpSize = filesize($webpPath);
                        $saved = $originalSize - $webpSize;
                        $totalSaved += $saved;
                        
                        $results[] = [
                            'file' => basename($sourcePath),
                            'original' => $originalSize,
                            'webp' => $webpSize,
                            'saved' => $saved,
                            'percent' => round(($saved / $originalSize) * 100, 1)
                        ];
                    }
                }
            }
        }
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Image Optimization</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Image Optimization</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-image me-1"></i>
            WebP Conversion & Optimization
        </div>
        <div class="card-body">
            
            <!-- RESULTS SECTION - ALWAYS VISIBLE AFTER SUBMISSION -->
            <?php if ($showResults): ?>
                <div class="alert alert-primary mb-4">
                    <h5><i class="fas fa-check-circle"></i> Optimization Process Completed!</h5>
                    <p class="mb-0">The optimization script has finished executing.</p>
                </div>
                
                <?php if ($gdError): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle"></i> GD Library Error</h5>
                        <p class="mb-0"><?= htmlspecialchars($gdError); ?></p>
                    </div>
                <?php elseif (!empty($results)): ?>
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle"></i> Images Optimized Successfully!</h5>
                        <ul class="mb-0">
                            <li><strong>Images Optimized:</strong> <?= count($results); ?></li>
                            <li><strong>Total Space Saved:</strong> <?= formatBytes($totalSaved); ?></li>
                            <li><strong>Total Images Found:</strong> <?= $totalFiles; ?></li>
                            <li><strong>Already Converted (Skipped):</strong> <?= $skippedFiles; ?></li>
                        </ul>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Original Size</th>
                                    <th>WebP Size</th>
                                    <th>Saved</th>
                                    <th>Reduction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                <tr>
                                    <td><?= htmlspecialchars($result['file']); ?></td>
                                    <td><?= formatBytes($result['original']); ?></td>
                                    <td><?= formatBytes($result['webp']); ?></td>
                                    <td class="text-success"><?= formatBytes($result['saved']); ?></td>
                                    <td><span class="badge bg-success"><?= $result['percent']; ?>%</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> All Images Already Optimized</h5>
                        <p class="mb-0">
                            <?php if ($totalFiles > 0): ?>
                                All <?= $totalFiles; ?> images have already been converted to WebP format.<br>
                                <strong>Already Converted:</strong> <?= $skippedFiles; ?> images
                            <?php else: ?>
                                No JPG or PNG images found in the directories.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <hr class="my-4">
            <?php endif; ?>
            
            <!-- INFORMATION SECTION -->
            <?php if (!$gdError): ?>
            <div class="alert alert-info">
                <h6><strong>What this does:</strong></h6>
                <ul class="mb-0">
                    <li>Converts all JPG and PNG images to WebP format (80% quality)</li>
                    <li>Creates WebP versions alongside original files (no deletion)</li>
                    <li>Reduces file sizes by 25-50% without visible quality loss</li>
                    <li>Improves page load speed and Core Web Vitals</li>
                    <li><strong>Batch Processing:</strong> Processes up to 100 images per run</li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> Processing may take 1-2 minutes depending on image count and size.
            </div>
            <?php endif; ?>

            <!-- FORM -->
            <?php if (!$gdError): ?>
            <form method="POST">
                <button type="submit" name="optimize" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-compress-alt"></i> Start Optimization
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-triangle"></i> GD Library Not Available</h5>
                <p><?= htmlspecialchars($gdError); ?></p>
                <hr>
                <h6>How to Enable GD Library in XAMPP:</h6>
                <ol>
                    <li>Open <code>C:\xampp\php\php.ini</code></li>
                    <li>Find: <code>;extension=gd</code></li>
                    <li>Remove semicolon: <code>extension=gd</code></li>
                    <li>Save and restart Apache</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
