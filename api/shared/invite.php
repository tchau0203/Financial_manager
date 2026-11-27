<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Chỉ hỗ trợ POST'], 405);
}

require_once '../../config/db.php';

$userId = requireAuth();
$data = getJsonInput();
$pdo = getDbConnection();

/* Tham gia quỹ bằng mã mời */
$inviteCode = strtoupper(trim((string)($data['invite_code'] ?? '')));

if ($inviteCode !== '') {
    $stmt = $pdo->prepare('SELECT id, name FROM shared_spaces WHERE invite_code = ?');
    $stmt->execute([$inviteCode]);
    $space = $stmt->fetch();

    if (!$space) {
        jsonResponse(['success' => false, 'message' => 'Mã mời không đúng'], 404);
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO shared_members (space_id, user_id, role)
         VALUES (?, ?, "member")'
    );
    $stmt->execute([(int)$space['id'], $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'Bạn đã là thành viên của quỹ này'], 409);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Đã tham gia quỹ',
        'data' => ['space_id' => (int)$space['id']]
    ]);
}

/* Mời tài khoản bằng email */
$spaceId = filter_var($data['space_id'] ?? null, FILTER_VALIDATE_INT);
$email = trim((string)($data['email'] ?? ''));

if (!$spaceId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Cần space_id và email hợp lệ'], 400);
}

$stmt = $pdo->prepare(
    'SELECT s.id, s.name
     FROM shared_spaces s
     JOIN shared_members m ON m.space_id = s.id
     WHERE s.id = ? AND m.user_id = ? AND m.role = "owner"'
);
$stmt->execute([$spaceId, $userId]);
$space = $stmt->fetch();

if (!$space) {
    jsonResponse(['success' => false, 'message' => 'Chỉ chủ quỹ mới được mời thành viên'], 403);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$invitee = $stmt->fetch();

if (!$invitee) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản với email này'], 404);
}

$inviteeId = (int)$invitee['id'];

if ($inviteeId === $userId) {
    jsonResponse(['success' => false, 'message' => 'Bạn không thể tự mời chính mình'], 400);
}

$stmt = $pdo->prepare(
    'SELECT 1 FROM shared_members WHERE space_id = ? AND user_id = ?'
);
$stmt->execute([$spaceId, $inviteeId]);

if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Người này đã là thành viên của quỹ'], 409);
}

$stmt = $pdo->prepare(
    'SELECT id FROM shared_invitations
     WHERE space_id = ? AND invitee_id = ? AND status = "pending"
     LIMIT 1'
);
$stmt->execute([$spaceId, $inviteeId]);

if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Lời mời đang chờ phản hồi'], 409);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO shared_invitations (space_id, inviter_id, invitee_id)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$spaceId, $userId, $inviteeId]);
    $invitationId = (int)$pdo->lastInsertId();

    $message = 'Bạn được mời tham gia quỹ "' . $space['name'] . '".';

    $stmt = $pdo->prepare(
        'INSERT INTO notifications
            (user_id, title, message, type, is_read, shared_invitation_id)
         VALUES (?, ?, ?, "shared_invite", 0, ?)'
    );
    $stmt->execute([
        $inviteeId,
        'Lời mời tham gia quỹ',
        $message,
        $invitationId
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Đã gửi lời mời tham gia quỹ',
        'data' => ['invitation_id' => $invitationId]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[shared-invite] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Không gửi được lời mời'], 500);
}