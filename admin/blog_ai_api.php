<?php
/**
 * Enterprise AI Blog Publishing Engine
 * Professional AI content generation with validation, scoring, auto-retry, and logging
 */

@ini_set('display_errors', '0');
@ini_set('memory_limit', '256M');
@set_time_limit(300); // 5 minutes — large content generation needs time

ob_start(); // Buffer all output so stray warnings don't break JSON

// Shutdown handler: if PHP dies (timeout/memory) return JSON not HTML
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $err['message']]);
    }
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = get_db_connection();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

// Load base API key from chatbot_settings
$apiKey = '';
try {
    $row = $db->query("SELECT setting_value FROM chatbot_settings WHERE setting_key='api_key' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $apiKey = $row['setting_value'] ?? '';
} catch (Exception $e) {}

if (empty($apiKey) && !in_array($action, ['get_settings', 'save_settings', 'get_cta_templates', 'get_internal_links', 'generate_thumbnail', 'save_featured_image'])) {
    echo json_encode(['success' => false, 'message' => 'AI API key not configured. Please set it in AI Settings.']);
    exit;
}

switch ($action) {
    case 'research':            handleResearch($input, $db, $apiKey); break;
    case 'generate':            handleGenerate($input, $db, $apiKey); break;
    case 'suggest_titles':      handleSuggestTitles($input, $db, $apiKey); break;
    case 'generate_thumbnail':  handleGenerateThumbnail($input, $db, $apiKey); break;
    case 'save_featured_image':  handleSaveFeaturedImage($input, $db); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ============================================================
// LOAD ENTERPRISE AI SETTINGS FROM DB
// ============================================================
function loadEnterpriseSettings($db) {
    $defaults = [
        'ai_model'           => 'gemini-1.5-flash',
        'temperature'        => 0.75,
        'max_tokens'         => 8000,
        'top_p'              => 0.95,
        'frequency_penalty'  => 0.10,
        'presence_penalty'   => 0.10,
        'retry_limit'        => 3,
        'auto_regenerate_failed'    => 1,
        'minimum_seo_score'         => 60,
        'minimum_ctr_score'         => 60,
        'minimum_readability_score' => 60,
        'minimum_conversion_score'  => 50,
        'minimum_human_score'       => 65,
        'enable_ai_scoring'              => 1,
        'enable_auto_internal_links'     => 1,
        'enable_auto_cta'                => 1,
        'enable_schema_generation'       => 1,
        'enable_auto_thumbnail_prompt'   => 1,
        'enable_emotional_optimizer'     => 1,
        'enable_search_intent_optimizer' => 1,
        'system_prompt'       => '',
        'seo_rules'           => '',
        'conversion_rules'    => '',
        'thumbnail_rules'     => '',
        'validation_rules'    => '',
        'emotional_rules'     => '',
        'ctr_rules'           => '',
        'internal_link_rules' => '',
        'faq_rules'           => '',
        'schema_rules'        => '',
        'readability_rules'   => '',
        'brand_voice_rules'   => '',
    ];
    try {
        $row = $db->query("SELECT * FROM ai_blog_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) { $defaults = array_merge($defaults, $row); }
    } catch (Exception $e) {}
    return $defaults;
}

// ============================================================
// LOAD INTERNAL LINK RULES
// ============================================================
function loadInternalLinks($db) {
    try {
        return $db->query("SELECT keyword, target_url, anchor_text, max_usage FROM internal_link_rules WHERE is_active=1 ORDER BY priority DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}

// ============================================================
// LOAD ACTIVE CTA TEMPLATES
// ============================================================
function loadCTATemplates($db) {
    try {
        return $db->query("SELECT * FROM cta_templates WHERE is_active=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}

// ============================================================
// INJECT DYNAMIC VARIABLES INTO SYSTEM PROMPT
// ============================================================
function injectPromptVariables($prompt, $vars) {
    foreach ($vars as $key => $value) {
        $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
    }
    return $prompt;
}

// ============================================================
// HANDLE RESEARCH ACTION - Deep keyword & topic analysis
// ============================================================
function handleResearch($input, $db, $apiKey) {
    $topic = $input['topic'] ?? '';
    $keywords = $input['keywords'] ?? '';
    $category = $input['category'] ?? '';
    $products = $input['products'] ?? [];
    
    if (empty($topic)) {
        echo json_encode(['success' => false, 'message' => 'Topic is required']);
        return;
    }
    
    $settings = loadEnterpriseSettings($db);
    $productNames = array_map(fn($p) => $p['name'] ?? '', $products);
    $productList = !empty($productNames) ? implode(', ', $productNames) : 'Kashmiri Saffron, Premium Spices';
    
    $prompt = <<<PROMPT
You are an enterprise-grade SEO research engine for Gilaf Store, a premium eCommerce store selling authentic Kashmiri saffron and spices.

TASK: Conduct deep keyword research, search intent analysis, and content opportunity mapping.

TOPIC: {$topic}
INITIAL KEYWORDS: {$keywords}
CATEGORY: {$category}
PRODUCTS TO FEATURE: {$productList}

Analyze this topic comprehensively. Return ONLY valid JSON (no markdown, no explanation):

{
    "suggestedKeywords": ["8-12 high-value keywords with search intent"],
    "relatedTerms": ["6-8 LSI/semantic keywords"],
    "longTailKeywords": ["5-7 long-tail keywords, 4-7 words each"],
    "trendingTopics": [
        {"title": "High-CTR Blog Title 1", "description": "Why this ranks well", "intent": "informational|commercial|transactional"},
        {"title": "High-CTR Blog Title 2", "description": "Conversion focus", "intent": "commercial"},
        {"title": "High-CTR Blog Title 3", "description": "Educational angle", "intent": "informational"}
    ],
    "searchIntent": "primary intent: informational|commercial|transactional|navigational",
    "userPainPoints": ["3-5 pain points this content should address"],
    "emotionalTriggers": ["3-5 emotional hooks to use"],
    "conversionOpportunities": ["2-3 ways to convert readers to buyers"],
    "faqOpportunities": ["5 questions people ask about this topic"],
    "competitorInsights": "What top-ranking content covers",
    "contentGaps": "What's missing that we can fill",
    "recommendedWordCount": 1500,
    "recommendedTone": "professional|friendly|educational|persuasive"
}

GUIDELINES:
1. Focus on HIGH search volume, MEDIUM-LOW competition keywords
2. Include buyer-intent keywords (buy, best, price, online, authentic)
3. Include informational keywords (benefits, how to, guide, uses)
4. Blog titles must be compelling, use numbers or power words
5. Consider Indian market (Rs., Ayurveda, Indian cooking, festivals)
6. Think about what questions people ask
7. Identify emotional triggers: trust, desire, fear of missing out, curiosity

Return ONLY valid JSON.
PROMPT;

    $startTime = microtime(true);
    $response = callAI($prompt, $apiKey, $settings, 4000);
    $genTime = round(microtime(true) - $startTime, 3);
    
    if ($response['success']) {
        $text = cleanJsonResponse($response['text']);
        $research = json_decode($text, true);
        
        if ($research) {
            echo json_encode(['success' => true, 'research' => $research, 'generation_time' => $genTime]);
        } else {
            echo json_encode(['success' => true, 'research' => generateFallbackResearch($topic, $keywords), 'generation_time' => $genTime]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => $response['error']]);
    }
}

// ============================================================
// HANDLE SUGGEST TITLES - Generate shocking/surprising blog titles
// ============================================================
function handleSuggestTitles($input, $db, $apiKey) {
    $keyword = trim($input['keyword'] ?? '');
    if (empty($keyword) || strlen($keyword) < 3) {
        echo json_encode(['success' => false, 'message' => 'Keyword too short']);
        return;
    }
    
    $settings = loadEnterpriseSettings($db);
    
    $prompt = <<<PROMPT
You are a viral content strategist for Gilaf Store (gilafstore.com), a premium Indian ecommerce store.

KEYWORD/TOPIC: {$keyword}

Generate exactly 8 blog title suggestions. Each title MUST follow these rules:

TITLE FORMULA (use a mix of these):
1. SHOCKING FACT: Start with a surprising statistic or fact that makes people go "WHAT?!"
2. CURIOSITY GAP: Create a gap between what they know and what they want to know
3. FEAR/WARNING: Warn about something they might be doing wrong
4. NUMBERED LIST: Use odd numbers (7, 9, 11) with a twist
5. QUESTION HOOK: Ask a question they desperately want answered
6. CONTRARIAN: Challenge a common belief

RULES:
- Each title must be 50-70 characters
- Must include the main keyword naturally
- Must create an irresistible urge to click
- Sound like a real Indian blogger, not AI
- No em dashes. No formal language.
- Use power words: shocking, secret, mistake, truth, never, always, warning, finally
- At least 2 titles should have numbers
- At least 2 titles should be questions

Return ONLY valid JSON array (no markdown, no explanation):
[
    {"title": "Title text here", "type": "shocking", "hook": "Why this works in 5 words"},
    {"title": "Another title", "type": "curiosity", "hook": "Brief explanation"}
]

Types: shocking, curiosity, fear, numbered, question, contrarian
PROMPT;

    $response = callAI($prompt, $apiKey, $settings, 2000);
    
    if ($response['success']) {
        $text = cleanJsonResponse($response['text']);
        $titles = json_decode($text, true);
        
        if ($titles && is_array($titles)) {
            echo json_encode(['success' => true, 'titles' => $titles]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not parse title suggestions']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => $response['error'] ?? 'AI request failed']);
    }
}

// ============================================================
// HANDLE GENERATE ACTION - Enterprise blog generation with validation & scoring
// ============================================================
function handleGenerate($input, $db, $apiKey) {
    $topic = $input['topic'] ?? '';
    $selectedTopic = $input['selectedTopic'] ?? null;
    $keywords = $input['keywords'] ?? [];
    $research = $input['research'] ?? [];
    $tone = $input['tone'] ?? 'professional';
    $length = intval($input['length'] ?? 1500);
    $includeFaqs = $input['includeFaqs'] ?? true;
    $includeProducts = $input['includeProducts'] ?? true;
    $products = $input['products'] ?? [];
    $category = $input['category'] ?? '';
    
    // Load enterprise settings
    $settings = loadEnterpriseSettings($db);
    $internalLinks = loadInternalLinks($db);
    $ctaTemplates = loadCTATemplates($db);
    
    $blogTitle = $selectedTopic['title'] ?? $topic;
    $keywordList = is_array($keywords) ? implode(', ', $keywords) : $keywords;
    $mainKeyword = is_array($keywords) && !empty($keywords) ? $keywords[0] : $topic;
    $productNames = array_map(fn($p) => $p['name'] ?? '', $products);
    $productList = !empty($productNames) ? implode(', ', $productNames) : '';
    
    // Build product links with actual slugs for AI to use
    $productLinksStr = '';
    foreach ($products as $product) {
        if (!empty($product['slug'])) {
            $productLinksStr .= "- {$product['name']} → /product/{$product['slug']}\n";
        }
    }
    
    // Build internal links string
    $internalLinksStr = '';
    foreach ($internalLinks as $link) {
        $internalLinksStr .= "- \"{$link['keyword']}\" → {$link['target_url']} (anchor: {$link['anchor_text']})\n";
    }
    
    // Build dynamic variables for prompt injection
    $promptVars = [
        'brand_name'         => 'Gilaf Store',
        'website_name'       => 'gilafstore.com',
        'target_audience'    => 'Health-conscious Indian families, home cooks, wellness seekers',
        'blog_category'      => $category,
        'main_keyword'       => $mainKeyword,
        'secondary_keywords' => $keywordList,
        'products'           => $productList,
        'product_links'      => $productLinksStr,
        'internal_links'     => $internalLinksStr,
        'brand_tone'         => $tone,
        'goal'               => 'Drive organic traffic and convert readers to customers',
        'seo_rules'          => $settings['seo_rules'] ?? '',
        'emotional_rules'    => $settings['emotional_rules'] ?? '',
        'conversion_rules'   => $settings['conversion_rules'] ?? '',
        'brand_voice_rules'  => $settings['brand_voice_rules'] ?? '',
    ];
    
    // Build system prompt from DB or use default
    $systemPrompt = !empty($settings['system_prompt']) 
        ? injectPromptVariables($settings['system_prompt'], $promptVars)
        : buildDefaultSystemPrompt($promptVars, $settings);
    
    // Build user prompt with strict JSON format
    $userPrompt = buildUserPrompt($blogTitle, $keywordList, $length, $tone, $productList, $includeFaqs, $research, $settings);
    
    $retryLimit = min(2, intval($settings['retry_limit'] ?? 2)); // cap at 2 to stay under server timeout
    $autoRegenerate = intval($settings['auto_regenerate_failed'] ?? 1);
    $attempt = 0;
    $validationErrors = [];
    $finalContent = null;
    $rawResponse = '';
    $startTime = microtime(true);
    
    // Retry loop with validation
    while ($attempt < $retryLimit) {
        $attempt++;
        $response = callAI($userPrompt, $apiKey, $settings, intval($settings['max_tokens'] ?? 8000), $systemPrompt);
        
        if (!$response['success']) {
            $validationErrors[] = "Attempt {$attempt}: API error - " . ($response['error'] ?? 'Unknown');
            if (!$autoRegenerate) break;
            continue;
        }
        
        $rawResponse = $response['text'];
        $content = tryDecodeJson($rawResponse);

        if (!$content || !isset($content['seo_title'])) {
            $validationErrors[] = "Attempt {$attempt}: Invalid JSON structure (json_error: " . json_last_error_msg() . ")";
            if (!$autoRegenerate) break;
            continue;
        }
        
        // Run validation engine
        $validation = validateBlogContent($content, $settings);
        
        if ($validation['passed']) {
            $finalContent = $content;
            break;
        } else {
            $validationErrors[] = "Attempt {$attempt}: " . implode(', ', $validation['errors']);
            if (!$autoRegenerate) break;
        }
    }
    
    $genTime = round(microtime(true) - $startTime, 3);
    
    if (!$finalContent) {
        // Use last response even if validation failed — try all repair strategies
        $finalContent = tryDecodeJson($rawResponse);
    }
    
    if ($finalContent) {
        // Always convert content to clean HTML
        if (!empty($finalContent['content'])) {
            $finalContent['content'] = convertMarkdownToHtml($finalContent['content']);
        }
        if (!empty($finalContent['contentHtml'])) {
            $finalContent['contentHtml'] = convertMarkdownToHtml($finalContent['contentHtml']);
        } elseif (!empty($finalContent['content'])) {
            $finalContent['contentHtml'] = $finalContent['content'];
        }
        
        // Apply internal linking
        if ($settings['enable_auto_internal_links'] && !empty($internalLinks)) {
            $finalContent['contentHtml'] = applyInternalLinks($finalContent['contentHtml'], $internalLinks);
            if (!empty($finalContent['content'])) {
                $finalContent['content'] = applyInternalLinks($finalContent['content'], $internalLinks);
            }
        }
        
        // Calculate AI scores
        $scores = calculateAIScores($finalContent, $settings);
        $finalContent['ai_scores'] = $scores;
        
        // Log generation
        logGeneration($db, null, $systemPrompt . "\n\n" . $userPrompt, $rawResponse, json_encode($finalContent), json_encode($validationErrors), json_encode($scores), $attempt, $genTime, $response['token_usage'] ?? 0);
        
        // Map to existing editor fields
        $mappedContent = [
            'title'           => $finalContent['seo_title'] ?? $finalContent['title'] ?? $blogTitle,
            'metaTitle'       => $finalContent['meta_title'] ?? $finalContent['seo_title'] ?? '',
            'metaDescription' => $finalContent['meta_description'] ?? '',
            'slug'            => $finalContent['slug'] ?? '',
            'excerpt'         => $finalContent['excerpt'] ?? '',
            'content'         => $finalContent['content'] ?? '',
            'contentHtml'     => $finalContent['contentHtml'] ?? '',
            'faqs'            => $finalContent['faq'] ?? $finalContent['faqs'] ?? [],
            'keywords'        => $finalContent['secondary_keywords'] ?? $finalContent['keywords'] ?? $keywords,
            'thumbnailText'   => $finalContent['thumbnail_text'] ?? '',
            'thumbnailPrompt' => $finalContent['thumbnail_prompt'] ?? '',
            'thumbnailSubject'=> $finalContent['thumbnail_subject'] ?? '',
            'featuredImageIdea' => $finalContent['featured_image_idea'] ?? '',
            'searchIntent'    => $finalContent['search_intent'] ?? '',
            'socialCaption'   => $finalContent['social_caption'] ?? '',
            'pinterestCaption'=> $finalContent['pinterest_caption'] ?? '',
            'schemaSuggestions' => $finalContent['schema_suggestions'] ?? [],
            'ai_scores'       => $scores,
            'validation_errors' => $validationErrors,
            'generation_time' => $genTime,
            'retry_count'     => $attempt,
        ];
        
        echo json_encode(['success' => true, 'content' => $mappedContent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate valid content after ' . $attempt . ' attempts', 'errors' => $validationErrors]);
    }
}

// ============================================================
// BUILD DEFAULT SYSTEM PROMPT
// ============================================================
function buildDefaultSystemPrompt($vars, $settings) {
    $prompt = "You are an enterprise-grade AI publishing engine for {$vars['brand_name']} ({$vars['website_name']}), a premium ecommerce store.

YOUR ROLE:
- SEO strategist optimizing for Google rankings
- Conversion copywriter maximizing sales
- Emotional marketer creating engagement
- Media publisher ensuring quality
- Ecommerce content strategist driving revenue

TARGET AUDIENCE: {$vars['target_audience']}
BRAND TONE: {$vars['brand_tone']}
BLOG CATEGORY: {$vars['blog_category']}
MAIN KEYWORD: {$vars['main_keyword']}
SECONDARY KEYWORDS: {$vars['secondary_keywords']}
FEATURED PRODUCTS: {$vars['products']}
PRODUCT LINKS (use these exact URLs):
{$vars['product_links']}
GOAL: {$vars['goal']}

INTERNAL LINKS TO USE:
{$vars['internal_links']}

WRITING RULES:
1. Write like a REAL Indian blogger who knows their stuff. Sound like a person, not a machine.
2. NEVER use long dashes or the pattern word-dash-word. Use commas, periods, or rewrite the sentence instead.
3. BANNED AI phrases: In today's world, It's worth noting, In this article, Let's dive in, Whether you're, In conclusion, It's important to, When it comes to, At the end of the day, game-changer, elevate, navigate, landscape, unlock, leverage, delve, comprehensive, robust, streamline
4. Use SHORT simple sentences. Break long sentences into two. Maximum 20 words per sentence.
5. Write paragraphs of 2-3 sentences only. White space is your friend.
6. Use numbers, real statistics, and specific facts. Be precise, not vague.
7. Mention products naturally as recommendations, not advertisements.
8. Use Indian English spellings and references (Rs, crore, lakh, chai, desi).
9. Include personal touches like I tested this myself or My family has been using.
10. Ask rhetorical questions to engage readers.
11. Use simple everyday words. Replace utilize with use, purchase with buy, commence with start.
12. Avoid semicolons. Use periods instead.
13. Start some sentences with And, But, So like people actually talk.

SEO RULES:
{$vars['seo_rules']}

EMOTIONAL WRITING RULES:
{$vars['emotional_rules']}

CONVERSION RULES:
{$vars['conversion_rules']}

BRAND VOICE:
{$vars['brand_voice_rules']}

CRITICAL: Return ONLY valid JSON. No markdown code blocks. No preamble. No explanation.";

    return $prompt;
}

// ============================================================
// BUILD USER PROMPT WITH STRICT JSON FORMAT
// ============================================================
function buildUserPrompt($title, $keywords, $length, $tone, $products, $includeFaqs, $research, $settings) {
    $faqInstruction = $includeFaqs ? "Include 5-7 FAQs matching real search queries" : "Do not include FAQs";
    $productInstruction = !empty($products) ? "Naturally feature these products: {$products}" : "";
    
    $researchContext = '';
    if (!empty($research)) {
        if (!empty($research['userPainPoints'])) {
            $researchContext .= "\nUSER PAIN POINTS TO ADDRESS: " . implode(', ', $research['userPainPoints']);
        }
        if (!empty($research['emotionalTriggers'])) {
            $researchContext .= "\nEMOTIONAL TRIGGERS TO USE: " . implode(', ', $research['emotionalTriggers']);
        }
        if (!empty($research['searchIntent'])) {
            $researchContext .= "\nSEARCH INTENT: " . $research['searchIntent'];
        }
    }

    return <<<PROMPT
Write a comprehensive, SEO-optimized blog post that sounds 100% human-written.

TITLE: {$title}
TARGET KEYWORDS: {$keywords}
WORD COUNT: {$length} words minimum
TONE: {$tone}
{$productInstruction}
{$faqInstruction}
{$researchContext}

CRITICAL WRITING RULES (MUST FOLLOW):
- Write like a real Indian blogger sharing personal experience. NOT like AI.
- ABSOLUTELY NO long dashes in content. Zero tolerance. Use commas or periods instead.
- ABSOLUTELY NO markdown: no **, no ##, no __, no *. Output clean HTML only.
- BANNED phrases: In today's world, It's worth noting, Let's dive in, Whether you're, game-changer, elevate, navigate, landscape, unlock, leverage, delve, robust, comprehensive
- Use contractions: don't, you'll, we're, isn't, can't, won't.
- Short sentences. Max 20 words each. Break long sentences.
- Use you and your to talk directly to the reader.
- Include real numbers: 7 out of 10, Rs 500 per gram, tested 4 brands.
- Sound opinionated: Honestly most brands are fake, I wouldn't touch this brand.
- Use Indian references: chai, desi, jugaad, paisa vasool, Rs instead of $.
- NO semicolons. NO formal academic language. NO passive voice.
- NEVER include external images from unsplash.com, pexels.com, or any other external domain. Do NOT add any <img> tags in the content.
- PRODUCT LINKS: When linking to products, use the URL format /product/product-slug (SINGULAR, not /products/). IMPORTANT: Use SINGLE QUOTES for ALL HTML attributes to keep JSON valid. Example: <a href='/product/kashmiri-mogra-saffron'>Kashmiri Mogra Saffron</a>
- ALL HTML ATTRIBUTES must use single quotes: href='...', src='...', class='...'. NEVER use double quotes inside HTML attributes. This is critical for JSON validity.

THUMBNAIL REQUIREMENTS:
- thumbnail_prompt must be a detailed, professional image generation prompt
- Focus on the MAIN SUBJECT of the blog (e.g., saffron threads, honey jar, spices)
- Include: lighting, composition, style, colors, mood
- Make it visually stunning and click-worthy
- Example: "Close-up photograph of vibrant red Kashmiri saffron threads in a wooden spoon, warm golden lighting, rustic kitchen background, shallow depth of field, premium food photography style"

Return ONLY this exact JSON structure (no markdown, no code blocks):

{
    "seo_title": "Compelling 50-60 char title with main keyword and power word",
    "meta_title": "SEO title for browser tab, 50-60 chars",
    "meta_description": "145-160 char description with keyword and CTA",
    "slug": "lowercase-hyphenated-max-5-words",
    "thumbnail_text": "Max 6 words for thumbnail overlay - shocking/attractive",
    "thumbnail_prompt": "Detailed professional image prompt: subject, lighting, style, composition, colors. Must match blog topic exactly. Example: Close-up of golden saffron threads on marble surface, soft natural lighting, luxury food photography",
    "thumbnail_subject": "Main visual subject for thumbnail - be specific (e.g., Kashmiri saffron threads, honey dripping from spoon)",
    "featured_image_idea": "Description of ideal featured image matching blog topic",
    "excerpt": "2-3 sentence compelling excerpt for blog listing - natural human tone",
    "intro": "Hook paragraph with main keyword in first 100 words - conversational",
    "search_intent": "informational|commercial|transactional",
    "primary_keyword": "main keyword",
    "secondary_keywords": ["keyword1", "keyword2", "keyword3"],
    "sections": [
        {"heading": "H2 heading with power word", "content": "2-4 paragraphs of plain text content - NO markdown symbols"},
        {"heading": "Another compelling H2", "content": "More valuable content in plain text"}
    ],
    "faq": [
        {"question": "Real search query question?", "answer": "40-80 word direct answer"},
        {"question": "Another FAQ?", "answer": "Helpful answer"}
    ],
    "product_cta": ["Natural product mention 1", "Product mention 2"],
    "internal_links": [{"anchor": "anchor text", "url": "/target-url"}],
    "schema_suggestions": ["Article", "FAQPage", "BreadcrumbList"],
    "social_caption": "Engaging social media caption with hashtags",
    "pinterest_caption": "Pinterest-optimized description",
    "conclusion": "Strong conclusion with CTA",
    "content": "Full blog content in clean HTML format with <h2> headings, <p> paragraphs, <ul><li> lists. NO markdown. NO ** symbols. Sound human."
}
PROMPT;
}

// ============================================================
// CLEAN JSON RESPONSE
// ============================================================
function cleanJsonResponse($text) {
    $text = preg_replace('/^```json\s*/im', '', $text);
    $text = preg_replace('/^```\s*$/m', '', $text);
    $text = preg_replace('/```$/m', '', $text);
    $text = trim($text);

    // Try object first
    $jsonStart = strpos($text, '{');
    $jsonEnd   = strrpos($text, '}');

    // Try array if no object found or array comes first
    $arrStart = strpos($text, '[');
    $arrEnd   = strrpos($text, ']');

    // Use whichever comes first (object or array)
    if ($arrStart !== false && $arrEnd !== false && ($jsonStart === false || $arrStart < $jsonStart)) {
        return substr($text, $arrStart, $arrEnd - $arrStart + 1);
    }

    if ($jsonStart !== false && $jsonEnd !== false) {
        return substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
    }
    return $text;
}

// ============================================================
// REPAIR BROKEN JSON — fixes HTML attribute " inside JSON strings
// ============================================================
function repairBrokenJson($text) {
    // Common AI mistake: href="url" inside a JSON string breaks json_decode.
    // Fix by converting HTML attribute double-quotes to single-quotes.
    $text = preg_replace('/\s(href|src|class|id|alt|title|action|type|data-[\w-]+)="([^"<>]*)"/', ' $1=\'$2\'', $text);

    // Remove bare control characters (tabs/newlines inside string values)
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    return $text;
}

// ============================================================
// TRY TO DECODE JSON — with fallback repair
// ============================================================
function tryDecodeJson($raw) {
    $text = cleanJsonResponse($raw);

    // Attempt 1: clean text as-is
    $result = json_decode($text, true);
    if ($result !== null) return $result;

    // Attempt 2: repair HTML attribute quotes then parse
    $repaired = repairBrokenJson($text);
    $result = json_decode($repaired, true);
    if ($result !== null) return $result;

    // Attempt 3: strip everything before first { and after last }
    $start = strpos($repaired, '{');
    $end   = strrpos($repaired, '}');
    if ($start !== false && $end !== false) {
        $result = json_decode(substr($repaired, $start, $end - $start + 1), true);
        if ($result !== null) return $result;
    }

    return null;
}

// ============================================================
// VALIDATION ENGINE
// ============================================================
function validateBlogContent($content, $settings) {
    $errors = [];
    
    // Title validation
    $title = $content['seo_title'] ?? $content['title'] ?? '';
    if (strlen($title) < 40) $errors[] = "Title too short (min 40 chars)";
    if (strlen($title) > 70) $errors[] = "Title too long (max 70 chars)";
    
    // Meta description validation
    $metaDesc = $content['meta_description'] ?? '';
    if (strlen($metaDesc) < 120) $errors[] = "Meta description too short (min 120 chars)";
    if (strlen($metaDesc) > 165) $errors[] = "Meta description too long (max 165 chars)";
    
    // Content validation
    $contentText = $content['content'] ?? '';
    $wordCount = str_word_count(strip_tags($contentText));
    if ($wordCount < 800) $errors[] = "Content too short ({$wordCount} words, min 800)";
    
    // FAQ validation
    $faqs = $content['faq'] ?? $content['faqs'] ?? [];
    if (empty($faqs)) $errors[] = "No FAQ section";
    
    // Thumbnail text validation
    $thumbText = $content['thumbnail_text'] ?? '';
    if (str_word_count($thumbText) > 8) $errors[] = "Thumbnail text too long (max 8 words)";
    
    // Check for generic headings
    $sections = $content['sections'] ?? [];
    foreach ($sections as $section) {
        $heading = strtolower($section['heading'] ?? '');
        if (in_array($heading, ['introduction', 'conclusion', 'section 1', 'section 2'])) {
            $errors[] = "Generic heading detected: {$heading}";
        }
    }
    
    return [
        'passed' => empty($errors),
        'errors' => $errors
    ];
}

// ============================================================
// AI SCORING ENGINE
// ============================================================
function calculateAIScores($content, $settings) {
    $scores = [
        'seo_score' => 0,
        'ctr_score' => 0,
        'emotional_score' => 0,
        'readability_score' => 0,
        'conversion_score' => 0,
        'human_score' => 0,
        'trust_score' => 0,
        'engagement_score' => 0,
        'overall_score' => 0
    ];
    
    $title = $content['seo_title'] ?? $content['title'] ?? '';
    $metaDesc = $content['meta_description'] ?? '';
    $contentText = $content['content'] ?? '';
    $faqs = $content['faq'] ?? $content['faqs'] ?? [];
    $sections = $content['sections'] ?? [];
    
    // SEO Score (0-100)
    $seo = 50;
    if (strlen($title) >= 40 && strlen($title) <= 65) $seo += 15;
    if (strlen($metaDesc) >= 140 && strlen($metaDesc) <= 160) $seo += 15;
    if (!empty($content['slug'])) $seo += 5;
    if (count($faqs) >= 5) $seo += 10;
    if (count($sections) >= 4) $seo += 5;
    $scores['seo_score'] = min(100, $seo);
    
    // CTR Score - based on title power words
    $ctr = 50;
    $powerWords = ['secret', 'proven', 'best', 'top', 'ultimate', 'guide', 'how', 'why', 'benefits', 'amazing', 'discover', 'exclusive'];
    foreach ($powerWords as $word) {
        if (stripos($title, $word) !== false) { $ctr += 8; break; }
    }
    if (preg_match('/\d+/', $title)) $ctr += 10; // Numbers in title
    if (strpos($title, '?') !== false) $ctr += 5; // Question
    if (preg_match('/\[.*\]/', $title)) $ctr += 5; // Brackets
    $scores['ctr_score'] = min(100, $ctr);
    
    // Emotional Score
    $emotional = 50;
    $emotionalWords = ['love', 'fear', 'trust', 'authentic', 'pure', 'natural', 'healthy', 'family', 'tradition', 'premium', 'exclusive', 'rare'];
    $emotionalCount = 0;
    foreach ($emotionalWords as $word) {
        if (stripos($contentText, $word) !== false) $emotionalCount++;
    }
    $emotional += min(30, $emotionalCount * 5);
    if (!empty($content['excerpt'])) $emotional += 10;
    $scores['emotional_score'] = min(100, $emotional);
    
    // Readability Score
    $readability = 60;
    $sentences = preg_split('/[.!?]+/', $contentText);
    $avgSentenceLength = count($sentences) > 0 ? str_word_count($contentText) / count($sentences) : 30;
    if ($avgSentenceLength < 20) $readability += 20;
    elseif ($avgSentenceLength < 25) $readability += 10;
    $paragraphs = explode("\n\n", $contentText);
    if (count($paragraphs) > 10) $readability += 10;
    $scores['readability_score'] = min(100, $readability);
    
    // Conversion Score
    $conversion = 50;
    $ctaWords = ['shop', 'buy', 'order', 'get', 'try', 'discover', 'explore'];
    foreach ($ctaWords as $word) {
        if (stripos($contentText, $word) !== false) { $conversion += 10; break; }
    }
    if (!empty($content['product_cta'])) $conversion += 15;
    if (stripos($contentText, 'gilaf') !== false) $conversion += 10;
    $scores['conversion_score'] = min(100, $conversion);
    
    // Human Score - detect AI patterns
    $human = 80;
    $aiPatterns = ['in conclusion', 'it is important to note', 'in this article', 'as mentioned above', 'in summary'];
    foreach ($aiPatterns as $pattern) {
        if (stripos($contentText, $pattern) !== false) $human -= 10;
    }
    $scores['human_score'] = max(30, $human);
    
    // Trust Score
    $trust = 60;
    $trustWords = ['authentic', 'certified', 'pure', 'genuine', 'quality', 'trusted', 'verified', 'organic'];
    foreach ($trustWords as $word) {
        if (stripos($contentText, $word) !== false) { $trust += 5; }
    }
    $scores['trust_score'] = min(100, $trust);
    
    // Engagement Score
    $engagement = 50;
    if (count($faqs) >= 5) $engagement += 15;
    if (count($sections) >= 5) $engagement += 10;
    if (!empty($content['social_caption'])) $engagement += 10;
    if (preg_match_all('/\?/', $contentText, $m) > 3) $engagement += 10;
    $scores['engagement_score'] = min(100, $engagement);
    
    // Overall Score (weighted average)
    $scores['overall_score'] = round(
        ($scores['seo_score'] * 0.20) +
        ($scores['ctr_score'] * 0.15) +
        ($scores['emotional_score'] * 0.10) +
        ($scores['readability_score'] * 0.15) +
        ($scores['conversion_score'] * 0.15) +
        ($scores['human_score'] * 0.10) +
        ($scores['trust_score'] * 0.05) +
        ($scores['engagement_score'] * 0.10)
    );
    
    return $scores;
}

// ============================================================
// INTERNAL LINKING ENGINE
// ============================================================
function applyInternalLinks($content, $links) {
    foreach ($links as $link) {
        $keyword = preg_quote($link['keyword'], '/');
        $anchor = $link['anchor_text'] ?: $link['keyword'];
        $url = $link['target_url'];
        $maxUsage = intval($link['max_usage'] ?? 2);
        
        // Replace keyword with link (limited times)
        $pattern = '/\b(' . $keyword . ')\b(?![^<]*>)/i';
        $replacement = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($anchor) . '</a>';
        $content = preg_replace($pattern, $replacement, $content, $maxUsage);
    }
    // Fix any /products/ links to /product/ (singular) to match .htaccess routing
    $content = preg_replace('/href=(["\'])\/products\//', 'href=$1/product/', $content);
    return $content;
}

// ============================================================
// LOG GENERATION TO DATABASE
// ============================================================
function logGeneration($db, $blogId, $prompt, $rawResponse, $parsedJson, $validationErrors, $scores, $retryCount, $genTime, $tokenUsage) {
    try {
        $stmt = $db->prepare("INSERT INTO ai_blog_logs (blog_id, prompt_used, raw_ai_response, parsed_json, validation_errors, ai_scores, retry_count, generation_time, token_usage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$blogId, $prompt, $rawResponse, $parsedJson, $validationErrors, $scores, $retryCount, $genTime, $tokenUsage]);
    } catch (Exception $e) {
        // Logging failure shouldn't break generation
    }
}

// ============================================================
// CALL AI API (Gemini/OpenAI/Claude)
// ============================================================
function callAI($prompt, $apiKey, $settings, $maxTokens = 8000, $systemPrompt = '') {
    // Get provider and model from chatbot_settings
    global $db;
    $provider = '';
    $model    = '';
    $temperature = floatval($settings['temperature'] ?? 0.75);

    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider', 'ai_model')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $provider = $rows['ai_provider'] ?? '';
        $model    = $rows['ai_model']    ?? '';
    } catch (Exception $e) {}

    // Auto-detect provider from API key format when not set in DB
    if (empty($provider)) {
        if (strpos($apiKey, 'sk-ant-') === 0) {
            $provider = 'claude';
        } elseif (strpos($apiKey, 'sk-') === 0) {
            $provider = 'openai';
        } else {
            $provider = 'gemini';
        }
    }

    // Sensible model defaults per provider when model not set in DB
    if (empty($model)) {
        if ($provider === 'openai') {
            $model = 'gpt-4o-mini';
        } elseif ($provider === 'claude') {
            $model = 'claude-3-haiku-20240307';
        } else {
            $model = 'gemini-2.0-flash';
        }
    }

    
    // Build messages with system prompt support
    $messages = [];
    if (!empty($systemPrompt)) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];
    
    if ($provider === 'openai') {
        $url = "https://api.openai.com/v1/chat/completions";
        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'frequency_penalty' => floatval($settings['frequency_penalty'] ?? 0.1),
            'presence_penalty' => floatval($settings['presence_penalty'] ?? 0.1)
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
    } elseif ($provider === 'claude') {
        $url = "https://api.anthropic.com/v1/messages";
        $data = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt ?: 'You are an expert content writer.',
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ];
    } else {
        // Gemini - combine system prompt with user prompt
        $fullPrompt = !empty($systemPrompt) ? $systemPrompt . "\n\n---\n\n" . $prompt : $prompt;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
        $data = [
            'contents' => [
                ['parts' => [['text' => $fullPrompt]]]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'topK' => 40,
                'topP' => floatval($settings['top_p'] ?? 0.95),
                'maxOutputTokens' => $maxTokens
            ]
        ];
        $headers = ['Content-Type: application/json'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    
    // SSL cert handling
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
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? $errorData['message'] ?? 'API error (HTTP ' . $httpCode . ')';
        return ['success' => false, 'error' => $errorMsg];
    }
    
    $result = json_decode($response, true);
    
    // Extract text based on provider
    $text = '';
    if ($provider === 'openai') {
        $text = $result['choices'][0]['message']['content'] ?? '';
    } elseif ($provider === 'claude') {
        $text = $result['content'][0]['text'] ?? '';
    } else {
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
    
    if (empty($text)) {
        return ['success' => false, 'error' => 'Empty response from AI'];
    }
    
    // Extract token usage if available
    $tokenUsage = 0;
    if ($provider === 'openai' && isset($result['usage']['total_tokens'])) {
        $tokenUsage = $result['usage']['total_tokens'];
    } elseif ($provider === 'claude' && isset($result['usage'])) {
        $tokenUsage = ($result['usage']['input_tokens'] ?? 0) + ($result['usage']['output_tokens'] ?? 0);
    }
    
    return ['success' => true, 'text' => $text, 'token_usage' => $tokenUsage];
}

function convertMarkdownToHtml($markdown) {
    // Basic markdown to HTML conversion
    $html = $markdown;
    
    // Headers
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Bold and italic - convert to HTML tags
    $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
    $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);
    $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);
    
    // Remove any remaining stray ** or __ symbols
    $html = preg_replace('/\*\*/', '', $html);
    $html = preg_replace('/__/', '', $html);
    
    // Remove AI-style em dashes and en dashes
    $html = str_replace(' — ', ', ', $html);
    $html = str_replace('— ', ', ', $html);
    $html = str_replace(' —', ', ', $html);
    $html = str_replace('—', ', ', $html);
    $html = str_replace(' – ', ', ', $html);
    $html = str_replace('–', ', ', $html);
    
    // Lists
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/^(\d+)\. (.+)$/m', '<li>$2</li>', $html);
    
    // Wrap consecutive li elements in ul
    $html = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html);
    
    // Paragraphs
    $html = preg_replace('/\n\n+/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // Clean up
    $html = str_replace('<p></p>', '', $html);
    $html = str_replace('<p><h', '<h', $html);
    $html = str_replace('</h1></p>', '</h1>', $html);
    $html = str_replace('</h2></p>', '</h2>', $html);
    $html = str_replace('</h3></p>', '</h3>', $html);
    $html = str_replace('<p><ul>', '<ul>', $html);
    $html = str_replace('</ul></p>', '</ul>', $html);
    
    return $html;
}

function generateFallbackResearch($topic, $keywords) {
    // Generate basic research structure if AI fails
    $baseKeywords = array_filter(array_map('trim', explode(',', $keywords)));
    
    return [
        'suggestedKeywords' => array_merge($baseKeywords, [
            $topic . ' benefits',
            $topic . ' uses',
            'buy ' . $topic . ' online',
            $topic . ' price',
            'best ' . $topic,
            'authentic ' . $topic,
            $topic . ' India',
            'premium ' . $topic
        ]),
        'relatedTerms' => [
            'health benefits',
            'natural remedy',
            'organic',
            'authentic',
            'premium quality',
            'traditional uses'
        ],
        'longTailKeywords' => [
            'how to use ' . $topic . ' at home',
            'benefits of ' . $topic . ' for health',
            'where to buy authentic ' . $topic . ' online',
            'best ' . $topic . ' brand in India',
            $topic . ' price per gram'
        ],
        'trendingTopics' => [
            [
                'title' => 'Complete Guide to ' . ucwords($topic) . ': Benefits, Uses & Where to Buy',
                'description' => 'Comprehensive guide covering everything about ' . $topic
            ],
            [
                'title' => 'Top 10 Health Benefits of ' . ucwords($topic) . ' You Should Know',
                'description' => 'Health-focused article targeting wellness seekers'
            ],
            [
                'title' => 'How to Identify Authentic ' . ucwords($topic) . ': Expert Tips',
                'description' => 'Educational content helping buyers make informed decisions'
            ]
        ],
        'searchIntent' => 'informational',
        'competitorInsights' => 'Top content focuses on health benefits and authenticity',
        'contentGaps' => 'Opportunity to provide more detailed buying guides and usage tips'
    ];
}

// ============================================================
// HANDLE GENERATE THUMBNAIL - Auto-generate using Pollinations.ai (FREE)
// ============================================================
function handleGenerateThumbnail($input, $db, $apiKey) {
    $thumbnailPrompt = $input['prompt'] ?? '';
    $thumbnailSubject = $input['subject'] ?? '';
    $overlayText = $input['overlay_text'] ?? '';
    $blogTitle = $input['blog_title'] ?? '';
    $slug = $input['slug'] ?? 'blog-thumbnail';
    
    if (empty($thumbnailPrompt) && empty($thumbnailSubject) && empty($blogTitle)) {
        echo json_encode(['success' => false, 'message' => 'No prompt or subject provided for thumbnail generation']);
        return;
    }
    
    // Build an enhanced prompt for stunning, shocking, click-worthy thumbnails
    $prompt = buildThumbnailPrompt($thumbnailPrompt, $thumbnailSubject, $overlayText, $blogTitle);
    
    // Ensure upload directory exists
    $uploadDir = __DIR__ . '/../uploads/blog/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $safeSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    $safeSlug = substr($safeSlug, 0, 50);
    if (empty($safeSlug)) $safeSlug = 'thumbnail';
    $filename = "ai-{$safeSlug}-{$timestamp}.jpg";
    $filepath = $uploadDir . $filename;
    
    // Call Pollinations.ai - completely free, no API key needed
    $encodedPrompt = urlencode($prompt);
    $imageUrl = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=1200&height=630&seed=" . rand(1, 999999) . "&nologo=true&enhance=true";
    
    // Download the image with cURL
    $ch = curl_init($imageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; GilafStore/1.0)');
    
    // SSL cert handling
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
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo json_encode(['success' => false, 'message' => 'Failed to connect to image generator: ' . $curlError]);
        return;
    }
    
    if ($httpCode !== 200 || empty($imageData)) {
        echo json_encode(['success' => false, 'message' => 'Image generation failed (HTTP ' . $httpCode . '). Try again.']);
        return;
    }
    
    // Verify it's actually an image
    if (strpos($contentType, 'image') === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid response from image generator. Try again.']);
        return;
    }
    
    // Save image
    if (file_put_contents($filepath, $imageData) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image to disk']);
        return;
    }
    
    // Also create WebP version for performance
    $webpFilename = '';
    if (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng')) {
        try {
            $img = @imagecreatefromstring($imageData);
            if ($img) {
                $webpFilename = str_replace('.jpg', '.webp', $filename);
                $webpPath = $uploadDir . $webpFilename;
                imagewebp($img, $webpPath, 85);
                imagedestroy($img);
            }
        } catch (Exception $e) {
            // WebP conversion is optional, don't fail
        }
    }
    
    $fileSize = filesize($filepath);
    $fileSizeKB = round($fileSize / 1024);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'webp_filename' => $webpFilename,
        'url' => 'uploads/blog/' . $filename,
        'webp_url' => $webpFilename ? 'uploads/blog/' . $webpFilename : '',
        'prompt_used' => $prompt,
        'file_size' => $fileSizeKB . ' KB',
        'dimensions' => '1200x630',
        'message' => 'Thumbnail generated successfully!'
    ]);
}

// ============================================================
// BUILD ENHANCED THUMBNAIL PROMPT
// Produces SHOCKING, NATURAL, ATTRACTIVE, FORCE-READ thumbnails
// ============================================================
function buildThumbnailPrompt($basePrompt, $subject, $overlayText, $blogTitle) {
    // Use the subject for the visual scene, fallback to prompt then title
    if (!empty($subject)) {
        $sceneSubject = $subject;
    } elseif (!empty($basePrompt)) {
        $sceneSubject = $basePrompt;
    } else {
        $sceneSubject = $blogTitle;
    }
    
    // Create a short punchy title for overlay (max 6-8 words from blog title)
    $titleOverlay = !empty($overlayText) ? $overlayText : '';
    if (empty($titleOverlay) && !empty($blogTitle)) {
        // Use the blog title itself as the overlay text
        $titleOverlay = $blogTitle;
    }
    
    // Build an ultra-realistic, shocking, YouTube-style thumbnail prompt
    $enhanced = "Professional YouTube-style blog thumbnail, "
        . "dramatic close-up photograph of {$sceneSubject}, "
        . "ultra realistic photography, shot on Sony A7R IV with 50mm f/1.2 lens, "
        . "dramatic studio lighting with strong rim light and moody shadows, "
        . "deep vibrant saturated colors, high contrast, "
        . "cinematic color grading with warm golden tones, "
        . "sharp focus on main subject with soft blurred background, "
        . "emotional and shocking composition that creates curiosity, "
        . "premium editorial magazine quality, 4K ultra detailed, photorealistic";
    
    // ALWAYS add bold title text overlay for shocking click-worthy look
    if (!empty($titleOverlay)) {
        $enhanced .= ", BOLD large white text overlay saying \"{$titleOverlay}\" "
            . "in thick modern Impact-style font, "
            . "text has strong black outline and dramatic drop shadow, "
            . "text is centered and highly readable, "
            . "text pops against the background like a YouTube thumbnail";
    }
    
    // Add action/shocking visual elements
    $enhanced .= ", dramatic visual impact, "
        . "bright attention-grabbing colors red yellow orange, "
        . "creates urgency and curiosity in viewer, "
        . "professional food and lifestyle photography";
    
    // Negative prompt
    $enhanced .= ". NO cartoon, NO illustration, NO watermark, NO blurry, NO low quality, NO artificial, NO clipart, NO plain background, NO boring composition";
    
    return $enhanced;
}

// ============================================================
// SAVE FEATURED IMAGE - Direct DB update for featured image
// Called from blog_edit.php on form submit to ensure image persists
// ============================================================
function handleSaveFeaturedImage($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    $filename = trim($input['filename'] ?? '');
    
    if ($blogId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid blog ID']);
        return;
    }
    
    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'No filename provided']);
        return;
    }
    
    // Verify blog exists
    $stmt = $db->prepare("SELECT id FROM blogs WHERE id = ?");
    $stmt->execute([$blogId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Blog not found']);
        return;
    }
    
    // Update featured_image directly in the DB
    $stmt = $db->prepare("UPDATE blogs SET featured_image = ? WHERE id = ?");
    $stmt->execute([$filename, $blogId]);
    
    echo json_encode(['success' => true, 'message' => 'Featured image saved', 'filename' => $filename]);
}
