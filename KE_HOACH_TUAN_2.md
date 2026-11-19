# 📋 Kế Hoạch Triển Khai Sprint 2 (Tuần 2) — Hệ Thống Quản Lý Tài Chính Cá Nhân

- **Tên dự án:** CashManager — Ứng dụng Quản lý Chi tiêu Cá nhân  
- **Thời gian thực hiện:** Tuần 2 (Tiếp nối thành công của Sprint 1)  
- **Nhánh tích hợp chính:** `main` (hoặc `feature/test-code`)  
- **Mục tiêu Sprint 2:** Nâng cấp hệ thống từ mức **Cơ bản (Core Features)** lên mức **Nâng cao & Hoàn thiện (Advanced & Production-ready)**: Bổ sung Quản lý nhiều Ví tiền/Tài khoản thanh toán, Mục tiêu tiết kiệm (Savings Goals), Đính kèm hóa đơn chứng từ, Chuông thông báo thời gian thực, Gợi ý chi tiêu thông minh và Triển khai đưa ứng dụng lên Web Hosting trực tuyến.

---

## 🗂️ 1. TỔNG QUAN PHÂN CÔNG NHIỆM VỤ TUẦN 2 (6 THÀNH VIÊN)

Dựa trên năng lực và sự phân vai hiệu quả từ Tuần 1, nhóm 6 thành viên tiếp tục phối hợp theo các phân hệ nâng cao:

| Nhóm | Thành viên | Nhánh Git | Phân hệ phụ trách trong Tuần 2 |
| :--- | :--- | :--- | :--- |
| **🏆 Nhóm 1: Architecture, Deploy & UI/UX** | **Thái Châu** (Lead/Full-stack) + **Ngô Bảo** (FE) | `feature/deploy-ux` | • Triển khai ứng dụng lên Web Hosting trực tuyến (InfinityFree/VPS)<br>• Hoàn thiện Responsive Mobile 100%, Drawer menu, Dark mode<br>• Chuông thông báo thời gian thực trên Header |
| **🎯 Nhóm 2: Ví tiền & Mục tiêu tiết kiệm** | **NG Bảo** (BE) + **QAnh** (FE) | `feature/wallet-savings` | • Phân hệ Quản lý nhiều Ví tiền (Tiền mặt, Tài khoản Ngân hàng, Ví MoMo, Thẻ tín dụng)<br>• Phân hệ Quản lý Mục tiêu tiết kiệm (Savings Goals) theo dõi tiến độ tích lũy quỹ |
| **💡 Nhóm 3: Thông minh & Mở rộng báo cáo** | **Hoàng Nam** (BE/DB) + **Gia Huy** (Full-stack) | `feature/insights-export` | • Module Gợi ý tài chính thông minh (Smart Financial Advisor)<br>• Mở rộng CSDL: Đính kèm hóa đơn ảnh giao dịch<br>• Nâng cấp Báo cáo: So sánh các tháng, Xuất báo cáo PDF |

---

## 🚀 2. CHI TIẾT TỪNG TÍNH NĂNG NÂNG CAO TRONG TUẦN 2

---

### PHÂN HỆ 1: QUẢN LÝ NHIỀU VÍ TIỀN & NGUỒN TIỀN (MULTI-WALLET MANAGEMENT)
> *Hiện tại: Giao dịch chỉ ghi nhận phương thức thanh toán dạng chữ (`cash`, `bank`).*  
> *Mục tiêu Tuần 2: Người dùng có thể quản lý số dư từng ví cụ thể (ví dụ: Ví tiền mặt còn 2tr, Tài khoản VCB còn 15tr, MoMo còn 500k). Khi thu/chi thì số dư ví tự động tăng/giảm.*

#### ✅ Backend — NG Bảo & Hoàng Nam
* **Database Migration:** Tạo bảng `wallets`
  ```sql
  CREATE TABLE `wallets` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `name` VARCHAR(100) NOT NULL, -- Tên ví: Ví tiền mặt, VCB, MoMo...
      `balance` DECIMAL(15,2) DEFAULT 0.00, -- Số dư hiện tại
      `icon` VARCHAR(50) DEFAULT 'fa-wallet',
      `color` VARCHAR(20) DEFAULT '#4F46E5',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
  );
  ALTER TABLE `transactions` ADD COLUMN `wallet_id` INT NULL,
  ADD CONSTRAINT `fk_trans_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE SET NULL;
  ```
* **Các API xây dựng:**
  * `api/wallet/list.php`: Lấy danh sách ví kèm số dư hiện tại của từng ví.
  * `api/wallet/add.php`: Thêm ví mới (Tên ví, số dư ban đầu, icon, màu).
  * `api/wallet/transfer.php`: Chuyển tiền giữa 2 ví nội bộ (ví dụ: Rút từ ATM về Tiền mặt).
  * Tự động cập nhật `balance` trong `api/transaction/add.php` (Thu: `balance += amount`, Chi: `balance -= amount`).

#### ✅ Frontend — QAnh
* **Màn hình & Giao diện:**
  * Dựng trang `pages/wallets.html` & `assets/js/wallets.js`: Thẻ hiển thị số dư từng ví, tổng tài sản ròng.
  * Tích hợp lựa chọn Ví thanh toán vào modal Thêm/Sửa thu chi ở `pages/income.html`, `pages/expense.html` và `dashboard.html`.

---

### PHÂN HỆ 2: MỤC TIÊU TIẾT KIỆM (SAVINGS GOALS)
> *Mục tiêu Tuần 2: Giúp người dùng đặt ra mục tiêu tài chính (ví dụ: Tiết kiệm mua Laptop 20 triệu trước 31/12/2026). Mỗi lần gửi tiền tiết kiệm, hệ thống hiển thị thanh tiến độ hoàn thành %.*

#### ✅ Backend — NG Bảo
* **Database Migration:** Tạo bảng `savings_goals`
  ```sql
  CREATE TABLE `savings_goals` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `name` VARCHAR(150) NOT NULL,
      `target_amount` DECIMAL(15,2) NOT NULL,
      `current_amount` DECIMAL(15,2) DEFAULT 0.00,
      `deadline` DATE NULL,
      `status` ENUM('in_progress', 'completed') DEFAULT 'in_progress',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
  );
  ```
* **Các API xây dựng:**
  * `api/savings/list.php`: Danh sách các mục tiêu tiết kiệm.
  * `api/savings/create.php`: Tạo mục tiêu mới.
  * `api/savings/deposit.php`: Nạp tiền vào quỹ tiết kiệm (tự động tính % hoàn thành).

#### ✅ Frontend — QAnh & Gia Huy
* Dựng thẻ Goal Card: Vòng tròn tiến độ %, số tiền còn thiếu, đếm ngược số ngày đến hạn chót (Deadline).
* Hiệu ứng chúc mừng (Confetti animation) khi người dùng hoàn thành 100% mục tiêu tiết kiệm.

---

### PHÂN HỆ 3: GỢI Ý TÀI CHÍNH THÔNG MINH (SMART FINANCIAL ADVISOR)
> *Mục tiêu Tuần 2: Phân tích tự động lịch sử chi tiêu để đưa ra lời khuyên hữu ích cho người dùng.*

#### ✅ Full-stack — Trần Gia Huy
* **Logic phân tích thuật toán (Rule-based Insights Engine):**
  * Tỷ lệ chi tiêu danh mục Ăn uống $> 40\%$ thu nhập $\rightarrow$ Đưa ra lời khuyên: *"Bạn đang chi quá nhiều cho ăn uống bên ngoài, hãy thử tự nấu ăn để tiết kiệm thêm 15% mỗi tháng."*
  * Tỷ lệ tiết kiệm tháng này $< 10\%$ $\rightarrow$ Cảnh báo: *"Tỷ lệ tiết kiệm của bạn đang ở mức thấp, hãy áp dụng quy tắc 50/30/20."*
  * Khoản chi bất thường: Xuất hiện giao dịch chi tiêu lớn gấp 3 lần mức trung bình hàng ngày $\rightarrow$ Gợi ý xem lại khoản chi.
* **API:** `api/report/insights.php`: Trả về danh sách lời khuyên thông minh.
* **Giao diện:** Thêm widget **"Lời khuyên thông minh hôm nay"** ngay đầu trang `pages/dashboard.html` và `pages/report.html`.

---

### PHÂN HỆ 4: CHUÔNG THÔNG BÁO THỜI GIAN THỰC & ĐÍNH KÈM HÓA ĐƠN
> *Mục tiêu Tuần 2: Chuông thông báo trên Header có số đếm đỏ (Badge), bấm vào mở dropdown xem nhanh các cảnh báo vượt hạn mức.*

#### ✅ Backend — Hoàng Nam
* **API Nâng cấp:**
  * Mở rộng `api/transaction/add.php`: Hỗ trợ upload ảnh chụp hóa đơn (`multipart/form-data`) lưu vào thư mục `assets/uploads/receipts/`.
  * Hoàn thiện hàm kiểm tra ngân sách tức thì, tự động đẩy tin nhắn vào `notifications`.

#### ✅ Frontend — Ngô Bảo
* Thêm icon chuông thông báo `<i class="fa-solid fa-bell"></i>` có gắn số lượng tin chưa đọc trên thanh Topbar chung của tất cả các trang.
* Bấm vào chuông hiện popover menu danh sách thông báo, có nút "Đánh dấu tất cả là đã đọc".

---

### PHÂN HỆ 5: XUẤT BÁO CÁO PDF CHUYÊN NGHIỆP & NÂNG CẤP BÁO CÁO
> *Mục tiêu Tuần 2: Ngoài xuất file CSV, hỗ trợ người dùng in hoặc xuất trực tiếp file Báo cáo tài chính tháng ra định dạng PDF có biểu đồ.*

#### ✅ Backend — Hoàng Nam
* Tạo API `api/export/export_pdf.php` (dùng thư viện nhẹ hoặc tính năng in chuẩn CSS `@media print`).

#### ✅ Frontend — Gia Huy
* Bổ sung tính năng in báo cáo trên `pages/report.html`: Bấm "In báo cáo PDF" $\rightarrow$ Tự động căn chỉnh trang in sạch đẹp không dính thanh menu Sidebar.

---

### PHÂN HỆ 6: TRIỂN KHAI LÊN WEB HOSTING (ONLINE DEPLOYMENT)
> *Mục tiêu Tuần 2: Đưa website lên Internet để Thầy/Cô và bạn bè có thể truy cập trực tiếp bằng điện thoại/máy tính bất kỳ lúc nào.*

#### ✅ Trưởng nhóm — Thái Châu
* **Các bước triển khai:**
  1. Đăng ký Web Hosting miễn phí hỗ trợ PHP 8 + MySQL (khuyên dùng **InfinityFree** hoặc **Hostinger/VPS**).
  2. Tạo CSDL trên hosting và import file `database/quanlychitieu.sql`.
  3. Cấu hình biến môi trường kết nối database trên máy chủ trực tuyến.
  4. Đẩy mã nguồn thông qua FTP (FileZilla) hoặc GitHub Actions Deploy.
  5. Kiểm thử toàn bộ 100% chức năng chạy online trên tên miền chính thức.

---

## 🧪 3. KẾ HOẠCH KIỂM THỬ SPRINT 2 (TESTING PLAN)

| STT | Kịch bản kiểm thử | Thao tác thực hiện | Kết quả mong đợi | Phụ trách |
| :---: | :--- | :--- | :--- | :---: |
| 1 | **Chuyển tiền giữa các Ví** | Chuyển 500k từ ví Ngân hàng sang Ví tiền mặt | Số dư ví Ngân hàng -500k, ví Tiền mặt +500k, tổng tài sản không đổi | NG Bảo & QAnh |
| 2 | **Cập nhật số dư khi Chi tiêu** | Thêm khoản chi 100k chọn ví "Tiền mặt" | Số dư ví Tiền mặt tự động giảm đúng 100k | NG Bảo & QAnh |
| 3 | **Tiến độ Mục tiêu tiết kiệm** | Nạp 5 triệu vào mục tiêu "Mua xe máy 30 triệu" | Thanh tiến độ nhảy lên 16.7%, hiển thị số tiền còn thiếu | QAnh & Huy |
| 4 | **Thuật toán Gợi ý AI** | Cố tình tạo chi tiêu ăn uống vượt 50% tổng thu | Xuất hiện thẻ khuyến nghị cắt giảm chi phí ăn uống | Gia Huy |
| 5 | **Chuông thông báo Real-time** | Chi tiêu vượt 80% ngân sách tháng | Chuông thông báo nhảy số `1`, click xem được nội dung chi tiết | Ngô Bảo & Nam |
| 6 | **Upload ảnh hóa đơn** | Thêm giao dịch chi tiêu kèm tệp ảnh `.png`/`.jpg` | Ảnh lưu vào máy chủ, bảng hiển thị icon xem lại ảnh hóa đơn | Nam & QAnh |
| 7 | **Kiểm thử Web Online** | Truy cập website bằng 4G trên điện thoại thông minh | Web load nhanh, giao diện co giãn chuẩn điện thoại, dữ liệu đồng bộ | Thái Châu & Cả nhóm |

---

## 📅 4. TIMELINE 7 NGÀY CHI TIẾT CỦA TUẦN 2

| Ngày | Trọng tâm công việc | Đầu ra bắt buộc (Deliverables) | Phụ trách chính |
| :---: | :--- | :--- | :--- |
| **Ngày 1 (Thứ 2)** | Mở rộng CSDL: Thêm bảng `wallets`, `savings_goals` | Cập nhật `database/quanlychitieu.sql`, viết migration script | Thái Châu, Hoàng Nam |
| **Ngày 2 (Thứ 3)** | Hoàn thiện Backend API cho Ví tiền & Mục tiêu tiết kiệm | `api/wallet/*.php`, `api/savings/*.php` hoàn thành | NG Bảo, Hoàng Nam |
| **Ngày 3 (Thứ 4)** | Dựng Giao diện Quản lý Ví tiền & Thẻ Mục tiêu tiết kiệm | `pages/wallets.html`, `pages/savings.html`, liên kết modal | Quốc Anh, Gia Huy |
| **Ngày 4 (Thứ 5)** | Module Lời khuyên tài chính thông minh (Insights) | `api/report/insights.php`, widget gợi ý trên Dashboard | Trần Gia Huy |
| **Ngày 5 (Thứ 6)** | Chuông thông báo Header, Upload hóa đơn & Xuất PDF | Dropdown thông báo, hỗ trợ in PDF chuẩn trang in | Ngô Bảo, Hoàng Nam |
| **Ngày 6 (Thứ 7)** | Triển khai đưa ứng dụng lên Web Hosting trực tuyến | Website chạy online có tên miền trực tiếp trên Internet | Thái Châu (Leader) |
| **Ngày 7 (Chủ nhật)**| Tổng kiểm thử End-to-End, Fix bug toàn diện & Đóng Sprint 2 | Hệ thống hoàn chỉnh 100%, sẵn sàng cho Báo cáo tiến độ lần 2 | Cả nhóm 6 thành viên |

---

> 💡 **Cam kết chất lượng Sprint 2:** Toàn bộ thành viên tiếp tục tuân thủ quy tắc phân nhánh Git (`feature/...`), tự kiểm thử mã nguồn trước khi tạo Pull Request để đảm bảo nhánh chính luôn ổn định và sẵn sàng demo bất cứ lúc nào.
