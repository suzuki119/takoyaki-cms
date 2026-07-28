# Changelog

このファイルは Takoyaki CMS の変更履歴を記録します。

形式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に準拠し、
バージョン管理は [Semantic Versioning](https://semver.org/lang/ja/) に従います。

---

## [2.0.0] - 2026-07-28

汎用CMSから **ポートフォリオCMS**（Works + Skills）へ方針転換した大型リリース。
元となった `myportfolio/cms` の作品紹介・スキル紹介の機能を取り戻し、
使われていなかった汎用機能を削除した。

移行には `migrations/v2.0.0.sql` を使用すること（**破壊的変更あり・要バックアップ**）。

### Added
- **スキル管理** — `skills` テーブル + `admin/skill.php` / `admin/skill-edit.php`
  - カテゴリ（`config.php` の `SKILL_CATEGORIES` で定義）ごとにグループ表示
  - スキル名 / アイコン画像 / 期間・習熟度 / 説明 / カテゴリ内での並び替え
  - ヘルパー: `get_skills()` / `get_skills_grouped()` / `get_skill($id)`
- **セクション式の本文** — `post_sections` テーブル
  - 「見出し＋本文」の組を並べて作品詳細を構成
  - 追加・削除・↑↓での並び替えに対応（セクションごとに CKEditor 5）
  - ヘルパー: `get_post_sections()` / `set_post_sections()`
- **作品メタ情報** — `posts` に `period` / `type` / `external_url` / `video_url` を追加
- **動画埋め込み** — `video_embed_url()` が YouTube / Vimeo のURLを iframe 用に変換。
  動画がある場合はサムネイルより優先して表示する
- カテゴリの**複数選択**（`set_post_categories()`）
- カテゴリ・タグの**編集機能**（従来は追加と削除のみ）
- メディアライブラリに「未使用画像の一括削除」
- 公開ページのサンプルを刷新: `samples/works.php` / `single.php` / `skill.php` + `style.css`
- `uploads/.htaccess` — uploads 配下での PHP 実行を禁止（多層防御）
- ヘルパー: `unique_slug()` / `parse_datetime_local()` / `handle_image_upload()` / `delete_upload()` / `upload_url()`

### Changed
- 管理画面ナビを再構成。**カテゴリ管理と監査ログがメニューに無く到達できなかった問題**を解消
  （監査ログ自体は廃止、カテゴリはナビに追加）
- 画像アップロードの検証・保存を `handle_image_upload()` に集約（従来は3ファイルにコピペ）
- 作品フォームを `admin/_post-form.php` に共通化（new / edit の重複を解消）
- `h()` が null と数値を受け付けるように（PHP 8.1 の "Passing null" 警告を解消）
- `config.php` と `config.example.php` のロジックを完全に同一化（従来は example 側にしか
  PHPDoc が無く、二重管理になっていた）。PHP全体では 5,181行 → 4,718行
- パスワード変更時に `session_regenerate_id()` を実行

### Removed
- **テーマ機構** — `themes/`, `admin/themes.php`, `theme_css_tag()` など
- **プラグイン機構・フック・ショートコード** — `plugins/`, `admin/plugins.php`,
  `add_action` / `do_action` / `add_filter` / `apply_filters` / `add_shortcode` / `do_shortcodes`
- **RSS / sitemap / きれいなURL** — `feed.php`, `sitemap.php`, `router.php`, `.htaccess.example`
- **監査ログ** — `audit_logs` テーブル, `admin/logs.php`, `log_action()`
- **DBバックアップ画面** — `admin/backup.php`
- **ゴミ箱（ソフトデリート）** — `posts.deleted_at`。削除は即時・完全削除になった
- **複数ユーザーとロール** — `admin/users.php`, `admin/user-edit.php`, `users.role`,
  `require_admin()`, `user_role()`。管理者1人構成に
- **パスワードリセット** — `forgot-password.php`, `reset-password.php`,
  `password_resets` テーブル, `send_email()`
- **カスタムフィールド** — `post_meta` テーブル（専用カラムの追加により役割が消滅）
- `posts.author_id`（1人構成のため）

### Fixed
- **記事編集画面の本文が未エスケープで、保存型XSSが成立していた問題**（重大）
  本文に `</textarea><script>` を含めると、次に編集画面を開いた人のブラウザで
  任意のJSが実行できた。セクション本文を `h()` でエスケープするよう修正
- **並び替え（↑↓）が一度も機能していなかった問題** — 新規作成時に `sort_order` を
  採番していなかったため全レコードが0で、隣接レコードを見つけられなかった。
  作成時に `MAX(sort_order)+1` を採番し、同値だった場合は自動で振り直すようにした
- **設定を保存しても画面に旧値が表示される問題** — `set_setting()` が
  `get_setting()` の static キャッシュを更新していなかった
- **カテゴリの slug に採番IDが入り、意味のあるURLを作れなかった問題**
- **slug が重複すると保存できなかった問題** — `-2` / `-3` を自動付与するようにした。
  日本語のみのタイトルは `work-{id}` にフォールバックする
- **タイムゾーン未設定で予約公開の判定がずれる問題** — `date_default_timezone_set()` と
  PDO接続時の `SET time_zone` を追加し、PHP と MySQL の判定基準をそろえた
- **`published_at` が未検証のまま SQL に渡っていた問題** — `parse_datetime_local()` で検証
- **作品を削除してもサムネイル画像が `uploads/` に残り続けた問題**
- DB接続失敗時にホスト名・DB名・ユーザー名が画面に出力されていた問題（`error_log()` へ）
- ログインフォームに CSRF トークンが無かった問題
- `login_attempts` の古い行が無限に溜まっていた問題（ログイン成功時に1日以上前を掃除）
- ページネーションが全ページ番号を出力していた問題（前後2ページ + 先頭/末尾に省略表示）
- メディアライブラリの使用状況チェックが画像ごとにクエリを投げていた問題（N+1）

### Security
- `uploads/` での PHP 実行を `.htaccess` で禁止
- アップロードのファイル名を `uniqid()` から `random_bytes()` ベースに変更（推測困難に）
- `delete_upload()` が `realpath()` で uploads 配下かを検証してから削除する
- スキルのカテゴリを `SKILL_CATEGORIES` のホワイトリストで検証
- 作品の保存をトランザクション化（セクション・カテゴリ・タグの中途半端な保存を防止）

---

## [1.13.0] - 2026-05-29

### Added
- **テーマ機構（CSS差し替え方式）** — 公開ページの見た目を切り替え可能に
  - `themes/<name>/style.css` を持つディレクトリをスキャンしてテーマとして認識
  - サンプルテーマ3種: `default`（既存スタイルそのまま）/ `dark`（ダーク基調）/ `newspaper`（セリフ書体）
  - **専用ページ `admin/themes.php`** — カード形式の一覧 + アクティブ表示 + 切替ボタン + プレビューリンク
  - 管理画面ナビに「テーマ」を追加（admin限定）
  - `site_settings.active_theme` に保存
  - 公開ページ（`samples/index.php`, `samples/single.php`, `samples/category.php`, `router.php`）の `<head>` 末尾に
    アクティブテーマの CSS を `<link rel="stylesheet">` で読込（既存のインラインCSSの**後ろ**で上書き）
  - `?v={mtime}` のキャッシュバスティング付き
  - ヘルパー: `get_themes()` / `active_theme()` / `get_theme_meta($name)` / `theme_css_url()` / `theme_css_tag()`
- `themes/<name>/theme.json`（任意） — `name` / `description` / `version` のメタデータ
- `themes/README.md` — テーマ開発ガイド
- `migrations/v1.13.0.sql`

### Security
- `active_theme` の更新は `admin/themes.php` 側で `get_themes()` の戻り値のホワイトリストに含まれる値のみ許可
  （任意のパス文字列をDBに書き込めないようにする）
- アクティブテーマの実体が見つからない場合は自動的に `default` にフォールバック

### Known limitations (v1.13.0時点で見送り)
- **PHPテンプレート単位のテーマ切替** — index.php / single.php まで差し替えるフルテーマ方式は実装範囲が広いため見送り。
  CSS差し替えで足りないカスタマイズは samples/ をコピー＆改造する従来手段で対応してください。

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.13.0.sql
```

既存サイトの見た目はそのままです（`active_theme=default` の `style.css` は空のため）。
管理画面 `admin/settings.php` から `dark` / `newspaper` に切替できます。

---

## [1.12.0] - 2026-05-20

### Added
- **きれいなURL（オプトイン）**
  - `router.php` — フロントコントローラ。`/`, `/post/{slug}`, `/category/{slug}`, `/tag/{slug}` を振り分け
  - `.htaccess.example` — `mod_rewrite` 用の書き換え設定。実ファイルは素通し、存在しないパスのみ router.php へ
  - `mod_rewrite` 非対応環境向けに `router.php?p=/post/slug` フォールバックも対応
- README に「きれいなURL」設定手順とルーティング表を追加

### Fixed
- **`get_post()` の公開判定をSQLの `NOW()` に統一** — 従来は PHP の `time()` で判定しており、
  PHP と MySQL のタイムゾーンが異なる環境で `get_posts()`（SQL判定）と結果が食い違う不具合があった。
  あわせて削除済み（ゴミ箱）除外も SQL 側で行うよう簡素化

### Changed
- README の「データベース構成」「既知の制限」を最新の機能セットに合わせて更新

### Upgrade Guide

```bash
git pull
# きれいなURLを使う場合のみ:
cp .htaccess.example .htaccess
# .htaccess の RewriteBase を設置パスに合わせて編集
```

DBスキーマ変更はありません。`.htaccess` を置かなければ従来どおり動作します。

---

## [1.11.0] - 2026-05-20

### Added
- **メールによるパスワードリセット**
  - `forgot-password.php` — ユーザー名 or メールで申請（存在有無は明かさない）
  - `reset-password.php` — メールのリンクからトークン検証＋新パスワード設定
  - `password_resets` テーブル（トークンは SHA-256 ハッシュで保存、1時間有効、単回使用）
  - `login.php` に「パスワードをお忘れですか？」リンクを追加
  - `send_email()` ヘルパーと `MAIL_FROM` 定数を追加（PHP標準 `mail()` 使用）
- `migrations/v1.11.0.sql`

### Security
- リセットトークンは生値をDBに保存せず SHA-256 ハッシュで保管（DB漏洩時も悪用困難）
- トークンは1時間で失効、使用後は即無効化、同一ユーザーの他トークンも無効化
- 申請時に「ユーザーが存在するか」を画面・レスポンスで区別しない（列挙攻撃対策）

### Note
- メール送信は PHP の `mail()` を使用します。ロリポップ等の共有サーバーでは概ね動作しますが、
  ローカル環境（MAMP等）では実際には送信されないことがあります。
  本番では `MAIL_FROM` を自ドメインのアドレスに設定してください。

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.11.0.sql
```

`config.php` に `MAIL_FROM` 定数を追記してください（`config.example.php` 参照）。

---

## [1.10.0] - 2026-05-20

### Added
- **ゴミ箱（ソフトデリート）** — 記事削除が即時完全削除ではなくゴミ箱行きに変更
  - `posts.deleted_at` カラム追加
  - 記事一覧の「ゴミ箱へ」、ゴミ箱ビュー（`?view=trash`）での「復元」「完全に削除」
  - 一括操作: 通常一覧は「ゴミ箱へ移動」、ゴミ箱では「復元」「完全に削除」
  - ゴミ箱内の件数を記事一覧にバッジ表示
- `migrations/v1.10.0.sql` — v1.9.0 からのアップグレード用SQL

### Changed
- `get_posts()` / `get_post()` は削除済み（ゴミ箱内）の記事を常に除外
- 公開側（samples / feed.php / sitemap.php）にもゴミ箱の記事は出ない
- 監査ログのアクション名: ゴミ箱移動は `post.trash` / `post.bulk_trash`、復元は `post.restore` / `post.bulk_restore`、完全削除は従来どおり `post.delete` / `post.bulk_delete`

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.10.0.sql
```

既存記事は全て「ゴミ箱外（通常）」として扱われます。

---

## [1.9.0] - 2026-05-20

### Added
- **タグシステム** — カテゴリと別軸の自由ラベル
  - `tags` / `post_tags` テーブル追加
  - 記事編集画面でカンマ区切り入力 → 未登録タグは自動作成
  - `admin/tags.php`（一覧・削除、editor 以上）
  - ヘルパー: `get_tags()` / `get_post_tags($post_id)` / `set_post_tags($post_id, $tags)`
- **カスタムフィールド (post_meta)** — 記事ごとの任意 key-value メタデータ
  - `post_meta` テーブル追加（同じ post_id+key を複数持てる配列対応）
  - 記事編集画面で「+ フィールドを追加」ボタンによる動的UI
  - ヘルパー: `get_post_meta($post_id, $key, $single)` / `set_post_meta()` / `delete_post_meta()` / `get_all_post_meta()`
- `samples/single.php` と `preview.php` にタグ表示・カスタムフィールド表示の例を追加
- `migrations/v1.9.0.sql` — v1.8.0 からのアップグレード用SQL

### Changed
- 管理画面ナビ（全ユーザー）に「タグ」を追加

### Known limitations (v1.9.0時点で見送り)
- **REST API** — 認証設計が独立した検討事項のため v1.10.0 で別途
- **コメント機能** — スパム対策・モデレーションUIが別ステップ規模
- **多言語化** — 記事の多言語化はアーキ刷新が必要なため見送り

### Upgrade Guide

```bash
git pull
mysql -u <user> -p <dbname> < migrations/v1.9.0.sql
```

既存記事のタグ・カスタムフィールドは空です。
記事編集画面から自由に追加できます。

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
