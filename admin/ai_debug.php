<?php
// Diagnostic: tests includes and reports PHP errors as JSON
ob_start();
$log = [];
$log['php_version'] = PHP_VERSION;
$log['php_os'] = PHP_OS;
$log['sapi'] = php_sapi_name();

// Test auth.php
try {
    ob_start();
    require_once __DIR__ . '/../includes/auth.php';
    $log['auth_output'] = ob_get_clean() ?: 'OK';
} catch (Throwable $t) {
    ob_end_clean();
    $log['auth_error'] = $t->getMessage() . ' in ' . basename($t->getFile()) . ':' . $t->getLine();
}

// Test functions.php
try {
    ob_start();
    if (!function_exists('asset_url')) require_once __DIR__ . '/../includes/functions.php';
    $log['functions_output'] = ob_get_clean() ?: 'OK';
} catch (Throwable $t) {
    ob_end_clean();
    $log['functions_error'] = $t->getMessage() . ' in ' . basename($t->getFile()) . ':' . $t->getLine();
}

// Test db_connect.php
try {
    ob_start();
    require_once __DIR__ . '/../includes/db_connect.php';
    $log['db_output'] = ob_get_clean() ?: 'OK';
} catch (Throwable $t) {
    ob_end_clean();
    $log['db_error'] = $t->getMessage() . ' in ' . basename($t->getFile()) . ':' . $t->getLine();
}

// Test is_admin function
$log['is_admin_exists'] = function_exists('is_admin');
if (function_exists('is_admin')) {
    $log['is_admin_result'] = is_admin();
    $log['session_id'] = session_id() ?: 'no session';
}

// Test get_db_connection
if (function_exists('get_db_connection')) {
    try {
        $db = get_db_connection();
        $row = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model','api_key')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $log['chatbot_ai_provider'] = $row['ai_provider'] ?? 'NOT SET';
        $log['chatbot_ai_model']    = $row['ai_model']    ?? 'NOT SET';
        $log['chatbot_api_key_len'] = isset($row['api_key']) ? strlen($row['api_key']) : 0;
        $log['chatbot_api_key_prefix'] = isset($row['api_key']) ? substr($row['api_key'], 0, 8) . '...' : 'NONE';
    } catch (Throwable $t) {
        $log['db_query_error'] = $t->getMessage();
    }
}

// Test that blog_ai_api.php can be parsed by PHP
$apiFile = __DIR__ . '/blog_ai_api.php';
$log['blog_ai_api_exists'] = file_exists($apiFile);
$log['blog_ai_api_size']   = file_exists($apiFile) ? filesize($apiFile) . ' bytes' : 'N/A';

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
