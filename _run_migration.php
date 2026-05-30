<?php
/**
 * One-time DB Migration: Shipping Hybrid System
 * Adds integration_type to shipping_partners, migrates courier_companies, creates order_shipments
 * DELETE THIS FILE AFTER RUNNING
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db_connect.php';

$db = get_db_connection();
$results = [];

// ─── Step 1: Add integration_type column to shipping_partners ───
try {
    $cols = $db->query("SHOW COLUMNS FROM shipping_partners LIKE 'integration_type'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE shipping_partners ADD COLUMN integration_type ENUM('api','manual') NOT NULL DEFAULT 'api' AFTER partner_type");
        $results[] = "✅ Added integration_type column to shipping_partners";
    } else {
        $results[] = "⏭️ integration_type column already exists in shipping_partners";
    }
} catch (Exception $e) {
    $results[] = "❌ Step 1 error: " . $e->getMessage();
}

// ─── Step 2: Ensure all existing shipping_partners are marked as 'api' ───
try {
    $db->exec("UPDATE shipping_partners SET integration_type = 'api' WHERE integration_type IS NULL OR integration_type = ''");
    $results[] = "✅ Existing shipping_partners marked as api";
} catch (Exception $e) {
    $results[] = "❌ Step 2 error: " . $e->getMessage();
}

// ─── Step 3: Migrate courier_companies into shipping_partners as manual ───
try {
    $couriers = $db->query("SELECT * FROM courier_companies WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    $migrated = 0;
    foreach ($couriers as $c) {
        $code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $c['name']));
        // Check if already exists (any integration type)
        $exists = $db->prepare("SELECT id FROM shipping_partners WHERE partner_code = ?");
        $exists->execute([$code]);
        if ($exists->fetch()) {
            $results[] = "⏭️ Skipped '{$c['name']}' — partner_code '{$code}' already exists";
            continue;
        }

        $stmt = $db->prepare("INSERT INTO shipping_partners (partner_name, partner_code, partner_type, integration_type, base_url, is_active) VALUES (?, ?, 'domestic', 'manual', ?, 1)");
        $trackingUrl = $c['tracking_url_pattern'] ?? '';
        $stmt->execute([$c['name'], $code, $trackingUrl]);
        $migrated++;
    }
    $results[] = "✅ Migrated {$migrated} courier companies as manual partners (total couriers: " . count($couriers) . ")";
} catch (Exception $e) {
    $results[] = "❌ Step 3 error: " . $e->getMessage();
}

// ─── Step 4: Create order_shipments table ───
try {
    $db->exec("CREATE TABLE IF NOT EXISTS order_shipments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        shipping_type ENUM('api','manual') NOT NULL DEFAULT 'manual',
        shipping_partner VARCHAR(100) DEFAULT NULL,
        shipping_partner_code VARCHAR(50) DEFAULT NULL,
        api_shipment_id VARCHAR(100) DEFAULT NULL,
        awb_or_tracking VARCHAR(100) DEFAULT NULL,
        dispatch_mode VARCHAR(30) DEFAULT NULL,
        label_url TEXT DEFAULT NULL,
        shipping_status VARCHAR(50) DEFAULT 'created',
        status_detail TEXT DEFAULT NULL,
        estimated_delivery DATE DEFAULT NULL,
        actual_delivery DATE DEFAULT NULL,
        weight_kg DECIMAL(8,3) DEFAULT NULL,
        dimensions_cm VARCHAR(50) DEFAULT NULL,
        shipping_cost DECIMAL(10,2) DEFAULT NULL,
        created_by_admin_id INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_shipping_status (shipping_status),
        INDEX idx_awb (awb_or_tracking)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "✅ order_shipments table created (or already exists)";
} catch (Exception $e) {
    $results[] = "❌ Step 4 error: " . $e->getMessage();
}

// ─── Output ───
echo "<!DOCTYPE html><html><head><title>Migration Results</title></head><body style='font-family:monospace;padding:40px;'>";
echo "<h2>🚀 Shipping Hybrid Migration</h2>";
foreach ($results as $r) {
    echo "<div style='margin:8px 0;font-size:16px;'>{$r}</div>";
}
echo "<hr><p style='color:red;font-weight:bold;'>⚠️ DELETE THIS FILE FROM THE SERVER NOW</p>";
echo "</body></html>";
