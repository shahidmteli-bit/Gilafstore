<?php
/**
 * TEMPORARY DEBUG SCRIPT — DELETE AFTER FIXING
 * Visit https://gilafstore.com/checkout-debug.php to see the actual fatal error.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Trap ALL errors including fatals
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        echo "\n\n=== FATAL ERROR CAUGHT ===\n";
        echo "Type:    " . $err['type'] . "\n";
        echo "Message: " . $err['message'] . "\n";
        echo "File:    " . $err['file'] . "\n";
        echo "Line:    " . $err['line'] . "\n";
        echo "</pre>";
    }
});

set_error_handler(function ($severity, $message, $file, $line) {
    echo "\n[ERROR L{$line}] $message  ($file)\n";
    return false; // continue normal handling
});

echo "<pre>";
echo "Loading checkout.php with full error trapping...\n\n";

try {
    // Simulate a fresh GET request to checkout.php (no POST data)
    $_SERVER['REQUEST_METHOD'] = 'GET';

    // Capture output so we can see where it stops
    ob_start();
    require __DIR__ . '/checkout.php';
    $out = ob_get_clean();

    echo "checkout.php loaded OK. Output length: " . strlen($out) . " bytes.\n";
    echo "First 500 chars of output:\n";
    echo htmlspecialchars(substr($out, 0, 500)) . "\n";
    echo "\nLast 500 chars of output:\n";
    echo htmlspecialchars(substr($out, -500)) . "\n";
} catch (\Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    echo "\n=== EXCEPTION CAUGHT ===\n";
    echo "Class:   " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File:    " . $e->getFile() . "\n";
    echo "Line:    " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
