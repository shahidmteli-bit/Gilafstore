<?php
/**
 * Sales Portal API - Products with Enhanced Party-Based Pricing
 * Returns products with correct price, GST, and MRP for the given profile_type
 * Supports: wholesaler, distributor, franchise, retailer
 * 
 * Usage: api_products_enhanced.php?profile_type=retailer&q=tea&cat=1&include_gst=1
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/sales_pricing_helper.php';
sales_require_login();

header('Content-Type: application/json');

$profileType = trim($_GET['profile_type'] ?? 'wholesaler');
$search = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['cat'] ?? 0);
// Always include GST in sales portal pricing
$includeGst = true;

// Validate profile type
if (!in_array($profileType, ['wholesaler', 'distributor', 'franchise', 'retailer'])) {
    $profileType = 'wholesaler';
}

// Map profile type to price columns
$priceMapping = [
    'wholesaler' => ['price_col' => 'pw.wholesale_price', 'gst_col' => 'pw.wholesale_gst'],
    'distributor' => ['price_col' => 'pw.distributor_price', 'gst_col' => 'pw.distributor_gst'],
    'franchise' => ['price_col' => 'pw.franchise_price', 'gst_col' => 'pw.franchise_gst'],
    'retailer' => ['price_col' => 'pw.retail_price', 'gst_col' => 'pw.retail_gst'],
];

$mapping = $priceMapping[$profileType];

// Build query
$sql = "SELECT p.id, p.name, p.image, p.stock, p.category_id,
        pw.id as weight_id, pw.display_weight, 
        pw.price as website_price,
        {$mapping['price_col']} as base_price,
        {$mapping['gst_col']} as gst_percent,
        pw.offline_mrp
        FROM products p
        INNER JOIN product_weights pw ON pw.product_id = p.id
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

$sql .= ' ORDER BY p.name ASC, pw.sort_order ASC, pw.weight_value ASC LIMIT 100';

try {
    $rows = db_fetch_all($sql, $params);
    
    // Group by product and calculate pricing
    $products = [];
    foreach ($rows as $row) {
        $productId = $row['id'];
        
        if (!isset($products[$productId])) {
            $products[$productId] = [
                'id' => $productId,
                'name' => $row['name'],
                'image' => $row['image'],
                'stock' => $row['stock'],
                'category_id' => $row['category_id'],
                'weights' => []
            ];
        }
        
        $basePrice = (float)$row['base_price'];
        $gstPercent = (float)$row['gst_percent'];
        $offlineMrp = (float)$row['offline_mrp'];
        
        // Calculate GST amount and total
        $gstAmount = $includeGst ? round(($basePrice * $gstPercent / 100), 2) : 0.00;
        $totalPrice = round($basePrice + $gstAmount, 2);
        
        $products[$productId]['weights'][] = [
            'weight_id' => $row['weight_id'],
            'display_weight' => $row['display_weight'],
            'base_price' => number_format($basePrice, 2, '.', ''),
            'gst_percent' => number_format($gstPercent, 2, '.', ''),
            'gst_amount' => number_format($gstAmount, 2, '.', ''),
            'total_price' => number_format($totalPrice, 2, '.', ''),
            'offline_mrp' => number_format($offlineMrp, 2, '.', ''),
            'website_price' => number_format((float)$row['website_price'], 2, '.', '')
        ];
    }
    
    // Convert to indexed array
    $productsArray = array_values($products);
    
    echo json_encode([
        'success' => true, 
        'products' => $productsArray, 
        'profile_type' => $profileType,
        'include_gst' => $includeGst,
        'count' => count($productsArray)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
