<?php
/**
 * Live Chat File Upload Handler
 */

session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check admin authentication
if (!is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'File upload incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'No file selected';
                break;
            default:
                $errorMsg = 'Upload error';
        }
    }
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$chat_id = intval($_POST['chat_id'] ?? 0);
$type = $_POST['type'] ?? 'file';

if (!$chat_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid chat ID']);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmp = $file['tmp_name'];

// Max 5MB
if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max 5MB allowed.']);
    exit;
}

// Allowed extensions
$allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowedFiles = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($type === 'image') {
    if (!in_array($ext, $allowedImages)) {
        echo json_encode(['success' => false, 'error' => 'Invalid image type. Allowed: ' . implode(', ', $allowedImages)]);
        exit;
    }
} else {
    if (!in_array($ext, $allowedFiles)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedFiles)]);
        exit;
    }
}

// Create upload directory
$uploadDir = '../uploads/chat_files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$newFileName = 'chat_' . $chat_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$uploadPath = $uploadDir . $newFileName;

if (move_uploaded_file($fileTmp, $uploadPath)) {
    // Get the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $fileUrl = $protocol . '://' . $host . $basePath . '/uploads/chat_files/' . $newFileName;
    
    echo json_encode([
        'success' => true,
        'url' => $fileUrl,
        'filename' => $fileName,
        'type' => $type
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}
