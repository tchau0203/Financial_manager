<?php
// POST /api/auth/forgot_password.php { email }
// Khôi phục mật khẩu qua Email (gửi mật khẩu tạm thời về hòm thư người dùng)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
require_once '../../config/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$data  = getJsonInput();
$email = strtolower(trim($data['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Vui lòng nhập địa chỉ email hợp lệ'], 400);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT id, username, full_name, email FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Để bảo mật, không tiết lộ email có tồn tại hay không nhưng thông báo rõ ràng cho trải nghiệm người dùng
    jsonResponse(['error' => 'Không tìm thấy tài khoản nào liên kết với email này'], 404);
}

// Tạo mật khẩu ngẫu nhiên 8 ký tự dễ đọc (VD: Cash@839201)
$randomCode = rand(100000, 999999);
$tempPassword = "Cash@" . $randomCode;
$hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

// Cập nhật vào cơ sở dữ liệu
$updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$updateStmt->execute([$hashedPassword, $user['id']]);

// Soạn nội dung email HTML gửi cho người dùng
$userName = htmlspecialchars($user['full_name'] ?: $user['username']);
$subject = "CashManager - Khôi phục mật khẩu tài khoản";
$htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 700; color: #4f46e5; }
        .pass-box { background: #eef2ff; border: 2px dashed #6366f1; border-radius: 8px; padding: 16px; text-align: center; margin: 24px 0; }
        .pass-code { font-family: monospace; font-size: 24px; font-weight: 700; color: #4338ca; letter-spacing: 2px; }
        .note { font-size: 13px; color: #64748b; line-height: 1.6; }
        .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo">💰 CashManager</div>
            <h2 style="margin: 12px 0 4px; color: #0f172a;">Yêu Cầu Đặt Lại Mật Khẩu</h2>
        </div>
        <p>Xin chào <strong>' . $userName . '</strong>,</p>
        <p>Hệ thống vừa nhận được yêu cầu khôi phục mật khẩu cho tài khoản <strong>' . htmlspecialchars($user['username']) . '</strong>.</p>
        <p>Mật khẩu tạm thời mới của bạn là:</p>
        <div class="pass-box">
            <div class="pass-code">' . $tempPassword . '</div>
        </div>
        <p class="note">⚠️ <em>Lưu ý: Bạn hãy dùng mật khẩu này để đăng nhập ngay, sau đó truy cập mục <strong>Cài đặt tài khoản</strong> để đổi lại mật khẩu cá nhân của mình.</em></p>
        <div class="footer">
            Email này được gửi tự động từ hệ thống Quản lý chi tiêu CashManager.<br>Nếu bạn không thực hiện yêu cầu này, vui lòng liên hệ quản trị viên.
        </div>
    </div>
</body>
</html>';

$mailSent = sendMail($email, $subject, $htmlBody);

if ($mailSent) {
    jsonResponse([
        'success' => true,
        'message' => 'Mật khẩu mới tạm thời đã được gửi tới email của bạn. Vui lòng kiểm tra hộp thư!'
    ]);
} else {
    jsonResponse([
        'error' => 'Không thể gửi email. Vui lòng thử lại sau hoặc liên hệ hỗ trợ!'
    ], 500);
}
