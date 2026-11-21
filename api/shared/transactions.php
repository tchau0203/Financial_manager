<?php
// GET  /api/shared/transactions.php?space_id=1[&month=11&year=2026]  -> giao dịch quỹ + tên người chi
// POST /api/shared/transactions.php {space_id, type, category_id, amount, transaction_date, note}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

$data = $method === 'POST' ? getJsonInput() : $_GET;
$spaceId = filter_var($data['space_id'] ?? null, FILTER_VALIDATE_INT);
if (!$spaceId) jsonResponse(['success' => false, 'message' => 'Thiếu space_id'], 400);

$st = $pdo->prepare('SELECT 1 FROM shared_members WHERE space_id = ? AND user_id = ?');
$st->execute([$spaceId, $userId]);
if (!$st->fetch()) jsonResponse(['success' => false, 'message' => 'Bạn không thuộc quỹ này'], 403);

if ($method === 'POST') {
    $type = $data['type'] ?? '';
    $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
    $amount = $data['amount'] ?? null;
    $date = $data['transaction_date'] ?? '';
    $note = is_string($data['note'] ?? null) ? trim($data['note']) : '';
    $d = is_string($date) ? DateTime::createFromFormat('!Y-m-d', $date) : false;

    if (!in_array($type, ['income', 'expense'], true) || !$categoryId || !is_numeric($amount) || (float)$amount <= 0
        || !$d || $d->format('Y-m-d') !== $date || strlen($note) > 500) {
        jsonResponse(['success' => false, 'message' => 'Thông tin giao dịch không hợp lệ'], 400);
    }
    $st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND type = ? AND (user_id IS NULL OR user_id = ?)');
    $st->execute([$categoryId, $type, $userId]);
    if (!$st->fetch()) jsonResponse(['success' => false, 'message' => 'Danh mục không hợp lệ'], 400);

    $pdo->prepare('INSERT INTO shared_transactions (space_id, user_id, category_id, type, amount, transaction_date, note) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$spaceId, $userId, $categoryId, $type, $amount, $date, $note]);
    jsonResponse(['success' => true, 'message' => 'Đã thêm giao dịch vào quỹ', 'data' => ['id' => (int)$pdo->lastInsertId()]], 201);
}

// GET
$where = 'st.space_id = ?';
$params = [$spaceId];
$month = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
if ($month && $year) {
    $where .= ' AND MONTH(st.transaction_date) = ? AND YEAR(st.transaction_date) = ?';
    array_push($params, $month, $year);
}

$st = $pdo->prepare("
    SELECT st.id, st.type, st.amount, st.transaction_date, st.note, st.created_at,
           st.user_id, u.username, u.full_name AS user_name,
           c.id AS category_id, c.name AS category_name, c.icon, c.color
    FROM shared_transactions st
    JOIN users u ON u.id = st.user_id
    JOIN categories c ON c.id = st.category_id
    WHERE $where ORDER BY st.transaction_date DESC, st.id DESC");
$st->execute($params);
$rows = $st->fetchAll();

$income = 0; $expense = 0;
foreach ($rows as $r) {
    if ($r['type'] === 'income') $income += (float)$r['amount']; else $expense += (float)$r['amount'];
}

jsonResponse([
    'success' => true,
    'data' => $rows,
    'summary' => ['total_income' => $income, 'total_expense' => $expense, 'balance' => $income - $expense],
]);
