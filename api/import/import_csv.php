<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

// 1. Kiểm tra file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['file']['error'] ?? 'FILE_MISSING';
    jsonResponse(['success' => false, 'message' => 'Vui lòng chọn tệp CSV hợp lệ để tải lên', 'error_code' => $errorCode], 400);
}

$file = $_FILES['file'];
$maxFileSize = 5 * 1024 * 1024; // Tối đa 5MB

if ($file['size'] > $maxFileSize) {
    jsonResponse(['success' => false, 'message' => 'Kích thước tệp vượt quá giới hạn cho phép (tối đa 5MB)'], 400);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'])) {
    jsonResponse(['success' => false, 'message' => 'Định dạng tệp không được hỗ trợ. Vui lòng tải lên tệp .csv'], 400);
}

// 2. Mở file và phát hiện dấu phân cách (delimiter)
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    jsonResponse(['success' => false, 'message' => 'Không thể đọc tệp đã tải lên'], 500);
}

// Đọc dòng đầu tiên để loại bỏ BOM và phát hiện delimiter
$firstLine = fgets($handle);
if ($firstLine === false) {
    fclose($handle);
    jsonResponse(['success' => false, 'message' => 'Tệp rỗng hoặc không có dữ liệu'], 400);
}

// Loại bỏ UTF-8 BOM nếu có
if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
    $firstLine = substr($firstLine, 3);
}

// Tự động phát hiện dấu phân cách: phẩy, chấm phẩy hoặc tab
$delimiter = ',';
if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
    $delimiter = ';';
} elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
    $delimiter = "\t";
}

// Parse header từ dòng đầu
$headers = str_getcsv($firstLine, $delimiter);
if (!$headers || count($headers) === 0) {
    fclose($handle);
    jsonResponse(['success' => false, 'message' => 'Không thể phân tích tiêu đề cột trong tệp'], 400);
}

// Hàm chuẩn hóa chuỗi tiêu đề để ánh xạ cột linh hoạt
function normalizeHeader($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $unicode = [
        'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd' => 'đ',
        'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i' => 'í|ì|ỉ|ĩ|ị',
        'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y' => 'ý|ỳ|ỷ|ỹ|ỵ'
    ];
    foreach ($unicode as $nonAccent => $accent) {
        $str = preg_replace("/($accent)/u", $nonAccent, $str);
    }
    return preg_replace('/[^a-z0-9]/', '', $str);
}

$colMap = [
    'date'     => -1,
    'type'     => -1,
    'category' => -1,
    'amount'   => -1,
    'method'   => -1,
    'note'     => -1
];

foreach ($headers as $idx => $headerText) {
    $norm = normalizeHeader($headerText);
    if (in_array($norm, ['ngay', 'ngaygiaodich', 'date', 'transactiondate', 'thoigian'])) {
        $colMap['date'] = $idx;
    } elseif (in_array($norm, ['loai', 'loaigiaodich', 'type', 'transactiontype'])) {
        $colMap['type'] = $idx;
    } elseif (in_array($norm, ['danhmuc', 'tendanhmuc', 'category', 'categoryname'])) {
        $colMap['category'] = $idx;
    } elseif (in_array($norm, ['sotien', 'amount', 'tien', 'giatri'])) {
        $colMap['amount'] = $idx;
    } elseif (in_array($norm, ['phuongthuc', 'phuongthucthanhtoan', 'method', 'paymentmethod', 'hinhthuc'])) {
        $colMap['method'] = $idx;
    } elseif (in_array($norm, ['ghichu', 'note', 'mota', 'noidung', 'description'])) {
        $colMap['note'] = $idx;
    }
}

// Fallback: nếu tiêu đề theo thứ tự xuất chuẩn [ID, Loại, Danh mục, Số tiền, Ngày, Phương thức, Ghi chú]
if ($colMap['amount'] === -1 && count($headers) >= 4) {
    if ($colMap['date'] === -1) $colMap['date'] = 4;
    if ($colMap['type'] === -1) $colMap['type'] = 1;
    if ($colMap['category'] === -1) $colMap['category'] = 2;
    if ($colMap['amount'] === -1) $colMap['amount'] = 3;
    if ($colMap['method'] === -1) $colMap['method'] = 5;
    if ($colMap['note'] === -1) $colMap['note'] = 6;
}

if ($colMap['amount'] === -1 || $colMap['date'] === -1) {
    fclose($handle);
    jsonResponse([
        'success' => false,
        'message' => 'Không tìm thấy các cột bắt buộc (Ngày giao dịch, Số tiền). Vui lòng kiểm tra lại cấu trúc file CSV.'
    ], 400);
}

// 3. Chuẩn bị ánh xạ Danh mục (Category Mapping)
$pdo = getDbConnection();

$catStmt = $pdo->prepare('SELECT id, name, type FROM categories WHERE user_id IS NULL OR user_id = ?');
$catStmt->execute([$userId]);
$existingCats = $catStmt->fetchAll();

$categoryMap = [];
foreach ($existingCats as $c) {
    $key = mb_strtolower(trim($c['name']), 'UTF-8') . '|' . $c['type'];
    $categoryMap[$key] = (int)$c['id'];
}

// Tự động tìm hoặc tạo mới danh mục nếu chưa có
function getOrCreateCategoryId($pdo, $userId, &$categoryMap, $catName, $type) {
    $catName = trim($catName);
    if ($catName === '') {
        $catName = ($type === 'income') ? 'Thu nhập khác' : 'Chi tiêu khác';
    }
    $key = mb_strtolower($catName, 'UTF-8') . '|' . $type;
    if (isset($categoryMap[$key])) {
        return $categoryMap[$key];
    }

    $stmt = $pdo->prepare('INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, "fa-tag", "#6366F1")');
    $stmt->execute([$userId, $catName, $type]);
    $newId = (int)$pdo->lastInsertId();
    $categoryMap[$key] = $newId;
    return $newId;
}

// Chuẩn hóa ngày tháng
function parseDate($dateStr) {
    $dateStr = trim($dateStr);
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat("!$fmt", $dateStr);
        if ($d && $d->format($fmt) === $dateStr) {
            return $d->format('Y-m-d');
        }
    }
    $time = strtotime($dateStr);
    if ($time !== false && $time > 0) {
        return date('Y-m-d', $time);
    }
    return date('Y-m-d');
}

// Chuẩn hóa số tiền
function parseAmount($amountStr) {
    $amountStr = trim((string)$amountStr);
    $amountStr = preg_replace('/[^\d.,-]/u', '', $amountStr);

    if (strpos($amountStr, '.') !== false && strpos($amountStr, ',') !== false) {
        if (strrpos($amountStr, ',') > strrpos($amountStr, '.')) {
            $amountStr = str_replace('.', '', $amountStr);
            $amountStr = str_replace(',', '.', $amountStr);
        } else {
            $amountStr = str_replace(',', '', $amountStr);
        }
    } else {
        if (strpos($amountStr, '.') !== false) {
            $parts = explode('.', $amountStr);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $amountStr = str_replace('.', '', $amountStr);
            }
        } elseif (strpos($amountStr, ',') !== false) {
            $parts = explode(',', $amountStr);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $amountStr = str_replace(',', '', $amountStr);
            } else {
                $amountStr = str_replace(',', '.', $amountStr);
            }
        }
    }
    return abs((float)$amountStr);
}

// 4. Bắt đầu Transaction và thực hiện INSERT hàng loạt
$pdo->beginTransaction();
try {
    $insertStmt = $pdo->prepare('
        INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, payment_method, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');

    $importedCount = 0;
    $skippedCount  = 0;
    $hasExpense    = false;
    $latestExpenseDate = null;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($row) === 1 && empty($row[0])) continue;

        $amountRaw = $colMap['amount'] >= 0 ? ($row[$colMap['amount']] ?? '') : '';
        $amount = parseAmount($amountRaw);
        if ($amount <= 0) {
            $skippedCount++;
            continue;
        }

        $typeRaw = $colMap['type'] >= 0 ? mb_strtolower(trim($row[$colMap['type']] ?? ''), 'UTF-8') : '';
        if (in_array($typeRaw, ['thu', 'thu nhập', 'income', '+'])) {
            $type = 'income';
        } elseif (in_array($typeRaw, ['chi', 'chi tiêu', 'expense', '-'])) {
            $type = 'expense';
        } else {
            $type = 'expense';
        }

        $dateRaw = $colMap['date'] >= 0 ? ($row[$colMap['date']] ?? '') : '';
        $date = parseDate($dateRaw);

        $catName = $colMap['category'] >= 0 ? trim($row[$colMap['category']] ?? '') : '';
        $categoryId = getOrCreateCategoryId($pdo, $userId, $categoryMap, $catName, $type);

        $method = $colMap['method'] >= 0 ? trim($row[$colMap['method']] ?? '') : '';
        if ($method === '') $method = 'Tiền mặt';
        if (strlen($method) > 50) $method = mb_substr($method, 0, 50, 'UTF-8');

        $note = $colMap['note'] >= 0 ? trim($row[$colMap['note']] ?? '') : '';
        if (strlen($note) > 500) $note = mb_substr($note, 0, 500, 'UTF-8');

        $insertStmt->execute([
            $userId,
            $categoryId,
            $type,
            $amount,
            $date,
            $method,
            $note
        ]);

        $importedCount++;
        if ($type === 'expense') {
            $hasExpense = true;
            $latestExpenseDate = $date;
        }
    }

    fclose($handle);

    if ($importedCount === 0) {
        $pdo->rollBack();
        jsonResponse([
            'success' => false,
            'message' => 'Không có giao dịch hợp lệ nào được nhập từ tệp. Vui lòng kiểm tra lại dữ liệu.'
        ], 400);
    }

    // Commit toàn bộ bản ghi vào CSDL
    $pdo->commit();

    // Tự động kiểm tra cảnh báo hạn mức ngân sách nếu có phát sinh chi tiêu
    if ($hasExpense && $latestExpenseDate) {
        $d = DateTime::createFromFormat('Y-m-d', $latestExpenseDate);
        if ($d) {
            checkBudgetAlerts($userId, $pdo, (int)$d->format('n'), (int)$d->format('Y'));
        }
    }

    jsonResponse([
        'success' => true,
        'message' => "Đã nhập thành công $importedCount giao dịch vào hệ thống" . ($skippedCount > 0 ? " (bỏ qua $skippedCount dòng không hợp lệ)" : ""),
        'data'    => [
            'imported_count' => $importedCount,
            'skipped_count'  => $skippedCount
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_resource($handle)) {
        fclose($handle);
    }
    jsonResponse([
        'success' => false,
        'message' => 'Lỗi khi nhập dữ liệu: ' . $e->getMessage()
    ], 500);
}
