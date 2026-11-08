<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

function loadEnvFile($filePath) {
    if (!file_exists($filePath)) return [];
    $vars = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $vars[$name] = $value;
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
    return $vars;
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        $env = loadEnvFile($envFile);

        $host   = $env['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port   = (int)($env['DB_PORT'] ?? getenv('DB_PORT') ?: 3307);
        $dbname = $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'quanlychitieu';
        $user   = $env['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $pass   = $env['DB_PASS'] ?? getenv('DB_PASS') ?? '';

        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Kết nối CSDL thất bại: ' . $e->getMessage()], 500);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Chưa đăng nhập'], 401);
    }
    return (int)$_SESSION['user_id'];
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if ($raw) {
        return json_decode($raw, true) ?? [];
    }
    return $_POST ?? [];
}

function checkBudgetAlerts($userId, $pdo) {
    $currentMonth = (int)date('n');
    $currentYear  = (int)date('Y');

    $stmt = $pdo->prepare('
        SELECT b.*, c.name as category_name 
        FROM budgets b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = ? AND b.month = ? AND b.year = ?
    ');
    $stmt->execute([$userId, $currentMonth, $currentYear]);
    $budgets = $stmt->fetchAll();

    foreach ($budgets as $b) {
        $spentStmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) FROM transactions 
            WHERE user_id = ? AND category_id = ? AND type = "expense"
              AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
        ');
        $spentStmt->execute([$userId, $b['category_id'], $currentMonth, $currentYear]);
        $spent = (float)$spentStmt->fetchColumn();

        $budgetAmount = (float)$b['amount'];
        $threshold = (float)($b['alert_threshold'] ?? 80);
        if ($budgetAmount > 0) {
            $pct = ($spent / $budgetAmount) * 100;
            if ($pct >= 100) {
                $title = "Cảnh báo vượt ngân sách";
                $msg = "Bạn đã chi tiêu " . number_format($spent, 0, ',', '.') . " đ vượt hạn mức " . number_format($budgetAmount, 0, ',', '.') . " đ cho danh mục " . ($b['category_name'] ?? 'Chung');
                $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, "danger")')->execute([$userId, $title, $msg]);
            } elseif ($pct >= $threshold) {
                $title = "Cảnh báo sắp đạt hạn mức";
                $msg = "Bạn đã chi " . round($pct, 1) . "% hạn mức ngân sách cho danh mục " . ($b['category_name'] ?? 'Chung');
                $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, "warning")')->execute([$userId, $title, $msg]);
            }
        }
    }
}
?>
