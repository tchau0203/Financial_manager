<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập"]);
    exit;
}

$conditions = ["t.user_id = ?"];
$params = [$_SESSION['user_id']];

foreach (['type', 'payment_method'] as $field) {
    if (isset($_GET[$field]) && $_GET[$field] !== '') {
        $conditions[] = "t.$field = ?";
        $params[] = $_GET[$field];
    }
}

if (isset($_GET['category_id']) && filter_var($_GET['category_id'], FILTER_VALIDATE_INT) !== false) {
    $conditions[] = "t.category_id = ?";
    $params[] = (int)$_GET['category_id'];
}
if (!empty($_GET['from_date'])) {
    $conditions[] = "t.transaction_date >= ?";
    $params[] = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
    $conditions[] = "t.transaction_date <= ?";
    $params[] = $_GET['to_date'];
}
if (!empty($_GET['month']) && !empty($_GET['year'])) {
    $conditions[] = "MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?";
    $params[] = (int)$_GET['month'];
    $params[] = (int)$_GET['year'];
}
if (!empty($_GET['search'])) {
    $conditions[] = "(t.note LIKE ? OR c.name LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

$where = implode(' AND ', $conditions);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t JOIN categories c ON c.id = t.category_id WHERE $where");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$listStmt = $pdo->prepare(
    "SELECT t.id, t.type, t.category_id, t.amount, t.transaction_date, t.payment_method, t.note,
            c.name AS category_name, c.icon, c.color
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE $where
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT $limit OFFSET $offset"
);
$listStmt->execute($params);
$transactions = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$totalsStmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE $where"
);
$totalsStmt->execute($params);
$totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "data" => [
        "transactions" => $transactions,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total_records" => $totalRecords,
            "total_pages" => (int)ceil($totalRecords / $limit)
        ],
        "summary" => [
            "total_income" => (float)$totals['total_income'],
            "total_expense" => (float)$totals['total_expense'],
            "net_balance" => (float)$totals['total_income'] - (float)$totals['total_expense']
        ]
    ]
]);
