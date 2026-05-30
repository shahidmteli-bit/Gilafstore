<?php
/**
 * Payment Adjustment Helper
 * Handles payment adjustment logic with FIFO (First In First Out) for oldest dues
 * Prevents duplicate payment vouchers and ensures proper order-wise adjustment
 */

/**
 * Adjust payment against party's outstanding orders
 * Uses FIFO logic: oldest unpaid orders are settled first
 * 
 * @param int $partyId Party ID
 * @param float $paymentAmount Payment amount received
 * @param string $paymentMethod Payment method (cash/cheque/online_transfer)
 * @param string $referenceNumber Payment reference number
 * @param string $notes Payment notes
 * @param int $recordedBy User ID who recorded the payment
 * @param int|null $collectionId Optional collection ID if from sales collection
 * @return array Adjustment details with breakdown
 */
function adjust_payment_to_orders(
    int $partyId,
    float $paymentAmount,
    string $paymentMethod = 'cash',
    string $referenceNumber = '',
    string $notes = '',
    int $recordedBy = 0,
    ?int $collectionId = null
): array {
    if ($paymentAmount <= 0) {
        return [
            'success' => false,
            'error' => 'Payment amount must be greater than zero',
            'adjustments' => []
        ];
    }

    // Fetch all unpaid orders for this party (oldest first - FIFO)
    $unpaidOrders = db_fetch_all(
        'SELECT id, order_number, total_amount, payment_amount, created_at 
         FROM sales_orders 
         WHERE party_id = ? 
         AND status IN ("approved", "dispatched", "delivered") 
         AND payment_status != "received"
         AND order_type = "new_order"
         ORDER BY created_at ASC',
        [$partyId]
    );

    $remainingPayment = $paymentAmount;
    $adjustments = [];
    $totalAdjusted = 0;

    // Adjust payment against each unpaid order (FIFO)
    foreach ($unpaidOrders as $order) {
        if ($remainingPayment <= 0) break;

        $orderDue = (float)$order['total_amount'] - (float)($order['payment_amount'] ?? 0);
        if ($orderDue <= 0) continue;

        $adjustmentAmount = min($remainingPayment, $orderDue);
        $newPaymentAmount = (float)($order['payment_amount'] ?? 0) + $adjustmentAmount;
        $isFullyPaid = ($newPaymentAmount >= (float)$order['total_amount']);

        // Update order payment
        db_query(
            'UPDATE sales_orders 
             SET payment_amount = ?, 
                 payment_status = ? 
             WHERE id = ?',
            [
                $newPaymentAmount,
                $isFullyPaid ? 'received' : 'partial',
                $order['id']
            ]
        );

        // Check if payment history already exists for this order + collection combination
        $existingPayment = null;
        if ($collectionId) {
            $existingPayment = db_fetch(
                'SELECT id FROM sales_payment_history 
                 WHERE party_id = ? 
                 AND order_id = ? 
                 AND reference_number LIKE ?
                 LIMIT 1',
                [$partyId, $order['id'], '%COL-%']
            );
        }

        // Only create payment history if it doesn't exist
        if (!$existingPayment) {
            db_query(
                'INSERT INTO sales_payment_history 
                 (party_id, order_id, amount, payment_type, payment_method, reference_number, notes, recorded_by) 
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $partyId,
                    $order['id'],
                    $adjustmentAmount,
                    'payment',
                    $paymentMethod,
                    $referenceNumber,
                    $notes . ' | Adjusted against Order #' . $order['order_number'],
                    $recordedBy
                ]
            );
        }

        $adjustments[] = [
            'order_id' => $order['id'],
            'order_number' => $order['order_number'],
            'order_total' => (float)$order['total_amount'],
            'previous_payment' => (float)($order['payment_amount'] ?? 0),
            'adjustment_amount' => $adjustmentAmount,
            'new_payment_amount' => $newPaymentAmount,
            'fully_paid' => $isFullyPaid,
            'order_date' => $order['created_at']
        ];

        $remainingPayment -= $adjustmentAmount;
        $totalAdjusted += $adjustmentAmount;
    }

    // If there's excess payment (no pending orders to adjust against)
    $excessPayment = $remainingPayment;

    return [
        'success' => true,
        'total_payment' => $paymentAmount,
        'total_adjusted' => $totalAdjusted,
        'excess_payment' => $excessPayment,
        'adjustments' => $adjustments,
        'orders_settled' => count(array_filter($adjustments, fn($a) => $a['fully_paid']))
    ];
}

/**
 * Check if payment would create excess without new orders
 * 
 * @param int $partyId Party ID
 * @param float $paymentAmount Payment amount
 * @return array Status with warning if excess
 */
function check_payment_excess(int $partyId, float $paymentAmount): array {
    $party = db_fetch('SELECT outstanding_amount FROM sales_parties WHERE id = ?', [$partyId]);
    
    if (!$party) {
        return ['has_excess' => false, 'outstanding' => 0, 'excess' => 0];
    }

    $outstanding = (float)($party['outstanding_amount'] ?? 0);
    $excess = max(0, $paymentAmount - $outstanding);

    return [
        'has_excess' => $excess > 0,
        'outstanding' => $outstanding,
        'payment' => $paymentAmount,
        'excess' => $excess,
        'warning' => $excess > 0 ? 
            "Payment received (₹" . number_format($paymentAmount, 2) . ") is more than current outstanding (₹" . number_format($outstanding, 2) . "). Excess amount: ₹" . number_format($excess, 2) . ". Please create a new order or adjust the payment amount." : 
            null
    ];
}

/**
 * Get payment adjustment breakdown for a party
 * Shows which payments were adjusted against which orders
 * 
 * @param int $partyId Party ID
 * @param int $limit Number of recent adjustments to fetch
 * @return array Payment adjustment history
 */
function get_payment_adjustment_history(int $partyId, int $limit = 20): array {
    $history = db_fetch_all(
        'SELECT ph.*, so.order_number, so.total_amount as order_total
         FROM sales_payment_history ph
         LEFT JOIN sales_orders so ON ph.order_id = so.id
         WHERE ph.party_id = ?
         AND ph.payment_type = "payment"
         ORDER BY ph.created_at DESC
         LIMIT ?',
        [$partyId, $limit]
    );

    return $history;
}

/**
 * Prevent duplicate payment voucher creation
 * Checks if a payment voucher already exists for the same reference
 * 
 * @param int $partyId Party ID
 * @param string $referenceNumber Payment reference
 * @return bool True if duplicate exists
 */
function is_duplicate_payment_voucher(int $partyId, string $referenceNumber): bool {
    if (empty($referenceNumber)) return false;

    $existing = db_fetch(
        'SELECT id FROM sales_payment_history 
         WHERE party_id = ? 
         AND reference_number = ?
         LIMIT 1',
        [$partyId, $referenceNumber]
    );

    return !empty($existing);
}

/**
 * Get party's oldest unpaid order details
 * 
 * @param int $partyId Party ID
 * @return array|null Oldest unpaid order or null
 */
function get_oldest_unpaid_order(int $partyId): ?array {
    $order = db_fetch(
        'SELECT id, order_number, total_amount, payment_amount, 
                (total_amount - COALESCE(payment_amount, 0)) as due_amount,
                created_at
         FROM sales_orders 
         WHERE party_id = ? 
         AND status IN ("approved", "dispatched", "delivered") 
         AND payment_status != "received"
         AND order_type = "new_order"
         ORDER BY created_at ASC
         LIMIT 1',
        [$partyId]
    );

    return $order ?: null;
}

/**
 * Calculate total pending dues for a party
 * 
 * @param int $partyId Party ID
 * @return array Dues breakdown
 */
function get_party_dues_breakdown(int $partyId): array {
    // Total unpaid order amount
    $unpaidOrders = db_fetch(
        'SELECT COALESCE(SUM(total_amount - COALESCE(payment_amount, 0)), 0) as total
         FROM sales_orders 
         WHERE party_id = ? 
         AND status IN ("approved", "dispatched", "delivered") 
         AND payment_status != "received"
         AND order_type = "new_order"',
        [$partyId]
    );

    // Count of unpaid orders
    $orderCount = db_fetch(
        'SELECT COUNT(*) as cnt
         FROM sales_orders 
         WHERE party_id = ? 
         AND status IN ("approved", "dispatched", "delivered") 
         AND payment_status != "received"
         AND order_type = "new_order"',
        [$partyId]
    );

    // Total payments received
    $totalPayments = db_fetch(
        'SELECT COALESCE(SUM(amount), 0) as total
         FROM sales_payment_history
         WHERE party_id = ?
         AND payment_type = "payment"',
        [$partyId]
    );

    return [
        'total_dues' => (float)($unpaidOrders['total'] ?? 0),
        'unpaid_order_count' => (int)($orderCount['cnt'] ?? 0),
        'total_payments_received' => (float)($totalPayments['total'] ?? 0)
    ];
}
