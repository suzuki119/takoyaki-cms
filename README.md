# Takoyaki CMS

PHP + MySQL で動作する、シンプルな自作CMSです。
ブログや作品紹介サイトなど、記事＋カテゴリで成り立つ小規模サイトを想定しています。

「PHPを学んだ人が読める / 改造できる」サイズ感を大切にしています。
学習用途・改造用途を歓迎します。

---

## 特徴

- 約 1,500 行のPHPコード（依存ライブラリは CKEditor 5 のみ、CDN経由）
- 記事の作成・編集・削除、サムネイル画像、CKEditor 5 によるリッチテキスト編集
- カテゴリ管理、記事との多対多紐付け
- 管理者認証（セッションベース）
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
├── LICENSE
├── config.example.php   # 設定ファイルのテンプレート
├── schema.sql           # DBスキーマ
├── setup.php            # 管理者初回登録（使用後に削除）
├── login.php            # ログイン画面
├── logout.php           # ログアウト処理
├── admin/
│   ├── index.php        # 記事一覧
│   ├── post-new.php     # 記事新規作成
│   ├── post-edit.php    # 記事編集
│   ├── categories.php   # カテゴリ管理
│   └── upload-image.php # 本文画像アップロード（CKEditor連携）
└── uploads/             # 画像保存先（要書き込み権限）
```

---

## データベース構成

| テーブル | 役割 |
|---------|------|
| `users` | 管理者ユーザー |
| `posts` | 記事（タイトル / 本文 / サムネイル / ステータス / 並び順） |
| `categories` | カテゴリ |
| `post_categories` | 記事とカテゴリの多対多 |

詳細は `schema.sql` を参照してください。

---

## フロントエンドについて

本CMSは **管理画面のみ** を提供します。記事を表示する公開ページは含まれていません。
利用者が自分のサイトに合わせて自由にデザイン・実装してください。

DBから記事を取得する例：

```php
<?php
require_once 'config.php';
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM posts WHERE status = 'published' ORDER BY sort_order ASC");
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<?php foreach ($posts as $post): ?>
    <article>
        <h2><?= h($post['title']) ?></h2>
        <?= $post['body'] ?>
    </article>
<?php endforeach; ?>
```

---

## 既知の制限・免責事項

このCMSは学習・小規模サイト向けです。本番運用は自己責任でお願いします。

- **CSRF対策が未実装** — 管理画面は信頼できるネットワーク内で運用してください
- **パスワードリセット機能なし** — 忘れた場合はDB直接更新が必要です
- **`setup.php` は使用後必ず削除** — 残すと第三者が管理者を上書きできます
- **画像最適化なし** — アップロード画像はそのまま保存されます（2MB上限）
- **セキュリティ監査未実施** — 大規模・公開度の高いサイトには適しません

---

## ライセンス

MIT License — 詳細は [LICENSE](LICENSE) を参照してください。

商用・非商用を問わず自由に利用・改変・再配布できます。
