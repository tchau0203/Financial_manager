<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/db.php';

$userId = requireAuth();
$data = getJsonInput();
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'ID ngân sách là bắt buộc'], 400);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy ngân sách'], 404);
}

jsonResponse(['success' => true, 'message' => 'Đã xóa ngân sách thành công']);