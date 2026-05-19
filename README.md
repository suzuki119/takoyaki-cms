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
- 複数管理者（admin / editor ロール）、CSRF対策、ログイン試行回数制限
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
├── admin/
│   ├── index.php        # 記事一覧
│   ├── post-new.php     # 記事新規作成
│   ├── post-edit.php    # 記事編集
│   ├── categories.php   # カテゴリ管理
│   ├── users.php        # ユーザー管理（admin限定）
│   ├── user-edit.php    # ユーザー編集（admin限定）
│   ├── account.php      # 自分のアカウント設定
│   └── upload-image.php # 本文画像アップロード（CKEditor連携）
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

本CMSは **管理画面のみ** を提供します。記事を表示する公開ページは含まれていません。
利用者が自分のサイトに合わせて自由にデザイン・実装してください。

DBから公開中の記事を取得する例：

```php
<?php
require_once 'config.php';
$pdo = db();

// status='published' かつ published_at が現在以前（または未設定）の記事だけ取得
// → 予約投稿は自動的に時刻が来るまで非表示になる
$stmt = $pdo->prepare(
    "SELECT * FROM posts
      WHERE status = 'published'
        AND (published_at IS NULL OR published_at <= NOW())
      ORDER BY sort_order ASC"
);
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<?php foreach ($posts as $post): ?>
    <article>
        <h2><?= h($post['title']) ?></h2>
        <?php if (!empty($post['excerpt'])): ?>
            <p><?= h($post['excerpt']) ?></p>
        <?php endif; ?>
        <?= $post['body'] ?>
    </article>
<?php endforeach; ?>
```

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
