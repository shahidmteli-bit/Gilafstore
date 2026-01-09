<?php
/**
 * Comprehensive Error Logging System
 * Provides detailed error tracking with unique error codes
 */

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
    chmod($logDir, 0777);
}

/**
 * Log error with unique error code
 * 
 * @param string $errorCode Unique error code (e.g., CAT001, PROD001)
 * @param string $message Error message
 * @param array $context Additional context data
 * @param string $severity Error severity: ERROR, WARNING, INFO
 */
function log_error(string $errorCode, string $message, array $context = [], string $severity = 'ERROR'): void
{
    $logFile = __DIR__ . '/../logs/app_errors.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error_code' => $errorCode,
        'severity' => $severity,
        'message' => $message,
        'context' => $context,
        'user_id' => $_SESSION['user']['id'] ?? 'guest',
        'user_email' => $_SESSION['user']['email'] ?? 'N/A',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    ];
    
    $logLine = json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n";
    
    file_put_contents($logFile, $logLine, FILE_APPEND);
    
    // Also log to PHP error log
    error_log("[$errorCode] $severity: $message");
}

/**
 * Log successful operation
 */
function log_success(string $code, string $message, array $context = []): void
{
    log_error($code, $message, $context, 'SUCCESS');
}

/**
 * Log warning
 */
function log_warning(string $code, string $message, array $context = []): void
{
    log_error($code, $message, $context, 'WARNING');
}

/**
 * Log info
 */
function log_info(string $code, string $message, array $context = []): void
{
    log_error($code, $message, $context, 'INFO');
}

/**
 * Get recent errors for admin viewing
 */
function get_recent_errors(int $limit = 50): array
{
    $logFile = __DIR__ . '/../logs/app_errors.log';
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $content = file_get_contents($logFile);
    $entries = explode("---\n", $content);
    $errors = [];
    
    foreach (array_reverse($entries) as $entry) {
        if (trim($entry)) {
            $decoded = json_decode($entry, true);
            if ($decoded) {
                $errors[] = $decoded;
                if (count($errors) >= $limit) {
                    break;
                }
            }
        }
    }
    
    return $errors;
}

/**
 * Clear error log
 */
function clear_error_log(): bool
{
    $logFile = __DIR__ . '/../logs/app_errors.log';
    if (file_exists($logFile)) {
        return unlink($logFile);
    }
    return true;
}

/**
 * Error Code Reference:
 * 
 * CATEGORIES:
 * - CAT001: Category creation failed - empty name
 * - CAT002: Category creation failed - database error
 * - CAT003: Category update failed - invalid ID
 * - CAT004: Category update failed - database error
 * - CAT005: Category delete failed - invalid ID
 * - CAT006: Category delete failed - has products
 * - CAT007: Category delete failed - database error
 * 
 * PRODUCTS:
 * - PROD001: Product creation failed - missing required fields
 * - PROD002: Product creation failed - invalid category
 * - PROD003: Product creation failed - image upload error
 * - PROD004: Product creation failed - database error
 * - PROD005: Product update failed - invalid ID
 * - PROD006: Product update failed - database error
 * - PROD007: Product delete failed - invalid ID
 * - PROD008: Product delete failed - database error
 * 
 * ORDERS:
 * - ORD001: Order status update failed - invalid order ID
 * - ORD002: Order status update failed - invalid status
 * - ORD003: Order status update failed - missing courier info
 * - ORD004: Order status update failed - database error
 * 
 * USERS:
 * - USER001: User access management failed - invalid user ID
 * - USER002: User access management failed - invalid action
 * - USER003: User access management failed - missing reason
 * - USER004: User access management failed - database error
 * - USER005: User delete failed - cannot delete self
 * - USER006: User delete failed - database error
 * 
 * BATCHES:
 * - BATCH001: Batch creation failed - empty batch code
 * - BATCH002: Batch creation failed - duplicate code
 * - BATCH003: Batch creation failed - database error
 * - BATCH004: Batch update failed - invalid ID
 * - BATCH005: Batch delete failed - database error
 * 
 * APPLICATIONS:
 * - APP001: Application submission failed - missing fields
 * - APP002: Application submission failed - file upload error
 * - APP003: Application submission failed - database error
 * - APP004: Application approval failed - invalid ID
 * - APP005: Application approval failed - database error
 * 
 * AUTH:
 * - AUTH001: Login failed - invalid credentials
 * - AUTH002: Login failed - user blocked
 * - AUTH003: Registration failed - email exists
 * - AUTH004: Registration failed - database error
 * - AUTH005: Unauthorized access attempt
 * 
 * SYSTEM:
 * - SYS001: Database connection failed
 * - SYS002: File upload directory not writable
 * - SYS003: Configuration error
 * - SYS004: Unknown error
 */
