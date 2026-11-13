<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$data  = getJsonInput();
$id    = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$name  = trim($data['name'] ?? '');
$icon  = trim($data['icon'] ?? '');
$color = trim($data['color'] ?? '');

if (!$id || $name === '') {
    jsonResponse(['success' => false, 'message' => 'ID và tên danh mục là bắt buộc'], 400);
}

$pdo = getDbConnection();

// Chỉ cho phép sửa danh mục do người dùng tạo (không phải danh mục hệ thống)
$stmt = $pdo->prepare('SELECT id, type FROM categories WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$category = $stmt->fetch();
if (!$category) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy danh mục hoặc bạn không có quyền chỉnh sửa'], 404);
}

// Kiểm tra trùng tên với danh mục khác cùng loại
$stmt = $pdo->prepare(
    'SELECT id FROM categories WHERE name = ? AND type = ? AND (user_id IS NULL OR user_id = ?) AND id != ? LIMIT 1'
);
$stmt->execute([$name, $category['type'], $userId, $id]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Danh mục với tên này đã tồn tại'], 409);
}

$fields = ['name = ?'];
$params = [$name];

if ($icon !== '') {
    $fields[] = 'icon = ?';
    $params[] = $icon;
}
if ($color !== '') {
    $fields[] = 'color = ?';
    $params[] = $color;
}

$params[] = $id;
$params[] = $userId;

$stmt = $pdo->prepare('UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
$stmt->execute($params);

jsonResponse([
    'success' => true,
    'message' => 'Đã cập nhật danh mục thành công'
]);
