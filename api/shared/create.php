<?php
/**
 * API Tạo Không gian Quỹ chung mới
 * Phụ trách: Thái Châu (Backend)
 * Phương thức: POST (Body: name, description)
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

$data = getJsonInput();
$name = trim($data['name'] ?? '');

if (empty($name)) {
    jsonResponse(['success' => false, 'message' => 'Tên quỹ không được để trống'], 400);
}

// TODO (Thái Châu): INSERT vào bảng shared_spaces và shared_members với role='owner'

jsonResponse([
    'success' => true,
    'message' => 'Tạo quỹ chung thành công (Endpoint sẵn sàng)'
]);
