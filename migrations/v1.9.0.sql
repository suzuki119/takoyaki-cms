-- ===================================================
-- Takoyaki CMS v1.8.0 → v1.9.0 マイグレーション
-- ===================================================
-- タグシステムとカスタムフィールド（post_meta）の追加
--   mysql -u <user> -p <dbname> < migrations/v1.9.0.sql
-- ===================================================

-- タグ
CREATE TABLE IF NOT EXISTS tags (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_tag_name (name),
    UNIQUE KEY uk_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 記事とタグの多対多
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_pt_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_tag  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- カスタムフィールド
CREATE TABLE IF NOT EXISTS post_meta (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT          NOT NULL,
    `key`   VARCHAR(100) NOT NULL,
    `value` TEXT,
    INDEX idx_post_key (post_id, `key`),
    CONSTRAINT fk_pm_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
