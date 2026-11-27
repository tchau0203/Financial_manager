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

$invitationId = filter_var($data['invitation_id'] ?? null, FILTER_VALIDATE_INT);
$action = $data['action'] ?? '';

if (!$invitationId || !in_array($action, ['accept', 'reject'], true)) {
    jsonResponse(['success' => false, 'message' => 'Lời mời hoặc hành động không hợp lệ'], 400);
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, space_id, status
         FROM shared_invitations
         WHERE id = ? AND invitee_id = ?
         FOR UPDATE'
    );
    $stmt->execute([$invitationId, $userId]);
    $invitation = $stmt->fetch();

    if (!$invitation) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy lời mời'], 404);
    }

    if ($invitation['status'] !== 'pending') {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Lời mời đã được xử lý'], 409);
    }

    if ($action === 'accept') {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO shared_members (space_id, user_id, role)
             VALUES (?, ?, "member")'
        );
        $stmt->execute([(int)$invitation['space_id'], $userId]);
        $newStatus = 'accepted';
        $message = 'Đã tham gia quỹ';
    } else {
        $newStatus = 'rejected';
        $message = 'Đã từ chối lời mời';
    }

    $stmt = $pdo->prepare(
        'UPDATE shared_invitations
         SET status = ?, responded_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$newStatus, $invitationId]);

    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE shared_invitation_id = ? AND user_id = ?'
    );
    $stmt->execute([$invitationId, $userId]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => [
            'invitation_id' => (int)$invitationId,
            'status' => $newStatus,
            'space_id' => (int)$invitation['space_id']
        ]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[shared-members] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Không xử lý được lời mời'], 500);
}