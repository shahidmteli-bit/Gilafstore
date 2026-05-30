<?php
/**
 * CRM Integration Hooks
 * 
 * Auto-fires CRM events from GilafStore order/cart/user actions.
 * Safe to include everywhere — silently does nothing if CRM tables
 * don't exist or integration is disabled.
 * 
 * Usage: require_once 'includes/crm_hooks.php';
 *        crm_on_order_placed($orderId, $userId, $total, $paymentMethod, $items);
 */

/**
 * Fire CRM event after an order is placed.
 */
function crm_on_order_placed(int $orderId, int $userId, float $total, string $paymentMethod, array $items = []): void
{
    _crm_fire_safe('order.placed', [
        'order_id' => $orderId,
        'user_id' => $userId,
        'total' => $total,
        'payment_method' => $paymentMethod,
        'items' => array_map(function($i) {
            return [
                'product_id' => $i['product_id'] ?? 0,
                'name' => $i['name'] ?? '',
                'quantity' => $i['quantity'] ?? 1,
                'price' => $i['price'] ?? 0,
            ];
        }, $items),
        'customer_name' => _crm_get_user_field($userId, 'name'),
        'phone' => _crm_get_user_field($userId, 'phone'),
        'email' => _crm_get_user_field($userId, 'email'),
    ]);

    // Send direct WhatsApp notification for order confirmation
    _crm_send_notification($orderId, 'order_placed', [
        'payment_method' => $paymentMethod,
        'item_count' => count($items),
    ]);

    // Also mark any abandoned cart as recovered
    _crm_mark_cart_recovered($userId, $orderId);
}

/**
 * Fire CRM event after successful payment.
 */
function crm_on_payment_success(int $orderId, int $userId, float $amount, string $method = 'online'): void
{
    _crm_fire_safe('payment.success', [
        'order_id' => $orderId,
        'user_id' => $userId,
        'amount' => $amount,
        'method' => $method,
        'phone' => _crm_get_user_field($userId, 'phone'),
    ]);
}

/**
 * Fire CRM event after payment failure.
 */
function crm_on_payment_failed(int $orderId, int $userId, string $reason = ''): void
{
    _crm_fire_safe('payment.failed', [
        'order_id' => $orderId,
        'user_id' => $userId,
        'reason' => $reason,
        'phone' => _crm_get_user_field($userId, 'phone'),
    ]);
}

/**
 * Fire CRM event when order status changes.
 */
function crm_on_order_status_change(int $orderId, string $newStatus, array $extra = []): void
{
    $eventMap = [
        'confirmed' => 'order.confirmed',
        'packed' => 'order.packed',
        'shipped' => 'order.shipped',
        'out_for_delivery' => 'order.out_for_delivery',
        'delivered' => 'order.delivered',
        'cancelled' => 'order.cancelled',
        'returned' => 'order.returned',
        'refunded' => 'order.refunded',
    ];

    $event = $eventMap[$newStatus] ?? "order.status_changed";

    $order = db_fetch("SELECT user_id, total_amount FROM orders WHERE id = ?", [$orderId]);
    $userId = $order['user_id'] ?? 0;

    _crm_fire_safe($event, array_merge([
        'order_id' => $orderId,
        'status' => $newStatus,
        'user_id' => $userId,
        'phone' => _crm_get_user_field($userId, 'phone'),
    ], $extra));

    // Send direct WhatsApp notification for status change
    $templateMap = [
        'shipped' => 'order_shipped',
        'out_for_delivery' => 'out_for_delivery',
        'delivered' => 'order_delivered',
        'cancelled' => 'order_cancelled',
    ];
    if (isset($templateMap[$newStatus])) {
        _crm_send_notification($orderId, $templateMap[$newStatus], $extra);
    }
}

/**
 * Track cart activity for abandonment detection.
 */
function crm_track_cart(int $userId, array $cartItems, float $total): void
{
    try {
        require_once __DIR__ . '/crm_engine.php';
        $crm = CRMEngine::getInstance();
        $crm->trackCartAbandonment($userId, session_id(), $cartItems, $total);
    } catch (\Throwable $e) {
        // Silent fail
    }
}

/**
 * Fire CRM event on user registration.
 */
function crm_on_user_registered(int $userId, string $name, string $email, string $phone, string $source = 'website'): void
{
    _crm_fire_safe('customer.created', [
        'local_user_id' => $userId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'source' => $source,
    ]);
}

/**
 * Fire CRM event on user login.
 */
function crm_on_user_login(int $userId): void
{
    _crm_fire_safe('customer.login', [
        'user_id' => $userId,
        'phone' => _crm_get_user_field($userId, 'phone'),
    ]);
}

// ─── Internal helpers ────────────────────────────────────────

function _crm_fire_safe(string $event, array $data): void
{
    try {
        require_once __DIR__ . '/crm_engine.php';
        $crm = CRMEngine::getInstance();
        if ($crm->isEnabled()) {
            $crm->fireEvent($event, $data);
        }
    } catch (\Throwable $e) {
        error_log("[CRM Hook] Failed to fire $event: " . $e->getMessage());
    }
}

function _crm_send_notification(int $orderId, string $templateKey, array $extraVars = []): void
{
    try {
        require_once __DIR__ . '/crm_engine.php';
        $crm = CRMEngine::getInstance();
        if ($crm->isEnabled()) {
            $crm->sendOrderNotification($orderId, $templateKey, $extraVars);
        }
    } catch (\Throwable $e) {
        error_log("[CRM Hook] Notification failed for order #$orderId ($templateKey): " . $e->getMessage());
    }
}

function _crm_get_user_field(int $userId, string $field): string
{
    if ($userId <= 0) return '';
    try {
        $altFields = ['phone' => 'mobile', 'name' => 'full_name'];
        $alt = $altFields[$field] ?? null;
        $sql = $alt
            ? "SELECT $field, $alt FROM users WHERE id = ? LIMIT 1"
            : "SELECT $field FROM users WHERE id = ? LIMIT 1";
        $row = db_fetch($sql, [$userId]);
        return $row[$field] ?? $row[$alt ?? ''] ?? '';
    } catch (\Throwable $e) {
        return '';
    }
}

function _crm_mark_cart_recovered(int $userId, int $orderId): void
{
    try {
        require_once __DIR__ . '/crm_engine.php';
        $crm = CRMEngine::getInstance();
        $crm->markCartRecovered($userId, $orderId);
    } catch (\Throwable $e) {
        // Silent fail
    }
}
