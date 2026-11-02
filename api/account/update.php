<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Chưa đăng nhập"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$name  = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');

if (!$name || !$email) {
    echo json_encode(["error" => "Vui lòng điền đầy đủ"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
$stmt->execute([$name, $email, $_SESSION['user_id']]);

echo json_encode(["success" => true, "message" => "Cập nhật thành công"]);
?>