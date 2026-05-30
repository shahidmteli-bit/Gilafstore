<?php
/**
 * ADMIN: Manage Promotions
 * Full CRUD interface for promotional system
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Manage Promotions';
$adminPage = 'manage_promotions';

$db = get_db_connection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $promoName = $_POST['promo_name'] ?? '';
        $promoType = $_POST['promo_type'] ?? 'discount';
        $promoMessage = $_POST['promo_message'] ?? '';
        $promoBadge = $_POST['promo_badge'] ?? '';
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $minOrderValue = (float)($_POST['min_order_value'] ?? 0);
        $maxDiscount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $couponCode = $_POST['coupon_code'] ?? null;
        
        $targetType = $_POST['target_type'] ?? 'all';
        $targetIds = !empty($_POST['target_ids']) ? json_encode(array_map('intval', explode(',', $_POST['target_ids']))) : null;
        
        $showOnHomepage = isset($_POST['show_on_homepage']) ? 1 : 0;
        $showOnProductPage = isset($_POST['show_on_product_page']) ? 1 : 0;
        $showOnCart = isset($_POST['show_on_cart']) ? 1 : 0;
        $showOnCheckout = isset($_POST['show_on_checkout']) ? 1 : 0;
        $showExitIntent = isset($_POST['show_exit_intent']) ? 1 : 0;
        $showStickyMobile = isset($_POST['show_sticky_mobile']) ? 1 : 0;
        
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $priority = (int)($_POST['priority'] ?? 0);
        
        $bannerColor = $_POST['banner_color'] ?? '#FF6B6B';
        $textColor = $_POST['text_color'] ?? '#FFFFFF';
        $icon = $_POST['icon'] ?? 'tag';
        
        $showCountdown = isset($_POST['show_countdown']) ? 1 : 0;
        $urgencyMessage = $_POST['urgency_message'] ?? null;
        $stockThreshold = !empty($_POST['stock_threshold']) ? (int)$_POST['stock_threshold'] : null;
        
        if ($action === 'create') {
            $sql = "INSERT INTO promotions (promo_name, promo_type, promo_message, promo_badge, discount_type, discount_value, min_order_value, max_discount, coupon_code, target_type, target_ids, show_on_homepage, show_on_product_page, show_on_cart, show_on_checkout, show_exit_intent, show_sticky_mobile, start_date, end_date, is_active, priority, banner_color, text_color, icon, show_countdown, urgency_message, stock_threshold)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$promoName, $promoType, $promoMessage, $promoBadge, $discountType, $discountValue, $minOrderValue, $maxDiscount, $couponCode, $targetType, $targetIds, $showOnHomepage, $showOnProductPage, $showOnCart, $showOnCheckout, $showExitIntent, $showStickyMobile, $startDate, $endDate, $isActive, $priority, $bannerColor, $textColor, $icon, $showCountdown, $urgencyMessage, $stockThreshold]);
            $success = "Promotion created successfully!";
        } else {
            $sql = "UPDATE promotions SET promo_name=?, promo_type=?, promo_message=?, promo_badge=?, discount_type=?, discount_value=?, min_order_value=?, max_discount=?, coupon_code=?, target_type=?, target_ids=?, show_on_homepage=?, show_on_product_page=?, show_on_cart=?, show_on_checkout=?, show_exit_intent=?, show_sticky_mobile=?, start_date=?, end_date=?, is_active=?, priority=?, banner_color=?, text_color=?, icon=?, show_countdown=?, urgency_message=?, stock_threshold=? WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$promoName, $promoType, $promoMessage, $promoBadge, $discountType, $discountValue, $minOrderValue, $maxDiscount, $couponCode, $targetType, $targetIds, $showOnHomepage, $showOnProductPage, $showOnCart, $showOnCheckout, $showExitIntent, $showStickyMobile, $startDate, $endDate, $isActive, $priority, $bannerColor, $textColor, $icon, $showCountdown, $urgencyMessage, $stockThreshold, $id]);
            $success = "Promotion updated successfully!";
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Promotion deleted successfully!";
    } elseif ($action === 'toggle_active') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("UPDATE promotions SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Promotion status updated!";
    }
}

// Get all promotions
$promotions = $db->query("SELECT * FROM promotions ORDER BY priority DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get categories and products for targeting
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name FROM products ORDER BY name LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
<div class="container-fluid">

<style>
    .promo-table { width: 100%; }
    .promo-table thead { background: #1a3c34; color: #fff; }
    .promo-table th, .promo-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
    .promo-table tbody tr:hover { background: #f9fafb; }
    .btn-icon { background: none; border: none; padding: 6px 8px; cursor: pointer; color: #667eea; font-size: 15px; }
    .btn-icon.btn-danger { color: #ef4444; }
    .promo-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
    .promo-modal-content { background: #fff; margin: 40px auto; padding: 30px; border-radius: 12px; max-width: 900px; max-height: 90vh; overflow-y: auto; }
    .promo-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; }
    .promo-close:hover { color: #000; }
    .promo-form-group { margin-bottom: 16px; }
    .promo-form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 14px; }
    .promo-form-group input, .promo-form-group select, .promo-form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
    .promo-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
    .promo-checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 16px; }
    .promo-checkbox-grid label { display: flex; align-items: center; gap: 6px; font-size: 14px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-semibold mb-0"><i class="fas fa-bullhorn me-2 text-primary"></i>Manage Promotions</h4>
        <p class="text-muted mb-0">Create and manage promotional campaigns</p>
    </div>
    <button class="btn btn-primary rounded-pill shadow-sm" onclick="showPromoModal()">
        <i class="fas fa-plus me-1"></i> Create Promotion
    </button>
</div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="promo-stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-bullhorn"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= count($promotions) ?></div>
                <div class="stat-label">Total Promotions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #10B981;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= count(array_filter($promotions, fn($p) => $p['is_active'])) ?></div>
                <div class="stat-label">Active Promotions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #F59E0B;"><i class="fas fa-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= array_sum(array_column($promotions, 'views')) ?></div>
                <div class="stat-label">Total Views</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #EF4444;"><i class="fas fa-mouse-pointer"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= array_sum(array_column($promotions, 'clicks')) ?></div>
                <div class="stat-label">Total Clicks</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <table class="table promo-table mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Discount</th>
                    <th>Display On</th>
                    <th>Schedule</th>
                    <th>Stats</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                        <td><?= $promo['id'] ?></td>
                        <td><strong><?= htmlspecialchars($promo['promo_name']) ?></strong></td>
                        <td><span class="badge badge-<?= $promo['promo_type'] ?>"><?= ucfirst(str_replace('_', ' ', $promo['promo_type'])) ?></span></td>
                        <td><?= htmlspecialchars(substr($promo['promo_message'], 0, 50)) ?>...</td>
                        <td>
                            <?php if ($promo['discount_type'] === 'percentage'): ?>
                                <?= $promo['discount_value'] ?>%
                            <?php else: ?>
                                ₹<?= $promo['discount_value'] ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="display-badges">
                                <?php if ($promo['show_on_homepage']): ?><span class="mini-badge">Home</span><?php endif; ?>
                                <?php if ($promo['show_on_product_page']): ?><span class="mini-badge">Product</span><?php endif; ?>
                                <?php if ($promo['show_on_cart']): ?><span class="mini-badge">Cart</span><?php endif; ?>
                                <?php if ($promo['show_on_checkout']): ?><span class="mini-badge">Checkout</span><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($promo['start_date'] && $promo['end_date']): ?>
                                <?= date('M d', strtotime($promo['start_date'])) ?> - <?= date('M d', strtotime($promo['end_date'])) ?>
                            <?php else: ?>
                                Always Active
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                Views: <?= $promo['views'] ?><br>
                                Clicks: <?= $promo['clicks'] ?>
                            </small>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                <button type="submit" class="status-toggle <?= $promo['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $promo['is_active'] ? 'Active' : 'Inactive' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <button class="btn-icon" onclick='editPromo(<?= json_encode($promo) ?>)' title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this promotion?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                <button type="submit" class="btn-icon btn-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<!-- Promo Modal -->
<div id="promoModal" class="promo-modal">
    <div class="promo-modal-content">
        <span class="promo-close" onclick="closePromoModal()">&times;</span>
        <h2 id="modalTitle">Create Promotion</h2>
        
        <form method="POST" id="promoForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="promoId">
            
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label>Promotion Name *</label>
                    <input type="text" name="promo_name" id="promo_name" required>
                </div>
                
                <div class="promo-form-group">
                    <label>Promotion Type *</label>
                    <select name="promo_type" id="promo_type" required>
                        <option value="discount">Discount</option>
                        <option value="free_shipping">Free Shipping</option>
                        <option value="combo">Combo Offer</option>
                        <option value="first_order">First Order</option>
                        <option value="bundle">Bundle</option>
                        <option value="seasonal">Seasonal</option>
                        <option value="best_seller">Best Seller</option>
                        <option value="buy_more_save_more">Buy More Save More</option>
                    </select>
                </div>
            </div>
            
            <div class="promo-form-group">
                <label>Promotion Message *</label>
                <textarea name="promo_message" id="promo_message" rows="2" required></textarea>
            </div>
            
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label>Badge Text</label>
                    <input type="text" name="promo_badge" id="promo_badge" placeholder="e.g., LIMITED TIME">
                </div>
                
                <div class="promo-form-group">
                    <label>Priority (Higher = Shows First)</label>
                    <input type="number" name="priority" id="priority" value="0">
                </div>
            </div>
            
            <h3>Discount Settings</h3>
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label>Discount Type</label>
                    <select name="discount_type" id="discount_type">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                        <option value="free_shipping">Free Shipping</option>
                    </select>
                </div>
                
                <div class="promo-form-group">
                    <label>Discount Value</label>
                    <input type="number" step="0.01" name="discount_value" id="discount_value" value="0">
                </div>
                
                <div class="promo-form-group">
                    <label>Min Order Value (₹)</label>
                    <input type="number" step="0.01" name="min_order_value" id="min_order_value" value="0">
                </div>
                
                <div class="promo-form-group">
                    <label>Max Discount (₹)</label>
                    <input type="number" step="0.01" name="max_discount" id="max_discount">
                </div>
            </div>
            
            <div class="promo-form-group">
                <label>Coupon Code (Optional)</label>
                <input type="text" name="coupon_code" id="coupon_code">
            </div>
            
            <h3>Targeting</h3>
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label>Target Type</label>
                    <select name="target_type" id="target_type">
                        <option value="all">All Products</option>
                        <option value="category">Specific Categories</option>
                        <option value="product">Specific Products</option>
                        <option value="new_users">New Users</option>
                        <option value="returning_users">Returning Users</option>
                    </select>
                </div>
                
                <div class="promo-form-group">
                    <label>Target IDs (comma-separated)</label>
                    <input type="text" name="target_ids" id="target_ids" placeholder="e.g., 1,2,3">
                </div>
            </div>
            
            <h3>Display Settings</h3>
            <div class="promo-checkbox-grid">
                <label><input type="checkbox" name="show_on_homepage" id="show_on_homepage"> Show on Homepage</label>
                <label><input type="checkbox" name="show_on_product_page" id="show_on_product_page"> Show on Product Pages</label>
                <label><input type="checkbox" name="show_on_cart" id="show_on_cart"> Show on Cart</label>
                <label><input type="checkbox" name="show_on_checkout" id="show_on_checkout"> Show on Checkout</label>
                <label><input type="checkbox" name="show_exit_intent" id="show_exit_intent"> Show in Exit Intent</label>
                <label><input type="checkbox" name="show_sticky_mobile" id="show_sticky_mobile"> Sticky Mobile Banner</label>
            </div>
            
            <h3>Schedule</h3>
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label>Start Date</label>
                    <input type="datetime-local" name="start_date" id="start_date">
                </div>
                
                <div class="promo-form-group">
                    <label>End Date</label>
                    <input type="datetime-local" name="end_date" id="end_date">
                </div>
            </div>
            
            <h3>Design</h3>
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label>Banner Color</label>
                    <input type="color" name="banner_color" id="banner_color" value="#FF6B6B">
                </div>
                
                <div class="promo-form-group">
                    <label>Text Color</label>
                    <input type="color" name="text_color" id="text_color" value="#FFFFFF">
                </div>
                
                <div class="promo-form-group">
                    <label>Icon (Font Awesome)</label>
                    <input type="text" name="icon" id="icon" value="tag" placeholder="e.g., tag, gift, fire">
                </div>
            </div>
            
            <h3>Urgency Settings</h3>
            <div class="promo-form-grid">
                <div class="promo-form-group">
                    <label><input type="checkbox" name="show_countdown" id="show_countdown"> Show Countdown Timer</label>
                </div>
                
                <div class="promo-form-group">
                    <label>Urgency Message</label>
                    <input type="text" name="urgency_message" id="urgency_message" placeholder="e.g., Hurry! Limited stock">
                </div>
                
                <div class="promo-form-group">
                    <label>Stock Urgency Threshold</label>
                    <input type="number" name="stock_threshold" id="stock_threshold" placeholder="e.g., 10">
                </div>
            </div>
            
            <div class="promo-form-group">
                <label><input type="checkbox" name="is_active" id="is_active" checked> Active</label>
            </div>
            
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save Promotion</button>
                <button type="button" class="btn btn-secondary" onclick="closePromoModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.promo-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 24px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1a1a1a;
}

.stat-label {
    font-size: 14px;
    color: #666;
}

.display-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.mini-badge {
    background: #e5e7eb;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.status-toggle {
    padding: 6px 16px;
    border-radius: 20px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.status-toggle.active {
    background: #10B981;
    color: #fff;
}

.status-toggle.inactive {
    background: #EF4444;
    color: #fff;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.checkbox-grid label {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<script>
function showPromoModal() {
    document.getElementById('promoModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Create Promotion';
    document.getElementById('formAction').value = 'create';
    document.getElementById('promoForm').reset();
}

function closePromoModal() {
    document.getElementById('promoModal').style.display = 'none';
}

function editPromo(promo) {
    document.getElementById('promoModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Edit Promotion';
    document.getElementById('formAction').value = 'update';
    
    // Populate form
    document.getElementById('promoId').value = promo.id;
    document.getElementById('promo_name').value = promo.promo_name;
    document.getElementById('promo_type').value = promo.promo_type;
    document.getElementById('promo_message').value = promo.promo_message;
    document.getElementById('promo_badge').value = promo.promo_badge || '';
    document.getElementById('discount_type').value = promo.discount_type;
    document.getElementById('discount_value').value = promo.discount_value;
    document.getElementById('min_order_value').value = promo.min_order_value;
    document.getElementById('max_discount').value = promo.max_discount || '';
    document.getElementById('coupon_code').value = promo.coupon_code || '';
    document.getElementById('target_type').value = promo.target_type;
    document.getElementById('target_ids').value = promo.target_ids ? JSON.parse(promo.target_ids).join(',') : '';
    document.getElementById('show_on_homepage').checked = promo.show_on_homepage == 1;
    document.getElementById('show_on_product_page').checked = promo.show_on_product_page == 1;
    document.getElementById('show_on_cart').checked = promo.show_on_cart == 1;
    document.getElementById('show_on_checkout').checked = promo.show_on_checkout == 1;
    document.getElementById('show_exit_intent').checked = promo.show_exit_intent == 1;
    document.getElementById('show_sticky_mobile').checked = promo.show_sticky_mobile == 1;
    document.getElementById('start_date').value = promo.start_date ? promo.start_date.replace(' ', 'T') : '';
    document.getElementById('end_date').value = promo.end_date ? promo.end_date.replace(' ', 'T') : '';
    document.getElementById('priority').value = promo.priority;
    document.getElementById('banner_color').value = promo.banner_color;
    document.getElementById('text_color').value = promo.text_color;
    document.getElementById('icon').value = promo.icon;
    document.getElementById('show_countdown').checked = promo.show_countdown == 1;
    document.getElementById('urgency_message').value = promo.urgency_message || '';
    document.getElementById('stock_threshold').value = promo.stock_threshold || '';
    document.getElementById('is_active').checked = promo.is_active == 1;
}

window.onclick = function(event) {
    const modal = document.getElementById('promoModal');
    if (event.target == modal) {
        closePromoModal();
    }
}
</script>

</div>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
