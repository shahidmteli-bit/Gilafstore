<?php
/**
 * SMS Service - OTP Generation, Verification & Multi-Provider SMS Sending
 * Phase 2: Phone OTP Login + SMS Admin Config
 * 
 * Supports: Fast2SMS, MSG91, Twilio, Textlocal, Custom API
 * Features: Provider abstraction, failover, rate limiting, logging
 */

require_once __DIR__ . '/db_connect.php';

class SMSService
{
    private $db;
    private $defaultProvider = null;
    private $fallbackProvider = null;

    public function __construct()
    {
        $this->db = get_db_connection();
        $this->loadProviders();
    }

    /**
     * Load active SMS providers from DB
     */
    private function loadProviders(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM sms_providers WHERE is_active = 1 ORDER BY is_default DESC, priority ASC");
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($providers as $p) {
                if ($p['is_default']) {
                    $this->defaultProvider = $p;
                } elseif ($p['is_fallback']) {
                    $this->fallbackProvider = $p;
                }
            }
            
            // If no default set, use first active
            if (!$this->defaultProvider && !empty($providers)) {
                $this->defaultProvider = $providers[0];
            }
        } catch (PDOException $e) {
            error_log("SMS Service: Failed to load providers - " . $e->getMessage());
        }
    }

    /**
     * Generate and send OTP
     */
    public function sendOTP(string $phone, string $purpose = 'login'): array
    {
        // Normalize phone (remove +91, spaces, etc.)
        $phone = $this->normalizePhone($phone);
        
        if (!$this->validatePhone($phone)) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        // Rate limit: max 5 OTPs per phone per 10 minutes
        if ($this->isOTPRateLimited($phone)) {
            return ['success' => false, 'error' => 'Too many OTP requests. Please wait a few minutes.'];
        }

        // Invalidate previous unused OTPs for this phone+purpose
        $this->invalidatePreviousOTPs($phone, $purpose);

        // Generate 6-digit OTP
        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes

        // Store OTP in DB
        try {
            $stmt = $this->db->prepare("INSERT INTO otp_codes (phone, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$phone, $otp, $purpose, $expiresAt]);
        } catch (PDOException $e) {
            error_log("SMS Service: Failed to store OTP - " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to generate OTP'];
        }

        // Send via SMS provider
        $message = "Your Gilaf Store OTP is: $otp. Valid for 5 minutes. Do not share this code.";
        $sendResult = $this->sendSMS($phone, $message, 'otp', $otp);

        $smsSent = false;
        if ($sendResult['success']) {
            $smsSent = true;
        } elseif ($this->fallbackProvider) {
            $fallbackResult = $this->sendSMSViaProvider($this->fallbackProvider, $phone, $message, 'otp', $otp);
            if (!empty($fallbackResult['success'])) {
                $smsSent = true;
            }
        }

        // OTP is stored in DB — always succeed so WACRM WhatsApp delivery can proceed.
        // _sms_sent = true means SMS was also dispatched; false = WhatsApp-only delivery.
        return [
            'success'       => true,
            'message'       => 'OTP generated successfully',
            'expires_in'    => 300,
            '_otp_internal' => $otp,
            '_sms_sent'     => $smsSent,
        ];
    }

    /**
     * Verify OTP
     */
    public function verifyOTP(string $phone, string $code, string $purpose = 'login'): array
    {
        $phone = $this->normalizePhone($phone);
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM otp_codes 
                WHERE phone = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$phone, $purpose]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$otpRecord) {
                return ['success' => false, 'error' => 'OTP expired or not found. Please request a new one.'];
            }

            // Check max attempts
            if ($otpRecord['attempts'] >= $otpRecord['max_attempts']) {
                // Mark as used (burned)
                $this->db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otpRecord['id']]);
                return ['success' => false, 'error' => 'Too many incorrect attempts. Please request a new OTP.'];
            }

            // Increment attempt count
            $this->db->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$otpRecord['id']]);

            // Verify OTP code
            if (!hash_equals($otpRecord['otp_code'], $code)) {
                $remaining = $otpRecord['max_attempts'] - $otpRecord['attempts'] - 1;
                return ['success' => false, 'error' => "Incorrect OTP. $remaining attempts remaining."];
            }

            // Mark as used
            $this->db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$otpRecord['id']]);

            // Mark verified in SMS logs
            try {
                $this->db->prepare("UPDATE sms_logs SET otp_verified = 1 WHERE phone = ? AND otp_code = ? ORDER BY created_at DESC LIMIT 1")
                    ->execute([$phone, $code]);
            } catch (Exception $e) {
                // Non-critical
            }

            return ['success' => true, 'message' => 'OTP verified successfully'];

        } catch (PDOException $e) {
            error_log("SMS Service: OTP verification failed - " . $e->getMessage());
            return ['success' => false, 'error' => 'Verification failed. Please try again.'];
        }
    }

    /**
     * Send SMS via the default provider (with logging)
     */
    public function sendSMS(string $phone, string $message, string $type = 'notification', ?string $otpCode = null): array
    {
        if (!$this->defaultProvider) {
            $this->logSMS($phone, null, $type, $message, 'failed', 'No active SMS provider configured', $otpCode);
            return ['success' => false, 'error' => 'No SMS provider configured'];
        }

        return $this->sendSMSViaProvider($this->defaultProvider, $phone, $message, $type, $otpCode);
    }

    /**
     * Send SMS via a specific provider
     */
    private function sendSMSViaProvider(array $provider, string $phone, string $message, string $type, ?string $otpCode): array
    {
        $slug = $provider['provider_slug'];
        $result = ['success' => false, 'error' => 'Unknown provider'];

        try {
            switch ($slug) {
                case 'fast2sms':
                    $result = $this->sendViaFast2SMS($provider, $phone, $message);
                    break;
                case 'msg91':
                    $result = $this->sendViaMSG91($provider, $phone, $message);
                    break;
                case 'twilio':
                    $result = $this->sendViaTwilio($provider, $phone, $message);
                    break;
                case 'textlocal':
                    $result = $this->sendViaTextlocal($provider, $phone, $message);
                    break;
                default:
                    $result = $this->sendViaCustomAPI($provider, $phone, $message);
                    break;
            }
        } catch (Exception $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        // Log the SMS
        $status = $result['success'] ? 'sent' : 'failed';
        $failureReason = $result['success'] ? null : ($result['error'] ?? 'Unknown error');
        $apiResponse = $result['api_response'] ?? null;
        $this->logSMS($phone, $provider['id'], $type, $message, $status, $failureReason, $otpCode, $apiResponse);

        return $result;
    }

    // ─── Provider Implementations ───

    private function sendViaFast2SMS(array $provider, string $phone, string $message): array
    {
        $apiKey = $this->decrypt($provider['api_key']);
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Fast2SMS API key not configured'];
        }

        $data = [
            'route' => 'otp',
            'variables_values' => substr($message, strpos($message, ':') + 2, 6), // Extract OTP digits
            'numbers' => $phone,
        ];

        // If DLT template is set, use DLT route
        if (!empty($provider['dlt_template_id'])) {
            $data = [
                'route' => 'dlt',
                'sender_id' => $provider['sender_id'] ?? 'GILAF',
                'message' => $message,
                'variables_values' => substr($message, strpos($message, ':') + 2, 6),
                'numbers' => $phone,
                'flash' => 0,
            ];
            if (!empty($provider['dlt_entity_id'])) {
                $data['DLTTemplateId'] = $provider['dlt_template_id'];
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $provider['base_url'] ?? 'https://www.fast2sms.com/dev/bulkV2',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'authorization: ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $provider['timeout_seconds'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL error: $error", 'api_response' => $response];
        }

        $decoded = json_decode($response, true);
        if ($httpCode === 200 && !empty($decoded['return'])) {
            return ['success' => true, 'api_response' => $response];
        }

        return ['success' => false, 'error' => $decoded['message'] ?? 'Fast2SMS request failed', 'api_response' => $response];
    }

    private function sendViaMSG91(array $provider, string $phone, string $message): array
    {
        $authKey = $this->decrypt($provider['api_key']);
        if (empty($authKey)) {
            return ['success' => false, 'error' => 'MSG91 auth key not configured'];
        }

        $countryCode = ltrim($provider['country_code'] ?? '+91', '+');
        
        $data = [
            'template_id' => $provider['otp_template_id'] ?? '',
            'mobile' => $countryCode . $phone,
            'authkey' => $authKey,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => ($provider['base_url'] ?? 'https://control.msg91.com/api/v5/otp') . '?' . http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $provider['timeout_seconds'] ?? 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($httpCode === 200 && ($decoded['type'] ?? '') === 'success') {
            return ['success' => true, 'api_response' => $response];
        }

        return ['success' => false, 'error' => $decoded['message'] ?? 'MSG91 request failed', 'api_response' => $response];
    }

    private function sendViaTwilio(array $provider, string $phone, string $message): array
    {
        $accountSid = $this->decrypt($provider['api_key']);
        $authToken = $this->decrypt($provider['auth_token']);
        $fromNumber = $provider['sender_id'] ?? '';

        if (empty($accountSid) || empty($authToken)) {
            return ['success' => false, 'error' => 'Twilio credentials not configured'];
        }

        $countryCode = $provider['country_code'] ?? '+91';
        $to = $countryCode . $phone;

        $data = http_build_query(['To' => $to, 'From' => $fromNumber, 'Body' => $message]);
        $url = ($provider['base_url'] ?? 'https://api.twilio.com/2010-04-01') . "/Accounts/$accountSid/Messages.json";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => "$accountSid:$authToken",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $provider['timeout_seconds'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'api_response' => $response];
        }

        return ['success' => false, 'error' => $decoded['message'] ?? 'Twilio request failed', 'api_response' => $response];
    }

    private function sendViaTextlocal(array $provider, string $phone, string $message): array
    {
        $apiKey = $this->decrypt($provider['api_key']);
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Textlocal API key not configured'];
        }

        $data = [
            'apikey' => $apiKey,
            'numbers' => '91' . $phone,
            'message' => urlencode($message),
            'sender' => $provider['sender_id'] ?? 'GILAF',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => ($provider['base_url'] ?? 'https://api.textlocal.in/send/') . '?' . http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $provider['timeout_seconds'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (($decoded['status'] ?? '') === 'success') {
            return ['success' => true, 'api_response' => $response];
        }

        return ['success' => false, 'error' => $decoded['errors'][0]['message'] ?? 'Textlocal request failed', 'api_response' => $response];
    }

    private function sendViaCustomAPI(array $provider, string $phone, string $message): array
    {
        if (empty($provider['base_url'])) {
            return ['success' => false, 'error' => 'Custom API URL not configured'];
        }

        $extra = json_decode($provider['extra_config'] ?? '{}', true) ?: [];
        $method = strtoupper($extra['method'] ?? 'POST');
        $headers = $extra['headers'] ?? [];
        $bodyTemplate = $extra['body_template'] ?? '{"phone":"{phone}","message":"{message}"}';

        // Replace placeholders
        $body = str_replace(
            ['{phone}', '{message}', '{api_key}', '{sender_id}', '{otp_template_id}'],
            [$phone, $message, $this->decrypt($provider['api_key']), $provider['sender_id'], $provider['otp_template_id']],
            $bodyTemplate
        );

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $provider['base_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $provider['timeout_seconds'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'api_response' => $response];
        }

        return ['success' => false, 'error' => 'Custom API returned HTTP ' . $httpCode, 'api_response' => $response];
    }

    // ─── Helper Methods ───

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Remove country code prefix if present
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        return $phone;
    }

    private function validatePhone(string $phone): bool
    {
        return preg_match('/^[6-9][0-9]{9}$/', $phone) === 1;
    }

    private function isOTPRateLimited(string $phone): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->execute([$phone]);
            return (int)$stmt->fetchColumn() >= 5;
        } catch (Exception $e) {
            return false;
        }
    }

    private function invalidatePreviousOTPs(string $phone, string $purpose): void
    {
        try {
            $this->db->prepare("UPDATE otp_codes SET is_used = 1 WHERE phone = ? AND purpose = ? AND is_used = 0")
                ->execute([$phone, $purpose]);
        } catch (Exception $e) {
            // Non-critical
        }
    }

    private function logSMS(string $phone, ?int $providerId, string $type, string $message, string $status, ?string $failureReason = null, ?string $otpCode = null, ?string $apiResponse = null): void
    {
        try {
            $providerName = null;
            if ($providerId) {
                $stmt = $this->db->prepare("SELECT provider_name FROM sms_providers WHERE id = ?");
                $stmt->execute([$providerId]);
                $providerName = $stmt->fetchColumn() ?: null;
            }

            $this->db->prepare("INSERT INTO sms_logs (phone, provider_id, provider_name, message_type, message_content, status, failure_reason, api_response, otp_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$phone, $providerId, $providerName, $type, $message, $status, $failureReason, $apiResponse, $otpCode]);
        } catch (Exception $e) {
            error_log("SMS Log failed: " . $e->getMessage());
        }
    }

    /**
     * Simple encryption for storing API keys (uses openssl)
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    private function decrypt(?string $ciphertext): string
    {
        if (empty($ciphertext)) return '';
        
        try {
            $key = self::getEncryptionKey();
            $data = base64_decode($ciphertext);
            $parts = explode('::', $data, 2);
            if (count($parts) !== 2) {
                // Not encrypted, return as-is (for backward compat during transition)
                return $ciphertext;
            }
            $iv = $parts[0];
            $encrypted = $parts[1];
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv) ?: '';
        } catch (Exception $e) {
            return $ciphertext; // Return as-is if decryption fails
        }
    }

    private static function getEncryptionKey(): string
    {
        // Use a constant derived from the DB host + a salt
        // In production, this should be an environment variable
        return hash('sha256', 'gilaf_sms_key_' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), true);
    }

    /**
     * Send test SMS (for admin panel)
     */
    public function sendTestSMS(string $phone, int $providerId): array
    {
        $phone = $this->normalizePhone($phone);
        if (!$this->validatePhone($phone)) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM sms_providers WHERE id = ?");
            $stmt->execute([$providerId]);
            $provider = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$provider) {
                return ['success' => false, 'error' => 'Provider not found'];
            }

            $testMessage = "Gilaf Store: This is a test SMS. If you received this, your SMS provider is configured correctly.";
            return $this->sendSMSViaProvider($provider, $phone, $testMessage, 'test', null);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all providers (for admin panel)
     */
    public static function getAllProviders(): array
    {
        try {
            $db = get_db_connection();
            return $db->query("SELECT * FROM sms_providers ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get SMS logs (for admin panel)
     */
    public static function getLogs(int $limit = 50, int $offset = 0, ?string $phone = null, ?string $status = null): array
    {
        try {
            $db = get_db_connection();
            $where = [];
            $params = [];
            
            if ($phone) {
                $where[] = "phone LIKE ?";
                $params[] = "%$phone%";
            }
            if ($status) {
                $where[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $countStmt = $db->prepare("SELECT COUNT(*) FROM sms_logs $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $db->prepare("SELECT * FROM sms_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['logs' => $logs, 'total' => $total];
        } catch (Exception $e) {
            return ['logs' => [], 'total' => 0];
        }
    }
}

// ─── Standalone helper functions ───

/**
 * OTP Login: Find or create user by phone number
 */
function otp_login_user(string $phone): array
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Find existing user by phone
    $user = db_fetch('SELECT * FROM users WHERE phone = ?', [$phone]);
    
    if ($user) {
        // Existing user - log them in
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'is_admin' => (bool)($user['is_admin'] ?? 0),
        ];
        if (function_exists('secure_session_regenerate')) {
            secure_session_regenerate();
        }
        return ['success' => true, 'is_new' => false, 'user_id' => $user['id']];
    }
    
    // New user - create account with phone only
    try {
        db_query("INSERT INTO users (name, phone, password, auth_method, phone_verified, created_at) VALUES (?, ?, NULL, 'otp', 1, NOW())", [
            'User ' . substr($phone, -4),
            $phone,
        ]);
        
        $newUser = db_fetch('SELECT * FROM users WHERE phone = ?', [$phone]);
        if ($newUser) {
            $_SESSION['user'] = [
                'id' => $newUser['id'],
                'name' => $newUser['name'],
                'email' => $newUser['email'] ?? '',
                'phone' => $newUser['phone'],
                'is_admin' => false,
            ];
            if (function_exists('secure_session_regenerate')) {
                secure_session_regenerate();
            }
            return ['success' => true, 'is_new' => true, 'user_id' => $newUser['id']];
        }
    } catch (Exception $e) {
        error_log("OTP Login: Failed to create user - " . $e->getMessage());
    }
    
    return ['success' => false, 'error' => 'Failed to create account'];
}
