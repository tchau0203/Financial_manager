<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('GET');

$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Bài của chính mình + bài public của bạn bè đã accepted
$where = "(p.user_id = :me1 OR (p.is_public = 1 AND p.user_id IN (
            SELECT CASE WHEN f.user_id = :me2 THEN f.friend_id ELSE f.user_id END
            FROM friendships f
            WHERE f.status = 'accepted' AND (f.user_id = :me3 OR f.friend_id = :me4)
         )))";
$bind = [':me1' => $userId, ':me2' => $userId, ':me3' => $userId, ':me4' => $userId];

// Bộ lọc: all (mặc định) | me (chỉ bài của tôi) | <id bạn bè>
$filter = (string)($_GET['filter'] ?? 'all');
if ($filter === 'me') {
    $where = 'p.user_id = :me1';
    $bind  = [':me1' => $userId];
} elseif (ctype_digit($filter) && (int)$filter > 0) {
    $fid = (int)$filter;
    if ($fid !== $userId && !areFriends($pdo, $userId, $fid)) {
        jsonResponse(['success' => false, 'message' => 'Người này chưa là bạn bè của bạn'], 403);
    }
    $where = 'p.user_id = :f1 AND (p.user_id = :me1 OR p.is_public = 1)';
    $bind  = [':f1' => $fid, ':me1' => $userId];
}

$sql = "SELECT p.id, p.user_id, p.image_path, p.caption, p.amount, p.type, p.is_public, p.created_at,
               u.username, u.full_name, u.avatar_url,
               c.name AS category_name, c.icon AS category_icon, c.color AS category_color
        FROM posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $where
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT " . ($limit + 1) . " OFFSET " . $offset;   // lấy dư 1 dòng để biết còn trang sau

try {
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $rows = $st->fetchAll();
} catch (Throwable $e) {
    // DB cũ chưa có cột avatar_url -> thử lại không có cột đó
    $sql2 = str_replace('u.avatar_url,', 'NULL AS avatar_url,', $sql);
    $st = $pdo->prepare($sql2);
    $st->execute($bind);
    $rows = $st->fetchAll();
}

$hasMore = count($rows) > $limit;
if ($hasMore) array_pop($rows);

// Reactions của các bài trong trang này
$reactions = [];   // post_id => emoji => ['count'=>n,'mine'=>bool]
if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $rs  = $pdo->prepare("SELECT post_id, emoji, COUNT(*) AS cnt, MAX(user_id = ?) AS mine
                          FROM post_reactions WHERE post_id IN ($in) GROUP BY post_id, emoji");
    $rs->execute(array_merge([$userId], $ids));
    foreach ($rs->fetchAll() as $r) {
        $reactions[(int)$r['post_id']][$r['emoji']] = ['count' => (int)$r['cnt'], 'mine' => (bool)$r['mine']];
    }
}

$posts = [];
foreach ($rows as $r) {
    $pid = (int)$r['id'];
    $rx = [];
    foreach (FEED_EMOJIS as $e) {
        $rx[] = [
            'emoji' => $e,
            'count' => $reactions[$pid][$e]['count'] ?? 0,
            'mine'  => $reactions[$pid][$e]['mine'] ?? false,
        ];
    }
    $posts[] = [
        'id'             => $pid,
        'user_id'        => (int)$r['user_id'],
        'is_mine'        => (int)$r['user_id'] === $userId,
        'username'       => $r['username'],
        'full_name'      => $r['full_name'] ?: $r['username'],
        'avatar_url'     => $r['avatar_url'],
        'image_path'     => $r['image_path'],
        'caption'        => $r['caption'],
        'amount'         => (float)$r['amount'],
        'type'           => $r['type'],
        'is_public'      => (int)$r['is_public'],
        'category_name'  => $r['category_name'],
        'category_icon'  => $r['category_icon'],
        'category_color' => $r['category_color'],
        'created_at'     => $r['created_at'],
        'reactions'      => $rx,
    ];
}

jsonResponse([
    'success'    => true,
    'data'       => $posts,
    'pagination' => ['page' => $page, 'limit' => $limit, 'has_more' => $hasMore],
]);
