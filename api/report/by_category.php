<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

$type = $_GET['type'] ?? 'expense';
if (!in_array($type, ['income', 'expense'], true)) {
    jsonResponse(['success' => false, 'message' => 'Loại giao dịch không hợp lệ'], 400);
}

$start = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$end   = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT c.id AS category_id, c.name AS category_name, c.icon, c.color,
            SUM(t.amount) AS total_amount
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.type = ? AND t.transaction_date >= ? AND t.transaction_date < ?
     GROUP BY c.id, c.name, c.icon, c.color
     ORDER BY total_amount DESC"
);
$stmt->execute([$userId, $type, $start, $end]);
$rows = $stmt->fetchAll();

$total = array_sum(array_map(static function ($row) {
    return (float)$row['total_amount'];
}, $rows));

foreach ($rows as &$row) {
    $row['total_amount'] = (float)$row['total_amount'];
    $row['percentage']   = $total > 0 ? round($row['total_amount'] / $total * 100, 2) : 0;
}
unset($row);

jsonResponse(['success' => true, 'data' => $rows]);
