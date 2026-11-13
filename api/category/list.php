<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

$type = $_GET['type'] ?? '';
$pdo = getDbConnection();

$sql = "
    SELECT c.*, COUNT(t.id) as transaction_count
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = :userId
    WHERE (c.user_id IS NULL OR c.user_id = :userId2)
";

$params = [
    ':userId' => $userId,
    ':userId2' => $userId
];

if (in_array($type, ['income', 'expense'])) {
    $sql .= " AND c.type = :type";
    $params[':type'] = $type;
}

$sql .= " GROUP BY c.id ORDER BY c.type, c.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $categories
]);
