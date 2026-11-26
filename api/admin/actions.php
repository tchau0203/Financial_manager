<?php
/**
 * POST /api/admin/actions.php
 * API Quản trị viên: Thực hiện các thao tác quản lý tài khoản người dùng
 * Phụ trách: Thái Châu (Lead)
 * Các hành động hỗ trợ (action):
 *   - toggle_status : Khóa hoặc Mở khóa tài khoản (active <-> banned)
 *   - change_role   : Thăng cấp Admin hoặc Hạ cấp User (admin <-> user)
 *   - reset_password: Đặt lại mật khẩu khẩn cấp cho người dùng
 *   - create_user   : Tạo tài khoản mới trực tiếp từ cổng Admin
 *   - delete_user   : Xóa tài khoản vi phạm / tài khoản rác
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$adminId = requireAdmin();
$pdo     = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ (yêu cầu POST)'], 405);
}

$data   = getJsonInput();
$action = trim((string)($data['action'] ?? ''));

// Hành động tạo tài khoản mới không cần user_id có sẵn
if ($action === 'create_user') {
    $username = trim((string)($data['username'] ?? ''));
    $email    = strtolower(trim((string)($data['email'] ?? '')));
    $fullName = trim((string)($data['full_name'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $role     = in_array($data['role'] ?? 'user', ['user', 'admin'], true) ? $data['role'] : 'user';

    if (strlen($username) < 3) {
        jsonResponse(['error' => 'Tên đăng nhập phải có ít nhất 3 ký tự'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Địa chỉ email không hợp lệ'], 400);
    }
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Mật khẩu phải có ít nhất 6 ký tự'], 400);
    }

    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $chk->execute([$username, $email]);
    if ($chk->fetch()) {
        jsonResponse(['error' => 'Tên đăng nhập hoặc email này đã tồn tại trong hệ thống'], 409);
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name, role, status, currency) VALUES (?, ?, ?, ?, ?, "active", "VND")');
    $stmt->execute([$username, $email, $hashed, $fullName ?: $username, $role]);
    $newId = (int)$pdo->lastInsertId();

    logAdminAction($pdo, $adminId, 'create_user', "@{$username} (#{$newId})", "Tạo tài khoản mới với vai trò {$role}");

    jsonResponse([
        'success' => true,
        'message' => "Đã tạo tài khoản '{$username}' với vai trò " . strtoupper($role),
        'user_id' => $newId
    ]);
}

// Các hành động thao tác trên tài khoản mục tiêu
$targetUserId = (int)($data['user_id'] ?? 0);
if ($targetUserId <= 0) {
    jsonResponse(['error' => 'Thiếu hoặc sai ID người dùng mục tiêu'], 400);
}

$targetStmt = $pdo->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE id = ? LIMIT 1');
$targetStmt->execute([$targetUserId]);
$targetUser = $targetStmt->fetch();

if (!$targetUser) {
    jsonResponse(['error' => 'Không tìm thấy tài khoản người dùng này'], 404);
}

// Bảo vệ: Không cho phép Admin tự khóa, tự hạ quyền hoặc tự xóa chính mình
if ($targetUserId === $adminId && in_array($action, ['toggle_status', 'change_role', 'delete_user'], true)) {
    jsonResponse(['error' => 'Bạn không thể tự khóa, tự hạ quyền hoặc tự xóa tài khoản Admin đang đăng nhập của chính mình!'], 400);
}

switch ($action) {
    case 'toggle_status':
        $currentStatus = $targetUser['status'] ?: 'active';
        $newStatus = ($currentStatus === 'banned') ? 'active' : 'banned';
        if (!empty($data['status']) && in_array($data['status'], ['active', 'banned'], true)) {
            $newStatus = $data['status'];
        }

        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $targetUserId]);
        $msg = ($newStatus === 'banned')
            ? "Đã khóa tài khoản @{$targetUser['username']} thành công"
            : "Đã mở khóa tài khoản @{$targetUser['username']} thành công";

        logAdminAction($pdo, $adminId, $newStatus === 'banned' ? 'ban_user' : 'unban_user', "@{$targetUser['username']} (#{$targetUserId})", $msg);

        jsonResponse([
            'success'    => true,
            'message'    => $msg,
            'new_status' => $newStatus
        ]);
        break;

    case 'change_role':
        $currentRole = $targetUser['role'] ?: 'user';
        $newRole = ($currentRole === 'admin') ? 'user' : 'admin';
        if (!empty($data['role']) && in_array($data['role'], ['user', 'admin'], true)) {
            $newRole = $data['role'];
        }

        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetUserId]);
        $roleLabel = ($newRole === 'admin') ? 'Quản trị viên (Admin)' : 'Người dùng (User)';

        logAdminAction($pdo, $adminId, 'change_role', "@{$targetUser['username']} (#{$targetUserId})", "Đổi vai trò từ {$currentRole} sang {$newRole}");

        jsonResponse([
            'success'  => true,
            'message'  => "Đã cập nhật vai trò của @{$targetUser['username']} thành {$roleLabel}",
            'new_role' => $newRole
        ]);
        break;

    case 'reset_password':
        $newPassword = trim((string)($data['new_password'] ?? ''));
        if ($newPassword === '') {
            $newPassword = 'Cash@' . rand(100000, 999999);
        } elseif (strlen($newPassword) < 6) {
            jsonResponse(['error' => 'Mật khẩu mới phải có tối thiểu 6 ký tự'], 400);
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hashed, $targetUserId]);

        logAdminAction($pdo, $adminId, 'reset_password', "@{$targetUser['username']} (#{$targetUserId})", "Đặt lại mật khẩu khẩn cấp");

        jsonResponse([
            'success'      => true,
            'message'      => "Đã đặt lại mật khẩu cho @{$targetUser['username']} thành công",
            'new_password' => $newPassword
        ]);
        break;

    case 'delete_user':
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetUserId]);
        logAdminAction($pdo, $adminId, 'delete_user', "@{$targetUser['username']} (#{$targetUserId})", "Xóa vĩnh viễn tài khoản email {$targetUser['email']}");
        jsonResponse([
            'success' => true,
            'message' => "Đã xóa vĩnh viễn tài khoản @{$targetUser['username']}"
        ]);
        break;

    default:
        jsonResponse(['error' => 'Hành động quản trị không hợp lệ'], 400);
}
