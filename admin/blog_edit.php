<?php
/**
 * Blog Editor - Create/Edit Blog with Product Linking
 * Professional SEO-focused blog editor
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

require_admin();

$adminPage = 'blog_create';

$db = get_db_connection();
$blogId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$blog = null;
$linkedProducts = [];
$linkedBlogs = [];
$faqs = [];

// Load AI settings from chatbot_settings table
$ai_settings = ['api_key' => '', 'ai_provider' => 'gemini', 'ai_model' => 'gemini-2.0-flash'];
try {
    // Check if table exists first
    $tableCheck = $db->query("SHOW TABLES LIKE 'chatbot_settings'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM chatbot_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($rows)) {
            $ai_settings['api_key'] = $rows['api_key'] ?? '';
            $ai_settings['ai_provider'] = $rows['ai_provider'] ?? 'gemini';
            $ai_settings['ai_model'] = $rows['ai_model'] ?? 'gemini-2.0-flash';
        }
    }
} catch (PDOException $e) {
    // Database error, use defaults
} catch (Exception $e) {
    // AI settings table may not exist, use defaults
}

// Fetch blog if editing
if ($blogId > 0) {
    $stmt = $db->prepare("SELECT * FROM blogs WHERE id = ?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($blog) {
        // Fetch linked products
        $stmt = $db->prepare("
            SELECT bp.*, p.name, p.image 
            FROM blog_products bp 
            JOIN products p ON p.id = bp.product_id 
            WHERE bp.blog_id = ? 
            ORDER BY bp.display_order ASC
        ");
        $stmt->execute([$blogId]);
        $linkedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch linked related blogs
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS blog_internal_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blog_id INT NOT NULL,
                linked_blog_id INT NOT NULL,
                display_type VARCHAR(20) DEFAULT 'bottom',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_link (blog_id, linked_blog_id),
                INDEX idx_blog (blog_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $db->exec("ALTER TABLE blog_internal_links ADD COLUMN IF NOT EXISTS display_type VARCHAR(20) DEFAULT 'bottom'"); } catch(\Exception $e) {}
            $stmt = $db->prepare("
                SELECT bil.linked_blog_id as id, b.title, b.slug, bil.display_type
                FROM blog_internal_links bil
                JOIN blogs b ON b.id = bil.linked_blog_id
                WHERE bil.blog_id = ?
                ORDER BY bil.id ASC
            ");
            $stmt->execute([$blogId]);
            $linkedBlogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $linkedBlogs = []; }

        // Fetch FAQs
        $stmt = $db->prepare("SELECT * FROM blog_faqs WHERE blog_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$blogId]);
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Fetch all published blogs for related blog search (excluding current)
$allBlogsForSearch = [];
try {
    $stmt = $db->prepare("SELECT id, title, slug FROM blogs WHERE status='published' AND id != ? ORDER BY title ASC");
    $stmt->execute([$blogId ?: 0]);
    $allBlogsForSearch = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $allBlogsForSearch = []; }

// Fetch categories
$categories = [];
try {
    $categories = $db->query("SELECT * FROM blog_categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Fetch all products for search (include slug for product links)
$products = [];
try {
    // Check if is_active column exists
    $hasIsActive = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM products LIKE 'is_active'");
        $hasIsActive = $checkCol->rowCount() > 0;
    } catch (Exception $e) {}
    
    if ($hasIsActive) {
        $products = $db->query("SELECT id, name, slug, image FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $products = $db->query("SELECT id, name, slug, image FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $products = [];
}

// Check if AI is configured
$aiConfigured = !empty($ai_settings['api_key']);

$pageTitle = $blog ? 'Edit Blog' : 'Create New Blog';
include __DIR__ . '/admin_header.php';
?>

<style>
/* Blog Editor Styles */
.blog-editor-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}
.blog-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}
.blog-editor-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a3c34;
    margin: 0;
}
.header-actions {
    display: flex;
    gap: 12px;
}
.btn-save {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26,60,52,0.3);
}
.btn-draft {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #ddd;
}
.btn-draft:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

/* Editor Layout */
.editor-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 30px;
}
@media (max-width: 1200px) {
    .editor-grid { grid-template-columns: 1fr; }
}

.editor-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 25px;
}
.editor-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a3c34;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}
.editor-card-title i {
    color: #c5a059;
}

/* Form Fields */
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 0.9rem;
}
.form-group label .required {
    color: #dc3545;
}
.form-group input[type="text"],
.form-group input[type="url"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.2s;
}
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: #1a3c34;
    box-shadow: 0 0 0 3px rgba(26,60,52,0.1);
    outline: none;
}
.form-group .char-count {
    text-align: right;
    font-size: 0.8rem;
    color: #888;
    margin-top: 5px;
}
.form-group .char-count.warning { color: #ffc107; }
.form-group .char-count.danger { color: #dc3545; }

/* Title Input */
.title-input {
    font-size: 1.5rem;
    font-weight: 600;
    padding: 15px 20px;
}

/* Slug Input */
.slug-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.slug-prefix {
    background: #f8f9fa;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 10px 0 0 10px;
    color: #666;
    font-size: 0.9rem;
    white-space: nowrap;
}
.slug-group input {
    border-radius: 0 10px 10px 0 !important;
    flex: 1;
}

/* Content Editor */
.content-editor {
    min-height: 400px;
    border: 1px solid #ddd;
    border-radius: 10px;
}

/* Featured Image */
.featured-image-upload {
    border: 2px dashed #ddd;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.featured-image-upload:hover {
    border-color: #1a3c34;
    background: #fafafa;
}
.featured-image-upload.has-image {
    padding: 0;
    border-style: solid;
}
.featured-image-upload img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 10px;
}
.featured-image-upload .upload-placeholder {
    color: #888;
}
.featured-image-upload .upload-placeholder i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #ddd;
}
.featured-image-upload input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}
.remove-image {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(220,53,69,0.9);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 10;
}

/* Product Linking */
.product-search-box {
    position: relative;
    margin-bottom: 15px;
}
.product-search-box input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 0.95rem;
}
.product-search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
}
.product-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 10px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    display: none;
}
.product-search-results.show { display: block; }
.product-search-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid #f0f0f0;
}
.product-search-item:last-child { border-bottom: none; }
.product-search-item:hover { background: #f8f9fa; }
.product-search-item img {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    object-fit: cover;
}
.product-search-item .product-name {
    flex: 1;
    font-weight: 500;
    color: #333;
}
.product-search-item .add-btn {
    background: #1a3c34;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
}

/* Linked Products List */
.linked-products {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.linked-product-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #eee;
}
.linked-product-item img {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
}
.linked-product-item .product-info {
    flex: 1;
}
.linked-product-item .product-info h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 4px 0;
}
.linked-product-item .product-info select {
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 0.8rem;
}
.linked-product-item .remove-product {
    background: #ffebee;
    color: #d32f2f;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}
.linked-product-item .remove-product:hover {
    background: #d32f2f;
    color: white;
}

/* FAQ Section */
.faq-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.faq-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    border: 1px solid #eee;
}
.faq-item input,
.faq-item textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-bottom: 10px;
}
.faq-item textarea {
    min-height: 80px;
    resize: vertical;
}
.faq-item .faq-actions {
    display: flex;
    justify-content: flex-end;
}
.faq-item .remove-faq {
    background: #ffebee;
    color: #d32f2f;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
}
.btn-add-faq {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #e8f5e9;
    color: #2e7d32;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-add-faq:hover {
    background: #c8e6c9;
}

/* SEO Preview */
.seo-preview {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-top: 15px;
}
.seo-preview-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}
.seo-preview-url {
    color: #1a0dab;
    font-size: 0.85rem;
    margin-bottom: 5px;
}
.seo-preview-heading {
    color: #1a0dab;
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 5px;
    text-decoration: underline;
}
.seo-preview-desc {
    color: #545454;
    font-size: 0.85rem;
    line-height: 1.4;
}

/* SEO Score Indicator */
.seo-score-card {
    background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.seo-score-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 2rem;
    font-weight: 700;
}
.seo-score-label {
    font-size: 0.9rem;
    opacity: 0.9;
}
.seo-checklist {
    text-align: left;
    margin-top: 15px;
}
.seo-checklist-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-size: 0.85rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.seo-checklist-item:last-child { border-bottom: none; }
.seo-checklist-item i.check { color: #4caf50; }
.seo-checklist-item i.missing { color: #ff9800; }

/* Status Toggle */
.status-toggle {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.status-option {
    flex: 1;
    min-width: 100px;
}
.status-option input { display: none; }
.status-option label {
    display: block;
    padding: 12px 15px;
    text-align: center;
    border: 2px solid #ddd;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    font-size: 0.9rem;
}
.status-option input:checked + label {
    border-color: #1a3c34;
    background: rgba(26,60,52,0.05);
    color: #1a3c34;
}

/* Empty State */
.empty-products {
    text-align: center;
    padding: 30px;
    color: #888;
}
.empty-products i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    opacity: 0.3;
}

/* AI Generator Button & Panel */
.btn-ai-generate {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}
.btn-ai-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.ai-generator-panel {
    background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
    border: 2px solid #6366f1;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}
.ai-panel-header {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ai-panel-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}
.btn-close-ai {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    line-height: 1;
}
.btn-close-ai:hover {
    background: rgba(255,255,255,0.3);
}
.ai-panel-body {
    padding: 20px;
}
.ai-panel-body .form-group {
    margin-bottom: 15px;
}
.ai-panel-body label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #374151;
    font-size: 0.9rem;
}
.ai-panel-body input,
.ai-panel-body select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
}
.ai-panel-body input:focus,
.ai-panel-body select:focus {
    border-color: #6366f1;
    outline: none;
}
.ai-panel-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}
.btn-generate-content {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}
.btn-generate-content:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.btn-generate-content:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.btn-cancel-ai {
    background: #f3f4f6;
    color: #6b7280;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}
.btn-cancel-ai:hover {
    background: #e5e7eb;
}
.ai-progress {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 8px;
}
.ai-progress-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}
.ai-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    width: 0%;
    transition: width 0.5s;
    animation: progressPulse 1.5s ease-in-out infinite;
}
@keyframes progressPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
.ai-progress-text {
    margin-top: 10px;
    font-size: 0.85rem;
    color: #6b7280;
    text-align: center;
}

/* Editor Tabs */
.editor-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    background: #f8f9fa;
    padding: 5px;
    border-radius: 12px;
}
.editor-tab {
    flex: 1;
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #666;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.editor-tab:hover {
    background: rgba(26,60,52,0.05);
    color: #1a3c34;
}
.editor-tab.active {
    background: white;
    color: #1a3c34;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.editor-tab i {
    font-size: 1rem;
}
.editor-tab-content {
    display: none;
}
.editor-tab-content.active {
    display: block;
}

/* AI Research Panel */
.ai-research-panel {
    background: linear-gradient(135deg, #fafbff 0%, #f0f4ff 100%);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
}
.ai-research-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.ai-research-header h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1a3c34;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ai-research-header h3 i {
    color: #6366f1;
}
.ai-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.ai-status-badge.configured {
    background: #dcfce7;
    color: #166534;
}
.ai-status-badge.not-configured {
    background: #fef2f2;
    color: #991b1b;
}

/* Keyword Research Section */
.keyword-research-box {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.keyword-research-box h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.keyword-research-box h4 i {
    color: #6366f1;
}
.keyword-input-group {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.keyword-input-group input {
    flex: 1;
    padding: 12px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.95rem;
}
.keyword-input-group input:focus {
    border-color: #6366f1;
    outline: none;
}
.btn-research {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}
.btn-research:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.btn-research:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Title Suggestions Dropdown */
.title-suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.18);
    border: 2px solid #6366f1;
    z-index: 1000;
    max-height: 450px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.title-suggestions-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    gap: 8px;
}
.title-suggestions-header i {
    color: #fbbf24;
}
.title-suggestions-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.title-suggestions-close:hover {
    background: rgba(255,255,255,0.4);
}
.title-suggestions-list {
    overflow-y: auto;
    max-height: 340px;
    padding: 8px 0;
}
.title-suggestion-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 1px solid #f3f4f6;
}
.title-suggestion-item:last-child {
    border-bottom: none;
}
.title-suggestion-item:hover {
    background: #f0f0ff;
}
.title-suggestion-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
    font-weight: 700;
    color: white;
}
.title-suggestion-icon.shocking { background: #ef4444; }
.title-suggestion-icon.curiosity { background: #8b5cf6; }
.title-suggestion-icon.fear { background: #f97316; }
.title-suggestion-icon.numbered { background: #06b6d4; }
.title-suggestion-icon.question { background: #22c55e; }
.title-suggestion-icon.contrarian { background: #ec4899; }
.title-suggestion-text h5 {
    margin: 0 0 4px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.3;
}
.title-suggestion-text p {
    margin: 0;
    font-size: 0.75rem;
    color: #9ca3af;
    line-height: 1.3;
}
.title-suggestions-footer {
    padding: 10px 16px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}
.btn-refresh-titles {
    background: none;
    border: 1px solid #6366f1;
    color: #6366f1;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-refresh-titles:hover {
    background: #6366f1;
    color: white;
}
.title-suggestions-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px;
    color: #6366f1;
    font-size: 0.9rem;
    gap: 10px;
}

/* Trending Topics */
.trending-topics {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.trending-topics h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.trending-topics h4 i {
    color: #f59e0b;
}
.trending-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.trending-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}
.trending-item:hover {
    background: #f0f4ff;
    border-color: #6366f1;
}
.trending-item.selected {
    background: #ede9fe;
    border-color: #8b5cf6;
}
.trending-item .trend-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}
.trending-item .trend-info {
    flex: 1;
}
.trending-item .trend-info h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 4px 0;
}
.trending-item .trend-info p {
    font-size: 0.8rem;
    color: #666;
    margin: 0;
}
.trending-item .trend-score {
    background: #dcfce7;
    color: #166534;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Keywords Display */
.keywords-display {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.keywords-display h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.keywords-display h4 i {
    color: #10b981;
}
.keyword-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.keyword-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 20px;
    font-size: 0.85rem;
    color: #166534;
    cursor: pointer;
    transition: all 0.2s;
}
.keyword-tag:hover {
    background: #dcfce7;
}
.keyword-tag.selected {
    background: #166534;
    color: white;
    border-color: #166534;
}
.keyword-tag .remove-keyword {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0;
    font-size: 0.9rem;
    opacity: 0.7;
}
.keyword-tag .remove-keyword:hover {
    opacity: 1;
}

/* AI Content Options */
.ai-content-options {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.ai-content-options h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ai-content-options h4 i {
    color: #8b5cf6;
}
.ai-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.ai-option-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.85rem;
}
.ai-option-group select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
}
.ai-option-group select:focus {
    border-color: #6366f1;
    outline: none;
}
.ai-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}
.ai-checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ai-checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #6366f1;
}
.ai-checkbox-item label {
    font-size: 0.9rem;
    color: #374151;
    cursor: pointer;
}
.btn-generate-full {
    background: linear-gradient(135deg, #1a3c34, #2d5a4e);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}
.btn-generate-full:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26,60,52,0.3);
}
.btn-generate-full:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* AI Progress Steps */
.ai-workflow-progress {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
    display: none;
}
.ai-workflow-progress.active {
    display: block;
}
.workflow-steps {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.workflow-step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: all 0.3s;
}
.workflow-step.active {
    background: #ede9fe;
}
.workflow-step.completed {
    background: #dcfce7;
}
.workflow-step .step-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e5e7eb;
    color: #6b7280;
    font-size: 0.85rem;
}
.workflow-step.active .step-icon {
    background: #6366f1;
    color: white;
    animation: pulse 1.5s infinite;
}
.workflow-step.completed .step-icon {
    background: #10b981;
    color: white;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.workflow-step .step-text {
    flex: 1;
    font-size: 0.9rem;
    color: #374151;
}
.workflow-step.active .step-text {
    font-weight: 600;
    color: #6366f1;
}
.workflow-step.completed .step-text {
    color: #166534;
}

/* No AI Warning */
.no-ai-warning {
    background: #fef2f2;
    border: 2px solid #fecaca;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.no-ai-warning i {
    font-size: 2.5rem;
    color: #dc2626;
    margin-bottom: 15px;
}
.no-ai-warning h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #991b1b;
    margin: 0 0 10px 0;
}
.no-ai-warning p {
    color: #7f1d1d;
    margin: 0 0 15px 0;
    font-size: 0.9rem;
}
.no-ai-warning a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #dc2626;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
}
.no-ai-warning a:hover {
    background: #b91c1c;
}
</style>

<script>
// TAB SWITCHING - Isolated in its own script block so it ALWAYS works
// even if other JS has errors
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.editor-tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    var clickedTab = document.querySelector('.editor-tab[data-tab="' + tabName + '"]');
    if (clickedTab) clickedTab.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.editor-tab-content').forEach(function(content) {
        content.classList.remove('active');
        content.style.display = 'none';
    });
    var targetContent = document.getElementById('tab-' + tabName);
    if (targetContent) {
        targetContent.classList.add('active');
        targetContent.style.display = 'block';
    }
    
    // Sync SEO fields when switching to SEO tab
    if (tabName === 'seo' && typeof syncSeoTabFields === 'function') {
        setTimeout(syncSeoTabFields, 100);
    }
}
</script>

<form id="blogForm" method="POST" action="blog_actions.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $blogId; ?>">
    <input type="hidden" name="linked_products" id="linkedProductsInput" value="">
    <input type="hidden" name="linked_blogs" id="linkedBlogsInput" value="">
    <input type="hidden" name="faqs" id="faqsInput" value="">
    
    <div class="blog-editor-container">
        <!-- Header -->
        <div class="blog-editor-header">
            <div>
                <h1><i class="fas fa-edit me-2"></i><?= $pageTitle; ?></h1>
                <p class="text-muted mb-0">
                    <a href="manage_blogs.php" class="text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Blogs</a>
                </p>
            </div>
            <div class="header-actions">
                <button type="submit" name="status_action" value="draft" class="btn-save btn-draft">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="submit" name="status_action" value="publish" class="btn-save">
                    <i class="fas fa-paper-plane"></i> Publish
                </button>
            </div>
        </div>
        
        <!-- Editor Tabs -->
        <div class="editor-tabs">
            <button type="button" class="editor-tab active" data-tab="write" onclick="switchTab('write')">
                <i class="fas fa-edit"></i> Write
            </button>
            <button type="button" class="editor-tab" data-tab="ai-research" onclick="switchTab('ai-research')">
                <i class="fas fa-robot"></i> AI Research
            </button>
            <button type="button" class="editor-tab" data-tab="seo" onclick="switchTab('seo')">
                <i class="fas fa-search"></i> SEO Settings
            </button>
        </div>
        
        <!-- Editor Grid -->
        <div class="editor-grid">
            <!-- Main Content -->
            <div class="editor-main">
                
                <!-- ==================== WRITE TAB ==================== -->
                <div id="tab-write" class="editor-tab-content active">
                
                <!-- Title & Slug -->
                <div class="editor-card">
                    <div class="form-group">
                        <label>Blog Title <span class="required">*</span></label>
                        <input type="text" name="title" id="blogTitle" class="title-input" 
                               value="<?= htmlspecialchars($blog['title'] ?? ''); ?>" 
                               placeholder="Enter an engaging title..." required
                               oninput="generateSlug(); updateSeoPreview();">
                    </div>
                    <div class="form-group">
                        <label>URL Slug</label>
                        <div class="slug-group">
                            <span class="slug-prefix">gilafstore.com/blog/</span>
                            <input type="text" name="slug" id="blogSlug" 
                                   value="<?= htmlspecialchars($blog['slug'] ?? ''); ?>" 
                                   placeholder="auto-generated-slug">
                        </div>
                    </div>
                </div>
                
                <!-- Excerpt -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-align-left"></i> Excerpt</div>
                    <div class="form-group">
                        <textarea name="excerpt" id="blogExcerpt" rows="3" 
                                  placeholder="Write a compelling summary (150-200 characters)..."
                                  oninput="updateCharCount(this, 200); updateSeoPreview();"><?= htmlspecialchars($blog['excerpt'] ?? ''); ?></textarea>
                        <div class="char-count" id="excerptCount">0/200</div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="editor-card">
                    <div class="editor-card-title d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-file-alt"></i> Content</span>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn-ai-generate" onclick="document.getElementById('contentImageInput').click();" style="background:#3b82f6;color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:0.82rem;font-weight:600;cursor:pointer;">
                                <i class="fas fa-image"></i> Insert Image
                            </button>
                            <button type="button" class="btn-ai-generate" onclick="openAiGenerator()">
                                <i class="fas fa-robot"></i> Generate with AI
                            </button>
                        </div>
                    </div>
                    <input type="file" id="contentImageInput" accept="image/*" style="display:none;" onchange="uploadContentImage(this)">
                    
                    <!-- AI Generator Panel (Hidden by default) -->
                    <div id="aiGeneratorPanel" class="ai-generator-panel" style="display:none;">
                        <div class="ai-panel-header">
                            <h4><i class="fas fa-magic"></i> AI Content Generator</h4>
                            <button type="button" class="btn-close-ai" onclick="closeAiGenerator()">&times;</button>
                        </div>
                        <div class="ai-panel-body">
                            <div class="form-group">
                                <label>Topic / Main Keyword</label>
                                <input type="text" id="aiTopic" placeholder="e.g., Benefits of Kashmiri Saffron for Health">
                            </div>
                            <div class="form-group">
                                <label>Target Keywords (comma separated)</label>
                                <input type="text" id="aiKeywords" placeholder="e.g., kashmiri saffron, saffron benefits, kesar">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Writing Tone</label>
                                        <select id="aiTone">
                                            <option value="professional">Professional</option>
                                            <option value="friendly">Friendly</option>
                                            <option value="educational">Educational</option>
                                            <option value="persuasive">Persuasive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Content Length</label>
                                        <select id="aiLength">
                                            <option value="800">Short (~800 words)</option>
                                            <option value="1500" selected>Medium (~1500 words)</option>
                                            <option value="2500">Long (~2500 words)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><input type="checkbox" id="aiIncludeFaqs" checked> Generate FAQs</label>
                            </div>
                            <div class="ai-progress" id="aiProgress" style="display:none;">
                                <div class="ai-progress-bar"><div class="ai-progress-fill" id="aiProgressFill"></div></div>
                                <div class="ai-progress-text" id="aiProgressText">Generating content...</div>
                            </div>
                            <div class="ai-panel-actions">
                                <button type="button" class="btn-generate-content" onclick="generateAiContent()">
                                    <i class="fas fa-magic"></i> Generate Content
                                </button>
                                <button type="button" class="btn-cancel-ai" onclick="closeAiGenerator()">Cancel</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <textarea name="content" id="blogContent" rows="20" 
                                  placeholder="Write your blog content here... Use headings, lists, and formatting."><?= htmlspecialchars($blog['content'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- FAQs -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-question-circle"></i> FAQs (Schema Markup)</div>
                    <p class="text-muted small mb-3">Add FAQs to get rich snippets in Google search results</p>
                    
                    <div class="faq-list" id="faqList">
                        <?php if (!empty($faqs)): ?>
                            <?php foreach ($faqs as $index => $faq): ?>
                            <div class="faq-item" data-index="<?= $index; ?>">
                                <input type="text" class="faq-question" placeholder="Question" value="<?= htmlspecialchars($faq['question']); ?>">
                                <textarea class="faq-answer" placeholder="Answer"><?= htmlspecialchars($faq['answer']); ?></textarea>
                                <div class="faq-actions">
                                    <button type="button" class="remove-faq" onclick="removeFaq(this)">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-faq mt-3" onclick="addFaq()">
                        <i class="fas fa-plus"></i> Add FAQ
                    </button>
                </div>
                
                <!-- SEO Settings -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-search"></i> SEO Settings</div>
                    
                    <div class="form-group">
                        <label>Meta Title <small class="text-muted">(50-60 characters)</small></label>
                        <input type="text" name="meta_title" id="metaTitle" 
                               value="<?= htmlspecialchars($blog['meta_title'] ?? ''); ?>" 
                               placeholder="SEO optimized title"
                               oninput="updateCharCount(this, 60); updateSeoPreview();">
                        <div class="char-count" id="metaTitleCount">0/60</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Description <small class="text-muted">(150-160 characters)</small></label>
                        <textarea name="meta_description" id="metaDesc" rows="3" 
                                  placeholder="Compelling description for search results"
                                  oninput="updateCharCount(this, 160); updateSeoPreview();"><?= htmlspecialchars($blog['meta_description'] ?? ''); ?></textarea>
                        <div class="char-count" id="metaDescCount">0/160</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Focus Keywords <small class="text-muted">(comma separated)</small></label>
                        <input type="text" name="meta_keywords" 
                               value="<?= htmlspecialchars($blog['meta_keywords'] ?? ''); ?>" 
                               placeholder="kashmiri saffron, pure saffron, buy saffron online">
                    </div>
                    
                    <!-- SEO Preview -->
                    <div class="seo-preview">
                        <div class="seo-preview-title">Google Search Preview</div>
                        <div class="seo-preview-url" id="previewUrl">gilafstore.com/blog/<span id="previewSlug">your-blog-slug</span></div>
                        <div class="seo-preview-heading" id="previewTitle">Your Blog Title | Gilaf Store</div>
                        <div class="seo-preview-desc" id="previewDesc">Your meta description will appear here...</div>
                    </div>
                </div>
                
                </div><!-- End Write Tab -->
                
                <!-- ==================== AI RESEARCH TAB ==================== -->
                <div id="tab-ai-research" class="editor-tab-content">
                    
                    <?php if ($aiConfigured): ?>
                    <div class="ai-research-panel">
                        <div class="ai-research-header">
                            <h3><i class="fas fa-robot"></i> AI Blog Assistant</h3>
                            <span class="ai-status-badge configured">
                                <i class="fas fa-check-circle"></i> AI Configured
                            </span>
                        </div>
                        
                        <!-- Keyword Research -->
                        <div class="keyword-research-box">
                            <h4><i class="fas fa-key"></i> Keyword Research</h4>
                            <div class="keyword-input-group" style="position:relative;">
                                <input type="text" id="researchTopic" placeholder="Enter your topic (e.g., Kashmiri Saffron Benefits)" autocomplete="off">
                                <button type="button" class="btn-research" onclick="doKeywordResearch()">
                                    <i class="fas fa-search"></i> Research
                                </button>
                                <!-- Title dropdown is rendered outside the form via JS -->
                            </div>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle"></i> Type 3+ characters and wait. AI will suggest shocking blog titles.
                            </p>
                        </div>
                        
                        <!-- Trending Topics -->
                        <div class="trending-topics" id="trendingTopicsBox" style="display:none;">
                            <h4><i class="fas fa-fire"></i> Suggested Topics</h4>
                            <div class="trending-list" id="trendingList">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                        
                        <!-- Keywords Display -->
                        <div class="keywords-display" id="keywordsBox" style="display:none;">
                            <h4><i class="fas fa-tags"></i> Suggested Keywords</h4>
                            <div class="keyword-tags" id="keywordTags">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                        
                        <!-- AI Content Options -->
                        <div class="ai-content-options" id="aiContentOptions" style="display:none;">
                            <h4><i class="fas fa-cog"></i> Content Generation Options</h4>
                            
                            <div class="ai-options-grid">
                                <div class="ai-option-group">
                                    <label>Writing Tone</label>
                                    <select id="aiToneSelect">
                                        <option value="professional">Professional</option>
                                        <option value="friendly">Friendly & Conversational</option>
                                        <option value="educational">Educational</option>
                                        <option value="persuasive">Persuasive & Sales</option>
                                    </select>
                                </div>
                                <div class="ai-option-group">
                                    <label>Content Length</label>
                                    <select id="aiLengthSelect">
                                        <option value="800">Short (~800 words)</option>
                                        <option value="1500" selected>Medium (~1500 words)</option>
                                        <option value="2500">Long (~2500 words)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="ai-checkboxes">
                                <div class="ai-checkbox-item">
                                    <input type="checkbox" id="aiIncludeFaqsCheck" checked>
                                    <label for="aiIncludeFaqsCheck">Generate FAQs for rich snippets</label>
                                </div>
                                <div class="ai-checkbox-item">
                                    <input type="checkbox" id="aiIncludeProductsCheck" checked>
                                    <label for="aiIncludeProductsCheck">Include product recommendations</label>
                                </div>
                                <div class="ai-checkbox-item">
                                    <input type="checkbox" id="aiIncludeSeoCheck" checked>
                                    <label for="aiIncludeSeoCheck">Generate SEO meta tags</label>
                                </div>
                            </div>
                            
                            <button type="button" class="btn-generate-full" onclick="generateFullBlog()">
                                <i class="fas fa-magic"></i> Generate Complete Blog
                            </button>
                        </div>
                        
                        <!-- Workflow Progress -->
                        <div class="ai-workflow-progress" id="aiWorkflowProgress">
                            <h4 style="margin:0 0 15px 0;font-size:1rem;font-weight:600;">
                                <i class="fas fa-spinner fa-spin" style="color:#6366f1;"></i> Generating Content...
                            </h4>
                            <div class="workflow-steps">
                                <div class="workflow-step" id="step-research">
                                    <div class="step-icon"><i class="fas fa-search"></i></div>
                                    <div class="step-text">Researching topic & keywords</div>
                                </div>
                                <div class="workflow-step" id="step-outline">
                                    <div class="step-icon"><i class="fas fa-list"></i></div>
                                    <div class="step-text">Creating content outline</div>
                                </div>
                                <div class="workflow-step" id="step-content">
                                    <div class="step-icon"><i class="fas fa-edit"></i></div>
                                    <div class="step-text">Writing blog content</div>
                                </div>
                                <div class="workflow-step" id="step-faqs">
                                    <div class="step-icon"><i class="fas fa-question-circle"></i></div>
                                    <div class="step-text">Generating FAQs</div>
                                </div>
                                <div class="workflow-step" id="step-seo">
                                    <div class="step-icon"><i class="fas fa-chart-line"></i></div>
                                    <div class="step-text">Optimizing for SEO</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no-ai-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>AI Not Configured</h4>
                        <p>To use AI features, please configure your AI API key in the Chatbot Settings.</p>
                        <a href="chatbot_settings.php"><i class="fas fa-cog"></i> Configure AI Settings</a>
                    </div>
                    <?php endif; ?>
                    
                </div><!-- End AI Research Tab -->
                
                <!-- ==================== SEO TAB ==================== -->
                <div id="tab-seo" class="editor-tab-content">
                    
                    <!-- SEO Settings Card -->
                    <div class="editor-card">
                        <div class="editor-card-title"><i class="fas fa-search"></i> Search Engine Optimization</div>
                        
                        <div class="form-group">
                            <label>Meta Title <small class="text-muted">(50-60 characters recommended)</small></label>
                            <input type="text" id="seoMetaTitle" 
                                   placeholder="SEO optimized title for search engines"
                                   oninput="updateCharCount(this, 60); syncSeoFields();">
                            <div class="char-count">0/60</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Meta Description <small class="text-muted">(150-160 characters recommended)</small></label>
                            <textarea id="seoMetaDesc" rows="3" 
                                      placeholder="Compelling description that appears in search results"
                                      oninput="updateCharCount(this, 160); syncSeoFields();"></textarea>
                            <div class="char-count">0/160</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Focus Keywords <small class="text-muted">(comma separated)</small></label>
                            <input type="text" id="seoKeywords" 
                                   placeholder="primary keyword, secondary keyword, related terms">
                        </div>
                    </div>
                    
                    <!-- SEO Preview Card -->
                    <div class="editor-card">
                        <div class="editor-card-title"><i class="fab fa-google"></i> Google Search Preview</div>
                        <div class="seo-preview" style="margin-top:0;">
                            <div class="seo-preview-url">gilafstore.com/blog/<span id="seoPreviewSlug">your-blog-slug</span></div>
                            <div class="seo-preview-heading" id="seoPreviewTitle">Your Blog Title | Gilaf Store</div>
                            <div class="seo-preview-desc" id="seoPreviewDesc">Your meta description will appear here. Make it compelling to improve click-through rates from search results.</div>
                        </div>
                    </div>
                    
                    <!-- SEO Tips Card -->
                    <div class="editor-card">
                        <div class="editor-card-title"><i class="fas fa-lightbulb"></i> SEO Tips</div>
                        <ul class="mb-0" style="padding-left:20px;color:#666;font-size:0.9rem;line-height:1.8;">
                            <li>Include your primary keyword in the title and first paragraph</li>
                            <li>Use headings (H2, H3) to structure your content</li>
                            <li>Add internal links to related products and blogs</li>
                            <li>Include FAQs for rich snippet eligibility</li>
                            <li>Optimize images with descriptive alt text</li>
                            <li>Aim for 1500+ words for comprehensive coverage</li>
                        </ul>
                    </div>
                    
                </div><!-- End SEO Tab -->
                
            </div>
            
            <!-- Sidebar -->
            <div class="editor-sidebar">
                <!-- SEO Score -->
                <div class="seo-score-card">
                    <div class="seo-score-circle" id="seoScoreValue">0%</div>
                    <div class="seo-score-label">SEO Score</div>
                    <div class="seo-checklist" id="seoChecklist">
                        <div class="seo-checklist-item"><i class="fas fa-circle missing"></i> Add meta title</div>
                        <div class="seo-checklist-item"><i class="fas fa-circle missing"></i> Add meta description</div>
                        <div class="seo-checklist-item"><i class="fas fa-circle missing"></i> Add featured image</div>
                        <div class="seo-checklist-item"><i class="fas fa-circle missing"></i> Link products</div>
                        <div class="seo-checklist-item"><i class="fas fa-circle missing"></i> Add content (500+ words)</div>
                    </div>
                    <?php if (!empty($blogId)): ?>
                    <a href="<?= base_url('admin/seo_dashboard.php') ?>" target="_blank" style="display:block;text-align:center;margin-top:12px;padding:8px 14px;background:rgba(255,255,255,0.15);border-radius:8px;color:white;text-decoration:none;font-size:0.78rem;font-weight:600;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                        <i class="fas fa-brain" style="margin-right:4px;"></i> Deep AI Analysis
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($blogId)): ?>
                <!-- Semantic Link Suggestions -->
                <div class="editor-card" id="semanticLinksCard" style="display:none;">
                    <div class="editor-card-title"><i class="fas fa-atom" style="color:#8b5cf6;"></i> Semantic Links <small style="color:#94a3b8;">(AI)</small></div>
                    <div id="semanticLinksList" style="max-height:260px;overflow-y:auto;font-size:0.82rem;"></div>
                    <button type="button" onclick="loadSemanticLinks()" style="width:100%;margin-top:8px;padding:6px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:0.78rem;font-weight:600;color:#64748b;cursor:pointer;" onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor='#e2e8f0'">
                        <i class="fas fa-brain"></i> Find Semantic Links
                    </button>
                </div>
                <script>
                document.getElementById('semanticLinksCard').style.display = 'block';
                async function loadSemanticLinks() {
                    const list = document.getElementById('semanticLinksList');
                    list.innerHTML = '<div style="text-align:center;padding:12px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Computing embeddings...</div>';
                    try {
                        const content = document.getElementById('blogContent')?.value || '';
                        const resp = await fetch('<?= base_url("admin/seo_api.php") ?>', {
                            method: 'POST',
                            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                            body: JSON.stringify({action:'v3_semantic_links', content: content, blog_id: <?= (int)$blogId ?>, limit: 8})
                        });
                        const data = await resp.json();
                        if (!data.success || !data.data?.length) {
                            list.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:8px;">No suggestions found</p>';
                            return;
                        }
                        let html = '';
                        data.data.forEach(s => {
                            const pct = Math.round(s.relevance * 100);
                            const typeIcon = s.type === 'blog' ? 'newspaper' : 'box';
                            const typeColor = s.type === 'blog' ? '#3b82f6' : '#22c55e';
                            html += `<div style="padding:6px 0;border-bottom:1px solid #f1f5f9;">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-${typeIcon}" style="color:${typeColor};font-size:0.7rem;flex-shrink:0;"></i>
                                    <strong style="font-size:0.8rem;color:#1e293b;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${s.title}</strong>
                                    <span style="background:${pct>=50?'rgba(34,197,94,0.1)':'rgba(234,179,8,0.1)'};color:${pct>=50?'#16a34a':'#ca8a04'};padding:1px 6px;border-radius:8px;font-size:0.7rem;font-weight:700;flex-shrink:0;">${pct}%</span>
                                </div>
                                ${s.suggested_anchor ? `<div style="font-size:0.72rem;color:#94a3b8;margin-top:2px;">Anchor: "${s.suggested_anchor}"</div>` : ''}
                            </div>`;
                        });
                        list.innerHTML = html;
                    } catch(e) {
                        list.innerHTML = '<p style="color:#ef4444;text-align:center;padding:8px;">Error loading suggestions</p>';
                    }
                }
                </script>
                <?php endif; ?>

                <?php if (!empty($blogId)): ?>
                <!-- Pre-Publish Connectivity Gate -->
                <div class="editor-card" id="connectivityGate">
                    <div class="editor-card-title"><i class="fas fa-shield-alt" style="color:#8b5cf6;"></i> Connectivity Gate</div>
                    <div id="connectivityResult" style="font-size:0.82rem;"></div>
                    <button type="button" onclick="runConnectivityCheck()" style="width:100%;margin-top:8px;padding:6px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:0.78rem;font-weight:600;color:#64748b;cursor:pointer;" onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor='#e2e8f0'">
                        <i class="fas fa-shield-alt"></i> Check Before Publish
                    </button>
                </div>
                <script>
                async function runConnectivityCheck() {
                    const c = document.getElementById('connectivityResult');
                    c.innerHTML = '<div style="text-align:center;padding:8px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Checking...</div>';
                    try {
                        const resp = await fetch('<?= base_url("admin/seo_api.php") ?>', {
                            method: 'POST',
                            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                            body: JSON.stringify({
                                action: 'pre_publish_check',
                                blog_id: <?= (int)$blogId ?>,
                                js_product_count: (typeof linkedProductIds !== 'undefined' ? linkedProductIds.length : -1),
                                js_blog_count: (typeof linkedBlogIds !== 'undefined' ? linkedBlogIds.length : -1)
                            })
                        });
                        const data = await resp.json();
                        if (!data.success) { c.innerHTML = '<p style="color:#ef4444;">Check failed</p>'; return; }
                        const d = data.data;
                        const riskColors = {critical:'#dc2626',high:'#ef4444',medium:'#eab308',low:'#22c55e'};
                        let html = `<div style="text-align:center;margin-bottom:8px;">
                            <div style="font-size:1.2rem;font-weight:800;color:${d.pass?'#22c55e':'#ef4444'};">${d.pass?'READY':'NOT READY'}</div>
                            <div style="font-size:0.72rem;color:#94a3b8;">${d.pass_count}/${d.total_checks} checks passed</div>
                            <span style="background:${riskColors[d.orphan_risk]||'#94a3b8'}22;color:${riskColors[d.orphan_risk]||'#94a3b8'};padding:2px 10px;border-radius:10px;font-size:0.7rem;font-weight:700;text-transform:uppercase;">Orphan Risk: ${d.orphan_risk}</span>
                        </div>`;
                        d.checks.forEach(ch => {
                            html += `<div style="display:flex;align-items:center;gap:6px;padding:3px 0;font-size:0.78rem;">
                                <i class="fas fa-${ch.pass?'check-circle':'times-circle'}" style="color:${ch.pass?'#22c55e':'#ef4444'};font-size:0.7rem;"></i>
                                <span style="color:${ch.pass?'#475569':'#ef4444'};">${ch.rule}</span>
                                <small style="color:#94a3b8;margin-left:auto;">${ch.current}/${ch.required}</small>
                            </div>`;
                        });
                        if (d.cluster_blogs?.length) {
                            html += '<div style="margin-top:6px;font-size:0.72rem;color:#94a3b8;"><strong>Cluster:</strong> ' + d.cluster_blogs.slice(0,3).map(b=>'<span style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">'+b.title.substring(0,30)+'</span>').join(' ') + '</div>';
                        }
                        if (d.cannibalized_with?.length) {
                            html += '<div style="margin-top:4px;font-size:0.72rem;color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> Cannibalized with: ' + d.cannibalized_with.map(c=>c.title.substring(0,25)).join(', ') + '</div>';
                        }
                        c.innerHTML = html;
                    } catch(e) { c.innerHTML = '<p style="color:#ef4444;font-size:0.78rem;">Error checking connectivity</p>'; }
                }
                // Auto-check on load
                setTimeout(runConnectivityCheck, 2000);
                </script>
                <?php endif; ?>
                
                <!-- Status -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-toggle-on"></i> Status</div>
                    <div class="status-toggle">
                        <div class="status-option">
                            <input type="radio" name="status" id="statusDraft" value="draft" 
                                   <?= ($blog['status'] ?? 'draft') === 'draft' ? 'checked' : ''; ?>>
                            <label for="statusDraft">Draft</label>
                        </div>
                        <div class="status-option">
                            <input type="radio" name="status" id="statusPublished" value="published"
                                   <?= ($blog['status'] ?? '') === 'published' ? 'checked' : ''; ?>>
                            <label for="statusPublished">Published</label>
                        </div>
                    </div>
                </div>
                
                <!-- Category -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-folder"></i> Category</div>
                    <div class="form-group mb-0">
                        <select name="category_id" id="categorySelect">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id']; ?>" <?= ($blog['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Featured Image -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-image"></i> Featured Image</div>
                    <?php
                    $uploadDir = __DIR__ . '/../uploads/blog/';
                    $dirExists = is_dir($uploadDir);
                    $blogSlug = $blog['slug'] ?? '';
                    
                    // If editing and featured_image is empty, try to find AI image on disk
                    if ($blogId > 0 && empty($blog['featured_image'])) {
                        if (!empty($blogSlug) && $dirExists) {
                            // Extract key words from slug for matching (e.g., "iranian-vs-kashmiri-saffron-quality" -> keywords)
                            $slugWords = explode('-', $blogSlug);
                            $aiMatches = [];
                            
                            // Priority 1: Exact slug match (both jpg and webp)
                            $aiMatches = array_merge(
                                glob($uploadDir . "ai-{$blogSlug}-*.jpg"),
                                glob($uploadDir . "ai-{$blogSlug}-*.webp")
                            );
                            
                            // Priority 2: Partial slug match (first 20 chars)
                            if (empty($aiMatches) && strlen($blogSlug) > 10) {
                                $partial = substr($blogSlug, 0, 20);
                                $aiMatches = array_merge(
                                    glob($uploadDir . "ai-{$partial}*.jpg"),
                                    glob($uploadDir . "ai-{$partial}*.webp")
                                );
                            }
                            
                            // Priority 3: Match by significant slug keywords
                            if (empty($aiMatches) && count($slugWords) >= 3) {
                                $allAiFiles = array_merge(glob($uploadDir . "ai-*.jpg"), glob($uploadDir . "ai-*.webp"));
                                $significantWords = array_filter($slugWords, function($w) { return strlen($w) > 3 && !in_array($w, ['the','and','for','with','from','that','this','how','why','what']); });
                                foreach ($allAiFiles as $f) {
                                    $fname = strtolower(basename($f));
                                    $matchCount = 0;
                                    foreach ($significantWords as $word) {
                                        if (strpos($fname, $word) !== false) $matchCount++;
                                    }
                                    // At least 2 significant words must match
                                    if ($matchCount >= 2) $aiMatches[] = $f;
                                }
                            }
                            
                            // Priority 4: Any file containing slug keywords (broader)
                            if (empty($aiMatches)) {
                                $allFiles = array_merge(glob($uploadDir . "*.jpg"), glob($uploadDir . "*.webp"));
                                foreach ($allFiles as $f) {
                                    $fname = strtolower(basename($f));
                                    if (strpos($fname, substr($blogSlug, 0, 15)) !== false) $aiMatches[] = $f;
                                }
                            }
                            
                            if (!empty($aiMatches)) {
                                usort($aiMatches, function($a, $b) { return filemtime($b) - filemtime($a); });
                                $foundImage = basename($aiMatches[0]);
                                try {
                                    $fixStmt = $db->prepare("UPDATE blogs SET featured_image = ? WHERE id = ?");
                                    $fixStmt->execute([$foundImage, $blogId]);
                                    $blog['featured_image'] = $foundImage;
                                } catch (Exception $e) {}
                            }
                        }
                    }
                    ?>
                    <div class="featured-image-upload <?= !empty($blog['featured_image']) ? 'has-image' : ''; ?>" id="imageUpload">
                        <?php if (!empty($blog['featured_image'])): ?>
                            <img src="<?= base_url('uploads/blog/' . $blog['featured_image']); ?>" id="imagePreview">
                            <button type="button" class="remove-image" onclick="removeImage()"><i class="fas fa-times"></i></button>
                        <?php else: ?>
                            <div class="upload-placeholder">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or drag to upload</p>
                                <small>Recommended: 1200x630px</small>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="featured_image" id="imageInput" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($blog['featured_image'] ?? ''); ?>">
                    <input type="hidden" name="remove_image" id="removeImageFlag" value="0">
                </div>
                
                <!-- Product Linking -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-link"></i> Link Products</div>
                    <p class="text-muted small mb-3">Add products mentioned in this blog</p>
                    
                    <div class="product-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="productSearch" placeholder="Search products..." 
                               oninput="searchProducts(this.value)" onfocus="showProductResults()">
                        <div class="product-search-results" id="productResults"></div>
                    </div>
                    
                    <div class="linked-products" id="linkedProductsList">
                        <?php if (!empty($linkedProducts)): ?>
                            <?php foreach ($linkedProducts as $lp): ?>
                            <div class="linked-product-item" data-product-id="<?= $lp['product_id']; ?>">
                                <img src="<?= asset_url('images/products/' . ltrim($lp['image'], '/')); ?>" alt="">
                                <div class="product-info">
                                    <h5><?= htmlspecialchars($lp['name']); ?></h5>
                                    <select class="product-display-type">
                                        <option value="bottom" <?= $lp['display_type'] === 'bottom' ? 'selected' : ''; ?>>Bottom</option>
                                        <option value="inline" <?= $lp['display_type'] === 'inline' ? 'selected' : ''; ?>>Inline</option>
                                        <option value="sidebar" <?= $lp['display_type'] === 'sidebar' ? 'selected' : ''; ?>>Sidebar</option>
                                    </select>
                                </div>
                                <button type="button" class="remove-product" onclick="removeProduct(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-products" id="emptyProducts">
                                <i class="fas fa-box-open"></i>
                                <p>No products linked yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Related Blog Linking -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-newspaper" style="color:#8b5cf6;"></i> Link Related Blogs</div>
                    <p class="text-muted small mb-3">Link to other blog posts for internal linking (Connectivity Gate)</p>
                    <div class="product-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="blogSearch" placeholder="Search blog posts..."
                               oninput="searchBlogsForLink(this.value)" onfocus="showBlogResults()">
                        <div class="product-search-results" id="blogSearchResults"></div>
                    </div>
                    <div class="linked-products" id="linkedBlogsList">
                        <?php if (!empty($linkedBlogs)): ?>
                            <?php foreach ($linkedBlogs as $lb): ?>
                            <div class="linked-product-item" data-blog-id="<?= $lb['id'] ?>" data-blog-slug="<?= htmlspecialchars($lb['slug']) ?>">
                                <div style="width:36px;height:36px;background:#ede9fe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-newspaper" style="color:#8b5cf6;font-size:0.9rem;"></i></div>
                                <div class="product-info">
                                    <h5 style="font-size:0.82rem;font-weight:600;color:#1e293b;"><?= htmlspecialchars($lb['title']) ?></h5>
                                    <a href="<?= base_url('blog/' . $lb['slug']) ?>" target="_blank" class="text-muted small" style="font-size:0.72rem;"><i class="fas fa-external-link-alt"></i> View</a>
                                    <select class="blog-display-type" style="display:block;margin-top:4px;font-size:0.75rem;padding:2px 6px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;color:#475569;">
                                        <option value="bottom" <?= ($lb['display_type']??'bottom')==='bottom'?'selected':'' ?>>Bottom</option>
                                        <option value="inline" <?= ($lb['display_type']??'')==='inline'?'selected':'' ?>>Inline</option>
                                        <option value="sidebar" <?= ($lb['display_type']??'')==='sidebar'?'selected':'' ?>>Sidebar</option>
                                    </select>
                                </div>
                                <button type="button" class="remove-product" onclick="removeLinkedBlog(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-products" id="emptyLinkedBlogs">
                                <i class="fas fa-newspaper" style="opacity:0.3;"></i>
                                <p>No related blogs linked yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Author -->
                <div class="editor-card">
                    <div class="editor-card-title"><i class="fas fa-user"></i> Author</div>
                    <div class="form-group mb-0">
                        <input type="text" name="author_name" 
                               value="<?= htmlspecialchars($blog['author_name'] ?? 'Gilaf Store'); ?>" 
                               placeholder="Author name">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Title Suggestions Dropdown (OUTSIDE form) -->
<div id="titleSuggestionsDropdown" style="display:none;position:fixed;z-index:99999;background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.25);border:2px solid #6366f1;max-height:450px;overflow:hidden;width:500px;display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;font-weight:700;font-size:0.9rem;">
        <span><i class="fas fa-bolt" style="color:#fbbf24"></i> Click-Worthy Title Ideas</span>
        <span id="closeTitleDD" style="cursor:pointer;font-size:1.2rem;opacity:0.8">&times;</span>
    </div>
    <div id="titleSuggestionsList" style="overflow-y:auto;max-height:340px;padding:8px 0;"></div>
    <div style="padding:10px 16px;border-top:1px solid #e5e7eb;text-align:center;">
        <span id="refreshTitleBtn" style="background:none;border:1px solid #6366f1;color:#6366f1;padding:6px 16px;border-radius:8px;font-size:0.8rem;font-weight:600;cursor:pointer;display:inline-block;">
            <i class="fas fa-sync-alt"></i> Get More Ideas
        </span>
    </div>
</div>
<script>
window._cachedTitles = [];

// Close button
document.getElementById('closeTitleDD').addEventListener('click', function(){
    document.getElementById('titleSuggestionsDropdown').style.display='none';
});

// Refresh button
document.getElementById('refreshTitleBtn').addEventListener('click', function(){
    if(window.refreshTitleSuggestions) window.refreshTitleSuggestions();
});

// MASTER CLICK HANDLER: Listen for clicks inside titleSuggestionsList
document.getElementById('titleSuggestionsList').addEventListener('click', function(e){
    var el = e.target;
    // Walk up to find element with data-pick attribute
    while(el && el !== this) {
        if(el.getAttribute && el.getAttribute('data-pick') !== null) {
            var idx = parseInt(el.getAttribute('data-pick'));
            if(!isNaN(idx) && window._cachedTitles && window._cachedTitles[idx]) {
                var title = window._cachedTitles[idx].title || '';
                // Fill Blog Title on Write tab
                var inp = document.getElementById('blogTitle');
                if(inp) inp.value = title;
                // Also fill Keyword Research input
                var researchInp = document.getElementById('researchTopic');
                if(researchInp) researchInp.value = title;
                document.getElementById('titleSuggestionsDropdown').style.display = 'none';
                // Generate slug
                try { if(typeof generateSlug==='function') generateSlug(); } catch(ex){}
                // Toast
                var toast = document.createElement('div');
                toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:12px 20px;background:#22c55e;color:white;border-radius:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
                toast.textContent = 'Title: ' + title;
                document.body.appendChild(toast);
                setTimeout(function(){ toast.remove(); }, 3000);
            }
            break;
        }
        el = el.parentElement;
    }
});
</script>

<!-- Products Data for JS -->
<script>
// VERSION: blog_edit_v4.0 (May 17, 2026 8:35pm)
console.log('%c=== BLOG EDITOR v4.0 LOADED ===', 'color:green;font-size:16px;font-weight:bold');

// Global error handler - shows errors as alert on page
window.onerror = function(msg, url, line, col, error) {
    console.error('JS ERROR:', msg, 'at line', line, 'col', col);
    // Show error banner on page for debugging
    var errDiv = document.createElement('div');
    errDiv.style.cssText = 'position:fixed;bottom:0;left:0;right:0;background:#dc2626;color:white;padding:10px;z-index:99999;font-size:12px;';
    errDiv.textContent = 'JS ERROR: ' + msg + ' (line ' + line + ')';
    document.body.appendChild(errDiv);
    setTimeout(function(){ errDiv.remove(); }, 10000);
    return false;
};

var allProducts = <?= json_encode($products, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?> || [];
var linkedProductIds = <?= json_encode(array_map('intval', array_column($linkedProducts, 'product_id')), JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
var allBlogsForSearch = <?= json_encode($allBlogsForSearch, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?> || [];
var linkedBlogIds = <?= json_encode(array_map('intval', array_column($linkedBlogs, 'id')), JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
console.log('Products loaded:', allProducts.length, 'Linked:', linkedProductIds.length);
console.log('Blogs for linking:', allBlogsForSearch.length, 'Linked blogs:', linkedBlogIds.length);

// Generate slug from title
function generateSlug() {
    const title = document.getElementById('blogTitle').value;
    const slug = title.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
    document.getElementById('blogSlug').value = slug;
    document.getElementById('previewSlug').textContent = slug || 'your-blog-slug';
}

// Character count
function updateCharCount(input, max) {
    const count = input.value.length;
    const countEl = input.parentElement.querySelector('.char-count');
    if (countEl) {
        countEl.textContent = count + '/' + max;
        countEl.className = 'char-count';
        if (count > max) countEl.classList.add('danger');
        else if (count > max * 0.8) countEl.classList.add('warning');
    }
}

// SEO Preview
function updateSeoPreview() {
    const title = document.getElementById('metaTitle').value || document.getElementById('blogTitle').value;
    const desc = document.getElementById('metaDesc').value || document.getElementById('blogExcerpt').value;
    const slug = document.getElementById('blogSlug').value;
    
    document.getElementById('previewTitle').textContent = (title || 'Your Blog Title') + ' | Gilaf Store';
    document.getElementById('previewDesc').textContent = desc || 'Your meta description will appear here...';
    document.getElementById('previewSlug').textContent = slug || 'your-blog-slug';
    
    updateSeoScore();
}

// SEO Score — Comprehensive RankMath-style analysis
function updateSeoScore() {
    const checks = [];
    let totalPoints = 0;
    let earnedPoints = 0;

    // Get values
    const title = document.getElementById('blogTitle').value.trim();
    const metaTitle = document.getElementById('metaTitle').value.trim();
    const metaDesc = document.getElementById('metaDesc').value.trim();
    const slug = document.getElementById('blogSlug').value.trim();
    const content = document.getElementById('blogContent').value.trim();
    const keywordsRaw = (document.querySelector('input[name="meta_keywords"]')?.value || '').trim();
    const focusKw = keywordsRaw.split(',').map(k => k.trim().toLowerCase()).filter(k => k)[0] || '';
    const wordCount = content.split(/\s+/).filter(w => w).length;
    const contentLower = content.toLowerCase();
    const titleLower = (metaTitle || title).toLowerCase();
    const descLower = metaDesc.toLowerCase();
    const slugLower = slug.toLowerCase();

    // Extract first paragraph (first 200 chars of content)
    const firstPara = contentLower.substring(0, 300);

    // Extract headings from content
    const headingMatches = content.match(/^#{1,4}\s+.+|<h[1-4][^>]*>.+?<\/h[1-4]>/gim) || [];
    const h2Count = (content.match(/^##\s+|<h2/gim) || []).length;

    // === BASIC SEO (5 checks, 5 pts each = 25) ===
    totalPoints += 5;
    if (metaTitle && metaTitle.length >= 30 && metaTitle.length <= 60) {
        earnedPoints += 5; checks.push({text:'Meta title length ('+metaTitle.length+'/60)', ok:true, cat:'basic'});
    } else if (metaTitle) {
        earnedPoints += 2; checks.push({text:'Meta title: aim 30-60 chars ('+metaTitle.length+')', ok:false, cat:'basic'});
    } else { checks.push({text:'Add meta title', ok:false, cat:'basic'}); }

    totalPoints += 5;
    if (metaDesc && metaDesc.length >= 120 && metaDesc.length <= 160) {
        earnedPoints += 5; checks.push({text:'Meta description length ('+metaDesc.length+'/160)', ok:true, cat:'basic'});
    } else if (metaDesc) {
        earnedPoints += 2; checks.push({text:'Meta desc: aim 120-160 chars ('+metaDesc.length+')', ok:false, cat:'basic'});
    } else { checks.push({text:'Add meta description', ok:false, cat:'basic'}); }

    totalPoints += 5;
    if (slug && slug.length <= 75) {
        earnedPoints += 5; checks.push({text:'URL slug length OK ('+slug.length+' chars)', ok:true, cat:'basic'});
    } else if (slug) {
        earnedPoints += 2; checks.push({text:'URL slug too long ('+slug.length+'/75)', ok:false, cat:'basic'});
    } else { checks.push({text:'Add URL slug', ok:false, cat:'basic'}); }

    const hasImage = !!document.querySelector('#imageUpload.has-image');
    totalPoints += 5;
    if (hasImage) { earnedPoints += 5; checks.push({text:'Featured image added', ok:true, cat:'basic'}); }
    else { checks.push({text:'Add featured image', ok:false, cat:'basic'}); }

    totalPoints += 5;
    if (linkedProductIds.length > 0) { earnedPoints += 5; checks.push({text:linkedProductIds.length+' product(s) linked', ok:true, cat:'basic'}); }
    else { checks.push({text:'Link related products', ok:false, cat:'basic'}); }

    // === KEYWORD ANALYSIS (6 checks, 5 pts each = 30) ===
    if (focusKw) {
        totalPoints += 5;
        if (titleLower.includes(focusKw)) { earnedPoints += 5; checks.push({text:'Keyword in title ✓', ok:true, cat:'keyword'}); }
        else { checks.push({text:'Add "'+focusKw+'" to title', ok:false, cat:'keyword'}); }

        totalPoints += 5;
        const allKws = keywordsRaw.split(',').map(k => k.trim().toLowerCase()).filter(k => k);
        const kwInDesc = allKws.some(kw => descLower.includes(kw));
        const matchedDescKw = allKws.find(kw => descLower.includes(kw)) || '';
        if (kwInDesc) { earnedPoints += 5; checks.push({text:'Keyword in meta description ✓ ('+matchedDescKw+')', ok:true, cat:'keyword'}); }
        else { checks.push({text:'Add a focus keyword to meta description', ok:false, cat:'keyword'}); }

        totalPoints += 5;
        const kwSlug = focusKw.replace(/\s+/g, '-');
        if (slugLower.includes(kwSlug) || slugLower.includes(focusKw.replace(/\s+/g, ''))) { earnedPoints += 5; checks.push({text:'Keyword in URL ✓', ok:true, cat:'keyword'}); }
        else { checks.push({text:'Add keyword to URL slug', ok:false, cat:'keyword'}); }

        totalPoints += 5;
        if (firstPara.includes(focusKw)) { earnedPoints += 5; checks.push({text:'Keyword in first paragraph ✓', ok:true, cat:'keyword'}); }
        else { checks.push({text:'Use keyword in first 200 chars', ok:false, cat:'keyword'}); }

        totalPoints += 5;
        const kwInHeadings = headingMatches.some(h => h.toLowerCase().includes(focusKw));
        if (kwInHeadings) { earnedPoints += 5; checks.push({text:'Keyword in headings ✓', ok:true, cat:'keyword'}); }
        else { checks.push({text:'Use keyword in a heading', ok:false, cat:'keyword'}); }

        totalPoints += 5;
        if (wordCount > 0) {
            const kwRegex = new RegExp(focusKw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'gi');
            const kwMatches = contentLower.match(kwRegex) || [];
            const density = ((kwMatches.length / wordCount) * 100).toFixed(1);
            if (density >= 0.5 && density <= 2.5) { earnedPoints += 5; checks.push({text:'Keyword density '+density+'% (ideal)', ok:true, cat:'keyword'}); }
            else if (density > 0) { earnedPoints += 2; checks.push({text:'Keyword density '+density+'% (aim 0.5-2.5%)', ok:false, cat:'keyword'}); }
            else { checks.push({text:'Keyword not found in content', ok:false, cat:'keyword'}); }
        } else { checks.push({text:'Add content to check density', ok:false, cat:'keyword'}); }
    } else {
        checks.push({text:'Add focus keyword for analysis', ok:false, cat:'keyword'});
    }

    // === CONTENT & READABILITY (5 checks, 5 pts each = 25) ===
    totalPoints += 5;
    if (wordCount >= 1500) { earnedPoints += 5; checks.push({text:wordCount+' words (comprehensive ✓)', ok:true, cat:'content'}); }
    else if (wordCount >= 800) { earnedPoints += 3; checks.push({text:wordCount+' words (aim 1500+)', ok:false, cat:'content'}); }
    else { checks.push({text:wordCount+' words (min 800 recommended)', ok:false, cat:'content'}); }

    totalPoints += 5;
    if (h2Count >= 2) { earnedPoints += 5; checks.push({text:h2Count+' subheadings found ✓', ok:true, cat:'content'}); }
    else { checks.push({text:'Add 2+ H2 subheadings', ok:false, cat:'content'}); }

    totalPoints += 5;
    const sentences = content.split(/[.!?]+/).filter(s => s.trim().length > 5);
    const avgSentLen = sentences.length > 0 ? Math.round(content.split(/\s+/).length / sentences.length) : 0;
    if (avgSentLen > 0 && avgSentLen <= 20) { earnedPoints += 5; checks.push({text:'Avg sentence length '+avgSentLen+' words ✓', ok:true, cat:'content'}); }
    else if (avgSentLen > 20) { earnedPoints += 2; checks.push({text:'Sentences too long (avg '+avgSentLen+' words)', ok:false, cat:'content'}); }
    else { checks.push({text:'Add content for readability check', ok:false, cat:'content'}); }

    totalPoints += 5;
    const faqItems = document.querySelectorAll('.faq-item');
    if (faqItems.length >= 3) { earnedPoints += 5; checks.push({text:faqItems.length+' FAQs (rich snippet eligible ✓)', ok:true, cat:'content'}); }
    else if (faqItems.length > 0) { earnedPoints += 2; checks.push({text:faqItems.length+' FAQ(s) — add 3+ for rich snippets', ok:false, cat:'content'}); }
    else { checks.push({text:'Add FAQs for rich snippets', ok:false, cat:'content'}); }

    // Internal links check — counts HTML links in content + linked products
    totalPoints += 5;
    const htmlInternalLinks = (content.match(/href=["'][^"']*gilafstore|href=["']\//gi) || []).length;
    const linkedProductCount = (typeof linkedProductIds !== 'undefined' ? linkedProductIds.length : 0);
    const totalInternalLinks = htmlInternalLinks + linkedProductCount;
    if (totalInternalLinks >= 2) { earnedPoints += 5; checks.push({text:totalInternalLinks+' internal links ✓ ('+(linkedProductCount ? linkedProductCount+' product(s)' : '')+(htmlInternalLinks && linkedProductCount ? ', ' : '')+(htmlInternalLinks ? htmlInternalLinks+' in content' : '')+')', ok:true, cat:'content'}); }
    else { checks.push({text:'Add 2+ internal links ('+totalInternalLinks+' found — link products or add <a> links)', ok:false, cat:'content'}); }

    // === CALCULATE FINAL SCORE ===
    const score = totalPoints > 0 ? Math.round((earnedPoints / totalPoints) * 100) : 0;
    const scoreEl = document.getElementById('seoScoreValue');
    scoreEl.textContent = score + '%';
    scoreEl.style.color = score >= 80 ? '#4caf50' : score >= 50 ? '#ffc107' : '#ff5722';

    const checklistHtml = checks.map(c => `
        <div class="seo-checklist-item">
            <i class="fas fa-${c.ok ? 'check-circle check' : 'exclamation-circle missing'}"></i> ${c.text}
        </div>
    `).join('');
    document.getElementById('seoChecklist').innerHTML = checklistHtml;
}

// Product Search
function searchProducts(query) {
    const results = document.getElementById('productResults');
    if (!query || query.length < 2) {
        results.classList.remove('show');
        return;
    }
    
    // Debug: log products count
    console.log('Total products:', allProducts.length, 'Searching for:', query);
    
    const filtered = allProducts.filter(p => {
        const nameMatch = p.name && p.name.toLowerCase().includes(query.toLowerCase());
        const notLinked = !linkedProductIds.includes(parseInt(p.id));
        return nameMatch && notLinked;
    }).slice(0, 10);
    
    console.log('Filtered products:', filtered.length);
    
    if (filtered.length === 0) {
        results.innerHTML = '<div class="p-3 text-center text-muted">No products found for "' + query + '"</div>';
    } else {
        results.innerHTML = filtered.map(p => `
            <div class="product-search-item" onclick="addProduct(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.image || ''}', '${p.slug || ''}')">
                <img src="${p.image ? '<?= asset_url('images/products/'); ?>' + p.image : '<?= asset_url('images/placeholder.png'); ?>'}" alt="">
                <span class="product-name">${p.name}</span>
                <button type="button" class="add-btn"><i class="fas fa-plus"></i> Add</button>
            </div>
        `).join('');
    }
    results.classList.add('show');
}

function showProductResults() {
    const query = document.getElementById('productSearch').value;
    if (query.length >= 2) searchProducts(query);
}

function addProduct(id, name, image, slug) {
    if (linkedProductIds.includes(parseInt(id))) return;
    
    linkedProductIds.push(parseInt(id));
    
    // Remove empty state
    const empty = document.getElementById('emptyProducts');
    if (empty) empty.remove();
    
    const productUrl = slug ? '<?= base_url('product/'); ?>' + slug : '#';
    
    const html = `
        <div class="linked-product-item" data-product-id="${id}" data-product-slug="${slug || ''}">
            <img src="${image ? '<?= asset_url('images/products/'); ?>' + image : '<?= asset_url('images/placeholder.png'); ?>'}" alt="">
            <div class="product-info">
                <h5>${name}</h5>
                <a href="${productUrl}" target="_blank" class="text-muted small"><i class="fas fa-external-link-alt"></i> View Product</a>
                <select class="product-display-type">
                    <option value="bottom">Bottom</option>
                    <option value="inline">Inline</option>
                    <option value="sidebar">Sidebar</option>
                </select>
            </div>
            <button type="button" class="remove-product" onclick="removeProduct(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('linkedProductsList').insertAdjacentHTML('beforeend', html);
    
    // Clear search
    document.getElementById('productSearch').value = '';
    document.getElementById('productResults').classList.remove('show');
    
    updateSeoScore();
}

function removeProduct(btn) {
    const item = btn.closest('.linked-product-item');
    const id = parseInt(item.dataset.productId);
    linkedProductIds = linkedProductIds.filter(pid => parseInt(pid) !== id);
    item.remove();
    
    // Show empty state if no products
    if (linkedProductIds.length === 0) {
        document.getElementById('linkedProductsList').innerHTML = `
            <div class="empty-products" id="emptyProducts">
                <i class="fas fa-box-open"></i>
                <p>No products linked yet</p>
            </div>
        `;
    }
    
    updateSeoScore();
}

// ============================================================
// Blog Linking — Related Blog Search/Add/Remove
// ============================================================
function searchBlogsForLink(query) {
    const results = document.getElementById('blogSearchResults');
    if (!query || query.length < 2) { results.classList.remove('show'); return; }
    const filtered = allBlogsForSearch.filter(b => {
        return b.title && b.title.toLowerCase().includes(query.toLowerCase()) && !linkedBlogIds.includes(parseInt(b.id));
    }).slice(0, 8);
    if (filtered.length === 0) {
        results.innerHTML = '<div class="p-3 text-center text-muted" style="font-size:0.82rem;">No blogs found</div>';
    } else {
        results.innerHTML = filtered.map(b => `
            <div class="product-search-item" onclick="addLinkedBlog(${b.id}, '${b.title.replace(/'/g,"\\'")}', '${b.slug}')">
                <div style="width:32px;height:32px;background:#ede9fe;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-newspaper" style="color:#8b5cf6;font-size:0.8rem;"></i></div>
                <span class="product-name" style="font-size:0.82rem;">${b.title}</span>
                <button type="button" class="add-btn"><i class="fas fa-plus"></i> Add</button>
            </div>
        `).join('');
    }
    results.classList.add('show');
}

function showBlogResults() {
    const query = document.getElementById('blogSearch').value;
    if (query.length >= 2) searchBlogsForLink(query);
}

function addLinkedBlog(id, title, slug) {
    if (linkedBlogIds.includes(parseInt(id))) return;
    linkedBlogIds.push(parseInt(id));
    const empty = document.getElementById('emptyLinkedBlogs');
    if (empty) empty.remove();
    const blogUrl = '<?= base_url('blog/') ?>' + slug;
    const html = `
        <div class="linked-product-item" data-blog-id="${id}" data-blog-slug="${slug}">
            <div style="width:36px;height:36px;background:#ede9fe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-newspaper" style="color:#8b5cf6;font-size:0.9rem;"></i></div>
            <div class="product-info">
                <h5 style="font-size:0.82rem;font-weight:600;color:#1e293b;">${title}</h5>
                <a href="${blogUrl}" target="_blank" class="text-muted small" style="font-size:0.72rem;"><i class="fas fa-external-link-alt"></i> View</a>
                <select class="blog-display-type" style="display:block;margin-top:4px;font-size:0.75rem;padding:2px 6px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;color:#475569;">
                    <option value="bottom">Bottom</option>
                    <option value="inline">Inline</option>
                    <option value="sidebar">Sidebar</option>
                </select>
            </div>
            <button type="button" class="remove-product" onclick="removeLinkedBlog(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    document.getElementById('linkedBlogsList').insertAdjacentHTML('beforeend', html);
    document.getElementById('blogSearch').value = '';
    document.getElementById('blogSearchResults').classList.remove('show');
    updateSeoScore();
}

function removeLinkedBlog(btn) {
    const item = btn.closest('.linked-product-item');
    const id = parseInt(item.dataset.blogId);
    linkedBlogIds = linkedBlogIds.filter(bid => parseInt(bid) !== id);
    item.remove();
    if (document.querySelectorAll('#linkedBlogsList .linked-product-item').length === 0) {
        document.getElementById('linkedBlogsList').innerHTML = `
            <div class="empty-products" id="emptyLinkedBlogs">
                <i class="fas fa-newspaper" style="opacity:0.3;"></i>
                <p>No related blogs linked yet</p>
            </div>`;
    }
    updateSeoScore();
}

// Close search results on outside click
document.addEventListener('click', function(e) {
    try {
        if (!e.target.closest('.product-search-box')) {
            var pr = document.getElementById('productResults');
            if (pr) pr.classList.remove('show');
            var br = document.getElementById('blogSearchResults');
            if (br) br.classList.remove('show');
        }
    } catch(err) { console.error('Click handler error:', err); }
});

// Featured Image
window._manualImageUploaded = false; // Track if admin manually uploaded an image
function previewImage(input) {
    if (input.files && input.files[0]) {
        window._manualImageUploaded = true; // Admin uploaded manually - don't let AI override
        var reader = new FileReader();
        reader.onload = function(e) {
            var upload = document.getElementById('imageUpload');
            
            // Remove any existing preview, remove button, or placeholder (but NOT the file input)
            var oldPreview = document.getElementById('imagePreview');
            if (oldPreview) oldPreview.remove();
            var oldBtn = upload.querySelector('.remove-image');
            if (oldBtn) oldBtn.remove();
            var oldPlaceholder = upload.querySelector('.upload-placeholder');
            if (oldPlaceholder) oldPlaceholder.remove();
            
            // Create preview image
            var img = document.createElement('img');
            img.id = 'imagePreview';
            img.src = e.target.result;
            
            // Create remove button
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'remove-image';
            btn.onclick = removeImage;
            btn.innerHTML = '<i class="fas fa-times"></i>';
            
            // Insert before the file input (keep file input last)
            upload.insertBefore(img, input);
            upload.insertBefore(btn, input);
            upload.classList.add('has-image');
            
            // CRITICAL: Clear existing_image so manual upload takes precedence over AI image
            var existingInput = document.querySelector('input[name="existing_image"]');
            if (existingInput) existingInput.value = '';
            
            // Clear remove flag since we're uploading, not removing
            var removeFlag = document.getElementById('removeImageFlag');
            if (removeFlag) removeFlag.value = '0';
            
            updateSeoScore();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    window._manualImageUploaded = false; // Reset so AI can auto-apply again
    var upload = document.getElementById('imageUpload');
    
    // Remove preview and remove button
    var preview = document.getElementById('imagePreview');
    var removeBtn = upload.querySelector('.remove-image');
    if (preview) preview.remove();
    if (removeBtn) removeBtn.remove();
    
    // Add placeholder back
    var placeholder = document.createElement('div');
    placeholder.className = 'upload-placeholder';
    placeholder.innerHTML = '<i class="fas fa-cloud-upload-alt"></i><p>Click or drag to upload</p><small>Recommended: 1200x630px</small>';
    
    // Get the file input
    var fileInput = document.getElementById('imageInput');
    if (fileInput) {
        upload.insertBefore(placeholder, fileInput);
        fileInput.value = ''; // Clear any selected file
    } else {
        upload.appendChild(placeholder);
        var newInput = document.createElement('input');
        newInput.type = 'file';
        newInput.name = 'featured_image';
        newInput.id = 'imageInput';
        newInput.accept = 'image/*';
        newInput.onchange = function() { previewImage(this); };
        upload.appendChild(newInput);
    }
    
    upload.classList.remove('has-image');
    document.getElementById('removeImageFlag').value = '1';
    // Clear the existing image reference so it gets removed on save
    var existingInput = document.querySelector('input[name="existing_image"]');
    if (existingInput) existingInput.value = '';
    updateSeoScore();
}

// Content Image Upload — uploads image via AJAX, inserts <img> tag at cursor
function uploadContentImage(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (!file.type.startsWith('image/')) { alert('Please select an image file.'); input.value=''; return; }
    if (file.size > 5 * 1024 * 1024) { alert('Image must be under 5MB.'); input.value=''; return; }

    var formData = new FormData();
    formData.append('action', 'upload_content_image');
    formData.append('content_image', file);

    // Show uploading toast
    var toast = document.createElement('div');
    toast.id = 'uploadToast';
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:12px 20px;background:#3b82f6;color:white;border-radius:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;align-items:center;gap:8px;';
    toast.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading image...';
    document.body.appendChild(toast);

    fetch('blog_actions.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            toast.remove();
            if (data.success) {
                var imgTag = '<img src="' + data.url + '" alt="' + (data.filename || 'content image') + '" style="max-width:100%;height:auto;border-radius:8px;margin:16px 0;" loading="lazy">';
                var ta = document.getElementById('blogContent');
                var start = ta.selectionStart || ta.value.length;
                var before = ta.value.substring(0, start);
                var after = ta.value.substring(start);
                ta.value = before + '\n' + imgTag + '\n' + after;
                ta.focus();
                ta.selectionStart = ta.selectionEnd = start + imgTag.length + 2;
                // Success toast
                var ok = document.createElement('div');
                ok.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:12px 20px;background:#22c55e;color:white;border-radius:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
                ok.innerHTML = '<i class="fas fa-check-circle"></i> Image inserted into content';
                document.body.appendChild(ok);
                setTimeout(function(){ ok.remove(); }, 3000);
                updateSeoScore();
            } else {
                alert('Upload failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(err) {
            toast.remove();
            alert('Upload error: ' + err.message);
        });
    input.value = ''; // Reset so same file can be re-uploaded
}

// FAQs
function addFaq() {
    const list = document.getElementById('faqList');
    const index = list.querySelectorAll('.faq-item').length;
    const html = `
        <div class="faq-item" data-index="${index}">
            <input type="text" class="faq-question" placeholder="Question">
            <textarea class="faq-answer" placeholder="Answer"></textarea>
            <div class="faq-actions">
                <button type="button" class="remove-faq" onclick="removeFaq(this)">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    list.insertAdjacentHTML('beforeend', html);
}

function removeFaq(btn) {
    btn.closest('.faq-item').remove();
}

// Form Submit - Collect data and pre-save featured image
try { document.getElementById('blogForm').addEventListener('submit', function(e) {
    // Ensure existing_image is set if AI thumbnail was generated and admin didn't manually upload
    var existingImageField = document.querySelector('input[name="existing_image"]');
    var manualFileCheck = document.getElementById('imageInput');
    var hasManualFile = manualFileCheck && manualFileCheck.files && manualFileCheck.files.length > 0;
    
    // If no existing_image field, create one
    if (!existingImageField) {
        existingImageField = document.createElement('input');
        existingImageField.type = 'hidden';
        existingImageField.name = 'existing_image';
        document.getElementById('blogForm').appendChild(existingImageField);
    }
    
    // Set AI thumbnail as existing_image if: no manual upload AND AI generated one AND field is empty
    if (!hasManualFile && !existingImageField.value && window._generatedThumbnail) {
        existingImageField.value = window._generatedThumbnail;
        console.log('Form submit: Set existing_image to AI thumbnail:', window._generatedThumbnail);
    }
    console.log('Form submit: existing_image =', existingImageField.value, '| manual upload:', hasManualFile);
    
    // PRE-SAVE: Save featured image to DB via AJAX before form submits
    // But ONLY if admin didn't manually upload a new file (file upload is handled by blog_actions.php)
    const blogId = document.querySelector('input[name="id"]')?.value;
    const imgValue = existingImageField?.value;
    const removeFlag = document.getElementById('removeImageFlag')?.value;
    const manualFile = document.getElementById('imageInput');
    const hasManualUpload = manualFile && manualFile.files && manualFile.files.length > 0;
    if (blogId && blogId > 0 && imgValue && removeFlag !== '1' && !hasManualUpload) {
        // Fire-and-forget AJAX to save featured_image directly
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?= base_url("admin/blog_ai_api.php") ?>', false); // synchronous
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        try {
            xhr.send(JSON.stringify({
                action: 'save_featured_image',
                blog_id: parseInt(blogId),
                filename: imgValue
            }));
        } catch(err) {}
    }
    
    // Collect linked products (scope to #linkedProductsList to avoid blog items)
    const products = [];
    document.querySelectorAll('#linkedProductsList .linked-product-item').forEach((item, index) => {
        const displayTypeEl = item.querySelector('.product-display-type');
        products.push({
            product_id: parseInt(item.dataset.productId),
            display_type: displayTypeEl ? displayTypeEl.value : 'bottom',
            display_order: index
        });
    });
    document.getElementById('linkedProductsInput').value = JSON.stringify(products);
    
    // Collect linked related blogs
    const relatedBlogs = [];
    document.querySelectorAll('#linkedBlogsList .linked-product-item').forEach(item => {
        const displayTypeEl = item.querySelector('.blog-display-type');
        relatedBlogs.push({
            blog_id: parseInt(item.dataset.blogId),
            display_type: displayTypeEl ? displayTypeEl.value : 'bottom'
        });
    });
    document.getElementById('linkedBlogsInput').value = JSON.stringify(relatedBlogs);
    
    // Collect FAQs
    const faqs = [];
    document.querySelectorAll('.faq-item').forEach((item, index) => {
        const q = item.querySelector('.faq-question').value.trim();
        const a = item.querySelector('.faq-answer').value.trim();
        if (q && a) {
            faqs.push({ question: q, answer: a, sort_order: index });
        }
    });
    document.getElementById('faqsInput').value = JSON.stringify(faqs);
}); } catch(e) { console.error('Form submit handler error:', e); }

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // If blog already has a featured image from DB, mark as manually set so AI won't override
    const existingImg = document.querySelector('input[name="existing_image"]');
    if (existingImg && existingImg.value) {
        window._manualImageUploaded = true;
    }
    
    
    // Init char counts
    const excerpt = document.getElementById('blogExcerpt');
    if (excerpt.value) updateCharCount(excerpt, 200);
    
    const metaTitle = document.getElementById('metaTitle');
    if (metaTitle.value) updateCharCount(metaTitle, 60);
    
    const metaDesc = document.getElementById('metaDesc');
    if (metaDesc.value) updateCharCount(metaDesc, 160);
    
    // Real-time SEO score updates on keyword/content/title/slug changes
    const kwField = document.querySelector('input[name="meta_keywords"]');
    if (kwField) kwField.addEventListener('input', function() { updateSeoScore(); });
    const contentField = document.getElementById('blogContent');
    if (contentField) contentField.addEventListener('input', function() { updateSeoScore(); });
    const titleField = document.getElementById('blogTitle');
    if (titleField) titleField.addEventListener('input', function() { updateSeoScore(); });
    const slugField = document.getElementById('blogSlug');
    if (slugField) slugField.addEventListener('input', function() { updateSeoScore(); });
    
    updateSeoPreview();
});

// End of core editor script block
</script>

<!-- AI Content Generator & Research Functions - ISOLATED SCRIPT BLOCK -->
<script>
console.log('=== AI BLOCK v3.0 LOADED ===');
// AI Content Generator Functions
function openAiGenerator() {
    document.getElementById('aiGeneratorPanel').style.display = 'block';
    // Pre-fill topic from title if available
    const title = document.getElementById('blogTitle').value.trim();
    if (title && !document.getElementById('aiTopic').value) {
        document.getElementById('aiTopic').value = title;
    }
}

function closeAiGenerator() {
    document.getElementById('aiGeneratorPanel').style.display = 'none';
    document.getElementById('aiProgress').style.display = 'none';
}

async function generateAiContent() {
    const topic = document.getElementById('aiTopic').value.trim();
    const keywords = document.getElementById('aiKeywords').value.trim();
    const tone = document.getElementById('aiTone').value;
    const length = document.getElementById('aiLength').value;
    const includeFaqs = document.getElementById('aiIncludeFaqs').checked;
    const category = document.getElementById('categorySelect').value;
    
    if (!topic) {
        alert('Please enter a topic or main keyword');
        return;
    }
    
    // Get linked products for context (include slug for correct links)
    const linkedProducts = [];
    document.querySelectorAll('.linked-product-item').forEach(item => {
        const productId = item.dataset.productId;
        const productData = allProducts.find(p => p.id == productId);
        linkedProducts.push({
            id: productId,
            name: item.querySelector('.product-name').textContent,
            slug: productData?.slug || '',
            url: productData?.slug ? `/product/${productData.slug}` : ''
        });
    });
    
    // Show progress
    document.getElementById('aiProgress').style.display = 'block';
    const progressFill = document.getElementById('aiProgressFill');
    const progressText = document.getElementById('aiProgressText');
    
    // Disable generate button
    const generateBtn = document.querySelector('.btn-generate-content');
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 10;
        if (progress > 90) progress = 90;
        progressFill.style.width = progress + '%';
    }, 500);
    
    const stages = [
        'Researching keywords...',
        'Creating outline...',
        'Writing introduction...',
        'Developing content...',
        'Adding product mentions...',
        'Generating FAQs...',
        'Optimizing for SEO...'
    ];
    
    let stageIndex = 0;
    const stageInterval = setInterval(() => {
        if (stageIndex < stages.length) {
            progressText.textContent = stages[stageIndex];
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
                action: 'generate',
                topic: topic,
                selectedTopic: { title: topic, description: '' },
                keywords: keywords.split(',').map(k => k.trim()).filter(k => k),
                tone: tone,
                length: length,
                includeFaqs: includeFaqs,
                includeProducts: linkedProducts.length > 0,
                products: linkedProducts,
                category: category
            })
        });
        
        const data = await response.json();
        
        clearInterval(progressInterval);
        clearInterval(stageInterval);
        
        if (data.success) {
            progressFill.style.width = '100%';
            progressText.textContent = 'Content generated successfully!';
            
            // Fill in the form fields
            setTimeout(() => {
                applyGeneratedContent(data.content);
                closeAiGenerator();
            }, 500);
        } else {
            const errDetails = (data.errors && data.errors.length) ? '\n\nDetails:\n' + data.errors.join('\n') : '';
            throw new Error((data.message || 'Generation failed') + errDetails);
        }
    } catch (error) {
        clearInterval(progressInterval);
        clearInterval(stageInterval);
        alert('Error: ' + error.message);
    } finally {
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Content';
    }
}

function applyGeneratedContent(content) {
    // Apply title
    const titleInput = document.getElementById('blogTitle');
    const title = content.title || content.metaTitle || '';
    if (!titleInput.value.trim() && title) {
        titleInput.value = title;
        generateSlug();
    }
    
    // Apply slug if provided
    if (content.slug) {
        document.getElementById('blogSlug').value = content.slug;
    }
    
    // Apply content
    if (content.content || content.contentHtml) {
        document.getElementById('blogContent').value = content.content || content.contentHtml;
    }
    
    // Apply excerpt
    if (content.excerpt) {
        document.getElementById('blogExcerpt').value = content.excerpt.substring(0, 200);
        updateCharCount(document.getElementById('blogExcerpt'), 200);
    }
    
    // Apply meta description
    let metaDesc = content.metaDescription || '';
    if (typeof metaDesc === 'object') {
        metaDesc = metaDesc.text || '';
    }
    metaDesc = String(metaDesc).replace(/^\{.*"title":/g, '').replace(/\}$/g, '').trim();
    
    if (metaDesc) {
        document.getElementById('metaDesc').value = metaDesc.substring(0, 160);
        updateCharCount(document.getElementById('metaDesc'), 160);
        if (!content.excerpt) {
            document.getElementById('blogExcerpt').value = metaDesc.substring(0, 200);
            updateCharCount(document.getElementById('blogExcerpt'), 200);
        }
    }
    
    // Apply meta title
    const metaTitle = content.metaTitle || content.title || '';
    if (metaTitle) {
        document.getElementById('metaTitle').value = metaTitle.substring(0, 60);
        updateCharCount(document.getElementById('metaTitle'), 60);
    }
    
    // Apply FAQs
    const faqs = content.faqs || [];
    if (faqs.length > 0) {
        document.getElementById('faqList').innerHTML = '';
        faqs.forEach((faq, index) => {
            const html = `
                <div class="faq-item" data-index="${index}">
                    <input type="text" class="faq-question" placeholder="Question" value="${escapeHtml(faq.question)}">
                    <textarea class="faq-answer" placeholder="Answer">${escapeHtml(faq.answer)}</textarea>
                    <div class="faq-actions">
                        <button type="button" class="remove-faq" onclick="removeFaq(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            document.getElementById('faqList').insertAdjacentHTML('beforeend', html);
        });
    }
    
    // Apply focus keywords from AI or selected keywords
    const keywordsField = document.querySelector('input[name="meta_keywords"]');
    if (keywordsField) {
        // Use AI-generated keywords or selected keywords from research
        let keywords = content.keywords || content.focusKeywords || selectedKeywords || [];
        if (Array.isArray(keywords)) {
            keywordsField.value = keywords.join(', ');
        } else if (typeof keywords === 'string') {
            keywordsField.value = keywords;
        }
    }
    
    // Update SEO preview and score
    updateSeoPreview();
    updateSeoScore();
    
    // Display AI scores if available
    if (content.ai_scores) {
        displayAIScores(content.ai_scores, content.generation_time, content.retry_count, content.validation_errors);
    }
    
    // Generate AI thumbnail ONLY if admin hasn't manually uploaded an image
    if (!window._manualImageUploaded) {
        const thumbPrompt = content.thumbnailPrompt || '';
        const thumbSubject = content.thumbnailSubject || '';
        const thumbText = content.thumbnailText || '';
        const thumbIdea = content.featuredImageIdea || '';
        const currentTitle = document.getElementById('blogTitle')?.value || content.title || '';
        if (thumbPrompt || thumbSubject || currentTitle) {
            displayThumbnailSuggestion(thumbPrompt, thumbSubject, thumbText || currentTitle, thumbIdea);
        }
    } else {
        console.log('Skipping AI thumbnail - admin has manually uploaded an image');
    }
    
    // Show success message
    const successMsg = document.createElement('div');
    successMsg.className = 'alert alert-success';
    successMsg.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:15px 25px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    successMsg.innerHTML = '<i class="fas fa-check-circle me-2"></i> AI content generated successfully!';
    document.body.appendChild(successMsg);
    setTimeout(() => successMsg.remove(), 3000);
}

function displayAIScores(scores, genTime, retryCount, validationErrors) {
    // Remove existing scores panel if any
    const existingPanel = document.getElementById('aiScoresPanel');
    if (existingPanel) existingPanel.remove();
    
    const getScoreColor = (score) => {
        if (score >= 80) return '#22c55e';
        if (score >= 60) return '#eab308';
        return '#ef4444';
    };
    
    const getScoreLabel = (score) => {
        if (score >= 80) return 'Excellent';
        if (score >= 60) return 'Good';
        return 'Needs Work';
    };
    
    let scoresHtml = `
        <div id="aiScoresPanel" style="background:linear-gradient(135deg,#1a3c34,#2d6a4f);color:white;border-radius:12px;padding:20px;margin-bottom:20px;">
            <h4 style="margin:0 0 15px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-robot"></i> AI Quality Scores
                <span style="margin-left:auto;font-size:0.8rem;opacity:0.8;">${genTime}s | ${retryCount} attempt(s)</span>
            </h4>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
    `;
    
    const scoreItems = [
        { key: 'overall_score', label: 'Overall', icon: 'fa-star' },
        { key: 'seo_score', label: 'SEO', icon: 'fa-search' },
        { key: 'ctr_score', label: 'CTR', icon: 'fa-mouse-pointer' },
        { key: 'emotional_score', label: 'Emotional', icon: 'fa-heart' },
        { key: 'readability_score', label: 'Readability', icon: 'fa-book-reader' },
        { key: 'conversion_score', label: 'Conversion', icon: 'fa-shopping-cart' },
        { key: 'human_score', label: 'Human-like', icon: 'fa-user' },
        { key: 'engagement_score', label: 'Engagement', icon: 'fa-comments' }
    ];
    
    scoreItems.forEach(item => {
        const score = scores[item.key] || 0;
        scoresHtml += `
            <div style="background:rgba(255,255,255,0.1);border-radius:8px;padding:12px;text-align:center;">
                <i class="fas ${item.icon}" style="font-size:1.2rem;opacity:0.8;"></i>
                <div style="font-size:1.5rem;font-weight:700;color:${getScoreColor(score)};">${score}</div>
                <div style="font-size:0.75rem;opacity:0.8;">${item.label}</div>
            </div>
        `;
    });
    
    scoresHtml += `</div>`;
    
    // Show validation warnings if any
    if (validationErrors && validationErrors.length > 0) {
        scoresHtml += `
            <div style="margin-top:15px;padding:10px;background:rgba(239,68,68,0.2);border-radius:8px;font-size:0.85rem;">
                <strong><i class="fas fa-exclamation-triangle me-1"></i> Validation Notes:</strong>
                <ul style="margin:8px 0 0;padding-left:20px;">
                    ${validationErrors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}
                </ul>
            </div>
        `;
    }
    
    scoresHtml += `</div>`;
    
    // Insert before the editor
    const editorContainer = document.querySelector('.blog-editor-container');
    if (editorContainer) {
        editorContainer.insertAdjacentHTML('afterbegin', scoresHtml);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function displayThumbnailSuggestion(prompt, subject, overlayText, imageIdea) {
    // Remove existing panel if any
    const existingPanel = document.getElementById('thumbnailSuggestionPanel');
    if (existingPanel) existingPanel.remove();
    
    // Store thumbnail data globally for regeneration
    window._thumbData = { prompt, subject, overlayText, imageIdea };
    
    let html = `
        <div id="thumbnailSuggestionPanel" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border-radius:12px;padding:20px;margin-bottom:20px;">
            <h4 style="margin:0 0 15px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-image"></i> AI Thumbnail Generator
                <div style="margin-left:auto;display:flex;gap:8px;">
                    <button type="button" onclick="copyThumbnailPrompt()" style="background:rgba(255,255,255,0.2);border:none;color:white;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.8rem;">
                        <i class="fas fa-copy"></i> Copy Prompt
                    </button>
                </div>
            </h4>
            
            <!-- Thumbnail Preview Area -->
            <div id="thumbnailPreviewArea" style="background:rgba(0,0,0,0.2);border-radius:10px;overflow:hidden;margin-bottom:15px;position:relative;min-height:180px;">
                <div id="thumbnailLoading" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;gap:12px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#fbbf24;"></i>
                    <span style="font-size:0.9rem;opacity:0.9;">Generating stunning thumbnail...</span>
                    <span style="font-size:0.75rem;opacity:0.6;">This may take 15-30 seconds</span>
                </div>
                <img id="thumbnailPreviewImg" style="display:none;width:100%;height:auto;border-radius:10px;" />
            </div>
            
            <!-- Action Buttons -->
            <div id="thumbnailActions" style="display:none;margin-bottom:15px;">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="button" onclick="useAsThumbnail()" style="flex:1;background:#22c55e;border:none;color:white;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                        <i class="fas fa-check-circle"></i> Use as Featured Image
                    </button>
                    <button type="button" onclick="regenerateThumbnail()" style="flex:1;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:white;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                        <i class="fas fa-sync-alt"></i> Regenerate
                    </button>
                </div>
            </div>
            
            <!-- Thumbnail Details -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
    `;
    
    if (subject) {
        html += `
                <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:10px;">
                    <strong style="font-size:0.75rem;opacity:0.8;"><i class="fas fa-bullseye me-1"></i> Subject:</strong>
                    <p style="margin:4px 0 0;font-size:0.85rem;">${escapeHtml(subject)}</p>
                </div>
        `;
    }
    
    if (overlayText) {
        html += `
                <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:10px;">
                    <strong style="font-size:0.75rem;opacity:0.8;"><i class="fas fa-font me-1"></i> Overlay:</strong>
                    <p style="margin:4px 0 0;font-size:0.85rem;font-weight:600;">"${escapeHtml(overlayText)}"</p>
                </div>
        `;
    }
    
    html += `
            </div>
    `;
    
    if (prompt) {
        html += `
            <details style="background:rgba(255,255,255,0.1);border-radius:8px;padding:10px;">
                <summary style="cursor:pointer;font-size:0.8rem;font-weight:600;opacity:0.9;"><i class="fas fa-magic me-1"></i> Image Prompt (click to expand)</summary>
                <p id="thumbnailPromptText" style="margin:8px 0 0;opacity:0.85;font-size:0.8rem;line-height:1.4;">${escapeHtml(prompt)}</p>
            </details>
        `;
    }
    
    html += `
            <div id="thumbnailStatus" style="margin-top:10px;padding:8px 12px;background:rgba(255,255,255,0.1);border-radius:6px;font-size:0.8rem;display:none;"></div>
        </div>`;
    
    // Insert after AI scores panel or at the beginning
    const scoresPanel = document.getElementById('aiScoresPanel');
    if (scoresPanel) {
        scoresPanel.insertAdjacentHTML('afterend', html);
    } else {
        const editorContainer = document.querySelector('.blog-editor-container');
        if (editorContainer) {
            editorContainer.insertAdjacentHTML('afterbegin', html);
        }
    }
    
    // Auto-generate thumbnail immediately
    generateAIThumbnail(prompt, subject, overlayText);
}

// Generate actual thumbnail image via Pollinations.ai
async function generateAIThumbnail(prompt, subject, overlayText) {
    const loadingEl = document.getElementById('thumbnailLoading');
    const imgEl = document.getElementById('thumbnailPreviewImg');
    const actionsEl = document.getElementById('thumbnailActions');
    const statusEl = document.getElementById('thumbnailStatus');
    
    if (!loadingEl) return;
    
    loadingEl.style.display = 'flex';
    imgEl.style.display = 'none';
    actionsEl.style.display = 'none';
    
    const slug = document.getElementById('blogSlug')?.value || 'blog-thumbnail';
    const blogTitle = document.getElementById('blogTitle')?.value || '';
    
    try {
        const resp = await fetch('<?= base_url("admin/blog_ai_api.php") ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({
                action: 'generate_thumbnail',
                prompt: prompt || '',
                subject: subject || '',
                overlay_text: overlayText || '',
                blog_title: blogTitle,
                slug: slug
            })
        });
        
        const data = await resp.json();
        
        if (data.success) {
            // Show the generated image
            imgEl.src = '<?= base_url("") ?>' + data.url + '?t=' + Date.now();
            imgEl.style.display = 'block';
            loadingEl.style.display = 'none';
            actionsEl.style.display = 'block';
            
            // Store the filename for "Use as Featured Image"
            window._generatedThumbnail = data.filename;
            window._generatedThumbnailUrl = data.url;
            
            // AUTO-SET as featured image immediately (no manual click needed)
            useAsThumbnail();
            
            // ALSO save to DB immediately via AJAX for existing blogs
            var blogIdEl = document.querySelector('input[name="id"]');
            var currentBlogId = blogIdEl ? parseInt(blogIdEl.value) : 0;
            if (currentBlogId > 0 && !window._manualImageUploaded) {
                try {
                    fetch('<?= base_url("admin/blog_ai_api.php") ?>', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
                        body: JSON.stringify({action:'save_featured_image', blog_id: currentBlogId, filename: data.filename})
                    }).then(function(r){ return r.json(); }).then(function(r){ console.log('AI thumbnail saved to DB:', r); });
                } catch(e) { console.error('Failed to save thumbnail to DB:', e); }
            }
            
            // Show status
            statusEl.style.display = 'block';
            statusEl.innerHTML = '<i class="fas fa-check-circle" style="color:#22c55e;"></i> Generated & Applied! ' + data.dimensions + ' | ' + data.file_size;
        } else {
            loadingEl.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-exclamation-triangle" style="font-size:1.5rem;color:#fbbf24;"></i><p style="margin:8px 0 0;font-size:0.85rem;">' + (data.message || 'Generation failed') + '</p><button type="button" onclick="regenerateThumbnail()" style="margin-top:10px;background:rgba(255,255,255,0.2);border:none;color:white;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-redo"></i> Try Again</button></div>';
        }
    } catch(err) {
        loadingEl.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-exclamation-triangle" style="font-size:1.5rem;color:#fbbf24;"></i><p style="margin:8px 0 0;font-size:0.85rem;">Connection error. Please try again.</p><button type="button" onclick="regenerateThumbnail()" style="margin-top:10px;background:rgba(255,255,255,0.2);border:none;color:white;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-redo"></i> Try Again</button></div>';
    }
}

// Regenerate thumbnail with new seed
function regenerateThumbnail() {
    if (window._thumbData) {
        generateAIThumbnail(window._thumbData.prompt, window._thumbData.subject, window._thumbData.overlayText);
    }
}

// Use generated thumbnail as the blog's featured image
function useAsThumbnail() {
    if (!window._generatedThumbnail) return;
    
    // DON'T override if admin manually uploaded an image
    if (window._manualImageUploaded) {
        console.log('useAsThumbnail blocked: admin manually uploaded an image');
        return;
    }
    
    // DON'T override if there's already a file selected in the file input
    const fileInput = document.getElementById('imageInput');
    if (fileInput && fileInput.files && fileInput.files.length > 0) {
        console.log('useAsThumbnail blocked: file input has a selected file');
        return;
    }
    
    const upload = document.getElementById('imageUpload');
    let existingInput = document.querySelector('input[name="existing_image"]');
    
    // If existing_image field doesn't exist, create it inside the form
    if (!existingInput) {
        existingInput = document.createElement('input');
        existingInput.type = 'hidden';
        existingInput.name = 'existing_image';
        document.getElementById('blogForm').appendChild(existingInput);
    }
    existingInput.value = window._generatedThumbnail;
    
    // Clear remove flag so image is NOT deleted on save
    const removeFlag = document.getElementById('removeImageFlag');
    if (removeFlag) removeFlag.value = '0';
    
    // Update the featured image preview
    if (upload) {
        upload.innerHTML = `
            <img src="<?= base_url('') ?>${window._generatedThumbnailUrl}?t=${Date.now()}" id="imagePreview" style="width:100%;height:100%;object-fit:cover;">
            <button type="button" class="remove-image" onclick="removeImage()"><i class="fas fa-times"></i></button>
            <input type="file" name="featured_image" id="imageInput" accept="image/*" onchange="previewImage(this)">
        `;
        upload.classList.add('has-image');
    }
    
    // Clear file input so it doesn't override existing_image on save
    var fi = document.getElementById('imageInput');
    if (fi) fi.value = '';
    
    // Show success toast (only if not auto-applied during generation)
    if (!window._thumbnailAutoApplied) {
        window._thumbnailAutoApplied = true;
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:15px 25px;background:linear-gradient(135deg,#22c55e,#16a34a);color:white;border-radius:10px;font-weight:600;box-shadow:0 4px 15px rgba(0,0,0,0.2);display:flex;align-items:center;gap:10px;';
        toast.innerHTML = '<i class="fas fa-check-circle" style="font-size:1.2rem;"></i> AI Thumbnail set as Featured Image!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
}

function copyThumbnailPrompt() {
    const promptText = document.getElementById('thumbnailPromptText');
    if (promptText) {
        navigator.clipboard.writeText(promptText.textContent).then(() => {
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => btn.innerHTML = originalHtml, 2000);
        });
    }
}

// ==================== TAB SWITCHING (handled in early script block above) ====================

// Sync SEO fields between Write tab and SEO tab
function syncSeoTabFields() {
    const metaTitle = document.getElementById('metaTitle');
    const metaDesc = document.getElementById('metaDesc');
    const seoMetaTitle = document.getElementById('seoMetaTitle');
    const seoMetaDesc = document.getElementById('seoMetaDesc');
    const slug = document.getElementById('blogSlug');
    
    if (seoMetaTitle && metaTitle) {
        seoMetaTitle.value = metaTitle.value;
        updateCharCount(seoMetaTitle, 60);
    }
    if (seoMetaDesc && metaDesc) {
        seoMetaDesc.value = metaDesc.value;
        updateCharCount(seoMetaDesc, 160);
    }
    
    // Update SEO tab preview
    const title = metaTitle?.value || document.getElementById('blogTitle')?.value || '';
    const desc = metaDesc?.value || document.getElementById('blogExcerpt')?.value || '';
    
    const seoPreviewTitle = document.getElementById('seoPreviewTitle');
    const seoPreviewDesc = document.getElementById('seoPreviewDesc');
    const seoPreviewSlug = document.getElementById('seoPreviewSlug');
    
    if (seoPreviewTitle) seoPreviewTitle.textContent = (title || 'Your Blog Title') + ' | Gilaf Store';
    if (seoPreviewDesc) seoPreviewDesc.textContent = desc || 'Your meta description will appear here...';
    if (seoPreviewSlug) seoPreviewSlug.textContent = slug?.value || 'your-blog-slug';
}

// Sync from SEO tab back to Write tab
function syncSeoFields() {
    const seoMetaTitle = document.getElementById('seoMetaTitle');
    const seoMetaDesc = document.getElementById('seoMetaDesc');
    const metaTitle = document.getElementById('metaTitle');
    const metaDesc = document.getElementById('metaDesc');
    
    if (metaTitle && seoMetaTitle) metaTitle.value = seoMetaTitle.value;
    if (metaDesc && seoMetaDesc) metaDesc.value = seoMetaDesc.value;
    
    // Update previews
    const title = seoMetaTitle?.value || document.getElementById('blogTitle')?.value || '';
    const desc = seoMetaDesc?.value || '';
    const slug = document.getElementById('blogSlug')?.value || '';
    
    document.getElementById('seoPreviewTitle').textContent = (title || 'Your Blog Title') + ' | Gilaf Store';
    document.getElementById('seoPreviewDesc').textContent = desc || 'Your meta description will appear here...';
    document.getElementById('seoPreviewSlug').textContent = slug || 'your-blog-slug';
    
    updateSeoPreview();
}

// ==================== TITLE SUGGESTIONS (Debounced) ====================
var titleSuggestTimer = null;
var lastTitleQuery = '';
var _cachedTitles = [];

// Setup on page load
(function() {
    var inp = document.getElementById('researchTopic');
    if (!inp) return;
    inp.addEventListener('input', function() {
        var v = this.value.trim();
        clearTimeout(titleSuggestTimer);
        if (v.length >= 3 && v !== lastTitleQuery) {
            titleSuggestTimer = setTimeout(function(){ window._fetchTitles(v); }, 1200);
        } else if (v.length < 3) {
            window._closeTS();
        }
    });
})();

window._fetchTitles = async function(keyword) {
    lastTitleQuery = keyword;
    var dd = document.getElementById('titleSuggestionsDropdown');
    var list = document.getElementById('titleSuggestionsList');
    // Position dropdown near the input - prefer below, flip above if no space
    var inp = document.getElementById('researchTopic');
    if (inp) {
        var rect = inp.getBoundingClientRect();
        var ddHeight = 450; // max-height of dropdown
        var spaceBelow = window.innerHeight - rect.bottom - 10;
        var spaceAbove = rect.top - 10;
        
        dd.style.left = rect.left + 'px';
        dd.style.width = Math.max(rect.width, 400) + 'px';
        
        if (spaceBelow >= ddHeight || spaceBelow >= spaceAbove) {
            // Show below input
            dd.style.top = (rect.bottom + 4) + 'px';
            dd.style.bottom = 'auto';
            dd.style.maxHeight = Math.min(ddHeight, spaceBelow) + 'px';
        } else {
            // Show above input
            dd.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
            dd.style.top = 'auto';
            dd.style.maxHeight = Math.min(ddHeight, spaceAbove) + 'px';
        }
    }
    dd.style.display = 'block';
    list.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;padding:30px;color:#6366f1;font-size:0.9rem;gap:10px;"><i class="fas fa-spinner fa-spin"></i> AI is thinking of viral titles...</div>';
    try {
        var resp = await fetch('<?= base_url("admin/blog_ai_api.php") ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({action:'suggest_titles', keyword: keyword})
        });
        var data = await resp.json();
        if (data.success && data.titles && data.titles.length > 0) {
            window._renderTitles(data.titles);
        } else {
            list.innerHTML = '<div class="title-suggestions-loading" style="color:#ef4444"><i class="fas fa-exclamation-circle"></i> ' + (data.message || 'No suggestions') + '</div>';
        }
    } catch(err) {
        list.innerHTML = '<div class="title-suggestions-loading" style="color:#ef4444"><i class="fas fa-exclamation-circle"></i> Failed to fetch</div>';
    }
};

window._renderTitles = function(titles) {
    window._cachedTitles = titles;
    var list = document.getElementById('titleSuggestionsList');
    var colors = {shocking:'#ef4444',curiosity:'#8b5cf6',fear:'#f97316',numbered:'#06b6d4',question:'#22c55e',contrarian:'#ec4899'};
    var icons = {shocking:'fa-bolt',curiosity:'fa-eye',fear:'fa-exclamation-triangle',numbered:'fa-list-ol',question:'fa-question',contrarian:'fa-exchange-alt'};
    
    // Build as anchor tags - browsers ALWAYS fire click on anchors
    var html = '';
    for (var i = 0; i < titles.length; i++) {
        var t = titles[i];
        var type = (t.type||'curiosity').toLowerCase();
        var ic = icons[type] || 'fa-lightbulb';
        var bg = colors[type] || '#6366f1';
        var ti = (t.title||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        var hk = (t.hook||type).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        html += '<a href="#" data-pick="'+i+'" style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;cursor:pointer;border-bottom:1px solid #f3f4f6;text-decoration:none;color:inherit;" onmouseover="this.style.background=\'#f0f0ff\'" onmouseout="this.style.background=\'\'">';
        html += '<span style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;background:'+bg+'"><i class="fas '+ic+'" style="font-size:0.75rem"></i></span>';
        html += '<span style="flex:1"><span style="display:block;margin:0 0 4px;font-size:0.9rem;font-weight:600;color:#1f2937;line-height:1.3">'+ti+'</span><span style="display:block;font-size:0.75rem;color:#9ca3af;line-height:1.3">'+hk+'</span></span>';
        html += '</a>';
    }
    list.innerHTML = html;
    // Click handler is already attached via addEventListener in the early script
};

window._closeTS = function() {
    var dd = document.getElementById('titleSuggestionsDropdown');
    if (dd) dd.style.display = 'none';
};

window.closeTitleSuggestions = window._closeTS;
window.refreshTitleSuggestions = function() {
    var v = document.getElementById('researchTopic').value.trim();
    if (v.length >= 3) { lastTitleQuery = ''; window._fetchTitles(v); }
};

// ==================== AI RESEARCH FUNCTIONS ====================
let selectedTopic = null;
let selectedKeywords = [];

async function doKeywordResearch() {
    const topic = document.getElementById('researchTopic').value.trim();
    if (!topic) {
        alert('Please enter a topic to research');
        return;
    }
    
    const btn = document.querySelector('.btn-research');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Researching...';
    
    try {
        const response = await fetch('<?= base_url("admin/blog_ai_api.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'research',
                topic: topic
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayResearchResults(data);
        } else {
            throw new Error(data.message || 'Research failed');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Research';
    }
}

function displayResearchResults(data) {
    // Display trending topics
    if (data.topics && data.topics.length > 0) {
        const trendingBox = document.getElementById('trendingTopicsBox');
        const trendingList = document.getElementById('trendingList');
        
        trendingList.innerHTML = data.topics.map((topic, index) => `
            <div class="trending-item" onclick="selectTopic(${index}, this)" data-topic='${JSON.stringify(topic).replace(/'/g, "&#39;")}'>
                <div class="trend-icon"><i class="fas fa-lightbulb"></i></div>
                <div class="trend-info">
                    <h5>${escapeHtml(topic.title || topic)}</h5>
                    <p>${escapeHtml(topic.description || 'Click to select this topic')}</p>
                </div>
                <span class="trend-score">${topic.score || 'High'}</span>
            </div>
        `).join('');
        
        trendingBox.style.display = 'block';
    }
    
    // Display keywords
    if (data.keywords && data.keywords.length > 0) {
        const keywordsBox = document.getElementById('keywordsBox');
        const keywordTags = document.getElementById('keywordTags');
        
        keywordTags.innerHTML = data.keywords.map(kw => `
            <span class="keyword-tag" onclick="toggleKeyword(this, '${escapeHtml(kw)}')">
                ${escapeHtml(kw)}
            </span>
        `).join('');
        
        keywordsBox.style.display = 'block';
    }
    
    // Show content options
    document.getElementById('aiContentOptions').style.display = 'block';
}

function selectTopic(index, element) {
    // Remove previous selection
    document.querySelectorAll('.trending-item').forEach(item => item.classList.remove('selected'));
    
    // Select this topic
    element.classList.add('selected');
    selectedTopic = JSON.parse(element.dataset.topic);
    
    // Pre-fill title if empty
    const titleInput = document.getElementById('blogTitle');
    if (!titleInput.value.trim() && selectedTopic.title) {
        titleInput.value = selectedTopic.title;
        generateSlug();
    }
}

function toggleKeyword(element, keyword) {
    element.classList.toggle('selected');
    
    if (element.classList.contains('selected')) {
        if (!selectedKeywords.includes(keyword)) {
            selectedKeywords.push(keyword);
        }
    } else {
        selectedKeywords = selectedKeywords.filter(k => k !== keyword);
    }
}

async function generateFullBlog() {
    const topic = selectedTopic?.title || document.getElementById('researchTopic').value.trim() || document.getElementById('blogTitle').value.trim();
    
    if (!topic) {
        alert('Please enter a topic or select one from the suggestions');
        return;
    }
    
    const tone = document.getElementById('aiToneSelect').value;
    const length = document.getElementById('aiLengthSelect').value;
    const includeFaqs = document.getElementById('aiIncludeFaqsCheck').checked;
    const includeProducts = document.getElementById('aiIncludeProductsCheck').checked;
    const includeSeo = document.getElementById('aiIncludeSeoCheck').checked;
    const category = document.getElementById('categorySelect').value;
    
    // Get linked products
    const products = [];
    document.querySelectorAll('.linked-product-item').forEach(item => {
        products.push({
            id: item.dataset.productId,
            name: item.querySelector('h5')?.textContent || ''
        });
    });
    
    // Show workflow progress
    const progressPanel = document.getElementById('aiWorkflowProgress');
    progressPanel.classList.add('active');
    
    // Disable generate button
    const btn = document.querySelector('.btn-generate-full');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    // Animate workflow steps
    const steps = ['step-research', 'step-outline', 'step-content', 'step-faqs', 'step-seo'];
    let currentStep = 0;
    
    const stepInterval = setInterval(() => {
        if (currentStep > 0) {
            document.getElementById(steps[currentStep - 1]).classList.remove('active');
            document.getElementById(steps[currentStep - 1]).classList.add('completed');
        }
        if (currentStep < steps.length) {
            document.getElementById(steps[currentStep]).classList.add('active');
            currentStep++;
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
                topic: topic,
                selectedTopic: selectedTopic || { title: topic, description: '' },
                keywords: selectedKeywords.length > 0 ? selectedKeywords : [],
                tone: tone,
                length: length,
                includeFaqs: includeFaqs,
                includeProducts: includeProducts,
                includeSeo: includeSeo,
                products: products,
                category: category
            })
        });
        
        const data = await response.json();
        
        clearInterval(stepInterval);
        
        // Mark all steps as completed
        steps.forEach(step => {
            document.getElementById(step).classList.remove('active');
            document.getElementById(step).classList.add('completed');
        });
        
        if (data.success) {
            setTimeout(() => {
                applyGeneratedContent(data.content);
                
                // Switch to Write tab
                switchTab('write');
                
                // Reset progress panel
                progressPanel.classList.remove('active');
                steps.forEach(step => {
                    document.getElementById(step).classList.remove('completed', 'active');
                });
                
                // Show success message
                showSuccessMessage('Blog content generated successfully! Review and edit as needed.');
            }, 1000);
        } else {
            const errDetails = (data.errors && data.errors.length) ? '\n\nDetails:\n' + data.errors.join('\n') : '';
            throw new Error((data.message || 'Generation failed') + errDetails);
        }
    } catch (error) {
        clearInterval(stepInterval);
        progressPanel.classList.remove('active');
        steps.forEach(step => {
            document.getElementById(step).classList.remove('completed', 'active');
        });
        alert('Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic"></i> Generate Complete Blog';
    }
}

function showSuccessMessage(message) {
    const successMsg = document.createElement('div');
    successMsg.className = 'alert alert-success';
    successMsg.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:15px 25px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);background:#dcfce7;color:#166534;border:1px solid #bbf7d0;';
    successMsg.innerHTML = '<i class="fas fa-check-circle me-2"></i> ' + message;
    document.body.appendChild(successMsg);
    setTimeout(() => successMsg.remove(), 4000);
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
