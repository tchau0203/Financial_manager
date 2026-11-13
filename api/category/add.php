<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$data  = getJsonInput();
$name  = trim($data['name'] ?? '');
$type  = $data['type'] ?? '';
$icon  = trim($data['icon'] ?? 'fa-tag');
$color = trim($data['color'] ?? '#4F46E5');

if ($name === '' || !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(['success' => false, 'message' => 'Tên danh mục và loại (income/expense) là bắt buộc'], 400);
}

$pdo = getDbConnection();

// Kiểm tra trùng tên cho cùng loại và cùng người dùng (hoặc danh mục hệ thống)
$stmt = $pdo->prepare(
    'SELECT id FROM categories WHERE name = ? AND type = ? AND (user_id IS NULL OR user_id = ?) LIMIT 1'
);
$stmt->execute([$name, $type, $userId]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Danh mục với tên này đã tồn tại'], 409);
}

$stmt = $pdo->prepare('INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$userId, $name, $type, $icon, $color]);

jsonResponse([
    'success' => true,
    'message' => 'Đã thêm danh mục thành công',
    'data'    => ['id' => (int)$pdo->lastInsertId()]
], 201);
