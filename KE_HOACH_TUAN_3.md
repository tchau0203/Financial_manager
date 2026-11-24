# 📋 Kế Hoạch Triển Khai Sprint 3 (Tuần 3) — Hệ Thống Quản Lý Tài Chính Cá Nhân

- **Tên dự án:** CashManager — Quản Lý Chi Tiêu Cá Nhân & Nhóm  
- **Thời gian thực hiện:** Sprint Tuần 3  
- **Nhánh tích hợp chính:** `main` (nhánh thử nghiệm: `feature/sprint-3`)  
- **Mục tiêu Sprint 3:** Nâng cấp hệ thống toàn diện với 3 phân hệ đột phá:  
  1. 🤖 **Tính năng Thông minh & AI (Trợ lý tài chính & Quét hóa đơn OCR)**  
  2. 💳 **Thanh toán VietQR & Quyết toán nợ Quỹ nhóm (Settlement & Split Bill)**  
  3. 📱 **Trải nghiệm Di động PWA & Thông báo đẩy (PWA App & Web Push)**  

---

## 🗂️ 1. PHÂN CÔNG NHIỆM VỤ CHO 6 THÀNH VIÊN TRONG NHÓM

| Phân hệ / Nhóm | Thành viên | Nhánh Git | Chức năng phụ trách chính |
| :--- | :--- | :--- | :--- |
| **🤖 Nhóm 1: Trí tuệ nhân tạo (AI & OCR)** | **NG Bảo** (BE)<br>**Quốc Anh** (FE) | `feature/ai-insights` | **1. Trợ lý AI tư vấn tài chính:** Phân tích dữ liệu chi tiêu hàng tháng, đưa ra gợi ý tiết kiệm.<br>**2. Quét hóa đơn OCR:** Tải ảnh hóa đơn/bill, tự động bóc tách số tiền, ngày, danh mục vào form. |
| **💳 Nhóm 2: VietQR & Quyết toán Quỹ** | **Thái Châu** (Lead/BE)<br>**Gia Huy** (FE) | `feature/vietqr-settlement` | **3. Quyết toán nợ Quỹ chung:** Thuật toán tính toán ai nợ ai, tối giản số lần chuyển tiền.<br>**4. Mã VietQR động:** Sinh mã QR chuyển khoản ngân hàng chuẩn Napas theo từng giao dịch. |
| **📱 Nhóm 3: PWA & Thông báo đẩy** | **Hoàng Nam** (BE)<br>**Ngô Bảo** (FE) | `feature/pwa-notifications` | **5. Cài đặt PWA (Mobile App):** Biến web thành app có thể cài lên màn hình điện thoại (Android/iOS).<br>**6. Nhắc nhở ghi chép:** Web Push Notification thông báo nhắc ghi chép chi tiêu vào buổi tối. |

---

## 🚀 2. THIẾT KẾ CHI TIẾT 3 PHÂN HỆ CHỨC NĂNG

---

### PHÂN HỆ 1: TÍNH NĂNG THÔNG MINH & TRÍ TUỆ NHÂN TẠO (AI & OCR)

#### Chức năng 1.1: Trợ lý AI tư vấn tài chính thông minh (Smart Financial Advisor)
* **Mô tả:** Hệ thống tổng hợp báo cáo thu chi, tỷ lệ tiết kiệm và các danh mục chi tiêu nhiều nhất trong tháng, gửi prompt đến AI (Gemini API) để tạo ra nhận xét và lời khuyên tiết kiệm tài chính cá nhân hóa.
* **Backend (NG Bảo):**
  * File: `api/ai/advice.php`.
  * Trích xuất tổng thu, tổng chi, số dư, top 3 danh mục tiêu nhiều nhất của user trong tháng.
  * Tích hợp API Gemini (`gemini-1.5-flash`) với prompt chuẩn định dạng tiếng Việt:
    * Đánh giá tình hình tài chính (Tốt / Báo động).
    * Chỉ ra các khoản lãng phí tiềm ẩn.
    * Đưa ra 3 hành động cụ thể để tiết kiệm tháng tới.
  * Trả về kết quả JSON có cấu trúc rõ ràng.
* **Frontend (Quốc Anh):**
  * Giao diện: Thêm nút **"Hỏi chuyên gia AI"** hoặc widget Trợ lý tài chính AI trên `dashboard.html` và `report.html`.
  * Hiệu ứng gõ chữ (typing effect) hoặc hiển thị thẻ nhận xét với huy hiệu đánh giá sức khỏe tài chính (A, B, C, D) sinh động.

#### Chức năng 1.2: Quét hóa đơn tự động bằng hình ảnh (Receipt OCR Scanner)
* **Mô tả:** Người dùng chụp hoặc tải ảnh hóa đơn (siêu thị, quán ăn, đổ xăng), AI tự động nhận diện thông tin và điền sẵn vào form thêm chi tiêu.
* **Backend (NG Bảo):**
  * File: `api/ai/ocr_receipt.php`.
  * Tiếp nhận file ảnh upload (`jpg`, `png`), kiểm tra kích thước tối đa 5MB.
  * Gọi Gemini Vision API phân tích ảnh hóa đơn, trích xuất:
    * `amount`: Tổng số tiền thanh toán.
    * `date`: Ngày trên hóa đơn.
    * `category_suggestion`: Đề xuất danh mục (Ăn uống, Mua sắm, Di chuyển...).
    * `note`: Tên cửa hàng hoặc danh sách món chính.
* **Frontend (Quốc Anh):**
  * Thêm nút **"📷 Quét hóa đơn"** tại Modal Thêm giao dịch (`expense.html`).
  * Cho phép chọn ảnh hoặc mở camera trực tiếp trên điện thoại.
  * Xem trước ảnh và tự động điền các ô Số tiền, Ngày, Danh mục, Ghi chú sau khi AI phân tích xong (có thể chỉnh sửa trước khi lưu).

---

### PHÂN HỆ 2: THANH TOÁN VIETQR & QUYẾT TOÁN QUỸ NHÓM

#### Chức năng 2.1: Thuật toán Quyết toán công nợ Quỹ chung (Debt Settlement)
* **Mô tả:** Trong không gian quỹ chung (phòng trọ, đi du lịch), các thành viên chi các khoản khác nhau. Hệ thống tự động tính toán số dư ròng của từng người và đưa ra phương án trả nợ tối ưu nhất (ít giao dịch chuyển khoản nhất).
* **Database & Backend (Thái Châu):**
  * Bảng `shared_settlements`: Lưu lịch sử thanh toán nợ giữa các thành viên.
  * File: `api/shared/settlement.php`:
    * Tính tổng tiền quỹ đã chi và phần chia bình quân đầu người.
    * Xác định: Ai đang trả thừa (được nhận lại tiền) và Ai đang trả thiếu (phải trả thêm).
    * Áp dụng thuật toán tối giản dòng tiền (Debt Simplification): Xuất ra danh sách cụ thể: *"Nguyễn Văn A cần chuyển 150.000đ cho Trần Thị B"*.
    * API xác nhận *"Đã thanh toán nợ"*.
* **Frontend (Gia Huy):**
  * Tab **"Quyết toán quỹ"** trên trang `pages/shared.html`.
  * Danh sách các khoản nợ cần hoàn trả dạng thẻ (Card) trực quan kèm trạng thái: *Chưa trả / Đã trả*.

#### Chức năng 2.2: Tích hợp mã VietQR động theo giao dịch
* **Mô tả:** Cho phép quét mã QR để chuyển khoản nhanh cho giao dịch quỹ hoặc chuyển khoản cá nhân theo chuẩn Napas 247 mà không cần nhớ số tài khoản.
* **Backend (Thái Châu):**
  * Cập nhật trường `bank_code` và `bank_account` trong bảng `users` để mỗi người có thể lưu STK ngân hàng của mình.
  * File: `api/shared/qr.php`: Sinh URL mã QR VietQR chuẩn:
    `https://img.vietqr.io/image/{bank_code}-{bank_account}-compact2.png?amount={amount}&addInfo={note}&accountName={name}`.
* **Frontend (Gia Huy):**
  * Thêm phần **"Thông tin tài khoản ngân hàng"** trong `pages/account.html`.
  * Khi bấm vào nút **"Thanh toán ngay"** trên thẻ nợ của Quỹ chung: Hiển thị Popup Modal chứa **Mã VietQR động**, số tiền, nội dung chuyển khoản và nút "Sao chép số tài khoản".

---

### PHÂN HỆ 3: TRẢI NGHIỆM DI ĐỘNG PWA & THÔNG BÁO ĐẨY

#### Chức năng 3.1: Ứng dụng Web cấp tiến (Progressive Web App - PWA)
* **Mô tả:** Cho phép người dùng cài đặt CashManager trực tiếp lên màn hình điện thoại (Home Screen) như ứng dụng App Store / Google Play, có icon ứng dụng riêng, màn hình splash và chế độ toàn màn hình (Standalone) không thanh URL.
* **Backend & Cấu hình (Hoàng Nam):**
  * Tạo tệp `manifest.json` chứa: `name`, `short_name`, `theme_color`, `background_color`, `display: standalone`, danh sách icon kích thước chuẩn (192x192, 512x512).
  * Viết Service Worker `sw.js` đăng ký caching các tài nguyên tĩnh (CSS, JS, Fonts, Images) để ứng dụng tải nhanh tức thì ngay cả khi mạng yếu.
* **Frontend (Ngô Bảo):**
  * Nhúng thẻ `<link rel="manifest">` và đăng ký Service Worker vào tất cả các trang HTML.
  * Banner thông minh: Hiển thị thanh gợi ý **"Cài đặt CashManager về điện thoại"** kèm nút "Cài đặt ngay" khi người dùng truy cập bằng trình duyệt di động (Chrome, Safari, Cốc Cốc).

#### Chức năng 3.2: Hệ thống Thông báo nhắc nhở ghi chép (Web Push / Reminder)
* **Mô tả:** Nhắc nhở người dùng hình thành thói quen tài chính tốt: gửi thông báo trình duyệt vào mỗi tối nhắc ghi chép chi tiêu trong ngày nếu người dùng chưa nhập giao dịch nào.
* **Backend (Hoàng Nam):**
  * File: `api/notification/settings.php`: Lưu cấu hình giờ nhắc nhở (mặc định 21:00) và trạng thái bật/tắt.
  * File: `api/notification/check_daily.php`: Kiểm tra xem hôm nay user đã ghi chép khoản nào chưa, nếu chưa sẽ kích hoạt thông báo.
* **Frontend (Ngô Bảo):**
  * Tích hợp Web Notifications API của trình duyệt (`Notification.requestPermission()`).
  * Mục cài đặt **"Nhắc nhở ghi chép hàng ngày"** trong `pages/account.html` cho phép người dùng chọn giờ nhận thông báo.
  * Hiển thị thông báo nổi trên desktop / màn hình khóa điện thoại: *"Hôm nay bạn có khoản chi tiêu nào chưa ghi lại không? Hãy cập nhật ngay nhé!"*.

---

## 🧪 3. KỊCH BẢN KIỂM THỬ SPRINT 3 (TEST CASES)

| STT | Kịch bản kiểm thử | Hành động thực hiện | Kết quả mong đợi | Phụ trách test |
| :---: | :--- | :--- | :--- | :---: |
| 1 | **Tư vấn AI** | Bấm nút "Hỏi chuyên gia AI" trên báo cáo tháng | Trả về nhận xét chi tiết, lời khuyên và đánh giá sức khỏe tài chính trong < 5 giây | NG Bảo & QAnh |
| 2 | **Quét hóa đơn OCR** | Upload ảnh bill cafe/siêu thị | Tự động điền đúng Số tiền, Ngày và Đề xuất danh mục chính xác | NG Bảo & QAnh |
| 3 | **Quyết toán nợ Quỹ** | Thành viên A chi 300k cho cả 3 người (A, B, C) | Bảng quyết toán hiển thị: B nợ A 100k, C nợ A 100k | Thái Châu & Gia Huy |
| 4 | **Quét VietQR chuyển tiền** | Bấm "Thanh toán ngay" cho khoản nợ 100k | Hiện mã QR có đúng logo ngân hàng, số tiền 100.000đ và nội dung chuyển khoản | Thái Châu & Gia Huy |
| 5 | **Cài đặt PWA** | Mở web trên điện thoại / Chrome và bấm "Cài đặt" | Ứng dụng cài ra màn hình chính, mở độc lập không thanh địa chỉ | Hoàng Nam & Ngô Bảo |
| 6 | **Thông báo nhắc nhở** | Cấp quyền thông báo và bật tính năng nhắc nhở | Nhận được thông báo push của CashManager trên thiết bị | Hoàng Nam & Ngô Bảo |

---

## 📅 4. TIMELINE 7 NGÀY TRIỂN KHAI SPRINT 3

| Ngày | Trọng tâm công việc | Đầu ra sản phẩm (Deliverables) | Phụ trách |
| :---: | :--- | :--- | :--- |
| **Thứ 2 (Ngày 1)** | Cấu hình Gemini API key, thiết kế bảng `shared_settlements` và file `manifest.json` | API Keys, DB Migration, PWA manifest | Thái Châu, NG Bảo, Hoàng Nam |
| **Thứ 3 (Ngày 2)** | Xây dựng API Trợ lý AI (`advice.php`) & API sinh mã VietQR (`qr.php`) | API tư vấn AI, API VietQR | NG Bảo, Thái Châu |
| **Thứ 4 (Ngày 3)** | Dựng giao diện Chatbot AI / Lời khuyên & Giao diện cài đặt STK ngân hàng | Widget AI tư vấn, Form ngân hàng `account.html` | Quốc Anh, Gia Huy |
| **Thứ 5 (Ngày 4)** | Xây dựng API OCR hóa đơn (`ocr_receipt.php`) & API Quyết toán nợ (`settlement.php`) | API OCR hình ảnh, Logic tối ưu nợ | NG Bảo, Thái Châu |
| **Thứ 6 (Ngày 5)** | Hoàn thiện Modal quét hóa đơn & Modal thanh toán VietQR động | Nút Camera OCR, Modal VietQR | Quốc Anh, Gia Huy |
| **Thứ 7 (Ngày 6)** | Hoàn thiện Service Worker PWA & Web Push Notifications | `sw.js`, Cài đặt PWA trên mobile | Hoàng Nam, Ngô Bảo |
| **Chủ nhật (Ngày 7)**| Tổng kiểm thử toàn diện Sprint 3, chạy kịch bản thử nghiệm End-to-End | Hệ thống hoàn thiện 100% | Cả nhóm 6 thành viên |

---

> 💡 **Kế hoạch này đã được lưu vào [`KE_HOACH_TUAN_3.md`](file:///d:/Financial_manager/Financial_manager/KE_HOACH_TUAN_3.md)** để cả nhóm cùng theo dõi và bắt tay vào thực hiện!
