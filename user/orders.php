<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pageTitle = 'My Orders — Gilaf Store';
$activePage = 'orders';

$userId = (int)$_SESSION['user']['id'];
$user = $_SESSION['user'];

// Fetch user orders with items
$orders = db_fetch_all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', [$userId]);

include __DIR__ . '/../includes/new-header.php';
?>

<div class="orders-page">
    <div class="orders-container">
        <div class="orders-content">
            <div class="content-header">
                <div>
                    <h1>Order history</h1>
                    <p>Track past purchases and monitor shipping status.</p>
                </div>
                <a href="<?= base_url('user/profile.php'); ?>" class="btn-back-profile">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>
                
                <?php if ($orders): ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <?php 
                            // Fetch order items
                            $orderItems = db_fetch_all(
                                'SELECT oi.*, p.name, p.image FROM order_items oi 
                                 LEFT JOIN products p ON oi.product_id = p.id 
                                 WHERE oi.order_id = ?', 
                                [$order['id']]
                            );
                            
                            // Status styling
                            $statusClass = '';
                            $statusIcon = '';
                            $orderStatus = $order['order_status'] ?? $order['status'] ?? 'pending';
                            switch(strtolower($orderStatus)) {
                                case 'pending':
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'fa-clock';
                                    break;
                                case 'processing':
                                    $statusClass = 'status-processing';
                                    $statusIcon = 'fa-cog';
                                    break;
                                case 'shipped':
                                    $statusClass = 'status-shipped';
                                    $statusIcon = 'fa-shipping-fast';
                                    break;
                                case 'delivered':
                                    $statusClass = 'status-delivered';
                                    $statusIcon = 'fa-check-circle';
                                    break;
                                case 'cancelled':
                                    $statusClass = 'status-cancelled';
                                    $statusIcon = 'fa-times-circle';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'fa-clock';
                            }
                            ?>
                            
                            <div class="order-card">
                                <!-- Row 1: Order No. and Status -->
                                <div class="order-row-1">
                                    <div class="order-number">Order No.: #<?= $order['id']; ?></div>
                                    <div class="order-status <?= $statusClass; ?>">
                                        <i class="fas <?= $statusIcon; ?>"></i>
                                        <span><?= ucfirst($orderStatus); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Row 2: Product Names (up to 3 with | separator) -->
                                <?php 
                                $productNames = [];
                                foreach ($orderItems as $item) {
                                    $productNames[] = htmlspecialchars($item['name']);
                                }
                                
                                $displayNames = array_slice($productNames, 0, 3);
                                $remainingCount = count($productNames) - 3;
                                $productDisplay = implode(' | ', $displayNames);
                                if ($remainingCount > 0) {
                                    $productDisplay .= ' + ' . $remainingCount . ' more';
                                }
                                
                                // Calculate total quantity
                                $totalQuantity = 0;
                                foreach ($orderItems as $item) {
                                    $totalQuantity += $item['quantity'];
                                }
                                ?>
                                <div class="order-row-2">
                                    <div class="product-name"><?= $productDisplay; ?></div>
                                </div>
                                
                                <!-- Row 3: Total Items, Total Amount, View Details (all horizontal) -->
                                <div class="order-row-3">
                                    <div class="quantity-info">Total Items: <?= $totalQuantity; ?></div>
                                    <div class="total-amount-info">Total Amount: <strong>₹<?= number_format($order['total_amount'], 2); ?></strong></div>
                                    <a href="<?= base_url('user/order_details.php?id=' . $order['id']); ?>" class="btn-view-compact">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h2>No orders yet</h2>
                        <p>Start shopping to see your orders and shipping updates here.</p>
                        <a href="<?= base_url('shop.php'); ?>" class="btn-shop-now">
                            <i class="fas fa-shopping-bag"></i> Shop Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --color-green: #1A3C34;
    --color-green-light: #2d5a4d;
    --color-green-dark: #0f2820;
    --color-gold: #C5A059;
    --color-gold-light: #d4b896;
    --color-gold-dark: #b08d4b;
    --color-ivory: #F8F5F2;
    --font-serif: 'Playfair Display', serif;
    --font-sans: 'Inter', sans-serif;
}

.orders-page {
    background: #f8f9fa;
    min-height: calc(100vh - 120px);
    padding: 40px 0;
}

.orders-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Main Content */
.orders-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    max-width: 1200px;
    margin: 0 auto;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.btn-back-profile {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(26, 60, 52, 0.2);
}

.btn-back-profile:hover {
    background: linear-gradient(135deg, #0f2820 0%, #1A3C34 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
}

.btn-back-profile i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .content-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .btn-back-profile {
        width: 100%;
        justify-content: center;
    }
}

.content-header h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-green-dark);
    margin: 0 0 6px 0;
    font-family: var(--font-serif);
}

.content-header p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.order-card {
    background: white;
    border: 1px solid var(--color-ivory);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    padding: 10px 14px;
}

.order-card:hover {
    border-color: var(--color-gold-light);
    box-shadow: 0 4px 12px rgba(197, 160, 89, 0.15);
    transform: translateY(-1px);
}

/* Row 1: Order No. and Status */
.order-row-1 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.order-number {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-green-dark);
}

/* Row 2: Product Name */
.order-row-2 {
    margin-bottom: 6px;
}

.product-name {
    font-size: 14px;
    font-weight: 500;
    color: #2c3e50;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
    line-height: 1.5;
    letter-spacing: 0.01em;
}

/* Row 3: Quantity, Total Amount, View Details (all horizontal) */
.order-row-3 {
    display: flex;
    align-items: center;
    gap: 12px;
}

.quantity-info {
    font-size: 12px;
    color: #666;
    white-space: nowrap;
}

.total-amount-info {
    font-size: 12px;
    color: #666;
    white-space: nowrap;
    flex: 1;
}

.total-amount-info strong {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
    letter-spacing: 0.02em;
}

.btn-view-compact {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--color-green);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 11px;
    transition: all 0.3s ease;
    white-space: nowrap;
    margin-left: auto;
}

.btn-view-compact:hover {
    background: var(--color-green-dark);
    transform: translateY(-1px);
    color: white;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: linear-gradient(135deg, var(--color-ivory) 0%, #fff 100%);
    border-bottom: 1.5px solid var(--color-ivory);
}

.order-info h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-green-dark);
    margin: 0 0 4px 0;
    font-family: var(--font-serif);
}

.order-info p {
    font-size: 12px;
    color: #666;
    margin: 0;
}

.order-info i {
    margin-right: 8px;
    color: var(--color-gold);
}

.order-status {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 12px;
}

.status-pending {
    background: rgba(255, 193, 7, 0.1);
    color: #f59e0b;
}

.status-processing {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.status-shipped {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.status-delivered {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.status-cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Order Items */
.order-items {
    padding: 12px 16px;
}

.order-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--color-ivory);
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 55px;
    height: 55px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--color-ivory);
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 24px;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-green-dark);
    margin: 0 0 3px 0;
}

.item-details p {
    font-size: 12px;
    color: #666;
    margin: 0;
}

.item-price {
    font-size: 14px;
    font-weight: 700;
    color: var(--color-green-dark);
    font-family: var(--font-serif);
}

/* Order Footer */
.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: linear-gradient(135deg, #fff 0%, var(--color-ivory) 100%);
    border-top: 1.5px solid var(--color-ivory);
}

.order-total {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.order-total span {
    font-size: 12px;
    color: #666;
}

.order-total strong {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-green-dark);
    font-family: var(--font-serif);
}

.btn-view-details {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    background: linear-gradient(135deg, var(--color-green) 0%, var(--color-green-light) 100%);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 60, 52, 0.3);
}

.btn-view-details:hover {
    background: linear-gradient(135deg, var(--color-green-dark) 0%, var(--color-green) 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.4);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
}

.empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-ivory) 0%, #fff 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    color: var(--color-gold);
    box-shadow: 0 10px 30px rgba(197, 160, 89, 0.2);
}

.empty-state h2 {
    font-size: 28px;
    font-weight: 700;
    color: var(--color-green-dark);
    margin: 0 0 15px 0;
    font-family: var(--font-serif);
}

.empty-state p {
    font-size: 16px;
    color: #666;
    margin: 0 0 30px 0;
}

.btn-shop-now {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: linear-gradient(135deg, var(--color-gold) 0%, var(--color-gold-dark) 100%);
    color: var(--color-green-dark);
    text-decoration: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(197, 160, 89, 0.4);
}

.btn-shop-now:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(197, 160, 89, 0.5);
    color: var(--color-green-dark);
}

/* Responsive */
@media (max-width: 992px) {
    .orders-layout {
        grid-template-columns: 1fr;
    }
    
    .profile-sidebar {
        position: relative;
        top: 0;
    }
}

@media (max-width: 1200px) {
    .orders-container {
        max-width: 98%;
        padding: 0 1%;
    }
}

@media (max-width: 768px) {
    .orders-page {
        padding: 60px 0 30px 0;
        margin-top: -120px !important;
    }
    
    .orders-container {
        max-width: 100%;
        padding: 0 10px;
    }
    
    .orders-content {
        padding: 20px 15px;
    }
    
    .content-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
    
    .content-header h1 {
        font-size: 22px;
    }
    
    .content-header p {
        font-size: 13px;
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        padding: 15px;
    }
    
    /* Mobile: Stack Row 3 vertically */
    .order-row-3 {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .btn-view-compact {
        width: 100%;
        justify-content: center;
        margin-left: 0;
    }
    
    .order-items {
        padding: 15px;
    }
    
    .order-item {
        gap: 12px;
        padding: 10px 0;
    }
    
    .item-image {
        width: 60px;
        height: 60px;
    }
    
    .item-details h4 {
        font-size: 14px;
    }
    
    .item-price {
        font-size: 15px;
    }
    
    .order-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
        padding: 15px;
    }
    
    .order-total {
        text-align: center;
    }
    
    .order-total strong {
        font-size: 18px;
    }
    
    .btn-view-details {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
    }
    
    .profile-sidebar {
        padding: 25px 20px;
    }
    
    .user-avatar {
        width: 70px;
        height: 70px;
        font-size: 28px;
    }
}
</style>

<?php
include __DIR__ . '/../includes/new-footer.php';
?>
