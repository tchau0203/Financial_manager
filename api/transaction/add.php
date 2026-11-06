<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$data = getJsonInput();
$type = $data['type'] ?? '';
$categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
$amount = $data['amount'] ?? null;
$date = $data['transaction_date'] ?? '';
$paymentMethod = is_string($data['payment_method'] ?? null) ? trim($data['payment_method']) : 'Tiền mặt';
$note = is_string($data['note'] ?? null) ? trim($data['note']) : '';
$parsedDate = is_string($date) ? DateTime::createFromFormat('!Y-m-d', $date) : false;

if (!in_array($type, ['income', 'expense'], true) || !$categoryId || !is_numeric($amount) || !is_finite((float)$amount) || (float)$amount <= 0 || !$parsedDate || $parsedDate->format('Y-m-d') !== $date || strlen($paymentMethod) > 50 || strlen($note) > 500) {
    jsonResponse(['success' => false, 'message' => 'Thông tin giao dịch không hợp lệ'], 400);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND type = ? AND (user_id IS NULL OR user_id = ?)');
$stmt->execute([$categoryId, $type, $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Danh mục không hợp lệ'], 400);
}

$stmt = $pdo->prepare('INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, payment_method, note) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $userId,
    $categoryId,
    $type,
    $amount,
    $date,
    $paymentMethod,
    $note
]);

jsonResponse([
    'success' => true,
    'message' => 'Đã thêm giao dịch thành công',
    'data'    => ['id' => (int)$pdo->lastInsertId()]
], 201);
