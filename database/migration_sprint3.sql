-- ========================================================
-- MIGRATION SPRINT 3: BỔ SUNG CƠ SỞ DỮ LIỆU SPRINT 3
-- Dự án: CashManager
-- ========================================================

USE `quanlychitieu`;

-- 1. Bổ sung thông tin tài khoản ngân hàng vào bảng users (phục vụ sinh mã VietQR)
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `bank_code` VARCHAR(20) DEFAULT NULL AFTER `currency`,
ADD COLUMN IF NOT EXISTS `bank_account` VARCHAR(50) DEFAULT NULL AFTER `bank_code`,
ADD COLUMN IF NOT EXISTS `bank_account_name` VARCHAR(100) DEFAULT NULL AFTER `bank_account`;

-- 2. Bổ sung cấu hình nhắc nhở ghi chép hàng ngày (phục vụ PWA Push Notification)
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `daily_reminder` TINYINT(1) DEFAULT 1 AFTER `email_alerts`,
ADD COLUMN IF NOT EXISTS `reminder_time` TIME DEFAULT '21:00:00' AFTER `daily_reminder`;

-- 3. Tạo bảng lưu trữ công nợ và quyết toán Quỹ chung (phục vụ Module Quỹ nhóm)
CREATE TABLE IF NOT EXISTS `shared_settlements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `from_user_id` INT NOT NULL,
    `to_user_id` INT NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `status` ENUM('pending', 'settled') DEFAULT 'pending',
    `note` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `settled_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`space_id`) REFERENCES `shared_spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
