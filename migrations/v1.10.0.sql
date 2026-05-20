-- ===================================================
-- Takoyaki CMS v1.9.0 → v1.10.0 マイグレーション
-- ===================================================
-- ソフトデリート（ゴミ箱）対応: posts.deleted_at を追加
--   mysql -u <user> -p <dbname> < migrations/v1.10.0.sql
-- ===================================================

ALTER TABLE posts
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER sort_order,
    ADD INDEX idx_deleted_at (deleted_at);
