# TÀI LIỆU CẤU TRÚC VÀ CHỨC NĂNG DỰ ÁN QUẢN LÝ CHI TIÊU

Hệ thống ứng dụng web quản lý tài chính cá nhân thông minh, xây dựng bằng **PHP thuần**, **MySQL (PDO)**, **HTML/CSS/JavaScript thuần** và **Chart.js**.

---

## 🛠️ Tech Stack (Công nghệ sử dụng)

- **Frontend**: HTML5, CSS3, JavaScript ES6 thuần (không dùng framework hay thư viện như React, Vue, jQuery).
- **Backend**: PHP thuần (không dùng Laravel, CodeIgniter, Symfony).
- **Database**: MySQL với kết nối chuẩn **PDO MySQL** (chống tấn công SQL Injection bằng Prepared Statements).
- **Thư viện biểu đồ**: **Chart.js** (tải qua CDN).
- **Icons**: Font Awesome 6 (tải qua CDN).

---

## 📁 Cấu Trúc Thư Mục Tổng Quan

```text
quanlychitieu/
├── config/
│   └── db.php                           # Kết nối CSDL PDO, Session, Helper & Cảnh báo ngân sách
├── api/
│   ├── auth/
│   │   ├── register.php                 # API Đăng ký tài khoản
│   │   ├── login.php                    # API Đăng nhập
│   │   ├── me.php                       # API Kiểm tra phiên đăng nhập
│   │   └── logout.php                   # API Đăng xuất
│   ├── transaction/
│   │   ├── add.php                      # API Thêm giao dịch thu / chi
│   │   ├── edit.php                     # API Chỉnh sửa giao dịch
│   │   ├── delete.php                   # API Xóa giao dịch
│   │   └── list.php                     # API Lấy danh sách giao dịch (lọc & phân trang)
│   ├── category/
│   │   ├── add.php                      # API Thêm danh mục riêng
│   │   ├── edit.php                     # API Sửa danh mục cá nhân
│   │   ├── delete.php                   # API Xóa danh mục cá nhân
│   │   └── list.php                     # API Lấy danh sách danh mục (hệ thống + cá nhân)
│   ├── budget/
│   │   ├── set.php                      # API Thiết lập hạn mức ngân sách tháng
│   │   ├── get.php                      # API Lấy ngân sách và tính toán % thực tế đã tiêu
│   │   └── delete.php                   # API Xóa thiết lập ngân sách
│   ├── report/
│   │   ├── summary.php                  # API Tổng hợp chỉ số tài chính và xu hướng 12 tháng
│   │   └── by_category.php              # API Thống kê cơ cấu theo danh mục cho biểu đồ tròn
│   ├── notification/
│   │   ├── list.php                     # API Lấy thông báo và đếm số lượng chưa đọc
│   │   └── mark_read.php                # API Đánh dấu đã đọc thông báo
│   ├── account/
│   │   ├── update.php                   # API Cập nhật thông tin hồ sơ người dùng
│   │   └── change_password.php          # API Đổi mật khẩu tài khoản
│   └── export/
│       └── export_csv.php               # API Xuất file CSV sao kê giao dịch (UTF-8 BOM)
├── pages/
│   ├── login.html                       # Màn hình Đăng nhập
│   ├── register.html                    # Màn hình Đăng ký tài khoản
│   ├── dashboard.html                   # Màn hình Bảng điều khiển chính (KPI + Biểu đồ)
│   ├── income.html                      # Màn hình Quản lý các nguồn thu nhập
│   ├── expense.html                     # Màn hình Quản lý các khoản chi tiêu
│   ├── budget.html                      # Màn hình Kế hoạch ngân sách & Cảnh báo hạn mức
│   ├── report.html                      # Màn hình Báo cáo phân tích chuyên sâu & Xuất CSV
│   └── account.html                     # Màn hình Cài đặt tài khoản & Quản lý danh mục riêng
├── assets/
│   ├── css/
│   │   ├── style.css                    # Design tokens, bố cục chung, form, table, responsive
│   │   └── components.css               # Modal popup, Toast thông báo, Progress bar, Dropdown
│   └── js/
│       ├── auth.js                      # Tiện ích chung, xử lý gọi API, format VNĐ, Toast, Session
│       ├── dashboard.js                 # Xử lý logic và vẽ biểu đồ trang Dashboard
│       ├── income.js                    # Xử lý logic nghiệp vụ trang Thu nhập
│       ├── expense.js                   # Xử lý logic nghiệp vụ trang Chi tiêu
│       ├── budget.js                    # Xử lý tính toán ngân sách và thanh tiến độ cảnh báo
│       ├── report.js                    # Xử lý báo cáo đa chiều và xuất file CSV
│       └── account.js                   # Xử lý thông tin cá nhân và quản lý danh mục riêng
├── database/
│   └── quanlychitieu.sql                # File kịch bản MySQL: Tạo CSDL, Bảng, Khóa ngoại & Dữ liệu mẫu
└── index.html                           # Trang cổng điều hướng ban đầu
```

---

## 📖 Bảng Chi Tiết Chức Năng Từng Thư Mục Và Từng File

### 1. Thư mục `config/` (Cấu hình hệ thống & Tiện ích dùng chung)
- **`config/db.php`**:
  - **Mục đích**: File trung tâm khởi tạo kết nối CSDL và các hàm dùng chung cho toàn bộ Backend.
  - **Chức năng chi tiết**:
    1. Khởi chạy `session_start()` an toàn và cấu hình múi giờ Việt Nam (`Asia/Ho_Chi_Minh`).
    2. Định nghĩa thông số kết nối MySQL (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
    3. Cung cấp hàm `getDbConnection()` tạo kết nối PDO với thuộc tính `ERRMODE_EXCEPTION` và `DEFAULT_FETCH_MODE = FETCH_ASSOC`.
    4. Cung cấp hàm chuẩn hóa JSON: `jsonResponse()`, `jsonSuccess()`, `jsonError()`.
    5. Cung cấp hàm `requireAuth()`: Xác thực đăng nhập từ Session, chặn truy cập trái phép với mã HTTP `401`.
    6. Cung cấp hàm `getJsonInput()`: Tự động giải mã dữ liệu gửi lên từ `php://input` hoặc `$_POST`.
    7. Cung cấp hàm `checkBudgetAlerts($userId, $pdo)`: Tự động so sánh số tiền đã chi với hạn mức ngân sách tháng để tạo thông báo cảnh báo nếu vượt ngưỡng quy định.

---

### 2. Thư mục `api/auth/` (Xác thực người dùng)
- **`api/auth/register.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `username`, `email`, `password`, `full_name`.
  - **Chức năng**: Kiểm tra tính hợp lệ dữ liệu, kiểm tra không trùng lặp username/email, mã hóa mật khẩu bằng `password_hash($password, PASSWORD_DEFAULT)` và lưu vào bảng `users`.
- **`api/auth/login.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `username` (hoặc email), `password`.
  - **Chức năng**: Tìm tài khoản theo username hoặc email, đối chiếu mật khẩu bằng `password_verify()`, lưu `user_id` vào `$_SESSION['user_id']`, trả về thông tin user (đã loại bỏ trường mật khẩu).
- **`api/auth/me.php`**:
  - **Phương thức**: `GET`
  - **Chức năng**: Trả về thông tin tài khoản đang đăng nhập; trả HTTP `401` khi không có phiên hợp lệ.
- **`api/auth/logout.php`**:
  - **Phương thức**: `POST` hoặc `GET`
  - **Chức năng**: Xóa dữ liệu phiên trong mảng `$_SESSION`, hủy cookie phiên làm việc trên trình duyệt và gọi `session_destroy()`.

---

### 3. Thư mục `api/category/` (Quản lý danh mục thu/chi)
- **`api/category/list.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `type` (tùy chọn: `income` hoặc `expense`).
  - **Chức năng**: Lấy danh sách danh mục thu/chi gồm: danh mục hệ thống mặc định (`user_id IS NULL`) kết hợp danh mục cá nhân do chính user tạo (`user_id = current_user`). Đếm kèm số lượng giao dịch đã dùng danh mục đó.
- **`api/category/add.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `name` (tên danh mục), `type` (`income` hoặc `expense`), `icon` (class FontAwesome), `color` (mã màu Hex).
  - **Chức năng**: Kiểm tra trùng lặp tên trong cùng loại và lưu danh mục mới gắn với `user_id` hiện tại vào bảng `categories`.
- **`api/category/edit.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `id`, `name`, `icon`, `color`.
  - **Chức năng**: Chỉnh sửa danh mục cá nhân. Kiểm tra chỉ cho phép sửa danh mục do chính user tạo (ngăn sửa danh mục mặc định của hệ thống).
- **`api/category/delete.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `id`.
  - **Chức năng**: Xóa danh mục cá nhân. Kiểm tra ràng buộc: nếu danh mục đã phát sinh giao dịch thì báo lỗi và yêu cầu xử lý các giao dịch liên quan trước khi xóa.

---

### 4. Thư mục `api/transaction/` (Quản lý giao dịch thu/chi)
- **`api/transaction/add.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `type` (`income` / `expense`), `category_id`, `amount`, `transaction_date`, `payment_method`, `note`.
  - **Chức năng**: Ghi nhận một giao dịch mới vào bảng `transactions`. Nếu là giao dịch chi tiêu (`expense`), tự động kích hoạt kiểm tra hạn mức ngân sách (`checkBudgetAlerts`).
- **`api/transaction/edit.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `id`, `type`, `category_id`, `amount`, `transaction_date`, `payment_method`, `note`.
  - **Chức năng**: Kiểm tra quyền sở hữu của `user_id` và cập nhật bản ghi trong bảng `transactions`.
- **`api/transaction/delete.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `id`.
  - **Chức năng**: Kiểm tra quyền sở hữu của `user_id` và xóa giao dịch khỏi CSDL.
- **`api/transaction/list.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `type`, `category_id`, `from_date`, `to_date`, `month`, `year`, `search`, `payment_method`, `page`, `limit`.
  - **Chức năng**: Lấy danh sách giao dịch có phân trang và bộ lọc nâng cao. Trả về mảng giao dịch kèm icon, màu sắc danh mục, thông tin phân trang (`total_pages`, `total_records`) và tổng thu, tổng chi, số dư trong kỳ lọc.

---

### 5. Thư mục `api/budget/` (Kế hoạch ngân sách)
- **`api/budget/set.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `category_id` (để `null` nếu là hạn mức tổng chi tiêu), `amount`, `month`, `year`, `alert_threshold` (ngưỡng % cảnh báo, mặc định 80%).
  - **Chức năng**: Thiết lập hoặc cập nhật hạn mức chi tiêu theo tháng và năm vào bảng `budgets`.
- **`api/budget/get.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `month`, `year`.
  - **Chức năng**: Lấy danh sách ngân sách tháng chỉ định. Tự động tính toán số tiền thực tế đã chi tiêu (`spent_amount`), số dư còn lại (`remaining_amount`), tỷ lệ % tiến độ (`percentage`) và gắn nhãn trạng thái (`normal`, `warning`, `exceeded`).
- **`api/budget/delete.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `id`.
  - **Chức năng**: Xóa bỏ một mục ngân sách đã thiết lập.

---

### 6. Thư mục `api/report/` (Báo cáo & Phân tích)
- **`api/report/summary.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `timeframe` (`this_month`, `last_month`, `this_year`, `custom`), `from_date`, `to_date`.
  - **Chức năng**: Tính toán tổng thu, tổng chi, số dư ròng, tỷ lệ tiết kiệm (%), so sánh % tăng trưởng với tháng trước và gom nhóm số liệu 12 tháng phục vụ vẽ biểu đồ xu hướng (Chart.js).
- **`api/report/by_category.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `type` (`expense` hoặc `income`), `timeframe`, `from_date`, `to_date`.
  - **Chức năng**: Gom nhóm số liệu theo danh mục, tính tổng tiền và tỷ trọng % phục vụ vẽ biểu đồ tròn (Doughnut / Pie Chart).

---

### 7. Thư mục `api/notification/` (Thông báo & Cảnh báo)
- **`api/notification/list.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `unread_only` (`1` hoặc `true` nếu chỉ lấy tin chưa đọc).
  - **Chức năng**: Lấy danh sách thông báo của người dùng và trả về số lượng thông báo chưa đọc (`unread_count`).
- **`api/notification/mark_read.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `id` (nếu đọc 1 tin) hoặc `all: true` (nếu đọc tất cả).
  - **Chức năng**: Cập nhật trạng thái `is_read = 1` trong bảng `notifications`.

---

### 8. Thư mục `api/account/` (Quản lý hồ sơ cá nhân)
- **`api/account/update.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `full_name`, `email`, `phone`, `currency` (VND / USD).
  - **Chức năng**: Kiểm tra tính hợp lệ và không trùng lặp email với tài khoản khác, cập nhật thông tin trong bảng `users`.
- **`api/account/change_password.php`**:
  - **Phương thức**: `POST`
  - **Tham số nhận vào**: `current_password`, `new_password`, `confirm_password`.
  - **Chức năng**: So khớp mật khẩu cũ bằng `password_verify()`, mã hóa mật khẩu mới bằng `password_hash()` và lưu vào bảng `users`.

---

### 9. Thư mục `api/export/` (Xuất file sao kê)
- **`api/export/export_csv.php`**:
  - **Phương thức**: `GET`
  - **Tham số Query**: `from_date`, `to_date`, `type`, `category_id`.
  - **Chức năng**: Thiết lập HTTP Header đính kèm tệp CSV, chèn mã UTF-8 BOM (`\xEF\xBB\xBF`) và xuất toàn bộ danh sách giao dịch để mở trực tiếp trên Microsoft Excel không bị lỗi font tiếng Việt.

---

### 10. Thư mục `pages/` (Giao diện HTML)
- **`pages/login.html`**: Giao diện đăng nhập người dùng (Username/Email, Password, Remember, nút Đăng nhập, liên kết Đăng ký).
- **`pages/register.html`**: Giao diện đăng ký tài khoản (Họ tên, Username, Email, Mật khẩu, Xác nhận mật khẩu).
- **`pages/dashboard.html`**: Bảng điều khiển chính tổng quan: 4 thẻ KPI (Tổng thu, Tổng chi, Số dư ròng, Tỷ lệ tiết kiệm), Biểu đồ cột xu hướng 12 tháng, Biểu đồ tròn cơ cấu chi tiêu tháng, Bảng giao dịch gần đây, Thẻ tiến độ ngân sách tháng và Modal thêm giao dịch nhanh.
- **`pages/income.html`**: Màn hình quản lý thu nhập: Thẻ tổng thu hôm nay/tháng/năm, thanh tìm kiếm & bộ lọc, bảng danh sách thu nhập có phân trang, biểu đồ cơ cấu nguồn thu, modal thêm và sửa khoản thu.
- **`pages/expense.html`**: Màn hình quản lý chi tiêu: Thẻ tổng chi hôm nay/tháng/trung bình ngày, thanh lọc theo danh mục & phương thức thanh toán, bảng chi tiêu có phân trang, biểu đồ chi tiêu, modal thêm và sửa khoản chi.
- **`pages/budget.html`**: Màn hình kế hoạch ngân sách: Bộ chọn tháng/năm, thẻ tiến độ tổng quan, lưới các thẻ ngân sách theo danh mục với thanh tiến độ đổi màu trực quan (xanh/vàng/đỏ) và modal đặt hạn mức.
- **`pages/report.html`**: Màn hình báo cáo tài chính chuyên sâu: Bộ lọc khoảng thời gian, biểu đồ xu hướng biến động dòng tiền, 2 biểu đồ phân bổ tròn, bảng phân tích % chi tiết và nút xuất file CSV.
- **`pages/account.html`**: Màn hình cài đặt tài khoản: Form đổi thông tin cá nhân, Form đổi mật khẩu, Bảng quản lý danh mục riêng và modal tạo danh mục mới với bảng chọn icon và màu sắc.

---

### 11. Thư mục `assets/css/` (Định kiểu giao diện)
- **`assets/css/style.css`**: Thiết lập hệ thống biến màu sắc CSS (Design Tokens), Typography chuẩn, Reset CSS, bố cục khung App Layout (Sidebar cố định, Header dính, Main Content), định dạng Card, ô nhập Form, Bảng dữ liệu và Responsive trên điện thoại / máy tính bảng.
- **`assets/css/components.css`**: Định dạng các thành phần tương tác: Hộp thoại Modal Popup (overlay mờ nền), Toast thông báo nổi góc phải, Thanh tiến độ ngân sách (Progress bar), Dropdown chuông thông báo, Dropdown tài khoản, Badge nhãn loại giao dịch.

---

### 12. Thư mục `assets/js/` (Xử lý tương tác Frontend)
- **`assets/js/auth.js`**: Module cốt lõi: Cung cấp hàm `apiFetch()` tự động bắt mã 401 điều hướng login, hàm định dạng tiền tệ VNĐ (`15.000.000 đ`), format ngày tháng, hiển thị Toast, kiểm tra phiên đăng nhập `checkAuth()`, xử lý đăng nhập, đăng ký và đăng xuất.
- **`assets/js/dashboard.js`**: Quản lý Dashboard: Nạp 4 chỉ số KPI, khởi tạo 2 biểu đồ Chart.js (cột 12 tháng & tròn chi tiêu), nạp bảng giao dịch gần nhất và gửi dữ liệu modal thêm giao dịch nhanh.
- **`assets/js/income.js`**: Quản lý trang thu: Nạp danh mục thu, gọi API danh sách thu kèm bộ lọc và phân trang, vẽ biểu đồ nguồn thu, xử lý thêm/sửa/xóa khoản thu.
- **`assets/js/expense.js`**: Quản lý trang chi: Nạp danh mục chi, gọi API danh sách chi kèm bộ lọc và phân trang, vẽ biểu đồ chi tiêu, xử lý thêm/sửa/xóa khoản chi.
- **`assets/js/budget.js`**: Quản lý ngân sách: Lắng nghe thay đổi tháng/năm, tính toán % tiến độ ngân sách, đổi màu thanh cảnh báo, xử lý thêm/sửa/xóa hạn mức.
- **`assets/js/report.js`**: Quản lý báo cáo: Lắng nghe bộ lọc thời gian, gọi API summary & by_category, vẽ biểu đồ Chart.js và kích hoạt tải file CSV.
- **`assets/js/account.js`**: Quản lý tài khoản: Nạp dữ liệu profile vào form, xử lý submit cập nhật hồ sơ, submit đổi mật khẩu, nạp bảng và thêm/xóa danh mục cá nhân.

---

### 13. File `database/quanlychitieu.sql` & `index.html`
- **`database/quanlychitieu.sql`**: Kịch bản tạo CSDL MySQL gồm 5 bảng chính (`users`, `categories`, `transactions`, `budgets`, `notifications`), các khóa ngoại, ràng buộc toàn vẹn và các danh mục mặc định.
- **`index.html`**: Trang đích cửa ngõ: Kiểm tra phiên đăng nhập của người dùng qua API `api/auth/me.php` để tự động điều hướng vào `pages/dashboard.html` (nếu đã đăng nhập) hoặc `pages/login.html` (nếu chưa đăng nhập).

---

## 🗄️ Mô Hình Cơ Sở Dữ Liệu (Database Schema)

| Tên bảng | Mục đích lưu trữ | Các trường chính |
| :--- | :--- | :--- |
| **`users`** | Lưu tài khoản người dùng | `id`, `username`, `email`, `password` (hash bcrypt), `full_name`, `phone`, `currency`, `created_at` |
| **`categories`** | Lưu danh mục thu chi | `id`, `user_id` (NULL là mặc định hệ thống), `name`, `type` (income/expense), `icon`, `color`, `created_at` |
| **`transactions`** | Lưu lịch sử giao dịch | `id`, `user_id`, `category_id`, `type`, `amount`, `transaction_date`, `payment_method`, `note`, `created_at` |
| **`budgets`** | Lưu hạn mức ngân sách | `id`, `user_id`, `category_id` (NULL là tổng ngân sách), `amount`, `month`, `year`, `alert_threshold`, `created_at` |
| **`notifications`** | Lưu cảnh báo & thông báo | `id`, `user_id`, `title`, `message`, `type` (budget_alert/system), `is_read`, `created_at` |
