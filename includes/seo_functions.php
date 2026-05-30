<?php
/**
 * SEO Functions Library — Gilaf Store
 * 
 * Provides slug generation, meta tag helpers, schema builders,
 * and pricing engine for SEO-accurate JSON-LD output.
 * 
 * All functions are designed for automation: new products get
 * correct SEO output without manual intervention.
 */

// ============================================================
// PRODUCT URL HELPER (used across all templates)
// ============================================================

/**
 * Get the frontend URL for a product.
 * Uses slug if available, falls back to ?id= URL.
 * Safe to call even before migration (graceful fallback).
 */
function product_url(array $product): string
{
    if (!empty($product['slug'])) {
        return base_url('product/' . $product['slug']);
    }
    return base_url('product.php?id=' . (int)$product['id']);
}

/**
 * Get the frontend URL for a category.
 */
function category_url(array $category): string
{
    if (!empty($category['slug'])) {
        return base_url('category/' . $category['slug']);
    }
    return base_url('shop.php?category=' . (int)$category['id']);
}

// ============================================================
// SLUG GENERATION
// ============================================================

/**
 * Generate a URL-friendly slug from a string.
 * Rules: lowercase, hyphens, no special chars, max ~60 chars, unique.
 *
 * @param string $text     The input string (e.g. product name)
 * @param string $table    Table to check uniqueness against (products|categories)
 * @param int|null $excludeId  ID to exclude from uniqueness check (for updates)
 * @return string  A unique slug
 */
function generate_slug(string $text, string $table = 'products', ?int $excludeId = null): string
{
    // Transliterate common characters
    $slug = mb_strtolower($text, 'UTF-8');
    
    // Remove accents / transliterate
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
    
    // Replace non-alphanumeric with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    
    // Trim hyphens from edges
    $slug = trim($slug, '-');
    
    // Collapse multiple hyphens
    $slug = preg_replace('/-{2,}/', '-', $slug);
    
    // Max length ~60
    if (strlen($slug) > 60) {
        $slug = substr($slug, 0, 60);
        $slug = rtrim($slug, '-');
    }
    
    // Ensure uniqueness
    $baseSlug = $slug;
    $counter = 1;
    
    while (slug_exists($slug, $table, $excludeId)) {
        $counter++;
        $suffix = '-' . $counter;
        $slug = substr($baseSlug, 0, 60 - strlen($suffix)) . $suffix;
    }
    
    return $slug;
}

/**
 * Check if a slug already exists in the given table.
 */
function slug_exists(string $slug, string $table = 'products', ?int $excludeId = null): bool
{
    $allowedTables = ['products', 'categories'];
    if (!in_array($table, $allowedTables, true)) {
        return false;
    }
    
    try {
        if ($excludeId) {
            $row = db_fetch("SELECT id FROM {$table} WHERE slug = ? AND id != ?", [$slug, $excludeId]);
        } else {
            $row = db_fetch("SELECT id FROM {$table} WHERE slug = ?", [$slug]);
        }
        return (bool)$row;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get a product by its slug (for SEO-friendly URL routing).
 */
function get_product_by_slug(string $slug): ?array
{
    try {
        return db_fetch(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug 
             FROM products p 
             LEFT JOIN categories c ON c.id = p.category_id 
             WHERE p.slug = ?',
            [$slug]
        );
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Create a 301 redirect entry when a slug changes.
 */
function create_seo_redirect(string $oldPath, string $newPath, int $statusCode = 301): bool
{
    try {
        // Don't create redirect if old == new
        if ($oldPath === $newPath) return false;
        
        // Upsert: update if old_path already exists
        $db = get_db_connection();
        $stmt = $db->prepare(
            "INSERT INTO seo_redirects (old_path, new_path, status_code) 
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), status_code = VALUES(status_code)"
        );
        return $stmt->execute([$oldPath, $newPath, $statusCode]);
    } catch (PDOException $e) {
        error_log("SEO redirect creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if request path matches a redirect and return target.
 */
function check_seo_redirect(string $requestPath): ?array
{
    try {
        $redirect = db_fetch(
            "SELECT new_path, status_code FROM seo_redirects WHERE old_path = ?",
            [$requestPath]
        );
        
        if ($redirect) {
            // Update hit counter
            try {
                $db = get_db_connection();
                $db->prepare("UPDATE seo_redirects SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE old_path = ?")->execute([$requestPath]);
            } catch (Exception $e) {
                // Non-critical
            }
            return $redirect;
        }
        return null;
    } catch (PDOException $e) {
        return null;
    }
}


// ============================================================
// SEO META TAG HELPERS
// ============================================================

/**
 * Get the canonical URL for a product.
 * Uses slug-based URL if slug exists, falls back to ?id= URL.
 */
function get_product_canonical_url(array $product): string
{
    $baseUrl = 'https://gilafstore.com';
    
    // Check for manual canonical override
    if (!empty($product['canonical_override'])) {
        return $product['canonical_override'];
    }
    
    if (!empty($product['slug'])) {
        return $baseUrl . '/product/' . $product['slug'];
    }
    
    return $baseUrl . '/product.php?id=' . (int)$product['id'];
}

/**
 * Get the canonical URL for a category.
 */
function get_category_canonical_url(array $category): string
{
    $baseUrl = 'https://gilafstore.com';
    
    if (!empty($category['slug'])) {
        return $baseUrl . '/category/' . $category['slug'];
    }
    
    return $baseUrl . '/shop.php?category=' . (int)$category['id'];
}

/**
 * Build SEO title with fallback.
 * Priority: seo_title > "Product Name | Gilaf Store"
 */
function get_seo_title(array $product): string
{
    if (!empty($product['seo_title'])) {
        return $product['seo_title'];
    }
    
    $title = $product['name'];
    
    // Add category context if available
    if (!empty($product['category_name'])) {
        $title .= ' — ' . $product['category_name'];
    }
    
    $title .= ' | Gilaf Store';
    
    // Truncate to ~60 chars for SERP display
    if (mb_strlen($title) > 65) {
        $title = $product['name'] . ' | Gilaf Store';
    }
    
    return $title;
}

/**
 * Build SEO meta description with fallback.
 * Priority: seo_description > short_description > first 155 chars of description
 */
function get_seo_description(array $product): string
{
    $desc = '';

    if (!empty($product['seo_description'])) {
        $desc = $product['seo_description'];
    } elseif (!empty($product['short_description'])) {
        $desc = strip_tags($product['short_description']);
    } elseif (!empty($product['description'])) {
        $desc = strip_tags($product['description']);
        $desc = preg_replace('/\s+/', ' ', trim($desc));
    } else {
        $desc = 'Buy ' . $product['name'] . ' online at Gilaf Store. Premium Kashmiri products with free shipping.';
    }

    // Enforce 160-char max (truncate at word boundary)
    if (mb_strlen($desc) > 160) {
        $desc = mb_substr($desc, 0, 157);
        $lastSpace = mb_strrpos($desc, ' ');
        if ($lastSpace > 100) {
            $desc = mb_substr($desc, 0, $lastSpace);
        }
        $desc = rtrim($desc, '.,;:!? ') . '...';
    }

    return $desc;
}

/**
 * Get OG image URL with fallback.
 * Priority: og_image_url > primary product image
 */
function get_og_image_url(array $product): string
{
    $baseUrl = 'https://gilafstore.com';
    
    if (!empty($product['og_image_url'])) {
        // If already absolute URL, return as-is
        if (str_starts_with($product['og_image_url'], 'http')) {
            return $product['og_image_url'];
        }
        return $baseUrl . '/assets/images/products/' . ltrim($product['og_image_url'], '/');
    }
    
    // Use image_1 first, then main image
    $image = $product['image_1'] ?? $product['image'] ?? '';
    if (!empty($image)) {
        return $baseUrl . '/assets/images/products/' . ltrim($image, '/');
    }
    
    // Default fallback
    return $baseUrl . '/assets/images/gilaf-store-og-default.jpg';
}

/**
 * Output all OG + Twitter meta tags for a product page.
 * Call this inside <head> on product.php.
 */
function render_product_meta_tags(array $product): string
{
    $title = get_seo_title($product);
    $description = get_seo_description($product);
    $canonical = get_product_canonical_url($product);
    $image = get_og_image_url($product);
    
    $html = '';
    
    // OpenGraph (Facebook / WhatsApp / Telegram)
    $html .= '    <meta property="og:type" content="product" />' . "\n";
    $html .= '    <meta property="og:title" content="' . htmlspecialchars($title) . '" />' . "\n";
    $html .= '    <meta property="og:description" content="' . htmlspecialchars($description) . '" />' . "\n";
    $html .= '    <meta property="og:url" content="' . htmlspecialchars($canonical) . '" />' . "\n";
    $html .= '    <meta property="og:image" content="' . htmlspecialchars($image) . '" />' . "\n";
    $html .= '    <meta property="og:image:width" content="1200" />' . "\n";
    $html .= '    <meta property="og:image:height" content="630" />' . "\n";
    $html .= '    <meta property="og:site_name" content="Gilaf Store" />' . "\n";
    $html .= '    <meta property="og:locale" content="en_IN" />' . "\n";
    
    // Product-specific OG tags
    $html .= '    <meta property="product:brand" content="Gilaf Store" />' . "\n";
    $html .= '    <meta property="product:availability" content="in stock" />' . "\n";
    $html .= '    <meta property="product:condition" content="new" />' . "\n";
    
    // Twitter Card
    $html .= '    <meta name="twitter:card" content="summary_large_image" />' . "\n";
    $html .= '    <meta name="twitter:title" content="' . htmlspecialchars($title) . '" />' . "\n";
    $html .= '    <meta name="twitter:description" content="' . htmlspecialchars($description) . '" />' . "\n";
    $html .= '    <meta name="twitter:image" content="' . htmlspecialchars($image) . '" />' . "\n";
    
    return $html;
}

/**
 * Output OG + Twitter meta tags for a generic (non-product) page.
 */
function render_page_meta_tags(string $title, string $description, string $url, string $image = ''): string
{
    $baseUrl = 'https://gilafstore.com';
    if (empty($image)) {
        $image = $baseUrl . '/assets/images/gilaf-store-og-default.jpg';
    }
    
    $html = '';
    $html .= '    <meta property="og:type" content="website" />' . "\n";
    $html .= '    <meta property="og:title" content="' . htmlspecialchars($title) . '" />' . "\n";
    $html .= '    <meta property="og:description" content="' . htmlspecialchars($description) . '" />' . "\n";
    $html .= '    <meta property="og:url" content="' . htmlspecialchars($url) . '" />' . "\n";
    $html .= '    <meta property="og:image" content="' . htmlspecialchars($image) . '" />' . "\n";
    $html .= '    <meta property="og:site_name" content="Gilaf Store" />' . "\n";
    $html .= '    <meta property="og:locale" content="en_IN" />' . "\n";
    $html .= '    <meta name="twitter:card" content="summary_large_image" />' . "\n";
    $html .= '    <meta name="twitter:title" content="' . htmlspecialchars($title) . '" />' . "\n";
    $html .= '    <meta name="twitter:description" content="' . htmlspecialchars($description) . '" />' . "\n";
    $html .= '    <meta name="twitter:image" content="' . htmlspecialchars($image) . '" />' . "\n";
    
    return $html;
}


// ============================================================
// SCHEMA / JSON-LD BUILDERS
// ============================================================

/**
 * Build Product JSON-LD schema with multi-variant Offers.
 * 
 * Rules:
 * - Price = effective price from PricingService (auto-discount applied)
 * - priceValidUntil ONLY when discount is active AND has end_date
 * - Promo codes NEVER included in schema pricing
 * - One Offer per variant; availability per variant stock
 * - aggregateRating only if real reviews exist
 */
function build_product_schema(array $product, array $weights, ?array $discount, array $ratingData, array $productImages): array
{
    $canonical = get_product_canonical_url($product);
    
    // Build image URLs
    $schemaImages = [];
    foreach ($productImages as $img) {
        $schemaImages[] = 'https://gilafstore.com/assets/images/products/' . ltrim($img, '/');
    }
    if (empty($schemaImages) && !empty($product['image'])) {
        $schemaImages[] = 'https://gilafstore.com/assets/images/products/' . ltrim($product['image'], '/');
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'image' => $schemaImages,
        'description' => !empty($product['description']) 
            ? mb_substr(strip_tags($product['description']), 0, 5000) 
            : ('Premium ' . ($product['category_name'] ?? 'product') . ' from Gilaf Store — Kashmiri heritage foods'),
        'brand' => [
            '@type' => 'Brand',
            'name' => 'Gilaf Store',
        ],
        'url' => $canonical,
    ];
    
    // SKU (use product ID as fallback)
    $schema['sku'] = $product['sku'] ?? ('GILAF-' . $product['id']);
    
    // Category
    if (!empty($product['category_name'])) {
        $schema['category'] = $product['category_name'];
    }
    
    // Build Offers array — one per variant
    $offers = [];
    
    if (!empty($weights)) {
        foreach ($weights as $w) {
            $basePrice = (float)$w['price'];
            $priceInfo = calculate_discount_price($basePrice, $discount);
            
            $offer = [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'INR',
                'price' => number_format($priceInfo['discounted_price'], 2, '.', ''),
                'itemCondition' => 'https://schema.org/NewCondition',
            ];
            
            // Availability per variant
            $stockQty = (int)($w['stock_qty'] ?? $product['stock'] ?? 0);
            $offer['availability'] = $stockQty > 0 
                ? 'https://schema.org/InStock' 
                : 'https://schema.org/OutOfStock';
            
            // priceValidUntil: ONLY if auto-discount is active with end_date
            if ($priceInfo['has_discount'] && !empty($discount['end_date'])) {
                $offer['priceValidUntil'] = date('Y-m-d', strtotime($discount['end_date']));
            }
            
            // Variant name for clarity
            if (!empty($w['display_weight'])) {
                $offer['name'] = $product['name'] . ' — ' . $w['display_weight'];
            }
            
            // EAN/GTIN
            if (!empty($w['ean'])) {
                $offer['gtin13'] = $w['ean'];
            }
            
            $offer['seller'] = [
                '@type' => 'Organization',
                'name' => 'Gilaf Store',
            ];
            
            $offers[] = $offer;
        }
    } else {
        // No weights — single offer from base product price
        $basePrice = (float)$product['price'];
        $priceInfo = calculate_discount_price($basePrice, $discount);
        
        $offer = [
            '@type' => 'Offer',
            'url' => $canonical,
            'priceCurrency' => 'INR',
            'price' => number_format($priceInfo['discounted_price'], 2, '.', ''),
            'availability' => ((int)($product['stock'] ?? 0) > 0) 
                ? 'https://schema.org/InStock' 
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => [
                '@type' => 'Organization',
                'name' => 'Gilaf Store',
            ],
        ];
        
        if ($priceInfo['has_discount'] && !empty($discount['end_date'])) {
            $offer['priceValidUntil'] = date('Y-m-d', strtotime($discount['end_date']));
        }
        
        $offers[] = $offer;
    }
    
    $schema['offers'] = count($offers) === 1 ? $offers[0] : $offers;
    
    // Aggregate rating — ONLY real reviews
    if (!empty($ratingData) && ($ratingData['count'] ?? 0) > 0 && ($ratingData['is_actual'] ?? false)) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float)($ratingData['rating'] ?? 0), 1, '.', ''),
            'reviewCount' => (int)$ratingData['count'],
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }
    
    return $schema;
}

/**
 * Build Organization schema (global, for homepage / all pages).
 */
function build_organization_schema(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Gilaf Store',
        'url' => 'https://gilafstore.com',
        'logo' => 'https://gilafstore.com/assets/images/logo.png',
        'description' => 'Premium Kashmiri saffron, organic honey, hand-selected spices, dry fruits, and tea. Farm-to-table heritage foods from the valley of Kashmir.',
        'sameAs' => [
            'https://www.instagram.com/gilafstore',
            'https://www.facebook.com/gilafstore',
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'availableLanguage' => ['English', 'Hindi'],
        ],
    ];
}

/**
 * Build WebSite schema with SearchAction (sitelinks search box).
 */
function build_website_schema(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Gilaf Store',
        'url' => 'https://gilafstore.com',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => 'https://gilafstore.com/shop.php?search={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

/**
 * Build BreadcrumbList schema for a product page.
 */
function build_product_breadcrumb_schema(array $product): array
{
    $items = [];
    $position = 1;
    
    // Home
    $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => 'Home',
        'item' => 'https://gilafstore.com',
    ];
    
    // Category
    if (!empty($product['category_name'])) {
        $catUrl = !empty($product['category_slug'])
            ? 'https://gilafstore.com/category/' . $product['category_slug']
            : 'https://gilafstore.com/shop.php?category=' . (int)$product['category_id'];
        
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $product['category_name'],
            'item' => $catUrl,
        ];
    }
    
    // Product (current page — no item URL needed for last item)
    $items[] = [
        '@type' => 'ListItem',
        'position' => $position,
        'name' => $product['name'],
    ];
    
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}


// ============================================================
// IMAGE SEO HELPERS
// ============================================================

/**
 * Generate SEO-friendly alt text for a product image.
 *
 * @param string $productName  Product name
 * @param string|null $sizeLabel  Variant size (e.g. "500g")
 * @param string $imageType  front|back|gallery
 * @param int $index  Image index (1-based)
 * @return string Alt text
 */
function generate_image_alt_text(string $productName, ?string $sizeLabel = null, string $imageType = 'front', int $index = 1): string
{
    $alt = $productName;
    
    if ($sizeLabel) {
        $alt .= ' ' . $sizeLabel;
    }
    
    switch ($imageType) {
        case 'front':
            $alt .= ' — product image';
            break;
        case 'back':
            $alt .= ' — back label';
            break;
        case 'gallery':
            $alt .= ' — image ' . $index;
            break;
    }
    
    return $alt;
}

/**
 * Generate SEO-friendly filename for a product image.
 *
 * @param string $slug        Product slug
 * @param string|null $sizeLabel  Variant size label
 * @param string $imageType   front|back|gallery
 * @param int $index          Image index
 * @param string $ext         File extension
 * @return string New filename
 */
function generate_seo_image_filename(string $slug, ?string $sizeLabel = null, string $imageType = 'front', int $index = 1, string $ext = 'webp'): string
{
    $parts = [$slug];
    
    if ($sizeLabel) {
        $sizeSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($sizeLabel));
        $parts[] = trim($sizeSlug, '-');
    }
    
    $parts[] = $imageType;
    $parts[] = str_pad($index, 2, '0', STR_PAD_LEFT);
    
    // Add short unique suffix to avoid collisions
    $parts[] = substr(uniqid(), -4);
    
    return implode('-', $parts) . '.' . $ext;
}


// ============================================================
// SITEMAP HELPERS
// ============================================================

/**
 * Get all products with slugs for sitemap generation.
 * Returns only canonical URLs (no variant params).
 */
function get_sitemap_products(): array
{
    try {
        return db_fetch_all(
            "SELECT id, slug, name, image, image_1, image_2, image_3, image_4, created_at, updated_at
             FROM products 
             ORDER BY id DESC"
        );
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all categories with slugs for sitemap.
 */
function get_sitemap_categories(): array
{
    try {
        return db_fetch_all(
            "SELECT id, slug, name, updated_at, created_at 
             FROM categories 
             ORDER BY id ASC"
        );
    } catch (PDOException $e) {
        return [];
    }
}


// ============================================================
// MIGRATION / SETUP UTILITIES
// ============================================================

/**
 * Generate and save slugs for all products that don't have one.
 * Safe to run multiple times (idempotent).
 *
 * @return int Number of products updated
 */
function migrate_product_slugs(): int
{
    $db = get_db_connection();
    $products = db_fetch_all("SELECT id, name FROM products WHERE slug IS NULL OR slug = ''");
    $count = 0;
    
    foreach ($products as $p) {
        $slug = generate_slug($p['name'], 'products', (int)$p['id']);
        $stmt = $db->prepare("UPDATE products SET slug = ? WHERE id = ?");
        $stmt->execute([$slug, $p['id']]);
        
        // Create redirect from old URL to new slug URL
        create_seo_redirect(
            '/product.php?id=' . $p['id'],
            '/product/' . $slug
        );
        
        $count++;
    }
    
    return $count;
}

/**
 * Generate and save slugs for all categories that don't have one.
 */
function migrate_category_slugs(): int
{
    $db = get_db_connection();
    $categories = db_fetch_all("SELECT id, name FROM categories WHERE slug IS NULL OR slug = ''");
    $count = 0;
    
    foreach ($categories as $c) {
        $slug = generate_slug($c['name'], 'categories', (int)$c['id']);
        $stmt = $db->prepare("UPDATE categories SET slug = ? WHERE id = ?");
        $stmt->execute([$slug, $c['id']]);
        $count++;
    }
    
    return $count;
}

/**
 * Check if SEO migration has been run (slug column exists and populated).
 */
function seo_migration_status(): array
{
    try {
        $db = get_db_connection();
        
        // Check if slug column exists on products
        $check = $db->query("SHOW COLUMNS FROM products LIKE 'slug'");
        $hasSlugColumn = $check->rowCount() > 0;
        
        if (!$hasSlugColumn) {
            return ['migrated' => false, 'reason' => 'slug column missing from products table'];
        }
        
        $total = db_fetch("SELECT COUNT(*) as cnt FROM products")['cnt'] ?? 0;
        $withSlugs = db_fetch("SELECT COUNT(*) as cnt FROM products WHERE slug IS NOT NULL AND slug != ''")['cnt'] ?? 0;
        
        return [
            'migrated' => true,
            'total_products' => (int)$total,
            'products_with_slugs' => (int)$withSlugs,
            'products_missing_slugs' => (int)$total - (int)$withSlugs,
        ];
    } catch (PDOException $e) {
        return ['migrated' => false, 'reason' => $e->getMessage()];
    }
}
