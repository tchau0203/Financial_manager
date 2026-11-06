<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

function loadEnvFile($filePath) {
    if (!file_exists($filePath)) return [];
    $vars = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $vars[$name] = $value;
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
    return $vars;
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        $env = loadEnvFile($envFile);

        $host   = $env['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port   = (int)($env['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $dbname = $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'quanlychitieu';
        $user   = $env['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $pass   = $env['DB_PASS'] ?? getenv('DB_PASS') ?? '';

        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Kết nối CSDL thất bại: ' . $e->getMessage()], 500);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Chưa đăng nhập'], 401);
    }
    return (int)$_SESSION['user_id'];
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if ($raw) {
        return json_decode($raw, true) ?? [];
    }
    return $_POST ?? [];
}
?>
