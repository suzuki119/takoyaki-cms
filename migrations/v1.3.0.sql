-- ===================================================
-- Takoyaki CMS v1.2.0 → v1.3.0 マイグレーション
-- ===================================================
-- 記事に slug / excerpt / published_at を追加
--   mysql -u <user> -p <dbname> < migrations/v1.3.0.sql
-- ===================================================

ALTER TABLE posts
    ADD COLUMN slug         VARCHAR(200) DEFAULT NULL AFTER title,
    ADD COLUMN excerpt      VARCHAR(500) DEFAULT NULL AFTER body,
    ADD COLUMN published_at TIMESTAMP    NULL DEFAULT NULL AFTER status,
    ADD UNIQUE KEY uk_slug (slug),
    ADD INDEX idx_published_at (published_at);

-- 既存の公開済み記事は created_at を published_at として扱う
UPDATE posts
   SET published_at = created_at
 WHERE status = 'published'
   AND published_at IS NULL;
