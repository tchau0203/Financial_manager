<?php
// GET /api/dashboard/summary.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

$today = new DateTimeImmutable('today');
$startThisMonth = $today->modify('first day of this month');
$endThisMonth   = $startThisMonth->modify('+1 month');

$startLastMonth = $today->modify('first day of last month');
$endLastMonth   = $startThisMonth;

// 1. Thu chi tháng này
$stmtThis = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions
    WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?
");
$stmtThis->execute([$userId, $startThisMonth->format('Y-m-d'), $endThisMonth->format('Y-m-d')]);
$thisData = $stmtThis->fetch() ?: ['total_income' => 0, 'total_expense' => 0];

$income  = (float)$thisData['total_income'];
$expense = (float)$thisData['total_expense'];
$balance = $income - $expense;
$savingRate = $income > 0 ? round(($balance / $income) * 100, 1) : 0.0;

// 2. Thu chi tháng trước để so sánh (badges so sánh ↑ +12% / ↓ -5%)
$stmtLast = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions
    WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?
");
$stmtLast->execute([$userId, $startLastMonth->format('Y-m-d'), $endLastMonth->format('Y-m-d')]);
$lastData = $stmtLast->fetch() ?: ['total_income' => 0, 'total_expense' => 0];

$lastIncome  = (float)$lastData['total_income'];
$lastExpense = (float)$lastData['total_expense'];
$lastBalance = $lastIncome - $lastExpense;
$lastSavingRate = $lastIncome > 0 ? round(($lastBalance / $lastIncome) * 100, 1) : 0.0;

function calcPctChange(float $current, float $prev): float {
    if ($prev <= 0) return $current > 0 ? 100.0 : 0.0;
    return round((($current - $prev) / $prev) * 100, 1);
}

$incomeChange  = calcPctChange($income, $lastIncome);
$expenseChange = calcPctChange($expense, $lastExpense);
$balanceChange = calcPctChange($balance, $lastBalance);
$savingChange  = round($savingRate - $lastSavingRate, 1);

// 3. Xu hướng 12 tháng gần nhất (Bar Chart)
$trendStart = $today->modify('first day of this month')->modify('-11 months');
$trendEnd   = $today->modify('first day of next month');

$trendStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
    FROM transactions
    WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?
    GROUP BY month_key
");
$trendStmt->execute([$userId, $trendStart->format('Y-m-d'), $trendEnd->format('Y-m-d')]);
$trendRows = $trendStmt->fetchAll();

$trendMap = [];
foreach ($trendRows as $r) {
    $trendMap[$r['month_key']] = $r;
}

$monthlyTrend = [];
for ($m = $trendStart; $m < $trendEnd; $m = $m->modify('+1 month')) {
    $k = $m->format('Y-m');
    $tr = $trendMap[$k] ?? [];
    $monthlyTrend[] = [
        'month'   => $m->format('m/Y'),
        'income'  => (float)($tr['income'] ?? 0),
        'expense' => (float)($tr['expense'] ?? 0)
    ];
}

// 4. Cơ cấu chi tiêu tháng này (Doughnut Chart)
$catStmt = $pdo->prepare("
    SELECT c.id, c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) AS total_amount
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date >= ? AND t.transaction_date < ?
    GROUP BY c.id, c.name, c.icon, c.color
    HAVING total_amount > 0
    ORDER BY total_amount DESC
");
$catStmt->execute([$userId, $startThisMonth->format('Y-m-d'), $endThisMonth->format('Y-m-d')]);
$categories = $catStmt->fetchAll();

$palette = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#14b8a6', '#f43f5e'];
$catStructure = [];
$catIdx = 0;
foreach ($categories as $cat) {
    $amt = (float)$cat['total_amount'];
    $catStructure[] = [
        'id'         => (int)$cat['id'],
        'name'       => $cat['name'],
        'icon'       => $cat['icon'] ?: 'fa-tag',
        'color'      => $cat['color'] ?: ($palette[$catIdx % count($palette)]),
        'amount'     => $amt,
        'percentage' => $expense > 0 ? round(($amt / $expense) * 100, 1) : 0
    ];
    $catIdx++;
}

// 5. Tiến độ ngân sách tháng này
$budgetMonth = (int)$startThisMonth->format('n');
$budgetYear  = (int)$startThisMonth->format('Y');

$bStmt = $pdo->prepare("
    SELECT b.id, b.category_id, b.amount AS limit_amount,
           c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
           COALESCE(SUM(t.amount), 0) AS spent_amount
    FROM budgets b
    JOIN categories c ON c.id = b.category_id
    LEFT JOIN transactions t ON t.category_id = b.category_id
                            AND t.user_id = b.user_id
                            AND t.type = 'expense'
                            AND t.transaction_date >= ? AND t.transaction_date < ?
    WHERE b.user_id = ? AND b.month = ? AND b.year = ?
    GROUP BY b.id, b.category_id, b.amount, c.name, c.icon, c.color
    ORDER BY spent_amount DESC
");
$bStmt->execute([
    $startThisMonth->format('Y-m-d'),
    $endThisMonth->format('Y-m-d'),
    $userId,
    $budgetMonth,
    $budgetYear
]);
$budgetRows = $bStmt->fetchAll();

$budgets = [];
foreach ($budgetRows as $b) {
    $limit = (float)$b['limit_amount'];
    $spent = (float)$b['spent_amount'];
    $pct   = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;
    $status = 'normal';
    if ($pct > 100) $status = 'exceeded';
    elseif ($pct > 85) $status = 'danger';
    elseif ($pct >= 60) $status = 'warning';

    $budgets[] = [
        'id'            => (int)$b['id'],
        'category_id'   => (int)$b['category_id'],
        'category_name' => $b['category_name'],
        'category_icon' => $b['category_icon'] ?: 'fa-wallet',
        'category_color'=> $b['category_color'] ?: '#6366f1',
        'limit_amount'  => $limit,
        'spent_amount'  => $spent,
        'percentage'    => $pct,
        'status'        => $status
    ];
}

// 6. Giao dịch gần đây (5 giao dịch gần nhất)
$txStmt = $pdo->prepare("
    SELECT t.id, t.type, t.amount, t.transaction_date, t.note,
           c.name AS category_name, c.icon AS category_icon, c.color AS category_color
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 5
");
$txStmt->execute([$userId]);
$recentTxRows = $txStmt->fetchAll();

$recentTx = [];
foreach ($recentTxRows as $t) {
    $recentTx[] = [
        'id'             => (int)$t['id'],
        'type'           => $t['type'],
        'amount'         => (float)$t['amount'],
        'date'           => $t['transaction_date'],
        'note'           => $t['note'] ?: ($t['category_name'] ?: 'Giao dịch'),
        'category_name'  => $t['category_name'] ?: ($t['type'] === 'income' ? 'Thu nhập khác' : 'Chi tiêu khác'),
        'category_icon'  => $t['category_icon'] ?: ($t['type'] === 'income' ? 'fa-arrow-down' : 'fa-receipt'),
        'category_color' => $t['category_color'] ?: ($t['type'] === 'income' ? '#10b981' : '#f43f5e')
    ];
}

jsonResponse([
    'success' => true,
    'data' => [
        'kpi' => [
            'total_income'    => $income,
            'total_expense'   => $expense,
            'net_balance'     => $balance,
            'saving_rate'     => $savingRate,
            'comparison'      => [
                'income_change_pct'  => $incomeChange,
                'expense_change_pct' => $expenseChange,
                'balance_change_pct' => $balanceChange,
                'saving_change_pct'  => $savingChange
            ]
        ],
        'monthly_trend'       => $monthlyTrend,
        'category_structure'  => $catStructure,
        'budgets'             => $budgets,
        'recent_transactions' => $recentTx
    ]
]);
