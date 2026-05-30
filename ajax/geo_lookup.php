<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');

$lat = filter_var($_GET['lat'] ?? '', FILTER_VALIDATE_FLOAT);
$lon = filter_var($_GET['lon'] ?? '', FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . $lat . '&lon=' . $lon . '&zoom=18&addressdetails=1';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT      => 'GilafStore/1.0 (checkout address autofill)',
    CURLOPT_FOLLOWLOCATION => true,
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($raw === false || $err) {
    echo json_encode(['success' => false, 'message' => 'Could not reach geocoding service.']);
    exit;
}

$data = json_decode($raw, true);
if (!$data || !isset($data['address'])) {
    echo json_encode(['success' => false, 'message' => 'Location not found. Please enter PIN manually.']);
    exit;
}

$addr  = $data['address'];
$pin   = preg_replace('/\D/', '', $addr['postcode'] ?? '');
$city  = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? '';
$state = $addr['state'] ?? '';

// Normalize state names to match dropdown values
$stateMap = [
    'Jammu & Kashmir'                          => 'Jammu and Kashmir',
    'Dadra And Nagar Haveli And Daman And Diu' => 'Dadra and Nagar Haveli and Daman and Diu',
    'Andaman & Nicobar Islands'                => 'Andaman and Nicobar Islands',
    'Andaman And Nicobar Islands'              => 'Andaman and Nicobar Islands',
];
foreach ($stateMap as $raw => $normalized) {
    if (strcasecmp($state, $raw) === 0) { $state = $normalized; break; }
}

// If PIN lookup can give better city, enrich from pin_lookup
if (strlen($pin) === 6) {
    $pinUrl = 'https://api.postalpincode.in/pincode/' . $pin;
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL            => $pinUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $pinRaw = curl_exec($ch2);
    curl_close($ch2);
    if ($pinRaw) {
        $pinData = json_decode($pinRaw, true);
        if ($pinData && isset($pinData[0]['Status']) && $pinData[0]['Status'] === 'Success' && !empty($pinData[0]['PostOffice'])) {
            $po   = $pinData[0]['PostOffice'][0];
            $city = $po['Division'] ?: ($po['District'] ?: $city);
            foreach ($stateMap as $rawS => $normalized) {
                if (strcasecmp($po['State'] ?? '', $rawS) === 0) { $state = $normalized; break; }
            }
            if (!array_key_exists(strtolower($po['State'] ?? ''), array_change_key_case($stateMap))) {
                $state = $po['State'] ?: $state;
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'pin'     => $pin,
    'city'    => $city,
    'state'   => $state,
    'display' => $data['display_name'] ?? '',
]);
