<?php
/**
 * ONE-TIME SCRIPT: Fix Asif's ₹1 Outstanding Issue
 * Run this ONCE to fix precision/rounding issues for Asif General Store
 * DELETE THIS FILE after running
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<!DOCTYPE html><html><head><title>Fix Asif Outstanding</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:800px;margin:0 auto;}";
echo ".success{color:#059669;background:#d1fae5;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".error{color:#dc2626;background:#fee2e2;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".info{color:#2563eb;background:#dbeafe;padding:10px;border-radius:5px;margin:10px 0;}";
echo "table{border-collapse:collapse;width:100%;margin:15px 0;}";
echo "th,td{border:1px solid #ddd;padding:8px;text-align:left;}";
echo "th{background:#f3f4f6;}</style></head><body>";

echo "<h1>🔧 Fix Asif's ₹1 Outstanding Issue</h1>";

// Find Asif General Store
$asif = db_fetch("SELECT * FROM sales_parties WHERE shop_name LIKE '%Asif%' OR owner_name LIKE '%Asif%' ORDER BY id LIMIT 1");

if (!$asif) {
    echo "<div class='error'>❌ Asif General Store not found in database.</div>";
    echo "</body></html>";
    exit;
}

echo "<div class='info'>📋 <strong>Party Found:</strong> " . htmlspecialchars($asif['shop_name']) . " (ID: {$asif['id']})</div>";
echo "<div class='info'>💰 <strong>Current Outstanding:</strong> ₹" . number_format($asif['outstanding_amount'], 2) . "</div>";

// Get all orders for Asif
$orders = db_fetch_all(
    "SELECT id, order_number, total_amount, payment_amount, payment_status, status, created_at 
     FROM sales_orders 
     WHERE party_id = ? 
     AND status IN ('approved','dispatched','delivered')
     AND order_type = 'new_order'
     ORDER BY created_at ASC",
    [$asif['id']]
);

echo "<h2>📦 Orders Analysis</h2>";
echo "<table>";
echo "<tr><th>Order #</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th></tr>";

$totalOrderAmount = 0;
$totalPaidAmount = 0;
$calculatedOutstanding = 0;

foreach ($orders as $order) {
    $total = (float)$order['total_amount'];
    $paid = (float)$order['payment_amount'];
    $balance = $total - $paid;
    
    $totalOrderAmount += $total;
    $totalPaidAmount += $paid;
    
    if ($order['payment_status'] !== 'received') {
        $calculatedOutstanding += $balance;
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($order['order_number']) . "</td>";
    echo "<td>₹" . number_format($total, 2) . "</td>";
    echo "<td>₹" . number_format($paid, 2) . "</td>";
    echo "<td>₹" . number_format($balance, 2) . "</td>";
    echo "<td>" . htmlspecialchars($order['payment_status']) . "</td>";
    echo "<td>" . date('d M Y', strtotime($order['created_at'])) . "</td>";
    echo "</tr>";
}

echo "<tr style='background:#f9fafb;font-weight:bold;'>";
echo "<td>TOTAL</td>";
echo "<td>₹" . number_format($totalOrderAmount, 2) . "</td>";
echo "<td>₹" . number_format($totalPaidAmount, 2) . "</td>";
echo "<td>₹" . number_format($calculatedOutstanding, 2) . "</td>";
echo "<td colspan='2'></td>";
echo "</tr>";
echo "</table>";

echo "<div class='info'>🧮 <strong>Calculated Outstanding:</strong> ₹" . number_format($calculatedOutstanding, 2) . "</div>";

// Apply precision fix
$calculatedOutstanding = round($calculatedOutstanding, 2);
if (abs($calculatedOutstanding) < 0.01) {
    $calculatedOutstanding = 0;
}

echo "<div class='info'>✨ <strong>After Precision Fix:</strong> ₹" . number_format($calculatedOutstanding, 2) . "</div>";

// Update the party's outstanding
db_query('UPDATE sales_parties SET outstanding_amount = ? WHERE id = ?', [$calculatedOutstanding, $asif['id']]);

echo "<div class='success'>✅ <strong>Outstanding Updated Successfully!</strong></div>";
echo "<div class='success'>🎯 New Outstanding: ₹" . number_format($calculatedOutstanding, 2) . "</div>";

// Verify
$updated = db_fetch("SELECT outstanding_amount FROM sales_parties WHERE id = ?", [$asif['id']]);
echo "<div class='success'>✔️ <strong>Verified:</strong> Database now shows ₹" . number_format($updated['outstanding_amount'], 2) . "</div>";

echo "<hr>";
echo "<h2>🗑️ Next Steps</h2>";
echo "<div class='info'>";
echo "<ol>";
echo "<li>Verify the outstanding amount is now correct in the sales app</li>";
echo "<li><strong>DELETE THIS FILE</strong> from both local and remote server</li>";
echo "<li>File to delete: <code>fix_asif_outstanding.php</code></li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
