<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$data = getJsonInput();
$id   = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'ID giao dịch là bắt buộc'], 400);
}

$pdo = getDbConnection();

// Kiểm tra quyền sở hữu
$stmt = $pdo->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy giao dịch hoặc bạn không có quyền xoá'], 404);
}

$stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);

jsonResponse([
    'success' => true,
    'message' => 'Đã xoá giao dịch thành công'
]);
