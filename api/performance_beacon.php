<?php
/**
 * Performance Beacon Receiver
 * Receives page load timing data from client-side Navigation Timing API
 * Called via navigator.sendBeacon() — lightweight, non-blocking
 */

require_once __DIR__ . '/../includes/db_connect.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Read beacon payload
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['url']) || !isset($data['load_time'])) {
    http_response_code(400);
    exit;
}

$pageUrl   = substr($data['url'], 0, 500);
$loadTime  = round((float)$data['load_time'], 4);
$ttfb      = isset($data['ttfb']) ? round((float)$data['ttfb'], 4) : null;
$domReady  = isset($data['dom_ready']) ? round((float)$data['dom_ready'], 4) : null;

// Basic validation — reject absurd values
if ($loadTime <= 0 || $loadTime > 60) {
    http_response_code(400);
    exit;
}

try {
    // Auto-create table if not exists (runs once, then cached by MySQL)
    @db_query("CREATE TABLE IF NOT EXISTS page_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_url VARCHAR(500) NOT NULL,
        load_time FLOAT NOT NULL,
        server_time FLOAT DEFAULT NULL,
        ttfb FLOAT DEFAULT NULL,
        dom_ready FLOAT DEFAULT NULL,
        source ENUM('server','client') DEFAULT 'client',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_page_url (page_url(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @db_query(
        "INSERT INTO page_performance (page_url, load_time, ttfb, dom_ready, source, created_at) VALUES (?, ?, ?, ?, 'client', NOW())",
        [$pageUrl, $loadTime, $ttfb, $domReady]
    );

    http_response_code(204); // No Content — success
} catch (Exception $e) {
    http_response_code(500);
}
