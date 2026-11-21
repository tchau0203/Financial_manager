<?php
// GET /api/backup/export.php -> tải file backup .json (có chữ ký HMAC-SHA256)
require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

function backupSecret() {
    $s = $_ENV['BACKUP_SECRET'] ?? getenv('BACKUP_SECRET');
    return $s ? $s : 'cashmanager-default-backup-secret';
}

$st = $pdo->prepare('SELECT id, username, email, full_name, phone, currency FROM users WHERE id = ?');
$st->execute([$userId]);
$user = $st->fetch();
if (!$user) jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);

$st = $pdo->prepare('SELECT id, name, type, icon, color FROM categories WHERE user_id = ? ORDER BY id');
$st->execute([$userId]);
$categories = $st->fetchAll();

$st = $pdo->prepare('
    SELECT t.type, t.amount, t.transaction_date, t.payment_method, t.note,
           c.name AS category_name, c.type AS category_type
    FROM transactions t JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? ORDER BY t.transaction_date, t.id');
$st->execute([$userId]);
$transactions = $st->fetchAll();

$st = $pdo->prepare('
    SELECT b.amount, b.month, b.year, b.alert_threshold,
           c.name AS category_name, c.type AS category_type
    FROM budgets b JOIN categories c ON c.id = b.category_id
    WHERE b.user_id = ? ORDER BY b.year, b.month, b.id');
$st->execute([$userId]);
$budgets = $st->fetchAll();

$data = [
    'user'         => $user,
    'categories'   => $categories,
    'transactions' => $transactions,
    'budgets'      => $budgets,
];
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$backup = [
    'app'         => 'CashManager',
    'version'     => 1,
    'exported_at' => date('c'),
    'data'        => $data,
    'signature'   => hash_hmac('sha256', json_encode($data, $flags), backupSecret()),
];

$filename = 'backup_' . $user['username'] . '_' . date('Ymd_His') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($backup, $flags | JSON_PRETTY_PRINT);
