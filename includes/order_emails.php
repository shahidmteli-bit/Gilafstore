<?php
/**
 * Order Email Notifications
 * Sends order confirmation and cancellation emails with full order details
 */

require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/db_connect.php';
if (file_exists(__DIR__ . '/email_helper.php')) { require_once __DIR__ . '/email_helper.php'; }

// Safe wrapper: use dynamic routing if available, otherwise fall back to send_email()
if (!function_exists('send_task_email')) {
    function send_task_email($taskKey, $to, $subject, $body, $fallbackEmail = '', $fallbackName = 'Gilaf Store') {
        return send_email($to, $subject, $body, $fallbackEmail, $fallbackName);
    }
}

// Fallback email sender constants (used when no DB config exists for a task)
if (!defined('GILAF_ORDER_EMAIL'))    define('GILAF_ORDER_EMAIL', 'Gilaf.sales@gmail.com');
if (!defined('GILAF_ORDER_NAME'))     define('GILAF_ORDER_NAME', 'Gilaf Store');
if (!defined('GILAF_SECURITY_EMAIL')) define('GILAF_SECURITY_EMAIL', 'Security@gilafstore.com');
if (!defined('GILAF_SECURITY_NAME'))  define('GILAF_SECURITY_NAME', 'Gilaf Security Team');

/**
 * Generate absolute production URL for email assets (images, links)
 * Gmail requires full HTTPS URLs from a public domain
 */
function email_asset_url($path = '') {
    $baseUrl = 'https://gilafstore.com';
    $path = ltrim($path, '/');
    return $path ? $baseUrl . '/' . $path : $baseUrl;
}

/**
 * Send order confirmation email
 * @param int $orderId Database order ID
 * @param string $paymentMethod Payment method used (cod, upi, razorpay)
 * @return bool
 */
function send_order_confirmation_email($orderId, $paymentMethod = '') {
    error_log("ORDER_CONFIRM_EMAIL: START for order #$orderId, method=$paymentMethod");
    try {
        $db = get_db_connection();
        
        // Fetch order
        $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) {
            error_log("ORDER_CONFIRM_EMAIL: FAIL - Order #$orderId not found in DB");
            return false;
        }
        error_log("ORDER_CONFIRM_EMAIL: Order found, user_id=" . ($order['user_id'] ?? 'NULL'));
        
        // Fetch user
        $user = db_fetch('SELECT * FROM users WHERE id = ?', [$order['user_id']]);
        if (!$user || empty($user['email'])) {
            error_log("ORDER_CONFIRM_EMAIL: FAIL - User not found or no email for order #$orderId, user_id=" . ($order['user_id'] ?? 'NULL'));
            return false;
        }
        error_log("ORDER_CONFIRM_EMAIL: User found, email=" . $user['email']);
        
        // Fetch order items with product details
        $items = db_fetch_all(
            'SELECT oi.*, p.name as product_name, p.image, pw.display_weight
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.price = oi.price
             WHERE oi.order_id = ?',
            [$orderId]
        );
        error_log("ORDER_CONFIRM_EMAIL: Items count=" . count($items));
        
        if (empty($items)) {
            error_log("ORDER_CONFIRM_EMAIL: FAIL - No items found for order #$orderId");
            return false;
        }
        
        $customerName = htmlspecialchars($user['name'] ?? 'Customer');
        $customerEmail = $user['email'];
        $orderTotal = number_format((float)$order['total_amount'], 2);
        $orderDate = date('d M Y, h:i A', strtotime($order['created_at']));
        $pmRaw = strtolower(trim($paymentMethod ?: ($order['payment_method'] ?? '')));
        $pmLabel = $pmRaw === 'razorpay' ? 'Razorpay' : ($pmRaw === 'upi' ? 'UPI' : ($pmRaw === 'cod' ? 'Cash on Delivery' : ($pmRaw !== '' ? strtoupper($pmRaw) : 'N/A')));
        $orderStatus = ucfirst(str_replace('_', ' ', $order['order_status'] ?? 'Processing'));
        
        // Parse shipping address
        $shippingAddress = '';
        if (!empty($order['shipping_address'])) {
            $addrData = json_decode($order['shipping_address'], true);
            if (is_array($addrData)) {
                $parts = array_filter([
                    $addrData['full_name'] ?? '',
                    $addrData['address_line1'] ?? '',
                    $addrData['address_line2'] ?? '',
                    $addrData['city'] ?? '',
                    $addrData['state'] ?? '',
                    $addrData['pincode'] ?? '',
                ]);
                $shippingAddress = implode(', ', $parts);
                if (!empty($addrData['phone'])) {
                    $shippingAddress .= '<br>Phone: ' . htmlspecialchars($addrData['phone']);
                }
            } else {
                $shippingAddress = htmlspecialchars($order['shipping_address']);
            }
        }
        
        // Build items HTML
        $itemsHtml = '';
        $itemSubtotal = 0;
        foreach ($items as $item) {
            $name = htmlspecialchars($item['product_name'] ?? 'Product');
            $weight = !empty($item['display_weight']) ? ' — ' . htmlspecialchars($item['display_weight']) : '';
            $qty = (int)$item['quantity'];
            $price = (float)$item['price'];
            $lineTotal = $price * $qty;
            $itemSubtotal += $lineTotal;
            
            $imgUrl = '';
            if (!empty($item['image'])) {
                $imgUrl = email_asset_url('assets/images/products/' . $item['image']);
            }
            
            $imgHtml = $imgUrl 
                ? '<img src="' . $imgUrl . '" alt="" width="60" height="60" style="width:60px;height:60px;object-fit:contain;border-radius:8px;border:1px solid #e5e7eb;display:block;">'
                : '<div style="width:60px;height:60px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:20px;">📦</div>';
            
            $itemsHtml .= '
            <tr>
                <td style="padding:12px;border-bottom:1px solid #f3f4f6;">
                    <table cellpadding="0" cellspacing="0" border="0"><tr>
                        <td style="vertical-align:middle;padding-right:12px;">' . $imgHtml . '</td>
                        <td style="vertical-align:middle;">
                            <div style="font-weight:600;color:#1f2937;font-size:14px;">' . $name . $weight . '</div>
                            <div style="color:#6b7280;font-size:12px;margin-top:2px;">Qty: ' . $qty . '</div>
                        </td>
                    </tr></table>
                </td>
                <td style="padding:12px;border-bottom:1px solid #f3f4f6;text-align:right;vertical-align:middle;">
                    <div style="font-weight:700;color:#1f2937;font-size:14px;">₹' . number_format($lineTotal, 2) . '</div>
                    <div style="color:#6b7280;font-size:11px;">₹' . number_format($price, 2) . ' × ' . $qty . '</div>
                </td>
            </tr>';
        }
        
        // UPI discount
        $upiDiscount = (float)($order['upi_discount'] ?? 0);
        $discountHtml = '';
        if ($upiDiscount > 0) {
            $discountHtml = '
            <tr>
                <td style="padding:8px 16px;color:#059669;font-size:14px;">UPI Discount</td>
                <td style="padding:8px 16px;text-align:right;color:#059669;font-weight:600;font-size:14px;">-₹' . number_format($upiDiscount, 2) . '</td>
            </tr>';
        }
        
        // Address section
        $addressHtml = '';
        if ($shippingAddress) {
            $addressHtml = '
            <div style="margin-top:24px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;">
                <div style="font-weight:700;color:#166534;font-size:14px;margin-bottom:8px;">📍 Delivery Address</div>
                <div style="color:#374151;font-size:13px;line-height:1.6;">' . $shippingAddress . '</div>
            </div>';
        }
        
        $subject = "Order Confirmed! 🎉 Your Gilaf Store Order #" . $orderId;
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;background:#f4f7fa;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                
                <!-- Header -->
                <div style="background:linear-gradient(135deg,#1A3C34 0%,#2d5a4d 100%);border-radius:16px 16px 0 0;padding:36px 30px;text-align:center;">
                    <div style="font-size:46px;margin-bottom:10px;">🛍️</div>
                    <div style="color:rgba(255,255,255,0.9);font-size:12px;letter-spacing:2px;font-weight:700;">GILAF STORE</div>
                    <h1 style="color:#ffffff;font-size:24px;font-weight:800;margin:10px 0 0;">Order Confirmed!</h1>
                    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:8px 0 0;">Thank you for shopping with Gilaf Store</p>
                </div>
                
                <!-- Order Info Bar -->
                <div style="background:#C5A059;padding:14px 30px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                        <td style="color:#fff;font-size:13px;font-weight:600;">Order #' . $orderId . '</td>
                        <td style="color:#fff;font-size:13px;text-align:center;">' . $orderDate . '</td>
                        <td style="color:#fff;font-size:13px;text-align:right;font-weight:700;">
                            ' . (
                                $pmRaw === 'razorpay'
                                    ? '<span style="display:inline-flex;align-items:center;gap:6px;justify-content:flex-end;"><img src="https://razorpay.com/assets/razorpay-glyph.svg" alt="Razorpay" style="height:14px;vertical-align:middle;filter:brightness(10);">Razorpay</span>'
                                    : htmlspecialchars($pmLabel)
                            ) . '
                        </td>
                    </tr></table>
                </div>
                
                <!-- Body -->
                <div style="background:#ffffff;padding:30px;border-radius:0 0 16px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    
                    <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 20px;">
                        Hi <strong>' . $customerName . '</strong>,<br>
                        We\'re excited to let you know that your order has been placed successfully! Here\'s a summary of what you ordered:
                    </p>
                    
                    <!-- Items Table -->
                    <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                        <div style="background:#f9fafb;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                            <strong style="color:#374151;font-size:14px;">🛒 Order Items</strong>
                        </div>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            ' . $itemsHtml . '
                        </table>
                    </div>
                    
                    <!-- Price Summary -->
                    <div style="margin-top:20px;background:#f9fafb;border-radius:12px;padding:4px 0;overflow:hidden;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:8px 16px;color:#6b7280;font-size:14px;">Subtotal</td>
                                <td style="padding:8px 16px;text-align:right;color:#374151;font-weight:600;font-size:14px;">₹' . number_format($itemSubtotal, 2) . '</td>
                            </tr>
                            ' . $discountHtml . '
                            <tr>
                                <td colspan="2" style="border-top:2px solid #e5e7eb;"></td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;color:#1f2937;font-size:16px;font-weight:700;">Total Paid</td>
                                <td style="padding:12px 16px;text-align:right;color:#1A3C34;font-size:18px;font-weight:800;">₹' . $orderTotal . '</td>
                            </tr>
                        </table>
                    </div>
                    
                    ' . $addressHtml . '
                    
                    <!-- Status -->
                    <div style="margin-top:24px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;text-align:center;">
                        <div style="font-size:13px;color:#1e40af;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Current Status</div>
                        <div style="font-size:20px;color:#1e3a8a;font-weight:700;margin-top:4px;">' . $orderStatus . '</div>
                    </div>
                    
                    <!-- Track Order Button -->
                    <div style="text-align:center;margin-top:24px;">
                        <a href="' . email_asset_url('user/orders.php') . '" style="display:inline-block;padding:14px 40px;background:#C5A059;color:#ffffff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">Track Your Order</a>
                    </div>
                    
                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">
                            If you have any questions about your order, feel free to reach out to us. We\'re here to help!
                        </p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="text-align:center;padding:24px;color:#9ca3af;font-size:12px;">
                    <strong style="color:#6b7280;">GILAF STORE</strong><br>
                    Premium Quality Products<br>
                    © ' . date('Y') . ' Gilaf Store. All rights reserved.
                </div>
            </div>
        </body>
        </html>';
        
        $result = send_task_email('order_confirmation', $customerEmail, $subject, $body, GILAF_ORDER_EMAIL, GILAF_ORDER_NAME);
        error_log("Order confirmation email " . ($result ? "SENT" : "FAILED") . " for order #$orderId to $customerEmail");
        return $result;
        
    } catch (\Throwable $e) {
        error_log("ORDER_CONFIRM_EMAIL: EXCEPTION for order #$orderId: " . get_class($e) . " - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return false;
    }
}

/**
 * Send order cancellation email
 * @param int $orderId Database order ID
 * @param string $reason Cancellation reason (optional)
 * @return bool
 */
function send_order_cancellation_email($orderId, $reason = '') {
    try {
        $db = get_db_connection();
        
        // Fetch order
        $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) {
            error_log("Order cancellation email: Order #$orderId not found");
            return false;
        }
        
        // Fetch user
        $user = db_fetch('SELECT * FROM users WHERE id = ?', [$order['user_id']]);
        if (!$user || empty($user['email'])) {
            error_log("Order cancellation email: User not found or no email for order #$orderId");
            return false;
        }
        
        // Fetch order items
        $items = db_fetch_all(
            'SELECT oi.*, p.name as product_name, pw.display_weight
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.price = oi.price
             WHERE oi.order_id = ?',
            [$orderId]
        );
        
        $customerName = htmlspecialchars($user['name'] ?? 'Customer');
        $customerEmail = $user['email'];
        $orderTotal = number_format((float)$order['total_amount'], 2);
        $orderDate = date('d M Y, h:i A', strtotime($order['created_at']));
        $pm = strtoupper($order['payment_method'] ?? 'N/A');
        
        // Build items list
        $itemsHtml = '';
        foreach ($items as $item) {
            $name = htmlspecialchars($item['product_name'] ?? 'Product');
            $weight = !empty($item['display_weight']) ? ' — ' . htmlspecialchars($item['display_weight']) : '';
            $qty = (int)$item['quantity'];
            $price = (float)$item['price'];
            $lineTotal = $price * $qty;
            
            $itemsHtml .= '
            <tr>
                <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;">
                    <div style="font-weight:600;color:#6b7280;font-size:14px;text-decoration:line-through;">' . $name . $weight . '</div>
                    <div style="color:#9ca3af;font-size:12px;">Qty: ' . $qty . '</div>
                </td>
                <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;text-align:right;vertical-align:middle;">
                    <div style="font-weight:600;color:#6b7280;font-size:14px;text-decoration:line-through;">₹' . number_format($lineTotal, 2) . '</div>
                </td>
            </tr>';
        }
        
        // Refund notice
        $refundHtml = '';
        if ($pm !== 'COD') {
            $refundHtml = '
            <div style="margin-top:20px;background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:20px;">
                <div style="font-weight:700;color:#92400e;font-size:14px;margin-bottom:6px;">💰 Refund Information</div>
                <div style="color:#78350f;font-size:13px;line-height:1.6;">
                    Your refund of <strong>₹' . $orderTotal . '</strong> will be processed within 5-7 business days to your original payment method (' . $pm . ').
                </div>
            </div>';
        }
        
        // Reason
        $reasonHtml = '';
        if ($reason) {
            $reasonHtml = '
            <div style="margin-top:16px;background:#f9fafb;border-radius:8px;padding:14px 16px;">
                <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Reason</div>
                <div style="font-size:14px;color:#374151;margin-top:4px;">' . htmlspecialchars($reason) . '</div>
            </div>';
        }
        
        $subject = "Order Cancelled — Gilaf Store Order #" . $orderId;
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;background:#f4f7fa;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                
                <!-- Header -->
                <div style="background:linear-gradient(135deg,#991b1b 0%,#b91c1c 100%);border-radius:16px 16px 0 0;padding:40px 30px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:12px;">❌</div>
                    <h1 style="color:#ffffff;font-size:24px;font-weight:700;margin:0;">Order Cancelled</h1>
                    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:8px 0 0;">Order #' . $orderId . ' has been cancelled</p>
                </div>
                
                <!-- Body -->
                <div style="background:#ffffff;padding:30px;border-radius:0 0 16px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    
                    <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 20px;">
                        Hi <strong>' . $customerName . '</strong>,<br>
                        We\'re sorry to inform you that your order <strong>#' . $orderId . '</strong> placed on <strong>' . $orderDate . '</strong> has been cancelled.
                    </p>
                    
                    ' . $reasonHtml . '
                    
                    <!-- Cancelled Items -->
                    <div style="margin-top:20px;border:1px solid #fecaca;border-radius:12px;overflow:hidden;">
                        <div style="background:#fef2f2;padding:12px 16px;border-bottom:1px solid #fecaca;">
                            <strong style="color:#991b1b;font-size:14px;">Cancelled Items</strong>
                        </div>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            ' . $itemsHtml . '
                        </table>
                        <div style="padding:12px 16px;background:#fef2f2;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                                <td style="font-weight:700;color:#991b1b;font-size:15px;">Order Total</td>
                                <td style="text-align:right;font-weight:700;color:#991b1b;font-size:15px;text-decoration:line-through;">₹' . $orderTotal . '</td>
                            </tr></table>
                        </div>
                    </div>
                    
                    ' . $refundHtml . '
                    
                    <!-- Shop Again -->
                    <div style="text-align:center;margin-top:28px;">
                        <p style="color:#6b7280;font-size:14px;margin-bottom:16px;">We\'d love to have you back!</p>
                        <a href="' . email_asset_url('shop.php') . '" style="display:inline-block;padding:14px 40px;background:#1A3C34;color:#ffffff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">Continue Shopping</a>
                    </div>
                    
                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">
                            If you didn\'t request this cancellation or have questions, please contact our support team immediately.
                        </p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="text-align:center;padding:24px;color:#9ca3af;font-size:12px;">
                    <strong style="color:#6b7280;">GILAF STORE</strong><br>
                    Premium Quality Products<br>
                    © ' . date('Y') . ' Gilaf Store. All rights reserved.
                </div>
            </div>
        </body>
        </html>';
        
        $result = send_task_email('order_cancellation', $customerEmail, $subject, $body, GILAF_ORDER_EMAIL, GILAF_ORDER_NAME);
        error_log("Order cancellation email " . ($result ? "SENT" : "FAILED") . " for order #$orderId to $customerEmail");
        return $result;
        
    } catch (Exception $e) {
        error_log("Order cancellation email exception for order #$orderId: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order shipped email with tracking info
 * @param int $orderId
 * @param string $courierCompany
 * @param string $trackingId
 * @return bool
 */
function send_order_shipped_email($orderId, $courierCompany = '', $trackingId = '') {
    try {
        $db = get_db_connection();
        
        $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) return false;
        
        $user = db_fetch('SELECT * FROM users WHERE id = ?', [$order['user_id']]);
        if (!$user || empty($user['email'])) return false;
        
        $items = db_fetch_all(
            'SELECT oi.*, p.name as product_name, p.image, pw.display_weight
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.price = oi.price
             WHERE oi.order_id = ?',
            [$orderId]
        );
        
        $customerName = htmlspecialchars($user['name'] ?? 'Customer');
        $customerEmail = $user['email'];
        $orderTotal = number_format((float)$order['total_amount'], 2);
        $orderDate = date('d M Y', strtotime($order['created_at']));
        $courier = htmlspecialchars($courierCompany ?: ($order['courier_company'] ?? 'Our delivery partner'));
        $tracking = htmlspecialchars($trackingId ?: ($order['tracking_id'] ?? ''));
        
        // Build items list
        $itemsHtml = '';
        foreach ($items as $item) {
            $name = htmlspecialchars($item['product_name'] ?? 'Product');
            $weight = !empty($item['display_weight']) ? ' — ' . htmlspecialchars($item['display_weight']) : '';
            $qty = (int)$item['quantity'];
            $imgUrl = !empty($item['image']) ? email_asset_url('assets/images/products/' . $item['image']) : '';
            $imgHtml = $imgUrl
                ? '<img src="' . $imgUrl . '" alt="" width="50" height="50" style="width:50px;height:50px;object-fit:contain;border-radius:8px;border:1px solid #e5e7eb;display:block;">'
                : '<div style="width:50px;height:50px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">📦</div>';
            
            $itemsHtml .= '
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <table cellpadding="0" cellspacing="0" border="0"><tr>
                        <td style="vertical-align:middle;padding-right:12px;">' . $imgHtml . '</td>
                        <td style="vertical-align:middle;">
                            <div style="font-weight:600;color:#1f2937;font-size:13px;">' . $name . $weight . '</div>
                            <div style="color:#6b7280;font-size:12px;">Qty: ' . $qty . '</div>
                        </td>
                    </tr></table>
                </td>
            </tr>';
        }
        
        // Tracking section
        $trackingHtml = '';
        if ($tracking) {
            $trackingHtml = '
            <div style="margin-top:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;">
                <div style="font-size:12px;color:#166534;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Tracking ID</div>
                <div style="font-size:22px;color:#15803d;font-weight:800;margin-top:6px;letter-spacing:2px;font-family:monospace;">' . $tracking . '</div>
                <div style="color:#6b7280;font-size:12px;margin-top:6px;">via ' . $courier . '</div>
            </div>';
        }
        
        // Shipping address
        $addressHtml = '';
        if (!empty($order['shipping_address'])) {
            $addrData = json_decode($order['shipping_address'], true);
            $addrStr = '';
            if (is_array($addrData)) {
                $parts = array_filter([$addrData['full_name'] ?? '', $addrData['address_line1'] ?? '', $addrData['address_line2'] ?? '', $addrData['city'] ?? '', $addrData['state'] ?? '', $addrData['pincode'] ?? '']);
                $addrStr = implode(', ', $parts);
            } else {
                $addrStr = htmlspecialchars($order['shipping_address']);
            }
            if ($addrStr) {
                $addressHtml = '
                <div style="margin-top:16px;background:#f9fafb;border-radius:8px;padding:14px 16px;">
                    <div style="font-size:12px;color:#6b7280;font-weight:600;">DELIVERING TO</div>
                    <div style="font-size:13px;color:#374151;margin-top:4px;">' . $addrStr . '</div>
                </div>';
            }
        }
        
        $subject = "Your Order #$orderId Has Been Shipped! 🚚";
        
        $body = '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;background:#f4f7fa;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                
                <div style="background:linear-gradient(135deg,#1e40af 0%,#3b82f6 100%);border-radius:16px 16px 0 0;padding:40px 30px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:12px;">🚚</div>
                    <h1 style="color:#ffffff;font-size:24px;font-weight:700;margin:0;">Your Order is On Its Way!</h1>
                    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:8px 0 0;">Order #' . $orderId . ' • Placed on ' . $orderDate . '</p>
                </div>
                
                <div style="background:#ffffff;padding:30px;border-radius:0 0 16px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    
                    <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 20px;">
                        Hi <strong>' . $customerName . '</strong>,<br>
                        Great news! Your order has been shipped and is on its way to you.
                    </p>
                    
                    ' . $trackingHtml . '
                    ' . $addressHtml . '
                    
                    <!-- Shipped Items -->
                    <div style="margin-top:20px;">
                        <div style="font-weight:700;color:#374151;font-size:14px;margin-bottom:10px;">📦 Items in this shipment</div>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            ' . $itemsHtml . '
                        </table>
                    </div>
                    
                    <!-- Estimated Delivery -->
                    <div style="margin-top:20px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;text-align:center;">
                        <div style="font-size:13px;color:#1e40af;font-weight:600;">ESTIMATED DELIVERY</div>
                        <div style="font-size:18px;color:#1e3a8a;font-weight:700;margin-top:4px;">3-7 Business Days</div>
                    </div>
                    
                    <div style="text-align:center;margin-top:24px;">
                        <a href="' . email_asset_url('user/orders.php') . '" style="display:inline-block;padding:14px 40px;background:#3b82f6;color:#ffffff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">Track Your Order</a>
                    </div>
                    
                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">Need help? Contact our support team anytime.</p>
                    </div>
                </div>
                
                <div style="text-align:center;padding:24px;color:#9ca3af;font-size:12px;">
                    <strong style="color:#6b7280;">GILAF STORE</strong><br>Premium Quality Products<br>© ' . date('Y') . ' Gilaf Store. All rights reserved.
                </div>
            </div>
        </body></html>';
        
        $result = send_task_email('order_shipped', $customerEmail, $subject, $body, GILAF_ORDER_EMAIL, GILAF_ORDER_NAME);
        error_log("Order shipped email " . ($result ? "SENT" : "FAILED") . " for order #$orderId to $customerEmail");
        return $result;
        
    } catch (Exception $e) {
        error_log("Order shipped email exception for order #$orderId: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order delivered email
 * @param int $orderId
 * @return bool
 */
function send_order_delivered_email($orderId) {
    try {
        $db = get_db_connection();
        
        $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) return false;
        
        $user = db_fetch('SELECT * FROM users WHERE id = ?', [$order['user_id']]);
        if (!$user || empty($user['email'])) return false;
        
        $items = db_fetch_all(
            'SELECT oi.*, p.name as product_name, pw.display_weight
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_weights pw ON pw.product_id = p.id AND pw.price = oi.price
             WHERE oi.order_id = ?',
            [$orderId]
        );
        
        $customerName = htmlspecialchars($user['name'] ?? 'Customer');
        $customerEmail = $user['email'];
        $orderTotal = number_format((float)$order['total_amount'], 2);
        
        $itemsList = '';
        foreach ($items as $item) {
            $name = htmlspecialchars($item['product_name'] ?? 'Product');
            $weight = !empty($item['display_weight']) ? ' — ' . htmlspecialchars($item['display_weight']) : '';
            $qty = (int)$item['quantity'];
            $itemsList .= '<li style="padding:6px 0;color:#374151;font-size:14px;">' . $name . $weight . ' × ' . $qty . '</li>';
        }
        
        $subject = "Order #$orderId Delivered! 🎉 Enjoy your purchase";
        
        $body = '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;background:#f4f7fa;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                
                <div style="background:linear-gradient(135deg,#059669 0%,#10b981 100%);border-radius:16px 16px 0 0;padding:40px 30px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:12px;">🎉</div>
                    <h1 style="color:#ffffff;font-size:24px;font-weight:700;margin:0;">Your Order Has Been Delivered!</h1>
                    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:8px 0 0;">Order #' . $orderId . '</p>
                </div>
                
                <div style="background:#ffffff;padding:30px;border-radius:0 0 16px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    
                    <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 20px;">
                        Hi <strong>' . $customerName . '</strong>,<br>
                        Your order has been successfully delivered! We hope you love your purchase.
                    </p>
                    
                    <!-- Delivered Items -->
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;">
                        <div style="font-weight:700;color:#166534;font-size:14px;margin-bottom:8px;">✅ Delivered Items</div>
                        <ul style="margin:0;padding-left:20px;list-style:none;">
                            ' . $itemsList . '
                        </ul>
                        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #bbf7d0;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                                <td style="font-weight:700;color:#166534;font-size:15px;">Total Paid</td>
                                <td style="text-align:right;font-weight:800;color:#166534;font-size:16px;">₹' . $orderTotal . '</td>
                            </tr></table>
                        </div>
                    </div>
                    
                    <!-- Feedback Request -->
                    <div style="margin-top:24px;background:#fefce8;border:1px solid #fde68a;border-radius:12px;padding:20px;text-align:center;">
                        <div style="font-size:28px;margin-bottom:8px;">⭐⭐⭐⭐⭐</div>
                        <div style="font-weight:700;color:#92400e;font-size:15px;">How was your experience?</div>
                        <p style="color:#78350f;font-size:13px;margin:6px 0 0;">Your feedback helps us serve you better!</p>
                    </div>
                    
                    <div style="text-align:center;margin-top:24px;">
                        <a href="' . email_asset_url('shop.php') . '" style="display:inline-block;padding:14px 40px;background:#C5A059;color:#ffffff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">Shop Again</a>
                    </div>
                    
                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">
                            Something wrong with your order? Contact our support team within 7 days for returns or exchanges.
                        </p>
                    </div>
                </div>
                
                <div style="text-align:center;padding:24px;color:#9ca3af;font-size:12px;">
                    <strong style="color:#6b7280;">GILAF STORE</strong><br>Premium Quality Products<br>© ' . date('Y') . ' Gilaf Store. All rights reserved.
                </div>
            </div>
        </body></html>';
        
        $result = send_task_email('order_delivered', $customerEmail, $subject, $body, GILAF_ORDER_EMAIL, GILAF_ORDER_NAME);
        error_log("Order delivered email " . ($result ? "SENT" : "FAILED") . " for order #$orderId to $customerEmail");
        return $result;
        
    } catch (Exception $e) {
        error_log("Order delivered email exception for order #$orderId: " . $e->getMessage());
        return false;
    }
}

/**
 * Send refund processed email
 * @param int $orderId
 * @param float $refundAmount
 * @param string $refundMethod
 * @return bool
 */
function send_order_refund_email($orderId, $refundAmount = 0, $refundMethod = '') {
    try {
        $db = get_db_connection();
        
        $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) return false;
        
        $user = db_fetch('SELECT * FROM users WHERE id = ?', [$order['user_id']]);
        if (!$user || empty($user['email'])) return false;
        
        $customerName = htmlspecialchars($user['name'] ?? 'Customer');
        $customerEmail = $user['email'];
        $amount = $refundAmount > 0 ? $refundAmount : (float)$order['total_amount'];
        $method = $refundMethod ?: strtoupper($order['payment_method'] ?? 'Original Payment Method');
        
        $subject = "Refund Processed — ₹" . number_format($amount, 2) . " for Order #$orderId";
        
        $body = '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;background:#f4f7fa;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                
                <div style="background:linear-gradient(135deg,#7c3aed 0%,#8b5cf6 100%);border-radius:16px 16px 0 0;padding:40px 30px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:12px;">💰</div>
                    <h1 style="color:#ffffff;font-size:24px;font-weight:700;margin:0;">Refund Processed</h1>
                    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:8px 0 0;">Order #' . $orderId . '</p>
                </div>
                
                <div style="background:#ffffff;padding:30px;border-radius:0 0 16px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    
                    <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 20px;">
                        Hi <strong>' . $customerName . '</strong>,<br>
                        We\'ve processed your refund for Order #' . $orderId . '. Here are the details:
                    </p>
                    
                    <!-- Refund Amount -->
                    <div style="background:linear-gradient(135deg,#f5f3ff 0%,#ede9fe 100%);border:2px solid #c4b5fd;border-radius:16px;padding:30px;text-align:center;">
                        <div style="font-size:13px;color:#6d28d9;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Refund Amount</div>
                        <div style="font-size:36px;color:#5b21b6;font-weight:800;margin-top:8px;">₹' . number_format($amount, 2) . '</div>
                        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #c4b5fd;">
                            <span style="font-size:12px;color:#7c3aed;font-weight:600;">REFUND TO: ' . htmlspecialchars($method) . '</span>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div style="margin-top:24px;background:#fefce8;border:1px solid #fde68a;border-radius:12px;padding:20px;">
                        <div style="font-weight:700;color:#92400e;font-size:14px;margin-bottom:10px;">⏱️ When will I receive my refund?</div>
                        <ul style="margin:0;padding-left:20px;">
                            <li style="color:#78350f;font-size:13px;padding:4px 0;"><strong>UPI / Wallet:</strong> 1-3 business days</li>
                            <li style="color:#78350f;font-size:13px;padding:4px 0;"><strong>Credit/Debit Card:</strong> 5-7 business days</li>
                            <li style="color:#78350f;font-size:13px;padding:4px 0;"><strong>Net Banking:</strong> 5-10 business days</li>
                        </ul>
                    </div>
                    
                    <div style="text-align:center;margin-top:24px;">
                        <a href="' . email_asset_url('shop.php') . '" style="display:inline-block;padding:14px 40px;background:#7c3aed;color:#ffffff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">Continue Shopping</a>
                    </div>
                    
                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">
                            If you don\'t receive your refund within the expected timeframe, please contact our support team.
                        </p>
                    </div>
                </div>
                
                <div style="text-align:center;padding:24px;color:#9ca3af;font-size:12px;">
                    <strong style="color:#6b7280;">GILAF STORE</strong><br>Premium Quality Products<br>© ' . date('Y') . ' Gilaf Store. All rights reserved.
                </div>
            </div>
        </body></html>';
        
        $result = send_task_email('order_refund', $customerEmail, $subject, $body, GILAF_ORDER_EMAIL, GILAF_ORDER_NAME);
        error_log("Order refund email " . ($result ? "SENT" : "FAILED") . " for order #$orderId to $customerEmail");
        return $result;
        
    } catch (Exception $e) {
        error_log("Order refund email exception for order #$orderId: " . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome email on new user registration
 * @param string $email
 * @param string $name
 * @return bool
 */
function send_welcome_email($email, $name = 'Customer') {
    try {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Welcome email: Invalid email - $email");
            return false;
        }
        
        $customerName = htmlspecialchars($name);
        
        $subject = "Welcome to Gilaf Store! 🎉 Your account is ready";
        
        $body = '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;background:#f4f7fa;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                
                <div style="background:linear-gradient(135deg,#1A3C34 0%,#2d5a4d 100%);border-radius:16px 16px 0 0;padding:50px 30px;text-align:center;">
                    <div style="font-size:56px;margin-bottom:16px;">🎊</div>
                    <h1 style="color:#ffffff;font-size:28px;font-weight:700;margin:0;">Welcome to Gilaf Store!</h1>
                    <p style="color:rgba(255,255,255,0.85);font-size:15px;margin:10px 0 0;">Premium Quality, Delivered to Your Door</p>
                </div>
                
                <div style="background:#C5A059;padding:14px 30px;text-align:center;">
                    <span style="color:#fff;font-size:14px;font-weight:700;letter-spacing:0.5px;">🌟 Your Account is Ready!</span>
                </div>
                
                <div style="background:#ffffff;padding:30px;border-radius:0 0 16px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    
                    <p style="color:#374151;font-size:16px;line-height:1.6;margin:0 0 20px;">
                        Hi <strong>' . $customerName . '</strong>,<br><br>
                        Thank you for joining the Gilaf Store family! We\'re thrilled to have you with us. Get ready to explore our premium range of products.
                    </p>
                    
                    <!-- Features -->
                    <div style="margin:24px 0;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:12px;background:#f0fdf4;border-radius:12px;text-align:center;width:33%;">
                                    <div style="font-size:28px;margin-bottom:6px;">🍃</div>
                                    <div style="font-weight:700;color:#166534;font-size:13px;">100% Natural</div>
                                    <div style="color:#6b7280;font-size:11px;margin-top:2px;">Pure & Organic</div>
                                </td>
                                <td style="width:8px;"></td>
                                <td style="padding:12px;background:#eff6ff;border-radius:12px;text-align:center;width:33%;">
                                    <div style="font-size:28px;margin-bottom:6px;">🚚</div>
                                    <div style="font-weight:700;color:#1e40af;font-size:13px;">Fast Delivery</div>
                                    <div style="color:#6b7280;font-size:11px;margin-top:2px;">Pan India Shipping</div>
                                </td>
                                <td style="width:8px;"></td>
                                <td style="padding:12px;background:#fef3c7;border-radius:12px;text-align:center;width:33%;">
                                    <div style="font-size:28px;margin-bottom:6px;">💎</div>
                                    <div style="font-weight:700;color:#92400e;font-size:13px;">Premium Quality</div>
                                    <div style="color:#6b7280;font-size:11px;margin-top:2px;">Lab Tested</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- What you can do -->
                    <div style="background:#f9fafb;border-radius:12px;padding:20px;margin-top:20px;">
                        <div style="font-weight:700;color:#374151;font-size:15px;margin-bottom:12px;">Here\'s what you can do:</div>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr><td style="padding:6px 0;color:#374151;font-size:14px;">🛒 Browse our premium product collection</td></tr>
                            <tr><td style="padding:6px 0;color:#374151;font-size:14px;">💰 Get exclusive deals and offers</td></tr>
                            <tr><td style="padding:6px 0;color:#374151;font-size:14px;">📦 Track your orders in real-time</td></tr>
                            <tr><td style="padding:6px 0;color:#374151;font-size:14px;">🎫 Use promo codes for extra savings</td></tr>
                        </table>
                    </div>
                    
                    <div style="text-align:center;margin-top:28px;">
                        <a href="' . email_asset_url('shop.php') . '" style="display:inline-block;padding:16px 50px;background:linear-gradient(135deg,#1A3C34 0%,#2d5a4d 100%);color:#ffffff;text-decoration:none;border-radius:50px;font-weight:700;font-size:15px;text-transform:uppercase;letter-spacing:0.5px;">Start Shopping</a>
                    </div>
                    
                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;text-align:center;">
                        <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0;">
                            Need help? Our support team is always here for you.<br>
                            <a href="' . email_asset_url('contact.php') . '" style="color:#C5A059;font-weight:600;text-decoration:none;">Contact Support</a>
                        </p>
                    </div>
                </div>
                
                <div style="text-align:center;padding:24px;color:#9ca3af;font-size:12px;">
                    <strong style="color:#6b7280;">GILAF STORE</strong><br>Premium Quality Products<br>© ' . date('Y') . ' Gilaf Store. All rights reserved.
                </div>
            </div>
        </body></html>';
        
        $result = send_task_email('welcome_email', $email, $subject, $body, GILAF_ORDER_EMAIL, GILAF_ORDER_NAME);
        error_log("Welcome email " . ($result ? "SENT" : "FAILED") . " to $email");
        return $result;
        
    } catch (Exception $e) {
        error_log("Welcome email exception: " . $e->getMessage());
        return false;
    }
}
