<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$userId = requireAuth();
$data   = getJsonInput();

$fullName = trim($data['full_name'] ?? $data['name'] ?? '');
$email    = trim($data['email'] ?? '');
$phone    = trim($data['phone'] ?? '');
$currency = trim($data['currency'] ?? 'VND');

if (empty($fullName) || empty($email)) {
    jsonResponse(['error' => 'Họ tên và email không được để trống'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email không đúng định dạng'], 400);
}

$pdo = getDbConnection();

// Kiểm tra email có trùng với user khác hay không
$checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
$checkStmt->execute([$email, $userId]);
if ($checkStmt->fetch()) {
    jsonResponse(['error' => 'Email đã được sử dụng bởi tài khoản khác'], 409);
}

// Cập nhật thông tin
$stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, currency = ? WHERE id = ?');
$stmt->execute([$fullName, $email, $phone, $currency, $userId]);

$_SESSION['full_name'] = $fullName;

jsonResponse([
    'success' => true,
    'message' => 'Cập nhật thông tin thành công',
    'user'    => [
        'id'        => $userId,
        'full_name' => $fullName,
        'email'     => $email,
        'phone'     => $phone,
        'currency'  => $currency
    ]
]);
