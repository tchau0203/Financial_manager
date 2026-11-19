# 📋 Kế Hoạch Triển Khai Sprint 2 (Tuần 2) — Hệ Thống Quản Lý Tài Chính Cá Nhân

- **Tên dự án:** CashManager — Quản Lý Chi Tiêu Cá Nhân & Nhóm  
- **Thời gian thực hiện:** Sprint Tuần 2  
- **Nhánh tích hợp chính:** `main` (hoặc `feature/test-code`)  
- **Mục tiêu Sprint 2:** Triển khai trọn vẹn 7 chức năng mở rộng nâng cao theo đặc tả yêu cầu:  
  1. **Nhập dữ liệu Excel/CSV (Import Transactions)**  
  2. **Xuất báo cáo tài chính PDF**  
  3. **Đa tiền tệ (VND, USD, EUR, JPY...)**  
  4. **Thông báo qua Email khi gần hết ngân sách**  
  5. **Tìm kiếm & Bộ lọc nâng cao**  
  6. **Sao lưu & Khôi phục dữ liệu (Backup & Restore)**  
  7. **Chia sẻ tài chính (Quản lý ngân sách chung nhiều người)**  

---

## 🗂️ 1. PHÂN CÔNG NHIỆM VỤ CHO 6 THÀNH VIÊN TRONG NHÓM

| Phân hệ / Nhóm | Thành viên | Nhánh Git | Chức năng phụ trách chính |
| :--- | :--- | :--- | :--- |
| **📁 Nhóm 1: Xử lý Tệp & Báo cáo** | **NG Bảo** (BE) + **Quốc Anh** (FE) | `feature/import-pdf` | **1. Nhập dữ liệu Excel/CSV:** Đọc file upload, kiểm tra dữ liệu và import hàng loạt.<br>**2. Xuất báo cáo PDF:** Định dạng trang in PDF chuyên nghiệp theo tháng kèm biểu đồ. |
| **🔍 Nhóm 2: Tìm kiếm & Tiền tệ** | **Hoàng Nam** (BE) + **Ngô Bảo** (FE) | `feature/search-currency` | **3. Đa tiền tệ:** Cấu hình tỷ giá, hiển thị ký hiệu (đ, $, €, ¥), quy đổi tự động.<br>**4. Tìm kiếm nâng cao:** Bộ lọc đa tiêu chí (khoảng tiền, khoảng ngày, danh mục, từ khóa). |
| **👥 Nhóm 3: Chia sẻ nhóm & Bảo mật** | **Thái Châu** (Lead/BE) + **Gia Huy** (FE) | `feature/shared-email-backup` | **5. Chia sẻ tài chính:** Quản lý ngân sách chung, phân quyền thành viên trong quỹ.<br>**6. Thông báo Email:** Tích hợp gửi email cảnh báo khi chạm ngưỡng ngân sách.<br>**7. Sao lưu & Khôi phục:** Xuất file backup (.json) và restore dữ liệu. |

---

## 🚀 2. THIẾT KẾ CHI TIẾT 7 CHỨC NĂNG NÂNG CAO

---

### Chức năng 1: Nhập dữ liệu từ Excel / CSV (Import Transactions)
* **Mô tả:** Cho phép người dùng tải lên file Excel (`.xlsx`, `.csv`) chứa danh sách giao dịch từ ngân hàng hoặc ứng dụng khác để nạp hàng loạt vào hệ thống mà không cần nhập tay từng dòng.
* **Backend (NG Bảo):**
  * File: `api/import/import_csv.php`.
  * Nhận file từ `$_FILES['file']`, kiểm tra định dạng và kích thước.
  * Phân tích các cột: Ngày giao dịch, Loại (Thu/Chi), Tên danh mục, Số tiền, Ghi chú.
  * Tự động ánh xạ (map) tên danh mục với `category_id` trong database; thực hiện `INSERT` hàng loạt bằng transaction PDO.
* **Frontend (Quốc Anh):**
  * Giao diện: Thêm nút **"Nhập từ Excel/CSV"** và Modal tải tệp trên trang `income.html` và `expense.html`.
  * Hỗ trợ Drag & Drop (kéo thả file), thanh tiến độ tải lên và bảng xem trước (preview) dữ liệu trước khi xác nhận lưu.
  * Cung cấp nút tải về **File mẫu CSV chuẩn** để người dùng điền theo đúng cột.

---

### Chức năng 2: Xuất báo cáo tài chính dạng PDF (Export PDF)
* **Mô tả:** Tạo file báo cáo tài chính tháng hoàn chỉnh dạng PDF có tiêu đề, bảng tổng kết thu/chi, số dư ròng và hình ảnh biểu đồ phân bổ.
* **Backend (NG Bảo):**
  * File: `api/export/export_pdf.php`.
  * Tổng hợp toàn bộ số liệu thu chi của tháng được chọn.
  * Tạo mẫu in HTML chuẩn A4 chuyên dụng (`@page { size: A4; margin: 15mm; }`) để tải về hoặc in trực tiếp.
* **Frontend (Quốc Anh):**
  * Nút **"Xuất báo cáo PDF"** trên trang `pages/report.html`.
  * Tự động chụp lại biểu đồ Canvas Chart.js gắn vào bản in và kích hoạt lệnh in/xuất PDF chuẩn đẹp, không bị dính thanh menu Sidebar.

---

### Chức năng 3: Hỗ trợ Đa tiền tệ (Multi-Currency)
* **Mô tả:** Người dùng có thể thiết lập đơn vị tiền tệ chính (VND, USD, EUR, JPY, GBP). Hệ thống tự động format ký hiệu tiền tệ và hỗ trợ tỷ giá quy đổi chuẩn.
* **Database & Backend (Hoàng Nam):**
  * Cập nhật trường `currency` trong bảng `users` (VND, USD, EUR, JPY).
  * Viết bảng tỷ giá quy đổi tham chiếu: `1 USD = 25,400 VND`, `1 EUR = 27,200 VND`...
  * File: `api/account/currency.php`: Cập nhật tiền tệ mặc định của tài khoản và lấy tỷ giá hiện hành.
* **Frontend (Ngô Bảo):**
  * Tùy chọn đổi đơn vị tiền tệ trong `pages/account.html`.
  * Nâng cấp hàm `formatCurrency(amount, currency)` trong `assets/js/auth.js` để tự động hiển thị:
    * VND: `15.000.000 đ`
    * USD: `$1,500.00`
    * EUR: `€1,200.00`
    * JPY: `¥150,000`

---

### Chức năng 4: Thông báo qua Email khi gần hết ngân sách (Email Alerts)
* **Mô tả:** Khi một khoản chi tiêu mới khiến ngân sách đạt $\ge 80\%$ hạn mức hoặc bị vượt quá $100\%$, hệ thống tự động gửi email cảnh báo đến hộp thư người dùng.
* **Backend (Thái Châu):**
  * Tích hợp thư viện gửi mail chuẩn qua SMTP (dùng Gmail SMTP hoặc hàm `mail()`).
  * Nâng cấp hàm `checkBudgetAlerts()` trong `config/db.php`:
    * Khi phát hiện trạng thái `warning` hoặc `exceeded`: Soạn nội dung email HTML có thiết kế đẹp mắt (Tên danh mục, Hạn mức, Số tiền đã tiêu, Số tiền còn lại).
    * Gửi email tự động đến địa chỉ `email` của tài khoản đăng ký.
* **Frontend (Gia Huy):**
  * Thêm nút bật/tắt: *"Nhận email cảnh báo khi ngân sách đạt 80%"* trong phần Cài đặt tài khoản (`account.html`).

---

### Chức năng 5: Tìm kiếm & Bộ lọc nâng cao (Advanced Search & Filter)
* **Mô tả:** Cho phép người dùng tìm kiếm giao dịch với nhiều tiêu chí kết hợp cùng lúc.
* **Backend (Hoàng Nam):**
  * Nâng cấp `api/transaction/list.php` hỗ trợ thêm các tham số lọc:
    * `min_amount` và `max_amount`: Lọc theo khoảng số tiền (ví dụ: các khoản từ 500k đến 2 triệu).
    * `from_date` và `to_date`: Lọc theo khoảng ngày linh hoạt.
    * `payment_method`: Lọc theo hình thức (Tiền mặt, Chuyển khoản, Ví điện tử).
    * `category_id`: Lọc theo danh mục.
    * `search`: Tìm theo từ khóa trong ghi chú.
* **Frontend (Ngô Bảo):**
  * Dựng thanh công cụ tìm kiếm nâng cao dạng Drawer/Collapse trên trang `pages/income.html` và `pages/expense.html`:
    * Thanh kéo trượt chọn khoảng số tiền (Price Range Slider).
    * Ô chọn khoảng ngày bắt đầu - kết thúc.
    * Nút Reset xóa nhanh toàn bộ bộ lọc.

---

### Chức năng 6: Sao lưu & Khôi phục dữ liệu (Backup & Restore)
* **Mô tả:** Người dùng có thể tự tải toàn bộ dữ liệu tài chính của mình về máy tính dạng file sao lưu JSON/SQL an toàn, và có thể khôi phục lại khi cần.
* **Backend (Thái Châu & Hoàng Nam):**
  * `api/backup/export.php`: Trích xuất toàn bộ dữ liệu của `user_id` hiện tại (thông tin user, danh mục riêng, toàn bộ transactions, budgets) thành file `.json` có chữ ký bảo mật.
  * `api/backup/restore.php`: Tiếp nhận file `.json` upload lên, kiểm tra tính toàn vẹn và nạp lại vào database (hỗ trợ chế độ: ghi đè hoặc giữ nguyên dữ liệu cũ).
* **Frontend (Gia Huy):**
  * Tab **"Sao lưu & Dữ liệu"** trong trang `pages/account.html`.
  * Nút **"Tải bản sao lưu (.json)"** và khu vực **"Khôi phục dữ liệu từ file"**.

---

### Chức năng 7: Chia sẻ tài chính nhóm (Shared / Family Budget)
* **Mô tả:** Cho phép nhiều người dùng (gia đình, nhóm bạn cùng phòng) cùng tham gia quản lý một quỹ ngân sách chung, ghi nhận ai là người đã chi tiền cho khoản nào.
* **Database & Backend (Thái Châu):**
  * Tạo bảng `shared_spaces` (Không gian quỹ chung) và `shared_members` (Thành viên được mời):
    ```sql
    CREATE TABLE `shared_spaces` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `owner_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL, -- Ví dụ: Quỹ gia đình, Chi tiêu phòng 402
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    );
    CREATE TABLE `shared_members` (
        `space_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `role` ENUM('owner', 'member') DEFAULT 'member',
        PRIMARY KEY (`space_id`, `user_id`),
        FOREIGN KEY (`space_id`) REFERENCES `shared_spaces`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    );
    ```
  * `api/shared/create.php`: Tạo quỹ chung mới.
  * `api/shared/invite.php`: Mời thành viên vào quỹ bằng email hoặc mã mời.
  * `api/shared/transactions.php`: Lấy và thêm giao dịch trong quỹ chung, hiển thị tên người đã chi tiền.
* **Frontend (Gia Huy):**
  * Dựng trang `pages/shared.html`: Chuyển đổi giữa *"Ví cá nhân"* và *"Không gian quỹ chung"*.
  * Bảng chi tiêu nhóm hiển thị avatar và tên người thực hiện giao dịch.

---

## 🧪 3. KẾ HOẠCH KIỂM THỬ SPRINT 2 (TEST CASES)

| STT | Chức năng kiểm thử | Kịch bản thực hiện | Kết quả mong đợi | Phụ trách test |
| :---: | :--- | :--- | :--- | :---: |
| 1 | **Nhập dữ liệu CSV/Excel** | Upload file CSV mẫu có 10 dòng giao dịch | 10 giao dịch nạp thành công vào database, bảng cập nhật ngay | NG Bảo & QAnh |
| 2 | **Xuất báo cáo PDF** | Chọn tháng 11/2026 và nhấn "Xuất PDF" | Mở hộp thoại in/tải PDF chuẩn trang A4 có số liệu và biểu đồ | NG Bảo & QAnh |
| 3 | **Đổi tiền tệ sang USD** | Đổi sang USD trong tài khoản | Toàn bộ giao dịch và thẻ KPI đổi từ `đ` sang `$` chuẩn format | Hoàng Nam & Ngô Bảo |
| 4 | **Tìm kiếm đa điều kiện** | Lọc: Số tiền từ 100k - 500k, danh mục Ăn uống | Bảng chỉ hiện đúng các giao dịch thỏa mãn cả 2 điều kiện | Hoàng Nam & Ngô Bảo |
| 5 | **Gửi Email cảnh báo** | Thêm chi tiêu vượt 85% hạn mức ngân sách | Hộp thư email của user nhận được email cảnh báo màu vàng | Thái Châu & Gia Huy |
| 6 | **Sao lưu & Khôi phục** | Tải file backup.json $\rightarrow$ Xóa 1 giao dịch $\rightarrow$ Nạp lại file backup | Giao dịch vừa xóa được phục hồi nguyên vẹn | Thái Châu & Gia Huy |
| 7 | **Chia sẻ quỹ nhóm** | Tài khoản A mời tài khoản B vào Quỹ phòng $\rightarrow$ B thêm khoản chi 50k | Tài khoản A và B đều nhìn thấy khoản chi 50k có tên người chi | Cả nhóm nghiệm thu |

---

## 📅 4. TIMELINE 7 NGÀY TRIỂN KHAI SPRINT 2

| Ngày | Trọng tâm công việc | Đầu ra sản phẩm (Deliverables) | Phụ trách |
| :---: | :--- | :--- | :--- |
| **Thứ 2 (Ngày 1)** | Thiết kế CSDL mở rộng: Bảng `shared_spaces`, `shared_members`, tỷ giá tiền tệ | Cập nhật file SQL migration | Thái Châu, Hoàng Nam |
| **Thứ 3 (Ngày 2)** | Xây dựng API Import Excel/CSV & API Tìm kiếm nâng cao đa tiêu chí | `import_csv.php`, nâng cấp `transaction/list.php` | NG Bảo, Hoàng Nam |
| **Thứ 4 (Ngày 3)** | Dựng Giao diện Import Excel (kéo thả file) & Bộ lọc tìm kiếm khoảng giá | Modal upload file, Price Range Slider | Quốc Anh, Ngô Bảo |
| **Thứ 5 (Ngày 4)** | Xây dựng chức năng Đa tiền tệ & Tích hợp gửi Email cảnh báo qua SMTP | Hàm format đa tiền tệ, tích hợp gửi email | Hoàng Nam, Thái Châu |
| **Thứ 6 (Ngày 5)** | Xây dựng tính năng Xuất PDF chuẩn A4 & Sao lưu / Khôi phục dữ liệu | `export_pdf.php`, `backup/export.php`, `restore.php` | NG Bảo, Gia Huy |
| **Thứ 7 (Ngày 6)** | Hoàn thiện Module Chia sẻ tài chính nhóm (Quỹ chung nhiều người) | `shared.html`, `api/shared/*.php` | Thái Châu, Gia Huy |
| **Chủ nhật (Ngày 7)**| Tổng kiểm thử toàn diện 7 tính năng, Fix bug và đóng gói Sprint 2 | Hệ thống hoàn chỉnh 100%, sẵn sàng báo cáo | Cả nhóm 6 thành viên |

---

> 💡 **Kế hoạch này đã được lưu vào file [`KE_HOACH_TUAN_2.md`](file:///d:/Financial_manager/KE_HOACH_TUAN_2.md)** trong thư mục dự án để cả nhóm cùng theo dõi và thực hiện.
