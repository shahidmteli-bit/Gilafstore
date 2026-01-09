<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/invoice_functions.php';

require_login();

$orderId = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user']['id'];

if (!$orderId) {
    redirect_with_message('/user/orders.php', 'Invalid order ID', 'error');
}

// Fetch order details
$db = get_db_connection();
$stmt = $db->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect_with_message('/user/orders.php', 'Order not found', 'error');
}

// Fetch order items
$stmt = $db->prepare("
    SELECT oi.*, p.name, p.image, p.price, p.weight, p.ean
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate discount (check if original_price column exists)
$subtotal = 0;
$discount = 0;

// Try to fetch original_price if column exists
try {
    $checkStmt = $db->prepare("
        SELECT oi.*, p.original_price
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $checkStmt->execute([$orderId]);
    $priceData = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge original_price data with items
    foreach ($items as $key => $item) {
        if (isset($priceData[$key]['original_price'])) {
            $items[$key]['original_price'] = $priceData[$key]['original_price'];
        }
    }
} catch (PDOException $e) {
    // Column doesn't exist, continue without it
}

// Calculate subtotal and discount
foreach ($items as $item) {
    $originalPrice = $item['original_price'] ?? $item['price'];
    $actualPrice = $item['price'];
    $quantity = $item['quantity'];
    
    $subtotal += $originalPrice * $quantity;
    $discount += ($originalPrice - $actualPrice) * $quantity;
}

// If no discount from original_price, calculate savings from MRP if available
// For display purposes, show a minimum savings message
if ($discount == 0 && $subtotal > 0) {
    // Get promotional discount from settings
    $promotionalDiscount = get_promotional_discount();
    $discount = $subtotal * ($promotionalDiscount / 100);
}

// Get or create invoice for this order (optional feature)
$invoice = null;
try {
    if (function_exists('get_invoice_by_order')) {
        $invoice = get_invoice_by_order($orderId);
        if (!$invoice && in_array($order['payment_status'] ?? 'pending', ['paid', 'pending', 'completed'])) {
            // Auto-generate invoice if it doesn't exist
            $invoice = create_invoice($orderId);
        }
    }
} catch (Exception $e) {
    // Invoice feature not available - continue without it
    error_log("Invoice error for order {$orderId}: " . $e->getMessage());
    $invoice = null;
}

$pageTitle = 'Order #' . $orderId . ' — Gilaf Store';

include __DIR__ . '/../includes/new-header.php';
?>

<style>
:root {
    --color-green: #1A3C34;
    --color-gold: #C5A089;
}

.order-details-page {
    background: #f8f9fa;
    min-height: 80vh;
    padding: 20px 0;
}

.order-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.breadcrumb {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
    font-size: 0.85rem;
    color: #666;
}

.breadcrumb a {
    color: var(--color-gold);
    text-decoration: none;
}

.order-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
}

.order-main {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-sidebar {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.card-header {
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    color: var(--color-green);
    display: flex;
    align-items: center;
    gap: 10px;
}

.order-status-badge {
    padding: 6px 16px;
    border-radius: 25px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-delivered {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.status-processing {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.status-pending {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.order-timeline {
    position: relative;
    padding-left: 35px;
}

.timeline-item {
    position: relative;
    padding-bottom: 18px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -29px;
    top: 8px;
    width: 2px;
    height: calc(100% + 10px);
    background: #e5e7eb;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-icon {
    position: absolute;
    left: -35px;
    top: 0;
    width: 20px;
    height: 20px;
    background: var(--color-gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.6rem;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.timeline-icon.completed {
    background: #10b981;
}

.timeline-content h4 {
    font-size: 0.9rem;
    color: var(--color-green);
    margin-bottom: 3px;
}

.timeline-content p {
    color: #666;
    font-size: 0.8rem;
}

.product-item {
    display: grid;
    grid-template-columns: 70px 1fr auto;
    gap: 15px;
    padding: 12px;
    border: 1.5px solid #f0f0f0;
    border-radius: 10px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.product-item:hover {
    border-color: var(--color-gold);
    box-shadow: 0 4px 12px rgba(197, 160, 89, 0.15);
}

.product-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    object-fit: cover;
}

.product-info h4 {
    font-size: 0.95rem;
    color: var(--color-green);
    margin-bottom: 5px;
}

.product-info p {
    color: #666;
    font-size: 0.8rem;
    margin-bottom: 3px;
}

.product-price {
    text-align: right;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.price {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--color-gold);
}

.delivery-info {
    background: linear-gradient(135deg, rgba(26, 60, 52, 0.05) 0%, rgba(197, 160, 89, 0.05) 100%);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
}

.delivery-info h4 {
    color: var(--color-green);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.delivery-info p {
    color: #666;
    margin-bottom: 5px;
    display: flex;
    align-items: start;
    gap: 8px;
    font-size: 0.8rem;
}

.delivery-info i {
    color: var(--color-gold);
    margin-top: 3px;
}

.price-breakdown {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
    color: #666;
    font-size: 0.85rem;
}

.price-row.total {
    border-top: 2px solid #e5e7eb;
    margin-top: 8px;
    padding-top: 10px;
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-green);
}

.price-row.total .amount {
    color: var(--color-gold);
    font-size: 1.2rem;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}

.btn {
    padding: 10px 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--color-green) 0%, #2d5a4e 100%);
    color: white;
    border: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
    color: white;
}

.btn-secondary {
    background: white;
    color: var(--color-green);
    border: 2px solid var(--color-green);
}

.btn-secondary:hover {
    background: var(--color-green);
    color: white;
}

.rating-section {
    background: linear-gradient(135deg, rgba(197, 160, 89, 0.1) 0%, rgba(197, 160, 89, 0.05) 100%);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
}

.rating-section h4 {
    color: var(--color-green);
    margin-bottom: 15px;
}

.star-rating {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 20px 0;
}

.star {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: all 0.3s ease;
}

.star:hover,
.star.active {
    color: var(--color-gold);
    transform: scale(1.2);
}

.btn-download-invoice {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
    border: none;
    cursor: pointer;
}

.btn-download-invoice:hover {
    background: linear-gradient(135deg, #0f2820 0%, #1A3C34 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(26, 60, 52, 0.4);
    color: white;
}

.btn-download-invoice i {
    font-size: 16px;
}

@media (max-width: 992px) {
    .order-layout {
        grid-template-columns: 1fr;
    }
    
    .product-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .product-image {
        margin: 0 auto;
    }
    
    .product-price {
        text-align: center;
    }
}
</style>

<div class="order-details-page">
    <div class="order-container">
        <div class="breadcrumb">
            <a href="<?= base_url('/') ?>"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="<?= base_url('user/profile.php') ?>">My Account</a>
            <span>/</span>
            <a href="<?= base_url('user/orders.php') ?>">My Orders</a>
            <span>/</span>
            <span>Order #<?= $orderId ?></span>
        </div>
        
        <div class="order-layout">
            <!-- Main Content -->
            <div class="order-main">
                <!-- Order Header -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                        <!-- Left: Order Items with First Product -->
                        <div style="flex: 1;">
                            <?php 
                            $orderStatus = $order['order_status'] ?? $order['status'] ?? 'pending';
                            $statusIcon = $orderStatus === 'delivered' ? 'check-circle' : 'clock';
                            $firstItem = $items[0] ?? null;
                            ?>
                            <h2 style="margin: 0 0 10px 0; font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--color-green); display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-shopping-bag"></i>
                                Order Items
                            </h2>
                            <?php if ($firstItem): ?>
                            <div>
                                <h3 style="margin: 0 0 5px 0; font-size: 1.1rem; color: var(--color-green);"><?= htmlspecialchars($firstItem['name']) ?></h3>
                                <?php if (!empty($firstItem['weight'])): ?>
                                <p style="margin: 3px 0; font-size: 0.85rem; color: #666;"><i class="fas fa-weight" style="color: var(--color-gold); margin-right: 5px;"></i> Net. weight: <?= htmlspecialchars($firstItem['weight']) ?></p>
                                <?php endif; ?>
                                <p style="margin: 3px 0; font-size: 0.85rem; color: #666;"><i class="fas fa-box" style="color: var(--color-gold); margin-right: 5px;"></i> Quantity: <?= $firstItem['quantity'] ?></p>
                                <p style="margin: 3px 0; font-size: 0.85rem; color: #666;"><i class="fas fa-rupee-sign" style="color: var(--color-gold); margin-right: 5px;"></i> Price: ₹<?= number_format($firstItem['price'], 2) ?> each</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Middle: Delivery Details -->
                        <div style="background: linear-gradient(135deg, rgba(26, 60, 52, 0.05) 0%, rgba(197, 160, 89, 0.05) 100%); border-radius: 10px; padding: 12px; min-width: 280px;">
                            <h3 style="color: var(--color-green); margin: 0 0 8px 0; font-family: 'Playfair Display', serif; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-map-marker-alt"></i> Delivery Details
                            </h3>
                            <div style="font-size: 0.8rem;">
                                <p style="margin: 0 0 5px 0; display: flex; align-items: center; gap: 8px; color: #666;">
                                    <i class="fas fa-user" style="color: var(--color-gold);"></i>
                                    <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                </p>
                                <p style="margin: 0 0 5px 0; display: flex; align-items: center; gap: 8px; color: #666;">
                                    <i class="fas fa-phone" style="color: var(--color-gold);"></i>
                                    <?= htmlspecialchars($order['phone'] ?? 'N/A') ?>
                                </p>
                                <p style="margin: 0 0 5px 0; display: flex; align-items: center; gap: 8px; color: #666;">
                                    <i class="fas fa-envelope" style="color: var(--color-gold);"></i>
                                    <?= htmlspecialchars($order['customer_email']) ?>
                                </p>
                                <p style="margin: 0; display: flex; align-items: start; gap: 8px; color: #666;">
                                    <i class="fas fa-home" style="color: var(--color-gold); margin-top: 3px;"></i>
                                    <?= htmlspecialchars($order['address'] ?? 'Address not available') ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Right: Status Badge -->
                        <div style="display: flex; align-items: flex-start;">
                            <div class="order-status-badge status-<?= $orderStatus ?>">
                                <i class="fas fa-<?= $statusIcon ?>"></i>
                                <?= ucfirst($orderStatus) ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Timeline -->
                    <div class="order-timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon completed">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="timeline-content">
                                <h4>Order Confirmed</h4>
                                <p><?= date('M d, Y \a\t h:i A', strtotime($order['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <?php if ($orderStatus !== 'pending'): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon completed">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="timeline-content">
                                <h4>Order Processing</h4>
                                <p>Your order is being prepared</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($orderStatus === 'delivered'): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon completed">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="timeline-content">
                                <h4>Out for Delivery</h4>
                                <p>Your order is on the way</p>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-icon completed">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="timeline-content">
                                <h4>Delivered</h4>
                                <p><?= date('M d, Y', strtotime($order['updated_at'])) ?></p>
                                <p style="color: #10b981; font-weight: 600;">Your item has been delivered</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Additional Products (if more than 1) -->
                <?php if (count($items) > 1): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-shopping-bag"></i> Additional Items</h2>
                    </div>
                    
                    <?php foreach (array_slice($items, 1) as $item): ?>
                    <div class="product-item">
                        <img src="<?= asset_url('images/products/' . ($item['image'] ?? 'placeholder.jpg')) ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>" class="product-image">
                        
                        <div class="product-info">
                            <h4><?= htmlspecialchars($item['name']) ?></h4>
                            <?php if (!empty($item['ean'])): ?>
                            <p><i class="fas fa-barcode"></i> EAN: <?= htmlspecialchars($item['ean']) ?></p>
                            <?php endif; ?>
                            <p><i class="fas fa-box"></i> Quantity: <?= $item['quantity'] ?></p>
                            <p><i class="fas fa-rupee-sign"></i> Price: ₹<?= number_format($item['price'], 2) ?> each</p>
                        </div>
                        
                        <div class="product-price">
                            <div class="price">₹<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Rating Section -->
                <?php if ($orderStatus === 'delivered'): ?>
                <div class="card">
                    <div class="rating-section">
                        <h4>Rate Your Experience</h4>
                        <p style="color: #666; margin-bottom: 20px;">How was your delivery experience?</p>
                        
                        <div class="star-rating">
                            <i class="fas fa-star star" onclick="rate(1)"></i>
                            <i class="fas fa-star star" onclick="rate(2)"></i>
                            <i class="fas fa-star star" onclick="rate(3)"></i>
                            <i class="fas fa-star star" onclick="rate(4)"></i>
                            <i class="fas fa-star star" onclick="rate(5)"></i>
                        </div>
                        
                        <p style="color: #666; font-size: 0.9rem;">Did you find this page helpful?</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="order-sidebar">
                <!-- Price Details -->
                <div class="card" style="margin-top: 12px;">
                    <h3 style="color: var(--color-green); margin-bottom: 10px; font-family: 'Playfair Display', serif; font-size: 1.1rem;">
                        <i class="fas fa-file-invoice-dollar"></i> Price Details
                    </h3>
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Listing price</span>
                            <span>₹<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div class="price-row">
                            <span>Discount</span>
                            <span style="color: #10b981;">-₹<?= number_format($discount, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="price-row">
                            <span>Shipping</span>
                            <span style="color: #10b981; font-weight: 600;">FREE</span>
                        </div>
                        <div class="price-row total">
                            <span>Total amount</span>
                            <span class="amount">₹<?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                    <div style="margin-top: 8px; padding: 10px 12px; background: rgba(34, 197, 94, 0.12); border-radius: 6px; text-align: center; border: 1px solid rgba(34, 197, 94, 0.2);">
                        <p style="color: #16a34a; font-weight: 600; margin: 0; font-size: 0.85rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;">
                            You have saved ₹<?= number_format($discount, 2) ?> on this order
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 8px; padding: 8px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; text-align: center;">
                        <p style="color: #059669; font-weight: 600; margin: 0; font-size: 0.85rem;">
                            <i class="fas fa-credit-card"></i> Payment method: <?= ucfirst($order['payment_method'] ?? 'N/A') ?>
                        </p>
                        <?php if ($order['payment_method'] === 'upi' && !empty($order['transaction_id'])): ?>
                        <p style="color: #6b7280; font-size: 0.8rem; margin: 5px 0 0 0;">
                            <i class="fas fa-receipt"></i> Transaction ID: 
                            <code style="background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 3px; font-weight: 600; font-size: 0.75rem;">
                                <?= htmlspecialchars($order['transaction_id']) ?>
                            </code>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php if ($order['payment_method'] === 'upi' && $order['payment_status'] === 'pending'): ?>
                    <p style="color: #f59e0b; font-size: 0.8rem; margin: 8px 0 0 0; padding: 8px 10px; text-align: center; background: rgba(245, 158, 11, 0.1); border-radius: 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif; font-weight: 500; line-height: 1.5;">
                        <i class="fas fa-clock"></i> Payment verification pending - Your order will be confirmed once the admin verifies your transaction ID
                    </p>
                    <?php endif; ?>
                    <?php if ($order['payment_method'] === 'upi' && $order['payment_status'] === 'completed'): ?>
                    <p style="color: #10b981; font-size: 0.75rem; margin: 6px 0 0 0; padding: 0 8px; text-align: center;">
                        <i class="fas fa-check-circle"></i> Payment verified and confirmed
                    </p>
                    <?php endif; ?>
                    
                    <div class="action-buttons" style="margin-top: 8px;">
                        <?php if ($invoice): ?>
                            <a href="<?= base_url('generate_invoice_pdf.php?id=' . $invoice['id']); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Download Invoice
                            </a>
                        <?php endif; ?>
                        <a href="<?= base_url('download_invoice.php?order_id=' . $orderId); ?>" class="btn-download-invoice">
                            <i class="fas fa-download"></i> Download Invoice
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function rate(stars) {
    const starElements = document.querySelectorAll('.star');
    starElements.forEach((star, index) => {
        if (index < stars) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
    
    // Show success message
    setTimeout(() => {
        alert('Thank you for rating! Your feedback helps us improve.');
    }, 300);
}
</script>

<?php include __DIR__ . '/../includes/new-footer.php'; ?>
