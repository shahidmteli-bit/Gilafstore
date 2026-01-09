<?php
/**
 * Image Optimization Debug & Diagnostic Tool
 * Comprehensive logging and troubleshooting for image optimization issues
 */

session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$adminPage = 'optimize_images';
include __DIR__ . '/../includes/admin_header.php';

// Diagnostic Results
$diagnostics = [
    'system' => [],
    'directories' => [],
    'files' => [],
    'test_processing' => []
];

// 1. System Checks
$diagnostics['system']['php_version'] = phpversion();
$diagnostics['system']['gd_loaded'] = extension_loaded('gd');
$diagnostics['system']['gd_info'] = function_exists('gd_info') ? gd_info() : 'Not available';
$diagnostics['system']['webp_support'] = function_exists('imagewebp');
$diagnostics['system']['memory_limit'] = ini_get('memory_limit');
$diagnostics['system']['max_execution_time'] = ini_get('max_execution_time');
$diagnostics['system']['post_max_size'] = ini_get('post_max_size');
$diagnostics['system']['upload_max_filesize'] = ini_get('upload_max_filesize');

// 2. Directory Checks
$directories = [
    'products' => __DIR__ . '/../assets/Images/products',
    'blog' => __DIR__ . '/../uploads/blog',
    'images' => __DIR__ . '/../assets/Images'
];

foreach ($directories as $name => $path) {
    $diagnostics['directories'][$name] = [
        'path' => $path,
        'exists' => is_dir($path),
        'readable' => is_dir($path) && is_readable($path),
        'writable' => is_dir($path) && is_writable($path),
        'absolute_path' => realpath($path)
    ];
}

// 3. File Scanning
foreach ($directories as $name => $dir) {
    if (!is_dir($dir)) continue;
    
    $fileCount = [
        'jpg' => 0,
        'jpeg' => 0,
        'png' => 0,
        'webp' => 0,
        'other' => 0
    ];
    
    $sampleFiles = [];
    
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            if (!$file->isFile()) continue;
            
            $ext = strtolower($file->getExtension());
            
            if (isset($fileCount[$ext])) {
                $fileCount[$ext]++;
            } else {
                $fileCount['other']++;
            }
            
            // Collect sample files (first 5 of each type)
            if (in_array($ext, ['jpg', 'jpeg', 'png']) && count($sampleFiles) < 5) {
                $sampleFiles[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'ext' => $ext,
                    'webp_exists' => file_exists(preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file->getPathname()))
                ];
            }
        }
    } catch (Exception $e) {
        $diagnostics['files'][$name]['error'] = $e->getMessage();
    }
    
    $diagnostics['files'][$name] = [
        'counts' => $fileCount,
        'samples' => $sampleFiles,
        'total_images' => $fileCount['jpg'] + $fileCount['jpeg'] + $fileCount['png']
    ];
}

// 4. Test Image Processing
if (extension_loaded('gd') && function_exists('imagewebp')) {
    foreach ($diagnostics['files'] as $dirName => $fileData) {
        if (!empty($fileData['samples'])) {
            $testFile = $fileData['samples'][0];
            
            try {
                $ext = strtolower($testFile['ext']);
                
                // Try to load image
                if ($ext === 'png') {
                    $image = @imagecreatefrompng($testFile['path']);
                    if ($image) {
                        // Convert palette images to true color
                        if (!imageistruecolor($image)) {
                            $width = imagesx($image);
                            $height = imagesy($image);
                            $trueColorImage = imagecreatetruecolor($width, $height);
                            
                            // Preserve transparency
                            imagealphablending($trueColorImage, false);
                            imagesavealpha($trueColorImage, true);
                            $transparent = imagecolorallocatealpha($trueColorImage, 0, 0, 0, 127);
                            imagefill($trueColorImage, 0, 0, $transparent);
                            
                            // Copy palette image to true color
                            imagealphablending($trueColorImage, true);
                            imagecopy($trueColorImage, $image, 0, 0, 0, 0, $width, $height);
                            imagedestroy($image);
                            $image = $trueColorImage;
                        }
                    }
                } else {
                    $image = @imagecreatefromjpeg($testFile['path']);
                }
                
                if ($image) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    $isTrueColor = imageistruecolor($image);
                    
                    // Try to create WebP in memory
                    ob_start();
                    $webpSuccess = @imagewebp($image, null, 80);
                    $webpData = ob_get_clean();
                    
                    $diagnostics['test_processing'][$dirName] = [
                        'file' => $testFile['name'],
                        'loaded' => true,
                        'width' => $width,
                        'height' => $height,
                        'is_truecolor' => $isTrueColor,
                        'webp_conversion' => $webpSuccess,
                        'webp_size' => strlen($webpData),
                        'original_size' => $testFile['size']
                    ];
                    
                    imagedestroy($image);
                } else {
                    $diagnostics['test_processing'][$dirName] = [
                        'file' => $testFile['name'],
                        'loaded' => false,
                        'error' => 'Failed to load image'
                    ];
                }
            } catch (Exception $e) {
                $diagnostics['test_processing'][$dirName] = [
                    'file' => $testFile['name'],
                    'error' => $e->getMessage()
                ];
            }
        }
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Image Optimization Diagnostics</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="optimize_images.php">Image Optimization</a></li>
        <li class="breadcrumb-item active">Debug</li>
    </ol>

    <!-- System Information -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-server me-1"></i>
            System Information
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <tr>
                    <th width="300">PHP Version</th>
                    <td><?= $diagnostics['system']['php_version']; ?></td>
                </tr>
                <tr>
                    <th>GD Library Loaded</th>
                    <td>
                        <?php if ($diagnostics['system']['gd_loaded']): ?>
                            <span class="badge bg-success">✓ Yes</span>
                        <?php else: ?>
                            <span class="badge bg-danger">✗ No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>WebP Support</th>
                    <td>
                        <?php if ($diagnostics['system']['webp_support']): ?>
                            <span class="badge bg-success">✓ Available</span>
                        <?php else: ?>
                            <span class="badge bg-danger">✗ Not Available</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Memory Limit</th>
                    <td><?= $diagnostics['system']['memory_limit']; ?></td>
                </tr>
                <tr>
                    <th>Max Execution Time</th>
                    <td><?= $diagnostics['system']['max_execution_time']; ?> seconds</td>
                </tr>
            </table>
            
            <?php if (is_array($diagnostics['system']['gd_info'])): ?>
            <h6 class="mt-3">GD Library Details:</h6>
            <table class="table table-sm table-bordered">
                <?php foreach ($diagnostics['system']['gd_info'] as $key => $value): ?>
                <tr>
                    <th width="300"><?= htmlspecialchars($key); ?></th>
                    <td><?= is_bool($value) ? ($value ? 'Yes' : 'No') : htmlspecialchars($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Directory Status -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <i class="fas fa-folder me-1"></i>
            Directory Status
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Directory</th>
                        <th>Exists</th>
                        <th>Readable</th>
                        <th>Writable</th>
                        <th>Path</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diagnostics['directories'] as $name => $info): ?>
                    <tr>
                        <td><strong><?= ucfirst($name); ?></strong></td>
                        <td>
                            <?php if ($info['exists']): ?>
                                <span class="badge bg-success">✓</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($info['readable']): ?>
                                <span class="badge bg-success">✓</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($info['writable']): ?>
                                <span class="badge bg-success">✓</span>
                            <?php else: ?>
                                <span class="badge bg-warning">✗</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($info['absolute_path'] ?: $info['path']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- File Analysis -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <i class="fas fa-images me-1"></i>
            File Analysis
        </div>
        <div class="card-body">
            <?php foreach ($diagnostics['files'] as $name => $data): ?>
            <h6><?= ucfirst($name); ?> Directory</h6>
            
            <?php if (isset($data['error'])): ?>
                <div class="alert alert-danger">Error: <?= htmlspecialchars($data['error']); ?></div>
            <?php else: ?>
                <table class="table table-sm table-bordered mb-3">
                    <tr>
                        <th>JPG Files</th>
                        <td><?= $data['counts']['jpg']; ?></td>
                        <th>JPEG Files</th>
                        <td><?= $data['counts']['jpeg']; ?></td>
                    </tr>
                    <tr>
                        <th>PNG Files</th>
                        <td><?= $data['counts']['png']; ?></td>
                        <th>WebP Files</th>
                        <td><?= $data['counts']['webp']; ?></td>
                    </tr>
                    <tr>
                        <th colspan="2">Total Images (JPG/PNG)</th>
                        <td colspan="2"><strong><?= $data['total_images']; ?></strong></td>
                    </tr>
                </table>
                
                <?php if (!empty($data['samples'])): ?>
                <h6>Sample Files:</h6>
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>WebP Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['samples'] as $sample): ?>
                        <tr>
                            <td><?= htmlspecialchars($sample['name']); ?></td>
                            <td><?= formatBytes($sample['size']); ?></td>
                            <td><span class="badge bg-secondary"><?= strtoupper($sample['ext']); ?></span></td>
                            <td>
                                <?php if ($sample['webp_exists']): ?>
                                    <span class="badge bg-success">✓ Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">✗ No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
            <hr>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Test Processing Results -->
    <?php if (!empty($diagnostics['test_processing'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-flask me-1"></i>
            Test Image Processing
        </div>
        <div class="card-body">
            <?php foreach ($diagnostics['test_processing'] as $dir => $result): ?>
            <h6><?= ucfirst($dir); ?> Directory Test</h6>
            <table class="table table-bordered mb-3">
                <tr>
                    <th width="200">Test File</th>
                    <td><?= htmlspecialchars($result['file']); ?></td>
                </tr>
                <?php if (isset($result['loaded']) && $result['loaded']): ?>
                <tr>
                    <th>Image Loaded</th>
                    <td><span class="badge bg-success">✓ Success</span></td>
                </tr>
                <tr>
                    <th>Dimensions</th>
                    <td><?= $result['width']; ?> x <?= $result['height']; ?> pixels</td>
                </tr>
                <tr>
                    <th>True Color</th>
                    <td><?= $result['is_truecolor'] ? 'Yes' : 'No (Palette)'; ?></td>
                </tr>
                <tr>
                    <th>WebP Conversion</th>
                    <td>
                        <?php if ($result['webp_conversion']): ?>
                            <span class="badge bg-success">✓ Success</span>
                        <?php else: ?>
                            <span class="badge bg-danger">✗ Failed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Original Size</th>
                    <td><?= formatBytes($result['original_size']); ?></td>
                </tr>
                <tr>
                    <th>WebP Size (in memory)</th>
                    <td><?= formatBytes($result['webp_size']); ?></td>
                </tr>
                <tr>
                    <th>Compression Ratio</th>
                    <td>
                        <?php 
                        $ratio = round((1 - ($result['webp_size'] / $result['original_size'])) * 100, 1);
                        ?>
                        <span class="badge bg-success"><?= $ratio; ?>% smaller</span>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <th>Status</th>
                    <td><span class="badge bg-danger">Failed to load image</span></td>
                </tr>
                <?php if (isset($result['error'])): ?>
                <tr>
                    <th>Error</th>
                    <td class="text-danger"><?= htmlspecialchars($result['error']); ?></td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
            </table>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recommendations -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="fas fa-lightbulb me-1"></i>
            Recommendations & Next Steps
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <?php if (!$diagnostics['system']['gd_loaded']): ?>
                <li class="text-danger"><strong>Critical:</strong> GD library is not enabled. Enable it in php.ini</li>
                <?php endif; ?>
                
                <?php if (!$diagnostics['system']['webp_support']): ?>
                <li class="text-danger"><strong>Critical:</strong> WebP support not available. Upgrade PHP or recompile GD with WebP support</li>
                <?php endif; ?>
                
                <?php 
                $totalImages = 0;
                $totalWebP = 0;
                foreach ($diagnostics['files'] as $data) {
                    if (isset($data['total_images'])) {
                        $totalImages += $data['total_images'];
                        $totalWebP += $data['counts']['webp'];
                    }
                }
                ?>
                
                <?php if ($totalImages === 0): ?>
                <li class="text-warning"><strong>Warning:</strong> No JPG or PNG images found in any directory</li>
                <?php elseif ($totalImages === $totalWebP): ?>
                <li class="text-success"><strong>Success:</strong> All <?= $totalImages; ?> images already have WebP versions</li>
                <?php else: ?>
                <li class="text-info"><strong>Ready:</strong> <?= $totalImages - $totalWebP; ?> images can be optimized</li>
                <?php endif; ?>
                
                <?php foreach ($diagnostics['directories'] as $name => $info): ?>
                    <?php if (!$info['exists']): ?>
                    <li class="text-warning">Directory "<?= $name; ?>" does not exist</li>
                    <?php elseif (!$info['writable']): ?>
                    <li class="text-warning">Directory "<?= $name; ?>" is not writable - WebP files cannot be created</li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="text-center mb-4">
        <a href="optimize_images.php" class="btn btn-primary btn-lg">
            <i class="fas fa-arrow-left"></i> Back to Image Optimization
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
