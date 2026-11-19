<?php
/**
 * API Lấy danh sách Quỹ chung / Không gian tài chính nhóm
 * Phụ trách: Thái Châu (Backend)
 * Phương thức: GET
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();
$pdo = getDbConnection();

// TODO (Thái Châu):
// Truy vấn các quỹ chung mà user_id là chủ sở hữu (owner) hoặc thành viên (member)

jsonResponse([
    'success' => true,
    'data' => [
        'spaces' => [],
        'message' => 'Endpoint lấy danh sách quỹ chung sẵn sàng'
    ]
]);
