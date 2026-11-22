<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/db.php';

$userId = requireAuth();
$pdo = getDbConnection();

$allowedCurrencies = ['VND', 'USD', 'EUR', 'JPY', 'GBP'];

// Tỷ giá tham chiếu: 1 đơn vị ngoại tệ đổi sang bao nhiêu VND.
// Đây là các giá trị mẫu theo kế hoạch, không phải tỷ giá trực tuyến.
$ratesInVnd = [
    'VND' => 1,
    'USD' => 25400,
    'EUR' => 27200,
    'JPY' => 170,
    'GBP' => 32500
];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT currency FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currency = $stmt->fetchColumn();

    if (!$currency) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy tài khoản'], 404);
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'currency' => strtoupper($currency),
            'base_currency' => 'VND',
            'rates_in_vnd' => $ratesInVnd
        ]
    ]);
}

if ($method === 'POST') {
    $data = getJsonInput();
    $currency = strtoupper(trim($data['currency'] ?? ''));

    if (!in_array($currency, $allowedCurrencies, true)) {
        jsonResponse(['success' => false, 'message' => 'Đơn vị tiền tệ không hợp lệ'], 400);
    }

    $stmt = $pdo->prepare('UPDATE users SET currency = ? WHERE id = ?');
    $stmt->execute([$currency, $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã cập nhật đơn vị tiền tệ',
        'data' => [
            'currency' => $currency,
            'base_currency' => 'VND',
            'rates_in_vnd' => $ratesInVnd
        ]
    ]);
}

jsonResponse(['success' => false, 'message' => 'Phương thức không hợp lệ'], 405);