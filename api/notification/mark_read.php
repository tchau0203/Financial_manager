<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

$data = getJsonInput();
$notificationId = isset($data['id']) ? (int)$data['id'] : 0;

$pdo = getDbConnection();
if ($notificationId > 0) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
} else {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
}

jsonResponse([
    'success' => true,
    'message' => 'Đã đánh dấu thông báo là đã đọc'
]);
