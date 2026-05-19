# Changelog

このファイルは Takoyaki CMS の変更履歴を記録します。

形式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に準拠し、
バージョン管理は [Semantic Versioning](https://semver.org/lang/ja/) に従います。

---

## [1.8.0] - 2026-05-20

### Added
- **アクション/フィルターフック** — WordPress風の拡張ポイント
  - `add_action($action, $callback, $priority)` / `do_action($action, ...$args)`
  - `add_filter($filter, $callback, $priority)` / `apply_filters($filter, $value, ...$args)`
  - 発火点: `post.save`, `post.delete`, `login.success`
- **ショートコード** — 本文中のタグを動的展開
  - `add_shortcode($tag, $callback)` / `do_shortcodes($content)`
  - `[tag attr="value"]` 形式に対応
- **プラグイン機構** — `plugins/` ディレクトリの動的読み込み
  - `plugins/<name>/index.php` を有効化済みのものだけ自動require
  - メタデータは `plugin.json`
  - `get_enabled_plugins()` / `scan_plugins()` / `load_plugins()` ヘルパー
- **`admin/plugins.php`**（admin限定）— プラグイン一覧・有効/無効をチェックボックスで切替
- **サンプルプラグイン `plugins/hello-world/`** — ショートコード・フィルター・アクションを全種類使う実例
- `plugins/README.md` — プラグイン開発ガイド
- `samples/single.php` を `apply_filters('the_content')` / `apply_filters('the_title')` / `do_shortcodes()` 利用例に更新

### Changed
- 管理画面ナビ（admin限定）に「プラグイン」を追加

### Known limitations (v1.8.0時点で見送り)
- **テーマ機構** — `samples/` で代替済み。テーマ切替UIは URLルーティング前提で embeddable 設計と相性が悪いため見送り

### Upgrade Guide

```bash
git pull
```

DBスキーマ変更はありません。
`plugins/` ディレクトリは新規追加されますが、デフォルトでは何も有効化されていません。
管理画面の「プラグイン」メニューで有効化できます。

---

## [1.7.0] - 2026-05-20

### Added
- **サイト設定 (`site_settings` テーブル)** — 管理画面 `admin/settings.php` でサイト名・説明・フッターテキスト・表示件数を編集
  - `get_setting($key, $default)` / `set_setting($key, $value)` ヘルパー追加
- **監査ログ (`audit_logs` テーブル)** — 全操作を `admin/logs.php` で閲覧（ページネーション付き）
  - `log_action($action, $target_type, $target_id, $details)` ヘルパー追加
  - 記録対象: 記事 create/update/delete/bulk_delete、カテゴリ create/delete、ユーザー create/delete/change_role/reset_password/change_password、メディア delete、設定 update、バックアップ download
- **DBバックアップ (`admin/backup.php`)** — admin限定。PHP製のSQLダンプ（mysqldump不要、共有サーバー対応）
  - `?action=download&_csrf=...` でファイル名付きダウンロード（`Content-Disposition: attachment`）
  - 全テーブルの CREATE TABLE + INSERT を含む
- `samples/index.php` を `get_setting()` 利用例に更新（サイト名・説明・フッター・表示件数を設定から取得）
- `migrations/v1.7.0.sql` — v1.6.0 からのアップグレード用SQL

### Changed
- 管理画面ナビ（admin限定）に「設定 / ログ / バックアップ」を追加

### Known limitations (v1.7.0時点で見送り)
- **エクスポート/インポート（JSON）** — 画像参照・カテゴリマッピングなどエッジケースが多いため見送り。バックアップ機能で DB レベルのポータビリティは確保済み

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.7.0.sql
```

既存環境では `site_settings` に初期値が挿入されます（既存値があれば維持）。
`admin/settings.php` から site_name 等を編集してください。

---

## [1.6.0] - 2026-05-20

### Added
- **共通レイアウト** — `admin/_layout.php` の `admin_header($title, $extra_head)` / `admin_footer($extra_body)` で全管理画面のヘッダー・ナビ・フッターを一元化
- **共通CSS** — `admin/admin.css` で統一されたスタイル（カード、テーブル、ボタン、フォーム、バッジ、ページネーション、メディアグリッド等）
- **記事検索** — タイトル・本文の LIKE 検索（`?q=keyword`）
- **ページネーション** — 20件/ページ（`POSTS_PER_PAGE` で調整可）。検索結果にも適用
- **一括削除** — チェックボックス + 一括操作セレクトで複数記事の同時削除
- 各記事行に「プレビュー」リンクを追加（編集中の記事を新規タブで確認）

### Changed
- 全管理画面（index / post-new / post-edit / categories / media / users / user-edit / account）をレイアウトヘルパーに移行
- 各ファイル内のインライン `<style>` を削減（CKEditor 用の最小設定のみ残置）
- 管理画面トップナビをファイル上部に配置（旧: ボタンが各ページ内に散在）

### Known limitations (v1.6.0時点で見送り)
- **記事の複製機能** — v1.6.x で別途検討
- **CSSフレームワーク導入** — カスタム共通CSSで十分のため見送り

### Upgrade Guide

```bash
git pull
```

DBスキーマ変更はありません。
v1.5.0 までの管理画面と見た目が変わります。設定ファイルの変更は不要です。

---

## [1.5.0] - 2026-05-19

### Added
- **フロントエンド向けヘルパー関数** を `config.php` に追加:
  - `get_posts(array $opts)` — 公開中の記事一覧。カテゴリ絞り込み、並び順、limit/offset、`include_drafts` をサポート
  - `get_post($id_or_slug, bool $include_drafts = false)` — 1件の記事を ID または slug で取得
  - `get_categories()` — 全カテゴリ
  - `get_category($id_or_slug)` — 1件のカテゴリを ID または slug で取得
  - `get_post_categories(int $post_id)` — 記事に紐付くカテゴリ
  - `post_thumb_url(?string $filename)` — サムネイル変種URL（無ければ元画像URL）
- **`samples/` サンプルテンプレート** — そのまま動作する公開ページの実装例:
  - `samples/index.php` — 最新10件の記事一覧
  - `samples/single.php` — 記事詳細（`?id=N` または `?slug=...`）
  - `samples/category.php` — カテゴリ別一覧
  - `samples/README.md` — 使い方ドキュメント
- **`feed.php`** — RSS 2.0 形式のフィード（最新50件）。Content-Type: `application/rss+xml`
- **`sitemap.php`** — XMLサイトマップ。検索エンジン登録用

### Known limitations (v1.5.0時点で見送り)
- **URLルーティング（`.htaccess` 含む）** — CMSが「embeddable」設計で、利用者のサイトURL構造に依存するため見送り。サンプルの `?id=N` / `?slug=...` パターンを採用

### Upgrade Guide

```bash
git pull
```

DBスキーマ変更はありません。
新規ファイル（`samples/`, `feed.php`, `sitemap.php`）は任意で利用してください。
`feed.php` / `sitemap.php` の冒頭にある `$post_url` コールバックを自分のサイトのURL構造に合わせて編集することを推奨します。

---

## [1.4.0] - 2026-05-19

### Added
- **画像の自動リサイズ** — アップロード時に最大幅 `IMAGE_MAX_WIDTH`（既定 1600px）にリサイズ。アスペクト比保持、PNG/GIF の透過保持
- **サムネイル変種の自動生成** — `{basename}-thumb.{ext}` として `IMAGE_THUMB_WIDTH`（既定 300px）のサムネイル変種を生成
- **メディアライブラリ** — `admin/media.php`（admin限定）でアップロード済み画像の一覧・寸法・サイズ・使用状況確認・削除が可能
- **画像処理ヘルパー** — `resize_image()` / `thumb_filename()` を `config.php` に追加
- **アップロードサイズの設定化** — `MAX_UPLOAD_SIZE`（既定 5MB）として設定ファイルから変更可能

### Changed
- アップロード上限を 2MB → 5MB に拡大（ファイル内 `MAX_UPLOAD_SIZE` で調整可）
- 既存サムネイルの差し替え時に古い `-thumb` 変種も一緒に削除
- `admin/index.php` のナビゲーションに「メディア」リンクを追加（admin限定）

### Known limitations (v1.4.0時点で見送り、将来検討)
- **WebP自動変換** — 既存記事の参照互換性を保ちながら導入する設計が必要なため見送り
- **alt属性入力UI** — 画像メタデータ用のDB構造が必要なため見送り

### Upgrade Guide

```bash
git pull
```

DBスキーマの変更はありません（マイグレーション不要）。
既存の画像は元のままで、新しくアップロードした画像から自動リサイズと thumb 生成が適用されます。

---

## [1.3.0] - 2026-05-19

### Added
- **`posts.slug`** — URL用識別子のUNIQUEカラム。タイトル入力から自動生成も対応
- **`posts.excerpt`** — 一覧表示用の抜粋フィールド（VARCHAR(500)）
- **`posts.published_at`** — 公開日時を `created_at` と分離（TIMESTAMP NULL）
- **予約公開** — `published_at` に未来日付を指定すると、その時刻まで公開されない（cron不要、クエリ側で判定）
- **記事プレビュー画面** — `preview.php` でログイン中ユーザーが下書きや予約記事を実機表示で確認可能
- **`sluggify()` ヘルパー** — 英数字＋ハイフン形式へ正規化（日本語のみは空文字を返す）
- `migrations/v1.3.0.sql` — v1.2.0 からのアップグレード用SQL

### Changed
- `admin/index.php` の公開状態表示を「公開中 / 予約公開 / 下書き」の3区分に分割し、予約公開には日時を併記
- 記事一覧から各記事のプレビューリンクへ遷移可能
- README のフロントエンド例コードを `published_at` 条件付きクエリに更新

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.3.0.sql
```

既存の公開済み記事は `published_at = created_at` で自動設定されます。
**フロントエンド側のクエリ** を `WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW())` に更新することを推奨。

---

## [1.2.0] - 2026-05-19

### Added
- **複数管理者管理** — `admin/users.php` でユーザー一覧・追加・削除・ロール変更が可能
- **ロール（admin / editor）** — `users.role` カラムを追加。admin は全機能、editor は記事・カテゴリ・自分のアカウントのみ操作可
- **権限ヘルパー** — `user_role()` / `require_admin()` を `config.php` に追加
- **パスワードリセット（admin → 他ユーザー）** — `admin/user-edit.php` で管理者が他ユーザーのパスワードを再設定
- **自分のアカウント設定** — `admin/account.php` で自分のパスワード・メールを変更（現在のパスワード確認必須）
- **自己ロックアウト防止** — 最後の admin の削除/降格、自分自身の削除はブロック
- `migrations/v1.2.0.sql` — v1.1.0 からのアップグレード用SQL

### Changed
- `setup.php` の初回登録ユーザーは `role='admin'` で作成
- ログイン成功時に `$_SESSION['role']` をセット
- `admin/index.php` のナビゲーションに「ユーザー管理」（admin限定）と「アカウント設定」を追加

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.2.0.sql
```

既存ユーザーは全員 `admin` に昇格します（v1.2.0 以前は権限分離が無かったため）。
必要に応じて `admin/users.php` から `editor` に変更してください。

---

## [1.1.0] - 2026-05-19

### Added
- **CSRF対策** — すべての管理画面のPOSTフォームにトークン埋め込みと検証を追加。`csrf_token()` / `csrf_field()` / `verify_csrf()` ヘルパーを `config.php` に追加
- **セッションCookieのセキュア化** — `httponly`, `samesite=Lax`、HTTPS時 `secure` を付与。`start_session()` ヘルパーで一元管理
- **ログイン試行回数制限** — 同一IPから15分以内に5回失敗すると一時ブロック。`login_attempts` テーブルを追加
- **パスワード強度バリデーション強化** — 8文字以上に加えて英字と数字の両方を必須化（`setup.php`）
- **アップロードファイルのMIME検証** — 拡張子チェックに加えて `mime_content_type()` で実際のMIMEを確認（`post-new.php` / `post-edit.php` / `upload-image.php`）
- `migrations/v1.1.0.sql` — v1.0.0 からのアップグレード用SQL

### Changed
- セッション開始ロジックを `start_session()` ヘルパーに集約

### Upgrade Guide

v1.0.0 から v1.1.0 にアップグレードする場合：

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.1.0.sql
```

設定ファイル（`config.php`）の変更は不要です。
ただし新規インストール時の参考として `config.example.php` を確認してください。

---

## [1.0.0] - 2026-05-19

### Added
- 初回リリース
- 管理者認証（セッションベース）
- 記事の作成・編集・削除、サムネイル画像、CKEditor 5 によるリッチテキスト編集
- カテゴリ管理、記事との多対多紐付け
- 並び替え（↑↓ボタンによる sort_order 入れ替え）
- 本文内画像アップロード（CKEditor連携）
- `setup.php` による初回管理者登録
