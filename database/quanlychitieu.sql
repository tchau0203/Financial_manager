CREATE DATABASE IF NOT EXISTS quanlychitieu
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE quanlychitieu;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'VND',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    icon VARCHAR(100) NULL,
    color CHAR(7) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_categories_owner_type (user_id, type),
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transactions_user_date (user_id, transaction_date),
    KEY idx_transactions_category (category_id),
    CONSTRAINT chk_transactions_amount CHECK (amount > 0),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    amount DECIMAL(15, 2) NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    alert_threshold TINYINT UNSIGNED NOT NULL DEFAULT 80,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_budgets_owner_period (user_id, year, month),
    CONSTRAINT chk_budgets_amount CHECK (amount > 0),
    CONSTRAINT chk_budgets_month CHECK (month BETWEEN 1 AND 12),
    CONSTRAINT chk_budgets_threshold CHECK (alert_threshold BETWEEN 1 AND 100),
    CONSTRAINT fk_budgets_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('budget_alert', 'system') NOT NULL DEFAULT 'system',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_read (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (user_id, name, type, icon, color)
SELECT NULL, defaults.name, defaults.type, defaults.icon, defaults.color
FROM (
    SELECT 'Lương' AS name, 'income' AS type, 'fa-solid fa-money-bill-wave' AS icon, '#10b981' AS color
    UNION ALL SELECT 'Thưởng', 'income', 'fa-solid fa-gift', '#22c55e'
    UNION ALL SELECT 'Đầu tư', 'income', 'fa-solid fa-chart-line', '#14b8a6'
    UNION ALL SELECT 'Ăn uống', 'expense', 'fa-solid fa-utensils', '#f97316'
    UNION ALL SELECT 'Di chuyển', 'expense', 'fa-solid fa-car', '#3b82f6'
    UNION ALL SELECT 'Nhà ở', 'expense', 'fa-solid fa-house', '#8b5cf6'
    UNION ALL SELECT 'Mua sắm', 'expense', 'fa-solid fa-bag-shopping', '#ec4899'
    UNION ALL SELECT 'Giải trí', 'expense', 'fa-solid fa-film', '#eab308'
) AS defaults
WHERE NOT EXISTS (
    SELECT 1 FROM categories existing
    WHERE existing.user_id IS NULL
      AND existing.name = defaults.name
      AND existing.type = defaults.type
);
