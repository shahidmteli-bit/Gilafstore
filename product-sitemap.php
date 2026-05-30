<?php
/**
 * Dynamic Product Sitemap with Image Support
 * - Pulls only active products from DB
 * - Includes image sitemap tags (Google Images indexing)
 * - 24-hour file cache for performance (handles 10K+ products)
 * - Priority based on product recency
 */

// --- 24-hour cache layer ---
$cacheFile = __DIR__ . '/cache/product-sitemap-cache.xml';
$cacheTTL  = 86400; // 24 hours

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    readfile($cacheFile);
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

$baseUrl = 'https://gilafstore.com';
$imgBase = $baseUrl . '/assets/images/products/';

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

try {
    $products = db_fetch_all("
        SELECT id, slug, name, image, image_1, image_2, image_3, image_4, created_at, updated_at
        FROM products
        ORDER BY id DESC
    ");

    $totalProducts = count($products);

    foreach ($products as $index => $p) {
        // Only include slug-based canonical URLs — skip products with no slug
        // (old ?id= URLs are 301-redirected to slugs and must NOT appear in sitemap)
        if (empty($p['slug'])) {
            continue;
        }
        $loc = htmlspecialchars($baseUrl . '/product/' . $p['slug']);

        // Priority: newest 20% get 0.90, next 30% get 0.80, rest get 0.70
        $position = $index / max($totalProducts, 1);
        if ($position < 0.2) {
            $priority = '0.90';
        } elseif ($position < 0.5) {
            $priority = '0.80';
        } else {
            $priority = '0.70';
        }

        // Lastmod: prefer updated_at, fallback to created_at
        $lastmod = '';
        if (!empty($p['updated_at'])) {
            $lastmod = date('Y-m-d', strtotime($p['updated_at']));
        } elseif (!empty($p['created_at'])) {
            $lastmod = date('Y-m-d', strtotime($p['created_at']));
        }

        echo "<url>\n";
        echo "  <loc>{$loc}</loc>\n";
        if ($lastmod) {
            echo "  <lastmod>{$lastmod}</lastmod>\n";
        }
        echo "  <changefreq>weekly</changefreq>\n";
        echo "  <priority>{$priority}</priority>\n";

        // Image sitemap tags — collect all product images
        $images = [];
        foreach (['image_1', 'image_2', 'image_3', 'image_4'] as $field) {
            if (!empty($p[$field])) {
                $images[] = $p[$field];
            }
        }
        if (empty($images) && !empty($p['image'])) {
            $images[] = $p['image'];
        }

        foreach ($images as $img) {
            $imgUrl = htmlspecialchars($imgBase . ltrim($img, '/'));
            $imgTitle = htmlspecialchars($p['name']);
            echo "  <image:image>\n";
            echo "    <image:loc>{$imgUrl}</image:loc>\n";
            echo "    <image:title>{$imgTitle}</image:title>\n";
            echo "  </image:image>\n";
        }

        echo "</url>\n";
    }
} catch (Exception $e) {
    // Silently fail — Google will retry on next crawl
}

echo "</urlset>\n";

$xml = ob_get_clean();

// Write cache file
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
@file_put_contents($cacheFile, $xml);

// Output
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo $xml;
