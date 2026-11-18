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

$pdo = getDbConnection();

if ($id) {
    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$id, $userId]);
} else {
    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
}

jsonResponse(['success' => true, 'message' => 'Đã cập nhật trạng thái thông báo']);