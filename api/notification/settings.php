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

    if (!array_key_exists('daily_reminder', $data) || !array_key_exists('reminder_time', $data)) {
        jsonResponse([
            'success' => false,
            'message' => 'Cần gửi daily_reminder và reminder_time'
        ], 400);
    }

    $enabled = filter_var(
        $data['daily_reminder'],
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );

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