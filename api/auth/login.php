<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
session_start();
require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';

if (!$email || !$pass) {
    echo json_encode(["error" => "Vui lòng điền đầy đủ thông tin"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($pass, $user['password'])) {
    echo json_encode(["error" => "Email hoặc mật khẩu không đúng"]);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];

echo json_encode([
    "success" => true,
    "user" => ["id" => $user['id'], "name" => $user['name'], "email" => $user['email']]
]);
?>