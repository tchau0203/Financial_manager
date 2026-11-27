<?php
/**
 * Finance Feed - dùng chung cho api/posts/* và api/friends/*
 * Tự tạo 3 bảng (nếu chưa có) để thành viên nhóm không cần import tay.
 */
require_once __DIR__ . '/db.php';

const FEED_EMOJIS = ['👍', '💰', '😮', '🔥'];

function ensureFeedTables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `posts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `transaction_id` INT DEFAULT NULL,
        `image_path` VARCHAR(255) NOT NULL,
        `caption` VARCHAR(500) DEFAULT NULL,
        `amount` DECIMAL(15,2) NOT NULL,
        `type` ENUM('income','expense') NOT NULL DEFAULT 'expense',
        `category_id` INT DEFAULT NULL,
        `is_public` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_posts_user_created` (`user_id`,`created_at`),
        KEY `idx_posts_created` (`created_at`),
        CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_posts_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_posts_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `friendships` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `friend_id` INT NOT NULL,
        `status` ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_friend_pair` (`user_id`,`friend_id`),
        KEY `idx_friend_to` (`friend_id`,`status`),
        CONSTRAINT `fk_fr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_fr_friend` FOREIGN KEY (`friend_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `post_reactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `post_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `emoji` VARCHAR(16) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_reaction` (`post_id`,`user_id`,`emoji`),
        CONSTRAINT `fk_pr_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Khởi tạo chung cho mọi API feed: header JSON, auth, PDO, bảng. Trả về [userId, pdo]. */
function feedBootstrap(string $method): array
{
    ini_set('display_errors', '0');
    header('Access-Control-Allow-Methods: ' . $method . ', OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        jsonResponse(['success' => false, 'message' => "Chỉ hỗ trợ phương thức $method"], 405);
    }
    $userId = requireAuth();
    $pdo = getDbConnection();
    try {
        ensureFeedTables($pdo);
    } catch (Throwable $e) {
        error_log('[feed-schema] ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Không thể khởi tạo bảng Feed. Hãy chạy database/migration_feed.sql'], 500);
    }
    return [$userId, $pdo];
}

/** Hai user đã là bạn (accepted) hay chưa */
function areFriends(PDO $pdo, int $a, int $b): bool
{
    $st = $pdo->prepare("SELECT 1 FROM friendships WHERE status = 'accepted'
        AND ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) LIMIT 1");
    $st->execute([$a, $b, $b, $a]);
    return (bool)$st->fetchColumn();
}

/** User có quyền xem bài này không: chủ bài, hoặc bài public của bạn bè */
function canViewPost(PDO $pdo, int $userId, int $postId): bool
{
    $st = $pdo->prepare('SELECT user_id, is_public FROM posts WHERE id = ?');
    $st->execute([$postId]);
    $p = $st->fetch();
    if (!$p) return false;
    if ((int)$p['user_id'] === $userId) return true;
    return (int)$p['is_public'] === 1 && areFriends($pdo, $userId, (int)$p['user_id']);
}
