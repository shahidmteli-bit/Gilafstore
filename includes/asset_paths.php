<?php
/**
 * Asset Path Manager — Single Source of Truth for all asset folder paths.
 * 
 * PURPOSE:
 *   1. One canonical folder per category — no duplicates, no case variants.
 *   2. Hybrid mode: works on both Windows (case-insensitive) and Linux (case-sensitive).
 *   3. Any code requesting an asset path gets the correct canonical path automatically.
 *   4. Prevents creation of duplicate folders with different casing.
 *
 * USAGE:
 *   $path = asset_path('products');           // → 'assets/images/products'
 *   $path = asset_path('shipping-partners');  // → 'assets/images/shipping-partners'
 *   $abs  = asset_abs('products');            // → '/var/www/.../assets/images/products/'
 *   $url  = asset_dir_url('products');        // → 'https://gilafstore.com/assets/images/products/'
 *   safe_mkdir('products');                   // Creates the canonical folder if missing
 */

if (!defined('ASSET_PATHS_LOADED')) {
    define('ASSET_PATHS_LOADED', true);

    // =========================================================================
    // CANONICAL FOLDER REGISTRY
    // Only ONE entry per category. This is the single source of truth.
    // To add a new asset category, add it here — nowhere else.
    // =========================================================================
    define('ASSET_CANONICAL_MAP', [
        // Category key          => Canonical relative path from site root
        'images'                 => 'assets/images',
        'products'               => 'assets/images/products',
        'payment-logos'          => 'assets/images/payment-logos',
        'shipping-partners'      => 'assets/images/shipping-partners',
        'website'                => 'assets/images/website',
        'icons'                  => 'assets/icons',
        'css'                    => 'assets/css',
        'js'                     => 'assets/js',
        'sounds'                 => 'assets/sounds',
        'lab-reports'            => 'assets/lab_reports',
        'uploads'                => 'assets/uploads',
        'hero-banner'            => 'assets/uploads/hero_banner',
        'advertisements'         => 'assets/uploads/advertisements',
    ]);

    // =========================================================================
    // ALIAS MAP — catches typos, old names, and case variants
    // Maps any known wrong/old name → correct canonical key
    // =========================================================================
    define('ASSET_ALIAS_MAP', [
        // Case variants
        'Images'                 => 'images',
        'IMAGES'                 => 'images',
        'Products'               => 'products',
        'PRODUCTS'               => 'products',
        'Icons'                  => 'icons',
        'ICONS'                  => 'icons',
        'CSS'                    => 'css',
        'JS'                     => 'js',

        // Typos & old folder names
        'Shipping-patners'       => 'shipping-partners',
        'shipping-patners'       => 'shipping-partners',
        'Shipping-Partners'      => 'shipping-partners',
        'Shipping-partners'      => 'shipping-partners',
        'shipping_partners'      => 'shipping-partners',

        // Old path fragments that might appear in code
        'payment_logos'          => 'payment-logos',
        'Payment-logos'          => 'payment-logos',
        'Payment-Logos'          => 'payment-logos',
        'hero_banner'            => 'hero-banner',
        'Hero_banner'            => 'hero-banner',
        'lab_reports'            => 'lab-reports',
        'Lab_reports'            => 'lab-reports',
    ]);

    /**
     * Resolve a category key (or alias) to its canonical relative path.
     * 
     * @param string $key  Category key like 'products', 'shipping-partners', etc.
     * @return string|null  Canonical relative path or null if unknown.
     */
    function asset_path(string $key): ?string
    {
        // Direct match
        if (isset(ASSET_CANONICAL_MAP[$key])) {
            return ASSET_CANONICAL_MAP[$key];
        }
        // Alias match
        if (isset(ASSET_ALIAS_MAP[$key])) {
            $canonical = ASSET_ALIAS_MAP[$key];
            return ASSET_CANONICAL_MAP[$canonical] ?? null;
        }
        // Case-insensitive fallback (hybrid mode)
        $lower = strtolower($key);
        foreach (ASSET_CANONICAL_MAP as $k => $v) {
            if (strtolower($k) === $lower) {
                return $v;
            }
        }
        return null;
    }

    /**
     * Get the absolute filesystem path for a canonical asset folder.
     * 
     * @param string $key  Category key like 'products', 'icons', etc.
     * @return string|null  Absolute path with trailing separator, or null if unknown.
     */
    function asset_abs(string $key): ?string
    {
        $rel = asset_path($key);
        if ($rel === null) return null;

        $siteRoot = defined('SITE_ROOT') ? SITE_ROOT : (realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
        return $siteRoot . str_replace('/', DIRECTORY_SEPARATOR, $rel) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the full URL for a canonical asset folder.
     * 
     * @param string $key  Category key like 'products', 'icons', etc.
     * @return string|null  Full URL with trailing slash, or null if unknown.
     */
    function asset_dir_url(string $key): ?string
    {
        $rel = asset_path($key);
        if ($rel === null) return null;

        if (function_exists('base_url')) {
            return base_url($rel . '/');
        }
        return '/' . $rel . '/';
    }

    /**
     * Safely create a canonical asset directory if it doesn't exist.
     * PREVENTS duplicate folders — only creates the canonical path.
     * 
     * @param string $key  Category key like 'products', 'icons', etc.
     * @return bool  True if directory exists or was created, false on failure.
     */
    function safe_mkdir(string $key): bool
    {
        $abs = asset_abs($key);
        if ($abs === null) {
            error_log("[AssetPaths] Cannot create folder: unknown category key '{$key}'");
            return false;
        }
        if (is_dir($abs)) {
            return true;
        }
        $created = mkdir($abs, 0755, true);
        if ($created) {
            error_log("[AssetPaths] Created canonical folder: {$abs}");
        } else {
            error_log("[AssetPaths] FAILED to create folder: {$abs}");
        }
        return $created;
    }

    /**
     * Normalize a raw asset path string to use canonical folder names.
     * Handles any casing or typo variant in the path.
     * 
     * Example:
     *   normalize_asset_path('assets/Images/Shipping-patners/dhl.png')
     *   → 'assets/images/shipping-partners/dhl.png'
     * 
     * @param string $rawPath  Raw path that may contain wrong case or typos.
     * @return string  Normalized path with canonical folder names.
     */
    function normalize_asset_path(string $rawPath): string
    {
        $path = str_replace('\\', '/', $rawPath);

        // Replace known folder variants with canonical names
        $replacements = [
            // Images folder — capital I variants
            'assets/Images/'       => 'assets/images/',
            'assets/IMAGES/'       => 'assets/images/',

            // Products
            'assets/images/Products/' => 'assets/images/products/',
            'assets/Images/Products/' => 'assets/images/products/',

            // Payment logos
            'assets/images/Payment-logos/' => 'assets/images/payment-logos/',
            'assets/Images/Payment-logos/' => 'assets/images/payment-logos/',
            'assets/Images/Payment-Logos/' => 'assets/images/payment-logos/',

            // Shipping partners (typo + case variants)
            'assets/Images/Shipping-patners/' => 'assets/images/shipping-partners/',
            'assets/images/Shipping-patners/' => 'assets/images/shipping-partners/',
            'assets/Images/Shipping-Partners/' => 'assets/images/shipping-partners/',
            'assets/images/Shipping-Partners/' => 'assets/images/shipping-partners/',
            'assets/Images/shipping-patners/'  => 'assets/images/shipping-partners/',
            'assets/images/shipping-patners/'  => 'assets/images/shipping-partners/',
            'assets/Images/shipping-partners/' => 'assets/images/shipping-partners/',

            // Icons
            'assets/Icons/'        => 'assets/icons/',
            'assets/ICONS/'        => 'assets/icons/',
        ];

        foreach ($replacements as $wrong => $correct) {
            if (stripos($path, $wrong) !== false) {
                $path = str_ireplace($wrong, $correct, $path);
            }
        }

        return $path;
    }

    /**
     * Get a cache-busting version string for an asset file.
     * Uses file modification time so browsers cache until file actually changes.
     * REPLACES the anti-pattern of ?v=<?= time(); ?> which defeats all caching.
     * 
     * @param string $relativePath  Path relative to site root (e.g. 'assets/css/new-design.css')
     * @return string  Version query string like '?v=1708800000'
     */
    function asset_version(string $relativePath): string
    {
        $siteRoot = defined('SITE_ROOT') ? SITE_ROOT : (realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
        $filePath = $siteRoot . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        
        if (file_exists($filePath)) {
            return '?v=' . filemtime($filePath);
        }
        // Fallback: static version that changes only on deployment
        return '?v=20260224';
    }

    /**
     * Get a versioned asset URL. Combines asset_url() + asset_version().
     * 
     * @param string $path  Path relative to assets/ (e.g. 'css/new-design.css')
     * @return string  Full URL with version query string
     */
    function asset_url_versioned(string $path): string
    {
        $url = function_exists('asset_url') ? asset_url($path) : '/assets/' . ltrim($path, '/');
        return $url . asset_version('assets/' . ltrim($path, '/'));
    }

    /**
     * List all canonical asset categories and their paths.
     * Useful for admin/debug pages.
     * 
     * @return array  ['key' => 'relative/path', ...]
     */
    function get_all_asset_paths(): array
    {
        return ASSET_CANONICAL_MAP;
    }

    /**
     * Check if a given path matches any canonical asset folder.
     * Returns the canonical key if matched, null otherwise.
     * 
     * @param string $path  Path to check.
     * @return string|null  Canonical key or null.
     */
    function identify_asset_category(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        foreach (ASSET_CANONICAL_MAP as $key => $canonical) {
            if (stripos($normalized, $canonical) !== false) {
                return $key;
            }
        }
        return null;
    }
}
