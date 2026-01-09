<?php
/**
 * Live Search Autocomplete API Endpoint
 * Returns product suggestions as user types
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Get search query from request
$query = trim($_GET['q'] ?? '');

// Return empty array if query is too short
if (strlen($query) < 1) {
    echo json_encode(['products' => []]);
    exit;
}

try {
    $db = get_db_connection();
    
    // Prepare search term variations for intelligent matching
    $searchTerm = '%' . $query . '%';  // Contains anywhere
    $startsWith = $query . '%';        // Starts with
    $wordStart = '% ' . $query . '%';  // Word boundary match
    
    // Check if optional columns exist
    $hasSku = false;
    $hasKeywords = false;
    
    try {
        $checkSku = $db->query("SHOW COLUMNS FROM products LIKE 'sku'");
        $hasSku = $checkSku->rowCount() > 0;
    } catch (PDOException $e) {}
    
    try {
        $checkKeywords = $db->query("SHOW COLUMNS FROM products LIKE 'keywords'");
        $hasKeywords = $checkKeywords->rowCount() > 0;
    } catch (PDOException $e) {}
    
    // Build intelligent search query with relevance scoring
    $sql = "SELECT DISTINCT
                p.id,
                p.name,
                p.price,
                p.image,
                c.name as category_name,
                CASE
                    -- Exact match (highest priority)
                    WHEN LOWER(p.name) = LOWER(?) THEN 1
                    -- Starts with query (very high priority)
                    WHEN LOWER(p.name) LIKE LOWER(?) THEN 2
                    -- Word starts with query (high priority)
                    WHEN LOWER(p.name) LIKE LOWER(?) THEN 3
                    -- Contains query (medium priority)
                    WHEN LOWER(p.name) LIKE LOWER(?) THEN 4
                    -- Category match (lower priority)
                    WHEN LOWER(c.name) LIKE LOWER(?) THEN 5";
    
    if ($hasSku) {
        $sql .= "
                    -- SKU match
                    WHEN LOWER(p.sku) LIKE LOWER(?) THEN 6";
    }
    
    if ($hasKeywords) {
        $sql .= "
                    -- Keywords match
                    WHEN LOWER(p.keywords) LIKE LOWER(?) THEN 7";
    }
    
    $sql .= "
                    ELSE 8
                END as relevance
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE (
                LOWER(p.name) LIKE LOWER(?)
                OR LOWER(p.description) LIKE LOWER(?)
                OR LOWER(c.name) LIKE LOWER(?)";
    
    if ($hasSku) {
        $sql .= " OR LOWER(p.sku) LIKE LOWER(?)";
    }
    
    if ($hasKeywords) {
        $sql .= " OR LOWER(p.keywords) LIKE LOWER(?)";
    }
    
    $sql .= "
            )
            AND p.stock > 0
            ORDER BY relevance ASC, p.name ASC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters for relevance scoring
    $paramIndex = 1;
    $stmt->bindValue($paramIndex++, $query, PDO::PARAM_STR);           // Exact match
    $stmt->bindValue($paramIndex++, $startsWith, PDO::PARAM_STR);      // Starts with
    $stmt->bindValue($paramIndex++, $wordStart, PDO::PARAM_STR);       // Word start
    $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);      // Contains
    $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);      // Category
    
    if ($hasSku) {
        $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);  // SKU
    }
    
    if ($hasKeywords) {
        $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);  // Keywords
    }
    
    // Bind parameters for WHERE clause
    $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);      // Name
    $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);      // Description
    $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);      // Category
    
    if ($hasSku) {
        $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);  // SKU
    }
    
    if ($hasKeywords) {
        $stmt->bindValue($paramIndex++, $searchTerm, PDO::PARAM_STR);  // Keywords
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for frontend
    $results = array_map(function($product) {
        return [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'price' => number_format($product['price'], 2),
            'category' => $product['category_name'] ?? 'Uncategorized',
            'image' => $product['image'],
            'url' => base_url('product.php?id=' . $product['id'])
        ];
    }, $products);
    
    echo json_encode([
        'success' => true,
        'products' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'message' => $e->getMessage()
    ]);
}
