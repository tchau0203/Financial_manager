<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('POST');

$data   = getJsonInput();
$postId = filter_var($data['post_id'] ?? null, FILTER_VALIDATE_INT);
$emoji  = (string)($data['emoji'] ?? '');

if (!$postId || !in_array($emoji, FEED_EMOJIS, true)) {
    jsonResponse(['success' => false, 'message' => 'Dữ liệu reaction không hợp lệ'], 400);
}
if (!canViewPost($pdo, $userId, $postId)) {
    jsonResponse(['success' => false, 'message' => 'Bài đăng không tồn tại hoặc bạn không có quyền xem'], 404);
}

// Toggle: đã react emoji này thì xóa, chưa thì thêm
$del = $pdo->prepare('DELETE FROM post_reactions WHERE post_id = ? AND user_id = ? AND emoji = ?');
$del->execute([$postId, $userId, $emoji]);
$active = false;
if ($del->rowCount() === 0) {
    $ins = $pdo->prepare('INSERT IGNORE INTO post_reactions (post_id, user_id, emoji) VALUES (?, ?, ?)');
    $ins->execute([$postId, $userId, $emoji]);
    $active = true;
}

$cnt = $pdo->prepare('SELECT COUNT(*) FROM post_reactions WHERE post_id = ? AND emoji = ?');
$cnt->execute([$postId, $emoji]);

jsonResponse([
    'success' => true,
    'data'    => ['post_id' => $postId, 'emoji' => $emoji, 'active' => $active, 'count' => (int)$cnt->fetchColumn()],
]);
