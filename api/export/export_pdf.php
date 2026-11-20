<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

// 1. Lấy thông số tháng và năm
$month = filter_var($_GET['month'] ?? $_POST['month'] ?? date('n'), FILTER_VALIDATE_INT);
$year  = filter_var($_GET['year']  ?? $_POST['year']  ?? date('Y'), FILTER_VALIDATE_INT);

if (!$month || $month < 1 || $month > 12) $month = (int)date('n');
if (!$year  || $year < 2000 || $year > 2100) $year = (int)date('Y');

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = (new DateTimeImmutable($startDate))->modify('+1 month')->format('Y-m-d');

// 2. Lấy thông tin người dùng
$userStmt = $pdo->prepare('SELECT full_name, username, email, phone, currency FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch() ?: [];
$fullName = !empty($user['full_name']) ? $user['full_name'] : ($user['username'] ?? 'Người dùng');
$currency = $user['currency'] ?? 'VND';

// 3. Tổng hợp số liệu KPI tháng
$summaryStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense,
        COUNT(id) AS total_transactions
    FROM transactions
    WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?
");
$summaryStmt->execute([$userId, $startDate, $endDate]);
$summary = $summaryStmt->fetch() ?: ['total_income' => 0, 'total_expense' => 0, 'total_transactions' => 0];

$totalIncome  = (float)$summary['total_income'];
$totalExpense = (float)$summary['total_expense'];
$netBalance   = $totalIncome - $totalExpense;
$savingRate   = $totalIncome > 0 ? round(($netBalance / $totalIncome) * 100, 1) : 0;
$totalTx      = (int)$summary['total_transactions'];

// 4. Cơ cấu theo danh mục Chi tiêu
$expenseCatStmt = $pdo->prepare("
    SELECT c.name, c.color, c.icon, SUM(t.amount) AS amount, COUNT(t.id) as count
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date >= ? AND t.transaction_date < ?
    GROUP BY c.id, c.name, c.color, c.icon
    ORDER BY amount DESC
");
$expenseCatStmt->execute([$userId, $startDate, $endDate]);
$expenseCategories = $expenseCatStmt->fetchAll();

// 5. Cơ cấu theo danh mục Thu nhập
$incomeCatStmt = $pdo->prepare("
    SELECT c.name, c.color, c.icon, SUM(t.amount) AS amount, COUNT(t.id) as count
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'income' AND t.transaction_date >= ? AND t.transaction_date < ?
    GROUP BY c.id, c.name, c.color, c.icon
    ORDER BY amount DESC
");
$incomeCatStmt->execute([$userId, $startDate, $endDate]);
$incomeCategories = $incomeCatStmt->fetchAll();

// 6. Danh sách chi tiết toàn bộ giao dịch trong tháng
$listStmt = $pdo->prepare("
    SELECT t.id, t.type, t.amount, t.transaction_date, t.payment_method, t.note, c.name AS category_name
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.transaction_date >= ? AND t.transaction_date < ?
    ORDER BY t.transaction_date ASC, t.id ASC
");
$listStmt->execute([$userId, $startDate, $endDate]);
$transactions = $listStmt->fetchAll();

// Nhận hình ảnh biểu đồ nếu gửi qua POST hoặc GET (base64)
$chartImage = $_POST['chart_image'] ?? $_GET['chart_image'] ?? null;

// Hàm định dạng tiền tệ
function formatMoney($amount, $curr = 'đ') {
    return number_format($amount, 0, ',', '.') . ' ' . $curr;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Tài Chính Tháng <?= sprintf('%02d/%04d', $month, $year) ?> - <?= htmlspecialchars($fullName) ?></title>
    <style>
        /* ============ CẤU HÌNH MẪU IN CHUẨN A4 ============ */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Thanh điều khiển nổi khi xem trên trình duyệt */
        .no-print-bar {
            background: #1e293b;
            color: #fff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 9999;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-print {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-print:hover { background: #059669; }

        .btn-close {
            background: #64748b;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 8px;
            text-decoration: none;
        }

        /* Khung trang in A4 */
        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 20mm 15mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 4px;
        }

        @media print {
            body { background: #ffffff; }
            .no-print, .no-print-bar { display: none !important; }
            .page-container {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }

        /* Header báo cáo */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header .title-block h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header .title-block p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }
        .header .meta-block {
            text-align: right;
            font-size: 12px;
            color: #475569;
        }
        .header .meta-block strong { color: #0f172a; }

        /* Khối chỉ số KPI */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .kpi-card .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }
        .kpi-card .value {
            font-size: 16px;
            font-weight: 700;
        }
        .kpi-income .value  { color: #10b981; }
        .kpi-expense .value { color: #ef4444; }
        .kpi-balance .value { color: #3b82f6; }
        .kpi-saving .value  { color: #8b5cf6; }

        /* Tiêu đề mục */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 20px 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
        }

        /* Bảng dữ liệu */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 12px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
        }
        tr:nth-child(even) td { background: #fafafa; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .badge-income {
            background: #d1fae5;
            color: #065f46;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-expense {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        /* Phân bổ tỷ lệ danh mục */
        .progress-bar-bg {
            background: #e2e8f0;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 3px;
        }

        /* Khối biểu đồ ảnh */
        .chart-box {
            text-align: center;
            margin: 16px 0;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
        }
        .chart-box img {
            max-width: 100%;
            max-height: 220px;
            object-fit: contain;
        }

        /* Chân trang */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #94a3b8;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            text-align: center;
        }
        .sig-box { width: 45%; }
        .sig-space { height: 50px; }
    </style>
</head>
<body>

    <!-- Thanh tác vụ trên web (ẩn khi in ra PDF) -->
    <div class="no-print-bar">
        <div>
            <strong>Xem trước Báo Cáo Tài Chính</strong> — Tháng <?= sprintf('%02d/%04d', $month, $year) ?>
        </div>
        <div>
            <button class="btn-close" onclick="window.close()">Đóng</button>
            <button class="btn-print" onclick="window.print()">🖨️ In / Tải PDF</button>
        </div>
    </div>

    <!-- Trang in chuẩn A4 -->
    <div class="page-container">

        <!-- HEADER -->
        <div class="header">
            <div class="title-block">
                <h1>Báo Cáo Tài Chính Tháng <?= sprintf('%02d/%04d', $month, $year) ?></h1>
                <p>Hệ thống Quản lý Chi tiêu Cá nhân — Financial Manager</p>
            </div>
            <div class="meta-block">
                <div>Chủ tài khoản: <strong><?= htmlspecialchars($fullName) ?></strong></div>
                <div>Email: <?= htmlspecialchars($user['email'] ?? '—') ?></div>
                <div>Ngày xuất báo cáo: <?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <!-- KPI TỔNG QUAN -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-income">
                <div class="label">Tổng Thu Nhập</div>
                <div class="value">+<?= formatMoney($totalIncome) ?></div>
            </div>
            <div class="kpi-card kpi-expense">
                <div class="label">Tổng Chi Tiêu</div>
                <div class="value">-<?= formatMoney($totalExpense) ?></div>
            </div>
            <div class="kpi-card kpi-balance">
                <div class="label">Số Dư Ròng</div>
                <div class="value" style="color: <?= $netBalance >= 0 ? '#10b981' : '#ef4444' ?>;">
                    <?= ($netBalance >= 0 ? '+' : '') . formatMoney($netBalance) ?>
                </div>
            </div>
            <div class="kpi-card kpi-saving">
                <div class="label">Tỷ Lệ Tiết Kiệm</div>
                <div class="value"><?= $savingRate ?>%</div>
            </div>
        </div>

        <!-- HÌNH ẢNH BIỂU ĐỒ (NẾU CÓ TRUYỀN VÀO) -->
        <?php if (!empty($chartImage)): ?>
        <div class="chart-box">
            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 6px;">BIỂU ĐỒ PHÂN BỔ TÀI CHÍNH</div>
            <img src="<?= htmlspecialchars($chartImage) ?>" alt="Biểu đồ phân bổ chi tiêu">
        </div>
        <?php endif; ?>

        <!-- CƠ CẤU CHI TIÊU THEO DANH MỤC -->
        <div class="section-title">
            <span>Cơ Cấu Chi Tiêu Theo Danh Mục</span>
            <span style="font-size: 12px; font-weight: normal; color: #64748b;">Tổng: <?= formatMoney($totalExpense) ?></span>
        </div>
        <?php if (!empty($expenseCategories)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">Danh Mục</th>
                    <th style="width: 20%;" class="text-center">Số Giao Dịch</th>
                    <th style="width: 25%;" class="text-right">Số Tiền</th>
                    <th style="width: 20%;" class="text-right">Tỷ Trọng (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenseCategories as $cat): 
                    $pct = $totalExpense > 0 ? round(($cat['amount'] / $totalExpense) * 100, 1) : 0;
                    $color = !empty($cat['color']) ? $cat['color'] : '#ef4444';
                ?>
                <tr>
                    <td>
                        <strong style="color: #334155;"><?= htmlspecialchars($cat['name']) ?></strong>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $color ?>;"></div>
                        </div>
                    </td>
                    <td class="text-center"><?= $cat['count'] ?></td>
                    <td class="text-right" style="font-weight: 600; color: #ef4444;"><?= formatMoney($cat['amount']) ?></td>
                    <td class="text-right"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #94a3b8; font-style: italic; margin-bottom: 16px;">Không có dữ liệu chi tiêu trong tháng này.</p>
        <?php endif; ?>

        <!-- CƠ CẤU THU NHẬP THEO DANH MỤC -->
        <?php if (!empty($incomeCategories)): ?>
        <div class="section-title">
            <span>Cơ Cấu Thu Nhập Theo Danh Mục</span>
            <span style="font-size: 12px; font-weight: normal; color: #64748b;">Tổng: <?= formatMoney($totalIncome) ?></span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">Nguồn Thu</th>
                    <th style="width: 20%;" class="text-center">Số Giao Dịch</th>
                    <th style="width: 25%;" class="text-right">Số Tiền</th>
                    <th style="width: 20%;" class="text-right">Tỷ Trọng (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomeCategories as $cat): 
                    $pct = $totalIncome > 0 ? round(($cat['amount'] / $totalIncome) * 100, 1) : 0;
                    $color = !empty($cat['color']) ? $cat['color'] : '#10b981';
                ?>
                <tr>
                    <td>
                        <strong style="color: #334155;"><?= htmlspecialchars($cat['name']) ?></strong>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $color ?>;"></div>
                        </div>
                    </td>
                    <td class="text-center"><?= $cat['count'] ?></td>
                    <td class="text-right" style="font-weight: 600; color: #10b981;"><?= formatMoney($cat['amount']) ?></td>
                    <td class="text-right"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- BẢNG LỊCH SỬ GIAO DỊCH TRONG THÁNG -->
        <div class="section-title">
            <span>Lịch Sử Giao Dịch Trong Tháng</span>
            <span style="font-size: 12px; font-weight: normal; color: #64748b;">Tổng số: <?= count($transactions) ?> bản ghi</span>
        </div>
        <?php if (!empty($transactions)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Ngày</th>
                    <th style="width: 10%;">Loại</th>
                    <th style="width: 20%;">Danh Mục</th>
                    <th style="width: 18%;" class="text-right">Số Tiền</th>
                    <th style="width: 16%;">Phương Thức</th>
                    <th style="width: 24%;">Ghi Chú</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): 
                    $isIncome = ($tx['type'] === 'income');
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($tx['transaction_date'])) ?></td>
                    <td>
                        <span class="<?= $isIncome ? 'badge-income' : 'badge-expense' ?>">
                            <?= $isIncome ? 'Thu' : 'Chi' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($tx['category_name']) ?></td>
                    <td class="text-right" style="font-weight: 600; color: <?= $isIncome ? '#10b981' : '#ef4444' ?>;">
                        <?= ($isIncome ? '+' : '-') . formatMoney($tx['amount']) ?>
                    </td>
                    <td><?= htmlspecialchars($tx['payment_method'] ?: 'Tiền mặt') ?></td>
                    <td style="color: #64748b;"><?= htmlspecialchars($tx['note'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #94a3b8; font-style: italic;">Chưa phát sinh giao dịch nào trong tháng này.</p>
        <?php endif; ?>

        <!-- CHỮ KÝ XÁC NHẬN -->
        <div class="signatures">
            <div class="sig-box">
                <div><strong>Người Lập Báo Cáo</strong></div>
                <div style="font-size: 11px; color: #64748b;">(Ký và ghi rõ họ tên)</div>
                <div class="sig-space"></div>
                <div><?= htmlspecialchars($fullName) ?></div>
            </div>
            <div class="sig-box">
                <div><strong>Xác Nhận Hệ Thống</strong></div>
                <div style="font-size: 11px; color: #64748b;">(Dữ liệu kết xuất tự động)</div>
                <div class="sig-space"></div>
                <div>Financial Manager System</div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <div>Báo cáo tài chính cá nhân tự động — Tệp chuẩn A4</div>
            <div>Trang 1 / 1</div>
        </div>

    </div>

    <!-- Tự động mở hộp thoại in nếu có tham số autoprint=1 -->
    <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == '1'): ?>
    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 300);
        });
    </script>
    <?php endif; ?>

</body>
</html>
