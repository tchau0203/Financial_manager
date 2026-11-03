<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập"]);
    exit;
}

$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
$year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
if (!$month || $month < 1 || $month > 12 || !$year || $year < 2000 || $year > 2100) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Tháng hoặc năm không hợp lệ"]);
    exit;
}

$start = sprintf('%04d-%02d-01', $year, $month);
$end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
$stmt = $pdo->prepare(
    "SELECT b.id, b.category_id, b.amount, b.alert_threshold,
            c.name AS category_name,
            COALESCE((
                SELECT SUM(t.amount)
                FROM transactions t
                WHERE t.user_id = b.user_id
                  AND t.type = 'expense'
                  AND t.transaction_date >= ?
                  AND t.transaction_date < ?
                  AND (b.category_id IS NULL OR t.category_id = b.category_id)
            ), 0) AS spent_amount
     FROM budgets b
     LEFT JOIN categories c ON c.id = b.category_id
     WHERE b.user_id = ? AND b.month = ? AND b.year = ?
     ORDER BY b.category_id IS NOT NULL, c.name"
);
$stmt->execute([$start, $end, $_SESSION['user_id'], $month, $year]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($budgets as &$budget) {
    $budget['amount'] = (float)$budget['amount'];
    $budget['spent_amount'] = (float)$budget['spent_amount'];
    $budget['remaining_amount'] = $budget['amount'] - $budget['spent_amount'];
    $budget['percentage'] = $budget['amount'] > 0
        ? round($budget['spent_amount'] / $budget['amount'] * 100, 2)
        : 0;
    $budget['status'] = $budget['percentage'] >= 100
        ? 'exceeded'
        : ($budget['percentage'] >= (int)$budget['alert_threshold'] ? 'warning' : 'normal');
}
unset($budget);

echo json_encode(["success" => true, "data" => $budgets]);
