<?php
header("Content-Type: application/json");
session_start();
require_once '../../config/db.php';
require_once '../../config/user_schema.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Chưa đăng nhập"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$name  = trim($data['full_name'] ?? $data['name'] ?? '');
$email = trim($data['email'] ?? '');

if (!$name || !$email) {
    echo json_encode(["error" => "Vui lòng điền đầy đủ"]);
    exit;
}

$userSchema = getUserSchema($pdo);
$nameColumn = $userSchema['name'];
$stmt = $pdo->prepare("UPDATE users SET $nameColumn = ?, email = ? WHERE id = ?");
$stmt->execute([$name, $email, $_SESSION['user_id']]);
$_SESSION['user_name'] = $name;

echo json_encode(["success" => true, "message" => "Cập nhật thành công"]);
?>