<?php
/**
 * Courier Tracking Helper Functions
 */

/**
 * Get all active courier companies
 */
function get_active_couriers() {
    $db = get_db_connection();
    $stmt = $db->query("SELECT * FROM courier_companies WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get courier by ID
 */
function get_courier_by_id($courier_id) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM courier_companies WHERE id = ?");
    $stmt->execute([$courier_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate tracking URL for a tracking number
 */
function generate_tracking_url($courier_id, $tracking_number) {
    $courier = get_courier_by_id($courier_id);
    if (!$courier) {
        return null;
    }
    
    return str_replace('{TN}', $tracking_number, $courier['tracking_url_pattern']);
}

/**
 * Update order tracking information
 */
function update_order_tracking($order_id, $courier_id, $tracking_number) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        UPDATE orders 
        SET courier_company_id = ?, 
            tracking_number = ?, 
            shipped_at = NOW(),
            status = 'shipped'
        WHERE id = ?
    ");
    
    return $stmt->execute([$courier_id, $tracking_number, $order_id]);
}

/**
 * Get order tracking details
 */
function get_order_tracking($order_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT o.*, c.name as courier_name, c.tracking_url_pattern, c.code as courier_code
        FROM orders o
        LEFT JOIN courier_companies c ON o.courier_company_id = c.id
        WHERE o.id = ?
    ");
    
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && $order['tracking_number'] && $order['tracking_url_pattern']) {
        $order['tracking_url'] = str_replace('{TN}', $order['tracking_number'], $order['tracking_url_pattern']);
    }
    
    return $order;
}

/**
 * Add shipment tracking history entry
 */
function add_tracking_history($order_id, $status, $location = null, $description = null) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        INSERT INTO shipment_tracking_history (order_id, status, location, description, tracked_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([$order_id, $status, $location, $description]);
}

/**
 * Get tracking history for an order
 */
function get_tracking_history($order_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT * FROM shipment_tracking_history 
        WHERE order_id = ? 
        ORDER BY tracked_at DESC
    ");
    
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark order as delivered
 */
function mark_order_delivered($order_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        UPDATE orders 
        SET status = 'delivered', 
            delivered_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$order_id]);
    
    if ($result) {
        add_tracking_history($order_id, 'delivered', null, 'Order delivered successfully');
    }
    
    return $result;
}

/**
 * Validate tracking number format (basic validation)
 */
function validate_tracking_number($tracking_number) {
    // Remove spaces and convert to uppercase
    $tracking_number = strtoupper(str_replace(' ', '', $tracking_number));
    
    // Basic validation: alphanumeric, 8-30 characters
    if (preg_match('/^[A-Z0-9]{8,30}$/', $tracking_number)) {
        return $tracking_number;
    }
    
    return false;
}

/**
 * Send tracking notification email to customer
 */
function send_tracking_notification($order_id) {
    $order = get_order_tracking($order_id);
    
    if (!$order || !$order['tracking_number']) {
        return false;
    }
    
    $to = $order['email'];
    $subject = "Your Order #" . $order['id'] . " Has Been Shipped!";
    
    $tracking_url = base_url('track-shipment.php?tracking=' . urlencode($order['tracking_number']));
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .tracking-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #667eea; }
            .tracking-number { font-size: 24px; font-weight: bold; color: #667eea; font-family: monospace; }
            .button { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📦 Your Order Has Been Shipped!</h1>
            </div>
            <div class='content'>
                <p>Dear Customer,</p>
                <p>Great news! Your order <strong>#" . $order['id'] . "</strong> has been shipped and is on its way to you.</p>
                
                <div class='tracking-box'>
                    <p style='margin: 0 0 10px 0;'><strong>Tracking Number:</strong></p>
                    <p class='tracking-number'>" . htmlspecialchars($order['tracking_number']) . "</p>
                    <p style='margin: 10px 0 0 0;'><strong>Courier:</strong> " . htmlspecialchars($order['courier_name']) . "</p>
                </div>
                
                <p>You can track your shipment using the button below:</p>
                
                <div style='text-align: center;'>
                    <a href='" . $tracking_url . "' class='button'>TRACK YOUR SHIPMENT</a>
                </div>
                
                <p>Or copy this link: <a href='" . $tracking_url . "'>" . $tracking_url . "</a></p>
                
                <p>Thank you for shopping with us!</p>
                
                <div class='footer'>
                    <p>This is an automated email. Please do not reply.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . get_site_setting('site_email', 'noreply@example.com') . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}
