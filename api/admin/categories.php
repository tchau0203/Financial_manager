<?php
/**
 * API Quản trị viên: Quản lý Danh mục mặc định hệ thống (Default Categories - user_id IS NULL)
 * Phụ trách: Thái Châu (Lead)
 * - GET  /api/admin/categories.php -> Lấy danh sách danh mục mặc định kèm thống kê số lần sử dụng
 * - POST /api/admin/categories.php -> Thêm mới, chỉnh sửa, hoặc xóa danh mục mặc định
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../../config/db.php';

$adminId = requireAdmin();
$pdo     = getDbConnection();
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT 
            c.id, c.name, c.type, c.icon, c.color, c.created_at,
            (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id) AS tx_count,
            (SELECT COUNT(*) FROM budgets b WHERE b.category_id = c.id) AS budget_count
        FROM categories c
        WHERE c.user_id IS NULL
        ORDER BY c.type ASC, c.id ASC
    ");
    $categories = $stmt->fetchAll();

    foreach ($categories as &$c) {
        $c['id']           = (int)$c['id'];
        $c['tx_count']     = (int)$c['tx_count'];
        $c['budget_count'] = (int)$c['budget_count'];
        $c['icon']         = $c['icon'] ?: 'fa-tag';
        $c['color']        = $c['color'] ?: '#4F46E5';
    }
    unset($c);

    jsonResponse([
        'success' => true,
        'data'    => $categories
    ]);
}

if ($method === 'POST') {
    $data   = getJsonInput();
    $action = trim((string)($data['action'] ?? ''));

    if ($action === 'create') {
        $name  = trim((string)($data['name'] ?? ''));
        $type  = in_array($data['type'] ?? 'expense', ['income', 'expense'], true) ? $data['type'] : 'expense';
        $icon  = trim((string)($data['icon'] ?? 'fa-tag')) ?: 'fa-tag';
        $color = trim((string)($data['color'] ?? '#4F46E5')) ?: '#4F46E5';

        if ($name === '') {
            jsonResponse(['error' => 'Tên danh mục không được để trống'], 400);
        }

        $chk = $pdo->prepare('SELECT id FROM categories WHERE user_id IS NULL AND name = ? AND type = ? LIMIT 1');
        $chk->execute([$name, $type]);
        if ($chk->fetch()) {
            jsonResponse(['error' => 'Danh mục mặc định này đã tồn tại'], 409);
        }

        $ins = $pdo->prepare('INSERT INTO categories (user_id, name, type, icon, color) VALUES (NULL, ?, ?, ?, ?)');
        $ins->execute([$name, $type, $icon, $color]);
        $newId = (int)$pdo->lastInsertId();

        logAdminAction($pdo, $adminId, 'create_category', "{$name} (#{$newId})", "Thêm danh mục mặc định loại {$type}");

        jsonResponse([
            'success' => true,
            'message' => "Đã thêm danh mục mặc định '{$name}' thành công",
            'id'      => $newId
        ]);
    }

    if ($action === 'update') {
        $catId = (int)($data['id'] ?? 0);
        $name  = trim((string)($data['name'] ?? ''));
        $type  = in_array($data['type'] ?? 'expense', ['income', 'expense'], true) ? $data['type'] : 'expense';
        $icon  = trim((string)($data['icon'] ?? 'fa-tag')) ?: 'fa-tag';
        $color = trim((string)($data['color'] ?? '#4F46E5')) ?: '#4F46E5';

        if ($catId <= 0 || $name === '') {
            jsonResponse(['error' => 'Thiếu ID hoặc tên danh mục'], 400);
        }

        $chk = $pdo->prepare('SELECT id, name FROM categories WHERE id = ? AND user_id IS NULL LIMIT 1');
        $chk->execute([$catId]);
        $existing = $chk->fetch();
        if (!$existing) {
            jsonResponse(['error' => 'Không tìm thấy danh mục mặc định này'], 404);
        }

        $upd = $pdo->prepare('UPDATE categories SET name = ?, type = ?, icon = ?, color = ? WHERE id = ? AND user_id IS NULL');
        $upd->execute([$name, $type, $icon, $color, $catId]);

        logAdminAction($pdo, $adminId, 'update_category', "{$name} (#{$catId})", "Cập nhật danh mục mặc định (icon: {$icon}, màu: {$color})");

        jsonResponse([
            'success' => true,
            'message' => "Đã cập nhật danh mục '{$name}' thành công"
        ]);
    }

    if ($action === 'delete') {
        $catId = (int)($data['id'] ?? 0);
        if ($catId <= 0) {
            jsonResponse(['error' => 'Thiếu ID danh mục cần xóa'], 400);
        }

        $chk = $pdo->prepare('SELECT id, name, type FROM categories WHERE id = ? AND user_id IS NULL LIMIT 1');
        $chk->execute([$catId]);
        $existing = $chk->fetch();
        if (!$existing) {
            jsonResponse(['error' => 'Không tìm thấy danh mục mặc định này'], 404);
        }

        // Kiểm tra xem có giao dịch nào đang gắn với danh mục này không
        $txChk = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = ?');
        $txChk->execute([$catId]);
        $txCount = (int)$txChk->fetchColumn();

        if ($txCount > 0) {
            jsonResponse([
                'error' => "Không thể xóa danh mục '{$existing['name']}' vì đang có {$txCount} giao dịch của người dùng sử dụng danh mục này. Bạn có thể đổi tên hoặc biểu tượng của nó."
            ], 409);
        }

        $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id IS NULL')->execute([$catId]);
        logAdminAction($pdo, $adminId, 'delete_category', "{$existing['name']} (#{$catId})", "Xóa danh mục mặc định loại {$existing['type']}");

        jsonResponse([
            'success' => true,
            'message' => "Đã xóa danh mục mặc định '{$existing['name']}'"
        ]);
    }

    jsonResponse(['error' => 'Hành động không hợp lệ'], 400);
}

jsonResponse(['error' => 'Phương thức không hợp lệ'], 405);
