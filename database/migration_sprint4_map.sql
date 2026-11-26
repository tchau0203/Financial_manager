-- ========================================================
-- MIGRATION SPRINT 4: BẢN ĐỒ CHI TIÊU (VỊ TRÍ GIAO DỊCH)
-- Dự án: CashManager
-- Lưu ý: hệ thống dùng bảng `transactions` (cả thu lẫn chi, phân biệt bằng cột `type`).
-- File này an toàn khi chạy nhiều lần (ADD COLUMN IF NOT EXISTS / CREATE INDEX có kiểm tra).
-- config/db.php cũng tự chạy bước này khi thiếu cột nên chạy tay là tùy chọn.
-- ========================================================

USE `quanlychitieu`;

-- 1. Thêm cột vị trí (đều cho phép NULL: khoản chi không có vị trí vẫn lưu bình thường)
ALTER TABLE `transactions`
    ADD COLUMN IF NOT EXISTS `latitude`      DECIMAL(9,6)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `longitude`     DECIMAL(9,6)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `location_name` VARCHAR(255)  DEFAULT NULL;

-- 2. Chỉ mục phục vụ truy vấn bản đồ (lọc theo user + ngày, bỏ qua bản ghi chưa có tọa độ)
--    MariaDB/XAMPP: dùng IF NOT EXISTS. MySQL 8: bỏ "IF NOT EXISTS" nếu báo lỗi cú pháp.
CREATE INDEX IF NOT EXISTS `idx_tx_user_date_geo`
    ON `transactions` (`user_id`, `transaction_date`, `latitude`, `longitude`);
