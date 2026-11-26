<?php
/**
 * API Quản trị viên: Gửi thông báo toàn hệ thống (System Broadcast)
 * Phụ trách: Thái Châu (Lead)
 * - GET  /api/admin/broadcast.php -> Lấy lịch sử các đợt thông báo đã phát & danh sách người dùng
 * - POST /api/admin/broadcast.php -> Phát thông báo mới hoặc thu hồi thông báo
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$adminId = requireAdmin();
$pdo     = getDbConnection();
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 1. Danh sách người dùng để chọn gửi riêng lẻ
    $users = $pdo->query("SELECT id, username, full_name, email, status FROM users ORDER BY username ASC")->fetchAll();

    // 2. Lịch sử các thông báo gần đây (gom nhóm theo tiêu đề + nội dung + thời điểm phát theo phút)
    $historyStmt = $pdo->query("
        SELECT 
            MIN(id) AS sample_id,
            title,
            message,
            type,
            COUNT(*) AS recipient_count,
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) AS read_count,
            DATE_FORMAT(MIN(created_at), '%Y-%m-%d %H:%i:%s') AS sent_at
        FROM notifications
        GROUP BY title, message, type, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
        ORDER BY MIN(created_at) DESC
        LIMIT 30
    ");
    $history = $historyStmt->fetchAll();

    foreach ($history as &$h) {
        $h['sample_id']       = (int)$h['sample_id'];
        $h['recipient_count'] = (int)$h['recipient_count'];
        $h['read_count']      = (int)$h['read_count'];
    }
    unset($h);

    jsonResponse([
        'success' => true,
        'data' => [
            'users'   => $users,
            'history' => $history
        ]
    ]);
}

if ($method === 'POST') {
    $data   = getJsonInput();
    $action = trim((string)($data['action'] ?? 'send'));

    // Thu hồi / xóa một đợt thông báo
    if ($action === 'revoke') {
        $title = trim((string)($data['title'] ?? ''));
        $msg   = trim((string)($data['message'] ?? ''));
        if ($title === '') {
            jsonResponse(['error' => 'Thiếu tiêu đề thông báo cần thu hồi'], 400);
        }
        $del = $pdo->prepare('DELETE FROM notifications WHERE title = ? AND message = ?');
        $del->execute([$title, $msg]);
        $deletedCount = $del->rowCount();

        logAdminAction($pdo, $adminId, 'revoke_broadcast', $title, "Thu hồi {$deletedCount} thông báo đã gửi");

        jsonResponse([
            'success' => true,
            'message' => "Đã thu hồi {$deletedCount} thông báo khỏi hộp thư người dùng"
        ]);
    }

    // Phát thông báo mới
    $target  = trim((string)($data['target'] ?? 'all')); // 'all', 'active', hoặc ID cụ thể
    $title   = trim((string)($data['title'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    $type    = in_array($data['type'] ?? 'info', ['info', 'success', 'warning', 'danger'], true) ? $data['type'] : 'info';

    if ($title === '' || $message === '') {
        jsonResponse(['error' => 'Vui lòng nhập đầy đủ tiêu đề và nội dung thông báo'], 400);
    }

    $targetUsers = [];
    $targetDesc  = '';

    if ($target === 'all') {
        $targetUsers = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $targetDesc  = 'Toàn bộ tài khoản (' . count($targetUsers) . ' người)';
    } elseif ($target === 'active') {
        $targetUsers = $pdo->query("SELECT id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        $targetDesc  = 'Tài khoản đang hoạt động (' . count($targetUsers) . ' người)';
    } elseif (is_numeric($target) && (int)$target > 0) {
        $uStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([(int)$target]);
        $uRow = $uStmt->fetch();
        if (!$uRow) {
            jsonResponse(['error' => 'Không tìm thấy người dùng nhận thông báo'], 404);
        }
        $targetUsers = [(int)$uRow['id']];
        $targetDesc  = "Cá nhân @{$uRow['username']} (#{$uRow['id']})";
    } else {
        jsonResponse(['error' => 'Đối tượng nhận thông báo không hợp lệ'], 400);
    }

    if (empty($targetUsers)) {
        jsonResponse(['error' => 'Không có người dùng nào phù hợp để gửi thông báo'], 400);
    }

    $ins = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)');
    $sentCount = 0;
    foreach ($targetUsers as $uid) {
        $ins->execute([(int)$uid, $title, $message, $type]);
        $sentCount++;
    }

    logAdminAction($pdo, $adminId, 'broadcast_notification', $targetDesc, "Tiêu đề: {$title} (Loại: {$type})");

    jsonResponse([
        'success'    => true,
        'message'    => "Đã phát thông báo thành công tới {$sentCount} tài khoản!",
        'sent_count' => $sentCount
    ]);
}

jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
