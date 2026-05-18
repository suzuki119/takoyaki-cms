-- ===================================================
-- Takoyaki CMS v1.0.0 → v1.1.0 マイグレーション
-- ===================================================
-- 既に v1.0.0 を運用している場合、このSQLを実行してください。
-- 新規インストールの場合は schema.sql のみ実行すれば足り、本ファイルは不要です。
--
--   mysql -u <user> -p <dbname> < migrations/v1.1.0.sql
-- ===================================================

-- ログイン試行のレート制限用テーブルを追加
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
