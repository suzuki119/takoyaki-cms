# Changelog

このファイルは Takoyaki CMS の変更履歴を記録します。

形式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に準拠し、
バージョン管理は [Semantic Versioning](https://semver.org/lang/ja/) に従います。

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
