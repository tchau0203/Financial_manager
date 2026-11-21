-- ========================================================
-- CẬP NHẬT DATABASE SPRINT 2 (Thái Châu)
-- Chạy file này SAU quanlychitieu.sql (không xóa dữ liệu cũ)
-- ========================================================
USE `quanlychitieu`;

-- 1. Cờ bật/tắt nhận email cảnh báo ngân sách (Chức năng 4)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_alerts');
SET @sql := IF(@col = 0,
    'ALTER TABLE `users` ADD COLUMN `email_alerts` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = nhận email cảnh báo ngân sách''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Quỹ chung (Chức năng 7)
CREATE TABLE IF NOT EXISTS `shared_spaces` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `invite_code` VARCHAR(12) NOT NULL UNIQUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shared_members` (
    `space_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('owner', 'member') DEFAULT 'member',
    `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`space_id`, `user_id`),
    FOREIGN KEY (`space_id`) REFERENCES `shared_spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shared_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `space_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'Người thực hiện giao dịch',
    `category_id` INT NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `note` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`space_id`) REFERENCES `shared_spaces`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
