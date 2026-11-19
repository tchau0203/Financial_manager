<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/db.php';

$userId = requireAuth();
$data = getJsonInput();

$categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
$amount = $data['amount'] ?? null;
$month = filter_var($data['month'] ?? null, FILTER_VALIDATE_INT);
$year = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);
$threshold = filter_var($data['alert_threshold'] ?? 80, FILTER_VALIDATE_INT);

if (
    !$categoryId ||
    !is_numeric($amount) ||
    !is_finite((float)$amount) ||
    (float)$amount <= 0 ||
    !$month || $month < 1 || $month > 12 ||
    !$year || $year < 2000 || $year > 2100
) {
    jsonResponse(['success' => false, 'message' => 'Thông tin ngân sách không hợp lệ'], 400);
}

if ($threshold === false || $threshold < 1 || $threshold > 100) {
    $threshold = 80;
}

$pdo = getDbConnection();

$categoryStmt = $pdo->prepare(
    "SELECT id
     FROM categories
     WHERE id = ?
       AND type = 'expense'
       AND (user_id IS NULL OR user_id = ?)"
);
$categoryStmt->execute([$categoryId, $userId]);

if (!$categoryStmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Danh mục chi tiêu không hợp lệ'], 400);
}

$stmt = $pdo->prepare(
    'INSERT INTO budgets (user_id, category_id, amount, month, year, alert_threshold)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        amount = VALUES(amount),
        alert_threshold = VALUES(alert_threshold)'
);
$stmt->execute([$userId, $categoryId, $amount, $month, $year, $threshold]);

checkBudgetAlerts($userId, $pdo, $month, $year);

jsonResponse([
    'success' => true,
    'message' => 'Đã thiết lập ngân sách thành công'
]);