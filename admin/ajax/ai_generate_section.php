<?php
/**
 * AI Section Content Generator — endpoint for manage_product_sections.php
 *
 * POST params:
 *   product_id      (int)    — required
 *   section_key     (string) — highlights|storage|description|nutritional|shipping
 *   tone            (string) — professional|simple|premium
 *   language        (string) — english|hindi
 *   target_keyword  (string) — optional, for description only
 *   regenerate_seed (int)    — optional, randomness seed for regenerate
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/section_sop_guard.php';

require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$productId     = (int)($_POST['product_id'] ?? 0);
$sectionKey    = trim($_POST['section_key'] ?? '');
$tone          = trim($_POST['tone'] ?? 'professional');
$language      = trim($_POST['language'] ?? 'english');
$targetKeyword = trim($_POST['target_keyword'] ?? '');
$regenerateSeed = (int)($_POST['regenerate_seed'] ?? 0);

$validSections = ['highlights', 'storage', 'description', 'nutritional', 'shipping'];
if (!$productId || !in_array($sectionKey, $validSections, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid product_id or section_key']);
    exit;
}

// ─── Load product facts ───
$product = db_fetch('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [$productId]);
if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

// Load weights
$weights = db_fetch_all('SELECT display_weight, price FROM product_weights WHERE product_id = ? ORDER BY sort_order ASC', [$productId]);
$weightList = array_map(fn($w) => $w['display_weight'] . ' (₹' . number_format((float)$w['price'], 0) . ')', $weights);

// Load existing highlights
$existingHighlights = db_fetch_all('SELECT highlight_text FROM product_highlights WHERE product_id = ? ORDER BY display_order ASC', [$productId]);
$highlightTexts = array_column($existingHighlights, 'highlight_text');

// Load existing section content
$existingSection = db_fetch('SELECT content FROM product_sections WHERE product_id = ? AND section_type = ?', [$productId, $sectionKey]);
$existingContent = $existingSection['content'] ?? '';

// ─── Load AI settings from DB ───
$aiSettings = [];
try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM chatbot_settings");
    $stmt->execute();
    $aiSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to load AI settings']);
    exit;
}

$apiKey     = $aiSettings['api_key'] ?? '';
$aiProvider = $aiSettings['ai_provider'] ?? 'gemini';
$aiModel    = $aiSettings['ai_model'] ?? 'gemini-2.0-flash';
$temperature = floatval($aiSettings['temperature'] ?? 0.7);

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'AI API key not configured. Go to Admin → Chatbot & AI Settings.']);
    exit;
}

// ─── Build product context ───
$productContext = "Product Name: {$product['name']}\n";
$productContext .= "Category: " . ($product['category_name'] ?? 'N/A') . "\n";
$productContext .= "Brand: " . ($product['brand'] ?? 'Gilaf') . "\n";
if (!empty($product['description'])) {
    $productContext .= "Current Description: " . mb_substr($product['description'], 0, 300) . "\n";
}
if (!empty($product['ingredients'])) {
    $productContext .= "Ingredients: {$product['ingredients']}\n";
}
if (!empty($weightList)) {
    $productContext .= "Weight Options: " . implode(', ', $weightList) . "\n";
}
if (!empty($highlightTexts)) {
    $productContext .= "Existing Highlights: " . implode('; ', $highlightTexts) . "\n";
}
if (!empty($existingContent)) {
    $productContext .= "Existing {$sectionKey} content: " . mb_substr($existingContent, 0, 300) . "\n";
}
if (!empty($product['food_type'])) {
    $productContext .= "Food Type: {$product['food_type']}\n";
}

// ─── Build section-specific prompt ───
$toneDesc = match($tone) {
    'simple'  => 'Use simple, easy-to-understand language suitable for all audiences.',
    'premium' => 'Use elegant, premium brand language that conveys luxury and quality.',
    default   => 'Use professional, factual language suitable for an ecommerce product listing.',
};

$langInstruction = $language === 'hindi'
    ? 'Write ALL content in Hindi (Devanagari script). Do NOT mix English unless it is a proper noun or brand name.'
    : 'Write ALL content in English.';

$seedNote = $regenerateSeed > 0 ? " Use variation seed {$regenerateSeed} to produce a different version than before." : '';

$prompt = build_section_prompt($sectionKey, $productContext, $toneDesc, $langInstruction, $targetKeyword, $seedNote);

// ─── Call AI provider ───
$aiResponse = call_ai_provider($aiProvider, $apiKey, $aiModel, $temperature, $prompt);

if (!$aiResponse['success']) {
    echo json_encode(['success' => false, 'error' => 'AI call failed: ' . $aiResponse['error']]);
    exit;
}

$rawText = $aiResponse['text'];

// ─── Parse JSON from AI response ───
$parsed = parse_ai_json_response($rawText, $sectionKey);
if ($parsed === null) {
    echo json_encode([
        'success' => false,
        'error' => 'AI returned invalid JSON. Raw response: ' . mb_substr($rawText, 0, 500),
    ]);
    exit;
}

// ─── Validate with SOP guard ───
$validation = validate_section_content($sectionKey, $parsed['content'], $product['name']);

// ─── Log generation attempt ───
try {
    $db = get_db_connection();
    $logStmt = $db->prepare("INSERT INTO ai_generation_logs (product_id, mode, ai_provider, ai_model, prompt_text, raw_response, validation_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $logStmt->execute([
        $productId,
        'section_' . $sectionKey,
        $aiProvider,
        $aiModel,
        mb_substr($prompt, 0, 2000),
        mb_substr($rawText, 0, 5000),
        $validation['status'],
    ]);
} catch (PDOException $e) {
    // Logging failure is non-fatal
}

// ─── Return response ───
echo json_encode([
    'success'   => true,
    'section_key' => $sectionKey,
    'content'   => $validation['sanitized'],
    'status'    => $validation['status'],
    'issues'    => $validation['issues'],
    'raw'       => $rawText,
]);
exit;


/* ═══════════════════════════════════════════════
   HELPER FUNCTIONS
   ═══════════════════════════════════════════════ */

function build_section_prompt(string $key, string $context, string $tone, string $lang, string $keyword, string $seed): string
{
    $rulesMap = [
        'highlights' => "Generate product highlights (bullet points).\n"
            . "REQUIREMENTS:\n"
            . "- Return EXACTLY 3 to 5 items as a JSON array of strings.\n"
            . "- Each item must be 60 characters or less.\n"
            . "- No emojis, no false medical/cure claims, no competitor names.\n"
            . "- Highlight genuine product benefits, ingredients, quality aspects.\n"
            . "- Each highlight must be unique — no repetition.",

        'storage' => "Generate storage and shelf life instructions.\n"
            . "REQUIREMENTS:\n"
            . "- Return 2 to 4 items as a JSON array of strings.\n"
            . "- Each item must be 70 characters or less.\n"
            . "- Include storage conditions (temperature, sunlight, moisture).\n"
            . "- Include shelf life or expiry reference if applicable.\n"
            . "- Be factual and specific to this product type.",

        'description' => "Generate a product description paragraph.\n"
            . "REQUIREMENTS:\n"
            . "- Return a single string (NOT an array).\n"
            . "- Must be 120-400 words.\n"
            . "- Simple paragraphs, can include natural bullet points.\n"
            . "- No competitor names (Amazon, Flipkart, etc.).\n"
            . "- No keyword stuffing — mention the product name naturally.\n"
            . "- No medical/cure claims. No false guarantees.\n"
            . "- No emojis.\n"
            . "- Focus on product origin, quality, taste, usage, and value.",

        'nutritional' => "Generate nutritional and usage information lines.\n"
            . "REQUIREMENTS:\n"
            . "- Return 2 to 6 items as a JSON array of strings.\n"
            . "- Each item must be 80 characters or less.\n"
            . "- Include nutritional benefits, usage suggestions, serving ideas.\n"
            . "- No medical cure claims (do NOT say \"cures\", \"treats disease\", etc.).\n"
            . "- Be specific to this product's ingredients and category.",

        'shipping' => "Generate shipping and returns information lines.\n"
            . "REQUIREMENTS:\n"
            . "- Return 2 to 5 items as a JSON array of strings.\n"
            . "- Each item must be 80 characters or less.\n"
            . "- Include domestic delivery timeline (3-5 business days).\n"
            . "- Mention international delivery availability if relevant.\n"
            . "- Return policy: \"Returns accepted only if damaged or wrong product delivered.\"\n"
            . "- Do NOT say \"no returns\" — instead state the actual conditional return policy.",
    ];

    $rules = $rulesMap[$key] ?? 'Generate appropriate content for this section.';

    if ($key === 'description' && $keyword !== '') {
        $rules .= "\n- Naturally incorporate the keyword \"{$keyword}\" 1-2 times.";
    }

    $jsonFormat = ($key === 'description')
        ? '{"section_key": "' . $key . '", "content": "your paragraph text here..."}'
        : '{"section_key": "' . $key . '", "content": ["line 1", "line 2", "line 3"]}';

    $prompt = "You are a product content writer for Gilaf Store, an Indian ecommerce store specializing in premium dry fruits, spices, honey, and natural foods.\n\n"
        . "{$tone}\n{$lang}\n{$seed}\n\n"
        . "PRODUCT INFORMATION:\n{$context}\n\n"
        . "TASK:\n{$rules}\n\n"
        . "OUTPUT FORMAT:\n"
        . "You MUST return ONLY valid JSON, nothing else. No markdown, no explanation, no extra text.\n"
        . "Format:\n{$jsonFormat}\n\n"
        . "Return ONLY the JSON object. No code fences, no backticks.";

    return $prompt;
}

function call_ai_provider(string $provider, string $apiKey, string $model, float $temperature, string $prompt): array
{
    if ($provider === 'openai') {
        return call_openai($apiKey, $model, $temperature, $prompt);
    }
    return call_gemini($apiKey, $model, $temperature, $prompt);
}

function call_gemini(string $apiKey, string $model, float $temperature, string $prompt): array
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $data = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => 2048,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => "cURL error: {$error}"];
    }
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP {$httpCode}: " . mb_substr($response, 0, 300)];
    }

    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($text)) {
        return ['success' => false, 'error' => 'Empty response from Gemini'];
    }

    return ['success' => true, 'text' => $text];
}

function call_openai(string $apiKey, string $model, float $temperature, string $prompt): array
{
    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => 'You are a product content writer. Return ONLY valid JSON.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature' => $temperature,
        'max_tokens'  => 2048,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => "cURL error: {$error}"];
    }
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP {$httpCode}: " . mb_substr($response, 0, 300)];
    }

    $json = json_decode($response, true);
    $text = $json['choices'][0]['message']['content'] ?? '';

    if (empty($text)) {
        return ['success' => false, 'error' => 'Empty response from OpenAI'];
    }

    return ['success' => true, 'text' => $text];
}

function parse_ai_json_response(string $rawText, string $sectionKey): ?array
{
    // Strip markdown fences if present
    $cleaned = $rawText;
    $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
    $cleaned = preg_replace('/\s*```\s*$/', '', $cleaned);
    $cleaned = trim($cleaned);

    $parsed = json_decode($cleaned, true);

    if ($parsed && isset($parsed['content'])) {
        return $parsed;
    }

    // Try to extract JSON object from the text
    if (preg_match('/\{[\s\S]*"content"\s*:[\s\S]*\}/U', $rawText, $matches)) {
        $parsed = json_decode($matches[0], true);
        if ($parsed && isset($parsed['content'])) {
            return $parsed;
        }
    }

    // Last resort: try greedy match
    if (preg_match('/\{[\s\S]*"content"\s*:[\s\S]*\}/', $rawText, $matches)) {
        $parsed = json_decode($matches[0], true);
        if ($parsed && isset($parsed['content'])) {
            return $parsed;
        }
    }

    return null;
}
