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
$today = new DateTimeImmutable('today');

$timeframe = $_GET['timeframe'] ?? 'this_month';

switch ($timeframe) {
    case 'this_month':
        $start = $today->modify('first day of this month');
        $end = $start->modify('+1 month');
        break;

    case 'last_month':
        $start = $today->modify('first day of last month');
        $end = $today->modify('first day of this month');
        break;

    case 'this_year':
        $start = $today->setDate((int)$today->format('Y'), 1, 1);
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

    default:
        jsonResponse(['success' => false, 'message' => 'Khoảng thời gian không hợp lệ'], 400);
}

$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
     FROM transactions
     WHERE user_id = ?
       AND transaction_date >= ?
       AND transaction_date < ?"
);
$stmt->execute([
    $userId,
    $start->format('Y-m-d'),
    $end->format('Y-m-d')
]);

$row = $stmt->fetch();
$income = (float)$row['total_income'];
$expense = (float)$row['total_expense'];

$trendStart = $today->modify('first day of this month')->modify('-11 months');
$trendEnd = $today->modify('first day of next month');

$trendStmt = $pdo->prepare(
    "SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
     FROM transactions
     WHERE user_id = ?
       AND transaction_date >= ?
       AND transaction_date < ?
     GROUP BY month_key"
);
$trendStmt->execute([
    $userId,
    $trendStart->format('Y-m-d'),
    $trendEnd->format('Y-m-d')
]);

$rowsByMonth = [];
foreach ($trendStmt->fetchAll() as $trendRow) {
    $rowsByMonth[$trendRow['month_key']] = $trendRow;
}

$monthlyTrend = [];
for ($month = $trendStart; $month < $trendEnd; $month = $month->modify('+1 month')) {
    $key = $month->format('Y-m');
    $trendRow = $rowsByMonth[$key] ?? [];

    $monthlyTrend[] = [
        'month' => $month->format('m/Y'),
        'income' => (float)($trendRow['income'] ?? 0),
        'expense' => (float)($trendRow['expense'] ?? 0)
    ];
}

jsonResponse([
    'success' => true,
    'data' => [
        'total_income' => $income,
        'total_expense' => $expense,
        'net_balance' => $income - $expense,
        'saving_rate' => $income > 0
            ? round(($income - $expense) / $income * 100, 2)
            : 0,
        'monthly_trend' => $monthlyTrend
    ]
]);