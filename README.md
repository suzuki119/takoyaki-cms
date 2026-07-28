# Takoyaki CMS

PHP + MySQL で動作する、**ポートフォリオサイト向けの自作CMS**です。

**作品紹介（Works）** と **スキル紹介（Skills）** の2つを、管理画面から更新できるようにします。
「PHPを学んだ人が読める / 改造できる」サイズ感を大切にしています。

> **v2.0.0 で汎用CMSからポートフォリオCMSに方針転換しました。**
> テーマ / プラグイン / RSS / ゴミ箱 / 複数ユーザーといった汎用機能を削り、
> 作品メタ情報・セクション式の本文・スキル管理を実装しています。
> v1.x からの移行は [migrations/v2.0.0.sql](migrations/v2.0.0.sql) を参照してください。

---

## できること

### 作品（Works）

- タイトル / slug / 概要 / サムネイル
- **制作期間・種別・外部リンク・動画URL**（YouTube / Vimeo の埋め込みに対応）
- **セクション式の本文** — 「見出し＋本文」を並べて詳細ページを構成
- カテゴリ（複数選択可）／ 使用技術タグ
- 下書き・予約公開・プレビュー
- ↑↓ ボタンでの並び替え

### スキル（Skills）

- カテゴリ（プログラミング / デザイン / その他）ごとにグループ表示
- スキル名 / アイコン画像 / 期間・習熟度 / 説明
- カテゴリ内での並び替え

### 共通

- CKEditor 5 によるリッチテキスト編集（本文セクションごと）
- 画像の自動リサイズ・サムネイル生成・メディアライブラリ
- 管理者ログイン（1人構成）、CSRF対策、ログイン試行回数制限
- そのまま動く公開ページのサンプル（`samples/`）

---

## 動作要件

- PHP 8.0 以上（8.1+ 推奨）
- MySQL 5.7 以上 または MariaDB 10.3 以上
- Apache（mod_rewrite は不要）
- GD 拡張（画像リサイズ用）
- ローカル開発環境: MAMP / XAMPP / Laragon など

---

## インストール手順

### 1. ファイルを配置

```bash
git clone https://github.com/<user>/takoyaki-cms.git
cd takoyaki-cms
```

### 2. データベースを作成

```sql
CREATE DATABASE my_cms DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. スキーマをインポート

```bash
mysql -u <user> -p my_cms < schema.sql
```

### 4. 設定ファイルを作成

```bash
cp config.example.php config.php
```

編集する項目：

| 定数 | 説明 |
|------|------|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | DB接続情報 |
| `SITE_URL` | このCMSを設置したURL（末尾スラッシュなし） |
| `CMS_TIMEZONE` | タイムゾーン（既定 `Asia/Tokyo`） |
| `SKILL_CATEGORIES` | スキルのカテゴリと表示順 |

### 5. アップロードディレクトリの権限設定

```bash
chmod 755 uploads
```

`uploads/.htaccess` に PHP 実行を止める設定が入っています。削除しないでください。

### 6. 管理者ユーザーを登録

ブラウザで `setup.php` を開き、フォームから登録します。

```
http://your-site/takoyaki-cms/setup.php
```

### 7. setup.php を削除する（重要）

```bash
rm setup.php
```

残すと第三者が管理者を登録できる可能性があります。

### 8. 管理画面にログイン

```
http://your-site/takoyaki-cms/login.php
```

---

## v1.x からのアップグレード

**破壊的変更を含みます。必ずバックアップを取ってから実行してください。**

```bash
mysqldump -u <user> -p <dbname> > backup-before-v2.sql
mysql -u <user> -p <dbname> < migrations/v2.0.0.sql
cp config.example.php config.php   # 設定ファイルも作り直す
```

移行の内容:

| 変更 | 挙動 |
|------|------|
| `posts.body` → `post_sections` | 既存の本文が「見出しなしの1セクション」として移行されます |
| ゴミ箱の廃止 | **ゴミ箱に入っていた記事は完全に削除されます** |
| 作品メタの追加 | `period` / `type` / `external_url` / `video_url` が空で追加されます |
| `sort_order` の採番 | v1.x では全て0で並び替えが機能していませんでした。id順に振り直します |
| カテゴリ slug の正常化 | 数字だけの slug を `cat-{id}` に置き換えます（あとで管理画面から編集してください） |
| ロールの廃止 | `users.role` を削除します。**複数ユーザーがいる場合、全員が管理者になります**。事前に整理してください |
| テーブル削除 | `post_meta` / `audit_logs` / `password_resets` |

---

## ディレクトリ構成

```
takoyaki-cms/
├── README.md / CHANGELOG.md / EMBEDDING.md / TESTING.md
├── config.example.php   # 設定ファイルのテンプレート
├── schema.sql           # DBスキーマ（新規インストール用）
├── migrations/          # バージョンアップ用SQL
├── setup.php            # 管理者初回登録（使用後に削除）
├── login.php / logout.php
├── preview.php          # 作品プレビュー（ログイン必須）
├── admin/
│   ├── index.php        # 作品一覧
│   ├── post-new.php     # 作品 新規作成
│   ├── post-edit.php    # 作品 編集
│   ├── _post-form.php   # 作品フォームの共通部品
│   ├── skill.php        # スキル一覧
│   ├── skill-edit.php   # スキル 新規/編集
│   ├── categories.php   # カテゴリ管理
│   ├── tags.php         # タグ管理
│   ├── media.php        # メディアライブラリ
│   ├── settings.php     # サイト設定
│   ├── account.php      # アカウント設定
│   ├── upload-image.php # 本文画像アップロード（CKEditor連携）
│   ├── _layout.php / admin.css
├── samples/             # 公開ページのサンプル
│   ├── works.php        # 作品一覧
│   ├── single.php       # 作品詳細
│   ├── skill.php        # スキル一覧
│   ├── _layout.php / style.css
└── uploads/             # 画像保存先（要書き込み権限）
```

---

## データベース構成

| テーブル | 役割 |
|---------|------|
| `posts` | 作品（タイトル / slug / 概要 / サムネイル / 制作期間 / 種別 / 外部リンク / 動画URL / ステータス / 公開日時 / 並び順） |
| `post_sections` | 作品詳細の本文セクション（見出し＋本文、1対多） |
| `skills` | スキル（カテゴリ / 名前 / アイコン / 期間 / 説明 / 並び順） |
| `categories` / `post_categories` | カテゴリと多対多 |
| `tags` / `post_tags` | 使用技術タグと多対多 |
| `users` | 管理者ユーザー（1人構成） |
| `site_settings` | サイト設定（key-value） |
| `login_attempts` | ログイン試行（レート制限用） |

詳細は [schema.sql](schema.sql) を参照してください。

---

## 公開ページについて

本CMSは **管理画面 + フロントエンド向けヘルパー** を提供します。見た目は利用者が自由に実装する設計です。

### サンプル

`samples/` にそのまま動くサンプルがあります（コピー・改変してご利用ください）。

| ファイル | 役割 |
|---------|------|
| `samples/works.php` | 作品一覧（カテゴリフィルター + ページ送り） |
| `samples/single.php` | 作品詳細（`?slug=my-work` or `?id=1`） |
| `samples/skill.php` | スキル一覧（カテゴリ別グリッド） |

既存サイトへの組み込み方は **[EMBEDDING.md](EMBEDDING.md)** を参照してください。

### ヘルパー関数

`config.php` を読み込むと次のヘルパーが使えます（公開中の作品のみが返ります）。

```php
require_once 'config.php';

// 作品
$posts    = get_posts(['limit' => 12, 'category_id' => 3]);
$post     = get_post('my-work');            // slug または ID
$sections = get_post_sections($post['id']); // 本文セクション

// 分類
$categories = get_categories();
$cats       = get_post_categories($post['id']);
$tags       = get_post_tags($post['id']);

// スキル
$skills  = get_skills();          // 表示順に並んだ配列
$grouped = get_skills_grouped();  // ['プログラミング' => [...], ...]

// URL・画像
$url   = public_post_url($post);               // 公開ページURL
$thumb = post_thumb_url($post['thumbnail']);   // サムネイルURL
$embed = video_embed_url($post['video_url']);  // YouTube/Vimeo 埋め込みURL
```

予約公開は時刻が来るまで自動的に除外されます。

---

## 既知の制限・免責事項

このCMSは学習・小規模サイト向けです。本番運用は自己責任でお願いします。

- **`setup.php` は使用後必ず削除** — 残すと第三者が管理者を登録できます
- **管理者は1人構成** — 複数人での編集や権限分けはできません
- **パスワードリセット機能はありません** — 忘れた場合は DB を直接書き換えてください
- **本文HTMLはサニタイズしません** — 書き手＝管理者本人を信頼する前提の設計です。
  管理画面の認証情報を他人に渡さないでください
- **セキュリティ監査未実施** — 大規模・公開度の高いサイトには適しません
- **REST API / コメント / 多言語化は未実装**

実装済みのセキュリティ対策と既知の課題は [CHANGELOG.md](CHANGELOG.md) と
[EMBEDDING.md の付記](EMBEDDING.md) を参照してください。

---

## ライセンス

MIT License — 詳細は [LICENSE](LICENSE) を参照してください。

商用・非商用を問わず自由に利用・改変・再配布できます。
