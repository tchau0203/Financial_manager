<?php
// POST /api/backup/restore.php  (multipart: file=<backup.json>, mode=merge|overwrite)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Chỉ hỗ trợ POST'], 405);
}

$mode = $_POST['mode'] ?? 'merge';
if (!in_array($mode, ['merge', 'overwrite'], true)) {
    jsonResponse(['success' => false, 'message' => 'Chế độ khôi phục không hợp lệ'], 400);
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'Chưa chọn file hoặc upload thất bại'], 400);
}
if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'File quá lớn (tối đa 10MB)'], 400);
}

$backup = json_decode(file_get_contents($_FILES['file']['tmp_name']), true);
if (!is_array($backup) || ($backup['app'] ?? '') !== 'CashManager' || !isset($backup['data'], $backup['signature'])
    || !is_array($backup['data'])) {
    jsonResponse(['success' => false, 'message' => 'File backup không đúng định dạng'], 400);
}

$secret = $_ENV['BACKUP_SECRET'] ?? getenv('BACKUP_SECRET');
$secret = $secret ? $secret : 'cashmanager-default-backup-secret';
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$expected = hash_hmac('sha256', json_encode($backup['data'], $flags), $secret);
if (!hash_equals($expected, (string)$backup['signature'])) {
    jsonResponse(['success' => false, 'message' => 'Chữ ký không hợp lệ: file đã bị chỉnh sửa hoặc không phải backup của hệ thống'], 400);
}

$data = $backup['data'];
$transactions = is_array($data['transactions'] ?? null) ? $data['transactions'] : [];
$budgets      = is_array($data['budgets'] ?? null) ? $data['budgets'] : [];
$categories   = is_array($data['categories'] ?? null) ? $data['categories'] : [];

$pdo = getDbConnection();

// Tìm hoặc tạo danh mục theo (tên, loại) – ưu tiên danh mục hệ thống
$catCache = [];
$findCat = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND type = ? AND (user_id IS NULL OR user_id = ?) ORDER BY user_id IS NULL DESC LIMIT 1');
$insCat  = $pdo->prepare('INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)');
$resolveCat = function ($name, $type, $icon = 'fa-tag', $color = '#4F46E5') use (&$catCache, $findCat, $insCat, $pdo, $userId) {
    $key = $type . '|' . $name;
    if (isset($catCache[$key])) return $catCache[$key];
    $findCat->execute([$name, $type, $userId]);
    $id = $findCat->fetchColumn();
    if (!$id) {
        $insCat->execute([$userId, $name, $type, $icon ?: 'fa-tag', $color ?: '#4F46E5']);
        $id = $pdo->lastInsertId();
    }
    return $catCache[$key] = (int)$id;
};

$stats = ['transactions' => 0, 'skipped' => 0, 'budgets' => 0, 'categories' => 0];

try {
    $pdo->beginTransaction();

    if ($mode === 'overwrite') {
        $pdo->prepare('DELETE FROM transactions WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM budgets WHERE user_id = ?')->execute([$userId]);
    }

    foreach ($categories as $c) {
        if (!isset($c['name'], $c['type']) || !in_array($c['type'], ['income', 'expense'], true)) continue;
        $resolveCat(mb_substr(trim($c['name']), 0, 100), $c['type'], $c['icon'] ?? 'fa-tag', $c['color'] ?? '#4F46E5');
        $stats['categories']++;
    }

    $dupCheck = $pdo->prepare('SELECT id FROM transactions WHERE user_id = ? AND category_id = ? AND type = ? AND amount = ? AND transaction_date = ? AND COALESCE(note, "") = ? LIMIT 1');
    $insTx = $pdo->prepare('INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, payment_method, note) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($transactions as $t) {
        $type = $t['type'] ?? '';
        $date = $t['transaction_date'] ?? '';
        $d = is_string($date) ? DateTime::createFromFormat('!Y-m-d', $date) : false;
        if (!in_array($type, ['income', 'expense'], true) || !is_numeric($t['amount'] ?? null) || (float)$t['amount'] <= 0
            || !$d || $d->format('Y-m-d') !== $date || empty($t['category_name'])) {
            $stats['skipped']++;
            continue;
        }
        $catId = $resolveCat(mb_substr(trim($t['category_name']), 0, 100), $t['category_type'] ?? $type);
        $note = (string)($t['note'] ?? '');
        if ($mode === 'merge') {
            $dupCheck->execute([$userId, $catId, $type, $t['amount'], $date, $note]);
            if ($dupCheck->fetch()) { $stats['skipped']++; continue; }
        }
        $insTx->execute([$userId, $catId, $type, $t['amount'], $date, mb_substr((string)($t['payment_method'] ?? 'Tiền mặt'), 0, 50), $note]);
        $stats['transactions']++;
    }

    $insBudget = $pdo->prepare('
        INSERT INTO budgets (user_id, category_id, amount, month, year, alert_threshold) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), alert_threshold = VALUES(alert_threshold)');
    foreach ($budgets as $b) {
        $m = (int)($b['month'] ?? 0); $y = (int)($b['year'] ?? 0);
        if ($m < 1 || $m > 12 || $y < 2000 || !is_numeric($b['amount'] ?? null) || empty($b['category_name'])) continue;
        $catId = $resolveCat(mb_substr(trim($b['category_name']), 0, 100), $b['category_type'] ?? 'expense');
        $insBudget->execute([$userId, $catId, $b['amount'], $m, $y, (int)($b['alert_threshold'] ?? 80)]);
        $stats['budgets']++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Khôi phục thất bại, dữ liệu cũ được giữ nguyên: ' . $e->getMessage()], 500);
}

jsonResponse([
    'success' => true,
    'message' => 'Khôi phục dữ liệu thành công',
    'data'    => $stats + ['mode' => $mode],
]);
