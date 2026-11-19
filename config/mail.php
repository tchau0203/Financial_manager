<?php
/**
 * Module cấu hình và gửi Email thông báo tự động (Email Alerts)
 * Phụ trách: Thái Châu (Backend)
 */

require_once __DIR__ . '/db.php';

/**
 * Gửi email cảnh báo ngân sách
 * @param string $toEmail Địa chỉ người nhận
 * @param string $fullName Tên người nhận
 * @param string $categoryName Tên danh mục cảnh báo
 * @param float $spent Số tiền đã tiêu
 * @param float $amount Hạn mức ngân sách
 * @param float $percentage Phần trăm đã tiêu
 * @return bool
 */
function sendBudgetAlertEmail($toEmail, $fullName, $categoryName, $spent, $amount, $percentage) {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = "[CashManager] Cảnh báo hạn mức ngân sách: " . $categoryName;
    
    $message = "
    <html>
    <head><title>Cảnh báo ngân sách</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            <h2 style='color: #ef4444;'>⚠️ Cảnh Báo Ngân Sách</h2>
            <p>Xin chào <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
            <p>Hệ thống nhận thấy chi tiêu trong danh mục <strong>" . htmlspecialchars($categoryName) . "</strong> của bạn đã đạt <strong>" . round($percentage) . "%</strong> hạn mức tháng.</p>
            <table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>
                <tr><td>Hạn mức:</td><td><strong>" . number_format($amount, 0, ',', '.') . " đ</strong></td></tr>
                <tr><td>Đã chi:</td><td style='color: #ef4444;'><strong>" . number_format($spent, 0, ',', '.') . " đ</strong></td></tr>
                <tr><td>Còn lại:</td><td style='color: #10b981;'><strong>" . number_format(max(0, $amount - $spent), 0, ',', '.') . " đ</strong></td></tr>
            </table>
            <p>Vui lòng cân đối lại chi tiêu để đảm bảo kế hoạch tài chính cá nhân!</p>
            <hr style='border: none; border-top: 1px solid #e2e8f0;'>
            <p style='font-size: 12px; color: #64748b;'>Hệ thống Quản lý Tài chính Cá nhân CashManager</p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: CashManager <no-reply@cashmanager.local>\r\n";

    // Sử dụng hàm mail() chuẩn của PHP
    // (Trong môi trường production hoặc XAMPP có thể cấu hình smtp trong php.ini hoặc dùng PHPMailer)
    return @mail($toEmail, $subject, $message, $headers);
}
