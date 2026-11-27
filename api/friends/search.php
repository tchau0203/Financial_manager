<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('GET');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    jsonResponse(['success' => true, 'data' => []]);
}
if (mb_strlen($q) > 50) $q = mb_substr($q, 0, 50);

// Escape ký tự đặc biệt của LIKE
$like = '%' . addcslashes($q, '\\%_') . '%';

$st = $pdo->prepare("
    SELECT u.id, u.username, COALESCE(u.full_name, u.username) AS full_name,
           f.id AS friendship_id, f.user_id AS requester_id, f.status
    FROM users u
    LEFT JOIN friendships f
           ON (f.user_id = :me1 AND f.friend_id = u.id) OR (f.user_id = u.id AND f.friend_id = :me2)
    WHERE u.id <> :me3
      AND COALESCE(u.status, 'active') = 'active'
      AND u.username LIKE :q
    ORDER BY (u.username = :exact) DESC, u.username ASC
    LIMIT 10");
$st->execute([':me1' => $userId, ':me2' => $userId, ':me3' => $userId, ':q' => $like, ':exact' => $q]);

$out = [];
foreach ($st->fetchAll() as $r) {
    if ($r['status'] === 'accepted')                         $rel = 'friends';
    elseif ($r['status'] === 'pending' && (int)$r['requester_id'] === $userId) $rel = 'sent';
    elseif ($r['status'] === 'pending')                      $rel = 'received';
    else                                                     $rel = 'none';
    $out[] = [
        'id'            => (int)$r['id'],
        'username'      => $r['username'],
        'full_name'     => $r['full_name'],
        'relation'      => $rel,
        'friendship_id' => $r['friendship_id'] ? (int)$r['friendship_id'] : null,
    ];
}

jsonResponse(['success' => true, 'data' => $out]);
