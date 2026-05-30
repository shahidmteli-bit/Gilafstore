<?php
/**
 * Dynamic Blog Sitemap
 * - Pulls all published blogs from DB
 * - 24-hour file cache for performance
 */

// --- 24-hour cache layer ---
$cacheFile = __DIR__ . '/cache/blog-sitemap-cache.xml';
$cacheTTL  = 86400; // 24 hours

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    readfile($cacheFile);
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

$baseUrl = 'https://gilafstore.com';

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

try {
    $blogs = db_fetch_all("
        SELECT slug, updated_at, created_at, publish_date, featured_image, title
        FROM blogs 
        WHERE status = 'published' AND slug IS NOT NULL AND slug != ''
        ORDER BY publish_date DESC
    ");

    foreach ($blogs as $blog) {
        $loc = htmlspecialchars($baseUrl . '/blog/' . $blog['slug']);
        $lastmod = '';
        if (!empty($blog['updated_at'])) {
            $lastmod = date('Y-m-d', strtotime($blog['updated_at']));
        } elseif (!empty($blog['publish_date'])) {
            $lastmod = date('Y-m-d', strtotime($blog['publish_date']));
        } elseif (!empty($blog['created_at'])) {
            $lastmod = date('Y-m-d', strtotime($blog['created_at']));
        }
        echo "<url>\n";
        echo "  <loc>{$loc}</loc>\n";
        if ($lastmod) echo "  <lastmod>{$lastmod}</lastmod>\n";
        echo "  <changefreq>weekly</changefreq>\n";
        echo "  <priority>0.70</priority>\n";
        if (!empty($blog['featured_image'])) {
            $imgLoc   = htmlspecialchars($baseUrl . '/uploads/blog/' . $blog['featured_image']);
            $imgTitle = htmlspecialchars($blog['title'] ?? '');
            echo "  <image:image>\n";
            echo "    <image:loc>{$imgLoc}</image:loc>\n";
            if ($imgTitle) echo "    <image:title>{$imgTitle}</image:title>\n";
            echo "  </image:image>\n";
        }
        echo "</url>\n";
    }
} catch (Exception $e) {
    // Silently fail
}

echo "</urlset>\n";

$xml = ob_get_clean();

// Write cache
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
@file_put_contents($cacheFile, $xml);

// Output
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo $xml;
