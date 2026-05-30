<?php
/**
 * Razorpay Payment Verification
 * Verifies payment signature and places the order if valid
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start(); // Buffer any stray output to keep JSON clean

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/promo_functions.php';
require_once __DIR__ . '/includes/security.php';
if (file_exists(__DIR__ . '/includes/rewards_engine.php')) {
    try { require_once __DIR__ . '/includes/rewards_engine.php'; } catch (Exception $e) {}
}
if (file_exists(__DIR__ . '/includes/batch_functions.php')) {
    require_once __DIR__ . '/includes/batch_functions.php';
}

// If browser navigates here directly (GET), redirect to thank-you or home
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['order_confirmation'])) {
        header('Location: ' . base_url('thank-you.php'));
    } else {
        header('Location: ' . base_url('index.php'));
    }
    exit;
}

ob_end_clean(); // Discard any buffered output from includes
header('Content-Type: application/json');

// Register shutdown to catch fatal errors and still return JSON with HTTP 200
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any partial output
        while (ob_get_level()) ob_end_clean();
        http_response_code(200); // Force 200 so JS can parse the JSON
        header('Content-Type: application/json');
        error_log("FATAL in razorpay_verify.php: " . $err['message'] . " in " . $err['file'] . ":" . $err['line']);
        echo json_encode(['success' => false, 'error' => 'Server processing error: ' . $err['message']]);
    }
});

// D5: Rate limit payment verification (10 per minute, block 10 min)
try {
    if (function_exists('rate_limit_enforce') && !rate_limit_enforce('payment_verify', 10, 60, 600)) {
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment.']);
        exit;
    }
} catch (\Throwable $rle) {
    error_log('RZP_VERIFY: rate_limit_enforce error: ' . $rle->getMessage());
    // Continue anyway — don't block payment over rate limiter error
}
error_log('RZP_VERIFY: STEP1 - Rate limit passed');

function db_column_exists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (\Throwable $e) {
        return false;
    }
}

// Guest checkout: auto-create account from pending_order guest info before auth check
if (!isset($_SESSION['user']) && !empty($_SESSION['pending_order']['guest_info'])) {
    try {
        require_once __DIR__ . '/includes/guest_checkout.php';
        $guestInfo = $_SESSION['pending_order']['guest_info'];
        $guestAddr = $_SESSION['pending_order']['guest_address'] ?? [];
        $newUserId = guest_auto_create_account($guestInfo, $guestAddr);
        if ($newUserId) {
            guest_save_address($newUserId, $guestAddr);
            $_SESSION['user'] = [
                'id' => $newUserId,
                'name' => $guestInfo['name'] ?? 'Guest',
                'email' => $guestInfo['email'] ?? '',
                'phone' => $guestInfo['phone'] ?? '',
                'is_admin' => false,
            ];
        }
    } catch (\Throwable $guestErr) {
        error_log("RZP_VERIFY: Guest account creation failed: " . $guestErr->getMessage());
    }
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$razorpayPaymentId = $_POST['razorpay_payment_id'] ?? '';
$razorpayOrderId = $_POST['razorpay_order_id'] ?? '';
$razorpaySignature = $_POST['razorpay_signature'] ?? '';

// Used for logging/troubleshooting
$errorRef = 'RZP-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

if (empty($razorpayPaymentId) || empty($razorpayOrderId) || empty($razorpaySignature)) {
    echo json_encode(['success' => false, 'error' => 'Missing payment details']);
    exit;
}

$keySecret = get_setting('razorpay_key_secret', '');
if (empty($keySecret)) {
    echo json_encode(['success' => false, 'error' => 'Payment gateway not configured']);
    exit;
}

// Verify signature
$expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $keySecret);

if (!hash_equals($expectedSignature, $razorpaySignature)) {
    error_log("Razorpay signature mismatch. Expected: $expectedSignature, Got: $razorpaySignature");
    
    // Update payment record as failed
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("UPDATE payments SET status = 'failed', razorpay_payment_id = ?, error_description = 'Signature verification failed' WHERE razorpay_order_id = ?");
        $stmt->execute([$razorpayPaymentId, $razorpayOrderId]);
    } catch (Exception $e) {}
    
    echo json_encode(['success' => false, 'error' => 'Payment verification failed. Please contact support.']);
    exit;
}

// Signature verified — payment is authentic
error_log("RZP_VERIFY: STEP2 - Signature verified for $razorpayOrderId");
$pendingOrder = $_SESSION['pending_order'] ?? null;
if (!$pendingOrder) {
    error_log("RZP_VERIFY: FAIL - No pending_order in session");
    echo json_encode(['success' => false, 'error' => 'Session expired. Please try again.']);
    exit;
}
error_log("RZP_VERIFY: STEP3 - Pending order found, items=" . count($pendingOrder['items'] ?? []));

$db = get_db_connection();

// Update payment record as captured (best-effort, before order placement)
try {
    $stmt = $db->prepare("UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'captured', payment_method = 'razorpay' WHERE razorpay_order_id = ?");
    $stmt->execute([$razorpayPaymentId, $razorpaySignature, $razorpayOrderId]);
} catch (\Throwable $payErr) {
    error_log("RZP_VERIFY: payments update error (non-fatal): " . $payErr->getMessage());
}

$userId = (int)$_SESSION['user']['id'];
$items = $pendingOrder['items'];
$isBuyNow = $pendingOrder['is_buy_now'] ?? false;
$orderTotal = $pendingOrder['total'];
$address = $pendingOrder['address'] ?? [];
$promoCode = $pendingOrder['promo_code'] ?? '';
$promoDiscount = $pendingOrder['promo_discount'] ?? 0;

try {
    error_log("RZP_VERIFY: STEP4 - Placing order via db_query, userId=$userId, total=$orderTotal");

    // Use the EXACT same INSERT that place_order() uses — proven to work for COD
    $db->beginTransaction();

    db_query(
        'INSERT INTO orders (user_id, total_amount, promo_code, promo_discount, payment_method, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$userId, $orderTotal, $promoCode ?: null, $promoDiscount, 'razorpay', 'processing']
    );
    $orderId = (int)$db->lastInsertId();
    error_log("RZP_VERIFY: STEP5 - Order #$orderId inserted");

    // Insert order items — same as place_order()
    foreach ($items as $item) {
        db_query(
            'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)',
            [$orderId, $item['product_id'], $item['quantity'], $item['price']]
        );

        // Deduct stock (best-effort)
        try {
            if (function_exists('reduce_batch_stock')) {
                reduce_batch_stock($item['product_id'], $item['quantity'], $item['weight_id'] ?? null);
            }
            if (function_exists('sync_product_batch_data')) {
                sync_product_batch_data($item['product_id']);
            }
        } catch (\Throwable $stockErr) {
            error_log("WARNING: Stock sync failed for order #{$orderId}, product #{$item['product_id']} - " . $stockErr->getMessage());
        }
    }

    $db->commit();
    error_log("RZP_VERIFY: STEP6 - Transaction committed for order #$orderId");

    // ── Post-commit: update order with Razorpay-specific fields (best-effort) ──
    try {
        $addressStr = '';
        if (!empty($address)) {
            $parts = array_filter([
                $address['full_name'] ?? '', $address['address_line1'] ?? '',
                $address['address_line2'] ?? '', $address['city'] ?? '',
                $address['state'] ?? '', $address['pincode'] ?? '',
                $address['phone'] ?? ''
            ]);
            $addressStr = implode(', ', $parts);
        }
        // Update with Razorpay details — each field wrapped separately so one missing column doesn't block others
        try { db_query('UPDATE orders SET razorpay_payment_id = ? WHERE id = ?', [$razorpayPaymentId, $orderId]); } catch (\Throwable $e) {}
        try { db_query('UPDATE orders SET shipping_address = ? WHERE id = ?', [$addressStr, $orderId]); } catch (\Throwable $e) {}
        try { db_query('UPDATE orders SET payment_status = ? WHERE id = ?', ['completed', $orderId]); } catch (\Throwable $e) {}
    } catch (\Throwable $e) {
        error_log("RZP_VERIFY: Post-commit update error (non-fatal): " . $e->getMessage());
    }

    // Update payment record with actual order_id
    try {
        db_query('UPDATE payments SET order_id = ? WHERE razorpay_order_id = ?', [$orderId, $razorpayOrderId]);
    } catch (\Throwable $e) {
        error_log("RZP_VERIFY: payments order_id update error: " . $e->getMessage());
    }

    // Auto-calculate GST
    try {
        require_once __DIR__ . '/includes/gst_calculator.php';
        GSTCalculator::autoCalculateOrderGST($orderId, $address ?: null);
    } catch (\Throwable $gstErr) {
        error_log("WARNING: GST auto-calc failed for order #$orderId - " . $gstErr->getMessage());
    }

    // Fire CRM events (best-effort, never blocks order)
    try {
        require_once __DIR__ . '/includes/crm_hooks.php';
        crm_on_order_placed($orderId, $userId, $orderTotal, 'razorpay', $items);
        crm_on_payment_success($orderId, $userId, $orderTotal, 'razorpay');
    } catch (\Throwable $crmErr) {
        error_log("CRM hook failed for razorpay order #$orderId: " . $crmErr->getMessage());
    }

    // Clear cart / buy now session
    if ($isBuyNow) {
        unset($_SESSION['buy_now']);
    } else {
        $_SESSION['cart'] = [];
    }
    unset($_SESSION['pending_order']);

    // Track promo usage
    if (!empty($promoCode) && $promoDiscount > 0) {
        try {
            require_once __DIR__ . '/includes/promo_functions.php';
            db_query("UPDATE promo_codes SET used_count = used_count + 1 WHERE code = ?", [$promoCode]);
            $promoRow = db_fetch("SELECT id FROM promo_codes WHERE code = ?", [$promoCode]);
            if ($promoRow && $userId) {
                db_query("INSERT INTO promo_code_usage (promo_code_id, user_id, order_id, discount_amount, created_at) VALUES (?, ?, ?, ?, NOW())", [$promoRow['id'], $userId, $orderId, $promoDiscount]);
            }
        } catch (\Throwable $e) {
            error_log("WARNING: Promo tracking failed for order #$orderId - " . $e->getMessage());
        }
    }
    if (function_exists('remove_promo_code')) { remove_promo_code(); }

    // Rewards: deduct redeemed + earn cashback (Razorpay)
    if (function_exists('rw_debit') && $userId > 0) {
        try {
            $rwRedeemRzp = (float)($pendingOrder['rw_redeem'] ?? 0);
            if ($rwRedeemRzp > 0) {
                rw_debit($userId, $rwRedeemRzp, 'Redeemed on Order #' . $orderId, $orderId);
                unset($_SESSION['rw_redeem_amount']);
            }
            rw_handle_first_order($userId, $orderId);
            rw_earn_on_purchase($userId, $orderTotal, $orderId);
            rw_complete_referral($userId, $orderId);
        } catch (\Throwable $rwErr) {
            error_log('Rewards failed for razorpay order #' . $orderId . ': ' . $rwErr->getMessage());
        }
    }

    // Auto-generate invoice
    try {
        require_once __DIR__ . '/includes/invoice_functions.php';
        create_invoice($orderId);
    } catch (\Throwable $invErr) {
        error_log("WARNING: Invoice generation failed for order #$orderId - " . $invErr->getMessage());
    }

    // Send order confirmation email
    try {
        require_once __DIR__ . '/includes/order_emails.php';
        send_order_confirmation_email($orderId, 'Razorpay');
    } catch (\Throwable $emailErr) {
        error_log("WARNING: Email failed for order #$orderId - " . $emailErr->getMessage());
    }

    $_SESSION['order_confirmation'] = [
        'order_id' => $orderId,
        'total' => $orderTotal,
        'payment_method' => 'razorpay',
        'razorpay_payment_id' => $razorpayPaymentId
    ];

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Payment successful! Order placed.',
        'redirect' => base_url('thank-you.php')
    ]);

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $errMsg = $e->getMessage();
    $errFile = $e->getFile();
    $errLine = $e->getLine();
    $errClass = get_class($e);
    error_log("RZP_VERIFY: EXCEPTION: $errClass - $errMsg in $errFile:$errLine");

    // Persist failure reason in payments table
    try {
        $safeMsg = mb_substr($errMsg, 0, 1000);
        if (!empty($razorpayOrderId)) {
            $stmtFail = $db->prepare("UPDATE payments SET status = 'failed', razorpay_payment_id = COALESCE(NULLIF(?, ''), razorpay_payment_id), error_description = ? WHERE razorpay_order_id = ?");
            $stmtFail->execute([$razorpayPaymentId, "{$errorRef}: {$safeMsg}", $razorpayOrderId]);
        }
    } catch (\Throwable $inner) {}

    // INCLUDE actual error in response for diagnosis
    echo json_encode([
        'success' => false,
        'error' => "Order failed: $errClass - $errMsg (in " . basename($errFile) . ":$errLine)",
        'payment_id' => $razorpayPaymentId
    ]);
}
