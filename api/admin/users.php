<?php
// GET /api/admin/users.php
// API Quản trị viên: Lấy danh sách tài khoản, hồ sơ tóm tắt & thống kê toàn hệ thống
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

// Bắt buộc quyền Quản trị viên (Admin)
$adminId = requireAdmin();
$pdo = getDbConnection();

// 1. Thống kê tổng quan hệ thống
$totalUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$bannedUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$txSummary = $pdo->query("
    SELECT 
        COUNT(*) AS total_tx,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions
")->fetch();

$totalTx      = (int)($txSummary['total_tx'] ?? 0);
$systemIncome = (float)($txSummary['total_income'] ?? 0);
$systemExpense= (float)($txSummary['total_expense'] ?? 0);

$totalSpaces = 0;
try {
    $totalSpaces = (int)$pdo->query("SELECT COUNT(*) FROM shared_spaces")->fetchColumn();
} catch (Throwable $e) {}

// 2. Thống kê tăng trưởng 7 ngày gần nhất (Người dùng mới & Giao dịch phát sinh)
$growthLabels = [];
$userGrowth   = [];
$txGrowth     = [];

$userByDateStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
$txByDateStmt   = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_date = ?");

for ($i = 6; $i >= 0; $i--) {
    $dateStr = date('Y-m-d', strtotime("-{$i} days"));
    $label   = date('d/m', strtotime($dateStr));
    $growthLabels[] = $label;

    $userByDateStmt->execute([$dateStr]);
    $userGrowth[] = (int)$userByDateStmt->fetchColumn();

    $txByDateStmt->execute([$dateStr]);
    $txGrowth[] = (int)$txByDateStmt->fetchColumn();
}

// 3. Lấy danh sách toàn bộ người dùng kèm hồ sơ tài chính tóm tắt
$hasSharedMembers = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'shared_members'")->fetch();
    $hasSharedMembers = (bool)$chk;
} catch (Throwable $e) {}

$spacesSubquery = $hasSharedMembers
    ? "(SELECT COUNT(*) FROM shared_members sm WHERE sm.user_id = u.id)"
    : "0";

$stmt = $pdo->prepare("
    SELECT 
        u.id, u.username, u.email, u.full_name, u.phone, u.role, u.status, u.currency, u.created_at,
        COALESCE(tx.tx_count, 0) AS tx_count,
        COALESCE(tx.total_income, 0) AS total_income,
        COALESCE(tx.total_expense, 0) AS total_expense,
        {$spacesSubquery} AS spaces_count
    FROM users u
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) AS tx_count,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
        FROM transactions
        GROUP BY user_id
    ) tx ON tx.user_id = u.id
    ORDER BY u.id ASC
");
$stmt->execute();
$users = $stmt->fetchAll();

foreach ($users as &$u) {
    $u['id']            = (int)$u['id'];
    $u['tx_count']      = (int)$u['tx_count'];
    $u['total_income']  = (float)$u['total_income'];
    $u['total_expense'] = (float)$u['total_expense'];
    $u['spaces_count']  = (int)$u['spaces_count'];
    $u['status']        = $u['status'] ?: 'active';
    $u['role']          = $u['role'] ?: 'user';
}
unset($u);

jsonResponse([
    'success' => true,
    'data' => [
        'current_admin_id' => $adminId,
        'stats' => [
            'total_users'        => $totalUsers,
            'active_users'       => $activeUsers,
            'banned_users'       => $bannedUsers,
            'total_admins'       => $totalAdmins,
            'total_transactions' => $totalTx,
            'system_income'      => $systemIncome,
            'system_expense'     => $systemExpense,
            'total_spaces'       => $totalSpaces
        ],
        'growth' => [
            'labels'       => $growthLabels,
            'new_users'    => $userGrowth,
            'transactions' => $txGrowth
        ],
        'users' => $users
    ]
]);
