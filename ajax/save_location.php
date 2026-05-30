<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db_connect.php';

$lat = filter_var($_POST['lat'] ?? '', FILTER_VALIDATE_FLOAT);
$lon = filter_var($_POST['lon'] ?? '', FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false) {
    echo json_encode(['success' => false]);
    exit;
}

$googleMapsUrl = 'https://www.google.com/maps?q=' . $lat . ',' . $lon . '&z=15';
$userId        = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
$guestEmail    = $_SESSION['guest_info']['email'] ?? null;
$ip            = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

try {
    $db = get_db_connection();

    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS user_location_captures (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        user_id       INT NULL,
        guest_email   VARCHAR(255) NULL,
        lat           DECIMAL(10,7) NOT NULL,
        lng           DECIMAL(10,7) NOT NULL,
        google_maps_url VARCHAR(500) NOT NULL,
        ip_address    VARCHAR(45) NULL,
        captured_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_captured_at (captured_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $db->prepare(
        "INSERT INTO user_location_captures (user_id, guest_email, lat, lng, google_maps_url, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $guestEmail, $lat, $lon, $googleMapsUrl, $ip]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('save_location error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}
