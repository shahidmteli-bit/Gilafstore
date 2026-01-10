<?php
/**
 * DEPLOYMENT ARCHITECTURE DEBUGGER
 * This file helps understand the deployment structure and verify changes
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Debug - Gilaf Store</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            background: #1a1a1a; 
            color: #00ff00; 
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { 
            color: #00ff00; 
            text-align: center; 
            margin-bottom: 30px;
            font-size: 2rem;
            text-shadow: 0 0 10px #00ff00;
        }
        .section { 
            background: #2a2a2a; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-left: 4px solid #00ff00;
            border-radius: 4px;
        }
        .section h2 { 
            color: #ffff00; 
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        .info-row { 
            display: flex; 
            padding: 8px 0; 
            border-bottom: 1px solid #3a3a3a;
        }
        .info-row:last-child { border-bottom: none; }
        .label { 
            flex: 0 0 300px; 
            color: #00aaff; 
            font-weight: bold;
        }
        .value { 
            flex: 1; 
            color: #00ff00;
            word-break: break-all;
        }
        .success { color: #00ff00; font-weight: bold; }
        .warning { color: #ffaa00; font-weight: bold; }
        .error { color: #ff0000; font-weight: bold; }
        .timestamp { 
            text-align: center; 
            color: #00aaff; 
            margin-top: 20px;
            font-size: 1.1rem;
        }
        .file-content {
            background: #1a1a1a;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
        .file-content pre {
            color: #00ff00;
            font-size: 0.9rem;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-left: 10px;
        }
        .badge.success { background: #00ff00; color: #000; }
        .badge.warning { background: #ffaa00; color: #000; }
        .badge.error { background: #ff0000; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 DEPLOYMENT ARCHITECTURE DEBUGGER</h1>
        
        <!-- Current Timestamp -->
        <div class="timestamp">
            Generated: <?php echo date('Y-m-d H:i:s'); ?> (Server Time)
        </div>

        <!-- Section 1: Server Environment -->
        <div class="section">
            <h2>1️⃣ SERVER ENVIRONMENT</h2>
            <div class="info-row">
                <div class="label">Document Root:</div>
                <div class="value"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET'; ?></div>
            </div>
            <div class="info-row">
                <div class="label">Current Script:</div>
                <div class="value"><?php echo $_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET'; ?></div>
            </div>
            <div class="info-row">
                <div class="label">HTTP Host:</div>
                <div class="value"><?php echo $_SERVER['HTTP_HOST'] ?? 'NOT SET'; ?></div>
            </div>
            <div class="info-row">
                <div class="label">Request URI:</div>
                <div class="value"><?php echo $_SERVER['REQUEST_URI'] ?? 'NOT SET'; ?></div>
            </div>
            <div class="info-row">
                <div class="label">PHP Version:</div>
                <div class="value"><?php echo PHP_VERSION; ?></div>
            </div>
            <div class="info-row">
                <div class="label">Server Software:</div>
                <div class="value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'NOT SET'; ?></div>
            </div>
        </div>

        <!-- Section 2: File System Paths -->
        <div class="section">
            <h2>2️⃣ FILE SYSTEM PATHS</h2>
            <div class="info-row">
                <div class="label">__FILE__:</div>
                <div class="value"><?php echo __FILE__; ?></div>
            </div>
            <div class="info-row">
                <div class="label">__DIR__:</div>
                <div class="value"><?php echo __DIR__; ?></div>
            </div>
            <div class="info-row">
                <div class="label">Realpath (this file):</div>
                <div class="value"><?php echo realpath(__FILE__); ?></div>
            </div>
            <div class="info-row">
                <div class="label">Parent Directory:</div>
                <div class="value"><?php echo realpath(__DIR__ . '/..'); ?></div>
            </div>
        </div>

        <!-- Section 3: Deployment Structure Detection -->
        <div class="section">
            <h2>3️⃣ DEPLOYMENT STRUCTURE DETECTION</h2>
            <?php
            $currentPath = __DIR__;
            $isInReleasesLive = strpos($currentPath, 'releases/live') !== false || strpos($currentPath, 'releases\\live') !== false;
            $isInPublicHtml = strpos($currentPath, 'public_html') !== false;
            ?>
            <div class="info-row">
                <div class="label">Current Location:</div>
                <div class="value">
                    <?php 
                    if ($isInReleasesLive) {
                        echo '<span class="success">✅ /releases/live/</span>';
                    } elseif ($isInPublicHtml) {
                        echo '<span class="warning">⚠️ /public_html/ (loader area)</span>';
                    } else {
                        echo '<span class="error">❌ Unknown location</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="info-row">
                <div class="label">Deployment Type:</div>
                <div class="value">
                    <?php
                    if ($isInReleasesLive) {
                        echo '<span class="success">Release-based deployment (CORRECT)</span>';
                    } else {
                        echo '<span class="warning">Direct deployment or local</span>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Section 4: Key Files Check -->
        <div class="section">
            <h2>4️⃣ KEY FILES VERIFICATION</h2>
            <?php
            $filesToCheck = [
                'index.php' => __DIR__ . '/index.php',
                'admin/health_dashboard.php' => __DIR__ . '/admin/health_dashboard.php',
                'includes/db_connect.php' => __DIR__ . '/includes/db_connect.php',
                'includes/health_monitor.php' => __DIR__ . '/includes/health_monitor.php',
                'assets/css/new-design.css' => __DIR__ . '/assets/css/new-design.css',
            ];

            foreach ($filesToCheck as $name => $path) {
                $exists = file_exists($path);
                $modified = $exists ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A';
                $size = $exists ? number_format(filesize($path)) : 'N/A';
                
                echo '<div class="info-row">';
                echo '<div class="label">' . htmlspecialchars($name) . ':</div>';
                echo '<div class="value">';
                if ($exists) {
                    echo '<span class="success">✅ EXISTS</span>';
                    echo '<span class="badge success">Modified: ' . $modified . '</span>';
                    echo '<span class="badge warning">Size: ' . $size . ' bytes</span>';
                } else {
                    echo '<span class="error">❌ NOT FOUND</span>';
                }
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- Section 5: index.php Content Check -->
        <div class="section">
            <h2>5️⃣ INDEX.PHP CONTENT VERIFICATION</h2>
            <?php
            $indexPath = __DIR__ . '/index.php';
            if (file_exists($indexPath)) {
                $indexContent = file_get_contents($indexPath);
                $hasTestBanner = strpos($indexContent, 'DEPLOYMENT TEST') !== false;
                $hasGreenBanner = strpos($indexContent, '#00ff00') !== false;
                $hasTimestamp = strpos($indexContent, "date('H:i:s')") !== false;
                
                echo '<div class="info-row">';
                echo '<div class="label">Test Banner Present:</div>';
                echo '<div class="value">';
                if ($hasTestBanner) {
                    echo '<span class="success">✅ YES - Test banner code found</span>';
                } else {
                    echo '<span class="error">❌ NO - Test banner NOT found</span>';
                }
                echo '</div>';
                echo '</div>';

                echo '<div class="info-row">';
                echo '<div class="label">Green Banner (#00ff00):</div>';
                echo '<div class="value">';
                if ($hasGreenBanner) {
                    echo '<span class="success">✅ YES</span>';
                } else {
                    echo '<span class="error">❌ NO</span>';
                }
                echo '</div>';
                echo '</div>';

                echo '<div class="info-row">';
                echo '<div class="label">Timestamp Function:</div>';
                echo '<div class="value">';
                if ($hasTimestamp) {
                    echo '<span class="success">✅ YES</span>';
                } else {
                    echo '<span class="error">❌ NO</span>';
                }
                echo '</div>';
                echo '</div>';

                echo '<div class="info-row">';
                echo '<div class="label">File Last Modified:</div>';
                echo '<div class="value">';
                echo '<span class="success">' . date('Y-m-d H:i:s', filemtime($indexPath)) . '</span>';
                echo '</div>';
                echo '</div>';

                // Show first 50 lines of index.php
                echo '<div class="file-content">';
                echo '<strong>First 50 lines of index.php:</strong>';
                echo '<pre>';
                $lines = explode("\n", $indexContent);
                $lineCount = 1;
                foreach (array_slice($lines, 0, 50) as $line) {
                    echo sprintf("%3d: %s\n", $lineCount++, htmlspecialchars($line));
                }
                echo '</pre>';
                echo '</div>';
            } else {
                echo '<div class="error">❌ index.php NOT FOUND</div>';
            }
            ?>
        </div>

        <!-- Section 6: Loader Files Check (if applicable) -->
        <div class="section">
            <h2>6️⃣ LOADER FILES CHECK</h2>
            <?php
            if ($isInReleasesLive) {
                // We're in releases/live, check if loader exists in parent
                $loaderPath = realpath(__DIR__ . '/../../index.php');
                $adminLoaderPath = realpath(__DIR__ . '/../../admin/index.php');
                
                echo '<div class="info-row">';
                echo '<div class="label">Main Loader (/public_html/index.php):</div>';
                echo '<div class="value">';
                if ($loaderPath && file_exists($loaderPath)) {
                    echo '<span class="success">✅ EXISTS</span>';
                    $loaderContent = file_get_contents($loaderPath);
                    if (strpos($loaderContent, 'releases/live') !== false) {
                        echo '<span class="badge success">Points to releases/live</span>';
                    } else {
                        echo '<span class="badge error">Does NOT point to releases/live</span>';
                    }
                } else {
                    echo '<span class="error">❌ NOT FOUND - This is why the site may not work!</span>';
                }
                echo '</div>';
                echo '</div>';

                echo '<div class="info-row">';
                echo '<div class="label">Admin Loader (/public_html/admin/index.php):</div>';
                echo '<div class="value">';
                if ($adminLoaderPath && file_exists($adminLoaderPath)) {
                    echo '<span class="success">✅ EXISTS</span>';
                } else {
                    echo '<span class="error">❌ NOT FOUND</span>';
                }
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="warning">⚠️ Not in releases/live structure - loader check skipped</div>';
            }
            ?>
        </div>

        <!-- Section 7: Git Information (if available) -->
        <div class="section">
            <h2>7️⃣ GIT DEPLOYMENT INFO</h2>
            <?php
            $gitHeadPath = __DIR__ . '/.git/HEAD';
            if (file_exists($gitHeadPath)) {
                $gitHead = trim(file_get_contents($gitHeadPath));
                echo '<div class="info-row">';
                echo '<div class="label">Git HEAD:</div>';
                echo '<div class="value"><span class="success">' . htmlspecialchars($gitHead) . '</span></div>';
                echo '</div>';

                // Try to get last commit hash
                if (strpos($gitHead, 'ref:') === 0) {
                    $refPath = __DIR__ . '/.git/' . substr($gitHead, 5);
                    if (file_exists($refPath)) {
                        $commitHash = trim(file_get_contents($refPath));
                        echo '<div class="info-row">';
                        echo '<div class="label">Last Commit Hash:</div>';
                        echo '<div class="value"><span class="success">' . htmlspecialchars($commitHash) . '</span></div>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<div class="warning">⚠️ .git directory not found (normal for production)</div>';
            }
            ?>
        </div>

        <!-- Section 8: Recommendations -->
        <div class="section">
            <h2>8️⃣ DIAGNOSIS & RECOMMENDATIONS</h2>
            <?php
            $issues = [];
            $recommendations = [];

            if (!$isInReleasesLive && strpos($currentPath, 'xampp') === false) {
                $issues[] = "Not in /releases/live/ directory";
                $recommendations[] = "Verify Hostinger is deploying to /public_html/releases/live/";
            }

            if ($isInReleasesLive && (!isset($loaderPath) || !file_exists($loaderPath))) {
                $issues[] = "Loader file missing in /public_html/";
                $recommendations[] = "Create /public_html/index.php with: require __DIR__ . '/releases/live/index.php';";
            }

            if (isset($hasTestBanner) && !$hasTestBanner) {
                $issues[] = "Test banner code not found in index.php";
                $recommendations[] = "Git deployment may not have completed or wrong file is being served";
            }

            if (empty($issues)) {
                echo '<div class="success">✅ No issues detected! Deployment structure looks correct.</div>';
            } else {
                echo '<div class="error"><strong>Issues Found:</strong></div>';
                echo '<ul style="margin-left: 20px; margin-top: 10px;">';
                foreach ($issues as $issue) {
                    echo '<li style="color: #ff0000; margin: 5px 0;">❌ ' . htmlspecialchars($issue) . '</li>';
                }
                echo '</ul>';

                echo '<div class="warning" style="margin-top: 15px;"><strong>Recommendations:</strong></div>';
                echo '<ul style="margin-left: 20px; margin-top: 10px;">';
                foreach ($recommendations as $rec) {
                    echo '<li style="color: #ffaa00; margin: 5px 0;">💡 ' . htmlspecialchars($rec) . '</li>';
                }
                echo '</ul>';
            }
            ?>
        </div>

        <!-- Footer -->
        <div class="timestamp" style="margin-top: 30px; border-top: 2px solid #00ff00; padding-top: 20px;">
            <strong>Gilaf Store Deployment Debugger v1.0</strong><br>
            Access this file at: <span style="color: #ffaa00;"><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></span>
        </div>
    </div>
</body>
</html>
