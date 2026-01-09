<?php
/**
 * Image Helper Functions
 * Provides WebP support with fallback and responsive images
 */

/**
 * Generate responsive image tag with WebP support and lazy loading
 * @param string $imagePath - Path to original image
 * @param string $alt - Alt text
 * @param bool $lazy - Enable lazy loading (default: true)
 * @param array $sizes - Responsive sizes array ['mobile' => 400, 'tablet' => 768, 'desktop' => 1200]
 * @return string HTML picture element
 */
function responsive_image($imagePath, $alt = '', $lazy = true, $sizes = null) {
    if (empty($imagePath)) return '';
    
    // Default responsive sizes
    if ($sizes === null) {
        $sizes = [
            'mobile' => 400,
            'tablet' => 768,
            'desktop' => 1200
        ];
    }
    
    $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $imagePath);
    $hasWebP = file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/')) && 
               file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($webpPath, '/'));
    
    $lazyAttr = $lazy ? 'loading="lazy"' : '';
    $alt = htmlspecialchars($alt);
    
    if ($hasWebP) {
        return <<<HTML
<picture>
    <source type="image/webp" srcset="{$webpPath}" {$lazyAttr}>
    <source type="image/jpeg" srcset="{$imagePath}" {$lazyAttr}>
    <img src="{$imagePath}" alt="{$alt}" {$lazyAttr} class="img-fluid">
</picture>
HTML;
    } else {
        return "<img src=\"{$imagePath}\" alt=\"{$alt}\" {$lazyAttr} class=\"img-fluid\">";
    }
}

/**
 * Get WebP version of image if available, otherwise return original
 * @param string $imagePath - Path to original image
 * @return string Path to WebP or original image
 */
function get_optimized_image($imagePath) {
    $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $imagePath);
    
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($webpPath, '/'))) {
        return $webpPath;
    }
    
    return $imagePath;
}

/**
 * Generate srcset for responsive images
 * @param string $imagePath - Base image path
 * @param array $widths - Array of widths [400, 768, 1200]
 * @return string srcset attribute value
 */
function generate_srcset($imagePath, $widths = [400, 768, 1200]) {
    $srcset = [];
    foreach ($widths as $width) {
        $srcset[] = "{$imagePath} {$width}w";
    }
    return implode(', ', $srcset);
}
