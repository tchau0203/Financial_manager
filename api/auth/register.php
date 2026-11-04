<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';
require_once '../../config/user_schema.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Dữ liệu gửi lên không hợp lệ"]);
    exit;
}

$fullName = is_string($data['full_name'] ?? null) ? trim($data['full_name']) : '';
$username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
$email = is_string($data['email'] ?? null) ? trim($data['email']) : '';
$password = $data['password'] ?? '';

if ($fullName === '' || $username === '' || $email === '' || !is_string($password) || $password === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Vui lòng điền đầy đủ thông tin"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email không hợp lệ"]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Mật khẩu phải có ít nhất 6 ký tự"]);
    exit;
}

if (strlen($fullName) > 150 || strlen($username) > 50 || strlen($email) > 255) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thông tin tài khoản vượt quá độ dài cho phép"]);
    exit;
}

$userSchema = getUserSchema($pdo);
$usernameColumn = $userSchema['username'];
$usernameCondition = $usernameColumn ? "$usernameColumn = ? OR " : '';
$stmt = $pdo->prepare("SELECT id FROM users WHERE $usernameCondition email = ? LIMIT 1");
$stmt->execute($usernameColumn ? [$username, $email] : [$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "Tên đăng nhập hoặc email đã được sử dụng"]);
    exit;
}

$columns = [];
$values = [];
if ($usernameColumn) {
    $columns[] = $usernameColumn;
    $values[] = $username;
}
$columns[] = 'email';
$values[] = $email;
$columns[] = 'password';
$values[] = password_hash($password, PASSWORD_DEFAULT);
$columns[] = $userSchema['name'];
$values[] = $fullName;
$placeholders = implode(', ', array_fill(0, count($columns), '?'));
$stmt = $pdo->prepare("INSERT INTO users (" . implode(', ', $columns) . ") VALUES ($placeholders)");
$stmt->execute($values);

http_response_code(201);
echo json_encode(["success" => true, "message" => "Đăng ký thành công"]);
