<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $host   = 'localhost';
        $dbname = 'quanlychitieu';
        $user   = 'root';
        $pass   = '';
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Kết nối CSDL thất bại'], 500);
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