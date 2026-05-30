<?php
// Default timezone (overridden by company_profile setting after DB connect)
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('APP_BASE_URI')) {
    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $baseUri = '';

    if ($documentRoot && str_starts_with($projectRoot, $documentRoot)) {
        $baseUri = substr($projectRoot, strlen($documentRoot));
    }

    if ($baseUri) {
        $segments = array_filter(explode('/', str_replace('\\', '/', trim($baseUri, '/'))), 'strlen');
        $encoded = array_map('rawurlencode', $segments);
        $baseUri = '/' . implode('/', $encoded);
    } else {
        $baseUri = '';
    }
    define('APP_BASE_URI', $baseUri);
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        // Get protocol (http or https)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        // Get host
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get base URI
        $base = APP_BASE_URI;
        $trimmed = ltrim($path, '/');

        if ($trimmed === '') {
            $relativePath = $base === '' ? '/' : $base;
            return $protocol . '://' . $host . $relativePath;
        }

        $query = '';
        if (str_contains($trimmed, '?')) {
            [$trimmed, $query] = explode('?', $trimmed, 2);
        }

        $segments = array_filter(explode('/', $trimmed), 'strlen');
        $encoded = array_map('rawurlencode', $segments);
        $encodedPath = implode('/', $encoded);

        $url = ($base === '' ? '' : $base) . '/' . $encodedPath;
        if ($query !== '') {
            $url .= '?' . $query;
        }

        return $protocol . '://' . $host . $url;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

// Auto-detect environment and use appropriate credentials
$isLocal = (
    php_sapi_name() === 'cli' ||
    (isset($_SERVER['HTTP_HOST']) && (
        $_SERVER['HTTP_HOST'] === 'localhost' ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
        strpos($_SERVER['HTTP_HOST'], '::1') !== false ||
        strpos($_SERVER['HTTP_HOST'], '192.168.') !== false
    )) ||
    (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'localhost') !== false)
);

if ($isLocal) {
    // Local XAMPP credentials
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ecommerce_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Production Hostinger credentials
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u237768108_gilafstore');
    define('DB_USER', 'u237768108_gilafstore');
    define('DB_PASS', '1Gfs@#$222');
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Set MySQL timezone to match PHP timezone
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $exception) {
    die('Database connection failed: ' . $exception->getMessage());
}

// MySQLi connection for legacy compatibility (used by GST module and callbacks)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('MySQLi connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+05:30'");

// ─── Dynamic Timezone from Company Profile ───
try {
    $tzRow = $pdo->query("SELECT timezone FROM company_profile WHERE id = 1 LIMIT 1")->fetch();
    if ($tzRow && !empty($tzRow['timezone'])) {
        $tzId = $tzRow['timezone'];
        if (in_array($tzId, timezone_identifiers_list())) {
            date_default_timezone_set($tzId);
            // Compute MySQL-compatible UTC offset
            $now = new DateTime('now', new DateTimeZone($tzId));
            $offsetSec = $now->getOffset();
            $sign = $offsetSec >= 0 ? '+' : '-';
            $offsetSec = abs($offsetSec);
            $mysqlOffset = $sign . sprintf('%02d:%02d', intdiv($offsetSec, 3600), ($offsetSec % 3600) / 60);
            $pdo->exec("SET time_zone = '{$mysqlOffset}'");
            $conn->query("SET time_zone = '{$mysqlOffset}'");
        }
    }
} catch (Exception $e) {
    // Column or table may not exist yet — keep default Asia/Kolkata
}

function get_db_connection(): PDO
{
    global $pdo;
    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_fetch(string $sql, array $params = []): ?array
{
    $stmt = db_query($sql, $params);
    $result = $stmt->fetch();
    return $result ?: null;
}

function db_last_insert_id(): int
{
    global $pdo;
    return (int)$pdo->lastInsertId();
}

function redirect_with_message(string $url, string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
    ];
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = base_url(ltrim($url, '/'));
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Recalculate outstanding for a single party from actual order & payment data.
 * Outstanding = Sum of (order_total - payment_amount) for all unpaid/partial orders.
 * This correctly accounts for FIFO payment allocation at order level.
 * Handles: deleted/cancelled/rejected orders (not counted), fully paid orders (excluded).
 * Uses DECIMAL precision to avoid floating point rounding errors.
 */
function recalculate_party_outstanding(int $partyId): float
{
    // Use DECIMAL precision in SQL to avoid floating point errors
    $orderRow = db_fetch(
        'SELECT CAST(COALESCE(SUM(total_amount - COALESCE(payment_amount, 0)), 0) AS DECIMAL(10,2)) as outstanding 
         FROM sales_orders
         WHERE party_id = ? 
         AND status IN ("approved","dispatched","delivered")
         AND order_type = "new_order" 
         AND payment_status != "received"',
        [$partyId]
    );
    
    $outstanding = (float)($orderRow['outstanding'] ?? 0);
    
    // Round to 2 decimal places and treat values < 0.01 as zero (fixes ₹1 residual issues)
    $outstanding = round($outstanding, 2);
    if (abs($outstanding) < 0.01) {
        $outstanding = 0;
    }
    
    db_query('UPDATE sales_parties SET outstanding_amount = ? WHERE id = ?', [$outstanding, $partyId]);
    return $outstanding;
}

/**
 * Recalculate outstanding for ALL parties at once. Safe to call on page load.
 */
function recalculate_all_parties_outstanding(): void
{
    $parties = db_fetch_all('SELECT id FROM sales_parties');
    foreach ($parties as $p) {
        recalculate_party_outstanding((int)$p['id']);
    }
}

function display_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $bgColor = ($flash['type'] === 'danger') ? '#dc3545' : '#1A3C34';
        $uid = 'flash_' . mt_rand(1000,9999);
        echo '<div id="' . $uid . '" style="position:fixed;top:16px;right:16px;z-index:9999;min-width:250px;max-width:360px;background:' . $bgColor . ';color:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.18);padding:14px 20px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;animation:flashIn .4s ease;pointer-events:auto;">';
        echo '<span style="flex:1;">' . htmlspecialchars($flash['message']) . '</span>';
        echo '<span onclick="this.parentElement.remove()" style="cursor:pointer;opacity:0.7;font-size:18px;line-height:1;">&times;</span>';
        echo '</div>';
        echo '<style>@keyframes flashIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}</style>';
        echo '<script>setTimeout(function(){var e=document.getElementById("' . $uid . '");if(e){e.style.transition="opacity .4s";e.style.opacity="0";setTimeout(function(){e.remove()},400);}},3500);</script>';
        unset($_SESSION['flash']);
    }
}
