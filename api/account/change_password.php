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

$currentPassword = $data['current_password'] ?? $data['old_password'] ?? '';
$newPassword     = $data['new_password'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    jsonResponse(['error' => 'Vui lòng nhập mật khẩu hiện tại và mật khẩu mới'], 400);
}

if (strlen($newPassword) < 6) {
    jsonResponse(['error' => 'Mật khẩu mới phải có ít nhất 6 ký tự'], 400);
}

$pdo = getDbConnection();

// Lấy mật khẩu hiện tại trong DB
$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password'])) {
    jsonResponse(['error' => 'Mật khẩu hiện tại không chính xác'], 400);
}

// Băm mật khẩu mới và cập nhật
$hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);
$updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$updateStmt->execute([$hashedNewPassword, $userId]);

jsonResponse([
    'success' => true,
    'message' => 'Đổi mật khẩu thành công'
]);
