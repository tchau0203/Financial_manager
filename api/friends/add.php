<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('POST');

$data     = getJsonInput();
$friendId = filter_var($data['friend_id'] ?? null, FILTER_VALIDATE_INT);
$username = trim((string)($data['username'] ?? ''));

if (!$friendId && $username !== '') {
    $st = $pdo->prepare("SELECT id FROM users WHERE username = ? AND COALESCE(status, 'active') = 'active' LIMIT 1");
    $st->execute([$username]);
    $friendId = (int)$st->fetchColumn();
}
if (!$friendId) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy người dùng'], 404);
}
if ($friendId === $userId) {
    jsonResponse(['success' => false, 'message' => 'Bạn không thể kết bạn với chính mình'], 400);
}
$st = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$st->execute([$friendId]);
if (!$st->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Người dùng không tồn tại'], 404);
}

// Đã có quan hệ ở một trong hai chiều?
$st = $pdo->prepare('SELECT id, user_id, status FROM friendships
                     WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1');
$st->execute([$userId, $friendId, $friendId, $userId]);
$ex = $st->fetch();

if ($ex) {
    if ($ex['status'] === 'accepted') {
        jsonResponse(['success' => false, 'message' => 'Hai bạn đã là bạn bè'], 409);
    }
    if ((int)$ex['user_id'] === $userId) {
        jsonResponse(['success' => false, 'message' => 'Bạn đã gửi lời mời trước đó, hãy chờ phản hồi'], 409);
    }
    // Đối phương đã mời mình -> tự động chấp nhận
    $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ?")->execute([$ex['id']]);
    jsonResponse(['success' => true, 'message' => 'Đối phương đã mời bạn trước, hai bạn đã kết nối!', 'data' => ['id' => (int)$ex['id'], 'status' => 'accepted']]);
}

$pdo->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')")->execute([$userId, $friendId]);
$newId = (int)$pdo->lastInsertId();   // lấy ngay, trước khi insert thông báo

// Thông báo cho người nhận (không làm hỏng luồng chính nếu lỗi)
try {
    $me = $pdo->prepare('SELECT COALESCE(full_name, username) FROM users WHERE id = ?');
    $me->execute([$userId]);
    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, "info")')
        ->execute([$friendId, 'Lời mời kết bạn mới', $me->fetchColumn() . ' muốn kết bạn với bạn trên Bảng tin']);
} catch (Throwable $e) {}

jsonResponse(['success' => true, 'message' => 'Đã gửi lời mời kết bạn', 'data' => ['id' => $newId, 'status' => 'pending']], 201);
