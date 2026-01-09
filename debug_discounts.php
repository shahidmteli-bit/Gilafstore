<?php
// Debug script to check discount functionality
require_once __DIR__ . '/includes/functions.php';

echo "<h2>Discount Debug Information</h2>";
echo "<hr>";

// Check if product_discounts table exists
try {
    $db = get_db_connection();
    $result = $db->query("SHOW TABLES LIKE 'product_discounts'");
    if ($result->rowCount() > 0) {
        echo "✅ Table 'product_discounts' exists<br><br>";
    } else {
        echo "❌ Table 'product_discounts' does NOT exist<br><br>";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "<br><br>";
    exit;
}

// Get all products
echo "<h3>Products in Database:</h3>";
$products = get_trending_products(10);
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Name</th><th>Price</th></tr>";
foreach ($products as $product) {
    echo "<tr>";
    echo "<td>" . $product['id'] . "</td>";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
    echo "<td>₹" . $product['price'] . "</td>";
    echo "</tr>";
}
echo "</table><br><br>";

// Get all discounts
echo "<h3>Discounts in Database:</h3>";
try {
    $discounts = db_fetch_all("SELECT * FROM product_discounts");
    if (empty($discounts)) {
        echo "⚠️ No discounts found in database<br><br>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Product ID</th><th>Type</th><th>Value</th><th>Start Date</th><th>End Date</th><th>Active</th></tr>";
        foreach ($discounts as $discount) {
            echo "<tr>";
            echo "<td>" . $discount['id'] . "</td>";
            echo "<td>" . $discount['product_id'] . "</td>";
            echo "<td>" . $discount['discount_type'] . "</td>";
            echo "<td>" . $discount['discount_value'] . "</td>";
            echo "<td>" . $discount['start_date'] . "</td>";
            echo "<td>" . $discount['end_date'] . "</td>";
            echo "<td>" . ($discount['is_active'] ? '✅' : '❌') . "</td>";
            echo "</tr>";
        }
        echo "</table><br><br>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching discounts: " . $e->getMessage() . "<br><br>";
}

// Test discount enrichment
echo "<h3>Testing Discount Enrichment:</h3>";
$testProducts = get_trending_products(4);
$enrichedProducts = enrich_products_with_discounts($testProducts);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Product Name</th><th>Original Price</th><th>Has Discount?</th><th>Discount %</th><th>Discounted Price</th></tr>";
foreach ($enrichedProducts as $product) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
    echo "<td>₹" . $product['price'] . "</td>";
    echo "<td>" . ($product['has_discount'] ? '✅ YES' : '❌ NO') . "</td>";
    echo "<td>" . round($product['discount_percentage']) . "%</td>";
    echo "<td>₹" . $product['discounted_price'] . "</td>";
    echo "</tr>";
}
echo "</table><br><br>";

// Check current date/time
echo "<h3>Server Date/Time Check:</h3>";
echo "Current Server Time: " . date('Y-m-d H:i:s') . "<br>";
echo "Timezone: " . date_default_timezone_get() . "<br><br>";

echo "<hr>";
echo "<p><strong>If discounts still don't show:</strong></p>";
echo "<ol>";
echo "<li>Check that product IDs in product_discounts match actual product IDs</li>";
echo "<li>Verify dates are current (not in future or past)</li>";
echo "<li>Ensure is_active = 1</li>";
echo "<li>Clear browser cache</li>";
echo "</ol>";
?>
