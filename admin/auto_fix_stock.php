<?php
// admin/auto_fix_stock.php
// Script to force-sync all products with their batch inventory

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/batch_functions.php';

require_admin();

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Stock Sync Tool</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head><body class='p-4'>";

echo "<h1>🔄 Global Stock Synchronization</h1>";
echo "<p class='lead'>This script recalculates the stock for ALL products based on their active batches.</p>";
echo "<hr>";

$db = get_db_connection();

// Get all products
$stmt = $db->query("SELECT id, name, stock FROM products ORDER BY id");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='card'><div class='card-body'><table class='table table-striped'>";
echo "<thead><tr><th>ID</th><th>Product</th><th>Old Stock</th><th>New Stock</th><th>Active Batch</th><th>Status</th></tr></thead>";
echo "<tbody>";

$count = 0;
foreach ($products as $product) {
    $result = sync_product_batch_data($product['id']);
    
    $oldStock = $product['stock'];
    $newStock = $result['stock'];
    $batchCode = $result['batch_code'];
    
    $status = ($oldStock != $newStock) ? "<span class='badge bg-warning text-dark'>UPDATED</span>" : "<span class='badge bg-success'>OK</span>";
    
    echo "<tr>";
    echo "<td>{$product['id']}</td>";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
    echo "<td>{$oldStock}</td>";
    echo "<td><strong>{$newStock}</strong></td>";
    echo "<td>{$batchCode}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
    
    $count++;
}

echo "</tbody></table></div></div>";
echo "<p class='mt-3 alert alert-success'>✅ Synced $count products successfully.</p>";

echo "<a href='manage_batches.php' class='btn btn-primary'>Return to Batch Management</a>";

echo "</body></html>";
?>
