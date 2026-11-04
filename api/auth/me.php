<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';
require_once '../../config/user_schema.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập"]);
    exit;
}

$userSchema = getUserSchema($pdo);
$nameColumn = $userSchema['name'];
$usernameSelect = $userSchema['username'] ? $userSchema['username'] . " AS username" : "email AS username";
$stmt = $pdo->prepare("SELECT id, $usernameSelect, email, $nameColumn AS full_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Phiên đăng nhập không hợp lệ"]);
    exit;
}

$_SESSION['user_name'] = $user['full_name'];
echo json_encode(["success" => true, "data" => $user]);
