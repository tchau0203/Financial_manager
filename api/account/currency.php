<?php
/**
 * API Cấu hình Đa tiền tệ & Tỷ giá quy đổi
 * Phụ trách: Hoàng Nam (Backend)
 * Phương thức: GET (Lấy tiền tệ & tỷ giá) / POST (Đổi tiền tệ mặc định)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();
$pdo = getDbConnection();

// Bảng tỷ giá tham chiếu mặc định (Quy đổi sang VND)
$exchangeRates = [
    'VND' => ['rate' => 1.0,      'symbol' => 'đ', 'position' => 'after'],
    'USD' => ['rate' => 25400.0,  'symbol' => '$', 'position' => 'before'],
    'EUR' => ['rate' => 27200.0,  'symbol' => '€', 'position' => 'before'],
    'JPY' => ['rate' => 165.0,    'symbol' => '¥', 'position' => 'before']
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT currency FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currentCurrency = $stmt->fetchColumn() ?: 'VND';

    jsonResponse([
        'success' => true,
        'data' => [
            'current_currency' => $currentCurrency,
            'rates' => $exchangeRates
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    $newCurrency = strtoupper(trim($data['currency'] ?? 'VND'));

    if (!array_key_exists($newCurrency, $exchangeRates)) {
        jsonResponse(['success' => false, 'message' => 'Đơn vị tiền tệ không được hỗ trợ'], 400);
    }

    $stmt = $pdo->prepare('UPDATE users SET currency = ? WHERE id = ?');
    $stmt->execute([$newCurrency, $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã cập nhật đơn vị tiền tệ sang ' . $newCurrency,
        'currency' => $newCurrency
    ]);
}
