<?php
// POST /api/shared/invite.php
//  - Mời bằng email (chỉ owner):  {space_id, email}
//  - Tham gia bằng mã mời:        {invite_code}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$data = getJsonInput();
$pdo = getDbConnection();

$addMember = function ($spaceId, $uid) use ($pdo) {
    $st = $pdo->prepare('SELECT 1 FROM shared_members WHERE space_id = ? AND user_id = ?');
    $st->execute([$spaceId, $uid]);
    if ($st->fetch()) return false;
    $pdo->prepare('INSERT INTO shared_members (space_id, user_id, role) VALUES (?, ?, "member")')->execute([$spaceId, $uid]);
    return true;
};

// Tham gia bằng mã mời
$code = is_string($data['invite_code'] ?? null) ? strtoupper(trim($data['invite_code'])) : '';
if ($code !== '') {
    $st = $pdo->prepare('SELECT id, name FROM shared_spaces WHERE invite_code = ?');
    $st->execute([$code]);
    $space = $st->fetch();
    if (!$space) jsonResponse(['success' => false, 'message' => 'Mã mời không đúng'], 404);
    if (!$addMember($space['id'], $userId)) {
        jsonResponse(['success' => false, 'message' => 'Bạn đã là thành viên của quỹ này'], 409);
    }
    jsonResponse(['success' => true, 'message' => 'Đã tham gia quỹ "' . $space['name'] . '"', 'data' => ['space_id' => (int)$space['id']]]);
}

// Mời bằng email
$spaceId = filter_var($data['space_id'] ?? null, FILTER_VALIDATE_INT);
$email = is_string($data['email'] ?? null) ? trim($data['email']) : '';
if (!$spaceId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Cần invite_code hoặc (space_id + email hợp lệ)'], 400);
}

$st = $pdo->prepare('SELECT role FROM shared_members WHERE space_id = ? AND user_id = ?');
$st->execute([$spaceId, $userId]);
$me = $st->fetch();
if (!$me || $me['role'] !== 'owner') {
    jsonResponse(['success' => false, 'message' => 'Chỉ chủ quỹ mới được mời thành viên'], 403);
}

$st = $pdo->prepare('SELECT id, full_name, username FROM users WHERE email = ?');
$st->execute([$email]);
$target = $st->fetch();
if (!$target) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản với email này. Hãy gửi mã mời cho họ.'], 404);
}
if (!$addMember($spaceId, (int)$target['id'])) {
    jsonResponse(['success' => false, 'message' => 'Người này đã là thành viên'], 409);
}
jsonResponse(['success' => true, 'message' => 'Đã thêm ' . ($target['full_name'] ?: $target['username']) . ' vào quỹ']);
