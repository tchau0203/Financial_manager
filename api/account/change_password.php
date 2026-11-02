<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Chưa đăng nhập"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$old  = $data['old_password'] ?? '';
$new  = $data['new_password'] ?? '';

$stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!password_verify($old, $user['password'])) {
    echo json_encode(["error" => "Mật khẩu cũ không đúng"]);
    exit;
}

$hashed = password_hash($new, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
$stmt->execute([$hashed, $_SESSION['user_id']]);

echo json_encode(["success" => true, "message" => "Đổi mật khẩu thành công"]);
?>