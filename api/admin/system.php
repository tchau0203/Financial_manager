<?php
/**
 * API Quản trị viên: Nhật ký hoạt động (Audit Logs) & Sao lưu Hệ thống (System Backup)
 * Phụ trách: Thái Châu (Lead)
 * - GET  /api/admin/system.php                      -> Lấy danh sách nhật ký quản trị & thông tin bảng CSDL
 * - GET  /api/admin/system.php?download=sql|json    -> Tải xuống tệp sao lưu toàn bộ CSDL
 * - POST /api/admin/system.php                      -> Dọn dẹp nhật ký cũ
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$adminId = requireAdmin();
$pdo     = getDbConnection();
$method  = $_SERVER['REQUEST_METHOD'];

// 1. Xử lý tải file Sao lưu toàn bộ CSDL (SQL hoặc JSON)
if ($method === 'GET' && !empty($_GET['download'])) {
    $format = strtolower(trim($_GET['download']));
    $timestamp = date('Y-m-d_H-i-s');

    $tables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    logAdminAction($pdo, $adminId, 'export_backup', strtoupper($format) . ' Backup', "Tải bản sao lưu toàn bộ CSDL (" . count($tables) . " bảng)");

    if ($format === 'json') {
        $dump = [
            'system'      => 'CashManager Full Database Backup',
            'exported_at' => date('c'),
            'exported_by' => $adminId,
            'tables'      => []
        ];
        foreach ($tables as $tbl) {
            $dump['tables'][$tbl] = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll();
        }

        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"cashmanager_backup_{$timestamp}.json\"");
        echo json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Mặc định xuất file .SQL chuẩn có thể import vào phpMyAdmin
    header('Content-Type: application/sql; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"cashmanager_backup_{$timestamp}.sql\"");

    $sqlOut  = "-- ========================================================\n";
    $sqlOut .= "-- CASHMANAGER FULL DATABASE BACKUP\n";
    $sqlOut .= "-- Thời gian sao lưu: " . date('Y-m-d H:i:s') . "\n";
    $sqlOut .= "-- Người thực hiện: Admin ID #{$adminId}\n";
    $sqlOut .= "-- ========================================================\n\n";
    $sqlOut .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sqlOut .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

    foreach ($tables as $tbl) {
        $createRow = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_NUM);
        $createSql = $createRow[1] ?? '';
        $createSql = preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $createSql, 1);

        $sqlOut .= "-- --------------------------------------------------------\n";
        $sqlOut .= "-- Cấu trúc và dữ liệu bảng `{$tbl}`\n";
        $sqlOut .= "-- --------------------------------------------------------\n";
        $sqlOut .= "{$createSql};\n\n";

        $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_map(fn($c) => "`{$c}`", array_keys($rows[0]));
            $colList = implode(', ', $cols);

            foreach ($rows as $r) {
                $vals = [];
                foreach ($r as $val) {
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string)$val);
                    }
                }
                $sqlOut .= "REPLACE INTO `{$tbl}` ({$colList}) VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sqlOut .= "\n";
        }
    }

    $sqlOut .= "SET FOREIGN_KEY_CHECKS=1;\n";
    echo $sqlOut;
    exit;
}

// 2. Lấy danh sách Nhật ký hoạt động & Thống kê bảng CSDL
if ($method === 'GET') {
    $logsStmt = $pdo->query("
        SELECT 
            l.id, l.admin_id, l.action, l.target, l.details, l.ip_address, l.created_at,
            u.username AS admin_username, u.full_name AS admin_name
        FROM admin_logs l
        LEFT JOIN users u ON u.id = l.admin_id
        ORDER BY l.id DESC
        LIMIT 100
    ");
    $logs = $logsStmt->fetchAll();

    // Lấy thống kê các bảng trong CSDL
    $tablesInfo = [];
    try {
        $statusStmt = $pdo->query("SHOW TABLE STATUS");
        while ($t = $statusStmt->fetch()) {
            $sizeKb = round(((int)($t['Data_length'] ?? 0) + (int)($t['Index_length'] ?? 0)) / 1024, 2);
            $tablesInfo[] = [
                'name'       => $t['Name'],
                'rows'       => (int)($t['Rows'] ?? 0),
                'size_kb'    => $sizeKb,
                'engine'     => $t['Engine'] ?? 'InnoDB',
                'collation'  => $t['Collation'] ?? 'utf8mb4'
            ];
        }
    } catch (Throwable $e) {}

    jsonResponse([
        'success' => true,
        'data' => [
            'logs'        => $logs,
            'tables_info' => $tablesInfo,
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s')
        ]
    ]);
}

// 3. Xóa bớt nhật ký cũ
if ($method === 'POST') {
    $data   = getJsonInput();
    $action = trim((string)($data['action'] ?? ''));

    if ($action === 'clear_logs') {
        $pdo->exec("DELETE FROM admin_logs");
        logAdminAction($pdo, $adminId, 'clear_logs', 'admin_logs', 'Xóa làm mới toàn bộ nhật ký hoạt động quản trị');
        jsonResponse([
            'success' => true,
            'message' => 'Đã xóa làm mới nhật ký hoạt động quản trị'
        ]);
    }

    jsonResponse(['error' => 'Hành động không hợp lệ'], 400);
}

jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
