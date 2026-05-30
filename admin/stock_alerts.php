<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Stock Alerts — Admin';
$adminPage = 'stock_alerts';

$db = get_db_connection();

// Get low stock threshold from settings or default
$lowStockThreshold = 10;
$criticalStockThreshold = 3;

// Get products with low stock
$stmt = $db->prepare("
    SELECT p.id, p.name, p.stock, p.image, p.price, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock <= ?
    ORDER BY p.stock ASC, p.name ASC
");
$stmt->execute([$lowStockThreshold]);
$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get out of stock products
$outOfStock = array_filter($lowStockProducts, fn($p) => (int)$p['stock'] <= 0);
$criticalStock = array_filter($lowStockProducts, fn($p) => (int)$p['stock'] > 0 && (int)$p['stock'] <= $criticalStockThreshold);
$lowStock = array_filter($lowStockProducts, fn($p) => (int)$p['stock'] > $criticalStockThreshold && (int)$p['stock'] <= $lowStockThreshold);

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-semibold mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Stock Alerts</h4>
        <p class="text-muted mb-0">Monitor low stock and out-of-stock products</p>
    </div>
    <div>
        <a href="<?= base_url('admin/manage_products.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-box me-1"></i> All Products
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3" style="background: #fee2e2;">
                    <i class="fas fa-times-circle fa-lg" style="color: #dc2626;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4"><?= count($outOfStock) ?></div>
                    <div class="text-muted small">Out of Stock</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3" style="background: #fef3c7;">
                    <i class="fas fa-exclamation-triangle fa-lg" style="color: #d97706;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4"><?= count($criticalStock) ?></div>
                    <div class="text-muted small">Critical (1-<?= $criticalStockThreshold ?>)</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3" style="background: #dbeafe;">
                    <i class="fas fa-arrow-down fa-lg" style="color: #2563eb;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4"><?= count($lowStock) ?></div>
                    <div class="text-muted small">Low Stock (<?= $criticalStockThreshold + 1 ?>-<?= $lowStockThreshold ?>)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($lowStockProducts)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h5>All products are well-stocked!</h5>
            <p class="text-muted">No products are below the threshold of <?= $lowStockThreshold ?> units.</p>
        </div>
    </div>
<?php else: ?>

    <!-- Stock Alerts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background: #1a3c34; color: #fff;">
                        <tr>
                            <th class="ps-3">Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <?php
                                $stock = (int)$product['stock'];
                                if ($stock <= 0) {
                                    $statusClass = 'bg-danger';
                                    $statusText = 'Out of Stock';
                                } elseif ($stock <= $criticalStockThreshold) {
                                    $statusClass = 'bg-warning text-dark';
                                    $statusText = 'Critical';
                                } else {
                                    $statusClass = 'bg-info text-dark';
                                    $statusText = 'Low Stock';
                                }
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?= base_url($product['image']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: #f3f4f6; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <strong class="small"><?= htmlspecialchars($product['name']) ?></strong>
                                    </div>
                                </td>
                                <td><span class="small text-muted"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span></td>
                                <td>₹<?= number_format($product['price'], 2) ?></td>
                                <td>
                                    <span class="fw-bold <?= $stock <= 0 ? 'text-danger' : ($stock <= $criticalStockThreshold ? 'text-warning' : 'text-info') ?>">
                                        <?= $stock ?>
                                    </span>
                                </td>
                                <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td>
                                    <a href="<?= base_url('admin/product_edit.php?id=' . $product['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>

</div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
