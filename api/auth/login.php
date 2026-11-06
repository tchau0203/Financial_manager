<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$data       = getJsonInput();
$loginInput = trim($data['username'] ?? $data['email'] ?? '');
$password   = $data['password'] ?? '';

if (empty($loginInput) || empty($password)) {
    jsonResponse(['error' => 'Vui lòng nhập tài khoản/email và mật khẩu'], 400);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT id, username, email, password, full_name, phone, currency FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute([$loginInput, $loginInput]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(['error' => 'Tên đăng nhập/email hoặc mật khẩu không chính xác'], 401);
}

// Thiết lập phiên đăng nhập
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];

unset($user['password']);

jsonResponse([
    'success' => true,
    'message' => 'Đăng nhập thành công',
    'user'    => $user,
    'data'    => $user
]);
