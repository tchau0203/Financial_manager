<?php
// POST /api/shared/create.php  {name}  -> tạo quỹ chung, người tạo là owner
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();
$data = getJsonInput();
$name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
if ($name === '' || mb_strlen($name) > 100) {
    jsonResponse(['success' => false, 'message' => 'Tên quỹ không hợp lệ (1-100 ký tự)'], 400);
}

$pdo = getDbConnection();
try {
    $pdo->beginTransaction();
    $code = null;
    for ($i = 0; $i < 5; $i++) {
        $try = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $chk = $pdo->prepare('SELECT 1 FROM shared_spaces WHERE invite_code = ?');
        $chk->execute([$try]);
        if (!$chk->fetch()) { $code = $try; break; }
    }
    if (!$code) throw new Exception('Không tạo được mã mời');

    $pdo->prepare('INSERT INTO shared_spaces (owner_id, name, invite_code) VALUES (?, ?, ?)')->execute([$userId, $name, $code]);
    $spaceId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO shared_members (space_id, user_id, role) VALUES (?, ?, "owner")')->execute([$spaceId, $userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Không thể tạo quỹ chung: ' . $e->getMessage()], 500);
}

jsonResponse([
    'success' => true,
    'message' => 'Đã tạo quỹ chung',
    'data'    => ['id' => $spaceId, 'name' => $name, 'invite_code' => $code, 'role' => 'owner'],
], 201);
