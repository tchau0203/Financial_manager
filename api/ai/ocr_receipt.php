<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

// Kiểm tra phương thức HTTP
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức yêu cầu phải là POST'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Xác thực đăng nhập
$userId = requireAuth();
$pdo    = getDbConnection();

// 1. Tiếp nhận file ảnh upload
$file = $_FILES['image'] ?? $_FILES['file'] ?? $_FILES['receipt'] ?? null;

if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    jsonResponse([
        'success' => false,
        'message' => 'Vui lòng chọn hoặc tải lên hình ảnh hóa đơn cần quét'
    ], 400);
}

// Kiểm tra mã lỗi upload PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Kích thước tệp vượt quá giới hạn cho phép của máy chủ (upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE  => 'Kích thước tệp vượt quá giới hạn biểu mẫu',
        UPLOAD_ERR_PARTIAL    => 'Tệp chỉ được tải lên một phần. Vui lòng thử lại',
        UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm trên máy chủ',
        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi tệp lên đĩa máy chủ',
        UPLOAD_ERR_EXTENSION  => 'Quá trình tải tệp bị chặn bởi tiện ích mở rộng máy chủ'
    ];
    $msg = $errorMessages[$file['error']] ?? 'Lỗi không xác định khi tải ảnh lên';
    jsonResponse(['success' => false, 'message' => $msg], 400);
}

// 2. Kiểm tra kích thước tối đa 5MB (5 * 1024 * 1024 bytes)
$maxFileSize = 5 * 1024 * 1024;
if ($file['size'] > $maxFileSize) {
    jsonResponse([
        'success' => false,
        'message' => 'Kích thước ảnh tối đa là 5MB. Vui lòng chọn ảnh dung lượng nhỏ hơn'
    ], 400);
}

// 3. Kiểm tra định dạng tệp (jpg, jpeg, png, webp)
$fileExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

if (!in_array($fileExt, $allowedExtensions, true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Định dạng ảnh không được hỗ trợ. Vui lòng tải lên ảnh có đuôi .jpg, .jpeg, .png, .webp'
    ], 400);
}

// Kiểm tra MIME type thực tế của tệp
$mimeType = null;
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}
if (!$mimeType && function_exists('mime_content_type')) {
    $mimeType = mime_content_type($file['tmp_name']);
}
if (!$mimeType) {
    $mimeMap = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp'
    ];
    $mimeType = $mimeMap[$fileExt] ?? 'image/jpeg';
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/x-ms-bmp'];
if (!in_array($mimeType, $allowedMimes, true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Tệp đã tải lên không phải là định dạng hình ảnh hợp lệ'
    ], 400);
}

// 4. Lấy danh sách danh mục chi tiêu của user để AI đề xuất chính xác
$catStmt = $pdo->prepare("
    SELECT id, name 
    FROM categories 
    WHERE (user_id IS NULL OR user_id = ?) AND type = 'expense'
    ORDER BY id ASC
");
$catStmt->execute([$userId]);
$userCategories = $catStmt->fetchAll();

$categoryNames = [];
foreach ($userCategories as $c) {
    $categoryNames[] = trim($c['name']);
}
$categoryListStr = !empty($categoryNames) 
    ? implode(', ', $categoryNames) 
    : 'Ăn uống, Mua sắm, Di chuyển, Hóa đơn & Tiện ích, Y tế, Giáo dục, Giải trí';

// 5. Chuẩn bị gọi Gemini Vision API
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
$envVars = file_exists($envFile) ? loadEnvFile($envFile) : [];
$apiKey  = $envVars['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? $_ENV['GEMINI_API_KEY'] ?? '';

$extractedData = null;
$source        = 'rule_based';

if (!empty($apiKey)) {
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

    // Chuyển đổi ảnh sang Base64
    $rawImageContent = file_get_contents($file['tmp_name']);
    $imageBase64     = base64_encode($rawImageContent);

    // Xây dựng prompt chuẩn tiếng Việt cho OCR Vision
    $prompt = "Bạn là trợ lý AI chuyên gia bóc tách dữ liệu hóa đơn (Receipt OCR Scanner) cho ứng dụng quản lý chi tiêu cá nhân bằng tiếng Việt.\n"
        . "Nhiệm vụ: Hãy phân tích kỹ hình ảnh hóa đơn được đính kèm và trích xuất các thông tin chính xác sau:\n"
        . "1. amount: Tổng số tiền thanh toán thực tế cuối cùng (Total / Tổng cộng / Thành tiền / Tổng thanh toán). Trả về kiểu số thực hoặc số nguyên (ví dụ: 125000 hoặc 55000), KHÔNG chứa ký hiệu tiền tệ (đ, VND, $) hay dấu chấm, dấu phẩy ngăn cách hàng nghìn. Nếu không tìm thấy số tiền, trả về 0.\n"
        . "2. date: Ngày in hoặc ngày phát hành giao dịch trên hóa đơn theo định dạng chuẩn YYYY-MM-DD. Nếu trên hóa đơn chỉ ghi ngày/tháng (ví dụ: 15/05) thì lấy năm hiện tại (" . date('Y') . "). Nếu không có ngày, trả về ngày hôm nay (" . date('Y-m-d') . ").\n"
        . "3. category_suggestion: Đề xuất danh mục chi tiêu phù hợp nhất cho hóa đơn này. Hãy ưu tiên chọn chính xác một trong các danh mục sau nếu phù hợp: [" . $categoryListStr . "]. Nếu không khớp, đề xuất một danh mục ngắn gọn (ví dụ: Ăn uống, Mua sắm, Di chuyển, Hóa đơn & Tiện ích, Y tế...).\n"
        . "4. note: Tên cửa hàng, siêu thị, nhà hàng hoặc tóm tắt ngắn các món/sản phẩm chính đã mua (tối đa 120 ký tự). Ví dụ: 'WinMart - Mua thực phẩm' hoặc 'Highlands Coffee - Cà phê phin sữa'.\n\n"
        . "YÊU CẦU BẮT BUỘC:\n"
        . "- Trả về DUY NHẤT một chuỗi JSON hợp lệ, KHÔNG bọc trong markdown (không dùng ```json ... ```), KHÔNG kèm bất kỳ lời dẫn hay ghi chú nào khác ngoài JSON.\n"
        . "- Cấu trúc JSON chuẩn:\n"
        . "{\n"
        . "  \"amount\": 125000,\n"
        . "  \"date\": \"YYYY-MM-DD\",\n"
        . "  \"category_suggestion\": \"Ăn uống\",\n"
        . "  \"note\": \"Highlands Coffee - Cà phê phin\"\n"
        . "}";

    // Cấu hình payload cho Gemini 1.5 Flash Vision (inlineData)
    $postData = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data'      => $imageBase64
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature'     => 0.1,
            'maxOutputTokens' => 800
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($postData, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $resJson = json_decode($response, true);
        $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (!empty($rawText)) {
            // Trích xuất chuỗi JSON chuẩn xác kể cả khi Gemini bọc markdown hoặc kèm văn bản
            $parsedAi = null;
            if (preg_match('/\{[\s\S]*\}/', $rawText, $matches)) {
                $parsedAi = json_decode($matches[0], true);
            }
            if (!is_array($parsedAi)) {
                $cleanedText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
                $cleanedText = preg_replace('/\s*```$/', '', $cleanedText);
                $parsedAi = json_decode(trim($cleanedText), true);
            }

            if (is_array($parsedAi)) {
                $rawAmount = $parsedAi['amount'] ?? 0;
                // Chuẩn hóa amount thành số dương
                if (is_string($rawAmount)) {
                    $cleanedAmount = preg_replace('/[^0-9.]/', '', $rawAmount);
                    $numericAmount = is_numeric($cleanedAmount) ? (float)$cleanedAmount : 0;
                } else {
                    $numericAmount = is_numeric($rawAmount) ? (float)$rawAmount : 0;
                }

                // Chuẩn hóa date
                $parsedDate = !empty($parsedAi['date']) ? trim($parsedAi['date']) : '';
                $d = DateTime::createFromFormat('!Y-m-d', $parsedDate);
                $finalDate = ($d && $d->format('Y-m-d') === $parsedDate) ? $parsedDate : date('Y-m-d');

                // Chuẩn hóa category_suggestion
                $categorySug = !empty($parsedAi['category_suggestion']) && is_string($parsedAi['category_suggestion'])
                    ? trim($parsedAi['category_suggestion'])
                    : 'Mua sắm';

                // Chuẩn hóa note
                $finalNote = !empty($parsedAi['note']) && is_string($parsedAi['note'])
                    ? mb_substr(trim($parsedAi['note']), 0, 150, 'UTF-8')
                    : 'Hóa đơn thanh toán';

                $extractedData = [
                    'amount'              => $numericAmount,
                    'date'                => $finalDate,
                    'category_suggestion' => $categorySug,
                    'note'                => $finalNote
                ];
                $source = 'gemini-1.5-flash';
            }
        }
    }
}

// 6. Cơ chế phân tích dự phòng thông minh (Fallback) nếu chưa cấu hình GEMINI_API_KEY hoặc lỗi kết nối
if ($extractedData === null) {
    // Đoán gợi ý dựa trên tên tệp hoặc mặc định hợp lý
    $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
    $lowerBase = mb_strtolower($baseName, 'UTF-8');

    $suggestedCategory = 'Mua sắm';
    $defaultNote = 'Hóa đơn ' . ($baseName ?: 'chi tiêu');

    if (preg_match('/(cafe|coffee|tra|an|com|pho|bun|food|drink|nha hang|quan an)/i', $lowerBase)) {
        $suggestedCategory = 'Ăn uống';
        $defaultNote = 'Hóa đơn ăn uống';
    } elseif (preg_match('/(xang|petro|grab|be|gojek|taxi|xe)/i', $lowerBase)) {
        $suggestedCategory = 'Di chuyển';
        $defaultNote = 'Hóa đơn xăng xe / di chuyển';
    } elseif (preg_match('/(dien|nuoc|internet|wifi|cuoc|hoa don)/i', $lowerBase)) {
        $suggestedCategory = 'Hóa đơn & Tiện ích';
        $defaultNote = 'Hóa đơn điện nước / tiện ích';
    } elseif (preg_match('/(thuoc|benh vien|kham|y te|pharmacy)/i', $lowerBase)) {
        $suggestedCategory = 'Y tế';
        $defaultNote = 'Hóa đơn thuốc men / y tế';
    }

    $extractedData = [
        'amount'              => 0,
        'date'                => date('Y-m-d'),
        'category_suggestion' => $suggestedCategory,
        'note'                => $defaultNote
    ];
}

// 7. Tự động ánh xạ category_suggestion với category_id trong cơ sở dữ liệu
$matchedCategoryId = null;
$normSug = mb_strtolower(trim($extractedData['category_suggestion']), 'UTF-8');

foreach ($userCategories as $cat) {
    $normCat = mb_strtolower(trim($cat['name']), 'UTF-8');
    // So khớp chính xác hoặc chứa nhau
    if ($normCat === $normSug || str_contains($normSug, $normCat) || str_contains($normCat, $normSug)) {
        $matchedCategoryId = (int)$cat['id'];
        $extractedData['category_suggestion'] = $cat['name']; // Dùng tên chuẩn trong DB
        break;
    }
}

// Nếu chưa khớp, thử tìm danh mục mặc định đầu tiên
if ($matchedCategoryId === null && !empty($userCategories)) {
    // Tìm danh mục có tên liên quan đến Mua sắm hoặc Khác
    foreach ($userCategories as $cat) {
        $normCat = mb_strtolower(trim($cat['name']), 'UTF-8');
        if (str_contains($normCat, 'mua sắm') || str_contains($normCat, 'khác') || str_contains($normCat, 'chi tiêu')) {
            $matchedCategoryId = (int)$cat['id'];
            break;
        }
    }
}

// 8. Trả về kết quả JSON chuẩn hóa
jsonResponse([
    'success' => true,
    'message' => $source === 'gemini-1.5-flash'
        ? 'Quét và nhận diện hóa đơn thành công bằng AI'
        : 'Nhận diện hóa đơn thành công (chế độ dự phòng)',
    'data'    => [
        'amount'              => $extractedData['amount'],
        'date'                => $extractedData['date'],
        'category_suggestion' => $extractedData['category_suggestion'],
        'category_id'         => $matchedCategoryId,
        'note'                => $extractedData['note'],
        'source'              => $source,
        'file_info'           => [
            'name' => $file['name'],
            'size' => $file['size'],
            'mime' => $mimeType
        ]
    ]
]);
