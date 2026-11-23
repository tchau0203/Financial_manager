-- ========================================================
-- KỊCH BẢN KHỞI TẠO CƠ SỞ DỮ LIỆU: quanlychitieu
-- Hệ thống Quản Lý Chi Tiêu Cá Nhân
-- ========================================================

CREATE DATABASE IF NOT EXISTS `quanlychitieu` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `quanlychitieu`;

-- 1. BẢNG NGƯỜI DÙNG (users)
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `budgets`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT 'VND',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG DANH MỤC THU / CHI (categories)
CREATE TABLE `categories` (
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
CREATE TABLE `transactions` (
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
CREATE TABLE `budgets` (
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
CREATE TABLE `notifications` (
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
-- DỮ LIỆU MẪU (SEED DATA)
-- ========================================================

-- Tài khoản demo (Mật khẩu: demo123)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `currency`) VALUES
(1, 'demo', 'demo@example.com', '$2y$10$auEUlELoaHOSLuGN9Snaeud9.eNlXRxXkDwWkVWbmleBnjUCm5hRy', 'Người dùng Demo', '0901234567', 'VND');

-- Danh mục chuẩn hệ thống (user_id = NULL)
INSERT INTO `categories` (`id`, `user_id`, `name`, `type`, `icon`, `color`) VALUES
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
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `currency`) VALUES
(2, 'thaichau', 'thaichau020326@gmail.com', '$2y$10$qxqlK3XJ.8KnBrTfuvJ9Zuk9eln.JwOVC27Igl0jEaKDNKwQKQv8O', 'Thái Châu', '0909999999', 'VND')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- ========================================================
-- DỮ LIỆU GIAO DỊCH MẪU (TRANSACTIONS SEED DATA)
-- ========================================================
INSERT INTO `transactions` (`id`, `user_id`, `category_id`, `type`, `amount`, `transaction_date`, `payment_method`, `note`) VALUES
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
(11, 1, 11, 'expense', 300000.00, '2026-11-17', 'bank_transfer', 'Mua sách chuyên ngành & tài liệu học tập')
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- ========================================================
-- DỮ LIỆU NGÂN SÁCH MẪU (BUDGETS SEED DATA)
-- ========================================================
INSERT INTO `budgets` (`id`, `user_id`, `category_id`, `amount`, `month`, `year`, `alert_threshold`) VALUES
(1, 1, 5, 5000000.00, 11, 2026, 80),  -- Ngân sách Ăn uống: 5,000,000 đ
(2, 1, 6, 2500000.00, 11, 2026, 80),  -- Ngân sách Mua sắm: 2,500,000 đ
(3, 1, 7, 1000000.00, 11, 2026, 80),  -- Ngân sách Di chuyển: 1,000,000 đ
(4, 1, 8, 1500000.00, 11, 2026, 80)   -- Ngân sách Hóa đơn: 1,500,000 đ
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- ========================================================
-- THÔNG BÁO MẪU (NOTIFICATIONS SEED DATA)
-- ========================================================
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Chào mừng bạn', 'Chào mừng bạn đến với ứng dụng Quản lý Chi tiêu Cá nhân CashManager!', 'info', 1, '2026-11-01 08:00:00'),
(2, 1, 'Nhắc nhở ngân sách', 'Bạn đã thiết lập thành công hạn mức ngân sách cho tháng 11/2026.', 'success', 0, '2026-11-05 09:30:00')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
