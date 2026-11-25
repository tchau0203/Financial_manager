<?php
// GET /api/auth/google_config.php -> {client_id} để frontend dựng nút "Đăng nhập với Google".
// client_id rỗng = chưa cấu hình -> frontend tự ẩn nút.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';
loadEnvFile(__DIR__ . '/../../.env');
$defaultClientId = '171317058723-ojhkos9up9gt9kldncj77obpbg4rm2b2.apps.googleusercontent.com';
$clientId = trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: $defaultClientId));

jsonResponse(['success' => true, 'client_id' => $clientId]);
