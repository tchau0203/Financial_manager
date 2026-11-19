<?php
/**
 * API Nhập dữ liệu giao dịch từ file CSV / Excel
 * Phụ trách: NG Bảo (Backend)
 * Phương thức: POST (multipart/form-data)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);
}

// TODO (NG Bảo):
// 1. Kiểm tra $_FILES['file'] có tồn tại và đúng định dạng (.csv, .xlsx)
// 2. Mở file CSV, đọc từng dòng (Bỏ qua dòng tiêu đề)
// 3. Map tên danh mục (Ăn uống, Lương...) với bảng categories để lấy category_id
// 4. Dùng PDO Transaction để INSERT an toàn hàng loạt vào bảng transactions
// 5. Trả về JSON: ['success' => true, 'imported_count' => $count]

jsonResponse([
    'success' => true,
    'message' => 'Endpoint import_csv sẵn sàng cho NG Bảo tích hợp code'
]);
