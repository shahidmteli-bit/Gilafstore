<?php
/**
 * AI Content Generation Endpoint
 * POST /admin/ajax/ai_generate.php
 *
 * Loads product facts from DB, calls configured AI provider,
 * validates output via SEO Guard, returns JSON.
 *
 * Params: product_id, mode (seo|description|featured), tone, language,
 *         target_keyword (optional), regenerate_seed (optional)
 */

// Suppress PHP warnings/notices from corrupting JSON output
error_reporting(E_ERROR);
ini_set('display_errors', '0');
set_time_limit(120); // Allow up to 2 minutes for AI API calls

// Global error handler — always return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Only handle fatal-ish errors
    if ($errno === E_ERROR || $errno === E_USER_ERROR) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'PHP Error: ' . $errstr]);
        exit;
    }
    return true; // suppress other errors
});

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/seo_guard.php';

require_admin();

header('Content-Type: application/json; charset=utf-8');
ob_start(); // Buffer output to prevent stray warnings from breaking JSON

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$productId      = (int)($input['product_id'] ?? 0);
$mode           = $input['mode'] ?? '';
$tone           = $input['tone'] ?? 'professional';
$language       = $input['language'] ?? 'English';
$targetKeyword  = trim($input['target_keyword'] ?? '');
$regenerateSeed = $input['regenerate_seed'] ?? null;

// Validate
if ($productId <= 0) {
    echo json_encode(['error' => 'Invalid product_id']);
    exit;
}
if (!in_array($mode, ['seo', 'description', 'featured'], true)) {
    echo json_encode(['error' => 'Invalid mode. Use: seo, description, featured']);
    exit;
}

// ============================================================
// 1. Load product facts from DB
// ============================================================
$db = get_db_connection();

$product = db_fetch('SELECT * FROM products WHERE id = ?', [$productId]);
if (!$product) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

// Category
$category = db_fetch('SELECT name FROM categories WHERE id = ?', [(int)$product['category_id']]);
$categoryName = $category['name'] ?? 'Uncategorized';

// Weights
$weights = db_fetch_all(
    'SELECT weight_value, weight_unit, display_weight, price FROM product_weights WHERE product_id = ? ORDER BY sort_order ASC',
    [$productId]
);
$weightsList = [];
foreach ($weights as $w) {
    $weightsList[] = ($w['display_weight'] ?: $w['weight_value'] . $w['weight_unit']) . ' — ₹' . $w['price'];
}

// Build product facts block
$facts = "Product Name: " . $product['name'] . "\n";
$facts .= "Category: " . $categoryName . "\n";
$facts .= "Brand: Gilaf Store\n";
if (!empty($product['description'])) {
    $facts .= "Description: " . mb_substr(strip_tags($product['description']), 0, 500) . "\n";
}
if (!empty($weightsList)) {
    $facts .= "Available Weights/Prices: " . implode('; ', $weightsList) . "\n";
}
if (!empty($product['ean'])) {
    $facts .= "EAN: " . $product['ean'] . "\n";
}
$facts .= "Website: https://gilafstore.com\n";

// ============================================================
// 2. Load AI settings from chatbot_settings table
// ============================================================
$aiSettings = [];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $aiSettings = $rows;
} catch (Exception $e) {
    echo json_encode(['error' => 'AI settings not configured. Go to Admin → Chatbot & AI Settings.']);
    exit;
}

$provider   = $aiSettings['ai_provider'] ?? 'gemini';
$apiKey     = $aiSettings['api_key'] ?? '';
$model      = $aiSettings['ai_model'] ?? 'gemini-2.0-flash';
$aiEnabled  = ($aiSettings['ai_enabled'] ?? '1') === '1';
$timeout    = (int)($aiSettings['response_timeout'] ?? 30);

if (!$aiEnabled || empty($apiKey)) {
    echo json_encode(['error' => 'AI is disabled or API key not configured. Go to Admin → Chatbot & AI Settings.']);
    exit;
}

// ============================================================
// 3-6: Build prompt, call AI, parse, validate — wrapped in try-catch
// ============================================================
try {
    $prompt = build_ai_prompt($mode, $facts, $tone, $language, $targetKeyword, $regenerateSeed);

    $aiResponse = call_ai_provider($provider, $apiKey, $model, $prompt, $timeout);

    if (!$aiResponse['success']) {
        log_ai_generation($db, $productId, $mode, $prompt, $aiResponse['raw'] ?? '', null, null);
        ob_end_clean();
        echo json_encode(['error' => 'AI API error: ' . $aiResponse['error']]);
        exit;
    }

    $rawText = $aiResponse['text'];

    $parsed = parse_ai_json($rawText);
    if ($parsed === null) {
        log_ai_generation($db, $productId, $mode, $prompt, $rawText, 'FAIL', [['type' => 'FAIL', 'field' => '_parse', 'message' => 'AI did not return valid JSON']]);
        ob_end_clean();
        echo json_encode([
            'status' => 'FAIL',
            'issues' => [['type' => 'FAIL', 'field' => '_parse', 'message' => 'AI returned invalid JSON. Try Regenerate.']],
            'data'   => null,
            'raw'    => mb_substr($rawText, 0, 500),
        ]);
        exit;
    }

    $result = seo_guard_validate($parsed, $mode, $productId);
    log_ai_generation($db, $productId, $mode, $prompt, $rawText, $result['status'], $result['issues']);

    ob_end_clean();
    echo json_encode($result);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
} catch (Throwable $t) {
    ob_end_clean();
    echo json_encode(['error' => 'Fatal error: ' . $t->getMessage()]);
    exit;
}


// ============================================================
// HELPER FUNCTIONS
// ============================================================

function build_ai_prompt(string $mode, string $facts, string $tone, string $language, string $keyword, $seed): string
{
    $variation = '';
    if ($seed) {
        $variation = "\nIMPORTANT: Generate a COMPLETELY DIFFERENT variation from previous attempts. Variation seed: {$seed}. Use different angles, word choices, and structure.\n";
    }

    $keywordNote = '';
    if (!empty($keyword)) {
        $keywordNote = "\nPRIMARY Target Keyword: \"" . $keyword . "\" — this MUST appear naturally in the title, description, and slug. Do NOT stuff it, but ensure it is present.\n";
    }

    // === SYSTEM ROLE: Amazon A9/A10 SEO Expert ===
    $base = "You are an Amazon A9/A10 algorithm expert, eCommerce growth strategist, and conversion copywriter for Gilaf Store (gilafstore.com), a premium Indian eCommerce brand specializing in authentic Kashmiri products — saffron, honey, spices, dry fruits, and teas.\n\n";

    $base .= "Target Market: India. Goal: Maximize organic ranking + click-through rate (CTR) + conversion rate (CVR).\n\n";

    // === STEP 1: ALGORITHM-AWARE KEYWORD RESEARCH ===
    $base .= "=== STEP 1: AMAZON A9/A10 KEYWORD RESEARCH (do this mentally FIRST) ===\n";
    $base .= "Before writing ANY content, perform deep keyword research analyzing:\n\n";

    $base .= "A9/A10 RANKING FACTORS YOU MUST OPTIMIZE FOR:\n";
    $base .= "- RELEVANCE: Title keyword match, backend search terms, bullet point keywords\n";
    $base .= "- PERFORMANCE: CTR from search results, conversion rate, sales velocity\n";
    $base .= "- CONTENT QUALITY: Title length (120-150 chars max), 5 bullet points, 1000+ char description\n\n";

    $base .= "1. HIGH-VOLUME KEYWORD EXTRACTION:\n";
    $base .= "   - Short-tail (1-2 words): \"kashmiri honey\", \"pure saffron\", \"green tea\"\n";
    $base .= "   - Mid-tail (2-3 words): \"organic kashmiri honey\", \"premium CTC tea\", \"pure dry fruits\"\n";
    $base .= "   - Long-tail (4+ words, buyer intent): \"buy pure kashmiri honey online india\", \"best organic saffron for cooking\"\n";
    $base .= "   - Misspellings and regional: \"kesar\", \"shahad\", \"kashmeer\", \"kehwa\"\n\n";

    $base .= "2. MULTI-PLATFORM ANALYSIS:\n";
    $base .= "   - Amazon India: What titles do top 5 listings use? What keywords appear in their bullets?\n";
    $base .= "   - Google Shopping: What product titles rank on page 1?\n";
    $base .= "   - Flipkart, BigBasket, JioMart: Secondary keyword sources\n\n";

    $base .= "3. BUYER INTENT KEYWORDS (prioritize these):\n";
    $base .= "   - Purchase intent: \"buy\", \"order\", \"price\", \"online\", \"best\", \"top\", \"premium\"\n";
    $base .= "   - Comparison: \"vs\", \"alternative\", \"which is better\"\n";
    $base .= "   - Use-case: \"for cooking\", \"for tea\", \"for gifting\", \"for health\"\n";
    $base .= "   - Trust: \"original\", \"pure\", \"authentic\", \"100%\", \"natural\", \"organic\", \"no additives\"\n\n";

    $base .= "4. SEASONAL AND TRENDING:\n";
    $base .= "   - Festival gifting: Diwali, Eid, Christmas, wedding, housewarming\n";
    $base .= "   - Health trends: immunity, antioxidant, superfood, clean eating\n";
    $base .= "   - Farm-to-table, artisan, small-batch, handpicked\n\n";

    // === STEP 2: CONTENT RULES ===
    $base .= "=== STEP 2: STRICT CONTENT RULES ===\n\n";

    $base .= "ABSOLUTE RULES:\n";
    $base .= "- Return ONLY valid JSON. No markdown, no explanations, no code fences, no commentary.\n";
    $base .= "- Base content on the provided product facts. Do not invent false claims.\n";
    $base .= "- NO medical/health claims (cure, treat, heal, prevent disease, FDA approved, clinically proven)\n";
    $base .= "- NO competitor brand names in output\n";
    $base .= "- NO emojis in any field\n";
    $base .= "- NO keyword stuffing — keep it natural and readable\n";
    $base .= "- Use natural, fluent " . $language . " language\n";
    $base .= "- Tone: " . $tone . "\n\n";

    $base .= "CONVERSION PSYCHOLOGY:\n";
    $base .= "- Lead with benefits, not features (what does the customer GAIN?)\n";
    $base .= "- Use power words: authentic, premium, handpicked, artisan, pure, natural, farm-fresh, heritage\n";
    $base .= "- Trust signals: \"directly sourced from Kashmir\", \"quality tested\", \"freshness sealed\"\n";
    $base .= "- Sensory language: taste, aroma, color, texture (makes customer visualize)\n";
    $base .= "- Subtle urgency: \"limited harvest\", \"seasonal\", \"small-batch\"\n";
    $base .= "- Social proof language: \"loved by\", \"preferred by\", \"trusted by thousands\"\n";
    $base .= $keywordNote . $variation . "\n";

    $base .= "=== PRODUCT FACTS (foundation for all content) ===\n";
    $base .= $facts . "\n\n";

    // === MODE-SPECIFIC TASKS ===
    switch ($mode) {
        case 'seo':
            $base .= "=== TASK: Generate Amazon-grade SEO metadata + backend search terms ===\n\n";
            $base .= "Return this exact JSON structure:\n";
            $base .= "{\n";
            $base .= "  \"slug\": \"<url-friendly slug, primary keyword, lowercase hyphens only, max 60 chars>\",\n";
            $base .= "  \"seo_title\": \"<120-150 character Amazon-style title (STRICT MAX 150 chars): Brand + Product + Key Attribute + Size/Variant + Benefit + Use Case. Front-load highest-volume keyword.>\",\n";
            $base .= "  \"meta_description\": \"<Google SERP meta description, 150-160 chars, primary keyword + CTA + unique benefit>\",\n";
            $base .= "  \"short_description\": \"<social media preview, max 200 chars, scroll-stopping>\",\n";
            $base .= "  \"social_image_url\": \"\",\n";
            $base .= "  \"seo_keywords\": \"<comma-separated 10-15 high-ranking keywords from research including short-tail, mid-tail, and long-tail>\",\n";
            $base .= "  \"backend_search_terms\": \"<250 chars max of hidden keywords NOT already in title or bullets: include misspellings, regional terms, Hindi/Urdu variants, synonyms, related searches>\"\n";
            $base .= "}\n\n";

            $base .= "AMAZON A9 TITLE OPTIMIZATION (CRITICAL):\n";
            $base .= "- MUST be 120-150 characters (STRICT MAXIMUM 150 chars — anything over 150 will be rejected)\n";
            $base .= "- Structure: [Brand] [Product Type] - [Key Attribute] | [Size/Weight] | [Primary Benefit] | [Use Case]\n";
            $base .= "- Example: \"Gilaf Pure Kashmiri Honey - 100% Raw Natural Honey from Kashmir Valley | 500g | Rich Aroma & Taste\"\n";
            $base .= "- Front-load the HIGHEST VOLUME keyword in first 80 characters (mobile cutoff)\n";
            $base .= "- Use pipes | to separate keyword clusters for readability\n";
            $base .= "- Include: brand, product type, key attributes, weight/size, benefits, use case\n\n";

            $base .= "BACKEND SEARCH TERMS (CRITICAL for hidden ranking):\n";
            $base .= "- Max 250 characters, single space separated, no commas needed\n";
            $base .= "- Include ONLY keywords NOT already in the title or bullet points\n";
            $base .= "- Must include: common misspellings (e.g., \"kasmiri\" \"kashmeer\" \"shahad\" \"shehad\")\n";
            $base .= "- Regional language terms (Hindi, Urdu equivalents)\n";
            $base .= "- Synonym variations (e.g., for honey: \"madhu\" \"apis\" \"raw honey\")\n";
            $base .= "- Related search terms customers might use\n";
            $base .= "- NO brand names, no ASINs, no subjective claims like \"best\"\n\n";

            $base .= "SEO KEYWORDS field:\n";
            $base .= "- 10-15 comma-separated keywords for meta keywords tag\n";
            $base .= "- Mix of: 3-4 short-tail, 4-5 mid-tail, 3-4 long-tail buyer-intent keywords\n\n";

            $base .= "OTHER FIELDS:\n";
            $base .= "- slug: primary keyword, derived from product name, e.g., \"pure-kashmiri-honey-500g\"\n";
            $base .= "- meta_description: 150-160 chars, [Hook] + [Product] + [Benefit] + [CTA]\n";
            $base .= "- short_description: #1 selling point for social sharing\n";
            $base .= "- social_image_url: leave empty string \"\"\n";
            break;

        case 'description':
            $base .= "=== TASK: Generate Amazon-grade product description + 5 conversion bullet points ===\n\n";
            $base .= "Return this exact JSON structure:\n";
            $base .= "{\n";
            $base .= "  \"product_description_html\": \"<rich HTML product description, MUST be 900-1000 characters including tags>\",\n";
            $base .= "  \"key_bullets\": [\"<bullet 1>\", \"<bullet 2>\", \"<bullet 3>\", \"<bullet 4>\", \"<bullet 5>\"]\n";
            $base .= "}\n\n";

            $base .= "DESCRIPTION REQUIREMENTS (Amazon A9 optimized):\n";
            $base .= "- MUST be between 900 and 1000 characters (including HTML tags). This is CRITICAL.\n";
            $base .= "- Count characters carefully. Too short hurts ranking. Over 1000 is rejected.\n\n";

            $base .= "DESCRIPTION STRUCTURE:\n";
            $base .= "Paragraph 1 (Hook + Primary Keyword): Open with the primary keyword naturally. What makes this product special? Use sensory language — taste, aroma, color, texture. Create desire.\n\n";
            $base .= "Paragraph 2 (Origin + Quality + Trust): Kashmir valley sourcing, traditional methods, quality testing, purity. Include secondary keywords. Build authenticity.\n\n";
            $base .= "Paragraph 3 (Usage + Benefits): How to use, serving suggestions, recipes, pairings. Include long-tail keywords. Make the customer visualize using it daily.\n\n";
            $base .= "Paragraph 4 (Brand Trust + CTA): Gilaf Store direct sourcing, freshness guarantee, premium packaging. Soft CTA — \"Order today\", \"Experience the difference\".\n\n";

            $base .= "HTML RULES:\n";
            $base .= "- Use ONLY these tags: <p>, <ul>, <ol>, <li>, <strong>, <em>, <h3>, <h4>\n";
            $base .= "- Wrap keywords in <strong> tags (helps indexing)\n";
            $base .= "- Use <ul>/<li> for scannable lists (improves time-on-page)\n";
            $base .= "- NO <br> tags between paragraphs — use separate <p> tags\n\n";

            $base .= "BULLET POINTS (5 REQUIRED — Amazon standard):\n";
            $base .= "- Exactly 5 bullet points, each max 100 characters\n";
            $base .= "- Each bullet MUST start with a CAPITALIZED BENEFIT PHRASE followed by details:\n";
            $base .= "  Bullet 1: PRIMARY BENEFIT — key quality/taste differentiator\n";
            $base .= "  Bullet 2: PURITY/AUTHENTICITY — sourcing and quality trust signal\n";
            $base .= "  Bullet 3: HEALTH/WELLNESS — nutritional or wellness benefit (no medical claims)\n";
            $base .= "  Bullet 4: USAGE/VERSATILITY — how/where to use, occasions\n";
            $base .= "  Bullet 5: PACKAGING/GUARANTEE — freshness seal, delivery promise\n";
            $base .= "- Include relevant keywords naturally in each bullet\n";
            $base .= "- Use conversion psychology: benefit-first, then feature\n\n";

            $base .= "KEYWORD INTEGRATION:\n";
            $base .= "- Primary keyword in paragraph 1 and at least one <strong> tag\n";
            $base .= "- Secondary keywords spread across paragraphs 2-4\n";
            $base .= "- Long-tail keywords in paragraph 3 (usage section)\n";
            $base .= "- Natural density — never forced or repetitive\n";
            break;

        case 'featured':
            $base .= "=== TASK: Generate featured/promotional content for homepage and category pages ===\n\n";
            $base .= "Return this exact JSON structure:\n";
            $base .= "{\n";
            $base .= "  \"featured_tagline\": \"<catchy tagline with primary keyword, max 60 chars, no emojis>\",\n";
            $base .= "  \"featured_bullets\": [\"<point 1>\", \"<point 2>\", \"<point 3>\"],\n";
            $base .= "  \"featured_badge\": \"<short badge like Bestseller or Premium or New Arrival, max 30 chars>\",\n";
            $base .= "  \"image_strategy\": [\"<image 1 description + text overlay>\", \"<image 2>\", \"<image 3>\", \"<image 4>\", \"<image 5>\", \"<image 6>\"]\n";
            $base .= "}\n\n";

            $base .= "RULES:\n";
            $base .= "- featured_tagline: max 60 chars, include primary keyword, create desire\n";
            $base .= "  Examples: \"Taste the Essence of Kashmir's Finest Saffron\", \"Pure Raw Kashmiri Honey — Nature's Gold\"\n";
            $base .= "- featured_bullets: exactly 3 items, each max 80 chars, benefit-focused:\n";
            $base .= "  Bullet 1: Key product benefit (quality/taste)\n";
            $base .= "  Bullet 2: Trust signal (sourcing/authenticity)\n";
            $base .= "  Bullet 3: Value proposition (packaging/freshness/delivery)\n";
            $base .= "- featured_badge: 1-2 words that create urgency or premium feel\n\n";

            $base .= "IMAGE STRATEGY (6 images for maximum CTR + conversion):\n";
            $base .= "- Image 1: MAIN IMAGE — Product on pure white background (1500x1500px min), product fills 85% of frame, high-res studio quality\n";
            $base .= "- Image 2: INFOGRAPHIC — Key benefits overlaid: \"100% Pure\", \"From Kashmir Valley\", \"Lab Tested\", weight/size callout\n";
            $base .= "- Image 3: LIFESTYLE — Product in use (e.g., honey drizzling on toast, saffron in milk). Text overlay: \"Perfect for [main use case]\"\n";
            $base .= "- Image 4: INGREDIENTS/SOURCING — Show origin story. Text: \"Directly Sourced from [Region]\", \"Traditional Harvesting\"\n";
            $base .= "- Image 5: SIZE/COMPARISON — Product next to common object for scale. Packaging details. Text: \"[Weight] of Pure [Product]\"\n";
            $base .= "- Image 6: TRUST/CERTIFICATION — Quality seals, lab reports visual, freshness guarantee. Text: \"Quality Tested\", \"Freshness Sealed\"\n";
            $base .= "For each image, provide: exact description of what to photograph + exact text to overlay on the image\n";
            break;
    }

    return $base;
}


function call_ai_provider(string $provider, string $apiKey, string $model, string $prompt, int $timeout): array
{
    if ($provider === 'gemini') {
        return call_gemini($apiKey, $model, $prompt, $timeout);
    } elseif ($provider === 'openai') {
        return call_openai($apiKey, $model, $prompt, $timeout);
    } elseif ($provider === 'claude') {
        return call_claude($apiKey, $model, $prompt, $timeout);
    }
    return ['success' => false, 'error' => 'Unknown AI provider: ' . $provider];
}


function call_gemini(string $apiKey, string $model, string $prompt, int $timeout): array
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature'    => 0.5,
            'maxOutputTokens' => 2048,
            'responseMimeType' => 'application/json',
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => "Connection error: {$curlErr}", 'raw' => ''];
    }
    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
        return ['success' => false, 'error' => $msg, 'raw' => $response];
    }

    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($text)) {
        return ['success' => false, 'error' => 'Empty AI response', 'raw' => $response];
    }

    return ['success' => true, 'text' => $text, 'raw' => $response];
}


function call_openai(string $apiKey, string $model, string $prompt, int $timeout): array
{
    $url = 'https://api.openai.com/v1/chat/completions';

    $body = [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => 'You are an SEO copywriter. Return ONLY valid JSON, no markdown.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.5,
        'max_tokens'  => 2048,
        'response_format' => ['type' => 'json_object'],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => "Connection error: {$curlErr}", 'raw' => ''];
    }
    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['error']['message'] ?? "HTTP {$httpCode}";
        return ['success' => false, 'error' => $msg, 'raw' => $response];
    }

    $decoded = json_decode($response, true);
    $text = $decoded['choices'][0]['message']['content'] ?? '';

    if (empty($text)) {
        return ['success' => false, 'error' => 'Empty AI response', 'raw' => $response];
    }

    return ['success' => true, 'text' => $text, 'raw' => $response];
}


function call_claude(string $apiKey, string $model, string $prompt, int $timeout): array
{
    $url = 'https://api.anthropic.com/v1/messages';

    $body = [
        'model'      => $model,
        'max_tokens' => 2048,
        'messages'   => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => max($timeout, 45),
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    // SSL cert fallback for XAMPP/Windows
    $caBundle = ini_get('curl.cainfo');
    if (empty($caBundle) || !file_exists($caBundle)) {
        $possibleCerts = [
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        ];
        foreach ($possibleCerts as $cert) {
            if (file_exists($cert)) {
                curl_setopt($ch, CURLOPT_CAINFO, $cert);
                break;
            }
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => "Connection error: {$curlErr}", 'raw' => ''];
    }
    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['error']['message'] ?? $decoded['message'] ?? "HTTP {$httpCode}";
        return ['success' => false, 'error' => $msg, 'raw' => $response];
    }

    $decoded = json_decode($response, true);
    $text = $decoded['content'][0]['text'] ?? '';

    if (empty($text)) {
        return ['success' => false, 'error' => 'Empty AI response', 'raw' => $response];
    }

    return ['success' => true, 'text' => $text, 'raw' => $response];
}


function parse_ai_json(string $text): ?array
{
    // Try direct parse
    $parsed = json_decode($text, true);
    if (is_array($parsed)) return $parsed;

    // Try extracting JSON from markdown code fences
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $m)) {
        $parsed = json_decode($m[1], true);
        if (is_array($parsed)) return $parsed;
    }

    // Try extracting first { ... } block
    if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
        $parsed = json_decode($m[0], true);
        if (is_array($parsed)) return $parsed;
    }

    return null;
}


function log_ai_generation(PDO $db, int $productId, string $mode, string $request, string $rawResponse, ?string $status, ?array $issues): void
{
    try {
        $stmt = $db->prepare(
            'INSERT INTO ai_generation_logs (product_id, mode, request_json, ai_raw_response, validated_status, issues_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $productId,
            $mode,
            json_encode(['prompt' => mb_substr($request, 0, 5000)]),
            mb_substr($rawResponse, 0, 10000),
            $status,
            $issues ? json_encode($issues) : null,
        ]);
    } catch (Exception $e) {
        error_log('AI log write failed: ' . $e->getMessage());
    }
}
