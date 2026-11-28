<?php
/**
 * API Cài đặt Hệ thống Quản trị (Admin Settings)
 * GET /api/admin/settings.php  -> Lấy cấu hình hệ thống
 * POST /api/admin/settings.php -> Lưu cấu hình hệ thống hoặc kiểm tra gửi mail
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';

$adminId = requireAdmin();
$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Tự động kiểm tra bảng system_settings
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_settings` (
            `setting_key` VARCHAR(64) PRIMARY KEY,
            `setting_value` TEXT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {}

// Cấu hình mặc định
$defaults = [
    'app_name'             => 'CashManager',
    'app_version'          => '2.4.0',
    'maintenance_mode'     => '0',
    'allow_registration'   => '1',
    'google_login_enabled' => '1',
    'default_threshold'    => '80',
    'default_currency'     => 'VND',
    'mail_host'            => 'smtp.gmail.com',
    'mail_port'            => '587',
    'mail_user'            => 'cashmanager.contact@gmail.com',
    'mail_from'            => 'cashmanager.contact@gmail.com',
    'ai_model'             => 'gemini-2.0-flash',
    'ai_status'            => 'active'
];

if ($method === 'GET') {
    $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($defaults, $rows ?: []);
    
    // Đếm tổng số người dùng và giao dịch làm thống kê nhanh
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalTxs   = (int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();

    jsonResponse([
        'success'     => true,
        'settings'    => $settings,
        'stats'       => [
            'total_users'        => $totalUsers,
            'total_transactions' => $totalTxs
        ]
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    // Kiểm tra nếu là action test gửi email
    if (($data['action'] ?? '') === 'test_mail') {
        $testTarget = trim($data['target_email'] ?? '');
        if (empty($testTarget) || !filter_var($testTarget, FILTER_VALIDATE_EMAIL)) {
            $st = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $st->execute([$adminId]);
            $testTarget = $st->fetchColumn() ?: 'cashmanager.contact@gmail.com';
        }

        $subject = 'Kiểm tra cấu hình Email Hệ thống - CashManager Admin';
        $body = '
            <div style="font-family:Arial,sans-serif;padding:20px;max-width:500px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;">
                <h3 style="color:#7c3aed;margin-top:0;">CashManager Admin - Thử nghiệm SMTP</h3>
                <p>Xin chào Quản trị viên,</p>
                <p>Đây là email kiểm tra kết nối SMTP từ hệ thống <b>CashManager</b>. Cấu hình máy chủ gửi thư hoạt động hoàn toàn bình thường!</p>
                <div style="background:#f1f5f9;padding:12px;border-radius:8px;font-size:13px;color:#475569;">
                    Thời gian gửi: ' . date('d/m/Y H:i:s') . '<br>
                    Máy chủ: smtp.gmail.com:587
                </div>
            </div>
        ';

        $sent = sendMail($testTarget, $subject, $body);
        if ($sent) {
            jsonResponse(['success' => true, 'message' => "Đã gửi email thử nghiệm thành công tới $testTarget!"]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Không thể kết nối máy chủ SMTP. Vui lòng kiểm tra lại cấu hình.'], 500);
        }
    }

    // Lưu các key cài đặt
    $stmt = $pdo->prepare('
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');

    $allowedKeys = array_keys($defaults);
    foreach ($data as $k => $v) {
        if (in_array($k, $allowedKeys, true)) {
            $stmt->execute([$k, is_bool($v) ? ($v ? '1' : '0') : (string)$v]);
        }
    }

    // Ghi nhật ký quản trị
    if (function_exists('logAdminAction')) {
        logAdminAction($pdo, $adminId, 'update_settings', 'System Settings', 'Cập nhật cấu hình hệ thống quản trị');
    }

    jsonResponse([
        'success' => true,
        'message' => 'Đã lưu cấu hình hệ thống thành công!'
    ]);
}
