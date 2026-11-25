<?php
// GET /api/admin/users.php
// API Quản trị viên: Lấy danh sách tài khoản & thống kê toàn hệ thống
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

// Bắt buộc quyền Quản trị viên (Admin)
$adminId = requireAdmin();
$pdo = getDbConnection();

// 1. Thống kê tổng quan hệ thống
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalTx = (int)$pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalSpaces = 0;
try {
    $totalSpaces = (int)$pdo->query("SELECT COUNT(*) FROM shared_spaces")->fetchColumn();
} catch (Throwable $e) {}

// 2. Lấy danh sách toàn bộ người dùng
$stmt = $pdo->prepare("
    SELECT id, username, email, full_name, phone, role, currency, created_at 
    FROM users 
    ORDER BY id ASC
");
$stmt->execute();
$users = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => [
        'stats' => [
            'total_users'        => $totalUsers,
            'total_admins'       => $totalAdmins,
            'total_transactions' => $totalTx,
            'total_spaces'       => $totalSpaces
        ],
        'users' => $users
    ]
]);
