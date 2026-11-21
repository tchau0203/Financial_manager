-- ========================================================
-- KỊCH BẢN KHỞI TẠO CƠ SỞ DỮ LIỆU: quanlychitieu
-- Hệ thống Quản Lý Chi Tiêu Cá Nhân
-- ========================================================

CREATE DATABASE IF NOT EXISTS `quanlychitieu` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `quanlychitieu`;

-- AN TOÀN: file này KHÔNG xóa dữ liệu cũ, có thể chạy lại nhiều lần.
-- 1. BẢNG NGƯỜI DÙNG (users)

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT 'VND',
    `email_alerts` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = nhận email cảnh báo ngân sách',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Nâng cấp DB cũ: thêm cột email_alerts nếu chưa có (không mất dữ liệu)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_alerts');
SET @sql := IF(@col = 0, 'ALTER TABLE `users` ADD COLUMN `email_alerts` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. BẢNG DANH MỤC THU / CHI (categories)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL COMMENT 'NULL là danh mục mặc định của hệ thống',
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'fa-tag',
    `color` VARCHAR(20) DEFAULT '#4F46E5',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG GIAO DỊCH THU / CHI (transactions)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Tiền mặt',
    `note` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transactions_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG NGÂN SÁCH (budgets)
CREATE TABLE IF NOT EXISTS `budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `month` TINYINT NOT NULL COMMENT '1 - 12',
    `year` SMALLINT NOT NULL,
    `alert_threshold` INT DEFAULT 80 COMMENT 'Ngưỡng cảnh báo theo %',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_category_month_year` (`user_id`, `category_id`, `month`, `year`),
    CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_budgets_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG THÔNG BÁO (notifications)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'warning',
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 6. QUỸ CHUNG (shared_spaces, shared_members, shared_transactions)
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

-- ========================================================
-- DỮ LIỆU MẪU (SEED DATA)
-- ========================================================

-- Tài khoản demo (Mật khẩu: demo123)
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `currency`) VALUES
(1, 'demo', 'demo@example.com', '$2y$10$auEUlELoaHOSLuGN9Snaeud9.eNlXRxXkDwWkVWbmleBnjUCm5hRy', 'Người dùng Demo', '0901234567', 'VND');

-- Danh mục chuẩn hệ thống (user_id = NULL)
INSERT IGNORE INTO `categories` (`id`, `user_id`, `name`, `type`, `icon`, `color`) VALUES
-- Khoản thu
(1, NULL, 'Lương', 'income', 'fa-money-bill-wave', '#10B981'),
(2, NULL, 'Thưởng', 'income', 'fa-gift', '#059669'),
(3, NULL, 'Đầu tư', 'income', 'fa-chart-line', '#3B82F6'),
(4, NULL, 'Thu nhập khác', 'income', 'fa-wallet', '#6366F1'),

-- Khoản chi
(5, NULL, 'Ăn uống', 'expense', 'fa-utensils', '#EF4444'),
(6, NULL, 'Mua sắm', 'expense', 'fa-shopping-cart', '#F59E0B'),
(7, NULL, 'Di chuyển', 'expense', 'fa-motorcycle', '#EC4899'),
(8, NULL, 'Hóa đơn & Tiện ích', 'expense', 'fa-bolt', '#8B5CF6'),
(9, NULL, 'Giải trí', 'expense', 'fa-gamepad', '#06B6D4'),
(10, NULL, 'Y tế & Sức khỏe', 'expense', 'fa-heartbeat', '#14B8A6'),
(11, NULL, 'Giáo dục', 'expense', 'fa-graduation-cap', '#F97316'),
(12, NULL, 'Chi tiêu khác', 'expense', 'fa-receipt', '#64748B');

-- Thêm tài khoản thành viên Thái Châu (Mật khẩu: demo123 hoặc pass cá nhân)
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `currency`) VALUES
(2, 'thaichau', 'thaichau020326@gmail.com', '$2y$10$qxqlK3XJ.8KnBrTfuvJ9Zuk9eln.JwOVC27Igl0jEaKDNKwQKQv8O', 'Thái Châu', '0909999999', 'VND');

-- ========================================================
-- DỮ LIỆU GIAO DỊCH MẪU (TRANSACTIONS SEED DATA)
-- ========================================================
INSERT IGNORE INTO `transactions` (`id`, `user_id`, `category_id`, `type`, `amount`, `transaction_date`, `payment_method`, `note`) VALUES
-- Thu nhập tài khoản demo (user_id = 1)
(1, 1, 1, 'income', 15000000.00, '2026-11-05', 'bank_transfer', 'Nhận lương tháng 11/2026'),
(2, 1, 2, 'income', 2500000.00, '2026-11-10', 'bank_transfer', 'Tiền thưởng hoàn thành dự án'),
(3, 1, 3, 'income', 1200000.00, '2026-11-12', 'e_wallet', 'Lãi đầu tư tài chính'),

-- Chi tiêu tài khoản demo (user_id = 1)
(4, 1, 5, 'expense', 50000.00, '2026-11-13', 'cash', 'Ăn sáng bánh mì & cà phê'),
(5, 1, 5, 'expense', 85000.00, '2026-11-13', 'e_wallet', 'Cơm trưa văn phòng'),
(6, 1, 5, 'expense', 120000.00, '2026-11-13', 'cash', 'Ăn tối cùng bạn bè'),
(7, 1, 6, 'expense', 450000.00, '2026-11-14', 'bank_transfer', 'Mua sắm vật dụng siêu thị'),
(8, 1, 7, 'expense', 100000.00, '2026-11-15', 'cash', 'Đổ xăng xe máy'),
(9, 1, 8, 'expense', 550000.00, '2026-11-16', 'e_wallet', 'Thanh toán tiền điện nước internet'),
(10, 1, 9, 'expense', 200000.00, '2026-11-16', 'e_wallet', 'Vé xem phim cuối tuần'),
(11, 1, 11, 'expense', 300000.00, '2026-11-17', 'bank_transfer', 'Mua sách chuyên ngành & tài liệu học tập');

-- ========================================================
-- DỮ LIỆU NGÂN SÁCH MẪU (BUDGETS SEED DATA)
-- ========================================================
INSERT IGNORE INTO `budgets` (`id`, `user_id`, `category_id`, `amount`, `month`, `year`, `alert_threshold`) VALUES
(1, 1, 5, 5000000.00, 11, 2026, 80),
(2, 1, 6, 2500000.00, 11, 2026, 80),
(3, 1, 7, 1000000.00, 11, 2026, 80),
(4, 1, 8, 1500000.00, 11, 2026, 80);

-- ========================================================
-- THÔNG BÁO MẪU (NOTIFICATIONS SEED DATA)
-- ========================================================
INSERT IGNORE INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Chào mừng bạn', 'Chào mừng bạn đến với ứng dụng Quản lý Chi tiêu Cá nhân CashManager!', 'info', 1, '2026-11-01 08:00:00'),
(2, 1, 'Nhắc nhở ngân sách', 'Bạn đã thiết lập thành công hạn mức ngân sách cho tháng 11/2026.', 'success', 0, '2026-11-05 09:30:00');
