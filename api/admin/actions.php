<?php
/**
 * API Quản trị viên: Thực hiện các hành động trên tài khoản người dùng
 * Phụ trách: Thái Châu (Lead)
 * Phương thức: POST /api/admin/actions.php
 * Payload mẫu:
 *   - Khóa/Mở tài khoản:   { action: "toggle_status", user_id: 12 }
 *   - Phân quyền:          { action: "change_role", user_id: 12, role: "admin" | "user" }
 *   - Đặt lại mật khẩu:    { action: "reset_password", user_id: 12, new_password: "..." }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

// Bắt buộc quyền Quản trị viên (Admin)
$adminId = requireAdmin();
$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ (yêu cầu POST)'], 405);
}

$data = getJsonInput();
$action = trim((string)($data['action'] ?? ''));
$targetUserId = (int)($data['user_id'] ?? 0);

if ($targetUserId <= 0) {
    jsonResponse(['error' => 'Thiếu hoặc sai ID người dùng mục tiêu'], 400);
}

// Không cho phép tự khóa hoặc tự giáng cấp tài khoản admin của chính mình
if ($targetUserId === $adminId && in_array($action, ['toggle_status', 'change_role'], true)) {
    jsonResponse(['error' => 'Bạn không thể tự khóa hoặc tự thay đổi vai trò của chính mình'], 400);
}

// TODO (Thái Châu): Triển khai chi tiết các hành động bên dưới:
switch ($action) {
    case 'toggle_status':
        // 1. Khóa hoặc Mở khóa tài khoản
        // $pdo->prepare("UPDATE users SET status = IF(status = 'banned', 'active', 'banned') WHERE id = ?")->execute([$targetUserId]);
        jsonResponse(['success' => true, 'message' => 'Cập nhật trạng thái tài khoản thành công']);
        break;

    case 'change_role':
        // 2. Chuyển đổi vai trò Admin / User
        $newRole = trim((string)($data['role'] ?? 'user'));
        if (!in_array($newRole, ['user', 'admin'], true)) {
            jsonResponse(['error' => 'Vai trò không hợp lệ (chỉ user hoặc admin)'], 400);
        }
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $targetUserId]);
        jsonResponse(['success' => true, 'message' => "Đã chuyển vai trò thành công sang {$newRole}"]);
        break;

    case 'reset_password':
        // 3. Đặt lại mật khẩu khẩn cấp cho người dùng
        $newPass = trim((string)($data['new_password'] ?? 'Cash@123456'));
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $targetUserId]);
        jsonResponse(['success' => true, 'message' => 'Đặt lại mật khẩu thành công']);
        break;

    default:
        jsonResponse(['error' => 'Hành động không hợp lệ'], 400);
}
