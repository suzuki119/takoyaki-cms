-- ===================================================
-- Takoyaki CMS データベーススキーマ
-- ===================================================
-- 利用方法:
--   1. phpMyAdmin等でデータベースを作成
--   2. mysql -u <user> -p <dbname> < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================
-- users : 管理者ユーザー
-- ===================================================
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    email      VARCHAR(255) DEFAULT NULL,
    role       ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- categories : カテゴリ
-- ===================================================
CREATE TABLE IF NOT EXISTS categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- posts : 記事
-- ===================================================
CREATE TABLE IF NOT EXISTS posts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    slug         VARCHAR(200) DEFAULT NULL,
    body         LONGTEXT     DEFAULT NULL,
    excerpt      VARCHAR(500) DEFAULT NULL,
    thumbnail    VARCHAR(255) DEFAULT NULL,
    status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP    NULL DEFAULT NULL,
    author_id    INT          DEFAULT NULL,
    sort_order   INT          NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at),
    INDEX idx_sort_order (sort_order),
    CONSTRAINT fk_posts_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- post_categories : 記事とカテゴリの多対多
-- ===================================================
CREATE TABLE IF NOT EXISTS post_categories (
    post_id     INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (post_id, category_id),
    CONSTRAINT fk_pc_post
        FOREIGN KEY (post_id)     REFERENCES posts(id)      ON DELETE CASCADE,
    CONSTRAINT fk_pc_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- login_attempts : ログイン試行のレート制限用
-- 一定時間内の失敗回数で簡易的にブロックする
-- ===================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- site_settings : サイト全体の key-value 設定
-- 管理画面 admin/settings.php から編集する
-- ===================================================
CREATE TABLE IF NOT EXISTS site_settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期値
INSERT INTO site_settings (`key`, `value`) VALUES
    ('site_name',        'Takoyaki CMS Site'),
    ('site_description', 'Powered by Takoyaki CMS'),
    ('footer_text',      ''),
    ('posts_per_page',   '10')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- ===================================================
-- audit_logs : 操作監査ログ
-- 記事・カテゴリ・ユーザー・設定の変更を記録する
-- username をスナップショットすることでユーザー削除後も追跡可能
-- ===================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          DEFAULT NULL,
    username    VARCHAR(50)  DEFAULT NULL,
    action      VARCHAR(50)  NOT NULL,
    target_type VARCHAR(50)  DEFAULT NULL,
    target_id   INT          DEFAULT NULL,
    details     TEXT         DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
