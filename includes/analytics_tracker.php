<?php
/**
 * Website Performance Analytics - Tracking System
 * Admin-Only Analytics Module
 */

class AnalyticsTracker {
    private $conn;
    private $visitorId;
    private $sessionId;
    private $userId;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeTracking();
    }
    
    private function initializeTracking() {
        // Get or create visitor ID
        if (isset($_COOKIE['analytics_visitor_id'])) {
            $this->visitorId = $_COOKIE['analytics_visitor_id'];
        } else {
            $this->visitorId = $this->generateVisitorId();
            setcookie('analytics_visitor_id', $this->visitorId, time() + (365 * 24 * 60 * 60), '/');
        }
        
        // Get or create session ID
        if (isset($_COOKIE['analytics_session_id'])) {
            $this->sessionId = $_COOKIE['analytics_session_id'];
        } else {
            $this->sessionId = $this->generateSessionId();
            setcookie('analytics_session_id', $this->sessionId, time() + 1800, '/'); // 30 min
        }
        
        // Get user ID if logged in
        $this->userId = $_SESSION['user']['id'] ?? null;
        
        // Update or create visitor record
        $this->updateVisitorRecord();
    }
    
    private function generateVisitorId() {
        return hash('sha256', uniqid() . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    }
    
    private function generateSessionId() {
        return hash('sha256', uniqid() . time() . rand());
    }
    
    private function updateVisitorRecord() {
        // Get geolocation data
        $geoData = $this->getGeolocationData();
        
        // Parse user agent
        $deviceData = $this->parseUserAgent();
        
        // Check if visitor exists
        $checkQuery = "SELECT id, total_visits FROM analytics_visitors WHERE visitor_id = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param('s', $this->visitorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing visitor
            $visitor = $result->fetch_assoc();
            $updateQuery = "UPDATE analytics_visitors 
                           SET last_visit_at = NOW(), 
                               total_visits = total_visits + 1,
                               user_id = ?
                           WHERE visitor_id = ?";
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param('is', $this->userId, $this->visitorId);
            $stmt->execute();
        } else {
            // Insert new visitor
            $insertQuery = "INSERT INTO analytics_visitors 
                           (visitor_id, user_id, first_visit_url, first_referrer, 
                            country, country_code, state, city, 
                            ip_address, user_agent, browser, browser_version, 
                            os, os_version, device_type, language) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($insertQuery);
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
            
            $stmt->bind_param('sissssssssssssss',
                $this->visitorId, $this->userId, $currentUrl, $referrer,
                $geoData['country'], $geoData['country_code'], $geoData['state'], $geoData['city'],
                $ipAddress, $userAgent, $deviceData['browser'], $deviceData['browser_version'],
                $deviceData['os'], $deviceData['os_version'], $deviceData['device_type'], $language
            );
            $stmt->execute();
        }
    }
    
    public function trackPageView($pageUrl, $pageTitle = null, $pageType = null) {
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $referrerType = $this->determineReferrerType($referrer);

        // Update visit duration for the previous page view in this session
        // This approximates session/page time without requiring frontend timers
        $durationQuery = "UPDATE analytics_page_views
                          SET visit_duration = TIMESTAMPDIFF(SECOND, viewed_at, NOW())
                          WHERE visitor_id = ? AND session_id = ?
                          ORDER BY viewed_at DESC
                          LIMIT 1";

        if ($stmt = $this->conn->prepare($durationQuery)) {
            $stmt->bind_param('ss', $this->visitorId, $this->sessionId);
            $stmt->execute();
            $stmt->close();
        }

        $query = "INSERT INTO analytics_page_views 
                 (visitor_id, user_id, page_url, page_title, page_type, 
                  referrer_url, referrer_type, session_id, viewed_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sissssss',
            $this->visitorId, $this->userId, $pageUrl, $pageTitle, $pageType,
            $referrer, $referrerType, $this->sessionId
        );
        $stmt->execute();
        
        // Update visitor page view count
        $updateQuery = "UPDATE analytics_visitors 
                       SET total_page_views = total_page_views + 1 
                       WHERE visitor_id = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param('s', $this->visitorId);
        $stmt->execute();
    }
    
    public function trackProductEvent($productId, $eventType, $eventSource = null, $categoryId = null, $price = null, $quantity = 1) {
        $pageUrl = $_SERVER['REQUEST_URI'] ?? null;
        
        $query = "INSERT INTO analytics_product_events 
                 (visitor_id, user_id, product_id, event_type, event_source, 
                  category_id, product_price, quantity, session_id, page_url, event_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("Analytics Error - Product Event Prepare Failed: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('siissidiss',
            $this->visitorId, $this->userId, $productId, $eventType, $eventSource,
            $categoryId, $price, $quantity, $this->sessionId, $pageUrl
        );
        
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Analytics Error - Product Event Execute Failed: " . $stmt->error);
            error_log("Data: visitor_id={$this->visitorId}, product_id={$productId}, event_type={$eventType}");
            return false;
        }
        
        $stmt->close();
        return true;
    }
    
    private function getGeolocationData() {
        // Get visitor IP address
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Default values
        $geoData = [
            'country' => 'Unknown',
            'country_code' => null,
            'state' => null,
            'city' => null
        ];
        
        // Skip geolocation for localhost/private IPs
        if (!$ip || $ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            $geoData['country'] = 'India'; // Default for local development
            $geoData['country_code'] = 'IN';
            return $geoData;
        }
        
        // Use free IP geolocation API (ip-api.com - no key required, 45 req/min limit)
        try {
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city";
            
            // Set timeout to prevent blocking
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2, // 2 second timeout
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && isset($data['status']) && $data['status'] === 'success') {
                    $geoData['country'] = $data['country'] ?? 'Unknown';
                    $geoData['country_code'] = $data['countryCode'] ?? null;
                    $geoData['state'] = $data['regionName'] ?? null;
                    $geoData['city'] = $data['city'] ?? null;
                }
            }
        } catch (Exception $e) {
            // Silently fail and use default values
            error_log("Geolocation API error: " . $e->getMessage());
        }
        
        return $geoData;
    }
    
    private function parseUserAgent() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Simple user agent parsing
        $browser = 'Unknown';
        $browserVersion = '';
        $os = 'Unknown';
        $osVersion = '';
        $deviceType = 'unknown';
        
        // Detect browser
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Safari';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge';
            $browserVersion = $matches[1];
        }
        
        // Detect OS
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $os = 'Windows';
            $osVersion = $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $os = 'macOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
            $os = 'Android';
            $osVersion = $matches[1];
        } elseif (preg_match('/iOS ([0-9_]+)/', $userAgent, $matches)) {
            $os = 'iOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        }
        
        // Detect device type
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            if (preg_match('/iPad|Tablet/', $userAgent)) {
                $deviceType = 'tablet';
            } else {
                $deviceType = 'mobile';
            }
        } else {
            $deviceType = 'desktop';
        }
        
        return [
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
            'device_type' => $deviceType
        ];
    }
    
    private function determineReferrerType($referrer) {
        if (empty($referrer)) {
            return 'direct';
        }
        
        $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
        $referrerDomain = parse_url($referrer, PHP_URL_HOST);
        
        if ($referrerDomain === $currentDomain) {
            return 'internal';
        }
        
        // Check for search engines
        $searchEngines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu'];
        foreach ($searchEngines as $engine) {
            if (stripos($referrerDomain, $engine) !== false) {
                return 'search';
            }
        }
        
        // Check for social media
        $socialMedia = ['facebook', 'twitter', 'instagram', 'linkedin', 'pinterest', 'reddit'];
        foreach ($socialMedia as $social) {
            if (stripos($referrerDomain, $social) !== false) {
                return 'social';
            }
        }
        
        return 'external';
    }
    
    public function getVisitorId() {
        return $this->visitorId;
    }
    
    public function getSessionId() {
        return $this->sessionId;
    }
}

// Global tracking function
function trackPageView($pageUrl, $pageTitle = null, $pageType = null) {
    global $conn;
    
    // Check if tracking is enabled
    $settingQuery = "SELECT setting_value FROM analytics_settings WHERE setting_key = 'tracking_enabled'";
    $result = $conn->query($settingQuery);
    if ($result && $result->num_rows > 0) {
        $setting = $result->fetch_assoc();
        if ($setting['setting_value'] !== 'true') {
            return;
        }
    }
    
    try {
        $tracker = new AnalyticsTracker($conn);
        $tracker->trackPageView($pageUrl, $pageTitle, $pageType);
    } catch (Exception $e) {
        error_log("Analytics tracking error: " . $e->getMessage());
    }
}

function trackProductEvent($productId, $eventType, $eventSource = null, $categoryId = null, $price = null, $quantity = 1) {
    global $conn;
    
    try {
        $tracker = new AnalyticsTracker($conn);
        $tracker->trackProductEvent($productId, $eventType, $eventSource, $categoryId, $price, $quantity);
    } catch (Exception $e) {
        error_log("Analytics product event error: " . $e->getMessage());
    }
}
