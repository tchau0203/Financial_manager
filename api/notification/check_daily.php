<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Chỉ hỗ trợ GET'], 405);
}

$userId = requireAuth();
$pdo = getDbConnection();

// Tự động thêm cột daily_reminder và reminder_time nếu DB chưa có
try {
    $pdo->query("SELECT daily_reminder, reminder_time FROM users LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN daily_reminder TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN reminder_time TIME NOT NULL DEFAULT '21:00:00'");
    } catch (Throwable $ex) {}
}

$stmt = $pdo->prepare(
    'SELECT daily_reminder, reminder_time FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
}

if (!(int)$user['daily_reminder']) {
    jsonResponse(['success' => true, 'data' => ['status' => 'disabled']]);
}

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$today = $now->format('Y-m-d');
$reminderTime = substr((string)$user['reminder_time'], 0, 5);

if ($now->format('H:i') < $reminderTime) {
    jsonResponse(['success' => true, 'data' => ['status' => 'not_due']]);
}

$transactionStmt = $pdo->prepare(
    'SELECT id FROM transactions
     WHERE user_id = ? AND transaction_date = ?
     LIMIT 1'
);
$transactionStmt->execute([$userId, $today]);

if ($transactionStmt->fetch()) {
    jsonResponse(['success' => true, 'data' => ['status' => 'already_recorded']]);
}

$title = 'Nhắc ghi chép chi tiêu';
$message = 'Hôm nay bạn có khoản chi tiêu nào chưa ghi lại không? Hãy cập nhật ngay nhé!';

$existingStmt = $pdo->prepare(
    'SELECT id FROM notifications
     WHERE user_id = ?
       AND type = ?
       AND DATE(created_at) = ?
     LIMIT 1'
);
$existingStmt->execute([$userId, 'daily_reminder', $today]);

if ($existingStmt->fetch()) {
    jsonResponse(['success' => true, 'data' => ['status' => 'already_sent']]);
}

$insertStmt = $pdo->prepare(
    'INSERT INTO notifications (user_id, title, message, type, is_read)
     VALUES (?, ?, ?, ?, 0)'
);
$insertStmt->execute([$userId, $title, $message, 'daily_reminder']);

jsonResponse([
    'success' => true,
    'data' => [
        'status' => 'reminder_created',
        'notification' => [
            'id' => (int)$pdo->lastInsertId(),
            'title' => $title,
            'message' => $message,
            'type' => 'daily_reminder'
        ]
    ]
]);