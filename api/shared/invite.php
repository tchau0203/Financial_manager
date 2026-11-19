<?php
/**
 * API Mời thành viên vào Quỹ chung bằng Email
 * Phụ trách: Thái Châu (Backend)
 * Phương thức: POST (Body: space_id, email)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

$data    = getJsonInput();
$spaceId = filter_var($data['space_id'] ?? null, FILTER_VALIDATE_INT);
$email   = trim($data['email'] ?? '');

// TODO (Thái Châu): Tìm user_id theo email và INSERT vào shared_members

jsonResponse([
    'success' => true,
    'message' => 'Endpoint mời thành viên sẵn sàng'
]);
