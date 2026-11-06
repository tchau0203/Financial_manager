<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$pdo = getDbConnection();

// Lấy thông tin người dùng
$stmt = $pdo->prepare('SELECT id, username, email, full_name, phone, currency, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // Trường hợp tài khoản bị xóa khi phiên còn tồn tại
    session_destroy();
    jsonResponse(['error' => 'Người dùng không tồn tại'], 404);
}

// Đếm số thông báo chưa đọc
$notifStmt = $pdo->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0');
$notifStmt->execute([$userId]);
$unreadCount = (int)($notifStmt->fetch()['unread_count'] ?? 0);

jsonResponse([
    'success'      => true,
    'user'         => $user,
    'unread_notifs'=> $unreadCount
]);
