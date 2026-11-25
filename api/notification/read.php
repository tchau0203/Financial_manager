<?php
/**
 * API Đánh dấu thông báo đã đọc / Xóa thông báo
 * Phụ trách: Hoàng Nam
 * Phương thức: POST /api/notification/read.php
 * Payload: { "id": 12 } hoặc { "all": true }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$data = getJsonInput();
$markAll = !empty($data['all']);
$notifId = (int)($data['id'] ?? 0);

// TODO (Hoàng Nam): Triển khai đánh dấu đã đọc
if ($markAll) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
    jsonResponse(['success' => true, 'message' => 'Đã đánh dấu tất cả thông báo là đã đọc']);
} elseif ($notifId > 0) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notifId, $userId]);
    jsonResponse(['success' => true, 'message' => 'Đã cập nhật trạng thái thông báo']);
} else {
    jsonResponse(['error' => 'Thiếu ID thông báo'], 400);
}
