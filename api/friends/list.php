<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('GET');

$base = "SELECT f.id AS friendship_id, u.id AS user_id, u.username, COALESCE(u.full_name, u.username) AS full_name, f.created_at
         FROM friendships f
         JOIN users u ON u.id = %s
         WHERE %s
         ORDER BY f.created_at DESC";

// Bạn bè đã kết nối (hai chiều)
$st = $pdo->prepare(sprintf($base,
    'CASE WHEN f.user_id = :a THEN f.friend_id ELSE f.user_id END',
    "f.status = 'accepted' AND (f.user_id = :b OR f.friend_id = :c)"));
$st->execute([':a' => $userId, ':b' => $userId, ':c' => $userId]);
$friends = $st->fetchAll();

// Lời mời đến mình (chờ chấp nhận)
$st = $pdo->prepare(sprintf($base, 'f.user_id', "f.status = 'pending' AND f.friend_id = :a"));
$st->execute([':a' => $userId]);
$incoming = $st->fetchAll();

// Lời mời mình đã gửi
$st = $pdo->prepare(sprintf($base, 'f.friend_id', "f.status = 'pending' AND f.user_id = :a"));
$st->execute([':a' => $userId]);
$outgoing = $st->fetchAll();

jsonResponse([
    'success' => true,
    'data'    => ['friends' => $friends, 'incoming' => $incoming, 'outgoing' => $outgoing],
]);
