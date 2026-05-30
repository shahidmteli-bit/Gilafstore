<?php
require_once __DIR__ . '/includes/functions.php';

echo "=== ALL PARTIES IN DATABASE ===\n\n";

$allParties = db_fetch_all('SELECT id, shop_name, owner_name, outstanding_amount, credit_limit, is_active, is_blocked, party_code, profile_type FROM sales_parties ORDER BY id DESC');

echo "Total Parties: " . count($allParties) . "\n\n";

foreach($allParties as $p) {
    echo "ID: {$p['id']}\n";
    echo "Shop: {$p['shop_name']}\n";
    echo "Owner: {$p['owner_name']}\n";
    echo "Party Code: {$p['party_code']}\n";
    echo "Profile: {$p['profile_type']}\n";
    echo "Outstanding: Rs{$p['outstanding_amount']}\n";
    echo "Credit Limit: Rs{$p['credit_limit']}\n";
    echo "Active: " . ($p['is_active'] ? 'Yes' : 'No') . "\n";
    echo "Blocked: " . ($p['is_blocked'] ? 'Yes' : 'No') . "\n";
    echo "---\n\n";
}

echo "\n=== PARTIES WITH OUTSTANDING > 0 ===\n\n";
$withOutstanding = array_filter($allParties, function($p) { return $p['outstanding_amount'] > 0; });
echo "Count: " . count($withOutstanding) . "\n\n";
foreach($withOutstanding as $p) {
    echo "{$p['shop_name']} - Rs{$p['outstanding_amount']}\n";
}

echo "\n=== INACTIVE PARTIES ===\n\n";
$inactive = array_filter($allParties, function($p) { return !$p['is_active']; });
echo "Count: " . count($inactive) . "\n\n";
foreach($inactive as $p) {
    echo "{$p['shop_name']} (ID: {$p['id']}) - Outstanding: Rs{$p['outstanding_amount']}\n";
}

echo "\n=== BLOCKED PARTIES ===\n\n";
$blocked = array_filter($allParties, function($p) { return $p['is_blocked']; });
echo "Count: " . count($blocked) . "\n\n";
foreach($blocked as $p) {
    echo "{$p['shop_name']} (ID: {$p['id']}) - Outstanding: Rs{$p['outstanding_amount']}\n";
}
