<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('POST');

$data = getJsonInput();
$id   = filter_var($data['friendship_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Thiếu mã lời mời'], 400);
}

// Chỉ người NHẬN lời mời mới được chấp nhận
$st = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND friend_id = ? AND status = 'pending'");
$st->execute([$id, $userId]);
if ($st->rowCount() === 0) {
    jsonResponse(['success' => false, 'message' => 'Lời mời không tồn tại hoặc đã được xử lý'], 404);
}

try {
    $q = $pdo->prepare('SELECT f.user_id, COALESCE(u.full_name, u.username) AS me FROM friendships f JOIN users u ON u.id = ? WHERE f.id = ?');
    $q->execute([$userId, $id]);
    if ($r = $q->fetch()) {
        $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, "success")')
            ->execute([$r['user_id'], 'Đã kết bạn', $r['me'] . ' đã chấp nhận lời mời kết bạn của bạn']);
    }
} catch (Throwable $e) {}

jsonResponse(['success' => true, 'message' => 'Đã chấp nhận lời mời kết bạn']);
