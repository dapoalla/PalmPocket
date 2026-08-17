-- PalmPocket database schema (MySQL / MariaDB)
-- Uses InnoDB + utf8mb4 throughout, proper foreign keys and indexes.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    role VARCHAR(60) NOT NULL DEFAULT 'Member',
    username VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#8b5cf6',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('expense','inflow') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    is_loan TINYINT(1) NOT NULL DEFAULT 0,
    category_id INT UNSIGNED NULL,
    purse_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    note TEXT NULL,
    txn_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_txn_date (txn_date),
    KEY idx_txn_category (category_id),
    KEY idx_txn_purse (purse_id),
    KEY idx_txn_user (user_id),
    KEY idx_txn_type (type),
    CONSTRAINT fk_txn_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_txn_purse FOREIGN KEY (purse_id) REFERENCES purses(id) ON DELETE SET NULL,
    CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    category_id INT UNSIGNED PRIMARY KEY,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_budget_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    purchased TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    date_added DATE NOT NULL,
    KEY idx_wish_purchased (purchased)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    currency VARCHAR(10) NOT NULL DEFAULT '$',
    monthly_expense_threshold DECIMAL(14,2) NOT NULL DEFAULT 0,
    low_purse_threshold DECIMAL(14,2) NOT NULL DEFAULT 0,
    alert_email VARCHAR(190) NOT NULL DEFAULT '',
    smtp_host VARCHAR(190) NOT NULL DEFAULT '',
    smtp_user VARCHAR(190) NOT NULL DEFAULT '',
    smtp_pass VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    smtp_secure VARCHAR(10) NOT NULL DEFAULT 'tls',
    smtp_from_email VARCHAR(190) NOT NULL DEFAULT '',
    smtp_from_name VARCHAR(190) NOT NULL DEFAULT 'PalmPocket',
    threshold_emails_enabled TINYINT(1) NOT NULL DEFAULT 0,
    theme VARCHAR(10) NOT NULL DEFAULT 'dark',
    quotes TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(40) NOT NULL,
    ref_key VARCHAR(120) NOT NULL,
    sent_on DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification (kind, ref_key, sent_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
