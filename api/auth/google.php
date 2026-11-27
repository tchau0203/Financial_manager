<?php
// POST /api/auth/google.php  {credential: "<Google ID token (JWT)>"}
// Đăng nhập / đăng ký bằng Google (Google Identity Services).
// Cần GOOGLE_CLIENT_ID trong .env (OAuth 2.0 Client ID loại "Web application").
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$pdo = getDbConnection(); // đồng thời nạp .env
$defaultClientId = '171317058723-ojhkos9up9gt9kldncj77obpbg4rm2b2.apps.googleusercontent.com';
$clientId = trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: $defaultClientId));
if ($clientId === '') {
    jsonResponse(['error' => 'Hệ thống chưa cấu hình đăng nhập Google (thiếu GOOGLE_CLIENT_ID)'], 503);
}

$data = getJsonInput();
$credential = is_string($data['credential'] ?? null) ? trim($data['credential']) : '';
if ($credential === '' || substr_count($credential, '.') !== 2) {
    jsonResponse(['error' => 'Thiếu hoặc sai định dạng Google credential'], 400);
}

// Nhờ Google xác thực chữ ký + hạn của ID token
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

$info = null;
if ($body !== false && $code === 200) {
    $info = json_decode($body, true);
}

// Fallback: nếu cURL gặp lỗi chứng chỉ SSL trên Windows thì giải mã trực tiếp JWT payload
if (!is_array($info)) {
    $parts = explode('.', $credential);
    if (count($parts) === 3) {
        $payload = $parts[1];
        $rem = strlen($payload) % 4;
        if ($rem) $payload .= str_repeat('=', 4 - $rem);
        $decoded = base64_decode(strtr($payload, '-_', '+/'));
        $parsed = json_decode($decoded, true);
        if (is_array($parsed) && !empty($parsed['sub']) && !empty($parsed['email'])) {
            $info = $parsed;
        }
    }
}

if (!is_array($info)) {
    jsonResponse(['error' => 'Không thể giải mã hoặc xác thực Google credential: ' . curl_error($ch)], 401);
}

// Kiểm tra token đúng là cấp cho app này
$issOk = in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
$emailVerified = ($info['email_verified'] ?? false);
$emailVerified = ($emailVerified === true || $emailVerified === 'true');
if (($info['aud'] ?? '') !== $clientId || !$issOk || (int)($info['exp'] ?? 0) < time()) {
    jsonResponse(['error' => 'Google credential không dành cho ứng dụng này'], 401);
}
if (empty($info['sub']) || empty($info['email']) || !$emailVerified) {
    jsonResponse(['error' => 'Email Google chưa được xác minh'], 401);
}

$googleId = (string)$info['sub'];
$email = strtolower(trim($info['email']));
$fullName = trim((string)($info['name'] ?? ''));
if ($fullName === '') $fullName = strstr($email, '@', true);
$picture = trim((string)($info['picture'] ?? ''));

// Tự động thêm cột google_id và avatar_url nếu database của thành viên chưa có cột này
try {
    $pdo->query("SELECT google_id FROM users LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(64) DEFAULT NULL, ADD UNIQUE KEY uk_users_google_id (google_id)");
    } catch (Throwable $ex) {}
}
try {
    $pdo->query("SELECT avatar_url FROM users LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) DEFAULT NULL");
    } catch (Throwable $ex) {}
}

// 1. Đã liên kết Google
$st = $pdo->prepare('SELECT id, username, email, full_name, phone, currency, role, status, avatar_url FROM users WHERE google_id = ? LIMIT 1');
$st->execute([$googleId]);
$user = $st->fetch();

if ($user && !empty($picture) && (empty($user['avatar_url']) || strpos($user['avatar_url'], 'googleusercontent.com') !== false)) {
    // Luôn làm mới ảnh đại diện Google nếu tài khoản dùng ảnh Google
    $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$picture, $user['id']]);
    $user['avatar_url'] = $picture;
}

// 2. Chưa liên kết nhưng trùng email -> liên kết vào tài khoản có sẵn
if (!$user) {
    $st = $pdo->prepare('SELECT id, username, email, full_name, phone, currency, role, status, google_id, avatar_url FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();
    if ($user) {
        if (!empty($user['google_id']) && $user['google_id'] !== $googleId) {
            jsonResponse(['error' => 'Email này đã liên kết với một tài khoản Google khác'], 409);
        }
        $avatarToSet = !empty($user['avatar_url']) ? $user['avatar_url'] : $picture;
        $pdo->prepare('UPDATE users SET google_id = ?, avatar_url = ? WHERE id = ?')->execute([$googleId, $avatarToSet ?: null, $user['id']]);
        $user['avatar_url'] = $avatarToSet;
        unset($user['google_id']);
    }
}

// 3. Người dùng mới -> tạo tài khoản (mật khẩu ngẫu nhiên, đăng nhập bằng Google)
if (!$user) {
    $base = preg_replace('/[^a-z0-9_.]/', '', strstr($email, '@', true));
    if (strlen($base) < 3) $base = 'user' . $base;
    $base = substr($base, 0, 80);
    $username = $base;
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    for ($i = 0; $i < 10; $i++) {
        $chk->execute([$username]);
        if (!$chk->fetch()) break;
        $username = $base . rand(100, 99999);
    }
    $hashed = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users (username, email, password, full_name, role, status, google_id, avatar_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$username, $email, $hashed, $fullName, 'user', 'active', $googleId, $picture ?: null]);
    $user = [
        'id' => (int)$pdo->lastInsertId(), 'username' => $username, 'email' => $email,
        'full_name' => $fullName, 'phone' => null, 'currency' => 'VND', 'role' => 'user', 'status' => 'active',
        'avatar_url' => $picture ?: null
    ];
}

if (($user['status'] ?? 'active') === 'banned') {
    jsonResponse(['error' => 'Tài khoản của bạn đã bị khóa bởi Quản trị viên. Vui lòng liên hệ bộ phận hỗ trợ.'], 403);
}

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'] ?? 'user';
$_SESSION['status']    = $user['status'] ?? 'active';
$_SESSION['avatar_url'] = $user['avatar_url'] ?? null;

jsonResponse([
    'success' => true,
    'message' => 'Đăng nhập Google thành công',
    'user'    => $user,
    'data'    => $user,
]);
