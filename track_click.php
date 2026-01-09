<?php
/**
 * Server-side Click Tracking Endpoint
 * Replicates Page View tracking architecture for reliable click tracking
 * 
 * This follows the EXACT same pattern as trackPageView() for consistency
 */

// Start session and include dependencies (same as Page View tracking)
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/analytics_tracker.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests (same validation as other tracking)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$source = isset($input['source']) ? trim($input['source']) : '';

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

// CRITICAL: Exclude admin users (SAME as Page View tracking)
if (isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']) {
    echo json_encode(['success' => true, 'message' => 'Admin user - not tracked']);
    exit;
}

// Check if tracking is enabled (SAME as Page View tracking)
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
    // Use AnalyticsTracker class (SAME as Page View tracking)
    $tracker = new AnalyticsTracker($conn);
    
    // Get product details for category and price
    $productQuery = "SELECT category_id, price FROM products WHERE id = ?";
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $productResult = $stmt->get_result();
    
    if ($productResult && $productResult->num_rows > 0) {
        $product = $productResult->fetch_assoc();
        
        // Track the click event using existing method
        $result = $tracker->trackProductEvent(
            $productId,
            'click',
            $source,
            $product['category_id'],
            $product['price'],
            1
        );
        
        if ($result !== false) {
            echo json_encode(['success' => true, 'message' => 'Click tracked']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database insert failed']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Click tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Tracking failed']);
}

$conn->close();
?>
