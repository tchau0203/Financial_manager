-- Migration Sprint 4: Bổ sung avatar_url cho người dùng (Google avatar & tải ảnh đại diện tùy chỉnh)
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `avatar_url` VARCHAR(500) DEFAULT NULL AFTER `currency`;
