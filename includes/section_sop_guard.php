<?php
/**
 * Section SOP Guard — validates AI-generated product section content
 * against strict SOP rules before allowing admin to apply.
 *
 * Returns: ['status' => 'PASS'|'WARN'|'FAIL', 'issues' => [...], 'sanitized' => ...]
 */

/**
 * Main entry: validate section content by section_key.
 *
 * @param string       $sectionKey  highlights|storage|description|nutritional|shipping
 * @param mixed        $content     string (paragraph) or array (lines)
 * @param string       $productName Product name for context-aware checks
 * @return array       ['status', 'issues', 'sanitized']
 */
function validate_section_content(string $sectionKey, $content, string $productName = ''): array
{
    $issues = [];
    $sanitized = $content;

    switch ($sectionKey) {
        case 'highlights':
            return validate_highlights($content, $productName);
        case 'storage':
            return validate_storage($content);
        case 'description':
            return validate_description($content, $productName);
        case 'nutritional':
            return validate_nutritional($content);
        case 'shipping':
            return validate_shipping($content);
        default:
            return ['status' => 'WARN', 'issues' => ['Unknown section type: ' . $sectionKey], 'sanitized' => $content];
    }
}

/* ─── HIGHLIGHTS ─── */
function validate_highlights($content, string $productName = ''): array
{
    $issues = [];
    $lines = is_array($content) ? $content : array_filter(array_map('trim', explode("\n", $content)));
    $lines = array_values($lines);

    // Sanitize each line
    $sanitized = [];
    foreach ($lines as $line) {
        $line = sanitize_section_line($line);
        if ($line !== '') {
            $sanitized[] = $line;
        }
    }

    $count = count($sanitized);

    // Hard rules (FAIL)
    if ($count < 3) {
        $issues[] = ['level' => 'FAIL', 'msg' => "Minimum 3 highlights required, got {$count}"];
    }
    if ($count > 5) {
        $issues[] = ['level' => 'FAIL', 'msg' => "Maximum 5 highlights allowed, got {$count}"];
    }

    foreach ($sanitized as $i => $line) {
        $len = mb_strlen($line);
        if ($len > 60) {
            $issues[] = ['level' => 'FAIL', 'msg' => "Highlight #" . ($i + 1) . " exceeds 60 chars ({$len})"];
        }
        if (contains_emoji($line)) {
            $issues[] = ['level' => 'WARN', 'msg' => "Highlight #" . ($i + 1) . " contains emoji — will be stripped"];
            $sanitized[$i] = strip_emoji($line);
        }
    }

    // Check for false/medical claims
    $medicalClaims = check_prohibited_claims(implode(' ', $sanitized));
    foreach ($medicalClaims as $claim) {
        $issues[] = ['level' => 'FAIL', 'msg' => $claim];
    }

    // Check for competitor mentions
    $competitors = check_competitor_mentions(implode(' ', $sanitized));
    foreach ($competitors as $c) {
        $issues[] = ['level' => 'FAIL', 'msg' => $c];
    }

    return build_result($issues, $sanitized);
}

/* ─── STORAGE & SHELF LIFE ─── */
function validate_storage($content): array
{
    $issues = [];
    $lines = is_array($content) ? $content : array_filter(array_map('trim', explode("\n", $content)));
    $lines = array_values($lines);

    $sanitized = [];
    foreach ($lines as $line) {
        $line = sanitize_section_line($line);
        if ($line !== '') {
            $sanitized[] = $line;
        }
    }

    $count = count($sanitized);

    if ($count < 2) {
        $issues[] = ['level' => 'FAIL', 'msg' => "Minimum 2 storage lines required, got {$count}"];
    }
    if ($count > 4) {
        $issues[] = ['level' => 'WARN', 'msg' => "Recommend max 4 storage lines, got {$count}"];
    }

    foreach ($sanitized as $i => $line) {
        $len = mb_strlen($line);
        if ($len > 70) {
            $issues[] = ['level' => 'WARN', 'msg' => "Storage line #" . ($i + 1) . " exceeds 70 chars ({$len})"];
        }
    }

    return build_result($issues, $sanitized);
}

/* ─── PRODUCT DESCRIPTION ─── */
function validate_description($content, string $productName = ''): array
{
    $issues = [];
    $text = is_array($content) ? implode("\n", $content) : $content;
    $text = trim($text);

    // Sanitize
    $text = sanitize_section_text($text);

    $wordCount = str_word_count($text);

    if ($wordCount < 120) {
        $issues[] = ['level' => 'FAIL', 'msg' => "Description too short: {$wordCount} words (min 120)"];
    }
    if ($wordCount > 400) {
        $issues[] = ['level' => 'WARN', 'msg' => "Description is long: {$wordCount} words (recommended max 400)"];
    }

    // No competitor mentions
    $competitors = check_competitor_mentions($text);
    foreach ($competitors as $c) {
        $issues[] = ['level' => 'FAIL', 'msg' => $c];
    }

    // No keyword stuffing
    if ($productName !== '') {
        $stuffing = check_keyword_stuffing($text, $productName);
        if ($stuffing) {
            $issues[] = ['level' => 'WARN', 'msg' => $stuffing];
        }
    }

    // No medical/false claims
    $claims = check_prohibited_claims($text);
    foreach ($claims as $claim) {
        $issues[] = ['level' => 'FAIL', 'msg' => $claim];
    }

    // Check for emoji
    if (contains_emoji($text)) {
        $issues[] = ['level' => 'WARN', 'msg' => "Description contains emoji — will be stripped"];
        $text = strip_emoji($text);
    }

    return build_result($issues, $text);
}

/* ─── NUTRITIONAL & USAGE ─── */
function validate_nutritional($content): array
{
    $issues = [];
    $lines = is_array($content) ? $content : array_filter(array_map('trim', explode("\n", $content)));
    $lines = array_values($lines);

    $sanitized = [];
    foreach ($lines as $line) {
        $line = sanitize_section_line($line);
        if ($line !== '') {
            $sanitized[] = $line;
        }
    }

    $count = count($sanitized);

    if ($count < 2) {
        $issues[] = ['level' => 'FAIL', 'msg' => "Minimum 2 nutritional lines required, got {$count}"];
    }
    if ($count > 6) {
        $issues[] = ['level' => 'WARN', 'msg' => "Recommend max 6 nutritional lines, got {$count}"];
    }

    foreach ($sanitized as $i => $line) {
        $len = mb_strlen($line);
        if ($len > 80) {
            $issues[] = ['level' => 'WARN', 'msg' => "Nutritional line #" . ($i + 1) . " exceeds 80 chars ({$len})"];
        }
    }

    // No medical cure claims
    $claims = check_prohibited_claims(implode(' ', $sanitized));
    foreach ($claims as $claim) {
        $issues[] = ['level' => 'FAIL', 'msg' => $claim];
    }

    return build_result($issues, $sanitized);
}

/* ─── SHIPPING & RETURNS ─── */
function validate_shipping($content): array
{
    $issues = [];
    $lines = is_array($content) ? $content : array_filter(array_map('trim', explode("\n", $content)));
    $lines = array_values($lines);

    $sanitized = [];
    foreach ($lines as $line) {
        $line = sanitize_section_line($line);
        if ($line !== '') {
            $sanitized[] = $line;
        }
    }

    $count = count($sanitized);

    if ($count < 2) {
        $issues[] = ['level' => 'FAIL', 'msg' => "Minimum 2 shipping lines required, got {$count}"];
    }
    if ($count > 5) {
        $issues[] = ['level' => 'WARN', 'msg' => "Recommend max 5 shipping lines, got {$count}"];
    }

    foreach ($sanitized as $i => $line) {
        $len = mb_strlen($line);
        if ($len > 80) {
            $issues[] = ['level' => 'WARN', 'msg' => "Shipping line #" . ($i + 1) . " exceeds 80 chars ({$len})"];
        }
    }

    // Check: must not claim "no returns" unless that's the policy
    $combined = mb_strtolower(implode(' ', $sanitized));
    if (preg_match('/\bno\s+returns?\b/i', $combined)) {
        $issues[] = ['level' => 'WARN', 'msg' => "Contains 'no returns' — verify this matches your actual return policy"];
    }

    return build_result($issues, $sanitized);
}

/* ═══════════════════════════════════════
   SHARED UTILITY FUNCTIONS
   ═══════════════════════════════════════ */

function build_result(array $issues, $sanitized): array
{
    $hasHard = false;
    $hasWarn = false;
    $issueMessages = [];

    foreach ($issues as $issue) {
        $level = $issue['level'] ?? 'WARN';
        $msg = $issue['msg'] ?? '';
        $issueMessages[] = "[{$level}] {$msg}";
        if ($level === 'FAIL') $hasHard = true;
        if ($level === 'WARN') $hasWarn = true;
    }

    $status = 'PASS';
    if ($hasHard) $status = 'FAIL';
    elseif ($hasWarn) $status = 'WARN';

    return [
        'status' => $status,
        'issues' => $issueMessages,
        'sanitized' => $sanitized,
    ];
}

function sanitize_section_line(string $line): string
{
    $line = trim($line);
    $line = strip_emoji($line);
    // Remove multiple spaces
    $line = preg_replace('/\s{2,}/', ' ', $line);
    // Strip HTML
    $line = strip_tags($line);
    return $line;
}

function sanitize_section_text(string $text): string
{
    $text = trim($text);
    $text = strip_tags($text, '<br><p><ul><li><ol><strong><em><b><i>');
    $text = preg_replace('/\s{3,}/', "\n\n", $text);
    return $text;
}

function contains_emoji(string $text): bool
{
    return (bool) preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F900}-\x{1F9FF}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u', $text);
}

function strip_emoji(string $text): string
{
    return preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F900}-\x{1F9FF}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u', '', $text);
}

function check_prohibited_claims(string $text): array
{
    $issues = [];
    $lower = mb_strtolower($text);

    $hardPatterns = [
        '/\bcures?\b/i' => 'Contains claim "cure" — medical claims are prohibited',
        '/\btreats?\s+(cancer|diabetes|heart|disease|covid|tumor)/i' => 'Contains medical treatment claim — prohibited',
        '/\bguaranteed\s+(weight\s+loss|hair\s+growth|cure)/i' => 'Contains guaranteed outcome claim — prohibited',
        '/\bFDA\s+approved\b/i' => 'Claims FDA approval — prohibited unless verified',
        '/\bmedically\s+proven\b/i' => 'Claims "medically proven" — prohibited',
        '/\bclinically\s+proven\b/i' => 'Claims "clinically proven" — prohibited without evidence',
        '/\bprevents?\s+(cancer|diabetes|heart\s+disease)/i' => 'Claims disease prevention — prohibited',
        '/\b100\s*%\s*(safe|effective|guaranteed)\b/i' => 'Contains absolute guarantee claim — prohibited',
    ];

    foreach ($hardPatterns as $pattern => $message) {
        if (preg_match($pattern, $text)) {
            $issues[] = $message;
        }
    }

    return $issues;
}

function check_competitor_mentions(string $text): array
{
    $issues = [];
    $competitors = [
        'amazon', 'flipkart', 'bigbasket', 'jiomart', 'dmart', 'blinkit',
        'zepto', 'swiggy instamart', 'myntra', 'meesho', 'snapdeal',
    ];

    $lower = mb_strtolower($text);
    foreach ($competitors as $comp) {
        if (strpos($lower, $comp) !== false) {
            $issues[] = "Contains competitor name \"{$comp}\" — prohibited";
        }
    }

    return $issues;
}

function check_keyword_stuffing(string $text, string $keyword): ?string
{
    if ($keyword === '') return null;
    $lower = mb_strtolower($text);
    $kw = mb_strtolower(trim($keyword));
    $wordCount = str_word_count($lower);
    if ($wordCount === 0) return null;

    $kwCount = substr_count($lower, $kw);
    $density = ($kwCount / $wordCount) * 100;

    if ($density > 3.0) {
        return "Keyword \"{$keyword}\" appears {$kwCount} times (" . round($density, 1) . "% density) — possible stuffing";
    }
    return null;
}
