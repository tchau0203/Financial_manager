<?php
/**
 * API Chatbot Cố Vấn Tài Chính AI Tương Tác
 * Phụ trách: Thành Long (Backend)
 * Phương thức: POST /api/ai/chat.php
 * Payload: { "question": "Nội dung câu hỏi của người dùng" }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ (yêu cầu POST)'], 405);
}

$data = getJsonInput();
$question = trim((string)($data['question'] ?? ''));

if ($question === '') {
    jsonResponse(['error' => 'Vui lòng nhập câu hỏi cho chuyên gia AI'], 400);
}

// TODO (Thành Long):
// 1. Lấy dữ liệu tài chính của user trong tháng hiện tại (Tổng thu, tổng chi, số dư, danh mục chi nhiều nhất).
// 2. Xây dựng prompt kèm ngữ cảnh tài chính thực tế và câu hỏi của user.
// 3. Gọi Gemini API (cURL CURLOPT_SSL_VERIFYPEER => false).
// 4. Cơ chế Fallback nếu chưa có API key hoặc lỗi mạng.

jsonResponse([
    'success' => true,
    'message' => 'Đã tiếp nhận câu hỏi của bạn',
    'data' => [
        'question' => $question,
        'answer'   => "Tôi là trợ lý tài chính AI của bạn. Dựa trên số liệu tháng này, bạn nên theo dõi sát các khoản chi tiêu hàng ngày để duy trì số dư an toàn."
    ]
]);
