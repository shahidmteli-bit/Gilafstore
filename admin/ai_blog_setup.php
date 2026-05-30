<?php
/**
 * AI Blog Engine - Database Setup & Migration
 * Run once to create all required tables for the enterprise AI publishing engine
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (!is_admin()) {
    header('Location: ' . base_url('admin/admin_login.php'));
    exit;
}

$db = get_db_connection();
$results = [];

$tables = [

'ai_blog_settings' => "
CREATE TABLE IF NOT EXISTS ai_blog_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    system_prompt LONGTEXT,
    seo_rules LONGTEXT,
    conversion_rules LONGTEXT,
    thumbnail_rules LONGTEXT,
    validation_rules LONGTEXT,
    emotional_rules LONGTEXT,
    ctr_rules LONGTEXT,
    internal_link_rules LONGTEXT,
    faq_rules LONGTEXT,
    schema_rules LONGTEXT,
    readability_rules LONGTEXT,
    brand_voice_rules LONGTEXT,
    ai_model VARCHAR(100) DEFAULT 'gemini-2.0-flash',
    temperature DECIMAL(3,2) DEFAULT 0.75,
    max_tokens INT DEFAULT 8000,
    top_p DECIMAL(3,2) DEFAULT 0.95,
    frequency_penalty DECIMAL(3,2) DEFAULT 0.10,
    presence_penalty DECIMAL(3,2) DEFAULT 0.10,
    auto_regenerate_failed TINYINT(1) DEFAULT 1,
    minimum_seo_score INT DEFAULT 60,
    minimum_ctr_score INT DEFAULT 60,
    minimum_readability_score INT DEFAULT 60,
    minimum_conversion_score INT DEFAULT 50,
    minimum_human_score INT DEFAULT 65,
    retry_limit INT DEFAULT 3,
    enable_ai_scoring TINYINT(1) DEFAULT 1,
    enable_auto_internal_links TINYINT(1) DEFAULT 1,
    enable_auto_cta TINYINT(1) DEFAULT 1,
    enable_schema_generation TINYINT(1) DEFAULT 1,
    enable_auto_thumbnail_prompt TINYINT(1) DEFAULT 1,
    enable_emotional_optimizer TINYINT(1) DEFAULT 1,
    enable_search_intent_optimizer TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'ai_blog_logs' => "
CREATE TABLE IF NOT EXISTS ai_blog_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT DEFAULT NULL,
    prompt_used LONGTEXT,
    raw_ai_response LONGTEXT,
    parsed_json LONGTEXT,
    validation_errors LONGTEXT,
    ai_scores LONGTEXT,
    retry_count INT DEFAULT 0,
    generation_time DECIMAL(8,3) DEFAULT 0,
    token_usage INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'blog_ai_scores' => "
CREATE TABLE IF NOT EXISTS blog_ai_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    seo_score INT DEFAULT 0,
    ctr_score INT DEFAULT 0,
    emotional_score INT DEFAULT 0,
    readability_score INT DEFAULT 0,
    conversion_score INT DEFAULT 0,
    human_score INT DEFAULT 0,
    trust_score INT DEFAULT 0,
    engagement_score INT DEFAULT 0,
    overall_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'internal_link_rules' => "
CREATE TABLE IF NOT EXISTS internal_link_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(255) NOT NULL,
    target_url VARCHAR(500) NOT NULL,
    anchor_text VARCHAR(255),
    priority INT DEFAULT 5,
    max_usage INT DEFAULT 2,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'cta_templates' => "
CREATE TABLE IF NOT EXISTS cta_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    button_text VARCHAR(100),
    button_link VARCHAR(500),
    style_type ENUM('banner','inline','popup','sticky') DEFAULT 'inline',
    position_rule ENUM('after_intro','mid_content','before_conclusion','after_conclusion') DEFAULT 'mid_content',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'thumbnail_prompt_templates' => "
CREATE TABLE IF NOT EXISTS thumbnail_prompt_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emotion_type VARCHAR(100) NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    prompt_template LONGTEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        $results[] = ['table' => $name, 'status' => 'success', 'message' => 'Created / already exists'];
    } catch (PDOException $e) {
        $results[] = ['table' => $name, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// Insert default ai_blog_settings if empty
try {
    $count = $db->query("SELECT COUNT(*) FROM ai_blog_settings")->fetchColumn();
    if ($count == 0) {
        $defaultSystemPrompt = 'You are an enterprise-grade AI publishing engine for {{brand_name}} ({{website_name}}), a premium ecommerce store.

YOUR ROLE:
- SEO strategist
- Conversion copywriter
- Emotional marketer
- Media publisher
- Ecommerce content strategist

TARGET AUDIENCE: {{target_audience}}
BRAND TONE: {{brand_tone}}
BLOG CATEGORY: {{blog_category}}
MAIN KEYWORD: {{main_keyword}}
SECONDARY KEYWORDS: {{secondary_keywords}}
FEATURED PRODUCTS: {{products}}
INTERNAL LINKS: {{internal_links}}
GOAL: {{goal}}

WRITING RULES:
1. Write human-like, emotionally engaging content
2. Optimize every sentence for search intent
3. Use power words, emotional triggers, curiosity gaps
4. Short paragraphs (2-3 sentences max)
5. Use numbers, statistics, facts to build trust
6. Naturally weave in products without being salesy
7. Every heading must be CTR-optimized
8. Include urgency and social proof naturally
9. Write for mobile-first reading
10. Minimize passive voice, maximize active voice

SEO RULES:
{{seo_rules}}

EMOTIONAL WRITING RULES:
{{emotional_rules}}

CONVERSION RULES:
{{conversion_rules}}

BRAND VOICE:
{{brand_voice_rules}}

YOU MUST RETURN ONLY VALID JSON. NO MARKDOWN. NO PREAMBLE. NO EXPLANATION.';

        $defaultSeoRules = '- Include main keyword in title, first 100 words, at least 2 H2 headings, conclusion
- Use LSI keywords and semantic variations naturally
- Target 1-1.5% keyword density (no stuffing)
- Every H2/H3 must be a question or contain a power word
- Include featured snippet block: a concise 40-60 word direct answer
- Add schema-ready FAQ with minimum 5 questions
- Meta title: 50-60 chars, include keyword + brand or number
- Meta description: 145-160 chars, include keyword + CTA word
- Slug: lowercase, hyphens only, max 5 words, no stop words';

        $defaultEmotionalRules = '- Use emotional triggers: fear of missing out, curiosity, desire, trust
- Power words: secret, proven, guaranteed, exclusive, rare, authentic, discover
- Open loops in headings to force readers to continue
- Use "you" directly to create personal connection
- Include social proof language: "thousands of customers", "trusted by families"
- Add sensory language for product descriptions
- Create emotional contrast: problem → solution → transformation';

        $defaultCtrRules = '- Titles must include: number OR question OR power word OR "how to"
- Use odd numbers in titles (7, 9, 11) - they outperform even numbers
- Include year in evergreen titles where relevant
- Titles should promise a specific outcome or benefit
- Use brackets for clarity: [Complete Guide], [2025], [Proven Tips]
- First sentence must hook immediately - no slow intros';

        $defaultConversionRules = '- Insert product CTA after problem is established (after intro)
- Use benefit-focused product descriptions, not feature-focused
- Include price anchoring language (value vs cost)
- Add urgency: limited stock, seasonal, harvest-fresh
- Trust signals: authentic, certified, family-owned, farm-direct
- Always link products with contextual anchor text, never "click here"';

        $defaultBrandVoice = '- Warm, knowledgeable, trustworthy
- Expert but approachable - not corporate
- Celebrate tradition and authenticity
- Connect products to wellness, family, and quality of life
- Avoid hype, focus on genuine value
- Use "we" for brand, "you" for reader';

        $defaultReadabilityRules = '- Flesch reading score target: 60-70
- Average sentence length: under 20 words
- Paragraph length: 2-3 sentences max
- Use subheadings every 200-250 words
- Use bullet points for lists of 3+ items
- Bold the most important phrase in each section
- Use transition words: However, Moreover, In fact, As a result';

        $defaultFaqRules = '- Minimum 5 FAQs, maximum 8
- Questions must match real search queries (use "how", "what", "why", "is", "can")
- Answers: 40-80 words, direct, conversational
- Include main keyword naturally in at least 2 FAQ answers
- FAQs should cover: what it is, benefits, how to use, where to buy, safety/authenticity';

        $defaultSchemaRules = '- Always suggest Article schema with author, datePublished, dateModified
- Always suggest FAQPage schema for FAQ section
- Suggest BreadcrumbList schema
- For product mentions, suggest Product schema with name, description, offers
- Suggest HowTo schema when content includes step-by-step instructions';

        $defaultThumbnailRules = '- Thumbnail text: max 6 words, high contrast, emotional hook
- Include a number or power word in thumbnail text
- Suggest warm, appetizing color palettes for food/spice content
- Main visual element should be product in use, not just product alone
- Include lifestyle context (kitchen, cooking, wellness)
- Text should create curiosity or promise a benefit';

        $defaultValidationRules = '- Reject if title under 40 chars or over 70 chars
- Reject if meta description under 120 chars or over 165 chars
- Reject if content under 800 words
- Reject if no FAQ section
- Reject if no CTA mention
- Reject if keyword density over 3%
- Reject if any section heading is generic ("Introduction", "Conclusion", "Section 1")
- Reject if thumbnail text over 8 words
- Flag if passive voice over 20% of sentences
- Flag if same sentence structure repeated 3+ times consecutively';

        $db->prepare("INSERT INTO ai_blog_settings 
            (system_prompt, seo_rules, conversion_rules, thumbnail_rules, validation_rules, emotional_rules, ctr_rules, faq_rules, schema_rules, readability_rules, brand_voice_rules, ai_model, temperature, max_tokens, top_p, frequency_penalty, presence_penalty)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $defaultSystemPrompt, $defaultSeoRules, $defaultConversionRules,
            $defaultThumbnailRules, $defaultValidationRules, $defaultEmotionalRules,
            $defaultCtrRules, $defaultFaqRules, $defaultSchemaRules,
            $defaultReadabilityRules, $defaultBrandVoice,
            'gemini-2.0-flash', 0.75, 8000, 0.95, 0.10, 0.10
        ]);
        $results[] = ['table' => 'ai_blog_settings', 'status' => 'success', 'message' => 'Default settings inserted'];
    }
} catch (PDOException $e) {
    $results[] = ['table' => 'ai_blog_settings defaults', 'status' => 'error', 'message' => $e->getMessage()];
}

// Insert default CTA templates
try {
    $count = $db->query("SELECT COUNT(*) FROM cta_templates")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO cta_templates (title, content, button_text, button_link, style_type, position_rule) VALUES
            ('Shop Premium Kashmiri Saffron', 'Experience the world''s finest saffron — harvested fresh from the valleys of Kashmir. Pure, authentic, and delivered to your door.', 'Shop Now', '/shop', 'banner', 'mid_content'),
            ('Limited Stock — Order Today', 'Our harvest-fresh stock is limited. Thousands of families trust Gilaf Store for authentic Kashmiri products. Don''t miss out.', 'Order Now', '/shop', 'banner', 'before_conclusion'),
            ('Free Shipping on Orders Over ₹499', 'Get premium Kashmiri products delivered to your door. Free shipping on all orders above ₹499.', 'Browse Products', '/shop', 'inline', 'after_intro')
        ");
        $results[] = ['table' => 'cta_templates', 'status' => 'success', 'message' => 'Default CTA templates inserted'];
    }
} catch (PDOException $e) {
    $results[] = ['table' => 'cta_templates defaults', 'status' => 'error', 'message' => $e->getMessage()];
}

// Insert default thumbnail prompt templates
try {
    $count = $db->query("SELECT COUNT(*) FROM thumbnail_prompt_templates")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO thumbnail_prompt_templates (emotion_type, template_name, prompt_template) VALUES
            ('curiosity', 'Curiosity Hook', 'Photorealistic thumbnail for a blog about {{topic}}. Show {{product}} in a warm kitchen setting with natural lighting. Text overlay: \"{{thumbnail_text}}\". Colors: warm amber, golden tones. Style: premium lifestyle photography.'),
            ('trust', 'Authority & Trust', 'Professional product photography of {{product}} on clean white/cream background. Natural ingredients scattered around. Text: \"{{thumbnail_text}}\". Colors: earthy greens and golds. Premium, trustworthy feel.'),
            ('desire', 'Desire & Appetite', 'Close-up food photography showing {{product}} in use — sprinkled on food, in a spoon, or in a beautiful bowl. Warm lighting, steam, rich colors. Text: \"{{thumbnail_text}}\". Evoke taste and luxury.'),
            ('urgency', 'Urgency & Scarcity', 'Flat lay product shot of {{product}} with harvest-fresh aesthetic. Rustic wooden background, natural props. Bold text: \"{{thumbnail_text}}\". Colors: deep saffron orange, forest green. Premium handcrafted feel.')
        ");
        $results[] = ['table' => 'thumbnail_prompt_templates', 'status' => 'success', 'message' => 'Default thumbnail templates inserted'];
    }
} catch (PDOException $e) {
    $results[] = ['table' => 'thumbnail_prompt_templates defaults', 'status' => 'error', 'message' => $e->getMessage()];
}

// Insert default internal link rules
try {
    $count = $db->query("SELECT COUNT(*) FROM internal_link_rules")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO internal_link_rules (keyword, target_url, anchor_text, priority, max_usage) VALUES
            ('kashmiri saffron', '/product/kashmiri-saffron', 'premium Kashmiri saffron', 10, 2),
            ('buy saffron', '/shop', 'shop our saffron collection', 9, 1),
            ('saffron benefits', '/blog/kashmiri-honey-health-benefits', 'saffron health benefits', 7, 1),
            ('gilaf store', '/', 'Gilaf Store', 8, 2)
        ");
        $results[] = ['table' => 'internal_link_rules', 'status' => 'success', 'message' => 'Default internal link rules inserted'];
    }
} catch (PDOException $e) {
    $results[] = ['table' => 'internal_link_rules defaults', 'status' => 'error', 'message' => $e->getMessage()];
}

$adminPage = 'ai_blog_settings';
include __DIR__ . '/admin_header.php';
?>
<style>
.setup-container { max-width: 800px; margin: 0 auto; }
.result-item { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:8px; margin-bottom:8px; }
.result-item.success { background:#f0fdf4; border:1px solid #bbf7d0; }
.result-item.error { background:#fef2f2; border:1px solid #fecaca; }
.result-icon { font-size:1.2rem; }
.result-item.success .result-icon { color:#16a34a; }
.result-item.error .result-icon { color:#dc2626; }
.result-table { font-weight:600; min-width:220px; }
.setup-hero { background:linear-gradient(135deg,#1a3c34,#2d6a4f); color:white; border-radius:12px; padding:32px; margin-bottom:24px; }
</style>

<div class="setup-container">
    <div class="setup-hero">
        <h1><i class="fas fa-robot me-2"></i>AI Blog Engine Setup</h1>
        <p class="mb-0 opacity-75">Database migration for the enterprise AI publishing system</p>
    </div>

    <div class="card shadow-sm p-4">
        <h5 class="mb-3"><i class="fas fa-database me-2 text-primary"></i>Migration Results</h5>
        <?php foreach ($results as $r): ?>
            <div class="result-item <?= $r['status'] ?>">
                <span class="result-icon">
                    <i class="fas fa-<?= $r['status'] === 'success' ? 'check-circle' : 'times-circle' ?>"></i>
                </span>
                <span class="result-table"><?= htmlspecialchars($r['table']) ?></span>
                <span class="text-muted small"><?= htmlspecialchars($r['message']) ?></span>
            </div>
        <?php endforeach; ?>

        <hr>
        <div class="d-flex gap-3 mt-3">
            <a href="<?= base_url('admin/ai_blog_settings.php') ?>" class="btn btn-primary">
                <i class="fas fa-cog me-2"></i>Go to AI Settings
            </a>
            <a href="<?= base_url('admin/manage_blogs.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-newspaper me-2"></i>Back to Blogs
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
