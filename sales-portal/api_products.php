<?php
/**
 * Sales Portal API - Products with profile-based pricing
 * Returns products with the correct price for the given profile_type
 * 
 * Usage: api_products.php?profile_type=wholesaler&q=tea&cat=1
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

header('Content-Type: application/json');

$profileType = trim($_GET['profile_type'] ?? 'wholesaler');
$search = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['cat'] ?? 0);

// Validate profile type
if (!in_array($profileType, ['wholesaler', 'distributor', 'franchise'])) {
    $profileType = 'wholesaler';
}

// Map profile type to price column
$priceColumn = 'pw.price'; // default retail
switch ($profileType) {
    case 'wholesaler':
        $priceColumn = 'COALESCE(NULLIF(pw.wholesale_price, 0), pw.price)';
        break;
    case 'distributor':
        $priceColumn = 'COALESCE(NULLIF(pw.distributor_price, 0), pw.price)';
        break;
    case 'franchise':
        $priceColumn = 'COALESCE(NULLIF(pw.franchise_price, 0), pw.price)';
        break;
}

// Build query — get products with their default weight's profile-specific price
$sql = "SELECT p.id, p.name, p.image, p.stock,
        pw.id as weight_id, pw.display_weight, pw.price as retail_price,
        {$priceColumn} as profile_price
        FROM products p
        LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.is_default = 1
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= ' AND p.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($categoryId) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryId;
}

$sql .= ' ORDER BY p.name ASC LIMIT 50';

try {
    $products = db_fetch_all($sql, $params);
    
    // For products without a default weight, fall back to product price
    foreach ($products as &$prod) {
        if (!$prod['profile_price']) {
            $prod['profile_price'] = $prod['retail_price'] ?? 0;
        }
        // Also get all weights with profile prices for this product
        $weightsSql = "SELECT id, display_weight, price as retail_price,
                       {$priceColumn} as profile_price
                       FROM product_weights pw WHERE pw.product_id = ? ORDER BY sort_order ASC";
        $prod['weights'] = db_fetch_all($weightsSql, [$prod['id']]);
    }
    
    echo json_encode(['success' => true, 'products' => $products, 'profile_type' => $profileType]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
