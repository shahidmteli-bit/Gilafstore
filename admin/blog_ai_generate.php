<?php
/**
 * AI Blog Generator
 * Deep keyword research + SEO-optimized blog generation
 * Uses existing Gemini/OpenAI/Claude API integration
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

require_admin();

$pageTitle = 'AI Blog Generator — Gilaf Store';
$adminPage = 'blog_ai';

$db = get_db_connection();

// Load AI settings from chatbot_settings
$ai_settings = [
    'api_key' => '',
    'ai_provider' => 'gemini',
    'ai_model' => 'gemini-2.0-flash',
    'temperature' => 0.7,
    'max_tokens' => 8000
];

try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM chatbot_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($rows)) {
        $ai_settings['api_key'] = $rows['api_key'] ?? '';
        $ai_settings['ai_provider'] = $rows['ai_provider'] ?? 'gemini';
        $ai_settings['ai_model'] = $rows['ai_model'] ?? 'gemini-2.0-flash';
        $ai_settings['temperature'] = floatval($rows['temperature'] ?? 0.7);
    }
} catch (Exception $e) {}

// Fetch categories
$categories = [];
try {
    $categories = $db->query("SELECT * FROM blog_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch products for linking
$products = [];
try {
    $products = $db->query("SELECT id, name, slug FROM products WHERE is_active = 1 ORDER BY name LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.ai-generator-container {
    max-width: 1400px;
    margin: 0 auto;
}
.ai-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    padding: 28px;
    margin-bottom: 24px;
}
.ai-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}
.ai-card-header i {
    font-size: 24px;
    color: #6366f1;
}
.ai-card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}
.step-indicator {
    display: flex;
    gap: 8px;
    margin-bottom: 32px;
}
.step {
    flex: 1;
    padding: 16px;
    background: #f3f4f6;
    border-radius: 12px;
    text-align: center;
    position: relative;
    transition: all 0.3s;
}
.step.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}
.step.completed {
    background: #10b981;
    color: #fff;
}
.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-bottom: 8px;
}
.step.active .step-number,
.step.completed .step-number {
    background: rgba(255,255,255,0.2);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #374151;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.keyword-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}
.keyword-tag {
    background: #e0e7ff;
    color: #4338ca;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.keyword-tag:hover {
    background: #6366f1;
    color: #fff;
}
.keyword-tag.selected {
    background: #6366f1;
    color: #fff;
}
.btn-ai {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    padding: 14px 28px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}
.btn-ai:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99,102,241,0.3);
}
.btn-ai:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.btn-ai.loading i {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.research-results {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}
.research-section {
    margin-bottom: 20px;
}
.research-section h4 {
    font-size: 14px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}
.trending-topic {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.trending-topic:hover {
    border-color: #6366f1;
    box-shadow: 0 4px 12px rgba(99,102,241,0.1);
}
.trending-topic.selected {
    border-color: #6366f1;
    background: #eef2ff;
}
.trending-topic h5 {
    margin: 0 0 6px 0;
    font-size: 15px;
    color: #1f2937;
}
.trending-topic p {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
}
.content-preview {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    max-height: 600px;
    overflow-y: auto;
}
.content-preview h1 {
    font-size: 28px;
    color: #1f2937;
    margin-bottom: 16px;
}
.content-preview h2 {
    font-size: 22px;
    color: #374151;
    margin: 24px 0 12px;
}
.content-preview p {
    line-height: 1.8;
    color: #4b5563;
    margin-bottom: 16px;
}
.seo-score-card {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.seo-score-value {
    font-size: 48px;
    font-weight: 700;
}
.product-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    max-height: 300px;
    overflow-y: auto;
    padding: 12px;
    background: #f9fafb;
    border-radius: 10px;
}
.product-item {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
}
.product-item:hover {
    border-color: #6366f1;
}
.product-item.selected {
    border-color: #6366f1;
    background: #eef2ff;
}
.product-item input {
    display: none;
}
.generation-progress {
    display: none;
    margin-top: 20px;
}
.progress-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    width: 0%;
    transition: width 0.5s;
}
.progress-status {
    margin-top: 12px;
    font-size: 14px;
    color: #6b7280;
}
.tone-options {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.tone-option {
    flex: 1;
    min-width: 120px;
    padding: 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.tone-option:hover {
    border-color: #6366f1;
}
.tone-option.selected {
    border-color: #6366f1;
    background: #eef2ff;
}
.tone-option i {
    font-size: 24px;
    margin-bottom: 8px;
    display: block;
}
.tone-option span {
    font-weight: 600;
    font-size: 14px;
}
.length-slider {
    margin-top: 12px;
}
.length-labels {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6b7280;
    margin-top: 8px;
}
.api-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 20px;
}
.api-status.connected {
    background: #d1fae5;
    color: #065f46;
}
.api-status.disconnected {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="ai-generator-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-robot me-2" style="color:#6366f1;"></i>AI Blog Generator</h2>
                <p class="text-muted mb-0">Generate SEO-optimized blogs with deep keyword research</p>
            </div>
            <a href="<?= base_url('admin/manage_blogs.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Blogs
            </a>
        </div>

        <!-- API Status -->
        <?php if (empty($ai_settings['api_key'])): ?>
        <div class="api-status disconnected">
            <i class="fas fa-exclamation-circle"></i>
            <span>AI API key not configured. Please configure it in <a href="<?= base_url('admin/chatbot_settings.php') ?>">Chatbot Settings</a></span>
        </div>
        <?php else: ?>
        <div class="api-status connected">
            <i class="fas fa-check-circle"></i>
            <span>Connected to <?= ucfirst($ai_settings['ai_provider']) ?> (<?= $ai_settings['ai_model'] ?>)</span>
        </div>
        <?php endif; ?>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1-indicator">
                <div class="step-number">1</div>
                <div>Topic & Keywords</div>
            </div>
            <div class="step" id="step2-indicator">
                <div class="step-number">2</div>
                <div>Research & Trends</div>
            </div>
            <div class="step" id="step3-indicator">
                <div class="step-number">3</div>
                <div>Generate Content</div>
            </div>
            <div class="step" id="step4-indicator">
                <div class="step-number">4</div>
                <div>Review & Publish</div>
            </div>
        </div>

        <!-- Step 1: Topic & Keywords -->
        <div id="step1" class="step-content">
            <div class="row">
                <div class="col-lg-8">
                    <div class="ai-card">
                        <div class="ai-card-header">
                            <i class="fas fa-lightbulb"></i>
                            <h3>What do you want to write about?</h3>
                        </div>

                        <div class="form-group">
                            <label>Blog Category</label>
                            <select id="category" class="form-select">
                                <option value="">Select a category...</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" data-slug="<?= htmlspecialchars($cat['slug']) ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Topic or Main Keyword</label>
                            <input type="text" id="mainTopic" placeholder="e.g., Benefits of Kashmiri Saffron for Health" />
                            <small class="text-muted">Enter your main topic or primary keyword</small>
                        </div>

                        <div class="form-group">
                            <label>Target Keywords (comma separated)</label>
                            <textarea id="targetKeywords" rows="2" placeholder="e.g., kashmiri saffron, saffron benefits, kesar health benefits, buy saffron online"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Products to Feature</label>
                            <div class="product-selector">
                                <?php foreach ($products as $product): ?>
                                <label class="product-item">
                                    <input type="checkbox" name="products[]" value="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" />
                                    <span><?= htmlspecialchars($product['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="button" class="btn-ai" onclick="startResearch()" <?= empty($ai_settings['api_key']) ? 'disabled' : '' ?>>
                            <i class="fas fa-search"></i>
                            Research Keywords & Trends
                        </button>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="ai-card">
                        <div class="ai-card-header">
                            <i class="fas fa-cog"></i>
                            <h3>Content Settings</h3>
                        </div>

                        <div class="form-group">
                            <label>Writing Tone</label>
                            <div class="tone-options">
                                <div class="tone-option selected" data-tone="professional">
                                    <i class="fas fa-briefcase"></i>
                                    <span>Professional</span>
                                </div>
                                <div class="tone-option" data-tone="friendly">
                                    <i class="fas fa-smile"></i>
                                    <span>Friendly</span>
                                </div>
                                <div class="tone-option" data-tone="educational">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span>Educational</span>
                                </div>
                                <div class="tone-option" data-tone="persuasive">
                                    <i class="fas fa-bullhorn"></i>
                                    <span>Persuasive</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Content Length</label>
                            <input type="range" id="contentLength" min="800" max="3000" value="1500" class="form-range length-slider" />
                            <div class="length-labels">
                                <span>Short (800)</span>
                                <span id="lengthValue">1500 words</span>
                                <span>Long (3000)</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="includeFaqs" checked /> Include FAQ Section
                            </label>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="includeProductBlocks" checked /> Include Product Recommendations
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Research Results -->
        <div id="step2" class="step-content" style="display:none;">
            <div class="ai-card">
                <div class="ai-card-header">
                    <i class="fas fa-chart-line"></i>
                    <h3>Keyword Research & Trending Topics</h3>
                </div>

                <div class="generation-progress" id="researchProgress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="researchProgressFill"></div>
                    </div>
                    <div class="progress-status" id="researchStatus">Analyzing keywords...</div>
                </div>

                <div class="research-results" id="researchResults" style="display:none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="research-section">
                                <h4><i class="fas fa-key me-2"></i>Suggested Keywords</h4>
                                <div class="keyword-tags" id="suggestedKeywords"></div>
                            </div>

                            <div class="research-section">
                                <h4><i class="fas fa-search me-2"></i>Related Search Terms</h4>
                                <div class="keyword-tags" id="relatedTerms"></div>
                            </div>

                            <div class="research-section">
                                <h4><i class="fas fa-hashtag me-2"></i>Long-tail Keywords</h4>
                                <div class="keyword-tags" id="longTailKeywords"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="research-section">
                                <h4><i class="fas fa-fire me-2"></i>Trending Blog Ideas</h4>
                                <div id="trendingTopics"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </button>
                        <button type="button" class="btn-ai" onclick="generateContent()">
                            <i class="fas fa-magic"></i>
                            Generate Blog Content
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Content Generation -->
        <div id="step3" class="step-content" style="display:none;">
            <div class="ai-card">
                <div class="ai-card-header">
                    <i class="fas fa-pen-fancy"></i>
                    <h3>Generating Your Blog</h3>
                </div>

                <div class="generation-progress" id="contentProgress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="contentProgressFill"></div>
                    </div>
                    <div class="progress-status" id="contentStatus">Crafting your SEO-optimized content...</div>
                </div>
            </div>
        </div>

        <!-- Step 4: Review & Publish -->
        <div id="step4" class="step-content" style="display:none;">
            <div class="row">
                <div class="col-lg-8">
                    <div class="ai-card">
                        <div class="ai-card-header">
                            <i class="fas fa-file-alt"></i>
                            <h3>Generated Blog Content</h3>
                        </div>

                        <div class="form-group">
                            <label>Blog Title</label>
                            <input type="text" id="generatedTitle" />
                        </div>

                        <div class="form-group">
                            <label>Meta Description</label>
                            <textarea id="generatedMetaDesc" rows="2"></textarea>
                            <small class="text-muted"><span id="metaDescCount">0</span>/160 characters</small>
                        </div>

                        <div class="form-group">
                            <label>Blog Content</label>
                            <div class="content-preview" id="contentPreview"></div>
                            <textarea id="generatedContent" style="display:none;"></textarea>
                        </div>

                        <div class="form-group" id="faqsSection">
                            <label>Generated FAQs</label>
                            <div id="generatedFaqs"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="ai-card">
                        <div class="seo-score-card mb-4">
                            <div class="seo-score-value" id="seoScoreValue">0%</div>
                            <div>SEO Score</div>
                        </div>

                        <div class="form-group">
                            <label>SEO Keywords Used</label>
                            <div class="keyword-tags" id="usedKeywords"></div>
                        </div>

                        <div class="form-group">
                            <label>Word Count</label>
                            <div class="fw-bold" id="wordCount">0 words</div>
                        </div>

                        <div class="form-group">
                            <label>Reading Time</label>
                            <div class="fw-bold" id="readingTime">0 min</div>
                        </div>
                    </div>

                    <div class="ai-card">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn-ai" onclick="saveBlog('draft')">
                                <i class="fas fa-save"></i>
                                Save as Draft
                            </button>
                            <button type="button" class="btn btn-success btn-lg" onclick="saveBlog('published')">
                                <i class="fas fa-paper-plane me-2"></i>
                                Publish Now
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="regenerateContent()">
                                <i class="fas fa-redo me-2"></i>
                                Regenerate
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                                <i class="fas fa-arrow-left me-2"></i>
                                Start Over
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
let researchData = {};
let generatedData = {};
let selectedTone = 'professional';
let selectedProducts = [];

// Tone selection
document.querySelectorAll('.tone-option').forEach(opt => {
    opt.addEventListener('click', function() {
        document.querySelectorAll('.tone-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        selectedTone = this.dataset.tone;
    });
});

// Product selection
document.querySelectorAll('.product-item').forEach(item => {
    item.addEventListener('click', function() {
        this.classList.toggle('selected');
        const checkbox = this.querySelector('input');
        checkbox.checked = !checkbox.checked;
    });
});

// Length slider
document.getElementById('contentLength').addEventListener('input', function() {
    document.getElementById('lengthValue').textContent = this.value + ' words';
});

// Meta description counter
document.getElementById('generatedMetaDesc').addEventListener('input', function() {
    document.getElementById('metaDescCount').textContent = this.value.length;
});

function goToStep(step) {
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.step-indicator .step').forEach(s => {
        s.classList.remove('active', 'completed');
    });
    
    // Show target step
    document.getElementById('step' + step).style.display = 'block';
    
    // Update indicators
    for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById('step' + i + '-indicator');
        if (i < step) {
            indicator.classList.add('completed');
        } else if (i === step) {
            indicator.classList.add('active');
        }
    }
    
    currentStep = step;
}

async function startResearch() {
    const category = document.getElementById('category').value;
    const mainTopic = document.getElementById('mainTopic').value.trim();
    const targetKeywords = document.getElementById('targetKeywords').value.trim();
    
    if (!mainTopic) {
        alert('Please enter a topic or main keyword');
        return;
    }
    
    // Get selected products
    selectedProducts = [];
    document.querySelectorAll('.product-item input:checked').forEach(cb => {
        selectedProducts.push({
            id: cb.value,
            name: cb.dataset.name
        });
    });
    
    goToStep(2);
    
    // Show progress
    document.getElementById('researchProgress').style.display = 'block';
    document.getElementById('researchResults').style.display = 'none';
    
    const progressFill = document.getElementById('researchProgressFill');
    const statusText = document.getElementById('researchStatus');
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        progressFill.style.width = progress + '%';
    }, 500);
    
    const stages = [
        'Analyzing your topic...',
        'Researching trending keywords...',
        'Finding related search terms...',
        'Analyzing competitor content...',
        'Generating blog ideas...'
    ];
    
    let stageIndex = 0;
    const stageInterval = setInterval(() => {
        if (stageIndex < stages.length) {
            statusText.textContent = stages[stageIndex];
            stageIndex++;
        }
    }, 1500);
    
    try {
        const response = await fetch('<?= base_url("admin/blog_ai_api.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'research',
                topic: mainTopic,
                keywords: targetKeywords,
                category: category,
                products: selectedProducts
            })
        });
        
        const data = await response.json();
        
        clearInterval(progressInterval);
        clearInterval(stageInterval);
        
        if (data.success) {
            progressFill.style.width = '100%';
            statusText.textContent = 'Research complete!';
            
            researchData = data.research;
            
            setTimeout(() => {
                displayResearchResults(data.research);
            }, 500);
        } else {
            throw new Error(data.message || 'Research failed');
        }
    } catch (error) {
        clearInterval(progressInterval);
        clearInterval(stageInterval);
        alert('Error: ' + error.message);
        goToStep(1);
    }
}

function displayResearchResults(research) {
    document.getElementById('researchProgress').style.display = 'none';
    document.getElementById('researchResults').style.display = 'block';
    
    // Suggested keywords
    const suggestedKeywords = document.getElementById('suggestedKeywords');
    suggestedKeywords.innerHTML = '';
    (research.suggestedKeywords || []).forEach(kw => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag';
        tag.textContent = kw;
        tag.onclick = () => tag.classList.toggle('selected');
        suggestedKeywords.appendChild(tag);
    });
    
    // Related terms
    const relatedTerms = document.getElementById('relatedTerms');
    relatedTerms.innerHTML = '';
    (research.relatedTerms || []).forEach(term => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag';
        tag.textContent = term;
        tag.onclick = () => tag.classList.toggle('selected');
        relatedTerms.appendChild(tag);
    });
    
    // Long-tail keywords
    const longTailKeywords = document.getElementById('longTailKeywords');
    longTailKeywords.innerHTML = '';
    (research.longTailKeywords || []).forEach(kw => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag';
        tag.textContent = kw;
        tag.onclick = () => tag.classList.toggle('selected');
        longTailKeywords.appendChild(tag);
    });
    
    // Trending topics
    const trendingTopics = document.getElementById('trendingTopics');
    trendingTopics.innerHTML = '';
    (research.trendingTopics || []).forEach((topic, index) => {
        const div = document.createElement('div');
        div.className = 'trending-topic' + (index === 0 ? ' selected' : '');
        div.innerHTML = `<h5>${topic.title}</h5><p>${topic.description}</p>`;
        div.onclick = () => {
            document.querySelectorAll('.trending-topic').forEach(t => t.classList.remove('selected'));
            div.classList.add('selected');
            researchData.selectedTopic = topic;
        };
        trendingTopics.appendChild(div);
        
        if (index === 0) {
            researchData.selectedTopic = topic;
        }
    });
}

async function generateContent() {
    goToStep(3);
    
    const progressFill = document.getElementById('contentProgressFill');
    const statusText = document.getElementById('contentStatus');
    
    document.getElementById('contentProgress').style.display = 'block';
    
    // Get selected keywords
    const selectedKeywords = [];
    document.querySelectorAll('.keyword-tag.selected').forEach(tag => {
        selectedKeywords.push(tag.textContent);
    });
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 8;
        if (progress > 95) progress = 95;
        progressFill.style.width = progress + '%';
    }, 800);
    
    const stages = [
        'Creating outline...',
        'Writing introduction...',
        'Developing main content...',
        'Adding product recommendations...',
        'Generating FAQs...',
        'Optimizing for SEO...',
        'Final polish...'
    ];
    
    let stageIndex = 0;
    const stageInterval = setInterval(() => {
        if (stageIndex < stages.length) {
            statusText.textContent = stages[stageIndex];
            stageIndex++;
        }
    }, 2000);
    
    try {
        const response = await fetch('<?= base_url("admin/blog_ai_api.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'generate',
                topic: document.getElementById('mainTopic').value,
                selectedTopic: researchData.selectedTopic,
                keywords: selectedKeywords,
                research: researchData,
                tone: selectedTone,
                length: document.getElementById('contentLength').value,
                includeFaqs: document.getElementById('includeFaqs').checked,
                includeProducts: document.getElementById('includeProductBlocks').checked,
                products: selectedProducts,
                category: document.getElementById('category').value
            })
        });
        
        const data = await response.json();
        
        clearInterval(progressInterval);
        clearInterval(stageInterval);
        
        if (data.success) {
            progressFill.style.width = '100%';
            statusText.textContent = 'Content generated!';
            
            generatedData = data.content;
            
            setTimeout(() => {
                displayGeneratedContent(data.content);
            }, 500);
        } else {
            throw new Error(data.message || 'Generation failed');
        }
    } catch (error) {
        clearInterval(progressInterval);
        clearInterval(stageInterval);
        alert('Error: ' + error.message);
        goToStep(2);
    }
}

function displayGeneratedContent(content) {
    goToStep(4);
    
    // Ensure metaDescription is a string
    let metaDesc = content.metaDescription || '';
    if (typeof metaDesc === 'object') {
        metaDesc = metaDesc.text || JSON.stringify(metaDesc);
    }
    // Clean any JSON artifacts
    metaDesc = String(metaDesc).replace(/^\{.*"title":/g, '').replace(/\}$/g, '').trim();
    if (metaDesc.startsWith('"')) metaDesc = metaDesc.slice(1);
    if (metaDesc.endsWith('"')) metaDesc = metaDesc.slice(0, -1);
    
    document.getElementById('generatedTitle').value = content.title || '';
    document.getElementById('generatedMetaDesc').value = metaDesc;
    document.getElementById('metaDescCount').textContent = metaDesc.length;
    document.getElementById('generatedContent').value = content.content || '';
    document.getElementById('contentPreview').innerHTML = content.contentHtml || content.content || '';
    
    // FAQs
    const faqsContainer = document.getElementById('generatedFaqs');
    faqsContainer.innerHTML = '';
    if (content.faqs && content.faqs.length > 0) {
        content.faqs.forEach((faq, index) => {
            const div = document.createElement('div');
            div.className = 'mb-3 p-3 bg-light rounded';
            div.innerHTML = `
                <strong>Q${index + 1}: ${faq.question}</strong>
                <p class="mb-0 mt-2">${faq.answer}</p>
            `;
            faqsContainer.appendChild(div);
        });
        document.getElementById('faqsSection').style.display = 'block';
    } else {
        document.getElementById('faqsSection').style.display = 'none';
    }
    
    // Keywords used
    const usedKeywords = document.getElementById('usedKeywords');
    usedKeywords.innerHTML = '';
    (content.keywordsUsed || []).forEach(kw => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag selected';
        tag.textContent = kw;
        usedKeywords.appendChild(tag);
    });
    
    // Stats
    const wordCount = (content.content || '').split(/\s+/).filter(w => w).length;
    document.getElementById('wordCount').textContent = wordCount + ' words';
    document.getElementById('readingTime').textContent = Math.ceil(wordCount / 200) + ' min';
    document.getElementById('seoScoreValue').textContent = (content.seoScore || 85) + '%';
}

function regenerateContent() {
    if (confirm('Regenerate the blog content? This will replace the current content.')) {
        generateContent();
    }
}

async function saveBlog(status) {
    const title = document.getElementById('generatedTitle').value.trim();
    const content = document.getElementById('generatedContent').value.trim();
    const metaDesc = document.getElementById('generatedMetaDesc').value.trim();
    
    if (!title || !content) {
        alert('Title and content are required');
        return;
    }
    
    // Collect FAQs
    const faqs = generatedData.faqs || [];
    
    try {
        const response = await fetch('<?= base_url("admin/blog_actions.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'save',
                title: title,
                content: content,
                excerpt: metaDesc,
                meta_title: title,
                meta_description: metaDesc,
                meta_keywords: (generatedData.keywordsUsed || []).join(', '),
                category_id: document.getElementById('category').value,
                status: status,
                reading_time: Math.ceil(content.split(/\s+/).length / 200),
                faqs: JSON.stringify(faqs),
                products: JSON.stringify(selectedProducts.map(p => ({
                    product_id: p.id,
                    display_type: 'bottom',
                    display_order: 0
                })))
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Blog ' + (status === 'published' ? 'published' : 'saved as draft') + ' successfully!');
            window.location.href = '<?= base_url("admin/manage_blogs.php") ?>';
        } else {
            throw new Error(data.message || 'Save failed');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
