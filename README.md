# Takoyaki CMS

PHP + MySQL で動作する、シンプルな自作CMSです。
ブログや作品紹介サイトなど、記事＋カテゴリで成り立つ小規模サイトを想定しています。

「PHPを学んだ人が読める / 改造できる」サイズ感を大切にしています。
学習用途・改造用途を歓迎します。

---

## 特徴

- PHP + MySQL のみ（依存ライブラリは CKEditor 5 のみ、CDN経由）
- 記事の作成・編集・削除、サムネイル画像、CKEditor 5 によるリッチテキスト編集
- 記事の slug / 抜粋 / 予約公開 / プレビュー
- カテゴリ管理、記事との多対多紐付け
- 画像の自動リサイズ・サムネイル変種生成、メディアライブラリ
- 複数管理者（admin / editor ロール）、CSRF対策、ログイン試行回数制限
- フロントエンド向けヘルパー関数 + サンプルテンプレート、RSS / sitemap.xml
- 並び替え（↑↓ボタンで `sort_order` 入れ替え）

---

## 動作要件

- PHP 7.4 以上（PHP 8 推奨）
- MySQL 5.7 以上 または MariaDB 10.3 以上
- Apache（mod_rewrite は不要）
- ローカル開発環境: MAMP / XAMPP / Laragon など

---

## インストール手順

### 1. ファイルを配置

サーバーのドキュメントルート配下に clone してください。

```bash
git clone https://github.com/<user>/takoyaki-cms.git
cd takoyaki-cms
```

### 2. データベースを作成

phpMyAdmin やコマンドラインで空のデータベースを作成します（文字コード `utf8mb4`）。

```sql
CREATE DATABASE my_cms DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. スキーマをインポート

```bash
mysql -u <user> -p my_cms < schema.sql
```

### 4. 設定ファイルを作成

`config.example.php` をコピーして `config.php` を作成し、自分の環境に合わせて編集します。

```bash
cp config.example.php config.php
```

編集する項目：
- `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`
- `SITE_URL`（このCMSを設置したURL、末尾スラッシュなし）

### 5. アップロードディレクトリの権限設定

```bash
chmod 755 uploads
```

Webサーバー（Apache等）から書き込めるようにします。

### 6. 管理者ユーザーを登録

ブラウザで `setup.php` にアクセスし、フォームから管理者を登録します。

```
http://your-site/takoyaki-cms/setup.php
```

### 7. setup.php を削除する（重要）

セキュリティ上、必ず削除してください。
残しておくと、第三者が新しい管理者を上書き登録できる可能性があります。

```bash
rm setup.php
```

### 8. 管理画面にログイン

```
http://your-site/takoyaki-cms/login.php
```

---

## ディレクトリ構成

```
takoyaki-cms/
├── README.md
├── CHANGELOG.md
├── LICENSE
├── config.example.php   # 設定ファイルのテンプレート
├── schema.sql           # DBスキーマ（新規インストール用）
├── migrations/          # バージョンアップ用SQL
├── setup.php            # 管理者初回登録（使用後に削除）
├── login.php            # ログイン画面
├── logout.php           # ログアウト処理
├── preview.php          # 記事プレビュー（ログイン必須）
├── feed.php             # RSS 2.0 フィード
├── sitemap.php          # sitemap.xml
├── admin/
│   ├── index.php        # 記事一覧
│   ├── post-new.php     # 記事新規作成
│   ├── post-edit.php    # 記事編集
│   ├── categories.php   # カテゴリ管理
│   ├── media.php        # メディアライブラリ（admin限定）
│   ├── users.php        # ユーザー管理（admin限定）
│   ├── user-edit.php    # ユーザー編集（admin限定）
│   ├── account.php      # 自分のアカウント設定
│   └── upload-image.php # 本文画像アップロード（CKEditor連携）
├── samples/             # 公開ページのサンプルテンプレート
│   ├── index.php        # 記事一覧
│   ├── single.php       # 記事詳細
│   └── category.php     # カテゴリ別一覧
└── uploads/             # 画像保存先（要書き込み権限）
```

---

## データベース構成

| テーブル | 役割 |
|---------|------|
| `users` | 管理者ユーザー（role: admin / editor） |
| `posts` | 記事（タイトル / slug / 本文 / 抜粋 / サムネイル / ステータス / 公開日時 / 並び順） |
| `categories` | カテゴリ |
| `post_categories` | 記事とカテゴリの多対多 |
| `login_attempts` | ログイン試行回数（レート制限用） |

詳細は `schema.sql` を参照してください。

---

## フロントエンドについて

本CMSは **管理画面 + フロントエンド向けヘルパー** を提供します。実際の見た目は利用者が自由にデザイン・実装する設計です。

### サンプルテンプレート

`samples/` ディレクトリにそのまま動くサンプルがあります（コピー・改変してご利用ください）。

| ファイル | 役割 |
|---------|------|
| `samples/index.php` | 記事一覧（最新10件） |
| `samples/single.php` | 記事詳細（`?id=1` or `?slug=my-post`） |
| `samples/category.php` | カテゴリ別一覧（`?id=1` or `?slug=blog`） |

詳しくは [samples/README.md](samples/README.md) を参照してください。

### ヘルパー関数

`config.php` を読み込むと次のヘルパーが使えます（公開中の記事のみが返ります）。

```php
require_once 'config.php';

// 公開中の記事を取得（最新10件）
$posts = get_posts([
    'limit'    => 10,
    'order_by' => 'published_at',
    'order'    => 'DESC',
]);

// 1件の記事を ID or slug で取得
$post = get_post(1);          // ID
$post = get_post('my-post');  // slug

// カテゴリ一覧、特定の記事に紐付くカテゴリ
$categories      = get_categories();
$post_categories = get_post_categories($post['id']);

// サムネイルURL（thumb 変種があればそれ、なければ元画像）
$url = post_thumb_url($post['thumbnail']);
```

予約投稿は時刻が来るまで自動的に除外されます。

### RSS フィード / サイトマップ

- `feed.php` — RSS 2.0 形式で最新50件
- `sitemap.php` — 検索エンジン向けXMLサイトマップ

どちらも記事URLを利用者のサイトに合わせて変更する想定です（ファイル先頭のコールバックを編集）。

---

## 既知の制限・免責事項

このCMSは学習・小規模サイト向けです。本番運用は自己責任でお願いします。

- **メールによるパスワードリセットなし** — 別の管理者にリセットしてもらうか、管理者が一人だけの場合はDB直接更新が必要
- **`setup.php` は使用後必ず削除** — 残すと第三者が管理者を上書きできます
- **画像最適化なし** — アップロード画像はそのまま保存されます（2MB上限）
- **セキュリティ監査未実施** — 大規模・公開度の高いサイトには適しません

実装済みのセキュリティ対策については [CHANGELOG.md](CHANGELOG.md) を参照してください。

---

## ライセンス

MIT License — 詳細は [LICENSE](LICENSE) を参照してください。

商用・非商用を問わず自由に利用・改変・再配布できます。
