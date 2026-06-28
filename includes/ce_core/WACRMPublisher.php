<?php
/**
 * Communication Engine — WACRM Event Publisher
 * Phase 2A: WACRM Integration
 *
 * Accepts internal GilafStore events, builds WACRM-compatible payloads,
 * dispatches via WACRMAdapter, and logs results via DeliveryLogger.
 *
 * Zero-impact constraints:
 *   - All dispatch is non-blocking (errors logged, never thrown to caller)
 *   - Only SELECT on production tables (orders, users)
 *   - Only INSERT/UPDATE on ce_dispatch_log (via DeliveryLogger)
 *   - No dependency on auth.php or any protected file
 */

if (!function_exists('get_db_connection')) {
    require_once dirname(__DIR__) . '/db_connect.php';
}
require_once __DIR__ . '/WACRMAdapter.php';
require_once __DIR__ . '/DeliveryLogger.php';

class CE_WACRMPublisher
{
    // Mapping internal event strings → WACRM event type strings
    private const EVENT_MAP = [
        'order.placed'            => 'order.placed',
        'order.confirmed'         => 'order.confirmed',
        'order.shipped'           => 'order.shipped',
        'order.delivered'         => 'order.delivered',
        'order.cancelled'         => 'order.cancelled',
        'payment.success'         => 'payment.success',
        'payment.failed'          => 'payment.failed',
        'customer.created'        => 'customer.created',
        'customer.updated'        => 'customer.updated',
        'cart.abandoned'          => 'cart.abandoned',
        'trigger.order_created'   => 'trigger.order_created',
        'trigger.payment_success' => 'trigger.payment_success',
        'customer.otp_request'    => 'customer.otp_request',
    ];

    // Mapping admin_actions.php $status values → WACRM event types
    private const STATUS_EVENT_MAP = [
        'shipped'          => 'order.shipped',
        'delivered'        => 'order.delivered',
        'cancelled'        => 'order.cancelled',
        'accepted'         => 'order.confirmed',
        'processing'       => 'order.confirmed',
        'confirmed'        => 'order.confirmed',
        'refunded'         => 'order.cancelled',
        'in_transit'       => 'order.shipped',
        'out_for_delivery' => 'order.shipped',
        'return_requested' => 'order.cancelled',
        'returned'         => 'order.cancelled',
        'delivery_failed'  => 'order.cancelled',
    ];

    private CE_WACRMAdapter   $adapter;
    private CE_DeliveryLogger $logger;

    public function __construct()
    {
        $this->adapter = new CE_WACRMAdapter();
        $this->logger  = new CE_DeliveryLogger();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Publish a WACRM event for an order.
     * Non-blocking: all errors are logged, never propagated to caller.
     *
     * @param string $eventType  e.g. 'order.placed', 'payment.success'
     * @param int    $orderId
     * @param array  $extra      Additional fields to merge into payload data
     */
    public function publish(string $eventType, int $orderId, array $extra = []): void
    {
        try {
            $order = $this->fetchOrder($orderId);
            if ($order === null) {
                error_log("CE_WACRMPublisher::publish — order #$orderId not found, skipping");
                return;
            }

            $wacrmEvent = self::EVENT_MAP[$eventType] ?? $eventType;
            $payload    = $this->buildOrderPayload($wacrmEvent, $order, $extra);
            $result     = $this->adapter->dispatch($payload);

            $this->logResult($wacrmEvent, $orderId, isset($order['user_id']) ? (int)$order['user_id'] : null, $result);

        } catch (\Throwable $e) {
            error_log('CE_WACRMPublisher::publish — unexpected: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * Publish an order status change event.
     * Maps admin_actions.php $status string to a WACRM event type.
     * Silently skips unmapped statuses.
     *
     * @param string $status   e.g. 'shipped', 'delivered', 'cancelled'
     * @param int    $orderId
     * @param array  $extra    e.g. ['tracking_id' => '...', 'courier' => '...']
     */
    public function publishOrderStatus(string $status, int $orderId, array $extra = []): void
    {
        $eventType = self::STATUS_EVENT_MAP[$status] ?? null;
        if ($eventType === null) {
            return;
        }
        $this->publish($eventType, $orderId, $extra);
    }

    /**
     * Publish a cart.abandoned event.
     * Non-blocking: all errors are logged, never propagated to caller.
     *
     * @param array $cart  Row from crm_abandoned_carts (id, user_id, phone, email,
     *                     cart_total, item_count, cart_data, abandoned_at, ...)
     */
    public function publishCartAbandoned(array $cart): void
    {
        try {
            $payload = [
                'event'     => 'cart.abandoned',
                'timestamp' => date('c'),
                'source'    => 'gilafstore',
                'data'      => [
                    'cart_id'        => (int)   ($cart['id']          ?? 0),
                    'user_id'        => (int)   ($cart['user_id']     ?? 0),
                    'cart_total'     => (float) ($cart['cart_total']  ?? 0),
                    'item_count'     => (int)   ($cart['item_count']  ?? 0),
                    'currency'       => 'INR',
                    'abandoned_at'   =>          ($cart['abandoned_at'] ?? ''),
                    'recovery_link'  =>          ($cart['recovery_link'] ?? ''),
                    'customer'       => [
                        'user_id' => (int) ($cart['user_id'] ?? 0),
                        'email'   =>       ($cart['email']   ?? ''),
                        'phone'   =>       ($cart['phone']   ?? ''),
                    ],
                ],
            ];

            $result = $this->adapter->dispatch($payload);
            $this->logResult('cart.abandoned', null,
                isset($cart['user_id']) ? (int)$cart['user_id'] : null, $result);

        } catch (\Throwable $e) {
            error_log('CE_WACRMPublisher::publishCartAbandoned — ' . $e->getMessage());
        }
    }

    /**
     * Publish an OTP request event so WACRM can fire a WhatsApp login_otp automation.
     *
     * @param string $phone           Normalised phone number (digits only, no country code)
     * @param string $otp             The generated OTP code
     * @param string $purpose         'login', 'signup', 'verify', 'order'
     * @param int    $expiryMinutes   OTP validity in minutes (default 5)
     * @param string $name            Customer display name (may be empty)
     */
    public function publishOTPEvent(string $phone, string $otp, string $purpose = 'login', int $expiryMinutes = 5, string $name = ''): void
    {
        try {
            $payload = [
                'event'     => 'customer.otp_request',
                'timestamp' => date('c'),
                'source'    => 'gilafstore',
                'data'      => [
                    'phone'          => $phone,
                    'otp'            => $otp,
                    'purpose'        => $purpose,
                    'expiry_minutes' => $expiryMinutes,
                    'name'           => $name,
                ],
            ];

            $result = $this->adapter->dispatch($payload);
            $this->logResult('customer.otp_request', null, null, $result);

        } catch (\Throwable $e) {
            error_log('CE_WACRMPublisher::publishOTPEvent — ' . $e->getMessage());
        }
    }

    /**
     * Publish a customer lifecycle event.
     *
     * @param string $eventType  'customer.created' or 'customer.updated'
     * @param int    $userId
     */
    public function publishCustomerEvent(string $eventType, int $userId): void
    {
        try {
            $user = $this->fetchUser($userId);
            if ($user === null) {
                error_log("CE_WACRMPublisher::publishCustomerEvent — user #$userId not found, skipping");
                return;
            }

            $wacrmEvent = self::EVENT_MAP[$eventType] ?? $eventType;
            $payload    = $this->buildCustomerPayload($wacrmEvent, $user);
            $result     = $this->adapter->dispatch($payload);

            $this->logResult($wacrmEvent, null, $userId, $result);

        } catch (\Throwable $e) {
            error_log('CE_WACRMPublisher::publishCustomerEvent — unexpected: ' . $e->getMessage());
        }
    }

    // ── Payload Builders ──────────────────────────────────────────────────────

    private function buildOrderPayload(string $eventType, array $order, array $extra): array
    {
        $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        if ($customerName === '') {
            $customerName = $order['customer_name'] ?? $order['name'] ?? '';
        }

        return [
            'event'     => $eventType,
            'timestamp' => date('c'),
            'source'    => 'gilafstore',
            'data'      => array_merge([
                'order_id'       => (int)   ($order['id']             ?? 0),
                'order_number'   => (string) ($order['id']             ?? ''),
                'status'         =>          ($order['status']         ?? $order['payment_status'] ?? ''),
                'total_amount'   => (float)  ($order['total_amount']   ?? 0),
                'currency'       => 'INR',
                'payment_method' =>          ($order['payment_method'] ?? ''),
                'created_at'     =>          ($order['created_at']     ?? ''),
                'customer'       => [
                    'user_id' => (int) ($order['user_id'] ?? 0),
                    'name'    => $customerName,
                    'email'   => $order['email']  ?? $order['customer_email'] ?? '',
                    'phone'   => $order['phone']  ?? $order['customer_phone'] ?? $order['mobile'] ?? '',
                ],
            ], $extra),
        ];
    }

    private function buildCustomerPayload(string $eventType, array $user): array
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($name === '') {
            $name = $user['name'] ?? '';
        }

        return [
            'event'     => $eventType,
            'timestamp' => date('c'),
            'source'    => 'gilafstore',
            'data'      => [
                'user_id'    => (int)   ($user['id']         ?? 0),
                'name'       =>          $name,
                'email'      =>          ($user['email']      ?? ''),
                'phone'      =>          ($user['phone']      ?? $user['mobile'] ?? ''),
                'created_at' =>          ($user['created_at'] ?? ''),
            ],
        ];
    }

    // ── Data Fetchers (SELECT only) ───────────────────────────────────────────

    private function fetchOrder(int $orderId): ?array
    {
        try {
            $db   = get_db_connection();
            $stmt = $db->prepare(
                'SELECT o.*, u.email, u.phone, u.name
                   FROM orders o
                   LEFT JOIN users u ON u.id = o.user_id
                  WHERE o.id = ?
                  LIMIT 1'
            );
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('CE_WACRMPublisher::fetchOrder — ' . $e->getMessage());
            return null;
        }
    }

    private function fetchUser(int $userId): ?array
    {
        try {
            $db   = get_db_connection();
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('CE_WACRMPublisher::fetchUser — ' . $e->getMessage());
            return null;
        }
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    private function logResult(string $eventType, ?int $orderId, ?int $userId, array $result): void
    {
        $routingDecision = [
            'routable'        => true,
            'channel_slug'    => 'wacrm',
            'rule_id'         => null,
            'rule_name'       => 'WACRM Direct Publish',
            'reason'          => "HTTP {$result['http_code']}",
            'user_id'         => $userId,
            'order_id'        => $orderId,
            'recipient_phone' => null,
            'recipient_email' => null,
            'decided_at'      => date('Y-m-d H:i:s'),
        ];

        $event = ['event_type' => $eventType];

        try {
            $logId = $this->logger->logDispatch($routingDecision, $event);

            if ($logId > 0) {
                $status        = $result['success'] ? 'sent' : 'failed';
                $failureReason = $result['success'] ? null : ($result['error'] ?? "HTTP {$result['http_code']}");
                $response      = !empty($result['body'])
                    ? ['body' => substr($result['body'], 0, 1000), 'http_code' => $result['http_code']]
                    : null;

                $this->logger->updateStatus($logId, $status, $response, $failureReason);
            }
        } catch (\Throwable $e) {
            error_log('CE_WACRMPublisher::logResult — logger failed: ' . $e->getMessage());
        }
    }
}
