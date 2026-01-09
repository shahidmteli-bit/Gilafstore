<?php
// Set timezone to India Standard Time
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

define('DB_HOST', 'localhost');
define('DB_NAME', 'u237768108_gilafstore');
define('DB_USER', 'u237768108_gilafstore');
define('DB_PASS', '1Mst@#$941940');

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

function display_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        echo '<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">';
        echo '<div class="toast show" role="alert"><div class="toast-header">';
        echo '<strong class="me-auto text-' . htmlspecialchars($flash['type']) . '">Alert</strong>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>';
        echo '</div><div class="toast-body">' . htmlspecialchars($flash['message']) . '</div></div></div>';
        unset($_SESSION['flash']);
    }
}
