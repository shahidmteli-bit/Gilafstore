<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// We need to include db_connect.php. 
// Since this file is in the root, and db_connect is in includes/
require_once __DIR__ . '/includes/db_connect.php';

$db = get_db_connection();
$productId = 24;
$weightId = 31;

echo "--- CHECKING WEIGHT $weightId FOR PRODUCT $productId ---\n";

$stmt = $db->prepare("SELECT id, display_weight, variant_image, variant_image_back FROM product_weights WHERE id = ?");
$stmt->execute([$weightId]);
$weight = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$weight) {
    echo "Weight not found!\n";
    exit;
}

echo "Display Weight: " . $weight['display_weight'] . "\n";
echo "Variant Image (Raw DB Value): [" . $weight['variant_image'] . "]\n";
echo "Variant Back (Raw DB Value): [" . $weight['variant_image_back'] . "]\n";

// Check file existence
$basePath = __DIR__ . '/assets/images/products/';

if ($weight['variant_image']) {
    $fullPath = $basePath . $weight['variant_image'];
    echo "Checking File: " . $fullPath . "\n";
    echo "Exists? " . (file_exists($fullPath) ? "YES" : "NO") . "\n";
}

// Test asset_url generation
echo "Asset URL: " . asset_url('images/products/' . $weight['variant_image']) . "\n";
?>
