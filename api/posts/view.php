<?php
// GET /api/posts/view.php?file=xxx.jpg
// Phục vụ hiển thị ảnh an toàn, tránh lỗi 500 / 403 trên các hosting shared như InfinityFree
while (ob_get_level() > 0) { ob_end_clean(); }

$file = basename($_GET['file'] ?? '');
if (!$file || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
    http_response_code(400);
    exit('Invalid file name');
}

$path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR . $file;
if (!file_exists($path)) {
    // Thử đường dẫn tương đối dự phòng
    $alt = __DIR__ . '/../../uploads/posts/' . $file;
    if (file_exists($alt)) {
        $path = $alt;
    } else {
        http_response_code(404);
        exit('File not found');
    }
}

@chmod($path, 0644);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif'
];

$contentType = $mimes[$ext] ?? 'image/jpeg';

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=604800'); // Cache 7 ngày
header('Access-Control-Allow-Origin: *');

readfile($path);
exit;
