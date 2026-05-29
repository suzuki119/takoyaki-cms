-- ===================================================
-- Takoyaki CMS v1.12.0 → v1.13.0 マイグレーション
-- ===================================================
-- テーマ機構（CSS差し替え方式）: site_settings に active_theme を追加
--   mysql -u <user> -p <dbname> < migrations/v1.13.0.sql
-- ===================================================

INSERT IGNORE INTO site_settings (`key`, `value`) VALUES
  ('active_theme', 'default');
