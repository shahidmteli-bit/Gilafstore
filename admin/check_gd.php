<?php
/**
 * Quick GD Library Status Checker
 * Use this to verify if GD library is properly enabled
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminPage = 'optimize_images';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">GD Library Status Check</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="optimize_images.php">Image Optimization</a></li>
        <li class="breadcrumb-item active">GD Status</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>
            PHP GD Library Information
        </div>
        <div class="card-body">
            <?php
            $gdLoaded = extension_loaded('gd');
            $gdInfo = function_exists('gd_info') ? gd_info() : [];
            $webpSupport = function_exists('imagewebp');
            ?>

            <div class="alert alert-<?= $gdLoaded && $webpSupport ? 'success' : 'danger'; ?>">
                <h5>
                    <i class="fas fa-<?= $gdLoaded && $webpSupport ? 'check-circle' : 'times-circle'; ?>"></i>
                    <?= $gdLoaded && $webpSupport ? 'GD Library is Ready!' : 'GD Library Issue Detected'; ?>
                </h5>
            </div>

            <table class="table table-bordered">
                <tr>
                    <th width="300">GD Extension Loaded</th>
                    <td>
                        <?php if ($gdLoaded): ?>
                            <span class="badge bg-success">✓ Yes</span>
                        <?php else: ?>
                            <span class="badge bg-danger">✗ No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>WebP Support</th>
                    <td>
                        <?php if ($webpSupport): ?>
                            <span class="badge bg-success">✓ Available</span>
                        <?php else: ?>
                            <span class="badge bg-danger">✗ Not Available</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>PHP Version</th>
                    <td><?= phpversion(); ?></td>
                </tr>
                <?php if (!empty($gdInfo)): ?>
                    <?php foreach ($gdInfo as $key => $value): ?>
                    <tr>
                        <th><?= htmlspecialchars($key); ?></th>
                        <td><?= is_bool($value) ? ($value ? 'Yes' : 'No') : htmlspecialchars($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>

            <?php if (!$gdLoaded || !$webpSupport): ?>
            <div class="alert alert-warning mt-3">
                <h6><i class="fas fa-wrench"></i> How to Fix:</h6>
                <ol>
                    <li>Open XAMPP Control Panel</li>
                    <li>Click "Config" button next to Apache</li>
                    <li>Select "PHP (php.ini)"</li>
                    <li>Find the line: <code>;extension=gd</code></li>
                    <li>Remove the semicolon: <code>extension=gd</code></li>
                    <li>Save and close the file</li>
                    <li>Click "Stop" then "Start" for Apache</li>
                    <li>Refresh this page to verify</li>
                </ol>
            </div>
            <?php endif; ?>

            <a href="optimize_images.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Image Optimization
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
