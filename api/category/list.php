<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

$stmt = $pdo->prepare(
    'SELECT id, title, message, type, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20'
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);
$countStmt->execute([$userId]);

jsonResponse([
    'success' => true,
    'data' => [
        'notifications' => $notifications,
        'unread_count' => (int)$countStmt->fetchColumn()
    ]
]);