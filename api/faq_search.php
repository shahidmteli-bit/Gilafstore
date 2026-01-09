<?php
/**
 * FAQ Search API for Chatbot Integration
 * This endpoint provides intelligent FAQ matching for chatbot queries
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/../includes/db_connect.php';

// Get the search query
$query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_POST['q']) ? trim($_POST['q']) : '');
$sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : (isset($_POST['session_id']) ? session_id() : null);

// Validate input
if (empty($query)) {
    echo json_encode([
        'success' => false,
        'error' => 'Query parameter is required',
        'message' => 'Please provide a search query'
    ]);
    exit;
}

// Minimum query length
if (strlen($query) < 3) {
    echo json_encode([
        'success' => false,
        'error' => 'Query too short',
        'message' => 'Please enter at least 3 characters'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Prepare search terms
    $searchTerm = strtolower($query);
    $searchWords = array_filter(explode(' ', $searchTerm), function($word) {
        return strlen($word) >= 3; // Only consider words with 3+ characters
    });
    
    // Build dynamic search query with relevance scoring
    $sql = "SELECT 
                f.id,
                f.question,
                f.answer,
                f.category,
                f.keywords,
                f.priority,
                f.view_count,
                f.helpful_count,
                CASE
                    -- Exact match in question (highest priority)
                    WHEN LOWER(f.question) = LOWER(:exact_query) THEN 100
                    
                    -- Question starts with query
                    WHEN LOWER(f.question) LIKE CONCAT(LOWER(:query), '%') THEN 90
                    
                    -- Question contains exact query
                    WHEN LOWER(f.question) LIKE CONCAT('%', LOWER(:query), '%') THEN 80
                    
                    -- Answer contains exact query
                    WHEN LOWER(f.answer) LIKE CONCAT('%', LOWER(:query), '%') THEN 70
                    
                    -- Keywords exact match
                    WHEN LOWER(f.keywords) LIKE CONCAT('%', LOWER(:query), '%') THEN 75
                    
                    -- Category match
                    WHEN LOWER(f.category) LIKE CONCAT('%', LOWER(:query), '%') THEN 60
                    
                    -- Partial word matches
                    ELSE 50
                END as relevance_score
            FROM faqs f
            WHERE f.is_active = 1
            AND (
                LOWER(f.question) LIKE CONCAT('%', LOWER(:query), '%')
                OR LOWER(f.answer) LIKE CONCAT('%', LOWER(:query), '%')
                OR LOWER(f.keywords) LIKE CONCAT('%', LOWER(:query), '%')
                OR LOWER(f.category) LIKE CONCAT('%', LOWER(:query), '%')";
    
    // Add individual word matching for better results
    $wordConditions = [];
    $params = [
        ':exact_query' => $searchTerm,
        ':query' => $searchTerm
    ];
    
    foreach ($searchWords as $index => $word) {
        $paramName = ":word{$index}";
        $wordConditions[] = "LOWER(f.question) LIKE CONCAT('%', {$paramName}, '%')";
        $wordConditions[] = "LOWER(f.answer) LIKE CONCAT('%', {$paramName}, '%')";
        $wordConditions[] = "LOWER(f.keywords) LIKE CONCAT('%', {$paramName}, '%')";
        $params[$paramName] = $word;
    }
    
    if (!empty($wordConditions)) {
        $sql .= " OR " . implode(' OR ', $wordConditions);
    }
    
    $sql .= ")
            ORDER BY 
                relevance_score DESC,
                f.priority DESC,
                f.helpful_count DESC,
                f.view_count DESC,
                f.id ASC
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($faqs)) {
        // No FAQs found
        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => "I couldn't find an exact answer to your question. However, I can help you with:\n\n" .
                        "• Product information and authenticity\n" .
                        "• Shipping and delivery tracking\n" .
                        "• Returns and refunds\n" .
                        "• Payment methods\n" .
                        "• Order management\n\n" .
                        "You can also create a support ticket, and our team will assist you within 24 hours.",
            'suggestions' => [
                'How can I verify product authenticity?',
                'What is your return policy?',
                'How long does shipping take?',
                'What payment methods do you accept?'
            ]
        ]);
        exit;
    }
    
    // Get the best match
    $bestMatch = $faqs[0];
    $relevanceScore = $bestMatch['relevance_score'];
    
    // Update view count
    $updateStmt = $pdo->prepare("UPDATE faqs SET view_count = view_count + 1 WHERE id = ?");
    $updateStmt->execute([$bestMatch['id']]);
    
    // Log analytics
    $matchedKeywords = [];
    if (!empty($bestMatch['keywords'])) {
        $keywords = array_map('trim', explode(',', $bestMatch['keywords']));
        foreach ($keywords as $keyword) {
            if (stripos($searchTerm, strtolower($keyword)) !== false) {
                $matchedKeywords[] = $keyword;
            }
        }
    }
    
    $analyticsStmt = $pdo->prepare(
        "INSERT INTO faq_analytics (faq_id, user_query, matched_keywords, relevance_score, session_id) 
         VALUES (?, ?, ?, ?, ?)"
    );
    $analyticsStmt->execute([
        $bestMatch['id'],
        $query,
        implode(', ', $matchedKeywords),
        $relevanceScore,
        $sessionId
    ]);
    
    // Prepare related FAQs
    $relatedFaqs = [];
    for ($i = 1; $i < count($faqs) && $i < 3; $i++) {
        $relatedFaqs[] = [
            'id' => $faqs[$i]['id'],
            'question' => $faqs[$i]['question'],
            'category' => $faqs[$i]['category']
        ];
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'found' => true,
        'faq' => [
            'id' => $bestMatch['id'],
            'question' => $bestMatch['question'],
            'answer' => $bestMatch['answer'],
            'category' => $bestMatch['category'],
            'relevance_score' => $relevanceScore
        ],
        'related_faqs' => $relatedFaqs,
        'confidence' => $relevanceScore >= 80 ? 'high' : ($relevanceScore >= 60 ? 'medium' : 'low'),
        'message' => $relevanceScore >= 70 ? null : 'This answer might be related to your question. If you need more specific help, please create a support ticket.'
    ]);
    
} catch (PDOException $e) {
    error_log("FAQ Search Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'An error occurred while searching FAQs. Please try again later.'
    ]);
}
