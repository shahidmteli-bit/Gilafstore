<?php
/**
 * Sales Executive Portal - New Order
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/sales_pricing_helper.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'New Order';
$currentPage = 'new_order';

// Pre-selected party
$partyId = (int)($_GET['party_id'] ?? 0);
$selectedParty = null;
if ($partyId) {
    $selectedParty = db_fetch('SELECT * FROM sales_parties WHERE id = ? AND created_by = ?', [$partyId, $execId]);
    // Check if party is blocked
    if ($selectedParty && !empty($selectedParty['is_blocked'])) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'This party is blocked. Reason: ' . ($selectedParty['blocked_reason'] ?? 'Contact admin.')];
        $selectedParty = null;
        $partyId = 0;
    }
}
$partyProfileType = $selectedParty['profile_type'] ?? 'wholesaler';

// Fetch all parties for dropdown
// Exclude blocked parties from the order dropdown
$allParties = [];
try {
    $allParties = db_fetch_all('SELECT id, shop_name, owner_name, outstanding_amount, credit_limit, profile_type FROM sales_parties WHERE created_by = ? AND is_active = 1 AND is_blocked = 0 ORDER BY shop_name ASC', [$execId]);
} catch (PDOException $epb) {
    // Fallback if is_blocked column doesn't exist yet
    $allParties = db_fetch_all('SELECT id, shop_name, owner_name, outstanding_amount, credit_limit, profile_type FROM sales_parties WHERE created_by = ? AND is_active = 1 ORDER BY shop_name ASC', [$execId]);
}

// Fetch products with stock + profile-based pricing
$searchProduct = trim($_GET['q'] ?? '');
$categoryFilter = (int)($_GET['cat'] ?? 0);

// Map profile type to price column (with proper retail support)
$priceColMap = [
    'wholesaler'  => 'COALESCE(NULLIF(pw.wholesale_price, 0), pw.price, p.price)',
    'distributor' => 'COALESCE(NULLIF(pw.distributor_price, 0), pw.price, p.price)',
    'franchise'   => 'COALESCE(NULLIF(pw.franchise_price, 0), pw.price, p.price)',
    'retailer'    => 'COALESCE(NULLIF(pw.retail_price, 0), pw.price, p.price)',
];
$priceCol = $priceColMap[$partyProfileType] ?? $priceColMap['wholesaler'];

$productSql = "SELECT p.id, p.name, p.price, p.image, p.stock,
        pw.id as weight_id, pw.display_weight, pw.price as weight_price,
        {$priceCol} as profile_price
        FROM products p
        INNER JOIN product_weights pw ON pw.product_id = p.id
        WHERE 1=1";
$productParams = [];

if ($searchProduct) {
    $productSql .= ' AND p.name LIKE ?';
    $like = '%' . $searchProduct . '%';
    $productParams[] = $like;
}
if ($categoryFilter) {
    $productSql .= ' AND p.category_id = ?';
    $productParams[] = $categoryFilter;
}
$productSql .= ' ORDER BY p.name ASC, pw.sort_order ASC, pw.weight_value ASC LIMIT 50';
$products = db_fetch_all($productSql, $productParams);

// Fetch categories for filter
$categories = [];
try {
    $categories = db_fetch_all('SELECT id, name FROM categories ORDER BY name ASC');
} catch (PDOException $e) { /* ignore */ }

// Generate order form nonce to prevent double-submit
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['order_nonce'] = bin2hex(random_bytes(16));
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $orderPartyId = (int)($_POST['party_id'] ?? 0);
    $items = json_decode($_POST['cart_items'] ?? '[]', true);
    $notes = trim($_POST['executive_notes'] ?? '');
    $orderType = $_POST['order_type'] ?? 'new_order';

    // Nonce check — prevent double-submit / page refresh re-post
    $submittedNonce = $_POST['order_nonce'] ?? '';
    if (empty($submittedNonce) || $submittedNonce !== ($_SESSION['order_nonce'] ?? '')) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Duplicate submission detected. If you meant to place another order, please reload the page.'];
        header('Location: ' . sales_base_url('orders.php'));
        exit;
    }
    // Invalidate nonce immediately so it can't be reused
    unset($_SESSION['order_nonce']);

    if (!$orderPartyId) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Please select a party.'];
    } elseif (empty($items)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Please add at least one product.'];
    } else {
        $party = db_fetch('SELECT * FROM sales_parties WHERE id = ? AND created_by = ?', [$orderPartyId, $execId]);
        if (!$party) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Invalid party selected.'];
        } elseif (!empty($party['is_blocked'])) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Cannot place order — this party is blocked. Reason: ' . ($party['blocked_reason'] ?? 'Contact admin.')];
        } else {
            // Calculate total using enhanced pricing helper (with decimal precision)
            $orderProfileType = $party['profile_type'] ?? 'wholesaler';
            
            $subtotal = 0;
            $validItems = [];
            
            // Map profile type to price column for fallback
            $profilePriceCol = [
                'wholesaler'  => 'COALESCE(NULLIF(pw.wholesale_price, 0), pw.price, p.price)',
                'distributor' => 'COALESCE(NULLIF(pw.distributor_price, 0), pw.price, p.price)',
                'franchise'   => 'COALESCE(NULLIF(pw.franchise_price, 0), pw.price, p.price)',
                'retailer'    => 'COALESCE(NULLIF(pw.retail_price, 0), pw.price, p.price)',
            ];
            $pCol = $profilePriceCol[$orderProfileType] ?? $profilePriceCol['wholesaler'];
            
            foreach ($items as $item) {
                $weightId = (int)($item['weight_id'] ?? 0);
                if ($weightId > 0) {
                    $price = 0;
                    $product = null;
                    
                    // Try using pricing helper first (enhanced pricing with GST support)
                    try {
                        $pricingData = get_party_price($weightId, $orderProfileType, true);
                        if ($pricingData && $pricingData['total_price'] > 0) {
                            $price = $pricingData['total_price'];
                            $product = db_fetch("SELECT p.id, p.name, pw.display_weight FROM products p INNER JOIN product_weights pw ON pw.product_id = p.id WHERE p.id = ? AND pw.id = ?", [(int)$item['product_id'], $weightId]);
                        }
                    } catch (Exception $e) {
                        // Pricing helper failed, will use fallback
                    }
                    
                    // Fallback: Use direct SQL query if helper failed or returned no price
                    if ($price == 0 || !$product) {
                        $product = db_fetch("SELECT p.id, p.name, p.price, p.stock, pw.display_weight, {$pCol} as profile_price FROM products p INNER JOIN product_weights pw ON pw.product_id = p.id WHERE p.id = ? AND pw.id = ?", [(int)$item['product_id'], $weightId]);
                        if ($product) {
                            $price = (float)($product['profile_price'] ?? $product['price'] ?? 0);
                        }
                    }
                    
                    if ($product && $price > 0) {
                        $qty = max(1, (int)$item['quantity']);
                        $originalPrice = $price;
                        
                        // Apply custom price if provided by salesperson
                        $customPrice = isset($item['custom_price']) ? (float)$item['custom_price'] : 0;
                        if ($customPrice > 0) {
                            $price = $customPrice;
                        }
                        
                        $lineTotal = round($price * $qty, 2);
                        $subtotal = round($subtotal + $lineTotal, 2);
                        
                        $itemName = $product['name'];
                        if (!empty($product['display_weight'])) {
                            $itemName .= ' — ' . $product['display_weight'];
                        }
                        
                        $validItems[] = [
                            'product_id' => $product['id'],
                            'product_name' => $itemName,
                            'sku' => '',
                            'price' => number_format($price, 2, '.', ''),
                            'original_price' => number_format($originalPrice, 2, '.', ''),
                            'is_custom_price' => ($customPrice > 0) ? 1 : 0,
                            'quantity' => $qty,
                            'total' => number_format($lineTotal, 2, '.', ''),
                        ];
                    }
                }
            }

            // Credit limit check
            $newOutstanding = $party['outstanding_amount'] + $subtotal;
            if ($party['credit_limit'] > 0 && $newOutstanding > $party['credit_limit'] && $orderType === 'new_order') {
                $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Credit Limit Exceeded — Outstanding: ₹' . number_format($party['outstanding_amount'], 0) . ' + New Order: ₹' . number_format($subtotal, 0) . ' exceeds limit of ₹' . number_format($party['credit_limit'], 0) . '. Please clear dues before placing new order.'];
            } else {
                // Server-side duplicate check: same party + same amount within last 2 minutes
                $dupCheck = db_fetch(
                    'SELECT id, order_number FROM sales_orders WHERE executive_id = ? AND party_id = ? AND total_amount = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1',
                    [$execId, $orderPartyId, $subtotal]
                );
                if ($dupCheck) {
                    $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Duplicate order detected! Order ' . $dupCheck['order_number'] . ' with the same party and amount was placed moments ago. If this is intentional, please wait 2 minutes.'];
                    header('Location: ' . sales_base_url('order_detail.php?id=' . $dupCheck['id']));
                    exit;
                }

                // Auto-activate party if inactive (reactivate when order is placed)
                if (!$party['is_active']) {
                    db_query('UPDATE sales_parties SET is_active = 1 WHERE id = ?', [$orderPartyId]);
                }
                
                // Create order
                $db = get_db_connection();

                // Ensure custom price columns exist (outside transaction — ALTER TABLE auto-commits)
                try {
                    $db->exec("ALTER TABLE sales_order_items ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2) DEFAULT NULL AFTER price");
                    $db->exec("ALTER TABLE sales_order_items ADD COLUMN IF NOT EXISTS is_custom_price TINYINT(1) DEFAULT 0 AFTER original_price");
                } catch (Exception $colErr) { /* columns may already exist */ }

                $db->beginTransaction();
                try {
                    $orderNumber = sales_generate_order_number();
                    db_query('INSERT INTO sales_orders (order_number, executive_id, party_id, order_type, subtotal, total_amount, status, district, location, executive_notes) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                        $orderNumber, $execId, $orderPartyId, $orderType, $subtotal, $subtotal, 'pending',
                        $exec['district'], $exec['location'], $notes
                    ]);
                    $orderId = (int)$db->lastInsertId();

                    foreach ($validItems as $vi) {
                        db_query('INSERT INTO sales_order_items (order_id, product_id, product_name, sku, price, original_price, is_custom_price, quantity, total) VALUES (?,?,?,?,?,?,?,?,?)', [
                            $orderId, $vi['product_id'], $vi['product_name'], $vi['sku'], $vi['price'], $vi['original_price'], $vi['is_custom_price'], $vi['quantity'], $vi['total']
                        ]);
                    }

                    $db->commit();
                    $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Order ' . $orderNumber . ' submitted successfully! Pending admin approval.'];
                    header('Location: ' . sales_base_url('order_detail.php?id=' . $orderId));
                    exit;
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Order failed: ' . $e->getMessage()];
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<form method="POST" id="orderForm">
    <input type="hidden" name="submit_order" value="1">
    <input type="hidden" name="order_nonce" value="<?= htmlspecialchars($_SESSION['order_nonce'] ?? '') ?>">
    <input type="hidden" name="cart_items" id="cartItemsInput" value="[]">
    <input type="hidden" name="order_type" value="new_order">

    <!-- Step 1: Select Party -->
    <div class="sp-card sp-mb-24">
        <div class="sp-card-header">
            <h3><i class="fas fa-user-check"></i> Select Party</h3>
            <a href="<?= sales_base_url('parties.php?action=create') ?>" class="sp-btn sp-btn-gold sp-btn-sm">
                <i class="fas fa-user-plus"></i> New Party
            </a>
        </div>

        <!-- Hidden field for form submission -->
        <input type="hidden" name="party_id" id="partyIdHidden" value="<?= $partyId ?>">

        <!-- Search + Scan Row -->
        <div style="display:flex;gap:8px;margin-bottom:10px;">
            <div class="sp-search-bar" style="flex:1;margin-bottom:0;">
                <i class="fas fa-search"></i>
                <input type="text" id="partySearchInput" placeholder="Search by name, phone, or party code..." autocomplete="off">
            </div>
            <button type="button" class="sp-btn sp-btn-primary" onclick="openPartyScanner()" title="Scan QR">
                <i class="fas fa-qrcode"></i>
            </button>
        </div>
        <div id="partySearchResults" style="max-height:200px;overflow-y:auto;"></div>

        <!-- Selected Party Display -->
        <div id="selectedPartyCard" style="display:<?= $selectedParty ? 'block' : 'none' ?>;">
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;margin-bottom:12px;">
                <div style="width:40px;height:40px;background:var(--sp-primary);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0;">
                    <i class="fas fa-store"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:14px;" id="selectedPartyName"><?= $selectedParty ? htmlspecialchars($selectedParty['shop_name']) : '' ?></div>
                    <div style="font-size:12px;color:#6b7280;" id="selectedPartyMeta"><?= $selectedParty ? htmlspecialchars($selectedParty['owner_name'] . ' · ' . $selectedParty['phone']) : '' ?></div>
                </div>
                <button type="button" onclick="clearSelectedParty()" style="background:none;border:none;color:#ef4444;font-size:16px;cursor:pointer;padding:6px;"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <!-- Party Financial Info -->
        <div id="partyInfo" class="<?= $selectedParty ? '' : 'sp-hidden' ?>">
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <div style="background:#f9fafb;padding:10px 16px;border-radius:8px;flex:1;min-width:140px;">
                    <div class="sp-fs-sm sp-text-muted">Outstanding</div>
                    <div class="sp-fw-700 sp-text-danger" id="partyOutstanding">₹<?= $selectedParty ? number_format($selectedParty['outstanding_amount'], 0) : '0' ?></div>
                </div>
                <div style="background:#f9fafb;padding:10px 16px;border-radius:8px;flex:1;min-width:140px;">
                    <div class="sp-fs-sm sp-text-muted">Credit Limit</div>
                    <div class="sp-fw-700 sp-text-gold" id="partyCreditLimit">₹<?= $selectedParty ? number_format($selectedParty['credit_limit'], 0) : '0' ?></div>
                </div>
                <div style="background:#f9fafb;padding:10px 16px;border-radius:8px;flex:1;min-width:140px;">
                    <div class="sp-fs-sm sp-text-muted">Available Credit</div>
                    <div class="sp-fw-700 sp-text-success" id="partyAvailable">₹<?= $selectedParty ? number_format(max(0, $selectedParty['credit_limit'] - $selectedParty['outstanding_amount']), 0) : '0' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div id="partyScannerModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:20px;padding:20px;max-width:380px;width:100%;text-align:center;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;"><i class="fas fa-qrcode"></i> Scan Party QR</h3>
                <button type="button" onclick="closePartyScanner()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;"><i class="fas fa-times"></i></button>
            </div>
            <div id="orderScannerArea" style="width:100%;border-radius:12px;overflow:hidden;background:#000;"></div>
            <div id="orderScanStatus" style="margin-top:10px;font-size:13px;color:#6b7280;"></div>
        </div>
    </div>

    <!-- Step 2: Product Catalog + Cart -->
    <div class="sp-order-layout">
        <!-- Left: Products -->
        <div>
            <div class="sp-card sp-mb-0">
                <div class="sp-card-header">
                    <h3><i class="fas fa-boxes"></i> Product Catalog</h3>
                </div>
                <!-- Filters -->
                <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                    <div class="sp-search-bar" style="flex:1;min-width:200px;margin-bottom:0;">
                        <i class="fas fa-search"></i>
                        <input type="text" id="productSearch" placeholder="Search products..." value="<?= htmlspecialchars($searchProduct) ?>">
                    </div>
                    <select id="categoryFilter" class="sp-select" style="width:auto;min-width:160px;">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Product Grid -->
                <div class="sp-product-grid" id="productGrid">
                    <?php foreach ($products as $prod): 
                        $imgUrl = !empty($prod['image']) ? base_url('assets/images/products/' . $prod['image']) : '';
                        $inStock = ($prod['stock'] ?? 0) > 0;
                        $displayPrice = $prod['profile_price'] ?? $prod['weight_price'] ?? $prod['price'];
                        $retailPrice = $prod['weight_price'] ?? $prod['price'];
                        $weightLabel = $prod['display_weight'] ?? '';
                        $weightId = $prod['weight_id'] ?? 0;
                    ?>
                    <div class="sp-product-card" data-product-id="<?= $prod['id'] ?>" data-weight-id="<?= $weightId ?>" data-name="<?= htmlspecialchars($prod['name'] . ($weightLabel ? ' — ' . $weightLabel : '')) ?>" data-price="<?= $displayPrice ?>" data-retail-price="<?= $retailPrice ?>" data-sku="" data-stock="<?= $prod['stock'] ?? 0 ?>">
                        <div class="sp-product-card-body">
                            <?php if ($imgUrl): ?>
                                <img src="<?= $imgUrl ?>" alt="" class="sp-product-img" loading="lazy">
                            <?php else: ?>
                                <div class="sp-product-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:24px;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                            <div class="sp-product-details">
                                <h4><?= htmlspecialchars($prod['name']) ?></h4>
                                <?php if ($weightLabel): ?>
                                    <span style="display:inline-block;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;margin-bottom:4px;"><?= htmlspecialchars($weightLabel) ?></span>
                                <?php endif; ?>
                                <div class="sp-price">₹<?= number_format($displayPrice, 0) ?></div>
                                <?php if ($displayPrice < $retailPrice): ?>
                                    <div style="font-size:10px;color:#9ca3af;text-decoration:line-through;">MRP ₹<?= number_format($retailPrice, 0) ?></div>
                                <?php endif; ?>
                                <div class="sp-product-stock <?= $inStock ? 'in-stock' : 'out-of-stock' ?>">
                                    <?= $inStock ? '● In Stock (' . (int)$prod['stock'] . ')' : '● Out of Stock' ?>
                                </div>
                            </div>
                        </div>
                        <div class="sp-product-card-footer">
                            <div class="sp-qty-control">
                                <button type="button" class="sp-qty-btn" onclick="changeQty(this, -1)" <?= !$inStock ? 'disabled' : '' ?>>−</button>
                                <input type="number" class="sp-qty-input" value="0" min="0" max="<?= $prod['stock'] ?? 9999 ?>" <?= !$inStock ? 'disabled' : '' ?>>
                                <button type="button" class="sp-qty-btn" onclick="changeQty(this, 1)" <?= !$inStock ? 'disabled' : '' ?>>+</button>
                            </div>
                            <button type="button" class="sp-add-btn" onclick="addToCart(this)" <?= !$inStock ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : '' ?>>
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($products)): ?>
                    <div class="sp-empty">
                        <i class="fas fa-box-open"></i>
                        <h3>No products found</h3>
                        <p>Try a different search or category.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Cart -->
        <div>
            <div class="sp-cart-panel">
                <div class="sp-cart-header">
                    <h3><i class="fas fa-shopping-cart"></i> Order Cart <span class="sp-cart-count" id="cartCount">0</span></h3>
                    <button type="button" class="sp-btn sp-btn-outline sp-btn-sm" onclick="clearCart()" id="clearCartBtn" style="display:none;">Clear</button>
                </div>
                <div id="cartEmpty" class="sp-cart-empty">
                    <i class="fas fa-shopping-basket"></i>
                    Add products to start building the order
                </div>
                <div id="cartItems" class="sp-cart-items" style="display:none;"></div>
                <div id="cartFooter" class="sp-cart-footer" style="display:none;">
                    <div class="sp-cart-total">
                        <span>Total Amount</span>
                        <span id="cartTotal">₹0</span>
                    </div>
                    <div class="sp-form-group sp-mb-8">
                        <label>Notes (Optional)</label>
                        <textarea name="executive_notes" class="sp-textarea" rows="2" placeholder="Any special instructions..."></textarea>
                    </div>
                    <button type="submit" class="sp-btn sp-btn-primary sp-btn-lg sp-btn-block" id="submitOrderBtn">
                        <i class="fas fa-paper-plane"></i> Submit Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Credit Limit Exceeded Modal -->
<div class="sp-modal-overlay" id="creditModal">
    <div class="sp-modal">
        <div class="sp-modal-icon danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Credit Limit Exceeded</h3>
        <p id="creditModalMsg">Please clear dues before placing new order.</p>
        <button type="button" class="sp-btn sp-btn-primary" onclick="document.getElementById('creditModal').classList.remove('active')">
            <i class="fas fa-check"></i> Understood
        </button>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// Cart State
let cart = [];
let partyOutstanding = 0;
let partyCreditLimit = 0;
let currentProfileType = '<?= $partyProfileType ?>';
let orderQrScanner = null;
let orderQrScanning = false;
var partyApiBase = '<?= sales_base_url("api_party_lookup.php") ?>';
var productApiBase = '<?= sales_base_url("api_products_enhanced.php") ?>';
var imgBase = '<?= base_url("assets/images/products/") ?>';
var profileLabels = { wholesaler: 'Wholesaler', distributor: 'Distributor', franchise: 'Franchise', retailer: 'Retailer' };

// ---- Party Search ----
let partySearchTimer;
document.getElementById('partySearchInput').addEventListener('input', function() {
    clearTimeout(partySearchTimer);
    var val = this.value.trim();
    if (val.length < 2) { document.getElementById('partySearchResults').innerHTML = ''; return; }
    partySearchTimer = setTimeout(function() {
        fetch(partyApiBase + '?search=' + encodeURIComponent(val))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var html = '';
                if (data.success && data.parties && data.parties.length > 0) {
                    data.parties.forEach(function(p) {
                        var pt = p.profile_type || 'wholesaler';
                        html += '<div onclick="selectPartyFromSearch(' + p.id + ',\'' + encodeURIComponent(p.shop_name) + '\',\'' + encodeURIComponent(p.owner_name) + '\',\'' + encodeURIComponent(p.phone || '') + '\',' + (p.outstanding_amount||0) + ',' + (p.credit_limit||0) + ',\'' + pt + '\')" style="padding:12px;border-bottom:1px solid #f3f4f6;cursor:pointer;display:flex;align-items:center;gap:10px;">';
                        html += '<div style="flex:1;"><div style="font-weight:600;font-size:13px;">' + p.shop_name + '</div>';
                        html += '<div style="font-size:11px;color:#6b7280;">' + p.owner_name + ' · ' + (p.phone||'') + '</div></div>';
                        html += '<div style="text-align:right;">';
                        html += '<span style="font-size:10px;background:rgba(26,60,52,0.08);color:#1A3C34;padding:3px 8px;border-radius:10px;display:block;margin-bottom:2px;">' + (p.party_code||'') + '</span>';
                        html += '<span style="font-size:9px;color:#7c3aed;font-weight:600;">' + (profileLabels[pt] || pt) + '</span>';
                        html += '</div></div>';
                    });
                } else {
                    html = '<div style="padding:12px;font-size:13px;color:#6b7280;text-align:center;">No parties found</div>';
                }
                document.getElementById('partySearchResults').innerHTML = html;
            });
    }, 400);
});

function selectPartyFromSearch(id, nameEnc, ownerEnc, phoneEnc, outstanding, credit, profileType) {
    var name = decodeURIComponent(nameEnc);
    var owner = decodeURIComponent(ownerEnc);
    var phone = decodeURIComponent(phoneEnc);
    document.getElementById('partyIdHidden').value = id;
    document.getElementById('selectedPartyName').textContent = name;
    document.getElementById('selectedPartyMeta').textContent = owner + ' · ' + phone + ' · ' + (profileLabels[profileType] || profileType);
    document.getElementById('selectedPartyCard').style.display = 'block';
    partyOutstanding = parseFloat(outstanding || 0);
    partyCreditLimit = parseFloat(credit || 0);
    var available = Math.max(0, partyCreditLimit - partyOutstanding);
    document.getElementById('partyOutstanding').textContent = '₹' + partyOutstanding.toLocaleString('en-IN');
    document.getElementById('partyCreditLimit').textContent = '₹' + partyCreditLimit.toLocaleString('en-IN');
    document.getElementById('partyAvailable').textContent = '₹' + available.toLocaleString('en-IN');
    document.getElementById('partyInfo').classList.remove('sp-hidden');
    document.getElementById('partySearchResults').innerHTML = '';
    document.getElementById('partySearchInput').value = '';

    // Reload product grid with profile-based pricing if profile changed
    if (profileType !== currentProfileType) {
        currentProfileType = profileType;
        reloadProductGrid();
    }
}

function reloadProductGrid() {
    var q = document.getElementById('productSearch').value;
    var cat = document.getElementById('categoryFilter').value;
    var grid = document.getElementById('productGrid');
    grid.innerHTML = '<div style="text-align:center;padding:30px;color:#6b7280;"><i class="fas fa-spinner fa-spin"></i> Loading ' + (profileLabels[currentProfileType] || '') + ' prices...</div>';

    fetch(productApiBase + '?profile_type=' + currentProfileType + '&q=' + encodeURIComponent(q) + '&cat=' + cat)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.products || data.products.length === 0) {
                grid.innerHTML = '<div style="text-align:center;padding:30px;color:#6b7280;"><i class="fas fa-box-open"></i> No products found</div>';
                return;
            }
            var html = '';
            // Flatten: render each weight as a separate card
            data.products.forEach(function(prod) {
                var weights = prod.weights && prod.weights.length > 0 ? prod.weights : [];
                weights.forEach(function(w) {
                    // Use total_price (base + GST) for accurate pricing
                    var price = parseFloat(w.total_price || 0);
                    var mrp = parseFloat(w.offline_mrp || 0);
                    var img = prod.image ? imgBase + prod.image : '';
                    var inStock = (parseInt(prod.stock) || 0) > 0;
                    var stockClass = inStock ? 'in-stock' : 'out-of-stock';
                    var stockText = inStock ? '● In Stock (' + prod.stock + ')' : '● Out of Stock';
                    var weightLabel = w.display_weight || '';
                    var cardName = shortName(prod.name, weightLabel);

                    html += '<div class="sp-product-card" data-product-id="' + prod.id + '" data-weight-id="' + (w.weight_id||0) + '" data-name="' + escHtml(cardName) + '" data-price="' + price.toFixed(2) + '" data-retail-price="' + mrp.toFixed(2) + '" data-sku="" data-stock="' + (prod.stock||0) + '">';
                    html += '<div class="sp-product-card-body">';
                    if (img) {
                        html += '<img src="' + img + '" alt="" class="sp-product-img" loading="lazy">';
                    } else {
                        html += '<div class="sp-product-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:24px;"><i class="fas fa-image"></i></div>';
                    }
                    html += '<div class="sp-product-details">';
                    html += '<h4>' + escHtml(shortName(prod.name, weightLabel)) + '</h4>';
                    if (weightLabel) {
                        html += '<span style="display:inline-block;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;margin-bottom:4px;">' + escHtml(weightLabel) + '</span>';
                    }
                    html += '<div class="sp-price">₹' + price.toFixed(2) + '</div>';
                    if (mrp > 0 && price < mrp) {
                        html += '<div style="font-size:10px;color:#9ca3af;text-decoration:line-through;">MRP ₹' + mrp.toFixed(2) + '</div>';
                    }
                    html += '<div class="sp-product-stock ' + stockClass + '">' + stockText + '</div>';
                    html += '</div></div>';
                    html += '<div class="sp-product-card-footer">';
                    html += '<div class="sp-qty-control">';
                    html += '<button type="button" class="sp-qty-btn" onclick="changeQty(this,-1)"' + (!inStock ? ' disabled' : '') + '>−</button>';
                    html += '<input type="number" class="sp-qty-input" value="0" min="0" max="' + (prod.stock||9999) + '"' + (!inStock ? ' disabled' : '') + '>';
                    html += '<button type="button" class="sp-qty-btn" onclick="changeQty(this,1)"' + (!inStock ? ' disabled' : '') + '>+</button>';
                    html += '</div>';
                    html += '<button type="button" class="sp-add-btn" onclick="addToCart(this)"' + (!inStock ? ' disabled style="opacity:0.4;cursor:not-allowed;"' : '') + '>';
                    html += '<i class="fas fa-plus"></i> Add</button>';
                    html += '</div></div>';
                });
            });
            grid.innerHTML = html;
        })
        .catch(function() {
            grid.innerHTML = '<div style="text-align:center;padding:30px;color:#dc2626;">Error loading products</div>';
        });
}

function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function shortName(name, weight) {
    var words = name.split(/[\s|,—–-]+/).filter(Boolean);
    var short = words.slice(0, 4).join(' ');
    if (weight) short += ' ' + weight;
    return short;
}

function clearSelectedParty() {
    document.getElementById('partyIdHidden').value = '';
    document.getElementById('selectedPartyCard').style.display = 'none';
    document.getElementById('partyInfo').classList.add('sp-hidden');
    partyOutstanding = 0;
    partyCreditLimit = 0;
}

// ---- QR Scanner for Party ----
function openPartyScanner() {
    document.getElementById('partyScannerModal').style.display = 'flex';
    document.getElementById('orderScanStatus').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting camera...';
    orderQrScanner = new Html5Qrcode('orderScannerArea');
    orderQrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 200, height: 200 } },
        function(decodedText) {
            document.getElementById('orderScanStatus').innerHTML = '<span style="color:#059669;"><i class="fas fa-check-circle"></i> Scanned: ' + decodedText + '</span>';
            closePartyScanner();
            // Lookup party by scanned code
            fetch(partyApiBase + '?code=' + encodeURIComponent(decodedText.trim()))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.party) {
                        var p = data.party;
                        selectPartyFromSearch(p.id, encodeURIComponent(p.shop_name), encodeURIComponent(p.owner_name), encodeURIComponent(p.phone||''), p.outstanding_amount, p.credit_limit, p.profile_type || 'wholesaler');
                    } else {
                        alert('Party not found for code: ' + decodedText);
                    }
                });
        },
        function() {}
    ).then(function() {
        orderQrScanning = true;
        document.getElementById('orderScanStatus').innerHTML = '<span style="color:#059669;"><i class="fas fa-video"></i> Point at party QR code</span>';
    }).catch(function(err) {
        document.getElementById('orderScanStatus').innerHTML = '<span style="color:#dc2626;">Camera error: ' + err + '</span>';
    });
}

function closePartyScanner() {
    if (orderQrScanner && orderQrScanning) {
        orderQrScanner.stop().catch(function(){});
        orderQrScanning = false;
    }
    document.getElementById('partyScannerModal').style.display = 'none';
}

function changeQty(btn, delta) {
    const input = btn.parentElement.querySelector('.sp-qty-input');
    let val = parseInt(input.value) || 0;
    val = Math.max(0, val + delta);
    input.value = val;
}

function addToCart(btn) {
    const card = btn.closest('.sp-product-card');
    const productId = parseInt(card.dataset.productId);
    const weightId = parseInt(card.dataset.weightId) || 0;
    const name = card.dataset.name;
    const price = parseFloat(card.dataset.price);
    const sku = card.dataset.sku;
    const qty = parseInt(card.querySelector('.sp-qty-input').value) || 0;
    if (qty <= 0) { alert('Please set quantity before adding.'); return; }

    // Check if already in cart (match by product_id + weight_id)
    const existing = cart.find(c => c.product_id === productId && c.weight_id === weightId);
    if (existing) {
        existing.quantity += qty;
    } else {
        cart.push({ product_id: productId, weight_id: weightId, name, price, sku, quantity: qty });
    }

    // Visual feedback
    btn.classList.add('added');
    btn.innerHTML = '<i class="fas fa-check"></i> Added';
    setTimeout(() => {
        btn.classList.remove('added');
        btn.innerHTML = '<i class="fas fa-plus"></i> Add';
    }, 1200);

    renderCart();
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function toggleCustomPrice(idx) {
    var row = document.getElementById('customPriceRow_' + idx);
    if (row.style.display === 'none') {
        row.style.display = 'flex';
        row.querySelector('input').focus();
    } else {
        row.style.display = 'none';
        // Reset to original price
        cart[idx].custom_price = null;
        renderCart();
    }
}

function applyCustomPrice(idx) {
    var input = document.getElementById('customPriceInput_' + idx);
    var val = parseFloat(input.value);
    if (isNaN(val) || val <= 0) {
        alert('Please enter a valid price.');
        input.focus();
        return;
    }
    cart[idx].custom_price = val;
    renderCart();
}

function resetCustomPrice(idx) {
    cart[idx].custom_price = null;
    renderCart();
}

function renderCart() {
    var countEl = document.getElementById('cartCount');
    var emptyEl = document.getElementById('cartEmpty');
    var itemsEl = document.getElementById('cartItems');
    var footerEl = document.getElementById('cartFooter');
    var clearBtn = document.getElementById('clearCartBtn');
    var totalEl = document.getElementById('cartTotal');
    var hiddenInput = document.getElementById('cartItemsInput');

    countEl.textContent = cart.length;

    if (cart.length === 0) {
        emptyEl.style.display = '';
        itemsEl.style.display = 'none';
        footerEl.style.display = 'none';
        clearBtn.style.display = 'none';
        hiddenInput.value = '[]';
        return;
    }

    emptyEl.style.display = 'none';
    itemsEl.style.display = '';
    footerEl.style.display = '';
    clearBtn.style.display = '';

    var total = 0;
    var html = '';
    cart.forEach(function(item, idx) {
        var activePrice = item.custom_price || item.price;
        var lineTotal = parseFloat((activePrice * item.quantity).toFixed(2));
        total = parseFloat((total + lineTotal).toFixed(2));
        var isCustom = item.custom_price && item.custom_price !== item.price;

        html += '<div class="sp-cart-item" style="flex-wrap:wrap;">';
        html += '<span class="sp-cart-item-name">' + escHtml(item.name) + '</span>';
        html += '<span class="sp-cart-item-qty">×' + item.quantity + '</span>';
        html += '<span class="sp-cart-item-total">';
        if (isCustom) {
            html += '<span style="text-decoration:line-through;color:#9ca3af;font-size:11px;margin-right:4px;">₹' + item.price.toFixed(2) + '</span>';
            html += '<span style="color:#7c3aed;font-weight:700;">₹' + activePrice.toFixed(2) + '</span>';
        } else {
            html += '₹' + lineTotal.toFixed(2);
        }
        html += '</span>';
        html += '<button type="button" class="sp-cart-item-remove" onclick="removeFromCart(' + idx + ')"><i class="fas fa-trash-alt"></i></button>';

        // Custom Price toggle button
        html += '<div style="width:100%;display:flex;align-items:center;gap:6px;margin-top:4px;">';
        if (isCustom) {
            html += '<span style="font-size:10px;background:#f3e8ff;color:#7c3aed;padding:2px 8px;border-radius:6px;font-weight:600;"><i class="fas fa-tag"></i> Custom: ₹' + activePrice.toFixed(2) + '/unit</span>';
            html += '<button type="button" onclick="resetCustomPrice(' + idx + ')" style="font-size:10px;color:#ef4444;background:none;border:none;cursor:pointer;padding:2px 6px;"><i class="fas fa-undo"></i> Reset</button>';
        } else {
            html += '<button type="button" onclick="toggleCustomPrice(' + idx + ')" style="font-size:10px;color:#7c3aed;background:none;border:1px solid #e9d5ff;border-radius:6px;cursor:pointer;padding:3px 8px;"><i class="fas fa-edit"></i> Custom Price</button>';
        }
        html += '</div>';

        // Custom price input row (hidden by default)
        html += '<div id="customPriceRow_' + idx + '" style="display:none;width:100%;align-items:center;gap:6px;margin-top:4px;">';
        html += '<span style="font-size:11px;color:#6b7280;white-space:nowrap;">₹</span>';
        html += '<input type="number" id="customPriceInput_' + idx + '" step="0.01" min="0.01" placeholder="Enter price" value="' + (item.custom_price || '') + '" style="flex:1;padding:5px 8px;border:1.5px solid #e9d5ff;border-radius:6px;font-size:12px;max-width:120px;">';
        html += '<button type="button" onclick="applyCustomPrice(' + idx + ')" style="font-size:11px;background:#7c3aed;color:#fff;border:none;border-radius:6px;padding:5px 10px;cursor:pointer;white-space:nowrap;"><i class="fas fa-check"></i> Apply</button>';
        html += '<button type="button" onclick="toggleCustomPrice(' + idx + ')" style="font-size:11px;color:#6b7280;background:none;border:none;cursor:pointer;padding:3px;"><i class="fas fa-times"></i></button>';
        html += '</div>';

        html += '</div>';
    });

    itemsEl.innerHTML = html;
    totalEl.textContent = '₹' + total.toFixed(2);
    hiddenInput.value = JSON.stringify(cart.map(function(c) {
        var obj = { product_id: c.product_id, weight_id: c.weight_id || 0, quantity: c.quantity };
        if (c.custom_price && c.custom_price !== c.price) {
            obj.custom_price = c.custom_price;
        }
        return obj;
    }));
}

// Form submission - credit check + double-click guard
let orderSubmitting = false;
document.getElementById('orderForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        alert('Please add at least one product to the cart.');
        return;
    }
    if (!document.getElementById('partyIdHidden').value) {
        e.preventDefault();
        alert('Please select a party.');
        return;
    }

    // Client-side credit check
    if (partyCreditLimit > 0) {
        let total = cart.reduce((sum, c) => parseFloat((sum + ((c.custom_price || c.price) * c.quantity)).toFixed(2)), 0);
        if (partyOutstanding + total > partyCreditLimit) {
            e.preventDefault();
            document.getElementById('creditModalMsg').textContent =
                'Outstanding: ₹' + partyOutstanding.toFixed(2) +
                ' + New Order: ₹' + total.toFixed(2) +
                ' exceeds credit limit of ₹' + partyCreditLimit.toFixed(2) +
                '. Please clear dues before placing new order.';
            document.getElementById('creditModal').classList.add('active');
            return;
        }
    }

    // Double-click guard — prevent multiple submissions
    if (orderSubmitting) {
        e.preventDefault();
        return;
    }
    orderSubmitting = true;
    var btn = document.getElementById('submitOrderBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        btn.style.opacity = '0.7';
    }
});

// Product search with debounce
let searchTimeout;
document.getElementById('productSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const q = this.value;
        const cat = document.getElementById('categoryFilter').value;
        window.location.href = '<?= sales_base_url('new_order.php') ?>?q=' + encodeURIComponent(q) + '&cat=' + cat + '&party_id=' + (document.getElementById('partyIdHidden').value || '');
    }, 600);
});

document.getElementById('categoryFilter').addEventListener('change', function() {
    const q = document.getElementById('productSearch').value;
    const cat = this.value;
    window.location.href = '<?= sales_base_url('new_order.php') ?>?q=' + encodeURIComponent(q) + '&cat=' + cat + '&party_id=' + (document.getElementById('partyIdHidden').value || '');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
