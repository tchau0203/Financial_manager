<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$userId = requireAuth();
$pdo    = getDbConnection();

// 1. Nhận thông số tháng và năm
$month = filter_var($_GET['month'] ?? $_POST['month'] ?? date('n'), FILTER_VALIDATE_INT);
$year  = filter_var($_GET['year']  ?? $_POST['year']  ?? date('Y'), FILTER_VALIDATE_INT);

if (!$month || $month < 1 || $month > 12) $month = (int)date('n');
if (!$year  || $year < 2000 || $year > 2100) $year = (int)date('Y');

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = (new DateTimeImmutable($startDate))->modify('+1 month')->format('Y-m-d');

// 2. Lấy thông tin người dùng
$userStmt = $pdo->prepare('SELECT full_name, username, currency FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch() ?: [];
$userName = !empty($user['full_name']) ? $user['full_name'] : ($user['username'] ?? 'Người dùng');
$currency = $user['currency'] ?? 'VND';

// 3. Tổng hợp số liệu thu chi của user trong tháng
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

// 4. Trích xuất Top 3 danh mục chi tiêu nhiều nhất trong tháng
$topExpenseStmt = $pdo->prepare("
    SELECT c.name, SUM(t.amount) AS total_amount, COUNT(t.id) as count
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date >= ? AND t.transaction_date < ?
    GROUP BY c.id, c.name
    ORDER BY total_amount DESC
    LIMIT 3
");
$topExpenseStmt->execute([$userId, $startDate, $endDate]);
$topExpenses = $topExpenseStmt->fetchAll();

foreach ($topExpenses as &$item) {
    $item['total_amount'] = (float)$item['total_amount'];
    $item['percentage']   = $totalExpense > 0 ? round(($item['total_amount'] / $totalExpense) * 100, 1) : 0;
}
unset($item);

// Định dạng tiền tệ để gửi prompt AI
if (!function_exists('formatMoneyVND')) {
    function formatMoneyVND($amount) {
        return number_format($amount, 0, ',', '.') . ' đ';
    }
}

// 5. Chuẩn bị gọi Gemini API
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
$envVars = file_exists($envFile) ? loadEnvFile($envFile) : [];
$apiKey  = trim((string)($envVars['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? ($_ENV['GEMINI_API_KEY'] ?? '')));

$topExpenseText = '';
if (!empty($topExpenses)) {
    $lines = [];
    foreach ($topExpenses as $idx => $t) {
        $lines[] = ($idx + 1) . ". Danh mục '{$t['name']}': " . formatMoneyVND($t['total_amount']) . " ({$t['percentage']}% tổng chi)";
    }
    $topExpenseText = implode("\n", $lines);
} else {
    $topExpenseText = "Chưa có khoản chi tiêu nào trong tháng.";
}

// Xây dựng prompt chuẩn tiếng Việt
$prompt = "Bạn là chuyên gia tư vấn tài chính cá nhân thông minh (Smart Financial Advisor). Hãy phân tích báo cáo tài chính tháng {$month}/{$year} của người dùng {$userName}:\n\n"
    . "- Tổng thu nhập: " . formatMoneyVND($totalIncome) . "\n"
    . "- Tổng chi tiêu: " . formatMoneyVND($totalExpense) . "\n"
    . "- Số dư ròng (Thu nhập - Chi tiêu): " . formatMoneyVND($netBalance) . "\n"
    . "- Tỷ lệ tiết kiệm: {$savingRate}%\n"
    . "- Top 3 danh mục chi tiêu nhiều nhất:\n{$topExpenseText}\n\n"
    . "YÊU CẦU: Trả về kết quả phân tích DUY NHẤT một chuỗi JSON hợp lệ (không kèm giải thích ngoài lề, không bọc markdown ```json ... ```) theo cấu trúc sau:\n"
    . "{\n"
    . "  \"status\": \"Tốt\" hoặc \"Báo động\" hoặc \"Cần chú ý\",\n"
    . "  \"overview\": \"Nhận xét tổng quan ngắn gọn 1-2 câu về tình hình tài chính trong tháng.\",\n"
    . "  \"potential_wastes\": [\n"
    . "    \"Chỉ ra khoản lãng phí hoặc danh mục cần kiểm soát 1\",\n"
    . "    \"Chỉ ra khoản lãng phí hoặc danh mục cần kiểm soát 2\"\n"
    . "  ],\n"
    . "  \"action_items\": [\n"
    . "    \"Hành động cụ thể 1 để tiết kiệm tháng tới\",\n"
    . "    \"Hành động cụ thể 2 để tiết kiệm tháng tới\",\n"
    . "    \"Hành động cụ thể 3 để tiết kiệm tháng tới\"\n"
    . "  ]\n"
    . "}";

$aiAdvice = null;
$source   = 'rule_based';

if (!empty($apiKey)) {
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=" . urlencode($apiKey);

    $postData = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature'     => 0.4,
            'maxOutputTokens' => 2048,
            'thinkingConfig'  => ['thinkingBudget' => 0]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($postData, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
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

            if (is_array($parsedAi) && !empty($parsedAi['status']) && !empty($parsedAi['action_items'])) {
                $aiAdvice = [
                    'status'           => $parsedAi['status'],
                    'overview'         => $parsedAi['overview'] ?? '',
                    'potential_wastes' => is_array($parsedAi['potential_wastes'] ?? null) ? $parsedAi['potential_wastes'] : [],
                    'action_items'     => is_array($parsedAi['action_items'] ?? null) ? $parsedAi['action_items'] : []
                ];
                $source = 'gemini-3.5-flash';
            }
        }
    }
}

// 6. Cơ chế phân tích dự phòng thông minh (Rule-based Fallback) khi chưa có API Key hoặc lỗi mạng
if ($aiAdvice === null) {
    if ($totalIncome == 0 && $totalExpense == 0) {
        $aiAdvice = [
            'status'           => 'Cần chú ý',
            'overview'         => "Tháng {$month}/{$year} chưa ghi nhận giao dịch thu chi nào. Hãy bắt đầu ghi chép các khoản chi tiêu hàng ngày.",
            'potential_wastes' => [
                'Chưa cập nhật đầy đủ các khoản chi nhỏ phát sinh hàng ngày.',
                'Thiếu dữ liệu theo dõi dòng tiền thực tế.'
            ],
            'action_items'     => [
                'Bắt đầu ghi lại tất cả các khoản chi tiêu ăn uống, mua sắm từ hôm nay.',
                'Thiết lập hạn mức ngân sách tháng để định hình kế hoạch tài chính.',
                'Dành ra tối thiểu 10% thu nhập dự kiến gửi vào quỹ tiết kiệm khẩn cấp.'
            ]
        ];
    } elseif ($totalExpense > $totalIncome) {
        $overAmount = formatMoneyVND($totalExpense - $totalIncome);
        $top1Name   = $topExpenses[0]['name'] ?? 'Mua sắm';
        $top1Pct    = $topExpenses[0]['percentage'] ?? 0;

        $aiAdvice = [
            'status'           => 'Báo động',
            'overview'         => "Tình trạng tài chính tháng {$month}/{$year} đang ở mức báo động khi chi tiêu đã vượt quá thu nhập {$overAmount}.",
            'potential_wastes' => [
                "Danh mục '{$top1Name}' chiếm đến {$top1Pct}% tổng chi tiêu trong tháng, vượt mức kiểm soát an toàn.",
                "Tỷ lệ tiết kiệm âm (-" . abs($savingRate) . "%), tiềm ẩn rủi ro thâm hụt tài chính."
            ],
            'action_items'     => [
                "Cắt giảm ngay 30% ngân sách cho danh mục '{$top1Name}' trong tháng tới.",
                "Tạm dừng các khoản mua sắm không thiết yếu và chỉ tập trung vào các nhu cầu cơ bản.",
                "Rà soát lại toàn bộ hóa đơn định kỳ để loại bỏ các dịch vụ không thực sự sử dụng."
            ]
        ];
    } elseif ($savingRate < 20) {
        $top1Name = $topExpenses[0]['name'] ?? 'Ăn uống';
        $top1Pct  = $topExpenses[0]['percentage'] ?? 0;

        $aiAdvice = [
            'status'           => 'Cần chú ý',
            'overview'         => "Thu nhập tháng {$month}/{$year} vẫn dương nhưng tỷ lệ tiết kiệm chỉ đạt {$savingRate}%, thấp hơn mức chuẩn khuyến nghị (20%).",
            'potential_wastes' => [
                "Danh mục '{$top1Name}' đang chiếm {$top1Pct}% chi tiêu, có thể tối ưu thêm để gia tăng tích lũy.",
                "Các khoản chi tiêu phát sinh lẻ có thể đang tích tụ làm giảm lượng tiền nhàn rỗi."
            ],
            'action_items'     => [
                "Áp dụng nguyên tắc tài chính 50/30/20 (50% thiết yếu, 30% sở thích, 20% tiết kiệm).",
                "Trích trước 15-20% thu nhập gửi tiết kiệm ngay khi vừa nhận lương, không tiêu phần còn lại rồi mới tiết kiệm.",
                "Đặt hạn mức ngân sách cụ thể cho danh mục '{$top1Name}' trên ứng dụng."
            ]
        ];
    } else {
        $aiAdvice = [
            'status'           => 'Tốt',
            'overview'         => "Tình hình tài chính tháng {$month}/{$year} rất tích cực với tỷ lệ tiết kiệm đạt {$savingRate}% và số dư ròng " . formatMoneyVND($netBalance) . ".",
            'potential_wastes' => [
                !empty($topExpenses[0]) ? "Theo dõi duy trì danh mục '{$topExpenses[0]['name']}' để tránh phát sinh đột biến." : "Các khoản chi hiện tại đang ở mức hợp lý.",
                "Tiền mặt nhàn rỗi chưa được phân bổ tối ưu vào các kênh sinh lời."
            ],
            'action_items'     => [
                "Duy trì thói quen chi tiêu kỷ luật và ghi chép giao dịch đầy đủ.",
                "Trích một phần số dư ròng vào quỹ khẩn cấp tương đương 3-6 tháng chi phí sinh hoạt.",
                "Tìm hiểu các kênh tích lũy hoặc đầu tư an toàn để tiền nhàn rỗi sinh lời hiệu quả."
            ]
        ];
    }
}

// 7. Tính xếp loại tài chính (Grade A, B, C, D) cho giao diện Dashboard & Report
$grade = 'B';
if (($aiAdvice['status'] ?? '') === 'Tốt') {
    $grade = ($savingRate >= 30) ? 'A' : 'B';
} elseif (($aiAdvice['status'] ?? '') === 'Cần chú ý') {
    $grade = ($savingRate >= 10) ? 'B' : 'C';
} else {
    $grade = 'D';
}

// 8. Trả về phản hồi JSON chuẩn hóa (tương thích cả Dashboard/Report frontend lẫn AI)
jsonResponse([
    'success' => true,
    'message' => 'Phân tích tài chính thành công',
    'data'    => [
        'month'            => $month,
        'year'             => $year,
        'currency'         => $currency,
        'user_name'        => $userName,
        'grade'            => $grade,
        'summary'          => $aiAdvice['overview'] ?? '',
        'advice'           => $aiAdvice['overview'] ?? '',
        'comment'          => $aiAdvice['overview'] ?? '',
        'status'           => $aiAdvice['status'] ?? 'Cần chú ý',
        'tips'             => $aiAdvice['action_items'] ?? [],
        'actions'          => $aiAdvice['action_items'] ?? [],
        'potential_wastes' => $aiAdvice['potential_wastes'] ?? [],
        'ai_advice'        => $aiAdvice,
        'metrics'          => [
            'total_income'  => $totalIncome,
            'total_expense' => $totalExpense,
            'net_balance'   => $netBalance,
            'saving_rate'   => $savingRate,
            'top_expenses'  => $topExpenses
        ],
        'source'           => $source
    ]
]);
