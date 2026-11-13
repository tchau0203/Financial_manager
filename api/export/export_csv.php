<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

$conditions = ['t.user_id = ?'];
$params     = [$userId];

if (!empty($_GET['from_date'])) {
    $conditions[] = 't.transaction_date >= ?';
    $params[]     = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
    $conditions[] = 't.transaction_date <= ?';
    $params[]     = $_GET['to_date'];
}
if (!empty($_GET['type']) && in_array($_GET['type'], ['income', 'expense'], true)) {
    $conditions[] = 't.type = ?';
    $params[]     = $_GET['type'];
}
if (isset($_GET['category_id']) && filter_var($_GET['category_id'], FILTER_VALIDATE_INT) !== false) {
    $conditions[] = 't.category_id = ?';
    $params[]     = (int)$_GET['category_id'];
}

$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    "SELECT t.id, t.type, c.name AS category_name, t.amount, t.transaction_date, t.payment_method, t.note
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE $where
     ORDER BY t.transaction_date DESC, t.id DESC"
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$filename = 'giao_dich_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');

// UTF-8 BOM để Excel hiển thị đúng tiếng Việt
fwrite($output, "\xEF\xBB\xBF");

// Header
fputcsv($output, ['ID', 'Loại', 'Danh mục', 'Số tiền', 'Ngày giao dịch', 'Phương thức', 'Ghi chú']);

foreach ($transactions as $row) {
    fputcsv($output, [
        $row['id'],
        $row['type'] === 'income' ? 'Thu nhập' : 'Chi tiêu',
        $row['category_name'],
        $row['amount'],
        $row['transaction_date'],
        $row['payment_method'],
        $row['note']
    ]);
}

fclose($output);
exit;
