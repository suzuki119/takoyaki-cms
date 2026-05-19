# Changelog

このファイルは Takoyaki CMS の変更履歴を記録します。

形式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に準拠し、
バージョン管理は [Semantic Versioning](https://semver.org/lang/ja/) に従います。

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
