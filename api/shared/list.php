<?php
// GET /api/shared/list.php            -> danh sách quỹ của tôi (kèm số thành viên, tổng thu/chi)
// GET /api/shared/list.php?space_id=1 -> chi tiết 1 quỹ + danh sách thành viên
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

$spaceId = filter_var($_GET['space_id'] ?? null, FILTER_VALIDATE_INT);

if ($spaceId) {
    $st = $pdo->prepare('
        SELECT s.id, s.name, s.owner_id, m.role,
               IF(m.role = "owner", s.invite_code, NULL) AS invite_code
        FROM shared_spaces s JOIN shared_members m ON m.space_id = s.id AND m.user_id = ?
        WHERE s.id = ?');
    $st->execute([$userId, $spaceId]);
    $space = $st->fetch();
    if (!$space) jsonResponse(['success' => false, 'message' => 'Bạn không thuộc quỹ này'], 403);

    $st = $pdo->prepare('
        SELECT u.id AS user_id, u.username, u.full_name, m.role, m.joined_at
        FROM shared_members m JOIN users u ON u.id = m.user_id
        WHERE m.space_id = ? ORDER BY m.role = "owner" DESC, m.joined_at');
    $st->execute([$spaceId]);
    $space['members'] = $st->fetchAll();
    jsonResponse(['success' => true, 'data' => $space]);
}

$st = $pdo->prepare('
    SELECT s.id, s.name, m.role, s.created_at,
           (SELECT COUNT(*) FROM shared_members WHERE space_id = s.id) AS member_count,
           COALESCE((SELECT SUM(amount) FROM shared_transactions WHERE space_id = s.id AND type = "income"), 0) AS total_income,
           COALESCE((SELECT SUM(amount) FROM shared_transactions WHERE space_id = s.id AND type = "expense"), 0) AS total_expense
    FROM shared_spaces s JOIN shared_members m ON m.space_id = s.id AND m.user_id = ?
    ORDER BY s.created_at DESC');
$st->execute([$userId]);
jsonResponse(['success' => true, 'data' => $st->fetchAll()]);
