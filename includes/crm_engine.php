<?php
/**
 * WhatsApp CRM Integration Engine
 * 
 * Handles all communication between GilafStore and WACRM.
 * Features: API calls, webhook dispatch, event queue, customer sync,
 * OTP management, cart recovery, order notifications.
 * 
 * Usage:
 *   require_once 'includes/crm_engine.php';
 *   $crm = CRMEngine::getInstance();
 *   $crm->fireEvent('order.placed', ['order_id' => 123]);
 */

class CRMEngine
{
    private static ?CRMEngine $instance = null;
    private PDO $db;
    private array $settings = [];
    private ?array $apiKey = null;
    private string $baseUrl = '';
    private bool $enabled = false;

    private function __construct()
    {
        global $pdo;
        $this->db = $pdo;
        $this->loadSettings();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── Settings ───────────────────────────────────────────────

    private function loadSettings(): void
    {
        try {
            $rows = $this->db->query("SELECT setting_key, setting_value, setting_type FROM crm_settings")->fetchAll();
            foreach ($rows as $row) {
                $this->settings[$row['setting_key']] = $this->castSetting($row['setting_value'], $row['setting_type']);
            }
            $this->enabled = (bool)($this->settings['crm_enabled'] ?? false);
            $this->baseUrl = rtrim($this->settings['crm_api_url'] ?? 'http://localhost:3000', '/');
        } catch (\PDOException $e) {
            // Tables may not exist yet
            $this->enabled = false;
        }
    }

    private function castSetting(?string $value, string $type)
    {
        if ($value === null) return null;
        switch ($type) {
            case 'boolean': return (bool)(int)$value;
            case 'integer': return (int)$value;
            case 'json': return json_decode($value, true);
            default: return $value;
        }
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Normalize and validate URL
     * @param string $url Raw URL input
     * @return array ['url' => normalizedUrl, 'error' => null|string]
     */
    private function normalizeUrl(string $url): array
    {
        $debug = [
            'raw' => $url,
            'step' => [],
        ];

        // Step 1: Trim whitespace and invisible characters
        $normalized = trim($url);
        $normalized = preg_replace('/[\x00-\x1F\x7F]/u', '', $normalized); // Remove control chars
        $debug['step'][] = 'trimmed: ' . $normalized;

        // Step 2: Check if empty
        if (empty($normalized)) {
            return ['url' => '', 'error' => 'URL is empty', 'debug' => $debug];
        }

        // Step 3: Ensure protocol exists
        if (!preg_match('/^https?:\/\//i', $normalized)) {
            $normalized = 'https://' . $normalized;
            $debug['step'][] = 'added protocol: ' . $normalized;
        }

        // Step 4: Remove trailing slash safely (but keep it for root paths)
        $normalized = rtrim($normalized, '/');
        $debug['step'][] = 'removed trailing slash: ' . $normalized;

        // Step 5: Validate URL structure with parse_url
        $parts = parse_url($normalized);
        $debug['parse_url'] = $parts;

        if (!$parts) {
            return [
                'url' => $normalized,
                'error' => 'Invalid URL format: parse_url failed',
                'debug' => $debug,
            ];
        }

        if (empty($parts['host'])) {
            return [
                'url' => $normalized,
                'error' => 'Invalid URL: no host part detected',
                'debug' => $debug,
            ];
        }

        // Step 6: Validate host format (basic domain validation)
        $host = $parts['host'];
        if (!preg_match('/^[a-zA-Z0-9][-a-zA-Z0-9.]*[a-zA-Z0-9]$/', $host)) {
            // Allow localhost for development
            if ($host !== 'localhost' && !preg_match('/\.localhost$/', $host)) {
                return [
                    'url' => $normalized,
                    'error' => 'Invalid hostname format: ' . $host,
                    'debug' => $debug,
                ];
            }
        }

        $debug['step'][] = 'validation passed';

        return ['url' => $normalized, 'error' => null, 'debug' => $debug];
    }

    public function updateSetting(string $key, $value): void
    {
        $strValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

        // Special handling for CRM API URL
        if ($key === 'crm_api_url') {
            $normalized = $this->normalizeUrl($strValue);

            // Log debug info for troubleshooting
            if ($normalized['error']) {
                error_log('[CRM Engine] URL normalization failed: ' . $normalized['error']);
                error_log('[CRM Engine] URL debug: ' . json_encode($normalized['debug']));
            }

            // Store the normalized URL
            $strValue = $normalized['url'];
            $this->baseUrl = $strValue;
        }

        $this->db->prepare("UPDATE crm_settings SET setting_value = ? WHERE setting_key = ?")
            ->execute([$strValue, $key]);
        $this->settings[$key] = $value;

        if ($key === 'crm_enabled') {
            $this->enabled = (bool)$value;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // ─── API Key Management ─────────────────────────────────────

    public function getActiveApiKey(): ?array
    {
        if ($this->apiKey) return $this->apiKey;
        try {
            $this->apiKey = $this->db->query(
                "SELECT * FROM crm_api_keys WHERE is_active = 1 ORDER BY id DESC LIMIT 1"
            )->fetch();
        } catch (\PDOException $e) {
            $this->apiKey = null;
        }
        return $this->apiKey;
    }

    public function generateApiKey(string $name = 'Auto-generated'): array
    {
        $key = 'gcrm_' . bin2hex(random_bytes(24));
        $secret = bin2hex(random_bytes(32));
        $this->db->prepare(
            "INSERT INTO crm_api_keys (key_name, api_key, api_secret, permissions) VALUES (?, ?, ?, ?)"
        )->execute([$name, $key, $secret, json_encode(['*'])]);
        return ['api_key' => $key, 'api_secret' => $secret];
    }

    public function validateApiKey(string $key, string $secret): bool
    {
        $row = $this->db->prepare(
            "SELECT id FROM crm_api_keys WHERE api_key = ? AND api_secret = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $row->execute([$key, $secret]);
        if ($row->fetch()) {
            $this->db->prepare("UPDATE crm_api_keys SET last_used_at = NOW() WHERE api_key = ?")->execute([$key]);
            return true;
        }
        return false;
    }

    // ─── HTTP Client ────────────────────────────────────────────

    /**
     * Simple health check without authentication
     * Used for initial connection testing
     */
    private function simpleHealthCheck(string $baseUrl, int $timeout = 10): array
    {
        $url = rtrim($baseUrl, '/') . '/api/health';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $durationMs = (int)((microtime(true) - $startTime) * 1000);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $result = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => json_decode($response, true) ?? $response,
            'duration_ms' => $durationMs,
            'error' => $error ?: null,
        ];

        return $result;
    }

    private function apiRequest(string $method, string $path, array $data = [], int $timeout = 10): array
    {
        $url = $this->baseUrl . '/api/' . ltrim($path, '/');
        $apiKey = $this->getActiveApiKey();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-GilafStore-Key: ' . ($apiKey['api_key'] ?? ''),
            'X-GilafStore-Signature: ' . $this->signPayload($data),
            'X-GilafStore-Timestamp: ' . time(),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $durationMs = (int)((microtime(true) - $startTime) * 1000);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $result = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => json_decode($response, true) ?? $response,
            'duration_ms' => $durationMs,
            'error' => $error ?: null,
        ];

        return $result;
    }

    private function signPayload(array $data): string
    {
        $apiKey = $this->getActiveApiKey();
        $secret = $apiKey['api_secret'] ?? '';
        return hash_hmac('sha256', json_encode($data), $secret);
    }

    // ─── Event System ───────────────────────────────────────────

    /**
     * Fire an event — queues it for async processing or sends immediately.
     */
    public function fireEvent(string $eventType, array $payload, bool $async = true): bool
    {
        if (!$this->enabled) return false;

        $payload['_event'] = $eventType;
        $payload['_timestamp'] = date('c');
        $payload['_source'] = 'gilafstore';

        if ($async) {
            return $this->queueEvent($eventType, $payload);
        }

        return $this->dispatchEvent($eventType, $payload);
    }

    private function queueEvent(string $eventType, array $payload): bool
    {
        try {
            $this->db->prepare(
                "INSERT INTO crm_event_queue (event_type, payload, priority) VALUES (?, ?, ?)"
            )->execute([$eventType, json_encode($payload), $this->getEventPriority($eventType)]);
            return true;
        } catch (\PDOException $e) {
            $this->logActivity('system', null, 'queue_failed', 'event', $eventType, ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function dispatchEvent(string $eventType, array $payload): bool
    {
        $endpoint = 'integration/webhook';
        $startTime = microtime(true);

        $result = $this->apiRequest('POST', $endpoint, [
            'event' => $eventType,
            'data' => $payload,
        ]);

        $this->logWebhook('outgoing', $eventType, $this->baseUrl . '/api/' . $endpoint, $payload, $result);

        return $result['success'];
    }

    private function getEventPriority(string $eventType): int
    {
        if (strpos($eventType, 'otp.') === 0) return 1;
        if (strpos($eventType, 'order.') === 0) return 2;
        if (strpos($eventType, 'payment.') === 0) return 2;
        if (strpos($eventType, 'cart.') === 0) return 3;
        if (strpos($eventType, 'customer.') === 0) return 4;
        return 5;
    }

    /**
     * Process pending events in the queue (call from cron/background worker).
     */
    public function processQueue(int $batchSize = 20): int
    {
        $lockId = gethostname() . '_' . getmypid();
        $processed = 0;

        // Lock a batch
        $this->db->prepare(
            "UPDATE crm_event_queue 
             SET locked_by = ?, locked_at = NOW() 
             WHERE status = 'pending' AND (locked_by IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
             ORDER BY priority ASC, created_at ASC 
             LIMIT ?"
        )->execute([$lockId, $batchSize]);

        // Fetch locked events
        $events = $this->db->prepare(
            "SELECT * FROM crm_event_queue WHERE locked_by = ? AND status = 'pending'"
        );
        $events->execute([$lockId]);

        foreach ($events->fetchAll() as $event) {
            $payload = json_decode($event['payload'], true);
            $success = $this->dispatchEvent($event['event_type'], $payload);

            if ($success) {
                $this->db->prepare(
                    "UPDATE crm_event_queue SET status = 'completed', processed_at = NOW(), locked_by = NULL WHERE id = ?"
                )->execute([$event['id']]);
                $processed++;
            } else {
                $attempts = $event['attempts'] + 1;
                $status = $attempts >= $event['max_attempts'] ? 'dead' : 'failed';
                $this->db->prepare(
                    "UPDATE crm_event_queue SET status = ?, attempts = ?, locked_by = NULL, error_message = ? WHERE id = ?"
                )->execute([$status, $attempts, 'Dispatch failed', $event['id']]);
            }
        }

        return $processed;
    }

    // ─── WhatsApp OTP ───────────────────────────────────────────

    public function sendOTP(string $phone, string $purpose = 'login', ?string $ip = null): array
    {
        if (!$this->getSetting('whatsapp_otp_enabled')) {
            return ['success' => false, 'error' => 'WhatsApp OTP is disabled'];
        }

        // Rate limiting
        if ($this->isRateLimited($phone, 'phone')) {
            return ['success' => false, 'error' => 'Too many OTP requests. Please try later.'];
        }
        if ($ip && $this->isRateLimited($ip, 'ip')) {
            return ['success' => false, 'error' => 'Too many requests from your location.'];
        }

        // Generate OTP
        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expirySeconds = $this->getSetting('whatsapp_otp_expiry', 300);
        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);

        // Expire previous pending OTPs for this phone
        $this->db->prepare(
            "UPDATE crm_whatsapp_otp SET status = 'expired' WHERE phone = ? AND status IN ('pending','sent')"
        )->execute([$phone]);

        // Store OTP
        $this->db->prepare(
            "INSERT INTO crm_whatsapp_otp (phone, otp_hash, purpose, ip_address, expires_at) VALUES (?, ?, ?, ?, ?)"
        )->execute([$phone, $otpHash, $purpose, $ip, $expiresAt]);
        $otpId = $this->db->lastInsertId();

        // Send via CRM/WhatsApp
        $result = $this->apiRequest('POST', 'integration/send-otp', [
            'phone' => $phone,
            'otp' => $otp,
            'expiry_minutes' => (int)($expirySeconds / 60),
            'template' => 'otp_verification',
        ]);

        if ($result['success']) {
            $messageId = $result['body']['message_id'] ?? null;
            $this->db->prepare(
                "UPDATE crm_whatsapp_otp SET status = 'sent', whatsapp_message_id = ? WHERE id = ?"
            )->execute([$messageId, $otpId]);
            $this->incrementRateLimit($phone, 'phone');
            if ($ip) $this->incrementRateLimit($ip, 'ip');
            return ['success' => true, 'expires_in' => $expirySeconds];
        }

        $this->db->prepare("UPDATE crm_whatsapp_otp SET status = 'failed' WHERE id = ?")->execute([$otpId]);
        return ['success' => false, 'error' => 'Failed to send OTP. Please try again.'];
    }

    public function verifyOTP(string $phone, string $otp): array
    {
        $record = $this->db->prepare(
            "SELECT * FROM crm_whatsapp_otp 
             WHERE phone = ? AND status = 'sent' AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1"
        );
        $record->execute([$phone]);
        $row = $record->fetch();

        if (!$row) {
            return ['success' => false, 'error' => 'OTP expired or not found. Request a new one.'];
        }

        $maxAttempts = $this->getSetting('whatsapp_otp_max_attempts', 5);
        if ($row['attempts'] >= $maxAttempts) {
            $this->db->prepare("UPDATE crm_whatsapp_otp SET status = 'expired' WHERE id = ?")->execute([$row['id']]);
            return ['success' => false, 'error' => 'Too many attempts. Request a new OTP.'];
        }

        // Increment attempts
        $this->db->prepare("UPDATE crm_whatsapp_otp SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);

        if (password_verify($otp, $row['otp_hash'])) {
            $this->db->prepare(
                "UPDATE crm_whatsapp_otp SET status = 'verified', verified_at = NOW() WHERE id = ?"
            )->execute([$row['id']]);
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Invalid OTP. Please try again.'];
    }

    private function isRateLimited(string $identifier, string $type): bool
    {
        $maxPerHour = $this->getSetting('whatsapp_otp_rate_limit', 10);
        $row = $this->db->prepare(
            "SELECT request_count, blocked_until FROM crm_otp_rate_limits 
             WHERE identifier = ? AND identifier_type = ? AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY window_start DESC LIMIT 1"
        );
        $row->execute([$identifier, $type]);
        $limit = $row->fetch();

        if ($limit && $limit['blocked_until'] && strtotime($limit['blocked_until']) > time()) {
            return true;
        }
        if ($limit && $limit['request_count'] >= $maxPerHour) {
            // Block for 1 hour
            $this->db->prepare(
                "UPDATE crm_otp_rate_limits SET blocked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE identifier = ? AND identifier_type = ?"
            )->execute([$identifier, $type]);
            return true;
        }
        return false;
    }

    private function incrementRateLimit(string $identifier, string $type): void
    {
        $existing = $this->db->prepare(
            "SELECT id FROM crm_otp_rate_limits 
             WHERE identifier = ? AND identifier_type = ? AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $existing->execute([$identifier, $type]);

        if ($existing->fetch()) {
            $this->db->prepare(
                "UPDATE crm_otp_rate_limits SET request_count = request_count + 1 
                 WHERE identifier = ? AND identifier_type = ? AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            )->execute([$identifier, $type]);
        } else {
            $this->db->prepare(
                "INSERT INTO crm_otp_rate_limits (identifier, identifier_type) VALUES (?, ?)"
            )->execute([$identifier, $type]);
        }
    }

    // ─── Customer Sync ──────────────────────────────────────────

    public function syncCustomer(int $userId): array
    {
        if (!$this->getSetting('customer_sync_enabled')) {
            return ['success' => false, 'error' => 'Customer sync disabled'];
        }

        // Fetch local user data
        $user = $this->db->prepare(
            "SELECT u.*, 
                    COUNT(DISTINCT o.id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_spend,
                    MAX(o.created_at) as last_order_date
             FROM users u
             LEFT JOIN orders o ON o.user_id = u.id AND o.status NOT IN ('cancelled','rejected')
             GROUP BY u.id
             HAVING u.id = ?"
        );
        $user->execute([$userId]);
        $userData = $user->fetch();

        if (!$userData) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $syncData = [
            'local_user_id' => $userId,
            'name' => $userData['name'] ?? $userData['full_name'] ?? '',
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'] ?? $userData['mobile'] ?? '',
            'order_count' => (int)$userData['order_count'],
            'total_spend' => (float)$userData['total_spend'],
            'last_order_date' => $userData['last_order_date'],
            'created_at' => $userData['created_at'] ?? $userData['registered_at'] ?? null,
        ];

        $syncHash = md5(json_encode($syncData));

        // Check if already synced with same data
        $existing = $this->db->prepare(
            "SELECT * FROM crm_customer_sync WHERE local_user_id = ?"
        );
        $existing->execute([$userId]);
        $existingSync = $existing->fetch();

        if ($existingSync && $existingSync['sync_hash'] === $syncHash) {
            return ['success' => true, 'status' => 'unchanged'];
        }

        // Send to CRM
        $result = $this->apiRequest('POST', 'integration/sync-customer', $syncData);

        if ($result['success']) {
            $crmContactId = $result['body']['contact_id'] ?? null;

            if ($existingSync) {
                $this->db->prepare(
                    "UPDATE crm_customer_sync SET crm_contact_id = ?, sync_status = 'synced', last_synced_at = NOW(), sync_hash = ? WHERE local_user_id = ?"
                )->execute([$crmContactId, $syncHash, $userId]);
            } else {
                $this->db->prepare(
                    "INSERT INTO crm_customer_sync (local_user_id, crm_contact_id, phone, email, sync_status, last_synced_at, sync_hash) 
                     VALUES (?, ?, ?, ?, 'synced', NOW(), ?)"
                )->execute([$userId, $crmContactId, $syncData['phone'], $syncData['email'], $syncHash]);
            }

            return ['success' => true, 'status' => 'synced', 'crm_contact_id' => $crmContactId];
        }

        // Mark as failed
        if ($existingSync) {
            $this->db->prepare("UPDATE crm_customer_sync SET sync_status = 'failed' WHERE local_user_id = ?")->execute([$userId]);
        }

        return ['success' => false, 'error' => 'CRM sync failed'];
    }

    // ─── Abandoned Cart ─────────────────────────────────────────

    public function trackCartAbandonment(int $userId, ?string $sessionId, array $cartItems, float $total): void
    {
        if (!$this->getSetting('cart_recovery_enabled')) return;

        $phone = $this->getUserPhone($userId);
        $email = $this->getUserEmail($userId);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 7); // 7 days

        // Upsert active cart
        $existing = $this->db->prepare(
            "SELECT id FROM crm_abandoned_carts WHERE user_id = ? AND recovery_status = 'active'"
        );
        $existing->execute([$userId]);
        $row = $existing->fetch();

        $cartJson = json_encode($cartItems);

        if ($row) {
            $this->db->prepare(
                "UPDATE crm_abandoned_carts SET cart_data = ?, cart_total = ?, item_count = ?, abandoned_at = NOW(), expires_at = ? WHERE id = ?"
            )->execute([$cartJson, $total, count($cartItems), $expiresAt, $row['id']]);
        } else {
            $this->db->prepare(
                "INSERT INTO crm_abandoned_carts (user_id, session_id, phone, email, cart_data, cart_total, item_count, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$userId, $sessionId, $phone, $email, $cartJson, $total, count($cartItems), $expiresAt]);
        }
    }

    public function markCartRecovered(int $userId, int $orderId): void
    {
        $this->db->prepare(
            "UPDATE crm_abandoned_carts SET recovery_status = 'recovered', recovered_order_id = ?, recovered_at = NOW() WHERE user_id = ? AND recovery_status IN ('active','reminded')"
        )->execute([$orderId, $userId]);
    }

    /**
     * Process cart recovery reminders (call from cron).
     */
    public function processCartRecovery(): int
    {
        if (!$this->getSetting('cart_recovery_enabled')) return 0;

        $delays = [
            1 => $this->getSetting('cart_recovery_delay_1', 15),
            2 => $this->getSetting('cart_recovery_delay_2', 60),
            3 => $this->getSetting('cart_recovery_delay_3', 1440),
        ];

        $sent = 0;

        foreach ($delays as $stage => $delayMinutes) {
            $carts = $this->db->prepare(
                "SELECT * FROM crm_abandoned_carts 
                 WHERE recovery_status IN ('active','reminded') 
                 AND reminder_stage < ? 
                 AND abandoned_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 AND (last_reminder_at IS NULL OR last_reminder_at <= DATE_SUB(NOW(), INTERVAL 10 MINUTE))
                 AND expires_at > NOW()
                 LIMIT 50"
            );
            $carts->execute([$stage, $delayMinutes]);

            foreach ($carts->fetchAll() as $cart) {
                $templateKey = "cart_reminder_$stage";
                $cartData = json_decode($cart['cart_data'], true);
                $productNames = array_column($cartData ?? [], 'name');

                $success = $this->sendNotification($cart['phone'], $templateKey, [
                    'customer_name' => $this->getUserName($cart['user_id']),
                    'item_count' => $cart['item_count'],
                    'cart_total' => number_format($cart['cart_total'], 2),
                    'product_names' => implode(', ', array_slice($productNames, 0, 3)),
                    'checkout_url' => base_url('checkout.php?recovered=1&uid=' . $cart['user_id']),
                    'discount' => '10',
                    'discount_code' => $this->getSetting('cart_recovery_discount', ''),
                ]);

                if ($success) {
                    $this->db->prepare(
                        "UPDATE crm_abandoned_carts SET reminder_stage = ?, last_reminder_at = NOW(), recovery_status = 'reminded' WHERE id = ?"
                    )->execute([$stage, $cart['id']]);
                    $sent++;
                }
            }
        }

        return $sent;
    }

    // ─── Order Notifications ────────────────────────────────────

    public function sendOrderNotification(int $orderId, string $eventType, array $extraVars = []): bool
    {
        if (!$this->getSetting('order_notifications_enabled')) return false;

        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return false;

        $phone = $order['phone'] ?? $this->getUserPhone($order['user_id'] ?? 0);
        if (!$phone) return false;

        $vars = array_merge([
            'customer_name' => $order['customer_name'] ?? $order['name'] ?? 'Customer',
            'order_id' => $orderId,
            'order_total' => number_format($order['total_amount'] ?? 0, 2),
            'tracking_url' => base_url("track_order.php?id=$orderId"),
        ], $extraVars);

        $success = $this->sendNotification($phone, $eventType, $vars);

        // Log notification
        $this->db->prepare(
            "INSERT INTO crm_order_notifications (order_id, user_id, phone, event_type, status) VALUES (?, ?, ?, ?, ?)"
        )->execute([$orderId, $order['user_id'] ?? null, $phone, $eventType, $success ? 'sent' : 'failed']);

        return $success;
    }

    // ─── Send Notification (generic) ────────────────────────────

    public function sendNotification(string $phone, string $templateKey, array $variables): bool
    {
        if (!$this->enabled) return false;

        $template = $this->db->prepare(
            "SELECT * FROM crm_notification_templates WHERE template_key = ? AND is_active = 1"
        );
        $template->execute([$templateKey]);
        $tpl = $template->fetch();

        if (!$tpl) return false;

        $result = $this->apiRequest('POST', 'integration/send-message', [
            'phone' => $phone,
            'template_name' => $tpl['whatsapp_template_name'],
            'template_lang' => $tpl['whatsapp_template_lang'],
            'variables' => $variables,
            'channel' => $tpl['channel'],
        ]);

        return $result['success'];
    }

    // ─── Connection Test ────────────────────────────────────────

    public function testConnection(): array
    {
        $debug = [
            'baseUrl' => $this->baseUrl,
            'timestamp' => date('c'),
        ];

        // Validate URL before testing
        if (empty($this->baseUrl)) {
            return [
                'connected' => false,
                'error' => 'WACRM URL is not configured. Please enter a valid URL in settings.',
                'debug' => $debug,
            ];
        }

        // Normalize and validate the URL
        $urlCheck = $this->normalizeUrl($this->baseUrl);
        if ($urlCheck['error']) {
            return [
                'connected' => false,
                'error' => 'URL validation failed: ' . $urlCheck['error'],
                'url_normalized' => $urlCheck['url'],
                'url_debug' => $urlCheck['debug'] ?? null,
                'debug' => $debug,
            ];
        }

        $debug['normalizedUrl'] = $urlCheck['url'];
        $debug['endpoint'] = $urlCheck['url'] . '/api/integration/health';

        // Attempt connection to integration health endpoint
        $result = $this->apiRequest('GET', 'integration/health');

        $debug['httpCode'] = $result['http_code'];
        $debug['curlError'] = $result['error'];

        // Build detailed response
        $response = [
            'connected' => $result['success'],
            'http_code' => $result['http_code'],
            'latency_ms' => $result['duration_ms'],
            'error' => $result['error'],
            'response' => $result['body'],
            'url_tested' => $urlCheck['url'],
            'debug' => $debug,
        ];

        // Log for troubleshooting
        if (!$result['success']) {
            error_log('[CRM Engine] Connection test failed: ' . json_encode($debug));
        }

        return $response;
    }

    // ─── Webhook Log ────────────────────────────────────────────

    private function logWebhook(string $direction, string $eventType, string $endpoint, array $payload, array $result): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO crm_webhook_logs (direction, event_type, endpoint, payload, response_code, response_body, status, duration_ms, completed_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([
                $direction,
                $eventType,
                $endpoint,
                json_encode($payload),
                $result['http_code'] ?? 0,
                is_string($result['body']) ? $result['body'] : json_encode($result['body']),
                $result['success'] ? 'delivered' : 'failed',
                $result['duration_ms'] ?? 0,
            ]);
        } catch (\PDOException $e) {
            // Silent fail for logging
        }
    }

    // ─── Activity Log ───────────────────────────────────────────

    public function logActivity(string $actorType, ?string $actorId, string $action, ?string $entityType = null, ?string $entityId = null, ?array $details = null): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO crm_activity_log (actor_type, actor_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $actorType, $actorId, $action, $entityType, $entityId,
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\PDOException $e) {
            // Silent fail
        }
    }

    // ─── Stats ──────────────────────────────────────────────────

    public function getStats(): array
    {
        try {
            return [
                'total_synced_customers' => (int)$this->db->query("SELECT COUNT(*) FROM crm_customer_sync WHERE sync_status = 'synced'")->fetchColumn(),
                'pending_sync' => (int)$this->db->query("SELECT COUNT(*) FROM crm_customer_sync WHERE sync_status = 'pending'")->fetchColumn(),
                'active_abandoned_carts' => (int)$this->db->query("SELECT COUNT(*) FROM crm_abandoned_carts WHERE recovery_status IN ('active','reminded')")->fetchColumn(),
                'recovered_carts' => (int)$this->db->query("SELECT COUNT(*) FROM crm_abandoned_carts WHERE recovery_status = 'recovered'")->fetchColumn(),
                'recovered_revenue' => (float)$this->db->query("SELECT COALESCE(SUM(cart_total), 0) FROM crm_abandoned_carts WHERE recovery_status = 'recovered'")->fetchColumn(),
                'pending_events' => (int)$this->db->query("SELECT COUNT(*) FROM crm_event_queue WHERE status = 'pending'")->fetchColumn(),
                'failed_events' => (int)$this->db->query("SELECT COUNT(*) FROM crm_event_queue WHERE status IN ('failed','dead')")->fetchColumn(),
                'notifications_sent_today' => (int)$this->db->query("SELECT COUNT(*) FROM crm_order_notifications WHERE DATE(created_at) = CURDATE() AND status = 'sent'")->fetchColumn(),
                'otps_sent_today' => (int)$this->db->query("SELECT COUNT(*) FROM crm_whatsapp_otp WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
                'webhook_success_rate' => $this->getWebhookSuccessRate(),
            ];
        } catch (\PDOException $e) {
            return [];
        }
    }

    private function getWebhookSuccessRate(): float
    {
        $total = (int)$this->db->query("SELECT COUNT(*) FROM crm_webhook_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        if ($total === 0) return 100.0;
        $success = (int)$this->db->query("SELECT COUNT(*) FROM crm_webhook_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND status = 'delivered'")->fetchColumn();
        return round(($success / $total) * 100, 1);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function getUserPhone(int $userId): ?string
    {
        if ($userId <= 0) return null;
        $row = $this->db->prepare("SELECT phone, mobile FROM users WHERE id = ? LIMIT 1");
        $row->execute([$userId]);
        $user = $row->fetch();
        return $user['phone'] ?? $user['mobile'] ?? null;
    }

    private function getUserEmail(int $userId): ?string
    {
        if ($userId <= 0) return null;
        $row = $this->db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $row->execute([$userId]);
        $user = $row->fetch();
        return $user['email'] ?? null;
    }

    private function getUserName(int $userId): string
    {
        if ($userId <= 0) return 'Customer';
        $row = $this->db->prepare("SELECT name, full_name FROM users WHERE id = ? LIMIT 1");
        $row->execute([$userId]);
        $user = $row->fetch();
        return $user['name'] ?? $user['full_name'] ?? 'Customer';
    }
}
