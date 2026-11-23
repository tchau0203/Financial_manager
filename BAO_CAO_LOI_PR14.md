# BÁO CÁO LỖI VÀ ĐÁNH GIÁ PULL REQUEST #14

> **Người thực hiện PR:** Hoàng Nam (`Hoangnam2606` - `Nam.0886936946@gmail.com`)  
> **Nhánh nguồn:** `feature/nam` (Pull Request #14)  
> **Commit ID:** `e77b68feb5b4f75ef9c42c652057af82ff6c5fbf`  
> **Tiêu đề commit:** `Cập nhật code`  
> **Mức độ rủi ro:** 🚨 **NGHIÊM TRỌNG (BLOCKER) - TUYỆT ĐỐI CHƯA MERGE**

---

## 1. TỔNG QUAN VẤN ĐỀ

Trong PR #14, bạn Hoàng Nam đã chỉnh sửa 6 file Back-end PHP:
* `api/budget/get.php`
* `api/budget/set.php`
* `api/notification/mark_read.php`
* `api/report/summary.php`
* ❌ **`api/category/list.php`** *(Ghi đè sai chức năng)*
* ❌ **`api/category/delete.php`** *(Ghi đè sai chức năng)*

Do nhầm lẫn trong quá trình code, **2 file cốt lõi của phân hệ Danh mục (`category`) đã bị dán nhầm toàn bộ nội dung của chức năng khác**, nếu merge sẽ làm tê liệt toàn bộ ứng dụng.

---

## 2. CHI TIẾT CÁC LỖI NGHIÊM TRỌNG

### ❌ LỖI 1: Dán nhầm code Thông báo vào API Lấy Danh mục (`api/category/list.php`)

* **Thực trạng trong PR #14:**
  Nam đã xóa toàn bộ câu lệnh SQL lấy danh mục `categories` và dán code lấy danh sách thông báo `notifications` vào file này:

```php
// Đoạn code Nam ghi đè vào api/category/list.php:
$stmt = $pdo->prepare(
    'SELECT id, title, message, type, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20'
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => [
        'notifications' => $notifications,
        'unread_count' => (int)$countStmt->fetchColumn()
    ]
]);
```

* **Hậu quả thực tế:**
  * Endpoint `api/category/list.php` không còn trả về danh sách danh mục (Ăn uống, Lương, Mua sắm...) mà trả về mảng thông báo `{ notifications: [...], unread_count: 0 }`.
  * **Toàn bộ Front-end sẽ bị hỏng:** Các trang `dashboard.html`, `income.html`, `expense.html`, `budget.html`, `account.html` đều không lấy được danh mục $\rightarrow$ **Menu chọn danh mục bị trống sạch**, người dùng không thể chọn danh mục để thêm hay sửa giao dịch.

---

### ❌ LỖI 2: Dán nhầm code Xóa ngân sách vào API Xóa danh mục (`api/category/delete.php`)

* **Thực trạng trong PR #14:**
  Nam đã xóa code xóa danh mục và dán nhầm code xóa ngân sách `DELETE FROM budgets` vào:

```php
// Đoạn code Nam ghi đè vào api/category/delete.php:
$stmt = $pdo->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy ngân sách'], 404);
}

jsonResponse(['success' => true, 'message' => 'Đã xóa ngân sách thành công']);
```

* **Hậu quả thực tế:**
  * Khi người dùng vào trang Cài đặt tài khoản để xóa 1 danh mục cá nhân của mình, API lại chạy lệnh xóa bản ghi trong bảng **`budgets` (Ngân sách)**.
  * Danh mục không hề bị xóa, nhưng hạn mức ngân sách của người dùng lại bị xóa oan.

---

## 3. HƯỚNG DẪN KHẮC PHỤC DÀNH CHO BẠN HOÀNG NAM

Bạn Nam cần khôi phục lại 2 file này về bản chuẩn trên nhánh `feature/nam` theo các bước sau:

### Cách 1: Dùng lệnh Git (Nhanh nhất)
Mở Terminal/PowerShell tại thư mục dự án và chạy:

```bash
# 1. Chuyển sang nhánh feature/nam
git checkout feature/nam

# 2. Khôi phục 2 file bị dán nhầm về bản chuẩn của nhánh chính
git checkout origin/feature/test-code -- api/category/list.php api/category/delete.php

# 3. Commit và đẩy lại lên GitHub
git commit -m "fix: restore original category list and delete API files"
git push origin feature/nam
```

---

### Cách 2: Khôi phục nội dung thủ công

#### File 1: `api/category/list.php` chuẩn:
```php
<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';
$userId = requireAuth();

$type = $_GET['type'] ?? '';
$pdo = getDbConnection();

$sql = "
    SELECT c.*, COUNT(t.id) as transaction_count
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = :userId
    WHERE (c.user_id IS NULL OR c.user_id = :userId2)
";

$params = [
    ':userId' => $userId,
    ':userId2' => $userId
];

if (in_array($type, ['income', 'expense'])) {
    $sql .= " AND c.type = :type";
    $params[':type'] = $type;
}

$sql .= " GROUP BY c.id ORDER BY c.type, c.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $categories
]);
```

#### File 2: `api/category/delete.php` chuẩn:
```php
<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/db.php';

$userId = requireAuth();

$data = getJsonInput();
$id   = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'ID danh mục là bắt buộc'], 400);
}

$pdo = getDbConnection();

// Chỉ cho phép xoá danh mục do người dùng tạo
$stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Không tìm thấy danh mục hoặc bạn không có quyền xoá'], 404);
}

// Kiểm tra danh mục có giao dịch không
$stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
if ((int)$stmt->fetchColumn() > 0) {
    jsonResponse(['success' => false, 'message' => 'Không thể xoá danh mục đang có giao dịch. Vui lòng xoá các giao dịch trước'], 400);
}

$stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);

jsonResponse([
    'success' => true,
    'message' => 'Đã xoá danh mục thành công'
]);
```

---

## 4. KẾT LUẬN

Sau khi bạn Hoàng Nam hoàn thành việc khôi phục 2 file trên và đẩy lại lên nhánh `feature/nam`, trưởng nhóm sẽ tiến hành review lại một lần nữa trước khi duyệt merge PR #14 vào nhánh chính.
