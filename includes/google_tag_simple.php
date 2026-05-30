<?php
/**
 * Google Tag Injector — Production-Ready
 * File-based config, bulletproof error handling, multi-tag support.
 * This file is included from new-header.php inside <head>.
 * ANY fatal error here = white page, so every path is wrapped in try/catch.
 */

function _gtag_read_config() {
    $path = __DIR__ . '/../google_tag_config.json';
    if (!file_exists($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $cfg = @json_decode($raw, true);
    return is_array($cfg) ? $cfg : null;
}

function _gtag_current_page_type() {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = @parse_url($uri, PHP_URL_PATH);
    if (!$path) $path = '/';

    if ($path === '/' || $path === '/index.php' || $path === '')          return 'homepage';
    if (strpos($path, '/product') === 0)                                  return 'product';
    if (strpos($path, '/shop') === 0)                                     return 'shop';
    if (strpos($path, '/cart') === 0)                                     return 'cart';
    if (strpos($path, '/checkout') === 0)                                 return 'checkout';
    if (strpos($path, '/thank') === 0 || strpos($path, '/order_success') === 0) return 'thank_you';
    if (strpos($path, '/blog') === 0)                                     return 'blog';
    if (strpos($path, '/offers') === 0)                                   return 'offers';
    if (strpos($path, '/contact') === 0)                                  return 'contact';
    return 'other';
}

function _gtag_should_load($cfg) {
    if (empty($cfg) || empty($cfg['enabled'])) return false;

    // Check tags array (new multi-tag format)
    if (!empty($cfg['tags']) && is_array($cfg['tags'])) {
        foreach ($cfg['tags'] as $tag) {
            if (!empty($tag['enabled']) && (!empty($tag['script']) || !empty($tag['tag_id']))) {
                if (_gtag_page_match($tag)) return true;
            }
        }
        return false;
    }

    // Legacy single-tag format
    if (empty($cfg['script'])) return false;
    return _gtag_page_match($cfg);
}

function _gtag_page_match($tag) {
    $pages       = $tag['pages'] ?? [];
    $custom_urls = $tag['custom_urls'] ?? [];

    if (in_array('all', $pages)) return true;

    $type = _gtag_current_page_type();
    if (in_array($type, $pages)) return true;

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    foreach ($custom_urls as $u) {
        if ($u !== '' && strpos($uri, $u) !== false) return true;
    }
    return false;
}

function _gtag_build_script_for_tag($tag) {
    $type   = $tag['type']   ?? 'custom';
    $script = $tag['script'] ?? '';
    $tag_id = $tag['tag_id'] ?? '';

    // For structured types, generate the correct script from the ID
    switch ($type) {
        case 'ga4':
            if (empty($tag_id)) return '';
            return '<!-- Google Analytics 4 -->' . "\n"
                 . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($tag_id) . '"></script>' . "\n"
                 . '<script>' . "\n"
                 . 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}' . "\n"
                 . 'gtag(\'js\',new Date());gtag(\'config\',\'' . htmlspecialchars($tag_id) . '\');' . "\n"
                 . '</script>';

        case 'gtm':
            if (empty($tag_id)) return '';
            return '<!-- Google Tag Manager -->' . "\n"
                 . '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});'
                 . 'var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';'
                 . 'j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);'
                 . '})(window,document,\'script\',\'dataLayer\',\'' . htmlspecialchars($tag_id) . '\');</script>';

        case 'gads':
            if (empty($tag_id)) return '';
            return '<!-- Google Ads -->' . "\n"
                 . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($tag_id) . '"></script>' . "\n"
                 . '<script>' . "\n"
                 . 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}' . "\n"
                 . 'gtag(\'js\',new Date());gtag(\'config\',\'' . htmlspecialchars($tag_id) . '\');' . "\n"
                 . '</script>';

        case 'meta_pixel':
            if (empty($tag_id)) return '';
            return '<!-- Meta Pixel -->' . "\n"
                 . '<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?'
                 . 'n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;'
                 . 'n.push=n;n.loaded=!0;n.version=\'2.0\';n.queue=[];t=b.createElement(e);t.async=!0;'
                 . 't.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,'
                 . 'document,\'script\',\'https://connect.facebook.net/en_US/fbevents.js\');'
                 . 'fbq(\'init\',\'' . htmlspecialchars($tag_id) . '\');fbq(\'track\',\'PageView\');</script>' . "\n"
                 . '<noscript><img height="1" width="1" style="display:none" '
                 . 'src="https://www.facebook.com/tr?id=' . htmlspecialchars($tag_id) . '&ev=PageView&noscript=1"/></noscript>';

        case 'custom':
        default:
            return !empty($script) ? ('<!-- Custom Tag -->' . "\n" . $script) : '';
    }
}

/**
 * Outputs GTM <noscript> fallback(s) — called from new-header.php after <body>.
 * Only outputs for enabled GTM-type tags on matching pages.
 */
function inject_gtm_noscript_fallback() {
    try {
        $cfg = _gtag_read_config();
        if (!$cfg || empty($cfg['enabled'])) return;
        if (empty($cfg['tags']) || !is_array($cfg['tags'])) return;

        foreach ($cfg['tags'] as $tag) {
            if (empty($tag['enabled'])) continue;
            if (($tag['type'] ?? '') !== 'gtm') continue;
            if (empty($tag['tag_id'])) continue;
            if (!_gtag_page_match($tag)) continue;

            $id = htmlspecialchars($tag['tag_id']);
            echo "<!-- Google Tag Manager (noscript) -->\n"
               . '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id . '"'
               . ' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
               . "<!-- End Google Tag Manager (noscript) -->\n";
        }
    } catch (\Throwable $e) {
        // Silently swallow — never break the storefront
    }
}

/**
 * Main entry — called from new-header.php inside <head>.
 * MUST NEVER throw or fatal-error.
 */
function inject_google_tag_simple() {
    try {
        $cfg = _gtag_read_config();
        if (!$cfg || empty($cfg['enabled'])) return;

        $output = '';

        // New multi-tag format
        if (!empty($cfg['tags']) && is_array($cfg['tags'])) {
            foreach ($cfg['tags'] as $tag) {
                if (empty($tag['enabled'])) continue;
                if (!_gtag_page_match($tag)) continue;
                $output .= _gtag_build_script_for_tag($tag) . "\n";
            }
        }
        // Legacy single-tag format (backward compat)
        elseif (!empty($cfg['script']) && _gtag_page_match($cfg)) {
            $output = "<!-- Google Tag (Admin) -->\n" . $cfg['script'] . "\n";
        }

        if ($output !== '') echo $output;
    } catch (\Throwable $e) {
        // Silently swallow — never break the storefront
    }
}
?>
