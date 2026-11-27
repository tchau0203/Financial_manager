<?php
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
    LIMIT 3
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

function formatMoneyChat(float $amount, string $currency = 'VND'): string
{
    $formatted = number_format($amount, 0, ',', '.');
    return $currency === 'VND' ? ($formatted . ' đ') : ($formatted . ' ' . $currency);
}

// 3. Xây dựng System Prompt cung cấp toàn bộ bức tranh tài chính của user
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

$systemPrompt = "Bạn là Cố vấn tài chính cá nhân thông minh, tận tâm của ứng dụng CashManager.\n"
    . "Dưới đây là bức tranh tài chính thực tế tháng {$month}/{$year} của người dùng {$userName} (đơn vị tiền tệ: {$currency}):\n"
    . "- Tổng thu nhập tháng: " . formatMoneyChat($totalIncome, $currency) . "\n"
    . "- Tổng chi tiêu tháng: " . formatMoneyChat($totalExpense, $currency) . "\n"
    . "- Số dư ròng hiện tại (Thu - Chi): " . formatMoneyChat($netBalance, $currency) . "\n"
    . "- Tỷ lệ tiết kiệm hiện tại: {$savingRate}%\n"
    . "- Danh mục tiêu nhiều tiền nhất tháng: {$topCategoryLine}\n"
    . "- Chi tiết Top danh mục chi tiêu:\n{$topListText}\n"
    . "- Tổng số giao dịch đã ghi nhận: {$totalTx}\n\n"
    . "YÊU CẦU TRẢ LỜI:\n"
    . "1. Đóng vai Cố vấn tài chính cá nhân, trả lời thẳng vào câu hỏi của người dùng dựa trên các số liệu thực tế ở trên.\n"
    . "2. Giọng điệu thực tế, gần gũi, tích cực và khích lệ, không phán xét.\n"
    . "3. Đưa ra gợi ý hành động cụ thể, ngắn gọn, dễ áp dụng ngay bằng tiếng Việt.";

// 4. Gọi Google Gemini API qua cURL (tắt SSL verify cho môi trường dev Windows)
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
$envVars = file_exists($envFile) ? loadEnvFile($envFile) : [];
$apiKey  = $envVars['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? ($_ENV['GEMINI_API_KEY'] ?? '');

$answer = null;
$source = 'rule_based';

if (!empty($apiKey) && function_exists('curl_init')) {
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode($apiKey);

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
            'temperature'     => 0.6,
            'maxOutputTokens' => 800
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($postData, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $resJson = json_decode($response, true);
        $rawText = trim((string)($resJson['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($rawText !== '') {
            $answer = $rawText;
            $source = 'gemini-1.5-flash';
        }
    }
}

// 5. Cơ chế fallback Rule-based thông minh khi chưa có GEMINI_API_KEY hoặc lỗi mạng
if ($answer === null) {
    $answer = buildRuleBasedAdvice(
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
        $topCategoryPct
    );
}

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
 * Tạo câu trả lời tư vấn tài chính cá nhân dựa trên quy tắc thông minh (Rule-based Fallback)
 */
function buildRuleBasedAdvice(
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
    float $topCategoryPct
): string {
    $qLower      = mb_strtolower($question, 'UTF-8');
    $incomeStr   = formatMoneyChat($totalIncome, $currency);
    $expenseStr  = formatMoneyChat($totalExpense, $currency);
    $balanceStr  = formatMoneyChat($netBalance, $currency);
    $topCatStr   = $topCategoryName !== null
        ? "{$topCategoryName} (" . formatMoneyChat($topCategoryAmount, $currency) . ", chiếm {$topCategoryPct}% tổng chi)"
        : 'chưa có danh mục chi tiêu nổi bật';

    if ($totalIncome <= 0 && $totalExpense <= 0) {
        return "Chào {$userName}, trong tháng {$month}/{$year} ví của bạn chưa ghi nhận giao dịch thu chi nào nên mình chưa thể đánh giá chi tiết cho câu hỏi \"{$question}\". "
            . "Hãy bắt đầu ghi lại các khoản thu nhập và chi tiêu hàng ngày (dù là nhỏ nhất) để mình đồng hành và đưa ra lời khuyên sát thực tế nhất cho bạn nhé!";
    }

    // Nhận diện ý định câu hỏi của người dùng
    if (preg_match('/(mua|chi|tiêu|tốn|cắt giảm|hạn chế)/u', $qLower)) {
        if ($netBalance < 0) {
            return "Chào {$userName}, tháng {$month}/{$year} bạn đang chi {$expenseStr} trong khi thu nhập là {$incomeStr} (âm " . formatMoneyChat(abs($netBalance), $currency) . "). "
                . "Với câu hỏi của bạn, lời khuyên thực tế nhất lúc này là tạm hoãn các khoản mua sắm chưa cấp bách và rà soát lại danh mục chi nhiều nhất là {$topCatStr}. "
                . "Chỉ cần cắt giảm khoảng 15-20% ở nhóm này, bạn sẽ sớm đưa số dư về mức dương trở lại — cố lên nhé!";
        }
        return "Chào {$userName}, hiện tại số dư ròng tháng {$month}/{$year} của bạn đang dương {$balanceStr} (thu {$incomeStr}, chi {$expenseStr}). "
            . "Trước khi quyết định chi thêm, bạn hãy đối chiếu với danh mục đang chiếm tỷ trọng cao nhất là {$topCatStr} để tránh vượt ngưỡng an toàn. "
            . "Nếu khoản chi này thực sự cần thiết và vẫn giữ tỷ lệ tiết kiệm trên 20% (hiện tại là {$savingRate}%), bạn hoàn toàn có thể cân nhắc một cách chủ động!";
    }

    if (preg_match('/(tiết kiệm|tích lũy|dư|để dành|quỹ)/u', $qLower)) {
        if ($savingRate >= 20) {
            return "Tuyệt vời {$userName}! Tháng {$month}/{$year} bạn đang đạt tỷ lệ tiết kiệm {$savingRate}% với số dư ròng {$balanceStr} (thu {$incomeStr}, chi {$expenseStr}). "
                . "Bạn đang làm tốt hơn mức khuyến nghị 20% theo quy tắc 50/30/20. Hãy trích ngay một phần số dư này vào quỹ dự phòng khẩn cấp hoặc kênh tích lũy an toàn trước khi phát sinh thêm chi tiêu ở nhóm {$topCatStr}.";
        }
        $targetSave = formatMoneyChat($totalIncome * 0.2, $currency);
        return "Chào {$userName}, tháng {$month}/{$year} tỷ lệ tiết kiệm của bạn đang ở mức {$savingRate}% (số dư ròng {$balanceStr}). "
            . "Để chạm mốc lý tưởng 20% (tương đương {$targetSave}/tháng), bạn có thể bắt đầu bằng việc trích tiết kiệm ngay khi nhận thu nhập và đặt hạn mức cho danh mục tiêu nhiều nhất là {$topCatStr}. "
            . "Mỗi bước điều chỉnh nhỏ đều giúp ví tiền của bạn vững vàng hơn mỗi ngày!";
    }

    // Phản hồi tổng quan cá nhân hóa cho mọi câu hỏi khác
    $statusNote = $netBalance < 0
        ? "hiện đang thâm hụt " . formatMoneyChat(abs($netBalance), $currency) . ", bạn nên ưu tiên kiểm soát nhóm {$topCatStr} để sớm cân bằng lại dòng tiền."
        : ($savingRate >= 20
            ? "rất tích cực với số dư ròng {$balanceStr} và tỷ lệ tiết kiệm {$savingRate}%. Hãy tiếp tục duy trì kỷ luật tốt này!"
            : "đang có số dư dương {$balanceStr} (tỷ lệ tiết kiệm {$savingRate}%). Nếu tối ưu thêm khoảng 10-15% ở danh mục {$topCatStr}, bạn sẽ đạt mốc tiết kiệm 20% rất nhanh.");

    return "Chào {$userName}, dựa trên ví tiền thực tế tháng {$month}/{$year} của bạn (Thu: {$incomeStr} | Chi: {$expenseStr} | Danh mục chi nhiều nhất: {$topCatStr}), tình hình tài chính của bạn {$statusNote}";
}
