<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
$year  = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
if (!$month || $month < 1 || $month > 12 || !$year || $year < 2000 || $year > 2100) {
    jsonResponse(['success' => false, 'message' => 'Tháng hoặc năm không hợp lệ'], 400);
}

$start = sprintf('%04d-%02d-01', $year, $month);
$end   = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');

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
$stmt->execute([$start, $end, $userId, $month, $year]);
$budgets = $stmt->fetchAll();

foreach ($budgets as &$budget) {
    $budget['amount']           = (float)$budget['amount'];
    $budget['spent_amount']     = (float)$budget['spent_amount'];
    $budget['remaining_amount'] = $budget['amount'] - $budget['spent_amount'];
    $budget['percentage']       = $budget['amount'] > 0
        ? round($budget['spent_amount'] / $budget['amount'] * 100, 2)
        : 0;
    $budget['status']           = $budget['percentage'] >= 100
        ? 'exceeded'
        : ($budget['percentage'] >= (int)$budget['alert_threshold'] ? 'warning' : 'normal');
}
unset($budget);

jsonResponse(['success' => true, 'data' => $budgets]);
