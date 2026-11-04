<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập"]);
    exit;
}

$type = $_GET['type'] ?? 'expense';
if (!in_array($type, ['income', 'expense'], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Loại giao dịch không hợp lệ"]);
    exit;
}

$start = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$end = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');
$stmt = $pdo->prepare(
    "SELECT c.id AS category_id, c.name AS category_name, c.icon, c.color,
            SUM(t.amount) AS total_amount
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.type = ? AND t.transaction_date >= ? AND t.transaction_date < ?
     GROUP BY c.id, c.name, c.icon, c.color
     ORDER BY total_amount DESC"
);
$stmt->execute([$_SESSION['user_id'], $type, $start, $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = array_sum(array_map(static function ($row) {
    return (float)$row['total_amount'];
}, $rows));

foreach ($rows as &$row) {
    $row['total_amount'] = (float)$row['total_amount'];
    $row['percentage'] = $total > 0 ? round($row['total_amount'] / $total * 100, 2) : 0;
}
unset($row);

echo json_encode(["success" => true, "data" => $rows]);
