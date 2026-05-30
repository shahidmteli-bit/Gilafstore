<?php
/**
 * Secure document serving proxy for admin panel.
 * Detects actual MIME type of uploaded files regardless of extension.
 * Fixes files that were compressed from PDF to JPEG but kept .pdf extension.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Admin only
if (empty($_SESSION['admin']) || empty($_SESSION['admin']['is_admin'])) {
    http_response_code(403);
    die('Unauthorized');
}

$file = $_GET['file'] ?? '';

// Sanitize: only allow alphanumeric, underscores, dots, hyphens
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
    http_response_code(400);
    die('Invalid filename');
}

$uploadDir = __DIR__ . '/../uploads/distributor_applications/';
$filePath = realpath($uploadDir . $file);

// Ensure the file is within the upload directory (prevent directory traversal)
if (!$filePath || strpos($filePath, realpath($uploadDir)) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Detect actual MIME type from file contents
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

// Map MIME to proper display extension for Content-Disposition
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
];

$realExt = $extMap[$mimeType] ?? pathinfo($file, PATHINFO_EXTENSION);
$displayName = pathinfo($file, PATHINFO_FILENAME) . '.' . $realExt;

// Serve the file with correct headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $displayName . '"');
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
