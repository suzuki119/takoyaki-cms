-- ===================================================
-- Takoyaki CMS v2.0.0 データベーススキーマ
-- ポートフォリオCMS（Works + Skills）
-- ===================================================
-- 利用方法:
--   1. phpMyAdmin等でデータベースを作成（utf8mb4）
--   2. mysql -u <user> -p <dbname> < schema.sql
--
-- 既存の v1.x から移行する場合は migrations/v2.0.0.sql を使うこと。

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================
-- users : 管理者ユーザー
-- v2.0.0 から管理者1人構成（role カラムは廃止）
-- ===================================================
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    email      VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- categories : 作品のカテゴリ（works のフィルターに使う）
-- ===================================================
CREATE TABLE IF NOT EXISTS categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- posts : 作品（Works）
-- 本文は post_sections で別管理する（1作品 : 多セクション）
-- ===================================================
CREATE TABLE IF NOT EXISTS posts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255)  NOT NULL,
    slug         VARCHAR(200)  DEFAULT NULL,
    excerpt      VARCHAR(500)  DEFAULT NULL,
    thumbnail    VARCHAR(255)  DEFAULT NULL,
    status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP     NULL DEFAULT NULL,
    -- --- 作品メタ（v2.0.0 で復活）---
    period       VARCHAR(100)  DEFAULT NULL,  -- 制作期間 例: 2025.06 – 08
    type         VARCHAR(100)  DEFAULT NULL,  -- 種別     例: 個人制作 / チーム制作
    external_url VARCHAR(2083) DEFAULT NULL,  -- 作品への外部リンク
    video_url    VARCHAR(2083) DEFAULT NULL,  -- YouTube / Vimeo のURL
    -- --------------------------------
    sort_order   INT           NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- post_sections : 作品詳細の本文セクション（1対多）
-- 「見出し + 本文」の繰り返しで作品ページを構成する
-- ===================================================
CREATE TABLE IF NOT EXISTS post_sections (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT          NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    title      VARCHAR(255) DEFAULT NULL,
    body       LONGTEXT     DEFAULT NULL,
    INDEX idx_post_order (post_id, sort_order),
    CONSTRAINT fk_ps_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- post_categories : 作品とカテゴリの多対多
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
-- tags : 使用技術タグ（例: PHP, SCSS, Three.js）
-- ===================================================
CREATE TABLE IF NOT EXISTS tags (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_tag_name (name),
    UNIQUE KEY uk_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- post_tags : 作品とタグの多対多
-- ===================================================
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_pt_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_tag  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- skills : スキル紹介（v2.0.0 で新設）
-- category ごとにグルーピングして表示する
-- ===================================================
CREATE TABLE IF NOT EXISTS skills (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    category   VARCHAR(50)  NOT NULL DEFAULT 'その他',  -- プログラミング / デザイン / その他
    title      VARCHAR(100) NOT NULL,                   -- 例: PHP
    image      VARCHAR(255) DEFAULT NULL,               -- uploads 内のアイコン画像ファイル名
    period     VARCHAR(100) DEFAULT NULL,               -- 例: 使用歴2年
    body       TEXT         DEFAULT NULL,               -- 説明文
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_order (category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- site_settings : サイト全体の key-value 設定
-- ===================================================
CREATE TABLE IF NOT EXISTS site_settings (
    `key`      VARCHAR(100) PRIMARY KEY,
    `value`    TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (`key`, `value`) VALUES
    ('site_name',        'My Portfolio'),
    ('site_description', 'Powered by Takoyaki CMS'),
    ('footer_text',      ''),
    ('posts_per_page',   '12')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- ===================================================
-- login_attempts : ログイン試行のレート制限用
-- ===================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
