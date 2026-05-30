<?php
/**
 * SEO Guard — SOP Validation for AI-Generated Content
 * Gilaf Store
 *
 * Validates AI output against hard rules (FAIL) and soft rules (WARN).
 * Returns { status: PASS|WARN|FAIL, issues: [...], data: {...sanitized...} }
 */

// ============================================================
// PROHIBITED PATTERNS
// ============================================================

define('SEO_GUARD_PROHIBITED_CLAIMS', [
    'cure', 'cures', 'treat', 'treats', 'heals', 'healing',
    'guaranteed results', 'miracle', 'FDA approved',
    'clinically proven', 'doctor recommended',
    'prevent disease', 'prevents cancer', 'anti-cancer',
    'weight loss guaranteed', '100% effective',
]);

define('SEO_GUARD_COMPETITOR_NAMES', [
    'amazon', 'flipkart', 'myntra', 'snapdeal', 'meesho',
    'jiomart', 'bigbasket', 'blinkit', 'zepto', 'swiggy',
    'zomato', 'alibaba', 'ebay', 'walmart',
]);

// ============================================================
// MAIN VALIDATION ENTRY POINT
// ============================================================

/**
 * Validate AI-generated content against SOP rules.
 *
 * @param array  $data      Parsed AI output (already JSON-decoded)
 * @param string $mode      'seo' | 'description' | 'featured'
 * @param int    $productId Current product ID (for slug uniqueness)
 * @return array { status, issues, data }
 */
function seo_guard_validate(array $data, string $mode, int $productId = 0): array
{
    $issues = [];
    $sanitized = $data;

    switch ($mode) {
        case 'seo':
            $sanitized = seo_guard_validate_seo($sanitized, $issues, $productId);
            break;
        case 'description':
            $sanitized = seo_guard_validate_description($sanitized, $issues);
            break;
        case 'featured':
            $sanitized = seo_guard_validate_featured($sanitized, $issues);
            break;
        default:
            $issues[] = ['type' => 'FAIL', 'field' => '_mode', 'message' => "Unknown mode: {$mode}"];
    }

    // Determine overall status
    $status = 'PASS';
    foreach ($issues as $issue) {
        if ($issue['type'] === 'FAIL') {
            $status = 'FAIL';
            break;
        }
        if ($issue['type'] === 'WARN') {
            $status = 'WARN';
        }
    }

    return [
        'status' => $status,
        'issues' => $issues,
        'data'   => $sanitized,
    ];
}

// ============================================================
// MODE: SEO
// ============================================================

function seo_guard_validate_seo(array $data, array &$issues, int $productId): array
{
    $required = ['slug', 'seo_title', 'meta_description', 'short_description'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $data)) {
            $issues[] = ['type' => 'FAIL', 'field' => $key, 'message' => "Missing required field: {$key}"];
            $data[$key] = '';
        }
    }

    // --- Slug ---
    $data['slug'] = seo_guard_sanitize_slug($data['slug'] ?? '');
    if (!empty($data['slug']) && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
        $issues[] = ['type' => 'FAIL', 'field' => 'slug', 'message' => 'Slug contains invalid characters. Must be lowercase letters, numbers, hyphens only.'];
    }
    // Slug uniqueness
    if (!empty($data['slug']) && $productId > 0) {
        $data['slug'] = seo_guard_ensure_unique_slug($data['slug'], $productId, $issues);
    }

    // --- SEO Title (Amazon-style: 120-150 chars ideal) ---
    $data['seo_title'] = trim($data['seo_title'] ?? '');
    $data['seo_title'] = seo_guard_strip_emojis($data['seo_title']);
    $titleLen = mb_strlen($data['seo_title']);
    if ($titleLen > 200) {
        $issues[] = ['type' => 'FAIL', 'field' => 'seo_title', 'message' => "SEO title is {$titleLen} chars (max 150). Too long even after trim."];
    } elseif ($titleLen > 150) {
        // Smart trim at last pipe or space before 150
        $trimmed = mb_substr($data['seo_title'], 0, 150);
        $lastPipe = mb_strrpos($trimmed, '|');
        $lastSpace = mb_strrpos($trimmed, ' ');
        $cutAt = $lastPipe && $lastPipe > 120 ? $lastPipe : ($lastSpace ?: 150);
        $data['seo_title'] = trim(mb_substr($data['seo_title'], 0, $cutAt));
        $issues[] = ['type' => 'WARN', 'field' => 'seo_title', 'message' => "SEO title trimmed from {$titleLen} to " . mb_strlen($data['seo_title']) . " chars."];
    } elseif ($titleLen < 80 && $titleLen > 0) {
        $issues[] = ['type' => 'WARN', 'field' => 'seo_title', 'message' => "SEO title is only {$titleLen} chars. Recommended: 120-150 for Amazon ranking."];
    }

    // --- Meta Description ---
    $data['meta_description'] = trim($data['meta_description'] ?? '');
    $data['meta_description'] = seo_guard_strip_emojis($data['meta_description']);
    $descLen = mb_strlen($data['meta_description']);
    if ($descLen > 170) {
        $data['meta_description'] = mb_substr($data['meta_description'], 0, 160);
        $issues[] = ['type' => 'WARN', 'field' => 'meta_description', 'message' => "Meta description trimmed from {$descLen} to 160 chars."];
        $descLen = 160;
    }
    if ($descLen < 120 && $descLen > 0) {
        $issues[] = ['type' => 'FAIL', 'field' => 'meta_description', 'message' => "Meta description is only {$descLen} chars (minimum 120)."];
    } elseif ($descLen < 140) {
        $issues[] = ['type' => 'WARN', 'field' => 'meta_description', 'message' => "Meta description is {$descLen} chars. Recommended: 150-160."];
    } elseif ($descLen > 160) {
        $issues[] = ['type' => 'WARN', 'field' => 'meta_description', 'message' => "Meta description is {$descLen} chars. Ideal range: 150-160."];
    }

    // --- Short Description ---
    $data['short_description'] = trim($data['short_description'] ?? '');
    $data['short_description'] = seo_guard_strip_emojis($data['short_description']);
    if (mb_strlen($data['short_description']) > 200) {
        $data['short_description'] = mb_substr($data['short_description'], 0, 200);
        $issues[] = ['type' => 'WARN', 'field' => 'short_description', 'message' => 'Short description trimmed to 200 chars.'];
    }

    // --- Social Image URL (optional) ---
    if (!empty($data['social_image_url'])) {
        $data['social_image_url'] = filter_var(trim($data['social_image_url']), FILTER_SANITIZE_URL);
    } else {
        $data['social_image_url'] = '';
    }

    // --- SEO Keywords (comma-separated ranking keywords) ---
    if (!empty($data['seo_keywords'])) {
        $data['seo_keywords'] = trim(strip_tags($data['seo_keywords']));
        $data['seo_keywords'] = seo_guard_strip_emojis($data['seo_keywords']);
    } else {
        $data['seo_keywords'] = '';
    }

    // --- Backend Search Terms (hidden keywords for ranking) ---
    if (!empty($data['backend_search_terms'])) {
        $data['backend_search_terms'] = trim(strip_tags($data['backend_search_terms']));
        $data['backend_search_terms'] = seo_guard_strip_emojis($data['backend_search_terms']);
        if (mb_strlen($data['backend_search_terms']) > 250) {
            $data['backend_search_terms'] = mb_substr($data['backend_search_terms'], 0, 250);
            $issues[] = ['type' => 'WARN', 'field' => 'backend_search_terms', 'message' => 'Backend search terms trimmed to 250 chars.'];
        }
    } else {
        $data['backend_search_terms'] = '';
    }

    // --- Cross-field checks ---
    seo_guard_check_prohibited($data, ['seo_title', 'meta_description', 'short_description'], $issues);
    seo_guard_check_competitors($data, ['seo_title', 'meta_description', 'short_description'], $issues);
    seo_guard_check_keyword_stuffing($data, ['seo_title', 'meta_description'], $issues);

    return $data;
}

// ============================================================
// MODE: DESCRIPTION
// ============================================================

function seo_guard_validate_description(array $data, array &$issues): array
{
    if (!array_key_exists('product_description_html', $data)) {
        $issues[] = ['type' => 'FAIL', 'field' => 'product_description_html', 'message' => 'Missing product_description_html field.'];
        $data['product_description_html'] = '';
    }

    // Sanitize HTML — allow safe tags only
    $allowed = '<p><br><ul><ol><li><strong><em><b><i><h3><h4><span>';
    $data['product_description_html'] = strip_tags($data['product_description_html'], $allowed);

    // Enforce 1000 character limit (Amazon-grade: 900-1000 chars ideal)
    $descLen = mb_strlen($data['product_description_html']);
    if ($descLen > 1000) {
        $data['product_description_html'] = mb_substr($data['product_description_html'], 0, 1000);
        $issues[] = ['type' => 'WARN', 'field' => 'product_description_html', 'message' => "Description trimmed from {$descLen} to 1000 characters."];
    } elseif ($descLen < 900 && $descLen > 0) {
        $issues[] = ['type' => 'WARN', 'field' => 'product_description_html', 'message' => "Description is {$descLen} chars. Recommended: 900-1000 for optimal ranking."];
    }

    // Key bullets
    if (!isset($data['key_bullets']) || !is_array($data['key_bullets'])) {
        $data['key_bullets'] = [];
        $issues[] = ['type' => 'WARN', 'field' => 'key_bullets', 'message' => 'Missing key_bullets array.'];
    } else {
        $data['key_bullets'] = array_slice($data['key_bullets'], 0, 5);
        foreach ($data['key_bullets'] as $i => &$bullet) {
            $bullet = trim(strip_tags($bullet));
            if (mb_strlen($bullet) > 100) {
                $bullet = mb_substr($bullet, 0, 100);
                $issues[] = ['type' => 'WARN', 'field' => "key_bullets[{$i}]", 'message' => 'Bullet trimmed to 100 chars.'];
            }
        }
        unset($bullet);
    }

    // Check prohibited content
    $descText = strip_tags($data['product_description_html']);
    $tempData = ['_desc' => $descText];
    seo_guard_check_prohibited($tempData, ['_desc'], $issues);
    seo_guard_check_competitors($tempData, ['_desc'], $issues);

    return $data;
}

// ============================================================
// MODE: FEATURED
// ============================================================

function seo_guard_validate_featured(array $data, array &$issues): array
{
    // Tagline
    $data['featured_tagline'] = trim($data['featured_tagline'] ?? '');
    $data['featured_tagline'] = seo_guard_strip_emojis($data['featured_tagline']);
    if (empty($data['featured_tagline'])) {
        $issues[] = ['type' => 'FAIL', 'field' => 'featured_tagline', 'message' => 'Missing featured_tagline.'];
    } elseif (mb_strlen($data['featured_tagline']) > 60) {
        $data['featured_tagline'] = mb_substr($data['featured_tagline'], 0, 60);
        $issues[] = ['type' => 'WARN', 'field' => 'featured_tagline', 'message' => 'Tagline trimmed to 60 chars.'];
    }

    // Bullets — must be exactly 3
    if (!isset($data['featured_bullets']) || !is_array($data['featured_bullets'])) {
        $data['featured_bullets'] = [];
        $issues[] = ['type' => 'FAIL', 'field' => 'featured_bullets', 'message' => 'Missing featured_bullets array.'];
    } else {
        // Trim to 3
        if (count($data['featured_bullets']) < 3) {
            $issues[] = ['type' => 'WARN', 'field' => 'featured_bullets', 'message' => 'Expected 3 bullets, got ' . count($data['featured_bullets']) . '.'];
        }
        $data['featured_bullets'] = array_slice($data['featured_bullets'], 0, 3);
        foreach ($data['featured_bullets'] as $i => &$bullet) {
            $bullet = trim(strip_tags($bullet));
            $bullet = seo_guard_strip_emojis($bullet);
            if (mb_strlen($bullet) > 80) {
                $bullet = mb_substr($bullet, 0, 80);
                $issues[] = ['type' => 'WARN', 'field' => "featured_bullets[{$i}]", 'message' => 'Bullet trimmed to 80 chars.'];
            }
        }
        unset($bullet);
    }

    // Badge (optional)
    $data['featured_badge'] = trim($data['featured_badge'] ?? '');
    $data['featured_badge'] = seo_guard_strip_emojis($data['featured_badge']);
    if (mb_strlen($data['featured_badge']) > 30) {
        $data['featured_badge'] = mb_substr($data['featured_badge'], 0, 30);
        $issues[] = ['type' => 'WARN', 'field' => 'featured_badge', 'message' => 'Badge trimmed to 30 chars.'];
    }

    // Prohibited
    seo_guard_check_prohibited($data, ['featured_tagline', 'featured_badge'], $issues);
    foreach ($data['featured_bullets'] as $i => $b) {
        $tmpArr = ['_b' => $b];
        seo_guard_check_prohibited($tmpArr, ['_b'], $issues);
    }

    return $data;
}

// ============================================================
// SHARED HELPERS
// ============================================================

function seo_guard_strip_emojis(string $text): string
{
    // Remove emoji unicode ranges
    return preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F900}-\x{1F9FF}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u', '', $text);
}

function seo_guard_sanitize_slug(string $slug): string
{
    $slug = mb_strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

function seo_guard_ensure_unique_slug(string $slug, int $productId, array &$issues): string
{
    try {
        $db = get_db_connection();
        $original = $slug;
        $counter = 2;
        while (true) {
            $existing = db_fetch(
                'SELECT id FROM products WHERE slug = ? AND id != ?',
                [$slug, $productId]
            );
            if (!$existing) break;
            $slug = $original . '-' . $counter;
            $counter++;
        }
        if ($slug !== $original) {
            $issues[] = ['type' => 'WARN', 'field' => 'slug', 'message' => "Slug adjusted to \"{$slug}\" for uniqueness."];
        }
    } catch (Exception $e) {
        // Non-critical — uniqueness will be enforced on save anyway
    }
    return $slug;
}

function seo_guard_check_prohibited(array $data, array $fields, array &$issues): void
{
    foreach ($fields as $field) {
        $text = mb_strtolower($data[$field] ?? '');
        foreach (SEO_GUARD_PROHIBITED_CLAIMS as $claim) {
            if (mb_strpos($text, $claim) !== false) {
                $issues[] = [
                    'type'    => 'FAIL',
                    'field'   => $field,
                    'message' => "Prohibited claim detected: \"{$claim}\"",
                ];
            }
        }
    }
}

function seo_guard_check_competitors(array $data, array $fields, array &$issues): void
{
    foreach ($fields as $field) {
        $text = mb_strtolower($data[$field] ?? '');
        foreach (SEO_GUARD_COMPETITOR_NAMES as $name) {
            if (mb_strpos($text, $name) !== false) {
                $issues[] = [
                    'type'    => 'FAIL',
                    'field'   => $field,
                    'message' => "Competitor name detected: \"{$name}\"",
                ];
            }
        }
    }
}

function seo_guard_check_keyword_stuffing(array $data, array $fields, array &$issues): void
{
    foreach ($fields as $field) {
        $text = mb_strtolower($data[$field] ?? '');
        $words = preg_split('/\s+/', $text);
        $total = count($words);
        if ($total < 5) continue;

        $freq = array_count_values($words);
        foreach ($freq as $word => $count) {
            if (mb_strlen($word) < 4) continue; // Skip short words
            $density = $count / $total;
            if ($density > 0.15 && $count >= 3) {
                $pct = round($density * 100);
                $issues[] = [
                    'type'    => 'WARN',
                    'field'   => $field,
                    'message' => "Possible keyword stuffing: \"{$word}\" appears {$count} times ({$pct}% density).",
                ];
            }
        }
    }
}
