<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';
require_once '../../config/user_schema.php';

$data = json_decode(file_get_contents("php://input"), true);
$data = is_array($data) ? $data : [];
$rawIdentifier = $data['username'] ?? $data['email'] ?? '';
$identifier = is_string($rawIdentifier) ? trim($rawIdentifier) : '';
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

if ($identifier === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Vui lòng nhập email hoặc tên đăng nhập và mật khẩu"]);
    exit;
}

$userSchema = getUserSchema($pdo);
$nameColumn = $userSchema['name'];
$usernameColumn = $userSchema['username'];
$usernameSelect = $usernameColumn ? "$usernameColumn AS username" : "email AS username";
$usernameCondition = $usernameColumn ? "$usernameColumn = ? OR " : '';
$query = "SELECT id, $usernameSelect, email, $nameColumn AS full_name, password FROM users WHERE $usernameCondition email = ? LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute($usernameColumn ? [$identifier, $identifier] : [$identifier]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Tài khoản hoặc mật khẩu không đúng"]);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];

echo json_encode([
    "success" => true,
    "data" => [
        "id" => $user['id'],
        "username" => $user['username'],
        "email" => $user['email'],
        "full_name" => $user['full_name']
    ]
]);
