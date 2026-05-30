<?php
/**
 * Remove ₹300 transaction for Asif General Store (created by mistake)
 * This script will:
 * 1. Find Asif General Store party
 * 2. Find the ₹300 transaction (order, collection, or payment)
 * 3. Delete it
 * 4. Recalculate outstanding amount
 */

require_once __DIR__ . '/includes/functions.php';

echo "=== REMOVE ₹300 TRANSACTION FOR ASIF GENERAL STORE ===\n\n";

// Find Asif General Store
$asif = db_fetch('SELECT * FROM sales_parties WHERE shop_name LIKE ?', ['%Asif%General%Store%']);

if (!$asif) {
    echo "ERROR: Asif General Store not found!\n";
    echo "Searching with different patterns...\n\n";
    
    $asif = db_fetch('SELECT * FROM sales_parties WHERE shop_name LIKE ? OR owner_name LIKE ?', ['%Asif%', '%Asif%']);
    
    if (!$asif) {
        echo "Still not found. Listing all parties:\n";
        $all = db_fetch_all('SELECT id, shop_name, owner_name FROM sales_parties');
        foreach($all as $p) {
            echo "ID: {$p['id']} | Shop: {$p['shop_name']} | Owner: {$p['owner_name']}\n";
        }
        exit;
    }
}

echo "Party Found:\n";
echo "ID: {$asif['id']}\n";
echo "Shop: {$asif['shop_name']}\n";
echo "Owner: {$asif['owner_name']}\n";
echo "Current Outstanding: ₹{$asif['outstanding_amount']}\n\n";

$partyId = $asif['id'];

// Search for ₹300 transactions
echo "=== SEARCHING FOR ₹300 TRANSACTIONS ===\n\n";

// 1. Check orders
echo "1. ORDERS:\n";
$orders300 = db_fetch_all('SELECT id, order_number, total_amount, status, payment_status, created_at FROM sales_orders WHERE party_id = ? AND total_amount = 300', [$partyId]);

if (!empty($orders300)) {
    foreach($orders300 as $o) {
        echo "  Order ID: {$o['id']} | #{$o['order_number']} | Amount: ₹{$o['total_amount']} | Status: {$o['status']} | Payment: {$o['payment_status']} | Date: {$o['created_at']}\n";
    }
} else {
    echo "  No orders with ₹300 found.\n";
}
echo "\n";

// 2. Check collections
echo "2. COLLECTIONS:\n";
$collections300 = db_fetch_all('SELECT id, collection_number, amount, status, payment_method, created_at FROM sales_collections WHERE party_id = ? AND amount = 300', [$partyId]);

if (!empty($collections300)) {
    foreach($collections300 as $c) {
        echo "  Collection ID: {$c['id']} | #{$c['collection_number']} | Amount: ₹{$c['amount']} | Status: {$c['status']} | Method: {$c['payment_method']} | Date: {$c['created_at']}\n";
    }
} else {
    echo "  No collections with ₹300 found.\n";
}
echo "\n";

// 3. Check payment history
echo "3. PAYMENT HISTORY:\n";
$payments300 = db_fetch_all('SELECT id, payment_type, amount, order_id, created_at, notes FROM sales_payment_history WHERE party_id = ? AND amount = 300', [$partyId]);

if (!empty($payments300)) {
    foreach($payments300 as $p) {
        echo "  Payment ID: {$p['id']} | Type: {$p['payment_type']} | Amount: ₹{$p['amount']} | Order ID: {$p['order_id']} | Date: {$p['created_at']} | Notes: {$p['notes']}\n";
    }
} else {
    echo "  No payment history with ₹300 found.\n";
}
echo "\n";

// Show all transactions for Asif
echo "=== ALL TRANSACTIONS FOR ASIF ===\n\n";

echo "ALL ORDERS:\n";
$allOrders = db_fetch_all('SELECT id, order_number, total_amount, status, payment_status, created_at FROM sales_orders WHERE party_id = ? ORDER BY created_at DESC', [$partyId]);
foreach($allOrders as $o) {
    echo "  Order ID: {$o['id']} | #{$o['order_number']} | ₹{$o['total_amount']} | {$o['status']} | Payment: {$o['payment_status']} | {$o['created_at']}\n";
}
echo "\n";

echo "ALL COLLECTIONS:\n";
$allCollections = db_fetch_all('SELECT id, collection_number, amount, status, created_at FROM sales_collections WHERE party_id = ? ORDER BY created_at DESC', [$partyId]);
foreach($allCollections as $c) {
    echo "  Collection ID: {$c['id']} | #{$c['collection_number']} | ₹{$c['amount']} | {$c['status']} | {$c['created_at']}\n";
}
echo "\n";

echo "ALL PAYMENT HISTORY:\n";
$allPayments = db_fetch_all('SELECT id, payment_type, amount, order_id, created_at FROM sales_payment_history WHERE party_id = ? ORDER BY created_at DESC', [$partyId]);
foreach($allPayments as $p) {
    echo "  Payment ID: {$p['id']} | {$p['payment_type']} | ₹{$p['amount']} | Order: {$p['order_id']} | {$p['created_at']}\n";
}
echo "\n";

// Ask for confirmation
echo "\n=== READY TO DELETE ===\n";
echo "Found transactions to delete:\n";
echo "- Orders with ₹300: " . count($orders300) . "\n";
echo "- Collections with ₹300: " . count($collections300) . "\n";
echo "- Payment history with ₹300: " . count($payments300) . "\n\n";

if (empty($orders300) && empty($collections300) && empty($payments300)) {
    echo "No ₹300 transactions found to delete.\n";
    exit;
}

echo "To execute deletion, uncomment the deletion code below and run again.\n";
echo "Current outstanding: ₹{$asif['outstanding_amount']}\n";

// UNCOMMENT BELOW TO EXECUTE DELETION
/*
$db = get_db_connection();
$db->beginTransaction();

try {
    // Delete orders
    foreach($orders300 as $o) {
        echo "Deleting order ID {$o['id']}...\n";
        db_query('DELETE FROM sales_order_items WHERE order_id = ?', [$o['id']]);
        db_query('DELETE FROM sales_orders WHERE id = ?', [$o['id']]);
    }
    
    // Delete collections
    foreach($collections300 as $c) {
        echo "Deleting collection ID {$c['id']}...\n";
        db_query('DELETE FROM sales_collections WHERE id = ?', [$c['id']]);
    }
    
    // Delete payment history
    foreach($payments300 as $p) {
        echo "Deleting payment history ID {$p['id']}...\n";
        db_query('DELETE FROM sales_payment_history WHERE id = ?', [$p['id']]);
    }
    
    $db->commit();
    
    echo "\n✓ Deletion completed successfully!\n\n";
    
    // Recalculate outstanding
    echo "Recalculating outstanding amount...\n";
    recalculate_party_outstanding($partyId);
    
    $asifUpdated = db_fetch('SELECT outstanding_amount FROM sales_parties WHERE id = ?', [$partyId]);
    echo "New outstanding: ₹{$asifUpdated['outstanding_amount']}\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
*/
