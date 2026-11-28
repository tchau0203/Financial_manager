<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';

$userId = requireAuth();
$pdo = getDbConnection();

$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT) ?: (int)date('n');
$year  = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT) ?: (int)date('Y');

// Lấy thông tin người dùng
$st = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ? LIMIT 1');
$st->execute([$userId]);
$user = $st->fetch();

if (!$user || empty($user['email'])) {
    jsonResponse(['success' => false, 'error' => 'Không tìm thấy địa chỉ email của bạn.'], 400);
}

// Lấy danh sách ngân sách của tháng
$stmt = $pdo->prepare('
    SELECT b.*, c.name as category_name 
    FROM budgets b
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND b.month = ? AND b.year = ?
');
$stmt->execute([$userId, $month, $year]);
$budgets = $stmt->fetchAll();

if (empty($budgets)) {
    jsonResponse(['success' => false, 'error' => "Bạn chưa thiết lập ngân sách cho Tháng $month/$year."], 400);
}

$totalLimit = 0;
$totalSpent = 0;
$items = [];
$exceededCount = 0;

foreach ($budgets as $b) {
    $spentStmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount), 0) FROM transactions 
        WHERE user_id = ? AND category_id = ? AND type = "expense"
          AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
    ');
    $spentStmt->execute([$userId, $b['category_id'], $month, $year]);
    $spent = (float)$spentStmt->fetchColumn();

    $limit = (float)$b['amount'];
    $pct = $limit > 0 ? ($spent / $limit) * 100 : 0;
    $status = $pct >= 100 ? 'exceeded' : ($pct >= ($b['alert_threshold'] ?? 80) ? 'warning' : 'ok');
    if ($pct >= 100) $exceededCount++;

    $totalLimit += $limit;
    $totalSpent += $spent;

    $items[] = [
        'name'   => $b['category_name'] ?: 'Khác',
        'limit'  => $limit,
        'spent'  => $spent,
        'pct'    => $pct,
        'status' => $status
    ];
}

$totalPct = $totalLimit > 0 ? ($totalSpent / $totalLimit) * 100 : 0;

// Soạn email báo cáo tổng thể
$fullName = htmlspecialchars($user['full_name'] ?: 'bạn', ENT_QUOTES, 'UTF-8');
$subject = "Báo cáo ngân sách Tháng $month/$year: " . ($exceededCount > 0 ? "Vượt ngân sách ($exceededCount danh mục)" : "Đạt " . round($totalPct) . "% hạn mức");

$rowsHtml = '';
foreach ($items as $it) {
    $cColor = $it['status'] === 'exceeded' ? '#DC2626' : ($it['status'] === 'warning' ? '#F59E0B' : '#10B981');
    $tagText = $it['status'] === 'exceeded' ? 'Vượt hạn mức' : ($it['status'] === 'warning' ? 'Sắp chạm ngưỡng' : 'Bình thường');
    $rowsHtml .= '
        <tr style="border-bottom: 1px solid #E5E7EB;">
            <td style="padding: 12px 8px; font-weight: 600; color: #1F2937;">' . htmlspecialchars($it['name']) . '</td>
            <td style="padding: 12px 8px; text-align: right; color: #4B5563;">' . formatVnd($it['limit']) . '</td>
            <td style="padding: 12px 8px; text-align: right; font-weight: 600; color: #111827;">' . formatVnd($it['spent']) . '</td>
            <td style="padding: 12px 8px; text-align: right; font-weight: 700; color: ' . $cColor . ';">' . round($it['pct']) . '%</td>
            <td style="padding: 12px 8px; text-align: center;"><span style="display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; background: ' . ($it['status'] === 'exceeded' ? '#FEE2E2' : ($it['status'] === 'warning' ? '#FEF3C7' : '#D1FAE5')) . '; color: ' . $cColor . ';">' . $tagText . '</span></td>
        </tr>
    ';
}

$mainColor = $totalPct >= 100 ? '#DC2626' : '#2563EB';

$htmlBody = '
<!DOCTYPE html>
<html>
<body style="margin: 0; padding: 20px 0; background-color: #F3F4F6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
<div style="max-width: 620px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    
    <!-- Header -->
    <div style="background: ' . $mainColor . '; color: #FFFFFF; padding: 26px 28px;">
        <h2 style="margin: 0; font-size: 22px;">Báo Cáo Ngân Sách Tháng ' . $month . '/' . $year . '</h2>
        <div style="opacity: 0.92; font-size: 13px; margin-top: 6px;">CashManager – Trợ lý tài chính cá nhân thông minh</div>
    </div>

    <!-- Body -->
    <div style="padding: 26px 28px;">
        <p style="font-size: 15px; color: #374151; margin-top: 0;">Xin chào <b>' . $fullName . '</b>,</p>
        <p style="font-size: 14px; color: #4B5563; line-height: 1.5;">
            Dưới đây là bảng tổng hợp tiến độ chi tiêu và tình trạng hạn mức ngân sách tháng <b>' . $month . '/' . $year . '</b> của bạn:
        </p>

        <!-- Summary Card -->
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px; margin: 18px 0;">
            <div style="display: flex; justify-content: space-between; font-size: 14px; color: #64748B; margin-bottom: 8px;">
                <span>Tổng chi: <b style="color: #0F172A;">' . formatVnd($totalSpent) . '</b></span>
                <span>Hạn mức: <b style="color: #0F172A;">' . formatVnd($totalLimit) . '</b></span>
            </div>
            <div style="background: #E2E8F0; border-radius: 99px; height: 10px; overflow: hidden; margin-bottom: 8px;">
                <div style="width: ' . min(100, round($totalPct)) . '%; height: 100%; background: ' . $mainColor . '; border-radius: 99px;"></div>
            </div>
            <div style="font-size: 13px; font-weight: 700; color: ' . $mainColor . '; text-align: right;">
                Đã sử dụng ' . round($totalPct, 1) . '% ngân sách cả tháng' . ($exceededCount > 0 ? ' (⚠️ Có ' . $exceededCount . ' danh mục vượt hạn mức)' : '') . '
            </div>
        </div>

        <!-- Chi tiết danh mục -->
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 14px;">
            <thead>
                <tr style="background: #F1F5F9; color: #475569; text-align: left;">
                    <th style="padding: 10px 8px; border-radius: 8px 0 0 8px;">Danh mục</th>
                    <th style="padding: 10px 8px; text-align: right;">Hạn mức</th>
                    <th style="padding: 10px 8px; text-align: right;">Đã chi</th>
                    <th style="padding: 10px 8px; text-align: right;">Tỷ lệ</th>
                    <th style="padding: 10px 8px; text-align: center; border-radius: 0 8px 8px 0;">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                ' . $rowsHtml . '
            </tbody>
        </table>

        <!-- Footer Notice -->
        <div style="margin-top: 26px; padding-top: 18px; border-top: 1px solid #E5E7EB; font-size: 12px; color: #9CA3AF; text-align: center;">
            Email này được gửi tự động từ hệ thống quản lý chi tiêu CashManager.<br>
            Bạn có thể quản lý ngân sách và cài đặt cảnh báo tại ứng dụng bất cứ lúc nào.
        </div>
    </div>
</div>
</body>
</html>
';

$sent = sendMail($user['email'], $subject, $htmlBody);

if ($sent) {
    jsonResponse([
        'success' => true,
        'message' => 'Đã gửi báo cáo ngân sách thành công tới ' . $user['email']
    ]);
} else {
    jsonResponse([
        'success' => false,
        'error'   => 'Không thể gửi email lúc này. Vui lòng kiểm tra lại kết nối mạng hoặc thử lại sau.'
    ], 500);
}
