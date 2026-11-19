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
    jsonResponse(['success' => false, 'message' => 'ID danh mục là bắt buộc'], 400);
}

$pdo = getDbConnection();

// Chỉ cho phép xoá danh mục do người dùng tạo
$stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy danh mục hoặc bạn không có quyền xoá'], 404);
}

// Kiểm tra danh mục có giao dịch không
$stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
if ((int)$stmt->fetchColumn() > 0) {
    jsonResponse(['success' => false, 'message' => 'Không thể xoá danh mục đang có giao dịch. Vui lòng xoá các giao dịch trước'], 400);
}

$stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);

jsonResponse([
    'success' => true,
    'message' => 'Đã xoá danh mục thành công'
]);
