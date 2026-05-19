# Takoyaki CMS — サンプルテンプレート

このディレクトリのファイルは **公開ページのサンプル実装** です。
Takoyaki CMS は管理画面のみを提供し、フロントエンド表示は利用者が自由に実装する設計です。
これらのサンプルはそのまま動作しますが、各自のサイトのデザインに合わせて編集する前提です。

## ファイル一覧

| ファイル | 役割 | アクセス例 |
|---------|------|-----------|
| `index.php` | 記事一覧（最新10件） | `/samples/index.php` |
| `single.php` | 記事詳細 | `/samples/single.php?id=1` または `?slug=my-post` |
| `category.php` | カテゴリ別一覧 | `/samples/category.php?id=1` または `?slug=blog` |

## 使い方

### そのまま動かす

このディレクトリのまま `http://your-site/takoyaki-cms/samples/index.php` でアクセス可能です（CMS本体と同じドメイン下で）。

### 自分のサイトにコピーする

各ファイルを自分のサイトのドキュメントルートやテーマディレクトリにコピーし、`require_once` のパスを自分の環境に合わせて修正してください。

```php
// samples/index.php の冒頭
require_once __DIR__ . '/../config.php';

// → コピー後、例えば
require_once '/var/www/takoyaki-cms/config.php';
```

## 使えるヘルパー関数

`config.php` に定義されており、`require_once 'config.php'` で読み込めば呼べます。

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_posts(array $opts)` | array | 公開中の記事一覧を取得 |
| `get_post($id_or_slug, bool $include_drafts = false)` | array\|null | 1件の記事を取得 |
| `get_categories()` | array | 全カテゴリ |
| `get_category($id_or_slug)` | array\|null | 1件のカテゴリ |
| `get_post_categories(int $post_id)` | array | 記事に紐付くカテゴリ |
| `post_thumb_url(?string $filename)` | string\|null | サムネイル変種のURL |

### `get_posts()` のオプション

```php
$posts = get_posts([
    'limit'         => 10,           // 件数上限
    'offset'        => 0,            // ページネーション用
    'category_id'   => 3,            // カテゴリ絞り込み
    'order_by'      => 'published_at', // sort_order / created_at / published_at / updated_at / id / title
    'order'         => 'DESC',       // ASC | DESC
    'include_drafts'=> false,        // 下書き・予約も含めるか
]);
```

予約投稿は自動的に時刻が来るまで除外されます。

## 関連ファイル

このディレクトリ外には次の即利用可能なファイルがあります：

- `../feed.php` — RSS 2.0 フィード
- `../sitemap.php` — sitemap.xml（検索エンジン用）
