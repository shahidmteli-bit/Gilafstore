<?php
/**
 * Dynamic Category Sitemap
 * - Pulls all categories from DB
 * - Links to shop.php?category=ID filter pages
 * - 24-hour file cache for performance
 */

// --- 24-hour cache layer ---
$cacheFile = __DIR__ . '/cache/category-sitemap-cache.xml';
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
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

try {
    $categories = db_fetch_all("SELECT id, name, slug, updated_at, created_at FROM categories ORDER BY name ASC");

    foreach ($categories as $cat) {
        // Only include slug-based canonical URLs — skip categories without slug
        if (empty($cat['slug'])) {
            continue;
        }
        $loc = htmlspecialchars($baseUrl . '/category/' . $cat['slug']);
        $lastmod = '';
        if (!empty($cat['updated_at'])) {
            $lastmod = date('Y-m-d', strtotime($cat['updated_at']));
        } elseif (!empty($cat['created_at'])) {
            $lastmod = date('Y-m-d', strtotime($cat['created_at']));
        }
        echo "<url>\n";
        echo "  <loc>{$loc}</loc>\n";
        if ($lastmod) echo "  <lastmod>{$lastmod}</lastmod>\n";
        echo "  <changefreq>weekly</changefreq>\n";
        echo "  <priority>0.70</priority>\n";
        echo "</url>\n";
    }
} catch (Exception $e) {
    // Silently fail — Google will retry
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
