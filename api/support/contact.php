<?php
/**
 * API Gửi yêu cầu hỗ trợ / liên hệ tới Quản trị viên (Admin)
 * POST /api/support/contact.php
 * Payload: { name, email, subject, message }
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Phương thức không được hỗ trợ (chỉ chấp nhận POST)'], 405);
}

$pdo = getDbConnection();

// Tự động kiểm tra và tạo bảng lưu trữ yêu cầu hỗ trợ (support_messages)
try {
    static $checkedTable = false;
    if (!$checkedTable) {
        $checkedTable = true;
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `support_messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(150) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `ip_address` VARCHAR(45) NULL,
                `status` ENUM('pending', 'read', 'replied') NOT NULL DEFAULT 'pending',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (`status`),
                INDEX (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
} catch (Exception $e) {
    error_log('[support-db-init] ' . $e->getMessage());
}

// Đọc dữ liệu gửi lên
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$name    = trim($input['name'] ?? '');
$email   = trim($input['email'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

// Lấy thông tin user nếu đang đăng nhập
$userId = $_SESSION['user_id'] ?? null;
if ($userId && (empty($name) || empty($email))) {
    try {
        $st = $pdo->prepare('SELECT full_name, email FROM users WHERE id = ? LIMIT 1');
        $st->execute([(int)$userId]);
        $u = $st->fetch();
        if ($u) {
            if (empty($name))  $name  = $u['full_name'] ?: 'Người dùng CashManager';
            if (empty($email)) $email = $u['email'];
        }
    } catch (Exception $e) {}
}

// Kiểm tra tính hợp lệ
if (empty($name)) {
    jsonResponse(['success' => false, 'error' => 'Vui lòng nhập họ và tên của bạn.'], 400);
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Vui lòng nhập địa chỉ email hợp lệ để nhận phản hồi.'], 400);
}
if (empty($subject)) {
    $subject = 'Yêu cầu hỗ trợ từ người dùng: ' . $name;
}
if (empty($message) || mb_strlen($message) < 5) {
    jsonResponse(['success' => false, 'error' => 'Vui lòng nhập nội dung yêu cầu hỗ trợ (tối thiểu 5 ký tự).'], 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// 1. Lưu vào cơ sở dữ liệu
try {
    $insertSt = $pdo->prepare('
        INSERT INTO support_messages (user_id, name, email, subject, message, ip_address, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())
    ');
    $insertSt->execute([
        $userId ? (int)$userId : null,
        $name,
        $email,
        $subject,
        $message,
        $ip
    ]);
    $ticketId = $pdo->lastInsertId();
} catch (Exception $e) {
    error_log('[support-insert-error] ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Lỗi hệ thống khi lưu yêu cầu hỗ trợ. Vui lòng thử lại sau.'], 500);
}

// 2. Tìm danh sách email của Admin hệ thống để nhận thông báo
$adminEmail = null;
$adminName  = 'Quản trị viên Hệ thống';

try {
    $adminSt = $pdo->query("SELECT email, full_name FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
    $adminRow = $adminSt->fetch();
    if ($adminRow && !empty($adminRow['email'])) {
        $adminEmail = $adminRow['email'];
        $adminName  = $adminRow['full_name'] ?: 'Quản trị viên';
    }
} catch (Exception $e) {
    error_log('[support-admin-lookup] ' . $e->getMessage());
}

// Danh sách email nhận thông báo: Luôn gửi đến cashmanager.contact@gmail.com và email tài khoản Admin (nếu có)
$recipients = ['cashmanager.contact@gmail.com'];
if (!empty($adminEmail) && !in_array($adminEmail, $recipients, true) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    $lower = strtolower($adminEmail);
    if (!str_ends_with($lower, '@cashmanager.vn') && !str_ends_with($lower, '@example.com')) {
        $recipients[] = $adminEmail;
    }
}

// 3. Xây dựng nội dung email gửi đến Admin
$mailSubject = '[CashManager Support #' . $ticketId . '] ' . $subject;
$safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$safeMsg     = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$currentTime = date('d/m/Y H:i:s');
$userInfo    = $userId ? "Tài khoản ID #{$userId}" : "Khách (Chưa đăng nhập)";

$htmlBody = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #ffffff; padding: 24px; text-align: center; }
        .header h2 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 13px; }
        .body { padding: 24px; font-size: 14px; line-height: 1.6; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #f8fafc; border-radius: 8px; overflow: hidden; }
        .info-table td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13.5px; }
        .info-table td.lbl { width: 140px; font-weight: 600; color: #64748b; }
        .msg-box { background: #fdfefe; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-top: 10px; font-size: 14px; line-height: 1.7; color: #0f172a; }
        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; }
        .btn-reply { display: inline-block; background: #4f46e5; color: #ffffff !important; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 16px; font-size: 13px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>📩 Yêu cầu hỗ trợ mới từ Người dùng</h2>
            <p>Hệ thống Quản lý Chi tiêu CashManager</p>
        </div>
        <div class='body'>
            <p>Chào <strong>{$adminName}</strong>,</p>
            <p>Hệ thống vừa nhận được một yêu cầu trợ giúp / phản hồi mới từ người dùng với thông tin chi tiết dưới đây:</p>
            
            <table class='info-table'>
                <tr>
                    <td class='lbl'>Mã yêu cầu (Ticket):</td>
                    <td><strong>#{$ticketId}</strong></td>
                </tr>
                <tr>
                    <td class='lbl'>Họ và tên:</td>
                    <td><strong>{$safeName}</strong></td>
                </tr>
                <tr>
                    <td class='lbl'>Email phản hồi:</td>
                    <td><a href='mailto:{$safeEmail}' style='color:#4f46e5; text-decoration:none;'>{$safeEmail}</a></td>
                </tr>
                <tr>
                    <td class='lbl'>Trạng thái tài khoản:</td>
                    <td>{$userInfo}</td>
                </tr>
                <tr>
                    <td class='lbl'>Chủ đề:</td>
                    <td><strong>{$safeSubject}</strong></td>
                </tr>
                <tr>
                    <td class='lbl'>Thời gian gửi:</td>
                    <td>{$currentTime} (IP: {$ip})</td>
                </tr>
            </table>

            <strong style='display:block; margin-bottom:6px; color:#1e293b;'>Nội dung yêu cầu chi tiết:</strong>
            <div class='msg-box'>
                {$safeMsg}
            </div>

            <div style='text-align: center;'>
                <a href='mailto:{$safeEmail}?subject=Re: {$mailSubject}' class='btn-reply'>
                    ↩️ Bấm vào đây để trả lời người dùng ({$safeEmail})
                </a>
            </div>
        </div>
        <div class='footer'>
            Email này được gửi tự động từ hệ thống Trung tâm Trợ giúp CashManager.<br>
            Quản trị viên có thể theo dõi danh sách phản hồi trong cơ sở dữ liệu bảng <code>support_messages</code>.
        </div>
    </div>
</body>
</html>
";

// 4. Gửi mail thông báo tới danh sách email Quản trị viên
$sent = false;
$sentList = [];
foreach ($recipients as $toEmail) {
    try {
        if (sendMail($toEmail, $mailSubject, $htmlBody)) {
            $sent = true;
            $sentList[] = $toEmail;
        }
    } catch (Exception $e) {
        error_log('[support-sendmail-error] ' . $e->getMessage());
    }
}

// 5. Thêm thông báo trong ứng dụng (Notifications) cho toàn bộ tài khoản Admin
try {
    $adminUserIds = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($adminUserIds)) {
        $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, "info", 0, NOW())');
        foreach ($adminUserIds as $aId) {
            $notifStmt->execute([
                $aId,
                "📩 Yêu cầu hỗ trợ mới #{$ticketId}",
                "{$safeName} ({$safeEmail}) đã gửi: {$safeSubject}"
            ]);
        }
    }
} catch (Exception $e) {
    error_log('[support-notif-insert] ' . $e->getMessage());
}

$recipientStr = implode(', ', $recipients);
jsonResponse([
    'success' => true,
    'ticket_id' => $ticketId,
    'mail_sent' => $sent,
    'sent_to' => $sentList,
    'admin_email' => $recipientStr,
    'message' => 'Yêu cầu của bạn (Mã #' . $ticketId . ') đã được gửi thành công đến Quản trị viên (' . $recipientStr . '). Chúng tôi sẽ phản hồi lại bạn sớm nhất!'
]);
