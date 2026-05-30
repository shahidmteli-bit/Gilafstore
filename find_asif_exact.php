<?php
require_once __DIR__ . '/includes/functions.php';

echo "=== SEARCHING FOR ASIF (ALL VARIATIONS) ===\n\n";

// Search with different patterns
$patterns = [
    '%Asif%',
    '%asif%',
    '%ASIF%',
    '%General%',
    '%general%',
    '%Store%',
    '%store%'
];

foreach($patterns as $pattern) {
    $results = db_fetch_all('SELECT id, shop_name, owner_name, outstanding_amount, is_active, is_blocked, district FROM sales_parties WHERE shop_name LIKE ? OR owner_name LIKE ?', [$pattern, $pattern]);
    
    if (!empty($results)) {
        echo "Pattern: {$pattern}\n";
        foreach($results as $r) {
            echo "  ID: {$r['id']} | Shop: {$r['shop_name']} | Owner: {$r['owner_name']} | Outstanding: Rs{$r['outstanding_amount']} | Active: " . ($r['is_active'] ? 'Yes' : 'No') . " | District: {$r['district']}\n";
        }
        echo "\n";
    }
}

echo "\n=== ALL PARTIES IN BARAMULLA ===\n\n";

$baramulla = db_fetch_all('SELECT id, shop_name, owner_name, outstanding_amount, is_active, is_blocked FROM sales_parties WHERE district LIKE ?', ['%Baramulla%']);

echo "Total in Baramulla: " . count($baramulla) . "\n\n";

foreach($baramulla as $p) {
    echo "ID: {$p['id']} | Shop: {$p['shop_name']} | Owner: {$p['owner_name']} | Outstanding: Rs{$p['outstanding_amount']} | Active: " . ($p['is_active'] ? 'Yes' : 'No') . "\n";
}

echo "\n=== ALL PARTIES (COMPLETE LIST) ===\n\n";

$all = db_fetch_all('SELECT id, shop_name, owner_name, outstanding_amount, is_active, is_blocked, district, created_at FROM sales_parties ORDER BY created_at DESC');

echo "Total Parties: " . count($all) . "\n\n";

foreach($all as $p) {
    echo "ID: {$p['id']}\n";
    echo "Shop: {$p['shop_name']}\n";
    echo "Owner: {$p['owner_name']}\n";
    echo "District: {$p['district']}\n";
    echo "Outstanding: Rs{$p['outstanding_amount']}\n";
    echo "Active: " . ($p['is_active'] ? 'Yes' : 'No') . "\n";
    echo "Blocked: " . ($p['is_blocked'] ? 'Yes' : 'No') . "\n";
    echo "Created: {$p['created_at']}\n";
    echo "---\n\n";
}
