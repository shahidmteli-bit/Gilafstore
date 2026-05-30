<?php
/**
 * Gilaf AI SEO Intelligence Engine — API Endpoint
 * Handles all AJAX requests for the SEO dashboard
 */

@ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/seo_engine.php';
require_once __DIR__ . '/../includes/seo_vector_engine.php';
require_once __DIR__ . '/../includes/seo_autofix_engine.php';

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = get_db_connection();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

// Load AI API key + init config
$apiKey = '';
try {
    $row = $db->query("SELECT setting_value FROM chatbot_settings WHERE setting_key='api_key' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $apiKey = $row['setting_value'] ?? '';
} catch (Exception $e) {}
SeoIntelConfig::init($db);

try {
    switch ($action) {
        case 'analyze_content':
            handleAnalyzeContent($input, $db);
            break;
        case 'scan_blogs':
            handleScanBlogs($db);
            break;
        case 'scan_products':
            handleScanProducts($db);
            break;
        case 'site_stats':
            handleSiteStats($db);
            break;
        case 'link_opportunities':
            handleLinkOpportunities($db);
            break;
        case 'orphan_pages':
            handleOrphanPages($db);
            break;
        case 'ai_semantic':
            handleAiSemantic($input, $db, $apiKey);
            break;
        case 'ai_brief':
            handleAiBrief($input, $db, $apiKey);
            break;
        case 'ai_eeat':
            handleAiEeat($input, $db, $apiKey);
            break;
        case 'ai_keywords':
            handleAiKeywords($input, $db, $apiKey);
            break;
        case 'ai_improve':
            handleAiImprove($input, $db, $apiKey);
            break;
        case 'ai_cluster':
            handleAiCluster($input, $db, $apiKey);
            break;
        case 'analyze_blog':
            handleAnalyzeBlog($input, $db);
            break;
        case 'bulk_analyze':
            handleBulkAnalyze($db);
            break;
        case 'content_workflow':
            handleContentWorkflow($input, $db);
            break;
        // === V3 INTELLIGENCE ENGINES ===
        case 'orphan_autofix':
            handleOrphanAutoFix($input, $db);
            break;
        case 'orphan_insert_link':
            handleOrphanInsertLink($input, $db);
            break;
        case 'pre_publish_check':
            handlePrePublishCheck($input, $db);
            break;
        case 'connectivity_graph':
            handleConnectivityGraph($db);
            break;
        case 'v3_entity_extract':
            handleEntityExtract($input, $apiKey);
            break;
        case 'v3_knowledge_graph':
            handleKnowledgeGraph($input, $apiKey);
            break;
        case 'v3_search_intent':
            handleSearchIntent($input, $apiKey);
            break;
        case 'v3_semantic_links':
            handleSemanticLinks($input, $db, $apiKey);
            break;
        case 'v3_semantic_duplicates':
            handleSemanticDuplicates($db, $apiKey);
            break;
        case 'v3_topical_authority':
            handleTopicalAuthority($input, $db, $apiKey);
            break;
        case 'v3_content_gaps':
            handleContentGaps($input, $db, $apiKey);
            break;
        case 'v3_content_opportunities':
            handleContentOpportunities($db, $apiKey);
            break;
        case 'v3_ai_search_optimize':
            handleAiSearchOptimize($input, $db, $apiKey);
            break;
        case 'v3_generate_snippets':
            handleGenerateSnippets($input, $db, $apiKey);
            break;
        case 'v3_ranking_predict':
            handleRankingPredict($input, $db, $apiKey);
            break;
        case 'v3_serp_analyze':
            handleSerpAnalyze($input, $db);
            break;
        case 'v3_keyword_data':
            handleKeywordData($input, $db);
            break;
        case 'v3_api_stats':
            handleApiStats();
            break;
        case 'v3_save_settings':
            handleSaveSettings($input, $db);
            break;
        case 'v3_get_settings':
            handleGetSettings($db);
            break;
        case 'v3_index_content':
            handleIndexContent($db, $apiKey);
            break;
        case 'api_test':
            handleApiTest($input, $db, $apiKey);
            break;
        case 'dataforseo_test':
            handleDataForSeoTest($db);
            break;
        case 'ai_ping':
            handleAiPing($db);
            break;
        // === NEW v4 FEATURES ===
        case 'v4_why_not_ranking':
            handleWhyNotRanking($input, $db);
            break;
        case 'v4_product_analyze':
            handleProductAnalyze($input, $db);
            break;
        case 'v4_ai_fix':
            handleAiFix($input, $db, $apiKey);
            break;
        case 'v4_generate_schema':
            handleGenerateSchema($input, $db, $apiKey);
            break;
        case 'v4_validate_schema':
            handleValidateSchema($input);
            break;
        case 'v4_apply_schema':
            handleApplySchema($input, $db);
            break;
        case 'v4_check_schema':
            handleCheckSchema($input, $db);
            break;
        case 'v4_pagespeed':
            handlePageSpeed($input);
            break;
        case 'v4_serp_compare':
            handleSerpCompare($input, $db);
            break;
        case 'v4_ctr_analyze':
            handleCtrAnalyze($input);
            break;
        case 'v4_qdrant_test':
            handleQdrantTest($db);
            break;
        case 'v4_hf_test':
            handleHuggingFaceTest($db);
            break;
        case 'v5_detailed_analysis':
            handleDetailedAnalysis($input, $db);
            break;
        // === V5 SMART AUTO FIX ENGINE ===
        case 'v5_fix_preview':
            handleFixPreview($input, $db, $apiKey);
            break;
        case 'v5_apply_fix':
            handleApplyFix($input, $db, $apiKey);
            break;
        case 'v5_bulk_fix':
            handleBulkFix($input, $db, $apiKey);
            break;
        case 'v5_undo_fix':
            handleUndoFix($input, $db);
            break;
        case 'v5_fix_log':
            handleGetFixLog($input, $db);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// ============================================================
// HANDLERS
// ============================================================

function handleAnalyzeContent($input, $db) {
    $analyzer = new GilafSeoAnalyzer($db);
    $results = $analyzer->analyzeContent($input);
    $results['overall_score'] = $analyzer->calculateOverallScore($results);
    echo json_encode(['success' => true, 'results' => $results]);
}

function handleAnalyzeBlog($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    if (!$blogId) {
        echo json_encode(['success' => false, 'message' => 'Blog ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT b.*, c.name as category_name FROM blogs b LEFT JOIN blog_categories c ON c.id = b.category_id WHERE b.id = ?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$blog) {
        echo json_encode(['success' => false, 'message' => 'Blog not found']);
        return;
    }
    
    // Render markdown to HTML for analysis
    $content = $blog['content'] ?? '';
    $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
    $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
    $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
    $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
    
    $analyzer = new GilafSeoAnalyzer($db);
    $results = $analyzer->analyzeContent([
        'content' => $content,
        'title' => $blog['title'],
        'meta_title' => $blog['meta_title'],
        'meta_description' => $blog['meta_description'],
        'slug' => $blog['slug'],
        'excerpt' => $blog['excerpt'],
        'focus_keyword' => $blog['meta_keywords'],
        'url' => '/blog/' . $blog['slug']
    ]);
    $results['overall_score'] = $analyzer->calculateOverallScore($results);
    $results['blog'] = [
        'id' => $blog['id'],
        'title' => $blog['title'],
        'slug' => $blog['slug'],
        'status' => $blog['status'],
        'category' => $blog['category_name'],
        'word_count' => str_word_count(strip_tags($blog['content'] ?? '')),
        'content'    => mb_substr(strip_tags($blog['content'] ?? ''), 0, 3000),
        'keyword'    => $blog['meta_keywords'] ?? ''
    ];
    
    echo json_encode(['success' => true, 'results' => $results]);
}

function handleBulkAnalyze($db) {
    $blogs = $db->query("SELECT id, title, slug, meta_title, meta_description, meta_keywords, content, excerpt, status FROM blogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    $analyzer = new GilafSeoAnalyzer($db);
    
    foreach ($blogs as $blog) {
        $content = $blog['content'] ?? '';
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        
        $analysis = $analyzer->analyzeContent([
            'content' => $content,
            'title' => $blog['title'],
            'meta_title' => $blog['meta_title'],
            'meta_description' => $blog['meta_description'],
            'slug' => $blog['slug'],
            'excerpt' => $blog['excerpt'],
            'focus_keyword' => $blog['meta_keywords']
        ]);
        
        $results[] = [
            'id' => $blog['id'],
            'title' => $blog['title'],
            'slug' => $blog['slug'],
            'status' => $blog['status'],
            'word_count' => str_word_count(strip_tags($blog['content'] ?? '')),
            'overall_score' => $analyzer->calculateOverallScore($analysis),
            'basic_seo' => $analysis['basic_seo']['score'],
            'keyword' => $analysis['keyword']['score'],
            'readability' => $analysis['readability']['score'],
            'content_quality' => $analysis['content_quality']['score'],
        ];
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
}

function handleScanBlogs($db) {
    $scanner = new GilafTechnicalSeoScanner($db);
    echo json_encode(['success' => true, 'results' => $scanner->scanBlogs()]);
}

function handleScanProducts($db) {
    $scanner = new GilafTechnicalSeoScanner($db);
    echo json_encode(['success' => true, 'results' => $scanner->scanProducts()]);
}

function handleSiteStats($db) {
    $scanner = new GilafTechnicalSeoScanner($db);
    echo json_encode(['success' => true, 'stats' => $scanner->getSiteStats()]);
}

function handleLinkOpportunities($db) {
    @set_time_limit(120);
    try {
        $scanner = new GilafTechnicalSeoScanner($db);
        $result = $scanner->findLinkOpportunities();
        echo json_encode([
            'success' => true,
            'opportunities' => $result['opportunities'] ?? [],
            'cannibalization' => $result['cannibalization'] ?? [],
            'orphans' => $result['orphans'] ?? [],
            'weak_pages' => $result['weak_pages'] ?? [],
            'stats' => $result['stats'] ?? [],
            'generated_at' => $result['generated_at'] ?? date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        error_log('SEO Link Opportunities Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Analysis failed: ' . $e->getMessage(), 'opportunities' => [], 'cannibalization' => [], 'orphans' => [], 'weak_pages' => [], 'stats' => []]);
    }
}

function handleOrphanPages($db) {
    $scanner = new GilafTechnicalSeoScanner($db);
    $result = $scanner->findOrphanPages();
    echo json_encode(['success' => true, 'orphans' => $result['orphans'], 'weak_pages' => $result['weak_pages']]);
}

function handleOrphanAutoFix($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    if (!$blogId) { echo json_encode(['success' => false, 'message' => 'Blog ID required']); return; }
    $scanner = new GilafTechnicalSeoScanner($db);
    $result = $scanner->autoFixOrphan($blogId);
    echo json_encode($result);
}

function handleOrphanInsertLink($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    $anchor = $input['anchor'] ?? '';
    $url = $input['url'] ?? '';
    if (!$blogId || !$anchor || !$url) { echo json_encode(['success' => false, 'message' => 'blog_id, anchor, and url required']); return; }
    $scanner = new GilafTechnicalSeoScanner($db);
    $result = $scanner->insertLink($blogId, $anchor, $url);
    echo json_encode($result);
}

function handlePrePublishCheck($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    if (!$blogId) { echo json_encode(['success' => false, 'message' => 'Blog ID required']); return; }
    $jsProductCount = isset($input['js_product_count']) ? (int)$input['js_product_count'] : -1;
    $jsBlogCount    = isset($input['js_blog_count'])    ? (int)$input['js_blog_count']    : -1;
    $scanner = new GilafTechnicalSeoScanner($db);
    $result = $scanner->prePublishCheck($blogId, $jsProductCount, $jsBlogCount);
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleConnectivityGraph($db) {
    $scanner = new GilafTechnicalSeoScanner($db);
    $result = $scanner->buildConnectivityGraph();
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleAiSemantic($input, $db, $apiKey) {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'AI API key not configured']);
        return;
    }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->analyzeSemanticGap($input['keyword'] ?? '', $input['content'] ?? '');
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'AI analysis failed']);
}

function handleAiBrief($input, $db, $apiKey) {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'AI API key not configured']);
        return;
    }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->generateContentBrief($input['keyword'] ?? '', $input['category'] ?? '');
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'Brief generation failed']);
}

function handleAiEeat($input, $db, $apiKey) {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'AI API key not configured']);
        return;
    }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->suggestEEATImprovements($input['content'] ?? '', $input['category'] ?? '');
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'EEAT analysis failed']);
}

function handleAiKeywords($input, $db, $apiKey) {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'AI API key not configured']);
        return;
    }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->researchKeywords($input['keyword'] ?? '', $input['category'] ?? '');
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'Keyword research failed']);
}

function handleAiImprove($input, $db, $apiKey) {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'AI API key not configured']);
        return;
    }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->improveContent($input['content'] ?? '', $input['instruction'] ?? 'Improve for SEO');
    echo json_encode(['success' => (bool)$result, 'data' => ['improved_content' => $result], 'message' => $result ? '' : 'Content improvement failed']);
}

function handleAiCluster($input, $db, $apiKey) {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'AI API key not configured']);
        return;
    }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->generateTopicCluster($input['topic'] ?? '');
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'Cluster generation failed']);
}

function handleContentWorkflow($input, $db) {
    $subAction = $input['sub_action'] ?? '';
    
    switch ($subAction) {
        case 'get_checklist':
            $blogId = (int)($input['blog_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM blogs WHERE id = ?");
            $stmt->execute([$blogId]);
            $blog = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$blog) {
                echo json_encode(['success' => false, 'message' => 'Blog not found']);
                return;
            }
            
            $checklist = [];
            $checklist[] = ['item' => 'Title set', 'done' => !empty($blog['title']), 'required' => true];
            $checklist[] = ['item' => 'Content written', 'done' => str_word_count(strip_tags($blog['content'] ?? '')) >= 300, 'required' => true];
            $checklist[] = ['item' => 'Meta title set', 'done' => !empty($blog['meta_title']), 'required' => true];
            $checklist[] = ['item' => 'Meta description set', 'done' => !empty($blog['meta_description']), 'required' => true];
            $checklist[] = ['item' => 'Focus keywords set', 'done' => !empty($blog['meta_keywords']), 'required' => true];
            $checklist[] = ['item' => 'Featured image set', 'done' => !empty($blog['featured_image']), 'required' => true];
            $checklist[] = ['item' => 'Excerpt written', 'done' => !empty($blog['excerpt']), 'required' => false];
            $checklist[] = ['item' => 'Category assigned', 'done' => !empty($blog['category_id']), 'required' => false];
            
            // Check FAQs
            $faqCount = (int)$db->prepare("SELECT COUNT(*) FROM blog_faqs WHERE blog_id = ?")->execute([$blogId]) ? $db->query("SELECT COUNT(*) FROM blog_faqs WHERE blog_id = {$blogId}")->fetchColumn() : 0;
            $checklist[] = ['item' => 'FAQs added (3+)', 'done' => $faqCount >= 3, 'required' => false];
            
            // Check linked products
            $productCount = (int)$db->query("SELECT COUNT(*) FROM blog_products WHERE blog_id = {$blogId}")->fetchColumn();
            $checklist[] = ['item' => 'Products linked', 'done' => $productCount > 0, 'required' => false];
            
            $requiredDone = count(array_filter($checklist, fn($c) => $c['required'] && $c['done']));
            $requiredTotal = count(array_filter($checklist, fn($c) => $c['required']));
            $ready = $requiredDone === $requiredTotal;
            
            echo json_encode([
                'success' => true,
                'checklist' => $checklist,
                'ready_to_publish' => $ready,
                'completion' => $requiredTotal > 0 ? round(($requiredDone / $requiredTotal) * 100) : 0
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid workflow action']);
    }
}

// ============================================================
// V3 INTELLIGENCE ENGINE HANDLERS
// ============================================================

function handleEntityExtract($input, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $engine = new EntityExtractionEngine($apiKey);
    $result = $engine->extractEntities($input['content'] ?? '', $input['keyword'] ?? '');
    echo json_encode(['success' => (bool)$result, 'data' => $result]);
}

function handleKnowledgeGraph($input, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $engine = new EntityExtractionEngine($apiKey);
    $entities = $engine->extractEntities($input['content'] ?? '', $input['keyword'] ?? '');
    if (!$entities) { echo json_encode(['success' => false, 'message' => 'Entity extraction failed']); return; }
    $graph = $engine->buildKnowledgeGraph($entities);
    echo json_encode(['success' => true, 'data' => ['entities' => $entities, 'graph' => $graph]]);
}

function handleSearchIntent($input, $apiKey) {
    $classifier = new SearchIntentClassifier($apiKey);
    $basic = $classifier->classify($input['keyword'] ?? '');
    $deep = null;
    if (!empty($apiKey) && ($input['deep'] ?? false)) {
        $deep = $classifier->deepClassify($input['keyword'] ?? '');
    }
    echo json_encode(['success' => true, 'data' => ['basic' => $basic, 'deep' => $deep]]);
}

function handleSemanticLinks($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $embedder = new EmbeddingEngine($apiKey);
    $engine = new SemanticLinkingEngine($db, $embedder);
    $suggestions = $engine->suggestLinks($input['content'] ?? '', (int)($input['blog_id'] ?? 0), (int)($input['limit'] ?? 10));
    echo json_encode(['success' => true, 'data' => $suggestions]);
}

function handleSemanticDuplicates($db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $blogs = $db->query("SELECT id, title, slug, content FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
    $contents = [];
    foreach ($blogs as $b) {
        $contents[] = ['id' => $b['id'], 'title' => $b['title'], 'slug' => $b['slug'], 'text' => $b['title'] . ' ' . mb_substr(strip_tags($b['content'] ?? ''), 0, 500)];
    }
    $embedder = new EmbeddingEngine($apiKey);
    $sim = new SemanticSimilarityEngine($embedder);
    $dupes = $sim->detectDuplicates($contents, 0.80);
    echo json_encode(['success' => true, 'data' => $dupes, 'total_compared' => count($contents)]);
}

function handleTopicalAuthority($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $embedder = new EmbeddingEngine($apiKey);
    $engine = new TopicalAuthorityEngine($db, $embedder, $apiKey);
    $result = $engine->calculateAuthority($input['topic'] ?? '');
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleContentGaps($input, $db, $apiKey) {
    $topic = trim($input['topic'] ?? '');
    if (empty($topic)) { echo json_encode(['success' => false, 'message' => 'Enter a topic first']); return; }
    $embedder = new EmbeddingEngine($apiKey);
    $engine = new TopicalAuthorityEngine($db, $embedder, $apiKey);
    $result = $engine->findContentGaps($topic);
    echo json_encode(['success' => !empty($result), 'data' => $result ?? ['gaps' => [], 'pillar_recommendations' => [], 'authority_improvement' => '']]);
}

function handleContentOpportunities($db, $apiKey) {
    $embedder = new EmbeddingEngine($apiKey);
    $engine = new ContentOpportunityEngine($db, $embedder, $apiKey);
    $result = $engine->discoverOpportunities();
    echo json_encode(['success' => !empty($result), 'data' => $result ?? ['opportunities' => [], 'underserved_topics' => [], 'product_content_gaps' => []]]);
}

function handleAiSearchOptimize($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    // Fetch blog content server-side if blog_id provided
    $content = $input['content'] ?? '';
    $keyword = $input['keyword'] ?? '';
    if (!empty($input['blog_id'])) {
        $stmt = $db->prepare("SELECT title, content, meta_keywords FROM blogs WHERE id = ?");
        $stmt->execute([(int)$input['blog_id']]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($blog) {
            $content = $blog['content'] ?? '';
            $keyword = $blog['meta_keywords'] ?: $blog['title'];
        }
    }
    $engine = new AiSearchOptimizer($apiKey, $db);
    $result = $engine->optimizeForAiSearch($content, $keyword);
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'AI analysis failed. Check your AI provider settings.']);
}

function handleGenerateSnippets($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $keyword = $input['keyword'] ?? '';
    $content = $input['content'] ?? '';
    $blogTitle = '';
    if (!empty($input['blog_id'])) {
        $stmt = $db->prepare("SELECT title, content, meta_keywords FROM blogs WHERE id = ?");
        $stmt->execute([(int)$input['blog_id']]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($blog) {
            $content = $blog['content'] ?? '';
            $blogTitle = $blog['title'] ?? '';
            $keyword = !empty($blog['meta_keywords']) ? $blog['meta_keywords'] : $blog['title'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Blog not found']);
            return;
        }
    }
    if (empty($keyword)) {
        echo json_encode(['success' => false, 'message' => 'No keyword/blog selected']);
        return;
    }
    $engine = new AiSearchOptimizer($apiKey, $db);
    $result = $engine->generateSnippets($keyword, $content);
    if ($result && is_array($result)) {
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Snippet generation failed — AI did not return valid JSON. Keyword used: "' . mb_substr($keyword, 0, 50) . '"']);
    }
}

function handleRankingPredict($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    // Get blog analysis first
    $blogId = (int)($input['blog_id'] ?? 0);
    $keyword = $input['keyword'] ?? '';
    $analysis = [];
    if ($blogId) {
        $stmt = $db->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($blog) {
            $analyzer = new GilafSeoAnalyzer($db);
            $content = $blog['content'] ?? '';
            $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
            $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
            $analysis = $analyzer->analyzeContent([
                'content' => $content, 'title' => $blog['title'], 'meta_title' => $blog['meta_title'],
                'meta_description' => $blog['meta_description'], 'slug' => $blog['slug'],
                'excerpt' => $blog['excerpt'], 'focus_keyword' => $blog['meta_keywords']
            ]);
            $analysis['overall_score'] = $analyzer->calculateOverallScore($analysis);
            if (!$keyword) $keyword = $blog['meta_keywords'] ?? $blog['title'];
        }
    }
    $engine = new RankingProbabilityEngine($apiKey, $db);
    $result = $engine->predictRanking($keyword, $analysis);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => $engine->lastError ?: 'Ranking prediction failed — check AI API key in API Center']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleSerpAnalyze($input, $db) {
    // Debug: check what credentials we actually have
    $login = '';
    $password = '';
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('dataforseo_login','dataforseo_password')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $login = $rows['dataforseo_login'] ?? '';
        $password = $rows['dataforseo_password'] ?? '';
    } catch (Exception $e) {}
    
    // Check for corrupted credentials (masked values saved by old bug)
    if (strpos($password, '***') !== false || strpos($password, '•') !== false) {
        echo json_encode(['success' => false, 'message' => 'DataForSEO password was corrupted (contains masked characters). Please re-enter your password in API Center tab and save again.']);
        return;
    }
    
    $engine = new DataForSeoEngine($db);
    if (!$engine->isConfigured()) {
        $hint = '';
        if (empty($login)) $hint = 'Login is empty.';
        elseif (empty($password)) $hint = 'Password is empty (login: ' . substr($login, 0, 5) . '...).';
        echo json_encode(['success' => false, 'message' => 'DataForSEO not configured. ' . $hint . ' Save your credentials in API Center → click "Test DataForSEO Connection".']);
        return;
    }
    $keyword = trim($input['keyword'] ?? '');
    if (empty($keyword)) { echo json_encode(['success' => false, 'message' => 'Keyword required']); return; }
    $result = $engine->analyzeCompetitors($keyword);
    if (empty($result)) {
        $err = $engine->lastError ?: 'No results returned';
        echo json_encode(['success' => false, 'message' => 'DataForSEO error for "' . $keyword . '": ' . $err]);
        return;
    }
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleKeywordData($input, $db) {
    $engine = new DataForSeoEngine($db);
    if (!$engine->isConfigured()) {
        echo json_encode(['success' => false, 'message' => 'DataForSEO not configured. Save your credentials in the API Center tab and click "Test DataForSEO Connection" to verify.']);
        return;
    }
    $keyword = trim($input['keyword'] ?? '');
    if (empty($keyword)) { echo json_encode(['success' => false, 'message' => 'Keyword required']); return; }
    $result = $engine->getKeywordData($keyword);
    echo json_encode(['success' => (bool)$result, 'data' => $result]);
}

function handleApiStats() {
    $cache = new EmbeddingCache();
    $tokenStats = $cache->getTokenStats(30);
    $cacheStats = $cache->getCacheStats();
    echo json_encode(['success' => true, 'data' => ['tokens' => $tokenStats, 'cache' => $cacheStats]]);
}

function handleSaveSettings($input, $db) {
    $keys = ['qdrant_url', 'qdrant_api_key', 'dataforseo_login', 'dataforseo_password', 'hf_api_key', 'embedding_provider'];
    $saved = 0;
    foreach ($keys as $key) {
        if (array_key_exists($key, $input)) {
            $val = $input[$key];
            // Skip masked values (from password fields that weren't changed)
            if (is_string($val) && strpos($val, '***') !== false) continue;
            $stmt = $db->prepare("INSERT INTO chatbot_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $val]);
            $saved++;
        }
    }
    echo json_encode(['success' => true, 'saved' => $saved]);
}

function handleGetSettings($db) {
    $keys = ['qdrant_url', 'qdrant_api_key', 'dataforseo_login', 'dataforseo_password', 'hf_api_key', 'embedding_provider'];
    $settings = [];
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('" . implode("','", $keys) . "')")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($keys as $k) $settings[$k] = $rows[$k] ?? '';
    } catch (Exception $e) {
        foreach ($keys as $k) $settings[$k] = '';
    }
    // Mask sensitive values
    foreach (['qdrant_api_key','dataforseo_password','hf_api_key'] as $s) {
        if (!empty($settings[$s])) $settings[$s] = str_repeat('*', max(0, strlen($settings[$s]) - 4)) . substr($settings[$s], -4);
    }
    echo json_encode(['success' => true, 'data' => $settings]);
}

function handleIndexContent($db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $embedder = new EmbeddingEngine($apiKey);
    $sim = new SemanticSimilarityEngine($embedder);
    
    $blogs = $db->query("SELECT id, title, slug, content, meta_keywords, status FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
    $indexed = 0;
    foreach ($blogs as $blog) {
        $text = $blog['title'] . ' ' . mb_substr(strip_tags($blog['content'] ?? ''), 0, 1000);
        $ok = $sim->indexContent('blog_' . $blog['id'], $text, 'blog', [
            'title' => $blog['title'], 'slug' => $blog['slug'], 'keywords' => $blog['meta_keywords'] ?? ''
        ]);
        if ($ok) $indexed++;
    }
    echo json_encode(['success' => true, 'indexed' => $indexed, 'total' => count($blogs)]);
}

function handleApiTest($input, $db, $apiKey) {
    $endpoint = $input['endpoint'] ?? '';
    if (empty($endpoint)) { echo json_encode(['success' => false, 'message' => 'No endpoint specified']); return; }
    
    $start = microtime(true);
    $result = ['endpoint' => $endpoint, 'status' => 'unknown', 'time_ms' => 0, 'details' => ''];
    
    try {
        switch ($endpoint) {
            case 'scan_blogs':
                $scanner = new GilafTechnicalSeoScanner($db);
                $blogs = $db->query("SELECT id, title, slug, content, meta_keywords, status FROM blogs WHERE status='published' LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
                $result['status'] = 'ok';
                $result['details'] = count($blogs) . ' blog(s) accessible';
                break;
            case 'scan_products':
                $count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
                $result['status'] = 'ok';
                $result['details'] = $count . ' product(s) found';
                break;
            case 'site_stats':
                $scanner = new GilafTechnicalSeoScanner($db);
                $stats = $scanner->getSiteStats();
                $result['status'] = 'ok';
                $result['details'] = 'Stats returned: ' . count($stats) . ' metrics';
                break;
            case 'link_opportunities':
                $blogCount = (int)$db->query("SELECT COUNT(*) FROM blogs WHERE status='published'")->fetchColumn();
                $scanner = new GilafTechnicalSeoScanner($db);
                $result['status'] = $blogCount > 0 ? 'ok' : 'warning';
                $result['details'] = $blogCount . ' published blogs for link analysis';
                break;
            case 'orphan_pages':
                $scanner = new GilafTechnicalSeoScanner($db);
                $orphans = $scanner->findOrphanPages();
                $result['status'] = 'ok';
                $result['details'] = count($orphans['orphans'] ?? []) . ' orphans, ' . count($orphans['weak_pages'] ?? []) . ' weak';
                break;
            case 'orphan_autofix':
                $blogId = $db->query("SELECT id FROM blogs WHERE status='published' LIMIT 1")->fetchColumn();
                if ($blogId) {
                    $scanner = new GilafTechnicalSeoScanner($db);
                    $fix = $scanner->autoFixOrphan((int)$blogId);
                    $result['status'] = $fix['success'] ? 'ok' : 'error';
                    $result['details'] = ($fix['total_outgoing'] ?? 0) . ' outgoing, ' . ($fix['total_incoming'] ?? 0) . ' incoming suggestions';
                } else {
                    $result['status'] = 'skip';
                    $result['details'] = 'No published blogs to test';
                }
                break;
            case 'pre_publish_check':
                $blogId = $db->query("SELECT id FROM blogs WHERE status='published' LIMIT 1")->fetchColumn();
                if ($blogId) {
                    $scanner = new GilafTechnicalSeoScanner($db);
                    $check = $scanner->prePublishCheck((int)$blogId);
                    $result['status'] = 'ok';
                    $result['details'] = ($check['pass'] ? 'PASS' : 'FAIL') . ' — ' . ($check['pass_count'] ?? 0) . '/' . ($check['total_checks'] ?? 0) . ' checks';
                } else {
                    $result['status'] = 'skip';
                    $result['details'] = 'No published blogs to test';
                }
                break;
            case 'connectivity_graph':
                $scanner = new GilafTechnicalSeoScanner($db);
                $graph = $scanner->buildConnectivityGraph();
                $result['status'] = 'ok';
                $result['details'] = count($graph['nodes'] ?? []) . ' nodes, ' . count($graph['edges'] ?? []) . ' edges';
                break;
            case 'analyze_blog':
                $blogId = $db->query("SELECT id FROM blogs WHERE status='published' LIMIT 1")->fetchColumn();
                if ($blogId) {
                    $blog = $db->prepare("SELECT id, title, slug, content, meta_title, meta_description, meta_keywords, excerpt FROM blogs WHERE id = ?");
                    $blog->execute([$blogId]);
                    $blogData = $blog->fetch(PDO::FETCH_ASSOC);
                    $analyzer = new GilafSeoAnalyzer($db);
                    $a = $analyzer->analyzeContent([
                        'content' => $blogData['content'] ?? '',
                        'title' => $blogData['title'] ?? '',
                        'meta_title' => $blogData['meta_title'] ?? '',
                        'meta_description' => $blogData['meta_description'] ?? '',
                        'slug' => $blogData['slug'] ?? '',
                        'excerpt' => $blogData['excerpt'] ?? '',
                        'focus_keyword' => $blogData['meta_keywords'] ?? ''
                    ]);
                    $score = $analyzer->calculateOverallScore($a);
                    $result['status'] = 'ok';
                    $result['details'] = 'Score: ' . $score . '/100';
                } else {
                    $result['status'] = 'skip';
                    $result['details'] = 'No published blogs to test';
                }
                break;
            case 'bulk_analyze':
                $count = $db->query("SELECT COUNT(*) FROM blogs WHERE status='published'")->fetchColumn();
                $result['status'] = 'ok';
                $result['details'] = $count . ' blogs ready for bulk analysis';
                break;
            case 'ai_semantic':
                $result['status'] = empty($apiKey) ? 'error' : 'ok';
                $result['details'] = empty($apiKey) ? 'No AI API key configured' : 'AI key present (' . strlen($apiKey) . ' chars)';
                break;
            case 'v3_entity_extract':
            case 'v3_knowledge_graph':
            case 'v3_search_intent':
            case 'v3_semantic_links':
            case 'v3_semantic_duplicates':
            case 'v3_topical_authority':
            case 'v3_content_gaps':
            case 'v3_content_opportunities':
            case 'v3_ai_search_optimize':
            case 'v3_generate_snippets':
            case 'v3_ranking_predict':
                $result['status'] = empty($apiKey) ? 'warning' : 'ok';
                $result['details'] = empty($apiKey) ? 'Requires AI API key' : 'AI-powered endpoint ready';
                break;
            case 'v3_serp_analyze':
            case 'v3_keyword_data':
                $result['status'] = 'ok';
                $result['details'] = 'Data endpoint accessible';
                break;
            case 'v3_api_stats':
                $result['status'] = 'ok';
                $result['details'] = 'Stats endpoint accessible';
                break;
            case 'db_connection':
                $db->query("SELECT 1");
                $result['status'] = 'ok';
                $result['details'] = 'Database connection healthy';
                break;
            default:
                $result['status'] = 'error';
                $result['details'] = 'Unknown endpoint: ' . $endpoint;
        }
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['details'] = $e->getMessage();
    }
    
    $result['time_ms'] = round((microtime(true) - $start) * 1000);
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleAiPing($db) {
    $config = [
        'ai_provider' => 'gemini',
        'api_key' => '',
        'ai_model' => 'gemini-2.0-flash',
        'ai_enabled' => '1',
        'temperature' => '0.7',
        'max_tokens' => '500',
        'response_timeout' => '30',
    ];
    
    // Auto-fetch AI config from chatbot_settings
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','api_key','ai_model','ai_enabled','temperature','max_tokens','response_timeout')")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as $k => $v) {
            if (isset($config[$k])) $config[$k] = $v;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Cannot read chatbot_settings: ' . $e->getMessage()]);
        return;
    }
    
    $provider = $config['ai_provider'];
    $apiKey = $config['api_key'];
    $model = $config['ai_model'];
    $timeout = intval($config['response_timeout']) ?: 30;
    
    // Mask key for display
    $maskedKey = empty($apiKey) ? '(not set)' : substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
    
    $info = [
        'provider' => $provider,
        'model' => $model,
        'ai_enabled' => $config['ai_enabled'] === '1',
        'masked_key' => $maskedKey,
        'temperature' => floatval($config['temperature']),
        'max_tokens' => intval($config['max_tokens']),
        'timeout' => $timeout,
        'ping_status' => 'unknown',
        'ping_time_ms' => 0,
        'ping_response' => '',
        'ping_error' => '',
    ];
    
    if (empty($apiKey)) {
        $info['ping_status'] = 'no_key';
        $info['ping_error'] = 'No API key configured in Chatbot Settings';
        echo json_encode(['success' => true, 'data' => $info]);
        return;
    }
    
    if ($config['ai_enabled'] !== '1') {
        $info['ping_status'] = 'disabled';
        $info['ping_error'] = 'AI is disabled in Chatbot Settings';
        echo json_encode(['success' => true, 'data' => $info]);
        return;
    }
    
    // Ping the AI
    $testMessage = "Respond with exactly: PONG";
    $start = microtime(true);
    
    if ($provider === 'gemini') {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $requestData = [
            'contents' => [['parts' => [['text' => $testMessage]]]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 20]
        ];
        $headers = ['Content-Type: application/json'];
    } elseif ($provider === 'openai') {
        $apiUrl = "https://api.openai.com/v1/chat/completions";
        $requestData = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $testMessage]],
            'temperature' => 0.1,
            'max_tokens' => 20
        ];
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
    } elseif ($provider === 'claude') {
        $apiUrl = "https://api.anthropic.com/v1/messages";
        $requestData = [
            'model' => $model,
            'max_tokens' => 20,
            'messages' => [['role' => 'user', 'content' => $testMessage]],
        ];
        $headers = ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
    } else {
        $info['ping_status'] = 'error';
        $info['ping_error'] = 'Unknown provider: ' . $provider;
        echo json_encode(['success' => true, 'data' => $info]);
        return;
    }
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, min($timeout, 15));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // SSL settings for XAMPP/Windows
    $caBundle = ini_get('curl.cainfo');
    if (empty($caBundle) || !file_exists($caBundle)) {
        $possibleCerts = [
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        ];
        foreach ($possibleCerts as $cert) {
            if (file_exists($cert)) { curl_setopt($ch, CURLOPT_CAINFO, $cert); break; }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $info['ping_time_ms'] = round((microtime(true) - $start) * 1000);
    
    if ($curlError) {
        $info['ping_status'] = 'error';
        $info['ping_error'] = 'Connection failed: ' . $curlError;
    } elseif ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $errMsg = $decoded['error']['message'] ?? $decoded['error']['type'] ?? ('HTTP ' . $httpCode);
        $info['ping_status'] = 'error';
        $info['ping_error'] = $errMsg;
    } else {
        $decoded = json_decode($response, true);
        $text = '';
        if ($provider === 'gemini') {
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } elseif ($provider === 'openai') {
            $text = $decoded['choices'][0]['message']['content'] ?? '';
        } elseif ($provider === 'claude') {
            $text = $decoded['content'][0]['text'] ?? '';
        }
        $info['ping_status'] = 'ok';
        $info['ping_response'] = trim($text);
    }
    
    echo json_encode(['success' => true, 'data' => $info]);
}

function handleDataForSeoTest($db) {
    // Read credentials directly from DB (not from SeoIntelConfig which may have stale values)
    $login = '';
    $password = '';
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('dataforseo_login','dataforseo_password')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $login = $rows['dataforseo_login'] ?? '';
        $password = $rows['dataforseo_password'] ?? '';
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Cannot read settings: ' . $e->getMessage()]);
        return;
    }
    
    $maskedLogin = $login ? (substr($login, 0, 3) . '***' . substr($login, -4)) : '(not set)';
    
    if (empty($login) || empty($password)) {
        echo json_encode(['success' => true, 'data' => [
            'status' => 'no_credentials',
            'login' => $maskedLogin,
            'error' => 'DataForSEO login or password not configured. Save them in API Configuration above.'
        ]]);
        return;
    }
    
    // Ping DataForSEO API with a lightweight call
    $start = microtime(true);
    $ch = curl_init('https://api.dataforseo.com/v3/appendix/user_data');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => $login . ':' . $password,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $pingMs = round((microtime(true) - $start) * 1000);
    
    if ($curlError) {
        echo json_encode(['success' => true, 'data' => [
            'status' => 'error',
            'login' => $maskedLogin,
            'ping_ms' => $pingMs,
            'error' => 'Connection failed: ' . $curlError
        ]]);
        return;
    }
    
    $decoded = json_decode($resp, true);
    
    if ($httpCode === 200 && !empty($decoded['tasks'][0]['result'][0])) {
        $userData = $decoded['tasks'][0]['result'][0];
        echo json_encode(['success' => true, 'data' => [
            'status' => 'ok',
            'login' => $maskedLogin,
            'ping_ms' => $pingMs,
            'money_balance' => $userData['money']['balance'] ?? 0,
            'money_currency' => $userData['money']['currency'] ?? 'USD',
            'rate_limit' => $userData['rates']['limits']['day']['tasks_per_day'] ?? null,
            'plan' => $userData['login'] ?? $login,
        ]]);
    } else {
        $errMsg = $decoded['status_message'] ?? $decoded['tasks'][0]['status_message'] ?? ('HTTP ' . $httpCode);
        echo json_encode(['success' => true, 'data' => [
            'status' => 'error',
            'login' => $maskedLogin,
            'ping_ms' => $pingMs,
            'error' => $errMsg
        ]]);
    }
}

// ============================================================
// V4 HANDLERS — New SEO Intelligence Features
// ============================================================

function handleWhyNotRanking($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    if (!$blogId) { echo json_encode(['success' => false, 'message' => 'Select a blog']); return; }
    $stmt = $db->prepare("SELECT id, title, slug, content, meta_title, meta_description, meta_keywords, excerpt FROM blogs WHERE id = ?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$blog) { echo json_encode(['success' => false, 'message' => 'Blog not found']); return; }
    $analyzer = new GilafSeoAnalyzer($db);
    $results = $analyzer->analyzeContent([
        'content' => $blog['content'] ?? '',
        'title' => $blog['title'] ?? '',
        'meta_title' => $blog['meta_title'] ?? '',
        'meta_description' => $blog['meta_description'] ?? '',
        'meta_keywords' => $blog['meta_keywords'] ?? '',
        'slug' => $blog['slug'] ?? '',
        'excerpt' => $blog['excerpt'] ?? '',
    ]);
    $results['overall_score'] = $analyzer->calculateOverallScore($results);
    $diagnosis = $analyzer->diagnoseWhyNotRanking($results, $blog['meta_keywords'] ?? '');
    echo json_encode(['success' => true, 'data' => $diagnosis]);
}

function handleProductAnalyze($input, $db) {
    $productId = (int)($input['product_id'] ?? 0);
    if (!$productId) { echo json_encode(['success' => false, 'message' => 'Select a product']); return; }
    try {
        $stmt = $db->prepare("SELECT id, name, slug, seo_title, seo_description, seo_keywords, description, short_description, price, image FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]); return; }
    if (!$product) { echo json_encode(['success' => false, 'message' => 'Product not found']); return; }
    $analyzer = new GilafSeoAnalyzer($db);
    $result = $analyzer->analyzeProductPage($product);
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleAiFix($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $fixType = $input['fix_type'] ?? '';
    $context = $input['context'] ?? [];
    if (empty($fixType)) { echo json_encode(['success' => false, 'message' => 'Fix type required']); return; }
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->generateAiFix($fixType, $context);
    echo json_encode(['success' => (bool)$result, 'data' => $result, 'message' => $result ? '' : 'AI fix generation failed']);
}

function handleGenerateSchema($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    $type = $input['schema_type'] ?? 'article';
    $contentId = $input['content_id'] ?? '';
    
    // Fetch real data from DB based on content selection
    $data = [];
    if (strpos($contentId, 'blog_') === 0) {
        $blogId = (int)str_replace('blog_', '', $contentId);
        $stmt = $db->prepare("SELECT b.*, c.name as category_name, c.slug as category_slug FROM blogs b LEFT JOIN blog_categories c ON c.id = b.category_id WHERE b.id = ?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$blog) { echo json_encode(['success' => false, 'message' => 'Blog not found']); return; }
        
        $blogUrl = 'https://gilafstore.com/blog/' . ($blog['slug'] ?? '');
        $blogImage = $blog['featured_image'] ? 'https://gilafstore.com/' . ltrim($blog['featured_image'], '/') : '';
        
        $data = [
            'title' => $blog['title'] ?? '',
            'description' => $blog['meta_description'] ?: ($blog['excerpt'] ?? ''),
            'author' => $blog['author_name'] ?: 'Gilaf Store',
            'date' => $blog['publish_date'] ?? date('Y-m-d'),
            'modified' => $blog['updated_at'] ?? '',
            'image' => $blogImage,
            'url' => $blogUrl,
            'keywords' => $blog['meta_keywords'] ?? '',
            'category' => $blog['category_name'] ?? '',
            'word_count' => str_word_count(strip_tags($blog['content'] ?? '')),
            'excerpt' => $blog['excerpt'] ?? ''
        ];
        
        // Fetch FAQs if type is faq
        if ($type === 'faq') {
            try {
                $faqStmt = $db->prepare("SELECT question, answer FROM blog_faqs WHERE blog_id = ? ORDER BY sort_order");
                $faqStmt->execute([$blogId]);
                $data['faqs'] = $faqStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { $data['faqs'] = []; }
        }
        
        // Auto-detect: if no type specified or type is 'auto', determine from content
        if ($type === 'auto') {
            if (!empty($data['faqs'])) $type = 'faq';
            else $type = 'article';
        }
        
    } elseif (strpos($contentId, 'prod_') === 0) {
        $prodId = (int)str_replace('prod_', '', $contentId);
        $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?");
        $stmt->execute([$prodId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) { echo json_encode(['success' => false, 'message' => 'Product not found']); return; }
        
        $prodUrl = 'https://gilafstore.com/product/' . ($prod['slug'] ?? $prod['id']);
        $prodImage = '';
        if (!empty($prod['image'])) $prodImage = 'https://gilafstore.com/' . ltrim($prod['image'], '/');
        
        $data = [
            'name' => $prod['name'] ?? '',
            'description' => $prod['description'] ?? '',
            'price' => $prod['price'] ?? 0,
            'image' => $prodImage,
            'url' => $prodUrl,
            'sku' => $prod['sku'] ?? ('GILAF-' . $prodId),
            'category' => $prod['category_name'] ?? '',
            'brand' => 'Gilaf Store',
            'availability' => ($prod['stock'] ?? 0) > 0 ? 'InStock' : 'OutOfStock'
        ];
        
        if ($type === 'auto') $type = 'product';
    } else {
        // Organization or generic
        $data = [
            'name' => 'Gilaf Store',
            'url' => 'https://gilafstore.com',
            'description' => 'Authentic Kashmiri products — saffron, dry fruits, honey, pashmina and more.',
            'logo' => 'https://gilafstore.com/assets/images/logo.png'
        ];
        if ($type === 'auto') $type = 'organization';
    }
    
    $ai = new GilafAiSeoHelper($apiKey, $db);
    $result = $ai->generateSchema($type, $data);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Schema generation failed — AI returned empty response']);
        return;
    }
    // Include content_id and detected type in response for Auto Apply
    $result['content_id'] = $contentId;
    $result['detected_type'] = $type;
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleValidateSchema($input) {
    $schemaJson = $input['schema'] ?? null;
    if (!$schemaJson) { echo json_encode(['success' => false, 'message' => 'No schema provided']); return; }
    
    // If string, parse it
    if (is_string($schemaJson)) {
        $schemaJson = json_decode($schemaJson, true);
        if (!$schemaJson) {
            echo json_encode(['success' => true, 'data' => [
                'valid' => false,
                'errors' => ['Invalid JSON syntax — could not parse the schema'],
                'warnings' => [],
                'rich_result_eligible' => false,
                'score' => 0
            ]]);
            return;
        }
    }
    
    $errors = [];
    $warnings = [];
    $score = 100;
    
    // Check @context
    if (empty($schemaJson['@context'])) { $errors[] = 'Missing @context (should be "https://schema.org")'; $score -= 20; }
    elseif ($schemaJson['@context'] !== 'https://schema.org') { $warnings[] = '@context should be "https://schema.org"'; $score -= 5; }
    
    // Check @type
    if (empty($schemaJson['@type'])) { $errors[] = 'Missing @type'; $score -= 20; }
    
    $type = $schemaJson['@type'] ?? '';
    
    // Type-specific validation
    if (in_array($type, ['Article', 'BlogPosting', 'NewsArticle'])) {
        $required = ['headline', 'author', 'datePublished', 'image'];
        $recommended = ['description', 'dateModified', 'publisher', 'mainEntityOfPage', 'wordCount'];
        foreach ($required as $f) {
            if (empty($schemaJson[$f])) { $errors[] = "Missing required field: {$f}"; $score -= 10; }
        }
        foreach ($recommended as $f) {
            if (empty($schemaJson[$f])) { $warnings[] = "Missing recommended field: {$f}"; $score -= 3; }
        }
        if (!empty($schemaJson['headline']) && mb_strlen($schemaJson['headline']) > 110) {
            $warnings[] = 'Headline exceeds 110 characters (may be truncated in search)'; $score -= 5;
        }
    } elseif ($type === 'Product') {
        $required = ['name', 'image', 'offers'];
        $recommended = ['description', 'brand', 'sku', 'aggregateRating', 'review'];
        foreach ($required as $f) {
            if (empty($schemaJson[$f])) { $errors[] = "Missing required field: {$f}"; $score -= 10; }
        }
        foreach ($recommended as $f) {
            if (empty($schemaJson[$f])) { $warnings[] = "Missing recommended field: {$f}"; $score -= 3; }
        }
        // Check offers structure
        if (!empty($schemaJson['offers'])) {
            $offers = $schemaJson['offers'];
            if (is_array($offers) && !empty($offers['@type'])) {
                if (empty($offers['price'])) { $errors[] = 'Offers missing price'; $score -= 10; }
                if (empty($offers['priceCurrency'])) { $warnings[] = 'Offers missing priceCurrency'; $score -= 5; }
                if (empty($offers['availability'])) { $warnings[] = 'Offers missing availability'; $score -= 5; }
            }
        }
    } elseif ($type === 'FAQPage') {
        if (empty($schemaJson['mainEntity']) || !is_array($schemaJson['mainEntity'])) {
            $errors[] = 'FAQPage requires mainEntity array of Questions'; $score -= 20;
        } else {
            foreach ($schemaJson['mainEntity'] as $i => $q) {
                if (empty($q['@type']) || $q['@type'] !== 'Question') { $warnings[] = "FAQ item {$i}: missing @type Question"; $score -= 3; }
                if (empty($q['name'])) { $errors[] = "FAQ item {$i}: missing question text (name)"; $score -= 5; }
                if (empty($q['acceptedAnswer']['text'])) { $errors[] = "FAQ item {$i}: missing answer text"; $score -= 5; }
            }
        }
    } elseif ($type === 'BreadcrumbList') {
        if (empty($schemaJson['itemListElement']) || !is_array($schemaJson['itemListElement'])) {
            $errors[] = 'BreadcrumbList requires itemListElement array'; $score -= 20;
        }
    } elseif ($type === 'Organization') {
        $required = ['name', 'url'];
        $recommended = ['logo', 'sameAs', 'description'];
        foreach ($required as $f) {
            if (empty($schemaJson[$f])) { $errors[] = "Missing required field: {$f}"; $score -= 10; }
        }
        foreach ($recommended as $f) {
            if (empty($schemaJson[$f])) { $warnings[] = "Missing recommended field: {$f}"; $score -= 3; }
        }
    } elseif ($type === 'HowTo') {
        if (empty($schemaJson['name'])) { $errors[] = 'Missing required field: name'; $score -= 10; }
        if (empty($schemaJson['step']) || !is_array($schemaJson['step'])) { $errors[] = 'HowTo requires step array'; $score -= 20; }
    }
    
    $score = max(0, $score);
    $richResultEligible = empty($errors) && $score >= 70;
    $richResultTypes = ['Article', 'BlogPosting', 'Product', 'FAQPage', 'HowTo', 'BreadcrumbList', 'Recipe', 'Review'];
    $isRichType = in_array($type, $richResultTypes);
    
    echo json_encode(['success' => true, 'data' => [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
        'rich_result_eligible' => $richResultEligible && $isRichType,
        'rich_result_type' => $isRichType ? $type : 'Not a rich result type',
        'score' => $score
    ]]);
}

function handleApplySchema($input, $db) {
    $contentId = $input['content_id'] ?? '';
    $schemaJson = $input['schema'] ?? null;
    if (empty($contentId) || !$schemaJson) {
        echo json_encode(['success' => false, 'message' => 'Content ID and schema required']);
        return;
    }
    
    $schemaStr = is_string($schemaJson) ? $schemaJson : json_encode($schemaJson, JSON_UNESCAPED_SLASHES);
    
    // Ensure custom_schema column exists
    if (strpos($contentId, 'blog_') === 0) {
        $blogId = (int)str_replace('blog_', '', $contentId);
        try {
            // Add column if not exists
            try { $db->exec("ALTER TABLE blogs ADD COLUMN custom_schema LONGTEXT NULL"); } catch (Exception $e) {}
            $stmt = $db->prepare("UPDATE blogs SET custom_schema = ? WHERE id = ?");
            $stmt->execute([$schemaStr, $blogId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Schema applied to blog successfully. It will appear on the blog page automatically.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Blog not found or no changes made']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif (strpos($contentId, 'prod_') === 0) {
        $prodId = (int)str_replace('prod_', '', $contentId);
        try {
            try { $db->exec("ALTER TABLE products ADD COLUMN custom_schema LONGTEXT NULL"); } catch (Exception $e) {}
            $stmt = $db->prepare("UPDATE products SET custom_schema = ? WHERE id = ?");
            $stmt->execute([$schemaStr, $prodId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Schema applied to product successfully. It will appear on the product page automatically.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found or no changes made']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid content ID format']);
    }
}

function handleCheckSchema($input, $db) {
    $contentId = $input['content_id'] ?? '';
    if (empty($contentId)) { echo json_encode(['success' => true, 'data' => ['has_schema' => false]]); return; }
    
    $hasSchema = false;
    $schemaData = null;
    $schemaType = '';
    
    try {
        if (strpos($contentId, 'blog_') === 0) {
            $blogId = (int)str_replace('blog_', '', $contentId);
            $stmt = $db->prepare("SELECT custom_schema FROM blogs WHERE id = ?");
            $stmt->execute([$blogId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['custom_schema'])) {
                $hasSchema = true;
                $schemaData = json_decode($row['custom_schema'], true);
                $schemaType = $schemaData['@type'] ?? 'Unknown';
            }
        } elseif (strpos($contentId, 'prod_') === 0) {
            $prodId = (int)str_replace('prod_', '', $contentId);
            $stmt = $db->prepare("SELECT custom_schema FROM products WHERE id = ?");
            $stmt->execute([$prodId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['custom_schema'])) {
                $hasSchema = true;
                $schemaData = json_decode($row['custom_schema'], true);
                $schemaType = $schemaData['@type'] ?? 'Unknown';
            }
        }
    } catch (Exception $e) {
        // Column may not exist yet — means no schema applied
    }
    
    echo json_encode(['success' => true, 'data' => [
        'has_schema' => $hasSchema,
        'schema_type' => $schemaType,
        'schema' => $schemaData
    ]]);
}

function handlePageSpeed($input) {
    $url = trim($input['url'] ?? '');
    if (empty($url)) { echo json_encode(['success' => false, 'message' => 'URL required']); return; }
    if (!preg_match('/^https?:\/\//', $url)) $url = 'https://' . $url;
    $strategy = $input['strategy'] ?? 'mobile';
    $engine = new PageSpeedEngine();
    $result = $engine->analyze($url, $strategy);
    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'message' => 'PageSpeed error: ' . $result['error']]);
    } else {
        echo json_encode(['success' => true, 'data' => $result]);
    }
}

function handleSerpCompare($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    $keyword = trim($input['keyword'] ?? '');
    if (!$blogId && empty($keyword)) { echo json_encode(['success' => false, 'message' => 'Blog or keyword required']); return; }
    $yourPage = ['title' => '', 'content' => '', 'meta_description' => '', 'keyword' => $keyword];
    if ($blogId) {
        $stmt = $db->prepare("SELECT title, content, meta_description, meta_keywords FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($blog) {
            $yourPage['title'] = $blog['title'];
            $yourPage['content'] = $blog['content'];
            $yourPage['meta_description'] = $blog['meta_description'];
            if (empty($keyword)) $keyword = $blog['meta_keywords'] ?: $blog['title'];
            $yourPage['keyword'] = $keyword;
        }
    }
    $serpEngine = new DataForSeoEngine($db);
    if (!$serpEngine->isConfigured()) { echo json_encode(['success' => false, 'message' => 'DataForSEO not configured']); return; }
    $serpResult = $serpEngine->analyzeCompetitors($keyword);
    if (empty($serpResult)) {
        echo json_encode(['success' => false, 'message' => 'SERP data unavailable: ' . ($serpEngine->lastError ?: 'No results')]);
        return;
    }
    $compEngine = new SerpComparisonEngine($db);
    $comparison = $compEngine->compareWithCompetitors($yourPage, $serpResult);
    echo json_encode(['success' => true, 'data' => $comparison]);
}

function handleCtrAnalyze($input) {
    $title = $input['title'] ?? '';
    $metaDesc = $input['meta_description'] ?? '';
    if (empty($title)) { echo json_encode(['success' => false, 'message' => 'Title required']); return; }
    $analyzer = new GilafSeoAnalyzer();
    $result = $analyzer->analyzeCTR($title, $metaDesc);
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleQdrantTest($db) {
    // Load Qdrant settings from DB
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('seo_qdrant_url','seo_qdrant_key')")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) { $rows = []; }
    $url = trim($rows['seo_qdrant_url'] ?? '');
    $key = trim($rows['seo_qdrant_key'] ?? '');
    
    if (empty($url)) {
        echo json_encode(['success' => true, 'data' => ['status' => 'no_url', 'error' => 'No Qdrant URL configured. Save settings first.']]);
        return;
    }
    
    $start = microtime(true);
    $endpoint = rtrim($url, '/') . '/collections';
    $ch = curl_init($endpoint);
    $headers = ['Content-Type: application/json'];
    if (!empty($key)) $headers[] = 'api-key: ' . $key;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $pingMs = round((microtime(true) - $start) * 1000);
    
    if ($curlError) {
        echo json_encode(['success' => true, 'data' => ['status' => 'error', 'url' => $url, 'ping_ms' => $pingMs, 'error' => 'Connection failed: ' . $curlError]]);
        return;
    }
    
    $decoded = json_decode($resp, true);
    
    if ($httpCode === 200 && isset($decoded['result'])) {
        $collections = is_array($decoded['result']['collections'] ?? null) ? count($decoded['result']['collections']) : 0;
        // Try to get version
        $version = '—';
        $chV = curl_init(rtrim($url, '/') . '/');
        $headersV = ['Content-Type: application/json'];
        if (!empty($key)) $headersV[] = 'api-key: ' . $key;
        curl_setopt_array($chV, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headersV, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => false]);
        $respV = curl_exec($chV); curl_close($chV);
        $decV = json_decode($respV, true);
        if (!empty($decV['version'])) $version = $decV['version'];
        elseif (!empty($decV['result']['version'])) $version = $decV['result']['version'];
        
        echo json_encode(['success' => true, 'data' => [
            'status' => 'ok',
            'url' => $url,
            'ping_ms' => $pingMs,
            'version' => $version,
            'collections' => $collections
        ]]);
    } else {
        $errMsg = $decoded['status']['error'] ?? $decoded['message'] ?? ('HTTP ' . $httpCode);
        echo json_encode(['success' => true, 'data' => ['status' => 'error', 'url' => $url, 'ping_ms' => $pingMs, 'error' => $errMsg]]);
    }
}

function handleHuggingFaceTest($db) {
    // Load HF key from DB
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('seo_hf_key')")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) { $rows = []; }
    $hfKey = trim($rows['seo_hf_key'] ?? '');
    
    if (empty($hfKey)) {
        echo json_encode(['success' => true, 'data' => ['status' => 'no_key', 'error' => 'No HuggingFace API key configured. Save settings first.']]);
        return;
    }
    
    $model = 'sentence-transformers/all-MiniLM-L6-v2';
    $start = microtime(true);
    $apiUrl = "https://api-inference.huggingface.co/pipeline/feature-extraction/{$model}";
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $hfKey],
        CURLOPT_POSTFIELDS => json_encode(['inputs' => 'test connection', 'options' => ['wait_for_model' => true]]),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $pingMs = round((microtime(true) - $start) * 1000);
    
    if ($curlError) {
        echo json_encode(['success' => true, 'data' => ['status' => 'error', 'model' => $model, 'ping_ms' => $pingMs, 'error' => 'Connection failed: ' . $curlError]]);
        return;
    }
    
    $decoded = json_decode($resp, true);
    
    if ($httpCode === 200 && is_array($decoded)) {
        $embeddingDim = is_array($decoded[0] ?? null) ? count($decoded[0]) : (is_array($decoded) ? count($decoded) : '—');
        echo json_encode(['success' => true, 'data' => [
            'status' => 'ok',
            'model' => $model,
            'ping_ms' => $pingMs,
            'embedding_dim' => $embeddingDim,
            'masked_key' => substr($hfKey, 0, 6) . '...' . substr($hfKey, -4)
        ]]);
    } else {
        $errMsg = $decoded['error'] ?? $decoded['message'] ?? ('HTTP ' . $httpCode);
        echo json_encode(['success' => true, 'data' => ['status' => 'error', 'model' => $model, 'ping_ms' => $pingMs, 'error' => $errMsg]]);
    }
}

// ============================================================
// V5: DETAILED SEO ANALYSIS HANDLER
// ============================================================
function handleDetailedAnalysis($input, $db) {
    $blogId = (int)($input['blog_id'] ?? 0);
    if (!$blogId) {
        echo json_encode(['success' => false, 'message' => 'Blog ID required']);
        return;
    }

    $stmt = $db->prepare("SELECT b.*, c.name as category_name FROM blogs b LEFT JOIN blog_categories c ON c.id = b.category_id WHERE b.id = ?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$blog) {
        echo json_encode(['success' => false, 'message' => 'Blog not found']);
        return;
    }

    $content = $blog['content'] ?? '';
    $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
    $content = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $content);
    $content = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $content);
    $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);

    $analyzer = new DetailedSeoAnalyzer();
    $results  = $analyzer->analyze([
        'content'          => $content,
        'title'            => $blog['title'],
        'meta_title'       => $blog['meta_title'],
        'meta_description' => $blog['meta_description'],
        'slug'             => $blog['slug'],
        'excerpt'          => $blog['excerpt'],
        'focus_keyword'    => $blog['meta_keywords'],
        'url'              => '/blog/' . $blog['slug'],
    ]);

    $results['blog'] = [
        'id'       => $blog['id'],
        'title'    => $blog['title'],
        'slug'     => $blog['slug'],
        'category' => $blog['category_name'],
    ];

    echo json_encode(['success' => true, 'data' => $results]);
}

// ============================================================
// V5 SMART SEO AUTO FIX ENGINE HANDLERS
// ============================================================

function handleFixPreview($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    
    $blogId = intval($input['blog_id'] ?? 0);
    $issue = $input['issue'] ?? null;
    $mode = $input['mode'] ?? 'smart';
    
    if (!$blogId || !$issue) {
        echo json_encode(['success' => false, 'message' => 'blog_id and issue are required']);
        return;
    }
    
    // Fetch blog content
    $stmt = $db->prepare("SELECT b.*, c.name as category_name FROM blogs b LEFT JOIN blog_categories c ON b.category_id=c.id WHERE b.id=?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$blog) { echo json_encode(['success' => false, 'message' => 'Blog not found']); return; }
    
    $content = $blog['content'] ?? '';
    $keyword = $blog['meta_keywords'] ?? $blog['title'] ?? '';
    
    $engine = new SeoAutoFixEngine($db, $apiKey);
    $result = $engine->generateFixPreview($issue, $content, $keyword, $mode);
    
    echo json_encode($result);
}

function handleApplyFix($input, $db, $apiKey) {
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    
    $blogId = intval($input['blog_id'] ?? 0);
    $originalText = $input['original_text'] ?? '';
    $fixedText = $input['fixed_text'] ?? '';
    $issue = $input['issue'] ?? [];
    
    if (!$blogId || empty($originalText) || empty($fixedText)) {
        echo json_encode(['success' => false, 'message' => 'blog_id, original_text, and fixed_text are required']);
        return;
    }
    
    // Fetch current blog content
    $stmt = $db->prepare("SELECT content FROM blogs WHERE id=?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$blog) { echo json_encode(['success' => false, 'message' => 'Blog not found']); return; }
    
    $content = $blog['content'] ?? '';
    $engine = new SeoAutoFixEngine($db, $apiKey);
    $result = $engine->applyFix($content, $originalText, $fixedText, $issue);
    
    if ($result['success']) {
        // Save updated content to database
        $stmt = $db->prepare("UPDATE blogs SET content=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$result['content'], $blogId]);
        
        // Save fix log
        $engine->saveFixLog($blogId);
        
        $result['message'] = 'Fix applied successfully';
        unset($result['content']); // Don't return full content in response
    }
    
    echo json_encode($result);
}

function handleBulkFix($input, $db, $apiKey) {
    @set_time_limit(300);
    if (empty($apiKey)) { echo json_encode(['success' => false, 'message' => 'AI API key not configured']); return; }
    
    $blogId = intval($input['blog_id'] ?? 0);
    $issues = $input['issues'] ?? [];
    $category = $input['category'] ?? '';
    $mode = $input['mode'] ?? 'smart';
    
    if (!$blogId || empty($issues)) {
        echo json_encode(['success' => false, 'message' => 'blog_id and issues are required']);
        return;
    }
    
    // Fetch blog
    $stmt = $db->prepare("SELECT content, meta_keywords, title FROM blogs WHERE id=?");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$blog) { echo json_encode(['success' => false, 'message' => 'Blog not found']); return; }
    
    $content = $blog['content'] ?? '';
    $keyword = $blog['meta_keywords'] ?? $blog['title'] ?? '';
    
    $engine = new SeoAutoFixEngine($db, $apiKey);
    $result = $engine->bulkFix($issues, $content, $keyword, $category, $mode);
    
    if ($result['success'] && $result['fixed'] > 0) {
        // Save updated content
        $stmt = $db->prepare("UPDATE blogs SET content=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$result['content'], $blogId]);
        $engine->saveFixLog($blogId);
        unset($result['content']);
    }
    
    echo json_encode($result);
}

function handleUndoFix($input, $db) {
    $blogId = intval($input['blog_id'] ?? 0);
    $fixIndex = intval($input['fix_index'] ?? -1);
    
    if (!$blogId) { echo json_encode(['success' => false, 'message' => 'blog_id is required']); return; }
    
    // Get the fix log
    try {
        $stmt = $db->prepare("SELECT id, fix_data FROM seo_fix_logs WHERE blog_id=? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$blogId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log) { echo json_encode(['success' => false, 'message' => 'No fix log found']); return; }
        
        $fixes = json_decode($log['fix_data'], true);
        if (empty($fixes)) { echo json_encode(['success' => false, 'message' => 'Empty fix log']); return; }
        
        // Reverse the last fix (or specific index)
        $targetFix = ($fixIndex >= 0 && $fixIndex < count($fixes)) ? $fixes[$fixIndex] : end($fixes);
        
        $stmt = $db->prepare("SELECT content FROM blogs WHERE id=?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$blog) { echo json_encode(['success' => false, 'message' => 'Blog not found']); return; }
        
        $content = $blog['content'] ?? '';
        $pos = mb_strpos($content, $targetFix['fixed']);
        
        if ($pos === false) {
            echo json_encode(['success' => false, 'message' => 'Fixed text not found in current content — may have been modified']);
            return;
        }
        
        $restored = mb_substr($content, 0, $pos) . $targetFix['original'] . mb_substr($content, $pos + mb_strlen($targetFix['fixed']));
        
        $stmt = $db->prepare("UPDATE blogs SET content=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$restored, $blogId]);
        
        echo json_encode(['success' => true, 'message' => 'Fix undone successfully', 'undone_issue' => $targetFix['issue_title'] ?? 'Issue']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Undo error: ' . $e->getMessage()]);
    }
}

function handleGetFixLog($input, $db) {
    $blogId = intval($input['blog_id'] ?? 0);
    if (!$blogId) { echo json_encode(['success' => false, 'message' => 'blog_id is required']); return; }
    
    try {
        $stmt = $db->prepare("SELECT id, fix_data, created_at FROM seo_fix_logs WHERE blog_id=? ORDER BY id DESC LIMIT 20");
        $stmt->execute([$blogId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted = [];
        foreach ($logs as $log) {
            $fixes = json_decode($log['fix_data'], true) ?: [];
            $formatted[] = [
                'id' => $log['id'],
                'timestamp' => $log['created_at'],
                'fixes' => $fixes
            ];
        }
        
        echo json_encode(['success' => true, 'logs' => $formatted]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
