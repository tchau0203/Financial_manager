-- ========================================================
-- FINANCE FEED (giống Locket): bài đăng, kết bạn, reaction
-- An toàn: chạy lại nhiều lần không mất dữ liệu.
-- (API cũng tự tạo các bảng này nếu chưa có - xem config/feed_schema.php)
-- ========================================================
USE `quanlychitieu`;

CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `transaction_id` INT DEFAULT NULL COMMENT 'Giao dịch được ghi kèm (nếu có)',
    `image_path` VARCHAR(255) NOT NULL,
    `caption` VARCHAR(500) DEFAULT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL DEFAULT 'expense',
    `category_id` INT DEFAULT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = bạn bè xem được, 0 = chỉ mình tôi',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_posts_user_created` (`user_id`, `created_at`),
    KEY `idx_posts_created` (`created_at`),
    CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_posts_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_posts_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `friendships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Người gửi lời mời',
    `friend_id` INT NOT NULL COMMENT 'Người nhận lời mời',
    `status` ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_friend_pair` (`user_id`, `friend_id`),
    KEY `idx_friend_to` (`friend_id`, `status`),
    CONSTRAINT `fk_fr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fr_friend` FOREIGN KEY (`friend_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_reactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `emoji` VARCHAR(16) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_reaction` (`post_id`, `user_id`, `emoji`),
    CONSTRAINT `fk_pr_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
