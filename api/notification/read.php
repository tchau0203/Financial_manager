<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Chỉ hỗ trợ POST'], 405);
}

require_once '../../config/db.php';

$userId = requireAuth();
$data = getJsonInput();
$action = $data['action'] ?? 'read';
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$pdo = getDbConnection();

if (!in_array($action, ['read', 'delete'], true)) {
    jsonResponse(['success' => false, 'message' => 'Hành động không hợp lệ'], 400);
}

if ($action === 'delete') {
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'Cần ID thông báo để xóa'], 400);
    }

    $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy thông báo'], 404);
    }

    jsonResponse(['success' => true, 'message' => 'Đã xóa thông báo']);
}

if ($id) {
    $stmt = $pdo->prepare(
        'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$id, $userId]);
} else {
    $stmt = $pdo->prepare(
        'UPDATE notifications SET is_read = 1 WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
}

jsonResponse(['success' => true, 'message' => 'Đã đánh dấu thông báo đã đọc']);