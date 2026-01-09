<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Track Your Shipment';

// Get tracking number from URL
$trackingNumber = $_GET['tracking'] ?? '';
$orderDetails = null;
$courierDetails = null;

if ($trackingNumber) {
    try {
        $db = get_db_connection();
        
        // Get order with tracking number
        $stmt = $db->prepare("
            SELECT o.*, c.name as courier_name, c.tracking_url_pattern, c.code as courier_code,
                   u.name as customer_name, u.email as customer_email
            FROM orders o
            LEFT JOIN courier_companies c ON o.courier_company_id = c.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.tracking_number = ?
        ");
        $stmt->execute([$trackingNumber]);
        $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orderDetails && $orderDetails['courier_company_id']) {
            $courierDetails = [
                'name' => $orderDetails['courier_name'],
                'tracking_url' => str_replace('{TN}', $trackingNumber, $orderDetails['tracking_url_pattern']),
                'code' => $orderDetails['courier_code']
            ];
        }
    } catch (Exception $e) {
        error_log("Tracking error: " . $e->getMessage());
    }
}

include __DIR__ . '/includes/new-header.php';
?>

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    background-attachment: fixed;
    min-height: 100vh;
}

.tracking-container {
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
}

.tracking-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(30px);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.18);
    animation: slideUp 0.6s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.tracking-header {
    text-align: center;
    margin-bottom: 40px;
}

.tracking-header h1 {
    color: white;
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 10px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.tracking-header p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 16px;
}

.tracking-input-group {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
}

.tracking-input {
    flex: 1;
    padding: 16px 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    font-size: 16px;
    transition: all 0.3s ease;
}

.tracking-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.tracking-input:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.2);
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.1);
}

.btn-track {
    padding: 16px 32px;
    background: rgba(59, 130, 246, 0.3);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    color: white;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

.btn-track:hover {
    background: rgba(59, 130, 246, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
}

.order-details {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
}

.detail-value {
    font-weight: 500;
}

.courier-tracking {
    text-align: center;
    padding: 30px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.courier-logo {
    font-size: 48px;
    margin-bottom: 20px;
}

.btn-track-courier {
    display: inline-block;
    padding: 16px 40px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.4) 0%, rgba(5, 150, 105, 0.4) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    color: white;
    font-weight: 700;
    font-size: 18px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.btn-track-courier:hover {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.6) 0%, rgba(5, 150, 105, 0.6) 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { background: rgba(251, 191, 36, 0.3); color: white; }
.status-processing { background: rgba(59, 130, 246, 0.3); color: white; }
.status-shipped { background: rgba(16, 185, 129, 0.3); color: white; }
.status-delivered { background: rgba(34, 197, 94, 0.3); color: white; }
.status-cancelled { background: rgba(239, 68, 68, 0.3); color: white; }

.alert-glass {
    background: rgba(239, 68, 68, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    color: white;
    text-align: center;
}
</style>

<div class="tracking-container">
    <div class="tracking-card">
        <div class="tracking-header">
            <h1>📦 Track Your Shipment</h1>
            <p>Enter your tracking number to get real-time updates</p>
        </div>

        <form method="GET" action="track-shipment.php">
            <div class="tracking-input-group">
                <input type="text" name="tracking" class="tracking-input" placeholder="Enter Tracking Number (e.g., EE123456789IN)" value="<?= htmlspecialchars($trackingNumber); ?>" required>
                <button type="submit" class="btn-track">
                    <i class="fas fa-search"></i> TRACK
                </button>
            </div>
        </form>

        <?php if ($trackingNumber): ?>
            <?php if ($orderDetails): ?>
                <div class="order-details">
                    <h3 style="color: white; margin-bottom: 20px; font-weight: 700;">Order Details</h3>
                    
                    <div class="detail-row">
                        <span class="detail-label">Order ID:</span>
                        <span class="detail-value">#<?= $orderDetails['id']; ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Tracking Number:</span>
                        <span class="detail-value" style="font-family: monospace; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 6px;"><?= htmlspecialchars($orderDetails['tracking_number']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?= $orderDetails['status']; ?>">
                                <?= ucfirst($orderDetails['status']); ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Order Date:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($orderDetails['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($orderDetails['shipped_at']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Shipped Date:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($orderDetails['shipped_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($orderDetails['delivered_at']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Delivered Date:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($orderDetails['delivered_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-row">
                        <span class="detail-label">Total Amount:</span>
                        <span class="detail-value" style="font-size: 18px; font-weight: 700;">₹<?= number_format($orderDetails['total_amount'], 2); ?></span>
                    </div>
                </div>

                <?php if ($courierDetails): ?>
                <div class="courier-tracking">
                    <div class="courier-logo">🚚</div>
                    <h3 style="color: white; margin-bottom: 10px; font-weight: 700;"><?= htmlspecialchars($courierDetails['name']); ?></h3>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 24px;">Click below to track on courier's official website</p>
                    
                    <a href="<?= htmlspecialchars($courierDetails['tracking_url']); ?>" target="_blank" class="btn-track-courier">
                        <i class="fas fa-external-link-alt"></i> TRACK ON <?= strtoupper(htmlspecialchars($courierDetails['name'])); ?>
                    </a>
                    
                    <p style="color: rgba(255, 255, 255, 0.6); margin-top: 20px; font-size: 13px;">
                        <i class="fas fa-info-circle"></i> Opens in new tab with tracking number auto-filled
                    </p>
                </div>
                <?php else: ?>
                <div class="alert-glass">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <p style="margin: 0; font-size: 16px;">Courier information not available yet. Please check back later.</p>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert-glass">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p style="margin: 0; font-size: 16px; font-weight: 600;">No order found with tracking number: <?= htmlspecialchars($trackingNumber); ?></p>
                    <p style="margin: 10px 0 0 0; font-size: 14px;">Please check the tracking number and try again.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
