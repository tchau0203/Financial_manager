<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
}

$userId = requireAuth();
$data   = getJsonInput();

$fullName = trim($data['full_name'] ?? $data['name'] ?? '');
$email    = trim($data['email'] ?? '');
$phone    = trim($data['phone'] ?? '');
$currency = trim($data['currency'] ?? 'VND');

if (empty($fullName) || empty($email)) {
    jsonResponse(['error' => 'Họ tên và email không được để trống'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email không đúng định dạng'], 400);
}

$pdo = getDbConnection();

// Kiểm tra email có trùng với user khác hay không
$checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
$checkStmt->execute([$email, $userId]);
if ($checkStmt->fetch()) {
    jsonResponse(['error' => 'Email đã được sử dụng bởi tài khoản khác'], 409);
}

// Cập nhật thông tin
$emailAlerts = isset($data['email_alerts']) ? (int)(bool)$data['email_alerts'] : null;

if ($emailAlerts !== null) {
    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, currency = ?, email_alerts = ? WHERE id = ?');
    $stmt->execute([$fullName, $email, $phone, $currency, $emailAlerts, $userId]);
} else {
    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, currency = ? WHERE id = ?');
    $stmt->execute([$fullName, $email, $phone, $currency, $userId]);
}

// Thông tin tài khoản ngân hàng (phục vụ VietQR) - chỉ cập nhật khi client gửi lên
$bank = null;
if (array_key_exists('bank_code', $data) || array_key_exists('bank_account', $data) || array_key_exists('bank_account_name', $data)) {
    $bankCode = strtoupper(trim((string)($data['bank_code'] ?? '')));
    $bankAccount = preg_replace('/\s+/', '', (string)($data['bank_account'] ?? ''));
    $bankName = trim((string)($data['bank_account_name'] ?? ''));

    if ($bankCode !== '' && !preg_match('/^[A-Z0-9]{2,20}$/', $bankCode)) {
        jsonResponse(['error' => 'Mã ngân hàng không hợp lệ'], 400);
    }
    if ($bankAccount !== '' && !preg_match('/^[0-9]{6,20}$/', $bankAccount)) {
        jsonResponse(['error' => 'Số tài khoản phải gồm 6-20 chữ số'], 400);
    }
    if (mb_strlen($bankName) > 100) {
        jsonResponse(['error' => 'Tên chủ tài khoản quá dài (tối đa 100 ký tự)'], 400);
    }
    if (($bankCode === '') !== ($bankAccount === '')) {
        jsonResponse(['error' => 'Vui lòng nhập đủ cả ngân hàng và số tài khoản'], 400);
    }
    $bankName = function_exists('mb_strtoupper') ? mb_strtoupper($bankName, 'UTF-8') : strtoupper($bankName);
    $pdo->prepare('UPDATE users SET bank_code = ?, bank_account = ?, bank_account_name = ? WHERE id = ?')
        ->execute([$bankCode ?: null, $bankAccount ?: null, $bankName ?: null, $userId]);
    $bank = ['bank_code' => $bankCode, 'bank_account' => $bankAccount, 'bank_account_name' => $bankName];
}

$_SESSION['full_name'] = $fullName;

jsonResponse([
    'success' => true,
    'message' => 'Cập nhật thông tin thành công',
    'user'    => [
        'id'           => $userId,
        'full_name'    => $fullName,
        'email'        => $email,
        'phone'        => $phone,
        'currency'     => $currency,
        'email_alerts' => $emailAlerts
    ] + ($bank ?? [])
]);
