<?php
// Không để warning/notice của PHP lọt vào body làm hỏng JSON trả về
ini_set('display_errors', '0');
ob_start();
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Lỗi máy chủ: ' . $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')'], JSON_UNESCAPED_UNICODE);
    }
});
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['error' => 'Phương thức không được hỗ trợ. Vui lòng sử dụng POST.'], 405);
}

$userId = requireAuth();
$pdo    = getDbConnection();

// 1. Tiếp nhận và kiểm tra câu hỏi đầu vào
$input    = getJsonInput();
$question = trim((string)($input['question'] ?? ''));

if ($question === '') {
    jsonResponse(['error' => 'Vui lòng nhập câu hỏi của bạn.'], 400);
}

// 2. Thu thập ngữ cảnh tài chính tháng hiện tại của người dùng
$month = (int)date('n');
$year  = (int)date('Y');

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = (new DateTimeImmutable($startDate))->modify('+1 month')->format('Y-m-d');

$userStmt = $pdo->prepare('SELECT full_name, username, currency FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user     = $userStmt->fetch() ?: [];
$userName = !empty($user['full_name']) ? $user['full_name'] : ($user['username'] ?? 'Bạn');
$currency = $user['currency'] ?? 'VND';

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
$savingRate   = $totalIncome > 0 ? round(($netBalance / $totalIncome) * 100, 1) : 0.0;
$totalTx      = (int)$summary['total_transactions'];

// Lấy danh mục chi tiêu nhiều nhất trong tháng (kèm top 3 để làm giàu ngữ cảnh)
$topExpenseStmt = $pdo->prepare("
    SELECT c.name, SUM(t.amount) AS total_amount, COUNT(t.id) AS tx_count
    FROM transactions t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date >= ? AND t.transaction_date < ?
    GROUP BY c.id, c.name
    ORDER BY total_amount DESC
    LIMIT 5
");
$topExpenseStmt->execute([$userId, $startDate, $endDate]);
$topExpenses = $topExpenseStmt->fetchAll();

foreach ($topExpenses as &$item) {
    $item['total_amount'] = (float)$item['total_amount'];
    $item['percentage']   = $totalExpense > 0 ? round(($item['total_amount'] / $totalExpense) * 100, 1) : 0.0;
}
unset($item);

$topCategory       = $topExpenses[0] ?? null;
$topCategoryName   = $topCategory['name'] ?? null;
$topCategoryAmount = $topCategory['total_amount'] ?? 0.0;
$topCategoryPct    = $topCategory['percentage'] ?? 0.0;

// Lấy danh sách giao dịch chi tiết gần đây (để AI phân tích chính xác từng khoản thu, chi)
$txStmt = $pdo->prepare("
    SELECT t.id, t.type, t.amount, t.transaction_date, c.name AS category_name, t.note, t.location_name
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 50
");
$txStmt->execute([$userId]);
$allTransactions = $txStmt->fetchAll();

// Phân loại các khoản thu nhập và chi tiêu
$incomes = [];
$expenses = [];
foreach ($allTransactions as $t) {
    $amt = (float)$t['amount'];
    $item = [
        'id'        => (int)$t['id'],
        'type'      => $t['type'],
        'amount'    => $amt,
        'date'      => $t['transaction_date'],
        'category'  => $t['category_name'] ?: ($t['type'] === 'income' ? 'Thu nhập khác' : 'Chi tiêu khác'),
        'note'      => trim((string)($t['note'] ?? '')),
        'location'  => trim((string)($t['location_name'] ?? ''))
    ];
    if ($t['type'] === 'income') {
        $incomes[] = $item;
    } else {
        $expenses[] = $item;
    }
}

// Tìm khoản thu/chi lớn nhất và nhỏ nhất
$minIncome = null;
$maxIncome = null;
if (!empty($incomes)) {
    $incomesSorted = $incomes;
    usort($incomesSorted, fn($a, $b) => $a['amount'] <=> $b['amount']);
    $minIncome = $incomesSorted[0];
    $maxIncome = end($incomesSorted);
}

$minExpense = null;
$maxExpense = null;
if (!empty($expenses)) {
    $expensesSorted = $expenses;
    usort($expensesSorted, fn($a, $b) => $a['amount'] <=> $b['amount']);
    $minExpense = $expensesSorted[0];
    $maxExpense = end($expensesSorted);
}

function formatMoneyChat(float $amount, string $currency = 'VND'): string
{
    $formatted = number_format($amount, 0, ',', '.');
    return $currency === 'VND' ? ($formatted . ' đ') : ($formatted . ' ' . $currency);
}

// 3. Xây dựng System Prompt chi tiết cung cấp toàn bộ dữ liệu tài chính thật
$topCategoryLine = $topCategoryName !== null
    ? "{$topCategoryName} (" . formatMoneyChat($topCategoryAmount, $currency) . ", chiếm {$topCategoryPct}% tổng chi)"
    : 'Chưa có khoản chi tiêu nào trong tháng';

$topListText = '';
if (!empty($topExpenses)) {
    $lines = [];
    foreach ($topExpenses as $idx => $cat) {
        $lines[] = ($idx + 1) . ". {$cat['name']}: " . formatMoneyChat($cat['total_amount'], $currency) . " ({$cat['percentage']}%)";
    }
    $topListText = implode("\n", $lines);
} else {
    $topListText = 'Chưa phát sinh chi tiêu trong tháng.';
}

$recentTxText = '';
foreach (array_slice($allTransactions, 0, 20) as $t) {
    $sign = $t['type'] === 'income' ? '+' : '-';
    $cat = $t['category_name'] ?: 'Khác';
    $note = !empty($t['note']) ? " (Ghi chú: {$t['note']})" : '';
    $recentTxText .= "- Ngày {$t['transaction_date']}: {$sign}" . formatMoneyChat((float)$t['amount'], $currency) . " [{$cat}]{$note}\n";
}

$systemPrompt = "Bạn là Trợ lý tài chính cá nhân thông minh của ứng dụng CashManager. Bạn hãy trả lời một cách tự nhiên, chân thật, gần gũi và chính xác như một chuyên gia tài chính đang trò chuyện trực tiếp với người dùng.\n\n"
    . "DƯỚI ĐÂY LÀ DỮ LIỆU TÀI CHÍNH THỰC TẾ CỦA {$userName} (tiền tệ: {$currency}):\n"
    . "- Tổng thu nhập tháng {$month}/{$year}: " . formatMoneyChat($totalIncome, $currency) . "\n"
    . "- Tổng chi tiêu tháng {$month}/{$year}: " . formatMoneyChat($totalExpense, $currency) . "\n"
    . "- Số dư ròng (Thu - Chi): " . formatMoneyChat($netBalance, $currency) . "\n"
    . "- Tỷ lệ tiết kiệm hiện tại: {$savingRate}%\n"
    . "- Khoản thu nhập ít nhất: " . ($minIncome ? formatMoneyChat($minIncome['amount'], $currency) . " [{$minIncome['category']}] vào ngày {$minIncome['date']}" : "Chưa có") . "\n"
    . "- Khoản thu nhập nhiều nhất: " . ($maxIncome ? formatMoneyChat($maxIncome['amount'], $currency) . " [{$maxIncome['category']}] vào ngày {$maxIncome['date']}" : "Chưa có") . "\n"
    . "- Khoản chi tiêu ít nhất: " . ($minExpense ? formatMoneyChat($minExpense['amount'], $currency) . " [{$minExpense['category']}] vào ngày {$minExpense['date']}" : "Chưa có") . "\n"
    . "- Khoản chi tiêu nhiều nhất: " . ($maxExpense ? formatMoneyChat($maxExpense['amount'], $currency) . " [{$maxExpense['category']}] vào ngày {$maxExpense['date']}" : "Chưa có") . "\n"
    . "- Danh mục tiêu nhiều tiền nhất: {$topCategoryLine}\n"
    . "- Chi tiết Top danh mục chi tiêu:\n{$topListText}\n\n"
    . "DANH SÁCH CÁC GIAO DỊCH THỰC TẾ GẦN ĐÂY:\n{$recentTxText}\n\n"
    . "YÊU CẦU TRẢ LỜI:\n"
    . "1. Trả lời thẳng vào câu hỏi của {$userName}. Nếu người dùng hỏi về một khoản thu/chi cụ thể (như khoản ít nhất, nhiều nhất, hôm nay, danh mục nào...), hãy nêu ĐÍCH DANH số tiền, ngày và danh mục từ dữ liệu thực tế ở trên.\n"
    . "2. Giọng điệu thân thiện, tự nhiên, xưng hô 'mình - bạn' hoặc gọi tên {$userName}.\n"
    . "3. Tuyệt đối không trả lời rập khuôn theo một công thức chung. Hãy trò chuyện như một người bạn đồng hành tài chính thông minh.";

// 4. Gọi Google Gemini API qua cURL (tắt SSL verify cho môi trường dev Windows)
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
$envVars = file_exists($envFile) ? loadEnvFile($envFile) : [];
$apiKey  = trim((string)($envVars['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? ($_ENV['GEMINI_API_KEY'] ?? '')));

$answer = null;
$source = 'rule_based';

if (!empty($apiKey) && function_exists('curl_init')) {
    $modelsToTry = ['gemini-3.5-flash', 'gemini-3.8-flash', 'gemini-flash-latest'];

    $postData = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ],
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [
                    ['text' => $question]
                ]
            ]
        ],
        'generationConfig' => [
            'thinkingConfig'  => [
                'thinkingBudget' => 0
            ],
            'temperature'     => 0.65,
            'maxOutputTokens' => 2048
        ]
    ];
    $jsonPayload = json_encode($postData, JSON_UNESCAPED_UNICODE);

    foreach ($modelsToTry as $modelName) {
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . urlencode($apiKey);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $jsonPayload,
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
            $rawText = trim((string)($resJson['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            if ($rawText !== '') {
                $answer = $rawText;
                $source = $modelName;
                break;
            }
        }
    }
}

// 5. Cơ chế phản hồi thông minh (Natural Financial Query Engine) khi chưa có GEMINI_API_KEY hoặc lỗi mạng
if ($answer === null) {
    $answer = buildSmartFinancialAnswer(
        $question,
        $userName,
        $month,
        $year,
        $currency,
        $totalIncome,
        $totalExpense,
        $netBalance,
        $savingRate,
        $topCategoryName,
        $topCategoryAmount,
        $topCategoryPct,
        $incomes,
        $expenses,
        $minIncome,
        $maxIncome,
        $minExpense,
        $maxExpense,
        $allTransactions,
        $topExpenses
    );
}

while (ob_get_level() > 0) { ob_end_clean(); }
jsonResponse([
    'success' => true,
    'data'    => [
        'answer'  => $answer,
        'source'  => $source,
        'context' => [
            'month'               => $month,
            'year'                => $year,
            'currency'            => $currency,
            'total_income'        => $totalIncome,
            'total_expense'       => $totalExpense,
            'net_balance'         => $netBalance,
            'saving_rate'         => $savingRate,
            'top_category'        => $topCategoryName,
            'top_category_amount' => $topCategoryAmount,
            'top_category_pct'    => $topCategoryPct
        ]
    ]
]);

/**
 * Trình phân tích tài chính tự nhiên thông minh dựa trên dữ liệu thật của người dùng
 */
function buildSmartFinancialAnswer(
    string $question,
    string $userName,
    int $month,
    int $year,
    string $currency,
    float $totalIncome,
    float $totalExpense,
    float $netBalance,
    float $savingRate,
    ?string $topCategoryName,
    float $topCategoryAmount,
    float $topCategoryPct,
    array $incomes,
    array $expenses,
    ?array $minIncome,
    ?array $maxIncome,
    ?array $minExpense,
    ?array $maxExpense,
    array $allTransactions,
    array $topExpenses
): string {
    $qLower     = mb_strtolower(trim($question), 'UTF-8');
    $incomeStr  = formatMoneyChat($totalIncome, $currency);
    $expenseStr = formatMoneyChat($totalExpense, $currency);
    $balanceStr = formatMoneyChat($netBalance, $currency);

    $fmtDate = function (?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    };

    // 1. Câu hỏi về KHOẢN THU NHẬP ÍT NHẤT / NHỎ NHẤT
    if (
        preg_match('/(thu nhập|khoản thu|tiền thu|thu vào).*(ít nhất|nhỏ nhất|thấp nhất|bé nhất)/iu', $qLower) ||
        preg_match('/(ít nhất|nhỏ nhất|thấp nhất|bé nhất).*(thu nhập|khoản thu|tiền thu|thu vào)/iu', $qLower)
    ) {
        if ($minIncome) {
            $amt  = formatMoneyChat($minIncome['amount'], $currency);
            $cat  = $minIncome['category'];
            $date = $fmtDate($minIncome['date']);
            $note = !empty($minIncome['note']) ? " (ghi chú: *{$minIncome['note']}*)" : '';

            $reply = "Khoản thu nhập ít nhất của bạn là **{$amt}** thuộc danh mục **{$cat}**{$note}, ghi nhận vào ngày **{$date}**.\n\n";
            if ($maxIncome && $maxIncome['amount'] > $minIncome['amount']) {
                $maxAmt  = formatMoneyChat($maxIncome['amount'], $currency);
                $maxDate = $fmtDate($maxIncome['date']);
                $reply .= "💡 Để bạn dễ so sánh, khoản thu nhập cao nhất của bạn là **{$maxAmt}** ({$maxIncome['category']}, ngày {$maxDate}).\n";
            }
            $reply .= "- Tổng thu nhập hiện tại của bạn: **{$incomeStr}**.";
            return $reply;
        }
        return "Chào {$userName}, hiện tại trong ví chưa có khoản thu nhập nào được ghi nhận. Bạn hãy bấm vào nút **+ Giao dịch** để ghi nhận thêm các nguồn thu của mình nhé!";
    }

    // 2. Câu hỏi về KHOẢN THU NHẬP NHIỀU NHẤT / LỚN NHẤT
    if (
        preg_match('/(thu nhập|khoản thu|tiền thu|thu vào).*(nhiều nhất|lớn nhất|cao nhất|khủng nhất)/iu', $qLower) ||
        preg_match('/(nhiều nhất|lớn nhất|cao nhất|khủng nhất).*(thu nhập|khoản thu|tiền thu|thu vào)/iu', $qLower)
    ) {
        if ($maxIncome) {
            $amt  = formatMoneyChat($maxIncome['amount'], $currency);
            $cat  = $maxIncome['category'];
            $date = $fmtDate($maxIncome['date']);
            $note = !empty($maxIncome['note']) ? " (ghi chú: *{$maxIncome['note']}*)" : '';

            $reply = "Khoản thu nhập lớn nhất của bạn là **{$amt}** thuộc danh mục **{$cat}**{$note}, ghi nhận vào ngày **{$date}**.\n\n";
            $reply .= "- Tổng thu nhập đã ghi chép: **{$incomeStr}**.\n";
            $reply .= "💡 Lời khuyên: Bạn có thể trích ngay 20-30% từ khoản thu này vào quỹ tiết kiệm hoặc đầu tư an toàn trước khi phát sinh thêm chi tiêu nhé!";
            return $reply;
        }
        return "Chào {$userName}, hiện tại chưa có khoản thu nhập nào được ghi nhận trong tháng này.";
    }

    // 3. Câu hỏi về KHOẢN CHI TIÊU ÍT NHẤT / NHỎ NHẤT
    if (
        preg_match('/(chi tiêu|khoản chi|tiền chi|tiêu xài|chi ra|tiêu).*(ít nhất|nhỏ nhất|thấp nhất|bé nhất)/iu', $qLower) ||
        preg_match('/(ít nhất|nhỏ nhất|thấp nhất|bé nhất).*(chi tiêu|khoản chi|tiền chi|tiêu xài|chi)/iu', $qLower)
    ) {
        if ($minExpense) {
            $amt  = formatMoneyChat($minExpense['amount'], $currency);
            $cat  = $minExpense['category'];
            $date = $fmtDate($minExpense['date']);
            $note = !empty($minExpense['note']) ? " (ghi chú: *{$minExpense['note']}*)" : '';

            return "Khoản chi tiêu nhỏ nhất của bạn là **{$amt}** cho danh mục **{$cat}**{$note} vào ngày **{$date}**.\n\n"
                . "Việc bạn ghi chép cả những khoản chi nhỏ nhất chứng tỏ bạn theo dõi dòng tiền rất sát sao và cẩn thận đấy!";
        }
        return "Chào {$userName}, bạn chưa có khoản chi tiêu nào được ghi nhận trong tháng này.";
    }

    // 4. Câu hỏi về KHOẢN CHI TIÊU NHIỀU NHẤT / LỚN NHẤT / TỐN KÉM NHẤT
    if (
        preg_match('/(chi tiêu|khoản chi|tiền chi|chi ra|tiêu|tốn).*(nhiều nhất|lớn nhất|cao nhất|nặng nhất|tốn nhất)/iu', $qLower) ||
        preg_match('/(nhiều nhất|lớn nhất|cao nhất|tốn nhất).*(chi tiêu|khoản chi|tiền chi|tiêu)/iu', $qLower) ||
        preg_match('/(tiêu nhiều nhất vào việc gì|chi nhiều nhất cho cái gì)/iu', $qLower)
    ) {
        if ($maxExpense) {
            $amt  = formatMoneyChat($maxExpense['amount'], $currency);
            $cat  = $maxExpense['category'];
            $date = $fmtDate($maxExpense['date']);
            $note = !empty($maxExpense['note']) ? " (ghi chú: *{$maxExpense['note']}*)" : '';

            $reply = "Khoản chi tiêu lớn nhất của bạn là **{$amt}** cho **{$cat}**{$note} vào ngày **{$date}**.\n\n";
            if ($topCategoryName) {
                $topAmt = formatMoneyChat($topCategoryAmount, $currency);
                $reply .= "- Tổng kết nhóm tiêu tốn nhất tháng là **{$topCategoryName}** với tổng số tiền **{$topAmt}** (chiếm {$topCategoryPct}% toàn bộ chi tiêu).\n\n";
            }
            $reply .= "💡 Để cân bằng lại ví tiền, bạn nên xem xét đây là nhu cầu thiết yếu hay mua sắm tùy thích để điều chỉnh hợp lý nhé.";
            return $reply;
        }
    }

    // 5. Câu hỏi về GIAO DỊCH GẦN ĐÂY / MỚI NHẤT / HÔM NAY
    if (preg_match('/(gần đây|vừa rồi|mới nhất|hôm nay|hôm qua|vừa chi|vừa thu|nhật ký)/iu', $qLower)) {
        if (!empty($allTransactions)) {
            $reply = "Dưới đây là các giao dịch gần nhất bạn đã ghi chép:\n";
            foreach (array_slice($allTransactions, 0, 5) as $idx => $t) {
                $sign = $t['type'] === 'income' ? '🟢 Thu' : '🔴 Chi';
                $amt  = formatMoneyChat((float)$t['amount'], $currency);
                $cat  = $t['category_name'] ?: 'Khác';
                $date = $fmtDate($t['transaction_date']);
                $note = !empty($t['note']) ? " - *{$t['note']}*" : '';
                $reply .= ($idx + 1) . ". {$sign} **{$amt}** | {$cat} ({$date}){$note}\n";
            }
            return $reply;
        }
    }

    // 6. Câu hỏi về MỘT DANH MỤC CỤ THỂ (Ví dụ: "tiền ăn uống", "mua sắm", "lương", "đầu tư")
    foreach ($topExpenses as $cat) {
        $cName = mb_strtolower($cat['name'], 'UTF-8');
        if (mb_strpos($qLower, $cName) !== false) {
            $catAmt = formatMoneyChat($cat['total_amount'], $currency);
            $txCount = $cat['tx_count'];
            $avg = $txCount > 0 ? formatMoneyChat($cat['total_amount'] / $txCount, $currency) : '';

            return "Về nhóm **{$cat['name']}**:\n"
                . "- Tổng chi tiêu tháng: **{$catAmt}** (chiếm **{$cat['percentage']}%** tổng chi).\n"
                . "- Số lần phát sinh giao dịch: **{$txCount} lần** (bình quân khoảng {$avg}/lần).\n\n"
                . ($cat['percentage'] > 40
                    ? "⚠️ Nhóm này đang chiếm tỷ trọng rất cao. Bạn hãy cài đặt hạn mức trong mục **Ngân sách** để kiểm soát chi tiêu tốt hơn nhé!"
                    : "✅ Chi tiêu cho nhóm này vẫn đang trong tầm kiểm soát ổn định.");
        }
    }

    // 7. GỢI Ý CÁCH GIẢM 30% TIỀN ĂN UỐNG (Câu hỏi gợi ý)
    if (preg_match('/(giảm 30%|giảm.*tiền ăn|tiền ăn uống)/iu', $qLower)) {
        $foodCat = null;
        foreach ($topExpenses as $c) {
            if (mb_stripos($c['name'], 'ăn') !== false) {
                $foodCat = $c;
                break;
            }
        }
        $foodAmt = $foodCat ? (float)$foodCat['total_amount'] : 4000000;
        $save30  = formatMoneyChat($foodAmt * 0.3, $currency);
        $remain  = formatMoneyChat($foodAmt * 0.7, $currency);

        return "Để giảm **30% tiền ăn uống** (tiết kiệm được khoảng **{$save30}**, duy trì ở mức **{$remain}**/tháng), bạn có thể áp dụng 3 bước thực tế sau:\n\n"
            . "1. **Lên thực đơn theo tuần**: Đi siêu thị 1-2 lần/tuần thay vì mua lẻ tẻ hàng ngày, tránh lãng phí thực phẩm thừa.\n"
            . "2. **Tự chuẩn bị bữa trưa 3-4 ngày/tuần**: Vừa sạch sẽ vừa tiết kiệm được 40.000 - 60.000 đ mỗi bữa so với ăn ngoài.\n"
            . "3. **Kiểm soát đồ uống & cà phê**: Giảm bớt 1 cốc trà sữa/cà phê mua ngoài mỗi ngày đã giúp bạn giữ lại 800.000 - 1.200.000 đ/tháng!";
    }

    // 8. CÓ NÊN MUA ĐIỆN THOẠI MỚI KHÔNG (Câu hỏi gợi ý)
    if (preg_match('/(mua điện thoại|mua xe|mua sắm|có nên mua)/iu', $qLower)) {
        if ($netBalance < 0) {
            $deficit = formatMoneyChat(abs($netBalance), $currency);
            return "Chào {$userName}, hiện tại số dư tháng này của bạn đang âm **{$deficit}** (chi tiêu đang vượt quá thu nhập).\n\n"
                . "Lời khuyên thật lòng của mình là **chưa nên mua điện thoại mới lúc này**. Hãy đợi số dư ròng ổn định dương ít nhất 2-3 tháng liên tiếp và tích lũy đủ quỹ dự phòng khẩn cấp trước khi đầu tư món đồ công nghệ lớn nhé!";
        }
        return "Chào {$userName}, số dư ròng hiện tại của bạn đang dương **{$balanceStr}**.\n\n"
            . "Nếu chiếc điện thoại hiện tại vẫn dùng tốt, bạn hãy cân nhắc quy tắc: *Giá trị món đồ không nên vượt quá 20-30% thu nhập một tháng* hoặc lập riêng một mục tiêu tiết kiệm trong 3 tháng để mua mà không ảnh hưởng tới dòng tiền hàng ngày.";
    }

    // 9. LÀM SAO ĐỂ TIẾT KIỆM 2 TRIỆU (Câu hỏi gợi ý)
    if (preg_match('/(tiết kiệm.*2 triệu|làm sao.*tiết kiệm)/iu', $qLower)) {
        $topName = $topCategoryName ?: 'Ăn uống / Mua sắm';
        return "Để tháng sau tiết kiệm chắc chắn được **2.000.000 đ**, mình gợi ý bạn công thức 3 bước sau:\n\n"
            . "1. **Trích trước - Tiêu sau**: Ngay khi nhận lương hoặc khoản thu lớn, hãy chuyển thẳng 2 triệu vào một tài khoản tiết kiệm riêng hoặc sổ tích lũy.\n"
            . "2. **Cắt giảm danh mục top 1 ({$topName})**: Đặt mục tiêu giảm khoảng 15-20% nhóm này (tương đương mỗi ngày bớt chi khoảng 50.000 - 70.000 đ).\n"
            . "3. **Cài đặt hạn mức Ngân sách**: Tạo ngân sách ngay trong mục **Ngân sách** của CashManager để hệ thống nhắc nhở mỗi khi bạn chạm mốc 80% hạn mức!";
    }

    // 10. CHÀO HỎI / GIỚI THIỆU
    if (preg_match('/^(chào|xin chào|hello|hi|bạn là ai|giới thiệu)/iu', $qLower)) {
        return "Xin chào **{$userName}**! Mình là Trợ lý tài chính cá nhân CashManager.\n\n"
            . "Mình có thể giúp bạn:\n"
            . "- 📊 Tra cứu chính xác khoản thu/chi (khoản ít nhất, nhiều nhất, hôm nay...)\n"
            . "- 💡 Đánh giá thói quen tiêu tiền và gợi ý cách tiết kiệm thực tế\n"
            . "- ⚖️ Tư vấn các quyết định mua sắm dựa trên số dư ví của bạn\n\n"
            . "Bạn cứ hỏi tự nhiên bất kỳ thắc mắc nào nhé!";
    }

    // 11. PHẢN HỒI TỔNG QUAN SINH ĐỘNG DỰA TRÊN TÌNH TRẠNG THỰC TẾ
    if ($netBalance < 0) {
        $deficit = formatMoneyChat(abs($netBalance), $currency);
        return "Chào {$userName}, với câu hỏi của bạn: tình hình ví tiền tháng {$month}/{$year} hiện tại đang ghi nhận tổng thu **{$incomeStr}** và chi **{$expenseStr}** (đang thâm hụt **{$deficit}**).\n\n"
            . "Nguyên nhân chính là nhóm **{$topCategoryName}** đang chiếm tỷ trọng lớn ({$topCategoryPct}%). Bạn hãy ưu tiên rà soát lại các khoản chi trong nhóm này và tạm hoãn các chi tiêu ngoài kế hoạch để sớm đưa số dư về mức an toàn nhé!";
    }

    return "Chào {$userName}, ví tiền tháng {$month}/{$year} của bạn đang khá tích cực với số dư ròng dương **{$balanceStr}** (tỷ lệ tiết kiệm đạt **{$savingRate}%**).\n\n"
        . "Khoản thu lớn nhất bạn ghi nhận là từ **" . ($maxIncome ? $maxIncome['category'] : 'lương') . "**, và nhóm chi tiêu nhiều nhất là **{$topCategoryName}** (" . formatMoneyChat($topCategoryAmount, $currency) . "). Bạn hoàn toàn có thể tiếp tục duy trì đà chi tiêu hợp lý này!";
}
