<?php
/**
 * API Xử lý Lời mời Quỹ chung (Shared Space Invitations)
 * Phụ trách: Hoàng Nam
 * Phương thức:
 *   - GET  /api/notification/invitations.php -> Lấy danh sách lời mời đang chờ
 *   - POST /api/notification/invitations.php -> Phản hồi lời mời: { "invitation_id": 5, "action": "accept" | "decline" }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // TODO (Hoàng Nam): Lấy danh sách lời mời quỹ chung đang chờ duyệt của user
    jsonResponse([
        'success' => true,
        'data' => []
    ]);
}

if ($method === 'POST') {
    $data = getJsonInput();
    $action = trim((string)($data['action'] ?? '')); // 'accept' hoặc 'decline'
    $spaceId = (int)($data['space_id'] ?? 0);

    // TODO (Hoàng Nam): Xử lý chấp thuận (thêm vào shared_members) hoặc từ chối lời mời
    jsonResponse([
        'success' => true,
        'message' => $action === 'accept' ? 'Đã tham gia Quỹ chung thành công' : 'Đã từ chối lời mời'
    ]);
}

jsonResponse(['error' => 'Phương thức không hỗ trợ'], 405);
