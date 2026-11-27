<?php
// GET /api/transaction/map.php?month=YYYY-MM[&category=<id>][&type=expense|income]
// Trả về các điểm chi tiêu (hoặc thu nhập) đã gom nhóm theo tọa độ làm tròn 2 chữ số thập phân (~1km).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

// Xác thực bằng cơ chế session hiện có: chưa đăng nhập => 401
$userId = requireAuth();

// --- Kiểm tra tham số ---
$month = $_GET['month'] ?? date('Y-m');
if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    jsonResponse(['success' => false, 'message' => 'Tháng không hợp lệ, định dạng đúng là YYYY-MM'], 400);
}

$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'expense', 'income'], true)) {
    jsonResponse(['success' => false, 'message' => 'Loại giao dịch không hợp lệ (hỗ trợ: all, expense, income)'], 400);
}

$categoryId = null;
if (isset($_GET['category']) && $_GET['category'] !== '') {
    $categoryId = filter_var($_GET['category'], FILTER_VALIDATE_INT);
    if ($categoryId === false || $categoryId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Danh mục không hợp lệ'], 400);
    }
}

// Khoảng ngày [đầu tháng, đầu tháng sau) - dùng so sánh trực tiếp để tận dụng chỉ mục
$from = DateTime::createFromFormat('!Y-m-d', $month . '-01');
$to   = (clone $from)->modify('+1 month');

// Điều kiện loại giao dịch
$typeCondition = ($type === 'all') ? "t.type IN ('expense', 'income')" : "t.type = ?";

// --- Truy vấn tham số hóa (không nối chuỗi dữ liệu người dùng vào SQL) ---
$sql = "SELECT ROUND(AVG(t.latitude), 6)  AS lat,
               ROUND(AVG(t.longitude), 6) AS lng,
               MAX(NULLIF(t.location_name, '')) AS location_name,
               SUM(t.amount) AS total,
               SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) AS total_expense,
               SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) AS total_income,
               COUNT(*)      AS `count`,
               ROUND(t.latitude, 2)  AS cell_lat,
               ROUND(t.longitude, 2) AS cell_lng
        FROM transactions t
        WHERE t.user_id = ?
          AND $typeCondition
          AND t.latitude IS NOT NULL
          AND t.longitude IS NOT NULL
          AND t.transaction_date >= ?
          AND t.transaction_date < ?";

$params = [$userId];
if ($type !== 'all') {
    $params[] = $type;
}
$params[] = $from->format('Y-m-d');
$params[] = $to->format('Y-m-d');

if ($categoryId !== null) {
    $sql .= ' AND t.category_id = ?';
    $params[] = $categoryId;
}

// Gom nhóm theo ô lưới ~1km (làm tròn 2 chữ số thập phân)
$sql .= ' GROUP BY ROUND(t.latitude, 2), ROUND(t.longitude, 2) ORDER BY total DESC';

// Truy vấn chi tiết từng giao dịch (hiển thị khi bấm marker) - cùng điều kiện lọc, cũng tham số hóa.
// Giới hạn 500 giao dịch mới nhất mỗi lần để phản hồi luôn nhẹ.
$detailSql = "SELECT t.id, t.type, t.transaction_date, t.amount, t.note, t.payment_method,
                     c.name AS category_name, c.color AS category_color,
                     ROUND(t.latitude, 2) AS cell_lat, ROUND(t.longitude, 2) AS cell_lng
              FROM transactions t
              JOIN categories c ON c.id = t.category_id
              WHERE t.user_id = ?
                AND $typeCondition
                AND t.latitude IS NOT NULL
                AND t.longitude IS NOT NULL
                AND t.transaction_date >= ?
                AND t.transaction_date < ?"
           . ($categoryId !== null ? ' AND t.category_id = ?' : '')
           . ' ORDER BY t.transaction_date DESC, t.id DESC LIMIT 500';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $detailStmt = $pdo->prepare($detailSql);
    $detailStmt->execute($params);
    $detailRows = $detailStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[map] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Không thể tải dữ liệu bản đồ, vui lòng thử lại sau'], 500);
}

// Gắn giao dịch vào đúng ô lưới ~1km của nó (tối đa 20 giao dịch mới nhất mỗi điểm)
$itemsByCell = [];
foreach ($detailRows as $d) {
    $key = sprintf('%.2f,%.2f', (float)$d['cell_lat'], (float)$d['cell_lng']);
    if (!isset($itemsByCell[$key])) $itemsByCell[$key] = [];
    if (count($itemsByCell[$key]) >= 20) continue;
    $itemsByCell[$key][] = [
        'id'             => (int)$d['id'],
        'type'           => $d['type'],
        'date'           => $d['transaction_date'],
        'amount'         => (float)$d['amount'],
        'note'           => $d['note'],
        'payment_method' => $d['payment_method'],
        'category_name'  => $d['category_name'],
        'category_color' => $d['category_color'],
    ];
}

$points = array_map(static function ($r) use ($itemsByCell) {
    $key = sprintf('%.2f,%.2f', (float)$r['cell_lat'], (float)$r['cell_lng']);
    $exp = (float)($r['total_expense'] ?? 0);
    $inc = (float)($r['total_income'] ?? 0);
    $pType = ($exp > 0 && $inc > 0) ? 'mixed' : ($inc > 0 ? 'income' : 'expense');
    return [
        'lat'           => (float)$r['lat'],
        'lng'           => (float)$r['lng'],
        'location_name' => $r['location_name'],
        'total'         => (float)$r['total'],
        'total_expense' => $exp,
        'total_income'  => $inc,
        'point_type'    => $pType,
        'count'         => (int)$r['count'],
        'items'         => $itemsByCell[$key] ?? [],
    ];
}, $rows);

jsonResponse(['success' => true, 'data' => ['month' => $month, 'points' => $points]]);
