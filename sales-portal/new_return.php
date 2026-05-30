<?php
/**
 * Sales Executive Portal - New Return / Credit Note
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$currentPage = 'returns';

$type = $_GET['type'] ?? 'return';
if (!in_array($type, ['return', 'credit_note'])) $type = 'return';
$pageTitle = $type === 'return' ? 'New Return Request' : 'New Credit Note Request';

$partyId = (int)($_GET['party_id'] ?? 0);
$allParties = db_fetch_all('SELECT id, shop_name, owner_name FROM sales_parties WHERE created_by = ? AND is_active = 1 ORDER BY shop_name ASC', [$execId]);

// Fetch products
$products = db_fetch_all('SELECT id, name, price, image FROM products ORDER BY name ASC LIMIT 100');

// Generate form nonce to prevent double-submit
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['return_nonce'] = bin2hex(random_bytes(16));
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderPartyId = (int)($_POST['party_id'] ?? 0);
    $items = json_decode($_POST['cart_items'] ?? '[]', true);
    $notes = trim($_POST['executive_notes'] ?? '');

    // Nonce check — prevent double-submit
    $submittedNonce = $_POST['return_nonce'] ?? '';
    if (empty($submittedNonce) || $submittedNonce !== ($_SESSION['return_nonce'] ?? '')) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Duplicate submission detected. Please reload the page if you need to submit again.'];
        header('Location: ' . sales_base_url('orders.php'));
        exit;
    }
    unset($_SESSION['return_nonce']);

    if (!$orderPartyId || empty($items)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Please select a party and add items.'];
    } else {
        $party = db_fetch('SELECT * FROM sales_parties WHERE id = ? AND created_by = ?', [$orderPartyId, $execId]);
        if (!$party) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Invalid party.'];
        } else {
            $subtotal = 0;
            $validItems = [];
            foreach ($items as $item) {
                $product = db_fetch('SELECT id, name, price FROM products WHERE id = ?', [(int)$item['product_id']]);
                if ($product) {
                    $qty = max(1, (int)$item['quantity']);
                    $price = (float)$product['price'];
                    $lineTotal = $price * $qty;
                    $subtotal += $lineTotal;
                    $validItems[] = [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'sku' => '',
                        'price' => $price,
                        'quantity' => $qty,
                        'total' => $lineTotal,
                    ];
                }
            }

            // Server-side duplicate check: same party + same type + same amount within 2 minutes
            $dupCheck = db_fetch(
                'SELECT id, order_number FROM sales_orders WHERE executive_id = ? AND party_id = ? AND order_type = ? AND total_amount = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1',
                [$execId, $orderPartyId, $type, $subtotal]
            );
            if ($dupCheck) {
                $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Duplicate detected! ' . $dupCheck['order_number'] . ' with the same details was submitted moments ago.'];
                header('Location: ' . sales_base_url('order_detail.php?id=' . $dupCheck['id']));
                exit;
            }

            $db = get_db_connection();
            $db->beginTransaction();
            try {
                $orderNumber = sales_generate_order_number();
                db_query('INSERT INTO sales_orders (order_number, executive_id, party_id, order_type, subtotal, total_amount, status, district, location, executive_notes) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                    $orderNumber, $execId, $orderPartyId, $type, $subtotal, $subtotal, 'pending',
                    $exec['district'], $exec['location'], $notes
                ]);
                $orderId = (int)$db->lastInsertId();

                foreach ($validItems as $vi) {
                    db_query('INSERT INTO sales_order_items (order_id, product_id, product_name, sku, price, quantity, total) VALUES (?,?,?,?,?,?,?)', [
                        $orderId, $vi['product_id'], $vi['product_name'], $vi['sku'], $vi['price'], $vi['quantity'], $vi['total']
                    ]);
                }

                $db->commit();
                $label = $type === 'return' ? 'Return' : 'Credit Note';
                $_SESSION['sp_flash'] = ['type' => 'success', 'message' => $label . ' request ' . $orderNumber . ' submitted successfully!'];
                header('Location: ' . sales_base_url('order_detail.php?id=' . $orderId));
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Failed: ' . $e->getMessage()];
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<a href="<?= sales_base_url('returns.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm sp-mb-16">
    <i class="fas fa-arrow-left"></i> Back to Returns
</a>

<form method="POST" id="returnForm">
    <input type="hidden" name="return_nonce" value="<?= htmlspecialchars($_SESSION['return_nonce'] ?? '') ?>">
    <input type="hidden" name="cart_items" id="cartItemsInput" value="[]">

    <div class="sp-card sp-mb-24">
        <div class="sp-card-header">
            <h3><i class="fas fa-<?= $type === 'return' ? 'undo-alt' : 'file-invoice-dollar' ?>"></i> <?= $pageTitle ?></h3>
        </div>
        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>Select Party *</label>
                <select name="party_id" class="sp-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($allParties as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $partyId == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['shop_name']) ?> — <?= htmlspecialchars($p['owner_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sp-form-group">
                <label>Notes</label>
                <input type="text" name="executive_notes" class="sp-input" placeholder="Reason for <?= $type === 'return' ? 'return' : 'credit note' ?>...">
            </div>
        </div>
    </div>

    <div class="sp-order-layout">
        <div>
            <div class="sp-card sp-mb-0">
                <div class="sp-card-header">
                    <h3><i class="fas fa-boxes"></i> Select Products</h3>
                </div>
                <div class="sp-product-grid" id="productGrid">
                    <?php foreach ($products as $prod):
                        $imgUrl = !empty($prod['image']) ? base_url('assets/images/products/' . $prod['image']) : '';
                    ?>
                    <div class="sp-product-card" data-product-id="<?= $prod['id'] ?>" data-name="<?= htmlspecialchars($prod['name']) ?>" data-price="<?= $prod['price'] ?>" data-sku="">
                        <div class="sp-product-card-body">
                            <?php if ($imgUrl): ?>
                                <img src="<?= $imgUrl ?>" alt="" class="sp-product-img" loading="lazy">
                            <?php else: ?>
                                <div class="sp-product-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;font-size:24px;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                            <div class="sp-product-details">
                                <h4><?= htmlspecialchars($prod['name']) ?></h4>
                                <div class="sp-price">₹<?= number_format($prod['price'], 0) ?></div>
                            </div>
                        </div>
                        <div class="sp-product-card-footer">
                            <div class="sp-qty-control">
                                <button type="button" class="sp-qty-btn" onclick="changeQty(this,-1)">−</button>
                                <input type="number" class="sp-qty-input" value="1" min="1">
                                <button type="button" class="sp-qty-btn" onclick="changeQty(this,1)">+</button>
                            </div>
                            <button type="button" class="sp-add-btn" onclick="addToCart(this)"><i class="fas fa-plus"></i> Add</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div>
            <div class="sp-cart-panel">
                <div class="sp-cart-header">
                    <h3><i class="fas fa-<?= $type === 'return' ? 'undo-alt' : 'file-invoice-dollar' ?>"></i> Items <span class="sp-cart-count" id="cartCount">0</span></h3>
                </div>
                <div id="cartEmpty" class="sp-cart-empty">
                    <i class="fas fa-shopping-basket"></i>
                    Add products for <?= $type === 'return' ? 'return' : 'credit note' ?>
                </div>
                <div id="cartItems" class="sp-cart-items" style="display:none;"></div>
                <div id="cartFooter" class="sp-cart-footer" style="display:none;">
                    <div class="sp-cart-total">
                        <span>Total</span>
                        <span id="cartTotal">₹0</span>
                    </div>
                    <button type="submit" class="sp-btn sp-btn-primary sp-btn-lg sp-btn-block">
                        <i class="fas fa-paper-plane"></i> Submit <?= $type === 'return' ? 'Return' : 'Credit Note' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let cart = [];
function changeQty(btn, d) { const i = btn.parentElement.querySelector('.sp-qty-input'); i.value = Math.max(1, parseInt(i.value||1)+d); }
function addToCart(btn) {
    const c = btn.closest('.sp-product-card');
    const id = parseInt(c.dataset.productId), name = c.dataset.name, price = parseFloat(c.dataset.price);
    const qty = parseInt(c.querySelector('.sp-qty-input').value) || 1;
    const ex = cart.find(x => x.product_id === id);
    if (ex) ex.quantity += qty; else cart.push({product_id:id, name, price, quantity:qty});
    btn.classList.add('added'); btn.innerHTML='<i class="fas fa-check"></i> Added';
    setTimeout(()=>{btn.classList.remove('added');btn.innerHTML='<i class="fas fa-plus"></i> Add';},1200);
    renderCart();
}
function removeFromCart(i) { cart.splice(i,1); renderCart(); }
function renderCart() {
    document.getElementById('cartCount').textContent = cart.length;
    if (!cart.length) {
        document.getElementById('cartEmpty').style.display='';
        document.getElementById('cartItems').style.display='none';
        document.getElementById('cartFooter').style.display='none';
        document.getElementById('cartItemsInput').value='[]';
        return;
    }
    document.getElementById('cartEmpty').style.display='none';
    document.getElementById('cartItems').style.display='';
    document.getElementById('cartFooter').style.display='';
    let t=0, h='';
    cart.forEach((item,i)=>{
        const lt=item.price*item.quantity; t+=lt;
        h+=`<div class="sp-cart-item"><span class="sp-cart-item-name">${item.name}</span><span class="sp-cart-item-qty">×${item.quantity}</span><span class="sp-cart-item-total">₹${lt.toLocaleString('en-IN')}</span><button type="button" class="sp-cart-item-remove" onclick="removeFromCart(${i})"><i class="fas fa-trash-alt"></i></button></div>`;
    });
    document.getElementById('cartItems').innerHTML=h;
    document.getElementById('cartTotal').textContent='₹'+t.toLocaleString('en-IN');
    document.getElementById('cartItemsInput').value=JSON.stringify(cart.map(c=>({product_id:c.product_id,quantity:c.quantity})));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
