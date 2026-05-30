<?php
/**
 * Google OAuth 2.0 Login Handler
 * Phase 3: Google Login (secondary auth method)
 * 
 * Requirements:
 * 1. Create project at https://console.cloud.google.com/
 * 2. Enable Google+ API / People API
 * 3. Create OAuth 2.0 credentials (Web application)
 * 4. Set Authorized redirect URI: https://gilafstore.com/api/google_callback.php
 * 5. Save Client ID and Client Secret in admin settings or DB
 */

require_once __DIR__ . '/db_connect.php';

class GoogleOAuth
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $tokenUrl = 'https://oauth2.googleapis.com/token';
    private $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function __construct()
    {
        // Load credentials from DB settings or fallback to constants
        $this->clientId = $this->getSetting('google_oauth_client_id', '');
        $this->clientSecret = $this->getSetting('google_oauth_client_secret', '');
        
        $baseUrl = 'https://gilafstore.com';
        $this->redirectUri = $baseUrl . '/api/google_callback.php';
    }

    /**
     * Check if Google OAuth is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get the authorization URL (redirects user to Google)
     */
    public function getAuthUrl(string $state = ''): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ];
        
        if ($state) {
            $params['state'] = $state;
        }

        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code): ?array
    {
        $data = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Google OAuth token error: $error");
            return null;
        }

        $decoded = json_decode($response, true);
        if (empty($decoded['access_token'])) {
            error_log("Google OAuth token response: $response");
            return null;
        }

        return $decoded;
    }

    /**
     * Get user info from Google using access token
     */
    public function getUserInfo(string $accessToken): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->userInfoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Google OAuth userinfo error: $error");
            return null;
        }

        $decoded = json_decode($response, true);
        if (empty($decoded['id'])) {
            error_log("Google OAuth userinfo response: $response");
            return null;
        }

        return $decoded;
    }

    /**
     * Find or create user from Google profile
     */
    public function findOrCreateUser(array $googleUser): ?array
    {
        $db = get_db_connection();
        $googleId = $googleUser['id'];
        $email = $googleUser['email'] ?? '';
        $name = $googleUser['name'] ?? 'Google User';
        $avatar = $googleUser['picture'] ?? '';

        // 1. Check oauth_accounts table first
        try {
            $stmt = $db->prepare("SELECT user_id FROM oauth_accounts WHERE provider = 'google' AND provider_id = ?");
            $stmt->execute([$googleId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update last used info
                $db->prepare("UPDATE oauth_accounts SET provider_name = ?, provider_avatar = ?, updated_at = NOW() WHERE provider = 'google' AND provider_id = ?")
                   ->execute([$name, $avatar, $googleId]);
                
                $user = db_fetch('SELECT * FROM users WHERE id = ?', [$existing['user_id']]);
                return $user ?: null;
            }
        } catch (Exception $e) {
            error_log("Google OAuth lookup error: " . $e->getMessage());
        }

        // 2. Check if user exists by email
        $user = null;
        if ($email) {
            $user = db_fetch('SELECT * FROM users WHERE email = ?', [$email]);
        }

        if ($user) {
            // Link Google account to existing user
            $this->linkGoogleAccount($user['id'], $googleId, $email, $name, $avatar);
            return $user;
        }

        // 3. Create new user
        try {
            $db->prepare("INSERT INTO users (name, email, password, auth_method, google_id, email_verified, created_at) VALUES (?, ?, NULL, 'google', ?, 1, NOW())")
               ->execute([$name, $email, $googleId]);
            
            $newUserId = (int)$db->lastInsertId();
            $this->linkGoogleAccount($newUserId, $googleId, $email, $name, $avatar);
            
            // Send welcome email
            try {
                if (function_exists('send_welcome_email') && $email) {
                    require_once __DIR__ . '/order_emails.php';
                    send_welcome_email($email, $name);
                }
            } catch (Exception $e) {
                error_log("Google user welcome email failed: " . $e->getMessage());
            }

            return db_fetch('SELECT * FROM users WHERE id = ?', [$newUserId]);
        } catch (Exception $e) {
            error_log("Google OAuth user creation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Link Google account to user in oauth_accounts table
     */
    private function linkGoogleAccount(int $userId, string $googleId, string $email, string $name, string $avatar): void
    {
        try {
            $db = get_db_connection();
            $db->prepare("INSERT INTO oauth_accounts (user_id, provider, provider_id, provider_email, provider_name, provider_avatar) VALUES (?, 'google', ?, ?, ?, ?) ON DUPLICATE KEY UPDATE provider_name = VALUES(provider_name), provider_avatar = VALUES(provider_avatar), updated_at = NOW()")
               ->execute([$userId, $googleId, $email, $name, $avatar]);
        } catch (Exception $e) {
            error_log("Link Google account error: " . $e->getMessage());
        }
    }

    /**
     * Log in the user via session
     */
    public function loginUser(array $user): void
    {
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
    }

    /**
     * Get a setting from the database
     */
    private function getSetting(string $key, string $default = ''): string
    {
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT setting_value FROM gst_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}
