<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/feed_schema.php';

[$userId, $pdo] = feedBootstrap('POST');

// ---- 1. Đọc & kiểm tra dữ liệu (multipart/form-data) ----
$type       = $_POST['type'] ?? '';
$amountRaw  = $_POST['amount'] ?? '';
$categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
$caption    = trim((string)($_POST['caption'] ?? ''));
$isPublic   = (($_POST['is_public'] ?? '1') === '0') ? 0 : 1;
$saveTx     = (($_POST['save_transaction'] ?? '1') === '0') ? false : true;

// Ngày giao dịch (mặc định hôm nay, không cho ngày tương lai)
$txDate = (string)($_POST['transaction_date'] ?? '');
$dObj   = $txDate !== '' ? DateTime::createFromFormat('!Y-m-d', $txDate) : false;
if ($txDate === '' || !$dObj || $dObj->format('Y-m-d') !== $txDate || $txDate > date('Y-m-d')) {
    $txDate = date('Y-m-d');
}
// Vị trí (không bắt buộc)
$loc = parseLocationInput($_POST);
if (!$loc['ok']) {
    jsonResponse(['success' => false, 'message' => 'Vị trí không hợp lệ'], 400);
}

if (!in_array($type, ['income', 'expense'], true)) {
    jsonResponse(['success' => false, 'message' => 'Loại giao dịch không hợp lệ'], 400);
}
$amountClean = str_replace([',', ' '], '', (string)$amountRaw);
if (!is_numeric($amountClean) || (float)$amountClean <= 0 || (float)$amountClean > 9999999999999) {
    jsonResponse(['success' => false, 'message' => 'Số tiền phải là số lớn hơn 0'], 400);
}
$amount = round((float)$amountClean, 2);
if (mb_strlen($caption) > 500) {
    jsonResponse(['success' => false, 'message' => 'Chú thích tối đa 500 ký tự'], 400);
}
if (!$categoryId) {
    jsonResponse(['success' => false, 'message' => 'Vui lòng chọn danh mục'], 400);
}
$st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND type = ? AND (user_id IS NULL OR user_id = ?)');
$st->execute([$categoryId, $type, $userId]);
if (!$st->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Danh mục không hợp lệ'], 400);
}

// ---- 2. Kiểm tra ảnh upload: jpg/png/webp, tối đa 5MB ----
if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    jsonResponse(['success' => false, 'message' => 'Vui lòng chọn hoặc chụp một bức ảnh'], 400);
}
$file = $_FILES['image'];
$errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
    jsonResponse(['success' => false, 'message' => 'Ảnh vượt quá 5MB'], 413);
}
if ($errCode !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    jsonResponse(['success' => false, 'message' => 'Tải ảnh lên thất bại, vui lòng thử lại'], 400);
}
if ($file['size'] > 5 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'Ảnh vượt quá 5MB'], 413);
}

// Kiểm tra MIME thật của file (không tin tên file / Content-Type client gửi)
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = '';
if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($fi, $file['tmp_name']);
    finfo_close($fi);
}
$info = @getimagesize($file['tmp_name']);
if ($mime === '' && $info) $mime = $info['mime'];
if (!isset($allowed[$mime]) || !$info) {
    jsonResponse(['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP'], 415);
}

$dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts';
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
    jsonResponse(['success' => false, 'message' => 'Không tạo được thư mục lưu ảnh'], 500);
}
// {user_id}_{timestamp}.ext (thêm hậu tố ngẫu nhiên để 2 ảnh cùng giây không đè nhau)
$fileName = $userId . '_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $allowed[$mime];
$target   = $dir . DIRECTORY_SEPARATOR . $fileName;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    jsonResponse(['success' => false, 'message' => 'Không lưu được ảnh lên máy chủ'], 500);
}
@chmod($dir, 0755);
@chmod($target, 0644);
$imagePath = 'uploads/posts/' . $fileName;

// ---- 3. Lưu DB (giao dịch + bài đăng cùng 1 transaction) ----
try {
    $pdo->beginTransaction();

    $txId = null;
    if ($saveTx) {
        $ins = $pdo->prepare('INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, payment_method, note, latitude, longitude, location_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$userId, $categoryId, $type, $amount, $txDate, 'Tiền mặt', $caption !== '' ? $caption : 'Đăng từ Bảng tin', $loc['lat'], $loc['lng'], $loc['name']]);
        $txId = (int)$pdo->lastInsertId();
    }

    $ins = $pdo->prepare('INSERT INTO posts (user_id, transaction_id, image_path, caption, amount, type, category_id, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([$userId, $txId, $imagePath, $caption !== '' ? $caption : null, $amount, $type, $categoryId, $isPublic]);
    $postId = (int)$pdo->lastInsertId();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink($target);
    error_log('[posts/create] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Không thể đăng bài, vui lòng thử lại'], 500);
}

if ($type === 'expense' && $saveTx) {
    try { checkBudgetAlerts($userId, $pdo, (int)date('n'), (int)date('Y')); } catch (Throwable $e) {}
}

jsonResponse([
    'success' => true,
    'message' => 'Đã đăng bài lên bảng tin',
    'data'    => ['id' => $postId, 'transaction_id' => $txId, 'image_path' => $imagePath]
], 201);
