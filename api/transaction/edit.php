<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$data          = getJsonInput();
$id            = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$type          = $data['type'] ?? '';
$categoryId    = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
$amount        = $data['amount'] ?? null;
$date          = $data['transaction_date'] ?? '';
$paymentMethod = is_string($data['payment_method'] ?? null) ? trim($data['payment_method']) : 'Tiền mặt';
$note          = is_string($data['note'] ?? null) ? trim($data['note']) : '';
$parsedDate    = is_string($date) ? DateTime::createFromFormat('!Y-m-d', $date) : false;

if (!$id || !in_array($type, ['income', 'expense'], true) || !$categoryId
    || !is_numeric($amount) || !is_finite((float)$amount) || (float)$amount <= 0
    || !$parsedDate || $parsedDate->format('Y-m-d') !== $date
    || strlen($paymentMethod) > 50 || strlen($note) > 500) {
    jsonResponse(['success' => false, 'message' => 'Thông tin giao dịch không hợp lệ'], 400);
}

$pdo = getDbConnection();

// Kiểm tra quyền sở hữu giao dịch
$stmt = $pdo->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy giao dịch hoặc bạn không có quyền chỉnh sửa'], 404);
}

// Kiểm tra danh mục hợp lệ
$stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND type = ? AND (user_id IS NULL OR user_id = ?)');
$stmt->execute([$categoryId, $type, $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Danh mục không hợp lệ'], 400);
}

$stmt = $pdo->prepare(
    'UPDATE transactions SET type = ?, category_id = ?, amount = ?, transaction_date = ?, payment_method = ?, note = ? WHERE id = ? AND user_id = ?'
);
$stmt->execute([$type, $categoryId, $amount, $date, $paymentMethod, $note, $id, $userId]);

// Kiểm tra ngân sách nếu là chi tiêu
if ($type === 'expense') {
    $txMonth = (int)$parsedDate->format('n');
    $txYear  = (int)$parsedDate->format('Y');
    checkBudgetAlerts($userId, $pdo, $txMonth, $txYear);
}

jsonResponse([
    'success' => true,
    'message' => 'Đã cập nhật giao dịch thành công'
]);
