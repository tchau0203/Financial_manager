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

$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
$year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);

if (!$month || $month < 1 || $month > 12 || !$year || $year < 2000 || $year > 2100) {
    jsonResponse(['success' => false, 'message' => 'Tháng hoặc năm không hợp lệ'], 400);
}

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = (new DateTimeImmutable($startDate))->modify('+1 month')->format('Y-m-d');

$sql = "
    SELECT
        b.id,
        b.category_id,
        b.amount,
        b.month,
        b.year,
        b.alert_threshold,
        c.name AS category_name,
        COALESCE(SUM(t.amount), 0) AS spent_amount
    FROM budgets b
    JOIN categories c ON c.id = b.category_id
    LEFT JOIN transactions t
        ON t.user_id = b.user_id
        AND t.category_id = b.category_id
        AND t.type = 'expense'
        AND t.transaction_date >= ?
        AND t.transaction_date < ?
    WHERE b.user_id = ?
        AND b.month = ?
        AND b.year = ?
    GROUP BY b.id, b.category_id, b.amount, b.month, b.year,
             b.alert_threshold, c.name
    ORDER BY c.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate, $endDate, $userId, $month, $year]);
$budgets = $stmt->fetchAll();

foreach ($budgets as &$budget) {
    $amount = (float)$budget['amount'];
    $spent = (float)$budget['spent_amount'];
    $threshold = (int)$budget['alert_threshold'];

    $budget['amount'] = $amount;
    $budget['spent_amount'] = $spent;
    $budget['remaining_amount'] = $amount - $spent;
    $budget['percentage'] = $amount > 0
        ? round(($spent / $amount) * 100, 2)
        : 0;

    if ($budget['percentage'] >= 100) {
        $budget['status'] = 'exceeded';
    } elseif ($budget['percentage'] >= $threshold) {
        $budget['status'] = 'warning';
    } else {
        $budget['status'] = 'normal';
    }
}
unset($budget);

jsonResponse(['success' => true, 'data' => $budgets]);