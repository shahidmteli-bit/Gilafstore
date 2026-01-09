<?php
/**
 * API endpoint to track product click events
 * Follows Page View tracking architecture for consistency
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$productId = (int)($input['product_id'] ?? 0);
$eventSource = $input['source'] ?? 'unknown';

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

// CRITICAL: Exclude admin users (SAME as Page View tracking in new-header.php)
if (isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    echo json_encode(['success' => true, 'message' => 'Admin user - not tracked']);
    exit;
}

// Check if tracking is enabled (SAME as trackPageView function)
global $conn;
$settingQuery = "SELECT setting_value FROM analytics_settings WHERE setting_key = 'tracking_enabled'";
$result = $conn->query($settingQuery);
if ($result && $result->num_rows > 0) {
    $setting = $result->fetch_assoc();
    if ($setting['setting_value'] !== 'true') {
        echo json_encode(['success' => true, 'message' => 'Tracking disabled']);
        exit;
    }
}

try {
    // Get product details
    $product = get_product($productId);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    
    // Track the click event using global function (SAME pattern as trackPageView)
    trackProductEvent(
        $productId, 
        'click', 
        $eventSource, 
        $product['category_id'] ?? null, 
        $product['price'] ?? null
    );
    
    echo json_encode(['success' => true, 'message' => 'Click tracked']);
    
} catch (Exception $e) {
    error_log("Product click tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Tracking failed']);
}
?>
