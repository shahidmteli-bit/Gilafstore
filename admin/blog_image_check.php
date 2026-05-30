<?php
/**
 * Blog Image Diagnostic Tool - Temporary
 * Check what's in the DB and on disk for blog featured images
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

require_admin();

$db = get_db_connection();

echo "<h2>Blog Image Diagnostic</h2>";
echo "<style>body{font-family:monospace;padding:20px;} table{border-collapse:collapse;width:100%;} td,th{border:1px solid #ccc;padding:8px;text-align:left;} .empty{color:red;font-weight:bold;} .ok{color:green;}</style>";

// 1. Check all blogs and their featured_image values
echo "<h3>1. All Blogs - featured_image column</h3>";
$blogs = $db->query("SELECT id, title, slug, featured_image, status FROM blogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>ID</th><th>Title</th><th>Slug</th><th>featured_image (DB)</th><th>File Exists?</th><th>Status</th></tr>";
foreach ($blogs as $b) {
    $img = $b['featured_image'];
    $fileExists = '';
    if (!empty($img)) {
        $path = __DIR__ . '/../uploads/blog/' . $img;
        $fileExists = file_exists($path) ? '<span class="ok">YES</span>' : '<span class="empty">NO - file missing!</span>';
    }
    $imgClass = empty($img) ? 'empty' : 'ok';
    echo "<tr><td>{$b['id']}</td><td>{$b['title']}</td><td>{$b['slug']}</td><td class='{$imgClass}'>" . ($img ?: 'EMPTY') . "</td><td>{$fileExists}</td><td>{$b['status']}</td></tr>";
}
echo "</table>";

// 2. Check uploads/blog/ directory
echo "<h3>2. Files in uploads/blog/</h3>";
$uploadDir = __DIR__ . '/../uploads/blog/';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    $files = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
    if (empty($files)) {
        echo "<p class='empty'>Directory is EMPTY - no images found!</p>";
    } else {
        echo "<p>Found " . count($files) . " files:</p><ul>";
        foreach ($files as $f) {
            $size = round(filesize($uploadDir . $f) / 1024) . ' KB';
            $time = date('Y-m-d H:i:s', filemtime($uploadDir . $f));
            echo "<li><b>{$f}</b> ({$size}, modified: {$time})</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p class='empty'>uploads/blog/ directory does NOT exist!</p>";
}

// 3. Check debug log
echo "<h3>3. Debug Log (blog_image_debug.log)</h3>";
$logFile = __DIR__ . '/blog_image_debug.log';
if (file_exists($logFile)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
} else {
    echo "<p>No debug log found.</p>";
}

// 4. Auto-fix: Try to match AI images to blogs with empty featured_image
if (isset($_GET['fix']) && $_GET['fix'] === '1') {
    echo "<h3>4. Auto-Fix Results</h3>";
    $fixed = 0;
    foreach ($blogs as $b) {
        if (!empty($b['featured_image'])) continue;
        $slug = $b['slug'];
        if (empty($slug)) continue;
        
        $matches = glob($uploadDir . "ai-{$slug}-*.jpg");
        if (empty($matches)) $matches = glob($uploadDir . "ai-{$slug}-*.webp");
        if (empty($matches)) {
            $partial = substr($slug, 0, 20);
            $matches = glob($uploadDir . "ai-{$partial}*.jpg");
        }
        if (!empty($matches)) {
            usort($matches, function($a, $b) { return filemtime($b) - filemtime($a); });
            $filename = basename($matches[0]);
            $db->prepare("UPDATE blogs SET featured_image = ? WHERE id = ?")->execute([$filename, $b['id']]);
            echo "<p class='ok'>Fixed blog #{$b['id']} ({$b['title']}): set featured_image = {$filename}</p>";
            $fixed++;
        }
    }
    if ($fixed === 0) echo "<p>No blogs could be auto-fixed (no matching images found on disk).</p>";
} else {
    echo "<p><a href='?fix=1' style='background:#22c55e;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;'>Run Auto-Fix</a></p>";
}
