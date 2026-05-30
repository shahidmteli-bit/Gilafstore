<?php
/**
 * Google Merchant Center Product Feed (RSS 2.0 / XML)
 * - Generates a feed compatible with Google Shopping
 * - Includes title, price, images, availability, brand, category
 * - 24-hour file cache for performance
 * 
 * Submit this URL to Google Merchant Center:
 * https://gilafstore.com/merchant-feed.php
 */

// --- 24-hour cache layer ---
$cacheFile = __DIR__ . '/cache/merchant-feed-cache.xml';
$cacheTTL  = 86400; // 24 hours

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('Content-Type: application/xml; charset=utf-8');
    readfile($cacheFile);
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

$baseUrl = 'https://gilafstore.com';
$imgBase = $baseUrl . '/assets/images/products/';

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
<title>Gilaf Store — Premium Heritage Foods</title>
<link><?= $baseUrl; ?></link>
<description>Premium Kashmiri saffron, organic honey, and hand-selected spices from Gilaf Store.</description>
<?php
try {
    // Fetch products with category and default weight price
    $products = db_fetch_all("
        SELECT p.id, p.name, p.slug, p.description, p.price, p.image,
               p.image_1, p.image_2, p.image_3, p.image_4,
               c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        ORDER BY p.id DESC
    ");

    foreach ($products as $p) {
        $productUrl = !empty($p['slug']) ? $baseUrl . '/product/' . $p['slug'] : $baseUrl . '/product.php?id=' . $p['id'];

        // Get best image
        $mainImage = '';
        foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image'] as $field) {
            if (!empty($p[$field])) {
                $mainImage = $imgBase . ltrim($p[$field], '/');
                break;
            }
        }

        // Get additional images
        $additionalImages = [];
        foreach (['image_2', 'image_3', 'image_4'] as $field) {
            if (!empty($p[$field])) {
                $additionalImages[] = $imgBase . ltrim($p[$field], '/');
            }
        }

        // Get default weight price if available
        $weightPrice = db_fetch("
            SELECT price FROM product_weights
            WHERE product_id = ? AND is_default = 1
            LIMIT 1
        ", [$p['id']]);
        $price = $weightPrice ? $weightPrice['price'] : $p['price'];

        $title = htmlspecialchars($p['name']);
        $desc = htmlspecialchars(strip_tags($p['description'] ?? $p['name'] . ' — Premium quality from Gilaf Store'));
        $category = htmlspecialchars($p['category_name'] ?? 'Food & Beverages');
        $formattedPrice = number_format((float)$price, 2, '.', '') . ' INR';
?>
<item>
<g:id><?= $p['id']; ?></g:id>
<title><?= $title; ?></title>
<description><?= $desc; ?></description>
<link><?= htmlspecialchars($productUrl); ?></link>
<g:image_link><?= htmlspecialchars($mainImage); ?></g:image_link>
<?php foreach ($additionalImages as $addImg): ?>
<g:additional_image_link><?= htmlspecialchars($addImg); ?></g:additional_image_link>
<?php endforeach; ?>
<g:price><?= $formattedPrice; ?></g:price>
<g:availability>in_stock</g:availability>
<g:condition>new</g:condition>
<g:brand>Gilaf Store</g:brand>
<g:product_type><?= $category; ?></g:product_type>
<g:shipping>
<g:country>IN</g:country>
<g:service>Standard</g:service>
<g:price>0 INR</g:price>
</g:shipping>
<g:shipping_label>free_shipping</g:shipping_label>
<g:transit_time_label>standard</g:transit_time_label>
<g:min_handling_time>1</g:min_handling_time>
<g:max_handling_time>2</g:max_handling_time>
<g:return_policy_label>standard_return</g:return_policy_label>
</item>
<?php
    }
} catch (Exception $e) {
    // Silently fail
}
?>
</channel>
</rss>
<?php

$xml = ob_get_clean();

// Write cache
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
@file_put_contents($cacheFile, $xml);

// Output
header('Content-Type: application/xml; charset=utf-8');
echo $xml;
