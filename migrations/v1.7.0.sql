-- ===================================================
-- Takoyaki CMS v1.6.0 → v1.7.0 マイグレーション
-- ===================================================
-- 運用機能（設定・監査ログ・バックアップ）の追加
--   mysql -u <user> -p <dbname> < migrations/v1.7.0.sql
-- ===================================================

-- サイト設定 key-value テーブル
CREATE TABLE IF NOT EXISTS site_settings (
    `key`      VARCHAR(100) PRIMARY KEY,
    `value`    TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期値（既存値があれば維持）
INSERT INTO site_settings (`key`, `value`) VALUES
    ('site_name',        'Takoyaki CMS Site'),
    ('site_description', 'Powered by Takoyaki CMS'),
    ('footer_text',      ''),
    ('posts_per_page',   '10')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- 監査ログ
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
