<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$data = is_array($data) ? $data : [];
$type = $data['type'] ?? '';
$categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
$amount = $data['amount'] ?? null;
$date = $data['transaction_date'] ?? '';
$paymentMethod = is_string($data['payment_method'] ?? null) ? trim($data['payment_method']) : '';
$note = is_string($data['note'] ?? null) ? trim($data['note']) : '';
$parsedDate = is_string($date) ? DateTime::createFromFormat('!Y-m-d', $date) : false;

if (!in_array($type, ['income', 'expense'], true) || !$categoryId || !is_numeric($amount) || !is_finite((float)$amount) || (float)$amount <= 0 || !$parsedDate || $parsedDate->format('Y-m-d') !== $date || strlen($paymentMethod) > 50 || strlen($note) > 500) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Thông tin giao dịch không hợp lệ"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND type = ? AND (user_id IS NULL OR user_id = ?)");
$stmt->execute([$categoryId, $type, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Danh mục không hợp lệ"]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, payment_method, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $_SESSION['user_id'],
    $categoryId,
    $type,
    $amount,
    $date,
    $paymentMethod,
    $note
]);

http_response_code(201);
echo json_encode(["success" => true, "message" => "Đã thêm giao dịch", "data" => ["id" => $pdo->lastInsertId()]]);
