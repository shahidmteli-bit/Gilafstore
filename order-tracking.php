<?php
/**
 * Public Order Tracking Page
 * Shareable via WhatsApp with live status updates
 */
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$orderId = (int)($_GET['id'] ?? 0);
$token = $_GET['t'] ?? '';
$phone = preg_replace('/[^0-9]/', '', $_GET['p'] ?? '');

$order = null;
$timeline = [];
$error = null;

// Validate access - either logged in user, or valid token/phone combo
if ($orderId > 0) {
    global $pdo;
    
    // Fetch order
    $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as user_phone, u.email 
                           FROM orders o 
                           LEFT JOIN users u ON o.user_id = u.id 
                           WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // Verify access
        $hasAccess = false;
        
        // Check if logged in user owns this order
        if (!empty($_SESSION['user']['id']) && (int)$order['user_id'] === (int)$_SESSION['user']['id']) {
            $hasAccess = true;
        }
        
        // Check token (hash of order_id + created_at)
        if ($token && hash_equals(substr(md5($orderId . $order['created_at']), 0, 12), $token)) {
            $hasAccess = true;
        }
        
        // Check phone number match
        $orderPhone = preg_replace('/[^0-9]/', '', $order['phone'] ?? $order['user_phone'] ?? '');
        if ($phone && strlen($phone) >= 10 && substr($orderPhone, -10) === substr($phone, -10)) {
            $hasAccess = true;
        }
        
        // Guest orders (no user_id) are accessible with order ID
        if (!$order['user_id']) {
            $hasAccess = true;
        }
        
        if (!$hasAccess) {
            $order = null;
            $error = 'Access denied. Please use the tracking link from your WhatsApp message.';
        }
    } else {
        $error = 'Order not found.';
    }
    
    // Build timeline if order found
    if ($order) {
        $statusHistory = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
        $statusHistory->execute([$orderId]);
        $history = $statusHistory->fetchAll(PDO::FETCH_ASSOC);
        
        // Define all possible steps
        $steps = [
            'pending' => ['icon' => 'fa-clock', 'label' => 'Order Placed'],
            'confirmed' => ['icon' => 'fa-check-circle', 'label' => 'Confirmed'],
            'packed' => ['icon' => 'fa-box', 'label' => 'Packed'],
            'shipped' => ['icon' => 'fa-truck', 'label' => 'Shipped'],
            'out_for_delivery' => ['icon' => 'fa-motorcycle', 'label' => 'Out for Delivery'],
            'delivered' => ['icon' => 'fa-home', 'label' => 'Delivered'],
        ];
        
        $currentStatus = $order['order_status'];
        $statusOrder = array_keys($steps);
        $currentIndex = array_search($currentStatus, $statusOrder);
        if ($currentIndex === false) $currentIndex = 0;
        
        foreach ($steps as $status => $info) {
            $stepIndex = array_search($status, $statusOrder);
            $historyEntry = array_filter($history, fn($h) => $h['new_status'] === $status);
            $historyEntry = reset($historyEntry);
            
            $timeline[] = [
                'status' => $status,
                'label' => $info['label'],
                'icon' => $info['icon'],
                'completed' => $stepIndex <= $currentIndex,
                'current' => $status === $currentStatus,
                'date' => $historyEntry ? date('d M, h:i A', strtotime($historyEntry['created_at'])) : null,
            ];
        }
    }
}

// Generate tracking token for sharing
function generateTrackingUrl($order) {
    $token = substr(md5($order['id'] . $order['created_at']), 0, 12);
    return base_url("order-tracking.php?id={$order['id']}&t=$token");
}

$pageTitle = $order ? "Track Order #{$order['id']}" : 'Track Your Order';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Gilaf Store</title>
    <link rel="icon" href="<?= asset_url('icons/icon-192x192.png') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); min-height: 100vh; }
        
        .track-header { background: linear-gradient(135deg, #1A3C34 0%, #2d5a4e 100%); color: #fff; padding: 20px; text-align: center; }
        .track-header h1 { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
        .track-header p { opacity: 0.85; font-size: 0.9rem; }
        
        .track-container { max-width: 600px; margin: 0 auto; padding: 20px; }
        
        .order-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 20px; }
        .order-card-header { padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .order-id { font-size: 1.1rem; font-weight: 700; color: #1A3C34; }
        .order-status { padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-packed { background: #e0e7ff; color: #3730a3; }
        .status-shipped { background: #fce7f3; color: #9d174d; }
        .status-out_for_delivery { background: #fed7aa; color: #c2410c; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .timeline { padding: 24px 20px; }
        .timeline-step { display: flex; align-items: flex-start; margin-bottom: 0; position: relative; }
        .timeline-step:last-child { margin-bottom: 0; }
        .timeline-step:last-child .timeline-line { display: none; }
        
        .timeline-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; z-index: 2; transition: all 0.3s; }
        .timeline-icon.completed { background: #1A3C34; color: #fff; }
        .timeline-icon.current { background: #25D366; color: #fff; animation: pulse 2s infinite; }
        .timeline-icon.pending { background: #e5e7eb; color: #9ca3af; }
        
        @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(37,211,102,0.4); } 50% { box-shadow: 0 0 0 10px rgba(37,211,102,0); } }
        
        .timeline-content { flex: 1; padding: 0 16px 24px; min-height: 60px; }
        .timeline-label { font-weight: 600; color: #1f2937; font-size: 0.95rem; }
        .timeline-label.pending { color: #9ca3af; }
        .timeline-date { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
        
        .timeline-line { position: absolute; left: 19px; top: 40px; bottom: 0; width: 2px; background: #e5e7eb; }
        .timeline-line.completed { background: #1A3C34; }
        
        .order-details { padding: 20px; background: #f9fafb; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; font-size: 0.9rem; }
        .detail-value { font-weight: 600; color: #1f2937; font-size: 0.9rem; }
        
        .tracking-info { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .tracking-info h3 { font-size: 1rem; color: #1f2937; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .tracking-number { background: #f3f4f6; padding: 12px 16px; border-radius: 10px; font-family: monospace; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .copy-btn { background: #1A3C34; color: #fff; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.8rem; }
        .copy-btn:hover { background: #15302a; }
        
        .share-section { text-align: center; padding: 20px; }
        .share-btn { display: inline-flex; align-items: center; gap: 10px; background: #25D366; color: #fff; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 1rem; transition: all 0.2s; }
        .share-btn:hover { background: #128C7E; transform: translateY(-2px); }
        
        .error-card { background: #fef2f2; border: 1px solid #fecaca; border-radius: 16px; padding: 40px; text-align: center; }
        .error-card i { font-size: 3rem; color: #ef4444; margin-bottom: 16px; }
        .error-card h2 { color: #991b1b; margin-bottom: 8px; }
        .error-card p { color: #7f1d1d; }
        
        .search-form { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .search-form h2 { font-size: 1.2rem; margin-bottom: 16px; color: #1f2937; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.9rem; color: #6b7280; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #1A3C34; }
        .search-btn { width: 100%; padding: 14px; background: #1A3C34; color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .search-btn:hover { background: #15302a; }
        
        .footer-link { text-align: center; padding: 20px; }
        .footer-link a { color: #1A3C34; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="track-header">
        <h1><i class="fas fa-truck"></i> Track Your Order</h1>
        <p>Real-time order tracking powered by Gilaf Store</p>
    </div>
    
    <div class="track-container">
        <?php if ($error): ?>
            <div class="error-card">
                <i class="fas fa-exclamation-circle"></i>
                <h2>Oops!</h2>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
            
            <div class="search-form" style="margin-top: 20px;">
                <h2>Find Your Order</h2>
                <form method="get">
                    <div class="form-group">
                        <label>Order ID</label>
                        <input type="number" name="id" placeholder="e.g., 12345" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number (last 10 digits)</label>
                        <input type="tel" name="p" placeholder="9876543210" maxlength="10">
                    </div>
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Track Order</button>
                </form>
            </div>
            
        <?php elseif (!$orderId): ?>
            <div class="search-form">
                <h2>Find Your Order</h2>
                <form method="get">
                    <div class="form-group">
                        <label>Order ID</label>
                        <input type="number" name="id" placeholder="e.g., 12345" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number (last 10 digits)</label>
                        <input type="tel" name="p" placeholder="9876543210" maxlength="10">
                    </div>
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Track Order</button>
                </form>
            </div>
            
        <?php elseif ($order): ?>
            <div class="order-card">
                <div class="order-card-header">
                    <span class="order-id">Order #<?= $order['id'] ?></span>
                    <span class="order-status status-<?= $order['order_status'] ?>"><?= ucfirst(str_replace('_', ' ', $order['order_status'])) ?></span>
                </div>
                
                <div class="timeline">
                    <?php foreach ($timeline as $i => $step): ?>
                        <div class="timeline-step">
                            <div class="timeline-icon <?= $step['completed'] ? ($step['current'] ? 'current' : 'completed') : 'pending' ?>">
                                <i class="fas <?= $step['icon'] ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-label <?= !$step['completed'] ? 'pending' : '' ?>"><?= $step['label'] ?></div>
                                <?php if ($step['date']): ?>
                                    <div class="timeline-date"><?= $step['date'] ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($i < count($timeline) - 1): ?>
                                <div class="timeline-line <?= $step['completed'] && !$step['current'] ? 'completed' : '' ?>"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="order-details">
                    <div class="detail-row">
                        <span class="detail-label">Order Date</span>
                        <span class="detail-value"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Amount</span>
                        <span class="detail-value">₹<?= number_format($order['total_amount'], 2) ?></span>
                    </div>
                    <?php if ($order['payment_method']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Payment</span>
                        <span class="detail-value"><?= ucfirst($order['payment_method']) ?> (<?= ucfirst($order['payment_status'] ?? 'pending') ?>)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($order['tracking_id']): ?>
            <div class="tracking-info">
                <h3><i class="fas fa-barcode"></i> Tracking Details</h3>
                <div class="tracking-number">
                    <span id="trackingNum"><?= htmlspecialchars($order['tracking_id']) ?></span>
                    <button class="copy-btn" onclick="copyTracking()"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <?php if ($order['courier_company']): ?>
                <p style="margin-top: 12px; color: #6b7280; font-size: 0.9rem;">
                    <i class="fas fa-truck"></i> Shipped via <strong><?= htmlspecialchars($order['courier_company']) ?></strong>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="share-section">
                <a href="https://wa.me/?text=<?= urlencode("Track my Gilaf Store order: " . generateTrackingUrl($order)) ?>" class="share-btn" target="_blank">
                    <i class="fab fa-whatsapp"></i> Share Tracking Link
                </a>
            </div>
        <?php endif; ?>
        
        <div class="footer-link">
            <a href="<?= base_url() ?>"><i class="fas fa-arrow-left"></i> Back to Gilaf Store</a>
        </div>
    </div>
    
    <script>
    function copyTracking() {
        const text = document.getElementById('trackingNum').textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('Tracking number copied!');
        });
    }
    </script>
</body>
</html>
