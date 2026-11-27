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
        $port   = (int)($env['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
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

            // Tự động kiểm tra và nâng cấp cột role & status, đảm bảo tài khoản Admin mặc định
            static $checkedAdminRole = false;
            if (!$checkedAdminRole) {
                $checkedAdminRole = true;
                try {
                    $colChk = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
                    if (!$colChk) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user'");
                    }
                    $statusChk = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch();
                    if (!$statusChk) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'banned') NOT NULL DEFAULT 'active'");
                    }
                    // Tự động tạo bảng nhật ký hoạt động quản trị (admin_logs)
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS `admin_logs` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `admin_id` INT NOT NULL,
                            `action` VARCHAR(50) NOT NULL,
                            `target` VARCHAR(255) DEFAULT NULL,
                            `details` TEXT DEFAULT NULL,
                            `ip_address` VARCHAR(45) DEFAULT NULL,
                            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    // Đảm bảo có tài khoản Admin mặc định (admin / Admin@123456)
                    $adminChk = $pdo->query("SELECT id FROM users WHERE username = 'admin' OR role = 'admin' LIMIT 1")->fetch();
                    if (!$adminChk) {
                        $hashed = '$2y$10$tq6XvVOf6CfTcheS3md2iuKCziEr6Alx9dhILYYYd4q.6HX4BNDdO'; // Admin@123456
                        $pdo->exec("INSERT INTO users (username, email, password, full_name, role, status, currency) VALUES ('admin', 'admin@cashmanager.vn', '$hashed', 'Quản trị viên Hệ thống', 'admin', 'active', 'VND')");
                    }
                } catch (Throwable $e) {}

                // Tự động thêm cột vị trí cho bản đồ chi tiêu (tương đương database/migration_sprint4_map.sql)
                try {
                    $geoChk = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'latitude'")->fetch();
                    if (!$geoChk) {
                        $pdo->exec("ALTER TABLE transactions
                            ADD COLUMN latitude DECIMAL(9,6) DEFAULT NULL,
                            ADD COLUMN longitude DECIMAL(9,6) DEFAULT NULL,
                            ADD COLUMN location_name VARCHAR(255) DEFAULT NULL");
                    }
                } catch (Throwable $e) {
                    error_log('[geo-migration] ' . $e->getMessage());
                }

                // Tự động thêm cột avatar_url cho người dùng (Google avatar & ảnh đại diện tùy chỉnh)
                try {
                    $avatarChk = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->fetch();
                    if (!$avatarChk) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) DEFAULT NULL AFTER currency");
                    }
                } catch (Throwable $e) {
                    error_log('[avatar-migration] ' . $e->getMessage());
                }
            }
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

function requireAdmin() {
    $userId = requireAuth();
    $pdo = getDbConnection();
    $st = $pdo->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        session_destroy();
        jsonResponse(['error' => 'Tài khoản không tồn tại'], 401);
    }
    if (($row['status'] ?? 'active') === 'banned') {
        session_destroy();
        jsonResponse(['error' => 'Tài khoản của bạn đã bị khóa bởi Quản trị viên'], 403);
    }
    $role = $row['role'] ?: 'user';
    $_SESSION['role'] = $role;
    if ($role !== 'admin') {
        jsonResponse(['error' => 'Bạn không có quyền quản trị viên (Admin)'], 403);
    }
    return $userId;
}

/**
 * Ghi nhận nhật ký hoạt động của Quản trị viên (Audit Log)
 */
function logAdminAction($pdo, $adminId, $action, $target = null, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $st = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, target, details, ip_address) VALUES (?, ?, ?, ?, ?)');
        $st->execute([(int)$adminId, (string)$action, $target, $details, $ip]);
    } catch (Throwable $e) {
        error_log('[admin-log] ' . $e->getMessage());
    }
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if ($raw) {
        return json_decode($raw, true) ?? [];
    }
    return $_POST ?? [];
}

/**
 * Đọc & kiểm tra vị trí (không bắt buộc) từ payload thêm/sửa giao dịch.
 * Trả về ['ok' => bool, 'lat' => ?float, 'lng' => ?float, 'name' => ?string].
 * - Không gửi tọa độ (null/rỗng) => hợp lệ, lưu NULL.
 * - Chỉ có 1 trong 2 tọa độ, sai kiểu hoặc ngoài phạm vi => ok = false.
 */
function parseLocationInput(array $data): array {
    $latRaw = $data['latitude'] ?? null;
    $lngRaw = $data['longitude'] ?? null;
    $hasLat = $latRaw !== null && $latRaw !== '';
    $hasLng = $lngRaw !== null && $lngRaw !== '';

    if (!$hasLat && !$hasLng) {
        return ['ok' => true, 'lat' => null, 'lng' => null, 'name' => null];
    }
    if ($hasLat !== $hasLng || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
        return ['ok' => false, 'lat' => null, 'lng' => null, 'name' => null];
    }

    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return ['ok' => false, 'lat' => null, 'lng' => null, 'name' => null];
    }

    $name = is_string($data['location_name'] ?? null) ? trim($data['location_name']) : '';
    if (mb_strlen($name) > 255) {
        return ['ok' => false, 'lat' => null, 'lng' => null, 'name' => null];
    }

    return ['ok' => true, 'lat' => round($lat, 6), 'lng' => round($lng, 6), 'name' => $name !== '' ? $name : null];
}

/**
 * Gửi email cảnh báo ngân sách nếu user bật email_alerts. Không bao giờ làm hỏng luồng chính.
 */
function sendBudgetAlertEmail($pdo, $userId, $catName, $limit, $spent, $pct, $status) {
    try {
        require_once __DIR__ . '/mail.php';
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_alerts'")->fetch();
        $sql = $col ? 'SELECT email, full_name, email_alerts FROM users WHERE id = ?'
                    : 'SELECT email, full_name, 1 AS email_alerts FROM users WHERE id = ?';
        $st = $pdo->prepare($sql);
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u || !(int)$u['email_alerts'] || empty($u['email'])) return false;
        $subject = ($status === 'exceeded' ? 'Vượt ngân sách: ' : 'Sắp hết ngân sách: ') . $catName;
        return sendMail($u['email'], $subject, buildBudgetAlertEmail($u['full_name'], $catName, $limit, $spent, $pct, $status));
    } catch (Throwable $e) {
        error_log('[budget-mail] ' . $e->getMessage());
        return false;
    }
}

function checkBudgetAlerts($userId, $pdo, $month = null, $year = null) {
    $targetMonth = $month ? (int)$month : (int)date('n');
    $targetYear  = $year ? (int)$year : (int)date('Y');

    $stmt = $pdo->prepare('
        SELECT b.*, c.name as category_name 
        FROM budgets b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = ? AND b.month = ? AND b.year = ?
    ');
    $stmt->execute([$userId, $targetMonth, $targetYear]);
    $budgets = $stmt->fetchAll();

    foreach ($budgets as $b) {
        $spentStmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) FROM transactions 
            WHERE user_id = ? AND category_id = ? AND type = "expense"
              AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
        ');
        $spentStmt->execute([$userId, $b['category_id'], $targetMonth, $targetYear]);
        $spent = (float)$spentStmt->fetchColumn();

        $budgetAmount = (float)$b['amount'];
        $threshold = (float)($b['alert_threshold'] ?? 80);
        if ($budgetAmount > 0) {
            $pct = ($spent / $budgetAmount) * 100;
            $catName = $b['category_name'] ?? 'Chung';
            
            if ($pct >= 100) {
                $title = "Cảnh báo vượt ngân sách";
                $msg = "Bạn đã chi tiêu " . number_format($spent, 0, ',', '.') . " đ vượt hạn mức " . number_format($budgetAmount, 0, ',', '.') . " đ cho danh mục " . $catName;
                
                // Tránh lặp thông báo nếu cảnh báo chưa đọc đã tồn tại
                $checkStmt = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? AND title = ? AND message LIKE ? AND is_read = 0 LIMIT 1');
                $checkStmt->execute([$userId, $title, "%$catName%"]);
                if (!$checkStmt->fetch()) {
                    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, "danger")')->execute([$userId, $title, $msg]);
                    sendBudgetAlertEmail($pdo, $userId, $catName, $budgetAmount, $spent, $pct, 'exceeded');
                }
            } elseif ($pct >= $threshold) {
                $title = "Cảnh báo sắp đạt hạn mức";
                $msg = "Bạn đã chi " . round($pct, 1) . "% hạn mức ngân sách cho danh mục " . $catName;
                
                $checkStmt = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? AND title = ? AND message LIKE ? AND is_read = 0 LIMIT 1');
                $checkStmt->execute([$userId, $title, "%$catName%"]);
                if (!$checkStmt->fetch()) {
                    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, "warning")')->execute([$userId, $title, $msg]);
                    sendBudgetAlertEmail($pdo, $userId, $catName, $budgetAmount, $spent, $pct, 'warning');
                }
            }
        }
    }
}
?>
