<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');

$pin = preg_replace('/\D/', '', $_GET['pin'] ?? '');
if (strlen($pin) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
    exit;
}

$url = 'https://api.postalpincode.in/pincode/' . $pin;
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
    CURLOPT_FOLLOWLOCATION => true,
]);
$raw  = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

if ($raw === false || $err) {
    echo json_encode(['success' => false, 'message' => 'Could not reach PIN service. Enter city/state manually.']);
    exit;
}

$data = json_decode($raw, true);

if (!$data || !isset($data[0]['Status']) || $data[0]['Status'] !== 'Success' || empty($data[0]['PostOffice'])) {
    echo json_encode(['success' => false, 'message' => 'PIN not found in database. Enter city/state manually.']);
    exit;
}

$po    = $data[0]['PostOffice'][0];
$city  = $po['Division'] ?: ($po['District'] ?: ($po['Block'] ?: ''));
$state = trim($po['State'] ?? '');

// Normalize state names to match dropdown values
$stateMap = [
    'Jammu & Kashmir'                         => 'Jammu and Kashmir',
    'Jammu and Kashmir'                       => 'Jammu and Kashmir',
    'Dadra And Nagar Haveli And Daman And Diu'=> 'Dadra and Nagar Haveli and Daman and Diu',
    'Andaman & Nicobar Islands'               => 'Andaman and Nicobar Islands',
    'Andaman And Nicobar Islands'             => 'Andaman and Nicobar Islands',
];
foreach ($stateMap as $raw => $normalized) {
    if (strcasecmp($state, $raw) === 0) { $state = $normalized; break; }
}

echo json_encode(['success' => true, 'city' => $city, 'state' => $state, 'district' => $po['District'] ?? '']);
