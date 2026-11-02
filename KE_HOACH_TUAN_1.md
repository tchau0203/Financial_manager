# 📋 Kế hoạch Triển khai Sprint 1 Tuần — Hệ thống Quản Lý Chi Tiêu
- **Ngày lập:** 02/11/2026  
- **Dựa trên:** Bảng phân công nhiệm vụ thành viên dự án  
- **Nhánh nguồn:** `main`  
- **Nhánh tích hợp đích:** `dev` (hoặc `main`)  

---

## 🗂️ Tổng quan
Sprint 1 (Tuần đầu tiên) tập trung hoàn thiện toàn bộ Cơ sở dữ liệu, bộ API cốt lõi (PHP thuần + PDO MySQL), giao diện người dùng (HTML, CSS, JS thuần) và tích hợp các luồng chức năng hoàn chỉnh theo bảng phân công công việc. Team được chia thành 3 nhóm phối hợp song song Backend & Frontend:

| Nhóm | Thành viên | Nhánh Git | Tính năng phụ trách |
| :--- | :--- | :--- | :--- |
| **🔐 Nhóm 1: Auth, CSDL & Dashboard** | **Châu** (SQL, BE) + **Ngô Bảo** (FE) | `feature/auth-dashboard` | CSDL MySQL, PDO Config, Đăng ký, Đăng nhập, Đăng xuất, Đổi MK, Layout, Dashboard |
| **💳 Nhóm 2: Giao dịch & Danh mục** | **NG Bảo** (BE) + **QAnh** (FE) | `feature/transaction-category` | CRUD Danh mục thu/chi, CRUD Thu nhập & Chi tiêu, Lịch sử giao dịch & Bộ lọc |
| **📊 Nhóm 3: Ngân sách & Báo cáo** | **Huy** (BE) + **Huy** (FE) | `feature/budget-report` | Quản lý hạn mức Ngân sách, Báo cáo Chart.js, Cảnh báo ngân sách, Xuất file CSV/Excel |

---

## 🔐 Nhóm 1: Châu (SQL, BE) & Ngô Bảo (FE)
- **Nhánh:** `feature/auth-dashboard`  
- **Lấy từ:** `main`  

---

### Tính năng 1.1 — Thiết kế CSDL & Cấu hình kết nối PDO (Database & Config)
#### ✅ Backend — Châu
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `database/quanlychitieu.sql`, `config/db.php` |
| **Cấu hình CSDL** | MySQL 8.x / MariaDB, bảng mã `utf8mb4_unicode_ci` |
| **Kết nối** | PDO MySQL (`ERRMODE_EXCEPTION`, `DEFAULT_FETCH_MODE = FETCH_ASSOC`) |

**Các bước cài đặt:**
1. Tạo kịch bản CSDL trong `database/quanlychitieu.sql`:
   - Tạo 5 bảng: `users`, `categories`, `transactions`, `budgets`, `notifications`.
   - Thiết lập ràng buộc khóa chính (PK) tự tăng, khóa ngoại (FK) nối bảng `users` và `categories`.
   - Chèn dữ liệu danh mục chuẩn và tài khoản mẫu (`demo` / `demo123`).
2. Viết file `config/db.php`:
   - Khởi tạo session: `session_start()`, cấu hình timezone `Asia/Ho_Chi_Minh`.
   - Hàm `getDbConnection()`: Khởi tạo kết nối PDO an toàn.
   - Hàm `jsonResponse($payload, $statusCode)`: Xuất JSON header UTF-8.
   - Hàm `requireAuth()`: Kiểm tra `$_SESSION['user_id']`, nếu chưa đăng nhập trả về HTTP 401.
   - Hàm `getJsonInput()`: Đọc dữ liệu từ body `php://input` hoặc `$_POST`.

---

### Tính năng 1.2 — Đăng ký, Đăng nhập & Đăng xuất (Authentication)
#### ✅ Backend — Châu
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `api/auth/register.php`, `api/auth/login.php`, `api/auth/logout.php`, `api/auth/me.php` |
| **API Đăng ký** | `POST /api/auth/register.php` (Body: `username`, `email`, `password`, `full_name`) |
| **API Đăng nhập** | `POST /api/auth/login.php` (Body: `username`, `password`) |
| **API Đăng xuất** | `POST /api/auth/logout.php` |
| **API Check Session**| `GET /api/auth/me.php` |
| **Bảo mật** | `password_hash()`, `password_verify()`, Prepared Statements chống SQL Injection |

**Các bước cài đặt:**
1. Trong `register.php`: Validate đầu vào (độ dài username, format email, password $\ge$ 6 ký tự). Kiểm tra trùng lặp email/username. Mã hóa mật khẩu bằng `PASSWORD_BCRYPT`. Thêm user vào bảng `users`. Khởi tạo `$_SESSION['user_id']`.
2. Trong `login.php`: Truy vấn user theo username/email bằng Prepared Statement. Đối chiếu `password_verify()`. Gán `$_SESSION['user_id'] = $user['id']`. Trả về JSON dữ liệu user (bỏ trường password).
3. Trong `logout.php`: Hủy session bằng `session_destroy()`, xóa cookie phiên.
4. Trong `me.php`: Gọi `requireAuth()`, trả về thông tin người dùng đang đăng nhập và đếm số thông báo chưa đọc.

#### ✅ Frontend — Ngô Bảo
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `pages/login.html`, `pages/register.html`, `assets/js/auth.js` |
| **Vị trí UI** | Giao diện đăng nhập và đăng ký tài khoản |

**Các bước cài đặt:**
1. Dựng giao diện `pages/login.html` & `pages/register.html` với form chuẩn, thông báo lỗi đẹp mắt.
2. Viết hàm `apiFetch(endpoint, options)` trong `assets/js/auth.js`: Tự động bắt lỗi 401 điều hướng về login.
3. Viết hàm `showToast(title, message, type)`: Hiển thị thông báo Toast góc màn hình.
4. Viết hàm `checkAuth()`: Tự động chạy khi vào trang nội bộ, nạp tên và avatar người dùng lên Header/Sidebar.

---

### Tính năng 1.3 — Layout chung & Bảng điều khiển (Dashboard & Shell Layout)
#### ✅ Backend — Châu
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `api/account/update.php`, `api/account/change_password.php` |
| **API Cập nhật profile** | `POST /api/account/update.php` (Body: `full_name`, `email`, `phone`, `currency`) |
| **API Đổi mật khẩu** | `POST /api/account/change_password.php` (Body: `current_password`, `new_password`) |

**Các bước cài đặt:**
1. Trong `update.php`: Kiểm tra email mới không trùng với user khác. Cập nhật bảng `users`.
2. Trong `change_password.php`: Xác thực mật khẩu cũ bằng `password_verify()`, băm mật khẩu mới và update DB.

#### ✅ Frontend — Ngô Bảo
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `assets/css/style.css`, `assets/css/components.css`, `pages/dashboard.html`, `assets/js/dashboard.js`, `index.html` |
| **Vị trí UI** | Layout tổng thể (Sidebar, Header, Responsive) và Bảng điều khiển Dashboard |

**Các bước cài đặt:**
1. Viết `assets/css/style.css`: Hệ màu sắc `:root`, layout flexbox/grid, Sidebar cố định, Header dính, Responsive di động.
2. Viết `assets/css/components.css`: Định dạng Modal dialog, Toast thông báo, Progress bar, Dropdown Menu.
3. Dựng `pages/dashboard.html`: 4 Thẻ KPI (Tổng thu, Tổng chi, Số dư, Tiết kiệm), khung biểu đồ 12 tháng, bảng 5 giao dịch gần đây, widget tiến độ ngân sách tháng.
4. Viết `assets/js/dashboard.js`: Gọi API nạp số liệu KPI, render bảng giao dịch gần đây, mở modal thêm giao dịch nhanh.
5. Tạo `index.html`: Cửa ngõ gọi `api/auth/me.php` tự động chuyển hướng dashboard hoặc login.

---

## 💳 Nhóm 2: NG Bảo (BE) & QAnh (FE)
- **Nhánh:** `feature/transaction-category`  
- **Lấy từ:** `main`  

---

### Tính năng 2.1 — Quản lý Danh mục Thu nhập & Chi tiêu (Categories)
#### ✅ Backend — NG Bảo
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `api/category/list.php`, `api/category/add.php`, `api/category/edit.php`, `api/category/delete.php` |
| **API Lấy danh mục** | `GET /api/category/list.php?type=income|expense` |
| **API Thêm danh mục** | `POST /api/category/add.php` (Body: `name`, `type`, `icon`, `color`) |
| **API Sửa danh mục** | `POST /api/category/edit.php` (Body: `id`, `name`, `icon`, `color`) |
| **API Xóa danh mục** | `POST /api/category/delete.php` (Body: `id`) |

**Các bước cài đặt:**
1. Trong `list.php`: Lấy danh mục hệ thống (`user_id IS NULL`) kết hợp danh mục cá nhân (`user_id = me`). Hỗ trợ lọc theo `type`.
2. Trong `add.php`: Validate tên danh mục, kiểm tra trùng lặp trong cùng loại, insert CSDL với `user_id`.
3. Trong `edit.php`: Ngăn chặn sửa danh mục hệ thống, chỉ cho sửa danh mục do chính user tạo.
4. Trong `delete.php`: Kiểm tra nếu danh mục đang được giao dịch sử dụng thì báo lỗi và không cho xóa.

#### ✅ Frontend — QAnh
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `assets/js/income.js`, `assets/js/expense.js` (Hàm nạp danh mục) |
| **Vị trí UI** | Dropdown chọn danh mục trong bộ lọc và modal thêm/sửa giao dịch |

**Các bước cài đặt:**
1. Viết hàm `loadCategories(type)` gọi `GET /api/category/list.php`.
2. Đổ dữ liệu vào các thẻ `<select>` danh mục với icon FontAwesome và màu sắc tương ứng.

---

### Tính năng 2.2 — Quản lý Thu nhập (Income Management)
#### ✅ Backend — NG Bảo
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | Tích hợp trong `api/transaction/add.php`, `edit.php`, `delete.php`, `list.php` với `type = 'income'` |
| **API Thêm thu nhập** | `POST /api/transaction/add.php` (Body: `type=income`, `category_id`, `amount`, `transaction_date`, `payment_method`, `note`) |

**Các bước cài đặt:**
1. Validate `amount > 0`, kiểm tra `category_id` tồn tại và đúng loại `income`.
2. Thêm bản ghi vào bảng `transactions` bằng Prepared Statement.

#### ✅ Frontend — QAnh
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `pages/income.html`, `assets/js/income.js` |
| **Vị trí UI** | Trang Quản lý Thu nhập (`pages/income.html`) |

**Các bước cài đặt:**
1. Dựng `pages/income.html`: 3 Thẻ chỉ số (Thu hôm nay, Thu tháng này, Thu năm nay), thanh tìm kiếm & bộ lọc, bảng danh sách giao dịch thu có màu xanh lá (`+`).
2. Viết `assets/js/income.js`:
   - Gọi API lấy danh sách thu nhập có phân trang và lọc theo ngày, danh mục.
   - Modal Thêm thu nhập mới ➔ gọi `POST /api/transaction/add.php`.
   - Modal Sửa thu nhập ➔ gọi `POST /api/transaction/edit.php`.
   - Nút Xóa thu nhập ➔ xác nhận và gọi `POST /api/transaction/delete.php`.

---

### Tính năng 2.3 — Quản lý Chi tiêu & Lịch sử Giao dịch (Expense Management)
#### ✅ Backend — NG Bảo
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `api/transaction/add.php`, `edit.php`, `delete.php`, `list.php` |
| **API Danh sách & Lọc** | `GET /api/transaction/list.php?type=expense&category_id=...&from_date=...&to_date=...&search=...&page=1&limit=10` |

**Các bước cài đặt:**
1. Xây dựng câu lệnh truy vấn động nối chuỗi `WHERE` an toàn:
   - Lọc theo `user_id`, `type`, `category_id`, khoảng ngày `from_date` đến `to_date`, từ khóa `search`.
   - Đếm `COUNT` và tính `SUM(amount)` để trả về tổng tiền.
   - Thêm `LIMIT ? OFFSET ?` để phân trang.
2. Trả về JSON gồm danh sách giao dịch, metadata phân trang (`current_page`, `total_pages`, `total_records`) và tổng chi tiêu.

#### ✅ Frontend — QAnh
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `pages/expense.html`, `assets/js/expense.js` |
| **Vị trí UI** | Trang Quản lý Chi tiêu (`pages/expense.html`) |

**Các bước cài đặt:**
1. Dựng `pages/expense.html`: 3 Thẻ chỉ số (Chi hôm nay, Chi tháng này, Bình quân/ngày), bộ lọc danh mục và phương thức thanh toán (Tiền mặt, Banking, Ví điện tử, Thẻ tín dụng).
2. Viết `assets/js/expense.js`:
   - Bảng hiển thị số tiền có màu đỏ (`-`), định dạng tiền tệ VNĐ (`formatCurrency`).
   - Xử lý bộ lọc tìm kiếm và phân trang mượt mà.
   - Modal thêm chi tiêu mới, sửa chi tiêu, xóa chi tiêu.

---

## 📊 Nhóm 3: Huy (BE) & Huy (FE)
- **Nhánh:** `feature/budget-report`  
- **Lấy từ:** `main`  

---

### Tính năng 3.1 — Quản lý Ngân sách & Cảnh báo hạn mức (Budget & Alerts)
#### ✅ Backend — Huy (BE)
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `api/budget/set.php`, `api/budget/get.php`, `api/budget/delete.php`, `api/notification/list.php`, `api/notification/mark_read.php` |
| **API Lấy ngân sách** | `GET /api/budget/get.php?month=11&year=2026` |
| **API Đặt ngân sách** | `POST /api/budget/set.php` (Body: `category_id`, `amount`, `month`, `year`, `alert_threshold`) |
| **API Thông báo** | `GET /api/notification/list.php`, `POST /api/notification/mark_read.php` |

**Các bước cài đặt:**
1. Trong `set.php`: Kiểm tra ngân sách đã có chưa (nếu có thì UPDATE, chưa có thì INSERT).
2. Trong `get.php`: Lấy các ngân sách trong tháng. Query từ bảng `transactions` để tính `spent_amount` (số tiền thực tế đã chi). Tính `remaining_amount = amount - spent_amount`, tính `percentage = (spent / amount) * 100`. Phân loại trạng thái:
   - `normal`: Dưới ngưỡng cảnh báo ($\le 80\%$)
   - `warning`: Chạm ngưỡng cảnh báo ($80\% - 99\%$)
   - `exceeded`: Vượt hạn mức ($\ge 100\%$)
3. Viết hàm kiểm tra ngân sách `checkBudgetAlerts()`: Tự động chèn bản ghi vào bảng `notifications` khi chi tiêu vượt hạn mức.
4. Trong `notification/list.php` & `mark_read.php`: Lấy danh sách thông báo và cập nhật `is_read = 1`.

#### ✅ Frontend — Huy (FE)
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `pages/budget.html`, `assets/js/budget.js` |
| **Vị trí UI** | Trang Kế hoạch Ngân sách (`pages/budget.html`) |

**Các bước cài đặt:**
1. Dựng bộ chọn Tháng (1 - 12) và Năm (hiện tại $\pm$ 1).
2. Thẻ tổng quan tiến độ ngân sách cả tháng kèm thanh Progress Bar.
3. Lưới các thẻ ngân sách theo từng danh mục: Thanh tiến độ đổi màu trực quan (Xanh lá $\rightarrow$ Vàng cam $\rightarrow$ Đỏ rực).
4. Modal thiết lập/sửa ngân sách và nút xóa ngân sách.

---

### Tính năng 3.2 — Báo cáo Thống kê & Xuất dữ liệu CSV (Reports & Export)
#### ✅ Backend — Huy (BE)
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `api/report/summary.php`, `api/report/by_category.php`, `api/export/export_csv.php` |
| **API Thống kê tổng quan** | `GET /api/report/summary.php?timeframe=this_month|this_year|custom` |
| **API Thống kê danh mục** | `GET /api/report/by_category.php?type=expense|income` |
| **API Xuất file CSV** | `GET /api/export/export_csv.php?from_date=...&to_date=...` |

**Các bước cài đặt:**
1. Trong `summary.php`: Tính tổng thu, tổng chi, số dư ròng, tỷ lệ tiết kiệm. Query tính tăng trưởng so với tháng trước. Gom nhóm 12 tháng (`GROUP BY MONTH`) cho biểu đồ xu hướng.
2. Trong `by_category.php`: `GROUP BY category_id`, tính tổng tiền và tỷ trọng % cho biểu đồ tròn.
3. Trong `export_csv.php`: Ghi header tải tệp, xuất mã UTF-8 BOM (`\xEF\xBB\xBF`) để Excel hiển thị đúng tiếng Việt có dấu, dùng `fputcsv()` xuất các dòng giao dịch.

#### ✅ Frontend — Huy (FE)
| Hạng mục | Chi tiết |
| :--- | :--- |
| **File** | `pages/report.html`, `pages/account.html`, `assets/js/report.js`, `assets/js/account.js` |
| **Vị trí UI** | Trang Báo cáo (`pages/report.html`) và Trang Cài đặt tài khoản (`pages/account.html`) |

**Các bước cài đặt:**
1. Dựng `pages/report.html`: Bộ chọn kỳ thời gian (Tháng này, Tháng trước, Năm nay, Tùy chỉnh ngày).
2. Viết `assets/js/report.js`: Tích hợp Chart.js vẽ 3 biểu đồ:
   - Biểu đồ Bar: Xu hướng Thu chi theo thời gian.
   - Biểu đồ Doughnut: Cơ cấu chi tiêu theo danh mục.
   - Biểu đồ Pie: Phân bổ các nguồn thu nhập.
3. Gắn sự kiện nút "Xuất báo cáo CSV" điều hướng tải tệp từ API.
4. Dựng `pages/account.html` & `assets/js/account.js`: Form sửa thông tin cá nhân, form đổi mật khẩu, bảng quản lý danh mục riêng của người dùng.

---

## 🌿 Quy trình Git — Phân nhánh & Đẩy code

### 1. Sơ đồ nhánh
```text
main (nguồn gốc chuẩn)
 ├── feature/auth-dashboard         (Châu + Ngô Bảo)
 ├── feature/transaction-category   (NG Bảo + QAnh)
 └── feature/budget-report          (Huy BE + Huy FE)
```

### 2. Các lệnh thao tác hàng ngày cho từng thành viên
```bash
# 1. Cập nhật nhánh main mới nhất từ remote
git checkout main
git pull origin main

# 2. Tạo hoặc chuyển sang nhánh tính năng của nhóm mình
git checkout -b feature/<ten-nhanh>
# (Nếu nhánh đã tạo từ trước: git checkout feature/<ten-nhanh>)

# 3. Code, thêm file và commit thường xuyên
git add .
git commit -m "feat(<module>): <mô tả ngắn gọn>"
# Ví dụ: git commit -m "feat(auth): complete register and login api"

# 4. Đẩy nhánh lên GitHub
git push origin feature/<ten-nhanh>

# 5. Tạo Pull Request (PR) lên main để cả nhóm review và merge
```

### 3. Quy ước đặt tên commit
| Tiền tố | Ý nghĩa | Ví dụ |
| :--- | :--- | :--- |
| `feat(auth):` | Thêm tính năng xác thực | `feat(auth): add password hash with bcrypt` |
| `feat(trans):` | Thêm tính năng giao dịch | `feat(trans): add transaction filter and pagination` |
| `feat(budget):` | Thêm tính năng ngân sách | `feat(budget): add budget alert notification trigger` |
| `feat(ui):` | Làm giao diện Frontend | `feat(ui): integrate chart.js on report page` |
| `fix(<module>):` | Sửa lỗi | `fix(export): add utf-8 bom for excel vietnamese font` |
| `refactor:` | Tối ưu hóa code | `refactor: clean up pdo helper functions in db.php` |

---

## 🧪 Kế hoạch Kiểm thử & Nghiệm thu (Verification Plan)

| STT | Tính năng kiểm thử | Các bước kiểm thử thực tế | Kết quả mong đợi | Phụ trách test |
|:---:|:---|:---|:---|:---:|
| 1 | **Đăng ký & Đăng nhập** | Đăng ký user `test1` ➔ Đăng xuất ➔ Đăng nhập lại ➔ Thử truy cập trực tiếp `pages/dashboard.html` khi chưa đăng nhập. | Đăng ký thành công, tự động chuyển dashboard; chặn truy cập trái phép khi chưa đăng nhập. | Châu & Ngô Bảo |
| 2 | **Giao dịch Thu - Chi** | Thêm 2 khoản thu (Lương, Thưởng), thêm 3 khoản chi (Ăn uống, Xăng xe) ➔ Kiểm tra hiển thị màu Xanh/Đỏ. | Dữ liệu lưu đúng vào CSDL, bảng hiển thị đúng số tiền format VNĐ (`đ`). | NG Bảo & QAnh |
| 3 | **Bộ lọc & Phân trang** | Lọc theo ngày từ `2026-11-01` đến `2026-11-02` ➔ Tìm kiếm từ khóa "xăng" ➔ Chuyển trang 2. | Bảng chỉ hiển thị giao dịch thỏa mãn điều kiện lọc; phân trang nhảy đúng dữ liệu. | NG Bảo & QAnh |
| 4 | **Cảnh báo Ngân sách** | Đặt ngân sách Ăn uống 1.000.000 đ ➔ Thêm khoản ăn uống 850.000 đ (85%) ➔ Thêm tiếp 200.000 đ (105%). | Chuông hiện thông báo cảnh báo màu vàng khi đạt 85%, hiện cảnh báo đỏ khi vượt 100%. | Huy BE & Huy FE |
| 5 | **Biểu đồ & Báo cáo** | Vào `pages/report.html`, chuyển các mốc thời gian: "Tháng này", "Năm nay". | 3 Biểu đồ Chart.js cập nhật lại theo thời gian thực mượt mà, tooltip hiển thị số tiền chuẩn. | Huy BE & Huy FE |
| 6 | **Xuất file CSV/Excel** | Nhấp nút "Xuất báo cáo CSV" trên trang Báo cáo ➔ Mở file bằng Excel trên Windows. | File tải về ngay lập tức, cột chữ tiếng Việt (Ăn uống, Tiền lương...) không bị lỗi font nhờ UTF-8 BOM. | Huy BE & QAnh |
| 7 | **Giao diện Responsive** | Nhấn `F12` trên trình duyệt, thu nhỏ màn hình về kích thước điện thoại (375px). | Menu Sidebar tự ẩn, nhấn nút hamburger mở drawer mượt mà, bảng cuộn ngang không bị vỡ layout. | Ngô Bảo & Cả nhóm |

---

## 📅 Timeline Lịch trình 7 Ngày của Tuần 1

| Ngày | Thứ | Trọng tâm công việc | Đầu ra bắt buộc (Deliverables) |
| :---: | :---: | :--- | :--- |
| **03/11** | **Thứ 2** | Setup CSDL, Config PDO, Bộ CSS Design Tokens & Khung Layout | `database/quanlychitieu.sql` import chạy tốt, `config/db.php`, `style.css`, `components.css`. |
| **04/11** | **Thứ 3** | Hoàn thành Module Auth (BE + FE) & Khung trang tĩnh | `register.php`, `login.php`, `logout.php`, `login.html`, `register.html`, `auth.js`. |
| **05/11** | **Thứ 4** | CRUD Danh mục & Thêm/Sửa/Xóa Thu - Chi | `api/category/*.php`, `api/transaction/add.php`, `pages/income.html`, `pages/expense.html`. |
| **06/11** | **Thứ 5** | Bộ lọc tìm kiếm, Phân trang giao dịch & Dựng giao diện Ngân sách | `api/transaction/list.php`, hoàn thiện `income.js`, `expense.js`, khung `budget.html`. |
| **07/11** | **Thứ 6** | API Ngân sách, Báo cáo Chart.js, Cài đặt tài khoản & Xuất CSV | `api/budget/*.php`, `api/report/*.php`, `export_csv.php`, `report.html`, `account.html`. |
| **08/11** | **Thứ 7** | Cảnh báo ngân sách tự động, Tích hợp chuông thông báo & Ghép nối API | `checkBudgetAlerts()`, `api/notification/*.php`, tích hợp toàn bộ API với Frontend. |
| **09/11** | **Chủ nhật** | Kiểm thử toàn diện (End-to-End), Fix bug, Merge Git & Nghiệm thu | 23 file chạy trơn tru, không có lỗi console hay PHP, demo hoàn chỉnh luồng nghiệp vụ. |

> **⚠️ Lưu ý cho cả team:**  
> - Tất cả các thay đổi phải thực hiện trên nhánh tính năng riêng (`feature/...`).  
> - Trước khi tạo Pull Request merge vào `main`, phải tự kiểm tra mã của mình chạy không có lỗi và thông báo cho các thành viên liên quan trên nhóm chat.
