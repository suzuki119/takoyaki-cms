-- ===================================================
-- Takoyaki CMS v1.13.0 → v2.0.0
-- 汎用CMS → ポートフォリオCMS（Works + Skills）への転換
-- ===================================================
--
-- ⚠️ 破壊的変更を含みます。実行前に必ずバックアップを取ってください。
--
--     mysqldump -u <user> -p <dbname> > backup-before-v2.sql
--
-- 実行:
--     mysql -u <user> -p <dbname> < migrations/v2.0.0.sql
--
-- 変更の概要:
--   - posts.body       → post_sections へ移行（自動で1セクションに変換）
--   - posts に作品メタ（period / type / external_url / video_url）を追加
--   - skills テーブルを新設
--   - ゴミ箱 / 監査ログ / パスワードリセット / カスタムフィールド / ロールを廃止
--   - sort_order が全て0の問題を解消（id順に採番し直す）
-- ===================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------
-- 1. post_sections を新設し、既存の posts.body を移行する
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS post_sections (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT          NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    title      VARCHAR(255) DEFAULT NULL,
    body       LONGTEXT     DEFAULT NULL,
    INDEX idx_post_order (post_id, sort_order),
    CONSTRAINT fk_ps_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 既存の本文を「見出しなしの1セクション」として移行
-- （ゴミ箱の記事も含めて移行し、あとで posts ごと消えるものは CASCADE で消える）
INSERT INTO post_sections (post_id, sort_order, title, body)
SELECT id, 0, NULL, body
  FROM posts
 WHERE body IS NOT NULL AND body <> '';

-- ---------------------------------------------------
-- 2. ゴミ箱の中身を完全削除してから deleted_at を落とす
-- ---------------------------------------------------
DELETE FROM posts WHERE deleted_at IS NOT NULL;

ALTER TABLE posts DROP INDEX idx_deleted_at;
ALTER TABLE posts DROP COLUMN deleted_at;

-- ---------------------------------------------------
-- 3. posts に作品メタを追加し、不要カラムを落とす
-- ---------------------------------------------------
ALTER TABLE posts
    ADD COLUMN period       VARCHAR(100)  DEFAULT NULL AFTER published_at,
    ADD COLUMN type         VARCHAR(100)  DEFAULT NULL AFTER period,
    ADD COLUMN external_url VARCHAR(2083) DEFAULT NULL AFTER type,
    ADD COLUMN video_url    VARCHAR(2083) DEFAULT NULL AFTER external_url;

ALTER TABLE posts DROP FOREIGN KEY fk_posts_author;
ALTER TABLE posts DROP COLUMN author_id;
ALTER TABLE posts DROP COLUMN body;

-- ---------------------------------------------------
-- 4. sort_order の採番し直し（v1.x では全て0で並び替えが機能しなかった）
-- ---------------------------------------------------
SET @row = 0;
UPDATE posts SET sort_order = (@row := @row + 1) ORDER BY id ASC;

-- ---------------------------------------------------
-- 5. categories.slug の正常化
--    v1.x は slug に採番IDの文字列を入れていたため、数字のままのものを
--    name ベースに直す。英数字にできない名前（日本語のみ）は cat-{id} にする。
-- ---------------------------------------------------
UPDATE categories
   SET slug = CONCAT('cat-', id)
 WHERE slug REGEXP '^[0-9]+$' OR slug = '';

ALTER TABLE categories ADD UNIQUE KEY uk_category_slug (slug);

-- ---------------------------------------------------
-- 6. skills テーブルを新設
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS skills (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    category   VARCHAR(50)  NOT NULL DEFAULT 'その他',
    title      VARCHAR(100) NOT NULL,
    image      VARCHAR(255) DEFAULT NULL,
    period     VARCHAR(100) DEFAULT NULL,
    body       TEXT         DEFAULT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_order (category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 7. 廃止したテーブル・カラムの削除
-- ---------------------------------------------------
DROP TABLE IF EXISTS post_meta;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS password_resets;

ALTER TABLE users DROP COLUMN role;

-- ---------------------------------------------------
-- 8. 廃止した設定キーの掃除
-- ---------------------------------------------------
DELETE FROM site_settings WHERE `key` IN ('active_theme', 'enabled_plugins');

SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================
-- 実行後の確認
-- ===================================================
--   SELECT COUNT(*) FROM post_sections;   -- 本文が移行されているか
--   SELECT id, sort_order FROM posts ORDER BY sort_order LIMIT 5;  -- 連番になっているか
--   SHOW COLUMNS FROM posts LIKE 'video_url';                      -- 作品メタが増えているか
--
-- ⚠️ 管理者が複数登録されていた場合、role を落としただけでは全員が管理者になります。
--    不要なユーザーは事前に削除しておいてください:
--      SELECT id, username FROM users;
--      DELETE FROM users WHERE id <> <残すID>;
