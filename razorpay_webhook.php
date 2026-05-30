<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/security.php';

header('Content-Type: application/json');

// D5: Rate limit payment webhook (20 per minute, block 10 min)
if (!rate_limit_enforce('payment_webhook', 20, 60, 600)) { exit; }

$webhookSecret = (string)get_setting('razorpay_webhook_secret', '');
$signatureHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');

if ($payload === false || $payload === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty payload']);
    exit;
}

if ($webhookSecret === '') {
    error_log('Razorpay webhook error: webhook secret not configured');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Webhook secret not configured']);
    exit;
}

if ($signatureHeader === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing signature header']);
    exit;
}

$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
if (!hash_equals($expectedSignature, $signatureHeader)) {
    error_log('Razorpay webhook signature mismatch');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid signature']);
    exit;
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$event = (string)($data['event'] ?? '');

$paymentEntity = $data['payload']['payment']['entity'] ?? null;
$razorpayOrderId = is_array($paymentEntity) ? ($paymentEntity['order_id'] ?? '') : '';
$razorpayPaymentId = is_array($paymentEntity) ? ($paymentEntity['id'] ?? '') : '';

$refundEntity = $data['payload']['refund']['entity'] ?? null;
$refundPaymentId = is_array($refundEntity) ? ($refundEntity['payment_id'] ?? '') : '';

if ($razorpayOrderId === '') {
    // For unsupported event structures, acknowledge to prevent retries.
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored (missing order_id)']);
    exit;
}

$status = null;
if ($event === 'payment.captured') {
    $status = 'captured';
} elseif ($event === 'payment.failed') {
    $status = 'failed';
} elseif ($event === 'payment.authorized') {
    $status = 'authorized';
} elseif ($event === 'refund.processed') {
    $status = 'refunded';
}

if ($status === null) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored event']);
    exit;
}

try {
    $db = get_db_connection();

    if ($status === 'refunded') {
        // Refund event may not include order_id; update by payment_id primarily
        if ($refundPaymentId !== '') {
            $stmt = $db->prepare("UPDATE payments SET status = 'refunded', updated_at = NOW() WHERE razorpay_payment_id = ?");
            $stmt->execute([$refundPaymentId]);
        } elseif ($razorpayPaymentId !== '') {
            $stmt = $db->prepare("UPDATE payments SET status = 'refunded', updated_at = NOW() WHERE razorpay_payment_id = ?");
            $stmt->execute([$razorpayPaymentId]);
        } elseif ($razorpayOrderId !== '') {
            $stmt = $db->prepare("UPDATE payments SET status = 'refunded', updated_at = NOW() WHERE razorpay_order_id = ?");
            $stmt->execute([$razorpayOrderId]);
        }
    } else {
        $stmt = $db->prepare("UPDATE payments SET status = ?, razorpay_payment_id = COALESCE(NULLIF(?, ''), razorpay_payment_id), payment_method = 'razorpay', updated_at = NOW() WHERE razorpay_order_id = ?");
        $stmt->execute([$status, $razorpayPaymentId, $razorpayOrderId]);
    }

    // If an internal order exists (order created already), update its status too.
    $stmt2 = $db->prepare("SELECT order_id FROM payments WHERE razorpay_order_id = ? LIMIT 1");
    $stmt2->execute([$razorpayOrderId]);
    $internalOrderId = (int)($stmt2->fetchColumn() ?: 0);

    if ($internalOrderId > 0) {
        $hasPaymentStatus = false;
        try {
            $check = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
            $check->execute(['payment_status']);
            $hasPaymentStatus = $check->rowCount() > 0;
        } catch (Exception $e) {}

        $orderStatusColumn = null;
        try {
            $check = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
            $check->execute(['order_status']);
            if ($check->rowCount() > 0) {
                $orderStatusColumn = 'order_status';
            } else {
                $check = $db->prepare("SHOW COLUMNS FROM orders LIKE ?");
                $check->execute(['status']);
                if ($check->rowCount() > 0) {
                    $orderStatusColumn = 'status';
                }
            }
        } catch (Exception $e) {}

        if ($status === 'captured') {
            if ($hasPaymentStatus) {
                $db->prepare("UPDATE orders SET payment_status = 'completed' WHERE id = ?")->execute([$internalOrderId]);
            }
            if ($orderStatusColumn) {
                $db->prepare("UPDATE orders SET {$orderStatusColumn} = 'processing' WHERE id = ?")->execute([$internalOrderId]);
            }
        } elseif ($status === 'failed') {
            if ($hasPaymentStatus) {
                $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$internalOrderId]);
            }
            if ($orderStatusColumn) {
                $db->prepare("UPDATE orders SET {$orderStatusColumn} = 'pending' WHERE id = ?")->execute([$internalOrderId]);
            }
        }
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Razorpay webhook DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
