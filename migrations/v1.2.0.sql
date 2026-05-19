-- ===================================================
-- Takoyaki CMS v1.1.0 → v1.2.0 マイグレーション
-- ===================================================
-- アカウント機能（複数管理者・ロール）の追加
--   mysql -u <user> -p <dbname> < migrations/v1.2.0.sql
-- ===================================================

-- users テーブルに role カラムを追加
ALTER TABLE users
    ADD COLUMN role ENUM('admin','editor') NOT NULL DEFAULT 'editor' AFTER email;

-- 既存ユーザーは全員 admin に昇格（v1.2.0 以前は権限分離が無かったため）
UPDATE users SET role = 'admin';
