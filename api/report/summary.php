<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

$now = new DateTimeImmutable('today');
$timeframe = $_GET['timeframe'] ?? 'this_month';
switch ($timeframe) {
    case 'last_month':
        $start = $now->modify('first day of last month');
        $end = $now->modify('first day of this month');
        break;
    case 'this_year':
        $start = $now->setDate((int)$now->format('Y'), 1, 1);
        $end = $start->modify('+1 year');
        break;
    case 'custom':
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['from_date'] ?? '');
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['to_date'] ?? '');
        if (!$from || !$to || $from > $to) {
            jsonResponse(['success' => false, 'message' => 'Khoảng ngày không hợp lệ'], 400);
        }
        $start = $from;
        $end = $to->modify('+1 day');
        break;
    case 'this_month':
        $start = $now->modify('first day of this month');
        $end = $start->modify('+1 month');
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Khoảng thời gian không hợp lệ'], 400);
}

$summaryStmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
     FROM transactions
     WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?"
);
$summaryStmt->execute([
    $userId,
    $start->format('Y-m-d'),
    $end->format('Y-m-d')
]);
$summary = $summaryStmt->fetch();
$income = (float)($summary['total_income'] ?? 0);
$expense = (float)($summary['total_expense'] ?? 0);

$trendStart = $now->modify('first day of this month')->modify('-11 months');
$trendEnd = $now->modify('first day of this month')->modify('+1 month');
$trendStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
     FROM transactions
     WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?
     GROUP BY month_key"
);
$trendStmt->execute([
    $userId,
    $trendStart->format('Y-m-d'),
    $trendEnd->format('Y-m-d')
]);
$trendRows = [];
foreach ($trendStmt->fetchAll() as $row) {
    $trendRows[$row['month_key']] = $row;
}

$monthlyTrend = [];
for ($month = $trendStart; $month < $trendEnd; $month = $month->modify('+1 month')) {
    $key = $month->format('Y-m');
    $row = $trendRows[$key] ?? [];
    $monthlyTrend[] = [
        'month'   => $month->format('m/Y'),
        'income'  => (float)($row['income'] ?? 0),
        'expense' => (float)($row['expense'] ?? 0)
    ];
}

jsonResponse([
    'success' => true,
    'data' => [
        'total_income'  => $income,
        'total_expense' => $expense,
        'net_balance'   => $income - $expense,
        'saving_rate'   => $income > 0 ? round(($income - $expense) / $income * 100, 2) : 0,
        'monthly_trend' => $monthlyTrend
    ]
]);
