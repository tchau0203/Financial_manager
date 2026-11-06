<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$data      = getJsonInput();
$username  = trim($data['username'] ?? '');
$email     = trim($data['email'] ?? '');
$password  = $data['password'] ?? '';
$full_name = trim($data['full_name'] ?? '');

// Validate
if (!$username || !$email || !$password) {
    jsonResponse(['error' => 'Vui lòng điền đầy đủ thông tin'], 400);
}
if (strlen($username) < 3 || strlen($username) > 100) {
    jsonResponse(['error' => 'Tên đăng nhập phải từ 3-100 ký tự'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email không hợp lệ'], 400);
}
if (strlen($password) < 6) {
    jsonResponse(['error' => 'Mật khẩu phải ít nhất 6 ký tự'], 400);
}

$pdo = getDbConnection();

// Kiểm tra trùng
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Email hoặc tên đăng nhập đã tồn tại'], 409);
}

// Tạo tài khoản
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare(
    'INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$username, $email, $hashed, $full_name]);
$userId = (int)$pdo->lastInsertId();

$_SESSION['user_id'] = $userId;

jsonResponse([
    'success' => true,
    'message' => 'Đăng ký thành công',
    'user'    => ['id' => $userId, 'username' => $username, 'email' => $email, 'full_name' => $full_name],
    'data'    => ['id' => $userId, 'username' => $username, 'email' => $email, 'full_name' => $full_name]
], 201);
?>