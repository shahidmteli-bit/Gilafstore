<?php
/**
 * Migration: Add profile-based pricing
 * - Adds profile_type to sales_parties (wholesaler, distributor, franchise)
 * - Adds wholesale_price, distributor_price, franchise_price to product_weights
 * 
 * Run this once: http://localhost/Gilaf%20Ecommerce%20website/sales-portal/migrate_profile_pricing.php
 */
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: text/plain');
$db = get_db_connection();
$results = [];

// 1. Add profile_type to sales_parties
try {
    $check = $db->query("SHOW COLUMNS FROM sales_parties LIKE 'profile_type'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE sales_parties ADD COLUMN profile_type ENUM('wholesaler','distributor','franchise','retailer') NOT NULL DEFAULT 'wholesaler' AFTER party_code");
        $results[] = "✅ Added profile_type to sales_parties";
    } else {
        $results[] = "⏭ profile_type already exists in sales_parties";
    }
} catch (PDOException $e) {
    $results[] = "❌ Error adding profile_type: " . $e->getMessage();
}

// 2. Add wholesale_price to product_weights
try {
    $check = $db->query("SHOW COLUMNS FROM product_weights LIKE 'wholesale_price'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE product_weights ADD COLUMN wholesale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");
        $results[] = "✅ Added wholesale_price to product_weights";
    } else {
        $results[] = "⏭ wholesale_price already exists in product_weights";
    }
} catch (PDOException $e) {
    $results[] = "❌ Error adding wholesale_price: " . $e->getMessage();
}

// 3. Add distributor_price to product_weights
try {
    $check = $db->query("SHOW COLUMNS FROM product_weights LIKE 'distributor_price'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE product_weights ADD COLUMN distributor_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER wholesale_price");
        $results[] = "✅ Added distributor_price to product_weights";
    } else {
        $results[] = "⏭ distributor_price already exists in product_weights";
    }
} catch (PDOException $e) {
    $results[] = "❌ Error adding distributor_price: " . $e->getMessage();
}

// 4. Add franchise_price to product_weights
try {
    $check = $db->query("SHOW COLUMNS FROM product_weights LIKE 'franchise_price'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE product_weights ADD COLUMN franchise_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER distributor_price");
        $results[] = "✅ Added franchise_price to product_weights";
    } else {
        $results[] = "⏭ franchise_price already exists in product_weights";
    }
} catch (PDOException $e) {
    $results[] = "❌ Error adding franchise_price: " . $e->getMessage();
}

// 5. Copy existing price as wholesale_price default (so existing data isn't lost)
try {
    $db->exec("UPDATE product_weights SET wholesale_price = price WHERE wholesale_price = 0 AND price > 0");
    $db->exec("UPDATE product_weights SET distributor_price = price WHERE distributor_price = 0 AND price > 0");
    $db->exec("UPDATE product_weights SET franchise_price = price WHERE franchise_price = 0 AND price > 0");
    $results[] = "✅ Copied existing prices as defaults for all profile types";
} catch (PDOException $e) {
    $results[] = "❌ Error copying prices: " . $e->getMessage();
}

echo "=== Profile-Based Pricing Migration ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\nDone! You can now set different prices for Wholesale, Distributor, and Franchise in the admin panel.\n";
