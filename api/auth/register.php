<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$name  = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';

if (!$name || !$email || !$pass) {
    echo json_encode(["error" => "Vui lòng điền đầy đủ thông tin"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["error" => "Email không hợp lệ"]);
    exit;
}

// Kiểm tra email đã tồn tại chưa
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(["error" => "Email đã được sử dụng"]);
    exit;
}

$hashed = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->execute([$name, $email, $hashed]);

echo json_encode(["success" => true, "message" => "Đăng ký thành công"]);
?>