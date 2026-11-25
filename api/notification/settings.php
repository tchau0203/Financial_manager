<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/db.php';

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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT daily_reminder, reminder_time FROM users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();

    if (!$settings) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'daily_reminder' => (int)$settings['daily_reminder'],
            'reminder_time' => substr((string)$settings['reminder_time'], 0, 5)
        ]
    ]);
}

if ($method === 'POST') {
    $data = getJsonInput();

    // Hỗ trợ cả 2 tên biến: daily_reminder (từ Nam) và reminder_enabled (từ account.js)
    $enabledRaw = $data['daily_reminder'] ?? $data['reminder_enabled'] ?? null;
    $time = trim((string)($data['reminder_time'] ?? ''));

    if ($enabledRaw === null || $time === '') {
        jsonResponse([
            'success' => false,
            'message' => 'Cần gửi daily_reminder (hoặc reminder_enabled) và reminder_time'
        ], 400);
    }

    $enabled = filter_var(
        $enabledRaw,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );
    if ($enabled === null && is_numeric($enabledRaw)) {
        $enabled = (int)$enabledRaw > 0;
    }

    $time = trim((string)$data['reminder_time']);

    if ($enabled === null) {
        jsonResponse(['success' => false, 'message' => 'Trạng thái nhắc nhở không hợp lệ'], 400);
    }

    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        jsonResponse(['success' => false, 'message' => 'Giờ nhắc phải theo dạng HH:MM'], 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET daily_reminder = ?, reminder_time = ? WHERE id = ?'
    );
    $stmt->execute([$enabled ? 1 : 0, $time . ':00', $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã lưu cài đặt nhắc nhở',
        'data' => [
            'daily_reminder' => $enabled ? 1 : 0,
            'reminder_time' => $time
        ]
    ]);
}

jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);