<?php
/**
 * Invoice Generation System
 * Handles automatic invoice creation, PDF generation, and management
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Generate unique invoice number
 * Format: INV-YYYYMMDD-XXXX
 */
function generate_invoice_number() {
    $date = date('Ymd');
    $db = get_db_connection();
    
    // Get the last invoice number for today
    $stmt = $db->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $pattern = "INV-{$date}-%";
    $stmt->execute([$pattern]);
    $lastInvoice = $stmt->fetch();
    
    if ($lastInvoice) {
        // Extract sequence number and increment
        $parts = explode('-', $lastInvoice['invoice_number']);
        $sequence = intval($parts[2]) + 1;
    } else {
        $sequence = 1;
    }
    
    return sprintf('INV-%s-%04d', $date, $sequence);
}

/**
 * Create invoice for an order
 * @param int $orderId Order ID
 * @return array|false Invoice data or false on failure
 */
function create_invoice($orderId) {
    try {
        $db = get_db_connection();
        
        // Fetch order details
        $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Check if invoice already exists
        $existingInvoice = db_fetch('SELECT * FROM invoices WHERE order_id = ?', [$orderId]);
        if ($existingInvoice) {
            return $existingInvoice;
        }
        
        // Generate invoice number
        $invoiceNumber = generate_invoice_number();
        
        // Calculate amounts
        $subtotal = floatval($order['total_amount']);
        $taxAmount = 0;
        $discountAmount = 0;
        $shippingAmount = 0;
        
        // Check if GST is enabled and calculate tax
        if (isset($order['gst_amount'])) {
            $taxAmount = floatval($order['gst_amount']);
            $subtotal = $subtotal - $taxAmount;
        }
        
        // Check for shipping charges
        if (isset($order['shipping_cost'])) {
            $shippingAmount = floatval($order['shipping_cost']);
        }
        
        $totalAmount = $subtotal + $taxAmount + $shippingAmount - $discountAmount;
        
        // Determine payment status
        $paymentStatus = 'pending';
        if (isset($order['payment_status'])) {
            $paymentStatus = $order['payment_status'];
        }
        
        // Insert invoice
        $stmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number, order_id, user_id, invoice_date, 
                subtotal, tax_amount, discount_amount, shipping_amount, total_amount,
                payment_status, payment_method
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $invoiceNumber,
            $orderId,
            $order['user_id'],
            $subtotal,
            $taxAmount,
            $discountAmount,
            $shippingAmount,
            $totalAmount,
            $paymentStatus,
            $order['payment_method'] ?? 'N/A'
        ]);
        
        $invoiceId = $db->lastInsertId();
        
        // Update order with invoice_id
        $db->prepare("UPDATE orders SET invoice_id = ? WHERE id = ?")->execute([$invoiceId, $orderId]);
        
        // Log invoice creation
        log_invoice_action($invoiceId, 'created', $order['user_id']);
        
        // Return invoice data
        return db_fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        
    } catch (Exception $e) {
        error_log("Invoice creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get invoice by ID
 */
function get_invoice($invoiceId) {
    return db_fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
}

/**
 * Get invoice by order ID
 */
function get_invoice_by_order($orderId) {
    return db_fetch('SELECT * FROM invoices WHERE order_id = ?', [$orderId]);
}

/**
 * Get invoice with full order details
 */
function get_invoice_details($invoiceId) {
    $invoice = get_invoice($invoiceId);
    if (!$invoice) {
        return null;
    }
    
    // Get order details
    $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$invoice['order_id']]);
    
    // Get order items
    $items = db_fetch_all(
        'SELECT oi.*, p.name, p.image, p.sku 
         FROM order_items oi 
         LEFT JOIN products p ON oi.product_id = p.id 
         WHERE oi.order_id = ?',
        [$invoice['order_id']]
    );
    
    // Get user details
    $user = db_fetch('SELECT * FROM users WHERE id = ?', [$invoice['user_id']]);
    
    return [
        'invoice' => $invoice,
        'order' => $order,
        'items' => $items,
        'user' => $user
    ];
}

/**
 * Log invoice action for audit trail
 */
function log_invoice_action($invoiceId, $action, $userId = null) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("
            INSERT INTO invoice_audit_log (invoice_id, action, performed_by, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $invoiceId,
            $action,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Invoice audit log error: " . $e->getMessage());
    }
}

/**
 * Check if user has access to invoice
 */
function user_can_access_invoice($invoiceId, $userId) {
    $invoice = get_invoice($invoiceId);
    if (!$invoice) {
        return false;
    }
    
    // User can access their own invoices
    if ($invoice['user_id'] == $userId) {
        return true;
    }
    
    // Admin can access all invoices
    if (isset($_SESSION['admin']) && $_SESSION['admin']) {
        return true;
    }
    
    return false;
}

/**
 * Get company details for invoice
 */
function get_company_details() {
    return [
        'name' => 'Gilaf Store',
        'logo' => base_url('assets/images/logo.png'),
        'address' => 'Your Company Address',
        'city' => 'Your City',
        'state' => 'Your State',
        'pincode' => '000000',
        'country' => 'India',
        'phone' => '+91 XXXXXXXXXX',
        'email' => 'support@gilafstore.com',
        'website' => 'www.gilafstore.com',
        'gstin' => 'XXXXXXXXXXXX', // GST Number if applicable
        'pan' => 'XXXXXXXXXX' // PAN Number if applicable
    ];
}

/**
 * Format currency for invoice
 */
function format_invoice_currency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Get invoice terms and conditions
 */
function get_invoice_terms() {
    return [
        'Payment is due within 30 days of invoice date.',
        'Please include the invoice number on your payment.',
        'All prices are in Indian Rupees (INR).',
        'Goods once sold will not be taken back or exchanged.',
        'For any queries, please contact our support team.'
    ];
}
