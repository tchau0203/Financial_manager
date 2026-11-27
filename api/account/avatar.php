<?php
// POST /api/account/avatar.php
// Quản lý ảnh đại diện người dùng:
// 1. Tải lên tệp ảnh từ máy (multipart/form-data: key 'avatar_file')
// 2. Cập nhật đường link ảnh trực tiếp (JSON hoặc form: key 'avatar_url')
// 3. Xóa ảnh đại diện (đặt về null để dùng chữ cái đầu mặc định)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$userId = requireAuth();
$pdo = getDbConnection();

// Đảm bảo cột avatar_url đã tồn tại trong bảng users
try {
    $pdo->query("SELECT avatar_url FROM users LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) DEFAULT NULL AFTER currency");
    } catch (Throwable $ex) {}
}

$avatarUrl = null;

// Trường hợp 1: Tải lên file ảnh
if (!empty($_FILES['avatar_file']['name'])) {
    $file = $_FILES['avatar_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Tải lên tệp thất bại, mã lỗi: ' . $file['error']], 400);
    }
    // Giới hạn kích thước 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(['error' => 'Kích thước ảnh tối đa là 5MB'], 400);
    }

    // Kiểm tra định dạng ảnh
    $tmpName = $file['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    if (!isset($allowed[$mime])) {
        jsonResponse(['error' => 'Chỉ chấp nhận định dạng ảnh JPG, PNG, WEBP hoặc GIF'], 400);
    }

    $ext = $allowed[$mime];
    $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $fileName = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        jsonResponse(['error' => 'Không thể lưu tệp ảnh trên máy chủ'], 500);
    }

    // Đường dẫn tương đối từ web root
    $avatarUrl = '../uploads/avatars/' . $fileName;
} else {
    // Trường hợp 2: Gửi URL hoặc JSON
    $input = getJsonInput();
    if (empty($input)) {
        $input = $_POST;
    }
    if (array_key_exists('avatar_url', $input)) {
        $url = trim((string)$input['avatar_url']);
        if ($url === '') {
            $avatarUrl = null; // Xóa ảnh đại diện
        } else {
            if (!preg_match('/^(https?:\/\/|\.\.\/|\/|data:image\/)/i', $url)) {
                jsonResponse(['error' => 'Đường dẫn ảnh không hợp lệ (cần bắt đầu bằng http:// hoặc https://)'], 400);
            }
            if (mb_strlen($url) > 500) {
                jsonResponse(['error' => 'Đường dẫn ảnh quá dài (tối đa 500 ký tự)'], 400);
            }
            $avatarUrl = $url;
        }
    } else {
        jsonResponse(['error' => 'Vui lòng chọn tệp ảnh hoặc nhập đường dẫn ảnh'], 400);
    }
}

// Cập nhật CSDL
$stmt = $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
$stmt->execute([$avatarUrl, $userId]);
$_SESSION['avatar_url'] = $avatarUrl;

jsonResponse([
    'success'    => true,
    'message'    => 'Cập nhật ảnh đại diện thành công',
    'avatar_url' => $avatarUrl
]);
