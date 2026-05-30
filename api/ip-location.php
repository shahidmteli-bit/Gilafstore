<?php
/**
 * Server-side IP Geolocation Endpoint
 * Returns the user's approximate location based on their IP address.
 * This avoids browser tracking prevention blocking client-side API calls.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
// Take the first IP if multiple are provided
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// Don't geolocate local IPs
if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
    echo json_encode(['success' => false, 'error' => 'Local IP address cannot be geolocated']);
    exit;
}

$result = null;

// Service 1: ipwho.is (unlimited, no key)
$result = tryService("https://ipwho.is/{$ip}", function($data) {
    if (!empty($data['success']) && $data['success'] !== false && !empty($data['latitude'])) {
        return [
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'city' => $data['city'] ?? '',
            'state' => $data['region'] ?? '',
            'zip' => $data['postal'] ?? '',
            'country' => $data['country'] ?? ''
        ];
    }
    return null;
});

// Service 2: ip-api.com (works server-side over HTTP, 45 req/min)
if (!$result) {
    $result = tryService("http://ip-api.com/json/{$ip}?fields=status,lat,lon,city,regionName,zip,country", function($data) {
        if (!empty($data['status']) && $data['status'] === 'success') {
            return [
                'lat' => $data['lat'],
                'lng' => $data['lon'],
                'city' => $data['city'] ?? '',
                'state' => $data['regionName'] ?? '',
                'zip' => $data['zip'] ?? '',
                'country' => $data['country'] ?? ''
            ];
        }
        return null;
    });
}

// Service 3: ipapi.co (1K/day)
if (!$result) {
    $result = tryService("https://ipapi.co/{$ip}/json/", function($data) {
        if (!empty($data['latitude'])) {
            return [
                'lat' => $data['latitude'],
                'lng' => $data['longitude'],
                'city' => $data['city'] ?? '',
                'state' => $data['region'] ?? '',
                'zip' => $data['postal'] ?? '',
                'country' => $data['country_name'] ?? ''
            ];
        }
        return null;
    });
}

if ($result) {
    echo json_encode(['success' => true, 'location' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not determine location from IP']);
}

function tryService($url, $parser) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "User-Agent: GilafStore/1.0\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) return null;
    
    $data = @json_decode($response, true);
    if (!$data) return null;
    
    return $parser($data);
}
