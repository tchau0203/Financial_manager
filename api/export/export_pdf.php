<?php
/**
 * API Xuất báo cáo tài chính dạng PDF (hoặc trang in chuẩn A4)
 * Phụ trách: NG Bảo & Hoàng Nam (Backend)
 * Phương thức: GET
 * Tham số: month, year
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
$year  = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);

// TODO (NG Bảo / Hoàng Nam):
// 1. Truy vấn tổng thu, tổng chi, số dư ròng của tháng/năm chỉ định
// 2. Lấy cơ cấu chi tiêu theo từng danh mục
// 3. Xuất giao diện HTML chuẩn in A4 (@media print) hoặc stream file PDF

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo cáo tài chính Tháng <?php echo htmlspecialchars($month . '/' . $year); ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
        .kpi-grid { display: flex; justify-content: space-between; margin-bottom: 25px; }
        .kpi-box { flex: 1; padding: 15px; border-radius: 8px; background: #f8fafc; border: 1px solid #cbd5e1; margin: 0 5px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
    <div class="header">
        <h2>BÁO CÁO TÀI CHÍNH THÁNG <?php echo htmlspecialchars($month . '/' . $year); ?></h2>
        <p>Hệ thống Quản lý Chi tiêu CashManager</p>
    </div>
    <div class="kpi-grid">
        <div class="kpi-box"><strong>Tổng thu nhập</strong><div style="color:#10b981;font-size:18px;margin-top:5px">Đang cập nhật</div></div>
        <div class="kpi-box"><strong>Tổng chi tiêu</strong><div style="color:#ef4444;font-size:18px;margin-top:5px">Đang cập nhật</div></div>
        <div class="kpi-box"><strong>Số dư ròng</strong><div style="color:#4f46e5;font-size:18px;margin-top:5px">Đang cập nhật</div></div>
    </div>
    <p><em>(Trang mẫu chuẩn in PDF đang sẵn sàng để hoàn thiện nội dung bảng chi tiết)</em></p>
    <script>
        // Tự động bật hộp thoại in khi người dùng tải trang
        if (window.location.search.includes('print=1')) {
            window.print();
        }
    </script>
</body>
</html>
