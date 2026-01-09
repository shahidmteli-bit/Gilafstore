<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Log script start
error_log("=== UPI Payment Confirmation Script Started ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Session ID: " . session_id());

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    error_log("ERROR: User not authenticated");
    redirect_with_message('/user/login_final.php', 'Please login to continue', 'info');
    exit;
}

$userId = $_SESSION['user']['id'];
error_log("User authenticated - User ID: {$userId}");

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Not a POST request - Method: " . $_SERVER['REQUEST_METHOD']);
    redirect_with_message('/checkout.php', 'Invalid request method', 'error');
    exit;
}

// Get and validate transaction ID
$transactionId = trim($_POST['transaction_id'] ?? '');
$orderId = trim($_POST['order_id'] ?? '');

error_log("Received POST data - Transaction ID: {$transactionId}, Order ID: {$orderId}");

// Validate transaction ID format (10-16 digits)
if (empty($transactionId)) {
    error_log("ERROR: Transaction ID is empty");
    $_SESSION['payment_error'] = 'Please enter a transaction ID';
    header('Location: upi_payment.php');
    exit;
}

if (strlen($transactionId) < 10) {
    error_log("ERROR: Transaction ID too short - Length: " . strlen($transactionId));
    $_SESSION['payment_error'] = 'Transaction ID must be at least 10 characters';
    header('Location: upi_payment.php');
    exit;
}

if (!preg_match('/^[A-Za-z0-9]+$/', $transactionId)) {
    error_log("ERROR: Transaction ID contains invalid characters");
    $_SESSION['payment_error'] = 'Transaction ID can only contain letters and numbers';
    header('Location: upi_payment.php');
    exit;
}

error_log("Transaction ID validation passed");

// Check if order exists in session
if (!isset($_SESSION['pending_order'])) {
    error_log("ERROR: No pending order in session");
    error_log("Available session keys: " . implode(', ', array_keys($_SESSION)));
    
    // Try to recover from database if we have order_id
    if (!empty($orderId)) {
        error_log("Attempting to recover order from database using order_id: {$orderId}");
        // This is a fallback - normally session should exist
    }
    
    $_SESSION['payment_error'] = 'Your session has expired. Please start checkout again.';
    header('Location: checkout.php');
    exit;
}

$order = $_SESSION['pending_order'];
error_log("Pending order found in session");
error_log("Order details - Total: " . $order['total'] . ", Items: " . count($order['items']));

// Set payment processing flag to prevent duplicate submissions
if (isset($_SESSION['payment_processing'])) {
    error_log("WARNING: Payment already being processed");
    $_SESSION['payment_error'] = 'Payment is already being processed. Please wait.';
    header('Location: upi_payment.php');
    exit;
}

$_SESSION['payment_processing'] = true;
error_log("Payment processing flag set");

try {
    error_log("Starting database transaction");
    $db = get_db_connection();
    $db->beginTransaction();
    
    // Check for duplicate transaction ID
    $checkStmt = $db->prepare("SELECT id FROM orders WHERE transaction_id = ? AND user_id = ?");
    $checkStmt->execute([$transactionId, $userId]);
    $existingOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingOrder) {
        error_log("WARNING: Duplicate transaction ID detected - Order ID: " . $existingOrder['id']);
        $db->rollBack();
        unset($_SESSION['payment_processing']);
        
        $_SESSION['order_success'] = [
            'order_id' => $existingOrder['id'],
            'transaction_id' => $transactionId,
            'amount' => $order['total'],
            'payment_method' => 'UPI'
        ];
        
        // Clear session data
        unset($_SESSION['cart']);
        unset($_SESSION['pending_order']);
        
        error_log("Redirecting to existing order success page");
        header('Location: order_success.php');
        exit;
    }
    
    error_log("No duplicate found, proceeding with order creation");
    
    // Insert order into database
    $stmt = $db->prepare("
        INSERT INTO orders (
            user_id, 
            total_amount, 
            payment_method, 
            payment_status,
            transaction_id,
            shipping_address,
            order_status,
            created_at
        ) VALUES (?, ?, 'upi', 'pending', ?, ?, 'processing', NOW())
    ");
    
    $executeResult = $stmt->execute([
        $userId,
        $order['total'],
        $transactionId,
        json_encode($order['address'] ?? [])
    ]);
    
    if (!$executeResult) {
        throw new Exception("Failed to insert order into database");
    }
    
    $orderDbId = $db->lastInsertId();
    
    if (!$orderDbId) {
        throw new Exception("Failed to get order ID after insertion");
    }
    
    error_log("Order created successfully - Database ID: {$orderDbId}");
    
    // Insert order items
    if (isset($order['items']) && is_array($order['items']) && count($order['items']) > 0) {
        error_log("Inserting " . count($order['items']) . " order items");
        
        $itemStmt = $db->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        
        $itemCount = 0;
        foreach ($order['items'] as $item) {
            $itemResult = $itemStmt->execute([
                $orderDbId,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);
            
            if ($itemResult) {
                $itemCount++;
            } else {
                error_log("WARNING: Failed to insert item - Product ID: " . $item['product_id']);
            }
        }
        
        error_log("Successfully inserted {$itemCount} order items");
    } else {
        error_log("WARNING: No items to insert");
    }
    
    // Commit transaction before clearing session
    error_log("Committing database transaction");
    $db->commit();
    error_log("Transaction committed successfully");
    
    // Now safe to clear session data
    if (isset($_SESSION['cart'])) {
        error_log("Clearing cart from session");
        unset($_SESSION['cart']);
    }
    
    if (isset($_SESSION['pending_order'])) {
        error_log("Clearing pending order from session");
        unset($_SESSION['pending_order']);
    }
    
    // Clear processing flag
    unset($_SESSION['payment_processing']);
    
    // Auto-generate invoice for the order
    try {
        error_log("Attempting to generate invoice");
        require_once __DIR__ . '/includes/invoice_functions.php';
        $invoiceResult = create_invoice($orderDbId);
        error_log("Invoice generation result: " . ($invoiceResult ? 'Success' : 'Failed'));
    } catch (Exception $invoiceError) {
        error_log("WARNING: Invoice generation failed - " . $invoiceError->getMessage());
        // Don't fail the order if invoice generation fails
    }
    
    // Set success session data
    $_SESSION['order_success'] = [
        'order_id' => $orderDbId,
        'transaction_id' => $transactionId,
        'amount' => $order['total'],
        'payment_method' => 'UPI',
        'timestamp' => time()
    ];
    
    error_log("Order success data set in session");
    error_log("=== UPI Payment Processing Complete - Redirecting to success page ===");
    
    // Use header redirect instead of redirect_with_message to avoid any issues
    header('Location: order_success.php');
    exit;
    
} catch (Exception $e) {
    error_log("=== EXCEPTION CAUGHT ===");
    error_log("Exception Type: " . get_class($e));
    error_log("Exception Message: " . $e->getMessage());
    error_log("Exception File: " . $e->getFile());
    error_log("Exception Line: " . $e->getLine());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    if (isset($db) && $db->inTransaction()) {
        error_log("Rolling back transaction");
        $db->rollBack();
    }
    
    // Clear processing flag
    if (isset($_SESSION['payment_processing'])) {
        unset($_SESSION['payment_processing']);
    }
    
    // Set error message in session
    $_SESSION['payment_error'] = 'Failed to process payment: ' . $e->getMessage();
    
    error_log("Redirecting to UPI payment page with error");
    header('Location: upi_payment.php');
    exit;
}
?>
