<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$message = '';
$messageType = '';

// Check if columns exist
$columnsExist = false;
try {
    $db = get_db_connection();
    $checkCol = $db->query("SHOW COLUMNS FROM products LIKE 'is_freshly_harvested'");
    $columnsExist = $checkCol->rowCount() > 0;
} catch (PDOException $e) {
    $columnsExist = false;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = get_db_connection();
        
        if ($_POST['ajax_action'] === 'toggle_product') {
            $productId = (int)$_POST['product_id'];
            $enabled = (int)$_POST['enabled'];
            
            $stmt = $db->prepare("UPDATE products SET is_freshly_harvested = ? WHERE id = ?");
            $stmt->execute([$enabled, $productId]);
            
            echo json_encode(['success' => true, 'message' => 'Product updated']);
            exit;
        }
        
        if ($_POST['ajax_action'] === 'update_order') {
            $orders = json_decode($_POST['orders'], true);
            
            foreach ($orders as $item) {
                $stmt = $db->prepare("UPDATE products SET freshly_harvested_order = ? WHERE id = ?");
                $stmt->execute([$item['order'], $item['id']]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Order updated']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch all products for selection
$allProducts = [];
$freshlyHarvestedProducts = [];

if ($columnsExist) {
    try {
        $allProducts = db_fetch_all("
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON c.id = p.category_id 
            ORDER BY p.name ASC
        ");
        
        $freshlyHarvestedProducts = db_fetch_all("
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON c.id = p.category_id 
            WHERE p.is_freshly_harvested = 1 
            ORDER BY p.freshly_harvested_order ASC, p.created_at DESC
        ");
    } catch (PDOException $e) {
        $message = 'Error fetching products: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$adminPage = 'freshly_harvested';
include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.fh-container { padding: 20px; }
.fh-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
.fh-card-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.fh-card-header h3 { margin: 0; color: #1A3C34; }
.fh-card-body { padding: 20px; }

.product-list { list-style: none; padding: 0; margin: 0; }
.product-item { 
    display: flex; 
    align-items: center; 
    padding: 12px 15px; 
    border: 1px solid #e0e0e0; 
    border-radius: 6px; 
    margin-bottom: 8px; 
    background: #fff;
    transition: all 0.2s;
}
.product-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.product-item.dragging { opacity: 0.5; background: #f5f5f5; }

.drag-handle { 
    cursor: grab; 
    padding: 0 10px; 
    color: #999; 
    font-size: 1.2rem;
}
.drag-handle:active { cursor: grabbing; }

.product-img { 
    width: 50px; 
    height: 50px; 
    object-fit: cover; 
    border-radius: 4px; 
    margin-right: 15px; 
}
.product-info { flex: 1; }
.product-info h4 { margin: 0 0 4px 0; font-size: 1rem; color: #333; }
.product-info small { color: #666; }

.toggle-switch { position: relative; width: 50px; height: 26px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { 
    position: absolute; 
    cursor: pointer; 
    top: 0; left: 0; right: 0; bottom: 0; 
    background-color: #ccc; 
    transition: .3s; 
    border-radius: 26px; 
}
.toggle-slider:before { 
    position: absolute; 
    content: ""; 
    height: 20px; 
    width: 20px; 
    left: 3px; 
    bottom: 3px; 
    background-color: white; 
    transition: .3s; 
    border-radius: 50%; 
}
input:checked + .toggle-slider { background-color: #2d5a27; }
input:checked + .toggle-slider:before { transform: translateX(24px); }

.order-badge { 
    background: #1A3C34; 
    color: #fff; 
    padding: 4px 10px; 
    border-radius: 4px; 
    font-size: 0.85rem; 
    margin-right: 10px;
}

.all-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}
.all-product-card {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #fff;
}
.all-product-card.selected { border-color: #2d5a27; background: #f0f7f0; }
</style>

<div class="fh-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="color: #1A3C34; margin: 0;"><i class="fas fa-leaf text-success me-2"></i>Freshly Harvested</h2>
            <p class="text-muted mb-0">Manage products shown in the Freshly Harvested section</p>
        </div>
        <a href="<?= base_url('admin/setup_freshly_harvested.php'); ?>" class="btn btn-outline-success">
            <i class="fas fa-database me-1"></i> Run Setup
        </a>
    </div>
    
    <?php if (!$columnsExist): ?>
    <div class="alert alert-warning">
        <strong><i class="fas fa-exclamation-triangle me-2"></i>Setup Required!</strong><br>
        Please <a href="<?= base_url('admin/setup_freshly_harvested.php'); ?>">run the setup</a> to add the required database columns.
    </div>
    <?php else: ?>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?>">
        <?= htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Currently Featured Products (Drag to reorder) -->
        <div class="col-lg-6 mb-4">
            <div class="fh-card">
                <div class="fh-card-header">
                    <h3><i class="fas fa-star text-warning me-2"></i>Featured Products</h3>
                    <span class="badge bg-success"><?= count($freshlyHarvestedProducts); ?> products</span>
                </div>
                <div class="fh-card-body">
                    <?php if (empty($freshlyHarvestedProducts)): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-info-circle me-2"></i>No products selected yet.<br>
                        <small>Enable products from the list on the right.</small>
                    </p>
                    <?php else: ?>
                    <p class="text-muted small mb-3"><i class="fas fa-grip-vertical me-1"></i> Drag to reorder</p>
                    <ul class="product-list" id="sortableProducts">
                        <?php foreach ($freshlyHarvestedProducts as $index => $product): ?>
                        <li class="product-item" data-id="<?= $product['id']; ?>">
                            <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                            <span class="order-badge">#<?= $index + 1; ?></span>
                            <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="" class="product-img">
                            <div class="product-info">
                                <h4><?= htmlspecialchars($product['name']); ?></h4>
                                <small><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></small>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked onchange="toggleProduct(<?= $product['id']; ?>, this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- All Products (Select to add) -->
        <div class="col-lg-6 mb-4">
            <div class="fh-card">
                <div class="fh-card-header">
                    <h3><i class="fas fa-boxes me-2"></i>All Products</h3>
                    <input type="text" id="productSearch" class="form-control form-control-sm" style="max-width: 200px;" placeholder="Search products...">
                </div>
                <div class="fh-card-body" style="max-height: 600px; overflow-y: auto;">
                    <div class="all-products-grid" id="allProductsGrid">
                        <?php foreach ($allProducts as $product): ?>
                        <?php $isSelected = !empty($product['is_freshly_harvested']); ?>
                        <div class="all-product-card <?= $isSelected ? 'selected' : ''; ?>" data-name="<?= strtolower($product['name']); ?>">
                            <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" alt="" class="product-img">
                            <div class="product-info">
                                <h4><?= htmlspecialchars($product['name']); ?></h4>
                                <small><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></small>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" <?= $isSelected ? 'checked' : ''; ?> onchange="toggleProduct(<?= $product['id']; ?>, this.checked, this)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// Initialize drag & drop
document.addEventListener('DOMContentLoaded', function() {
    const sortable = document.getElementById('sortableProducts');
    if (sortable) {
        new Sortable(sortable, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function() {
                updateOrder();
            }
        });
    }
    
    // Product search
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.all-product-card').forEach(card => {
                const name = card.dataset.name;
                card.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });
    }
});

// Toggle product in Freshly Harvested
function toggleProduct(productId, enabled, element) {
    fetch('<?= base_url('admin/manage_freshly_harvested.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_action=toggle_product&product_id=${productId}&enabled=${enabled ? 1 : 0}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Reload page to update both lists
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err));
}

// Update display order
function updateOrder() {
    const items = document.querySelectorAll('#sortableProducts .product-item');
    const orders = [];
    
    items.forEach((item, index) => {
        orders.push({ id: item.dataset.id, order: index + 1 });
        // Update visual badge
        const badge = item.querySelector('.order-badge');
        if (badge) badge.textContent = '#' + (index + 1);
    });
    
    fetch('<?= base_url('admin/manage_freshly_harvested.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_action=update_order&orders=${JSON.stringify(orders)}`
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert('Error updating order: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err));
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
