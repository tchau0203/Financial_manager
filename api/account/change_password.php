<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Chưa đăng nhập"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$oldPassword = $data['old_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($oldPassword, $user['password'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Mật khẩu hiện tại không đúng"]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Mật khẩu mới phải có ít nhất 6 ký tự"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['user_id']]);

echo json_encode(["success" => true, "message" => "Đổi mật khẩu thành công"]);
