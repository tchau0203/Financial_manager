<?php
/**
 * API Lấy và thêm giao dịch trong Quỹ chung
 * Phụ trách: Thái Châu (Backend)
 * Phương thức: GET (Lấy giao dịch) / POST (Thêm giao dịch trong quỹ)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

// TODO (Thái Châu): Xử lý GET và POST cho giao dịch quỹ chung

jsonResponse([
    'success' => true,
    'data' => [
        'transactions' => [],
        'message' => 'Endpoint giao dịch quỹ nhóm sẵn sàng'
    ]
]);
