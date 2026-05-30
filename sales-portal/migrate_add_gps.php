<?php
/**
 * Migration: Add GPS columns + party_code to sales_parties table
 * Run once, then delete this file.
 */
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();

$queries = [
    "ALTER TABLE sales_parties ADD COLUMN latitude DECIMAL(11,8) DEFAULT NULL",
    "ALTER TABLE sales_parties ADD COLUMN longitude DECIMAL(11,8) DEFAULT NULL",
    "ALTER TABLE sales_parties ADD COLUMN google_maps_url VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE sales_parties ADD COLUMN party_code VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE sales_parties ADD UNIQUE INDEX idx_party_code (party_code)",
];

$ok = 0;
$errors = [];
foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $ok++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'Duplicate key name') !== false) {
            $ok++;
        } else {
            $errors[] = $e->getMessage();
        }
    }
}

// Generate party_code for existing parties that don't have one
$existing = $db->query("SELECT id FROM sales_parties WHERE party_code IS NULL OR party_code = ''")->fetchAll(PDO::FETCH_ASSOC);
$generated = 0;
foreach ($existing as $row) {
    $code = 'GLP-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
    try {
        $stmt = $db->prepare("UPDATE sales_parties SET party_code = ? WHERE id = ?");
        $stmt->execute([$code, $row['id']]);
        $generated++;
    } catch (PDOException $e) {
        $errors[] = "Party #{$row['id']}: " . $e->getMessage();
    }
}

echo "<h3>Sales Parties Migration</h3>";
echo "<p style='color:green;'>✅ {$ok} schema changes applied.</p>";
if ($generated > 0) {
    echo "<p style='color:green;'>✅ Generated party codes for {$generated} existing parties.</p>";
}
if ($errors) {
    foreach ($errors as $err) echo "<p style='color:red;'>❌ {$err}</p>";
}
echo "<p><a href='parties.php'>→ Go to Parties</a></p>";
