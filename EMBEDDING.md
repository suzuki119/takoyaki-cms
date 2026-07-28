# Takoyaki CMS 組み込みガイド

> ## ⚠️ このガイドの本文は v1.x 時代のものです（2026-07-28 時点）
>
> v2.0.0 でポートフォリオCMSへ方針転換したため、**以下の記述はもう正しくありません**:
>
> - `$post['body']` を直接出力するコード例 → 本文は `get_post_sections()` で取得します
> - RSS / sitemap / `router.php` / `.htaccess` の章 → **これらのファイルは削除されました**
> - テーマ / プラグイン / ショートコードへの言及 → **機構ごと廃止されました**
> - `post_meta`（カスタムフィールド）→ **テーブルごと削除されました**
> - admin / editor のロールの話 → **管理者1人構成になりました**
>
> **[付録: ヘルパー関数チートシート](#付録-ヘルパー関数チートシート) は v2.0.0 に更新済み**なので、
> 実装時はそちらを参照してください。設置手順・URL設計・トラブルシューティングの考え方は
> v2.0.0 でもそのまま通用します。
>
> 本文全体の v2.0.0 対応は未着手です（[作業ログ](#作業ログ) 参照）。

**既存の PHP サイトに記事管理機能だけを足したい人向け**のガイドです。
サイトのデザイン・URL構造・既存テンプレートはそのままに、Takoyaki CMS を
「記事ストア + 管理画面」として使う方法をまとめます。

> 公開ページ（フロント）まで含めて Takoyaki CMS で作りたい場合は、
> [samples/](samples/) と [router.php](router.php) のサンプル実装をそのままコピーして
> カスタマイズする方が早いです。このガイドはあくまで「既存サイト + CMS」の構成向け。

---

## 目次

1. [このガイドの読み方](#このガイドの読み方)
2. [30秒サマリ](#30秒サマリ)
3. [設置](#1-設置)
4. [既存サイトから記事を取り出す](#2-既存サイトから記事を取り出す)
5. [URL設計（3通り）](#3-url設計3通り)
6. [メディアと画像](#4-メディアと画像)
7. [SEO（タイトル / description / RSS / sitemap）](#5-seo)
8. [編集者の運用](#6-編集者の運用)
9. [セキュリティチェックリスト](#7-セキュリティチェックリスト)
10. [一段上のテクニック](#8-一段上のテクニック)
11. [トラブルシューティング](#9-トラブルシューティング)
12. [やらないこと・制約](#10-やらないこと制約)
13. [付録: ヘルパー関数チートシート](#付録-ヘルパー関数チートシート)

---

## このガイドの読み方

| あなたの状況 | 読む順序 |
|------------|---------|
| とりあえず動かしたい | [30秒サマリ](#30秒サマリ) → [設置](#1-設置) → [既存サイトから記事を取り出す](#2-既存サイトから記事を取り出す) |
| 本番投入を見据えて設計したい | [URL設計](#3-url設計3通り) → [SEO](#5-seo) → [セキュリティチェックリスト](#7-セキュリティチェックリスト) |
| 既に組み込んだが詰まっている | [トラブルシューティング](#9-トラブルシューティング) |

---

## 30秒サマリ

既存サイトの PHP ファイルに **1行 require** するだけで、記事一覧/記事詳細/カテゴリ/タグが取り出せます。

```php
<?php require_once __DIR__ . '/takoyaki-cms/config.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <link rel="stylesheet" href="/assets/css/site.css"><!-- 既存CSSそのまま -->
</head>
<body>
  <!-- 既存のヘッダー・サイドバー等そのまま -->

  <ul class="news">
    <?php foreach (get_posts(['limit' => 5, 'order_by' => 'published_at', 'order' => 'DESC']) as $p): ?>
      <li>
        <time><?= h(substr($p['published_at'], 0, 10)) ?></time>
        <a href="/news/<?= h($p['slug']) ?>"><?= h($p['title']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- 既存のフッターそのまま -->
</body>
</html>
```

- 編集者は `https://your-site.com/takoyaki-cms/admin/` で記事を編集
- 公開ページは既存サイトのまま（デザインも URL もそのまま）
- 下書き・予約公開は自動で除外される

---

## 1. 設置

### 1.1 ディレクトリ配置

既存サイトの**サブディレクトリ**に置くのが一番素直です。

```
/var/www/your-site/         ← 既存サイト（ドキュメントルート）
├── index.php               ← 既存トップ
├── about.php               ← 既存ページ
├── news/                   ← 新規: 記事関連のページ群
│   ├── index.php           ← 記事一覧
│   └── detail.php          ← 記事詳細
├── assets/                 ← 既存アセット
└── takoyaki-cms/           ← ★CMSをここに配置
    ├── config.php
    ├── admin/              ← 編集者はここにアクセス
    └── uploads/            ← 画像保存先
```

> **サブドメイン（cms.your-site.com）に分ける構成**もできますが、同一ドメイン配下のほうが
> `uploads/` の画像URLや認証Cookieの取り回しが楽です。最初はサブディレクトリ推奨。

### 1.2 インストール手順

[README](README.md#インストール手順) に沿って:

1. `git clone` で `takoyaki-cms/` を配置
2. DBを作成（`utf8mb4`）
3. `mysql ... < schema.sql` でスキーマ投入
4. `cp config.example.php config.php` し、DB接続情報を編集
5. **`SITE_URL` を `https://your-site.com/takoyaki-cms` に設定**（CMS自身のURL）
6. ブラウザで `https://your-site.com/takoyaki-cms/setup.php` にアクセスして管理者作成
7. **`takoyaki-cms/setup.php` を削除**（必須）
8. `https://your-site.com/takoyaki-cms/admin/` から記事を作成

### 1.3 `SITE_URL` の理解

`SITE_URL` は **CMS 自身の設置URL**で、**既存サイトのURLとは別**です。

| 用途 | 値 |
|------|-----|
| 既存サイトのトップ | `https://your-site.com/` |
| CMS の `SITE_URL` | `https://your-site.com/takoyaki-cms` |
| 管理画面 | `https://your-site.com/takoyaki-cms/admin/` |
| アップロード画像のURL | `https://your-site.com/takoyaki-cms/uploads/...` |
| **公開ページ（あなたが作るもの）** | `https://your-site.com/news/...`（自由設計） |

公開ページのURLは `SITE_URL` と独立に設計できます。

---

## 2. 既存サイトから記事を取り出す

### 2.1 最小コード

```php
<?php require_once '/path/to/takoyaki-cms/config.php'; ?>
```

これで以下のヘルパーが使えるようになります（→ [付録: チートシート](#付録-ヘルパー関数チートシート)）。

### 2.2 記事一覧（トップページにお知らせを5件）

```php
<?php
$news = get_posts([
    'limit'    => 5,
    'order_by' => 'published_at',
    'order'    => 'DESC',
]);
?>
<section class="news">
  <h2>お知らせ</h2>
  <ul>
    <?php foreach ($news as $p): ?>
      <li>
        <time datetime="<?= h($p['published_at']) ?>">
          <?= h(date('Y.m.d', strtotime($p['published_at']))) ?>
        </time>
        <a href="/news/<?= h($p['slug']) ?>"><?= h($p['title']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
```

### 2.3 記事詳細

```php
<?php
require_once __DIR__ . '/../takoyaki-cms/config.php';

$post = get_post($_GET['slug'] ?? '');
if (!$post) { http_response_code(404); exit('Not found'); }

$categories = get_post_categories((int)$post['id']);
$tags       = get_post_tags((int)$post['id']);
?>
<!-- 既存サイトのヘッダー -->
<article class="既存の記事クラス">
  <h1><?= h($post['title']) ?></h1>

  <div class="meta">
    <time><?= h($post['published_at']) ?></time>
    <?php if ($categories): ?>
      <span class="cats">
        <?php foreach ($categories as $c): ?>
          <a href="/category/<?= h($c['id']) ?>"><?= h($c['name']) ?></a>
        <?php endforeach; ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($post['thumbnail']): ?>
    <img src="<?= h(post_thumb_url($post['thumbnail'])) ?>" alt="">
  <?php endif; ?>

  <div class="body">
    <?= $post['body'] /* CKEditor が出力した HTML。エスケープしない */ ?>
  </div>

  <?php if ($tags): ?>
    <div class="tags">
      <?php foreach ($tags as $t): ?>
        <a href="/tag/<?= h($t['slug']) ?>">#<?= h($t['name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</article>
<!-- 既存サイトのフッター -->
```

### 2.4 カテゴリ別一覧

```php
<?php
require_once __DIR__ . '/../takoyaki-cms/config.php';

$cat = get_category($_GET['slug'] ?? $_GET['id'] ?? '');
if (!$cat) { http_response_code(404); exit; }

$posts = get_posts([
    'category_id' => (int)$cat['id'],
    'order_by'    => 'published_at',
    'order'       => 'DESC',
    'limit'       => 20,
]);
?>
<h1><?= h($cat['name']) ?> の記事</h1>
<ul>
  <?php foreach ($posts as $p): ?>
    <li><a href="/news/<?= h($p['slug']) ?>"><?= h($p['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
```

### 2.5 サイドバー: カテゴリ一覧

```php
<aside class="既存のサイドバー">
  <h3>カテゴリ</h3>
  <ul>
    <?php foreach (get_categories() as $c): ?>
      <li><a href="/category/<?= h($c['id']) ?>"><?= h($c['name']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</aside>
```

### 2.6 ページネーション

```php
<?php
$per_page = 10;
$page     = max(1, (int)($_GET['p'] ?? 1));

$posts = get_posts([
    'limit'    => $per_page,
    'offset'   => ($page - 1) * $per_page,
    'order_by' => 'published_at',
    'order'    => 'DESC',
]);

// 総件数は別途取得（ヘルパー化推奨）
$total = (int)db()->query(
    "SELECT COUNT(*) FROM posts
     WHERE deleted_at IS NULL
       AND status = 'published'
       AND (published_at IS NULL OR published_at <= NOW())"
)->fetchColumn();
$total_pages = (int)ceil($total / $per_page);
?>
<nav class="pagination">
  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="?p=<?= $i ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</nav>
```

---

## 3. URL設計（3通り）

CMS は公開側URLを強制しません。既存サイトに合わせて自由に設計できます。代表的な3パターン:

### A. クエリ文字列（最も簡単・mod_rewrite不要）

```
/news/                       ← /news/index.php   (一覧)
/news/detail.php?slug=hello  ← 記事詳細
/category/index.php?id=2     ← カテゴリ別
```

メリット: `.htaccess` 不要、デプロイ簡単
デメリット: 見栄えが悪い

### B. `.htaccess` で書き換え（推奨）

```apache
# your-site/news/.htaccess
RewriteEngine On
RewriteBase /news/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^([^/]+)/?$ detail.php?slug=$1 [L,QSA]
```

これで `/news/hello-world` → `/news/detail.php?slug=hello-world` になります。

### C. フロントコントローラ方式（凝った構造向け）

`your-site/index.php` を全ルーティングのエントリにする方式。CMS 同梱の
[router.php](router.php) がそのまま参考実装になります。コピーして
既存サイトのテンプレートに合わせて改造してください。

---

## 4. メディアと画像

### 4.1 仕組み

- アップロード先（ファイルシステム）: `takoyaki-cms/uploads/`
- 公開URL: `https://your-site.com/takoyaki-cms/uploads/{file}`
- サムネイル変種: `{basename}-thumb.{ext}` が自動生成される（既定 300px幅）
- 元画像も自動リサイズ（既定 最大幅 1600px）

### 4.2 既存サイトから画像URLを取り出す

```php
<!-- サムネイルがあればそれ、なければ元画像 -->
<img src="<?= h(post_thumb_url($post['thumbnail'])) ?>" alt="">

<!-- 元画像（オリジナル）を使いたい場合 -->
<?php if ($post['thumbnail']): ?>
  <img src="<?= h(UPLOAD_URL . $post['thumbnail']) ?>" alt="">
<?php endif; ?>
```

### 4.3 本文内画像（CKEditor から挿入）

CKEditor で挿入した画像は本文 HTML の `<img src="...uploads/...">` として含まれます。
追加処理は不要、`<?= $post['body'] ?>` でそのまま表示されます。

### 4.4 既存サイト側で別パス（例 `/img/`）にしたい場合

`config.php` の `UPLOAD_URL` を書き換えれば良いですが、運用が複雑になります。
推奨は **デフォルトの `SITE_URL/uploads/` のまま使う**こと。
URL の見栄えが気になるなら `.htaccess` でエイリアスを切るのが簡単:

```apache
# your-site/.htaccess
Alias /img /var/www/your-site/takoyaki-cms/uploads
```

---

## 5. SEO

### 5.1 タイトル・description

```php
<head>
  <title><?= h($post['title']) ?> | あなたのサイト名</title>
  <?php if ($post['excerpt']): ?>
    <meta name="description" content="<?= h($post['excerpt']) ?>">
  <?php endif; ?>

  <!-- OGP（任意） -->
  <meta property="og:title" content="<?= h($post['title']) ?>">
  <meta property="og:description" content="<?= h($post['excerpt']) ?>">
  <?php if ($post['thumbnail']): ?>
    <meta property="og:image" content="<?= h(UPLOAD_URL . $post['thumbnail']) ?>">
  <?php endif; ?>
  <meta property="og:type" content="article">
  <meta property="og:url" content="https://your-site.com/news/<?= h($post['slug']) ?>">
</head>
```

### 5.2 RSS / sitemap.xml

`takoyaki-cms/feed.php` と `takoyaki-cms/sitemap.php` がそのまま使えますが、
**記事URLが CMS の URL になっている**ので、既存サイトのURL構造に合わせて
書き換えてください。ファイル先頭の `$post_url` コールバックを修正:

```php
// feed.php / sitemap.php の冒頭で
$post_url = function (array $post): string {
    return 'https://your-site.com/news/' . urlencode($post['slug']);
};
```

その後、Google Search Console 等には `https://your-site.com/takoyaki-cms/sitemap.php`
を登録するか、既存サイトの `robots.txt` から参照を張ります。

### 5.3 既存サイト側に薄いラッパーを置く方式（推奨）

CMS の `feed.php` `sitemap.php` を**直接編集したくない**なら、既存サイト側に薄いラッパーを置きます:

```php
<?php
// your-site/feed.xml.php  →  /feed.xml で配信
require_once __DIR__ . '/takoyaki-cms/config.php';
header('Content-Type: application/rss+xml; charset=utf-8');

$posts = get_posts(['limit' => 50, 'order_by' => 'published_at', 'order' => 'DESC']);
// （RSS2.0のXMLを自前出力。takoyaki-cms/feed.php がそのまま参考になる）
```

これなら CMS のバージョンアップで `feed.php` を上書きされても影響を受けません。

---

## 6. 編集者の運用

### 6.1 アクセスURL

| 役割 | URL |
|------|-----|
| サイト訪問者 | `https://your-site.com/` |
| 編集者ログイン | `https://your-site.com/takoyaki-cms/login.php` |
| 管理画面 | `https://your-site.com/takoyaki-cms/admin/` |

`takoyaki-cms/admin/` へのアクセスは内部でログインゲートがかかるので、
別途 Basic認証等を足す必要はありません（必要に応じて追加可能）。

### 6.2 ロール

- **admin** — 全機能（ユーザー追加・設定・テーマ・プラグイン・バックアップ）
- **editor** — 記事・カテゴリ・タグ・自分のアカウントのみ

複数人で運用するなら、編集者ごとに editor ユーザーを作るのがおすすめ。
admin は1〜2人に絞ると安全です。

### 6.3 編集 → 公開の流れ

1. 編集者が管理画面で記事を作成（ステータス: 下書き or 公開 or 予約公開）
2. 「公開」で保存 → DBに反映
3. **次のページリクエストから即座に既存サイトに反映**
   - キャッシュなし（PHPが毎回DBを引く）
   - 静的サイトジェネレーターと違って再ビルド不要

### 6.4 バックアップ

`管理画面 → バックアップ` から SQL ダンプをダウンロードできます。
編集が活発な期間は週次〜月次で取っておくと安心です。

---

## 7. セキュリティチェックリスト

本番投入前に必ず確認:

- [ ] `takoyaki-cms/setup.php` を **削除した**
- [ ] `config.php` のDB接続情報を本番用に変更
- [ ] `SITE_URL` を `https://...`（HTTPS）に設定
- [ ] サイト全体を HTTPS 化（セッションCookieに `secure` が付くようになる）
- [ ] DB ユーザーは CMS 用に専用作成（root を使い回さない）
- [ ] `uploads/` ディレクトリの**PHP実行を無効化**（推奨）:
  ```apache
  # takoyaki-cms/uploads/.htaccess
  <FilesMatch "\.(php|phtml|phar)$">
    Require all denied
  </FilesMatch>
  ```
- [ ] `takoyaki-cms/config.php` がブラウザから直接読めないことを確認
  （PHPとして実行されるので読めないはずだが、念のため `curl https://your-site.com/takoyaki-cms/config.php` で空応答を確認）
- [ ] 編集者には強いパスワード（8文字以上 + 英字 + 数字）
- [ ] 本番サーバーの PHP の `display_errors` は Off（DB接続失敗時の情報漏洩防止）

---

## 8. 一段上のテクニック

### 8.1 ラッパー関数を切り出す

組み込み側のテンプレが読みやすくなります。

```php
<?php
// your-site/inc/cms.php
require_once __DIR__ . '/../takoyaki-cms/config.php';

/** 記事の公開ページURL（既存サイトのURL構造） */
function article_url(array $post): string {
    return '/news/' . urlencode($post['slug']);
}

/** カテゴリの公開ページURL */
function category_url(array $cat): string {
    return '/category/' . urlencode($cat['id']);
}

/** 最新記事 */
function latest_news(int $n = 5): array {
    return get_posts(['limit' => $n, 'order_by' => 'published_at', 'order' => 'DESC']);
}

/** RFC3339 形式の日付（OGP/構造化データ用） */
function iso_date(string $sql_dt): string {
    return date('c', strtotime($sql_dt));
}
```

```php
<!-- テンプレ側 -->
<?php require_once __DIR__ . '/inc/cms.php'; ?>
<?php foreach (latest_news(5) as $p): ?>
  <a href="<?= h(article_url($p)) ?>"><?= h($p['title']) ?></a>
<?php endforeach; ?>
```

### 8.2 共通レイアウトの切り出し

既存サイトに `header.php` `footer.php` が無いなら作っておくと、記事ページの追加が楽になります:

```php
<!-- your-site/inc/header.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= h($page_title ?? 'あなたのサイト') ?></title>
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
  <header><!-- 既存ヘッダー --></header>
  <main>
```

```php
<!-- your-site/news/detail.php -->
<?php
require_once __DIR__ . '/../inc/cms.php';
$post = get_post($_GET['slug'] ?? '');
if (!$post) { http_response_code(404); exit; }
$page_title = $post['title'] . ' | あなたのサイト';
include __DIR__ . '/../inc/header.php';
?>
<article>
  <h1><?= h($post['title']) ?></h1>
  <?= $post['body'] ?>
</article>
<?php include __DIR__ . '/../inc/footer.php'; ?>
```

### 8.3 キャッシュ（高トラフィック向け）

DBクエリを毎回叩くのが負荷的に厳しくなったら、ページ単位のフラグメントキャッシュ:

```php
<?php
$cache_file = __DIR__ . '/cache/latest_news.html';
$ttl        = 300; // 5分

if (!file_exists($cache_file) || time() - filemtime($cache_file) > $ttl) {
    ob_start();
    require_once __DIR__ . '/inc/cms.php';
    foreach (latest_news(5) as $p) {
        echo '<li><a href="' . h(article_url($p)) . '">' . h($p['title']) . '</a></li>';
    }
    file_put_contents($cache_file, ob_get_clean());
}
readfile($cache_file);
```

編集者が記事を保存したらキャッシュを破棄したい場合は、CMS のフック機構を使えます:

```php
// your-site/inc/cache-invalidate.php （CMS のプラグインとして配置でも可）
add_action('post.save', function ($post) {
    @unlink(__DIR__ . '/../cache/latest_news.html');
});
```

---

## 9. トラブルシューティング

### 9.1 `require_once` で「No such file or directory」

→ パスが間違っています。**絶対パスか `__DIR__` ベースの相対**で書きましょう:

```php
// ❌ ワーキングディレクトリに依存して壊れる
require_once 'takoyaki-cms/config.php';

// ✅ そのファイルからの相対が安定
require_once __DIR__ . '/takoyaki-cms/config.php';
```

### 9.2 `DB接続エラー: SQLSTATE[HY000] [2002] No such file or directory`

→ MAMP/XAMPP 等で `DB_HOST = 'localhost'` がソケットを探しに行って失敗。
ホスト名を `127.0.0.1` にするか、ソケットパスを明示:

```php
// MAMPの例
define('DB_HOST', '127.0.0.1:8889');
// または config.php の db() を編集してソケットを指定
```

### 9.3 既存サイトのCSSと CKEditor の出力 HTML が衝突する

CKEditor は `<p>`, `<h2>`, `<ul>`, `<img>`, `<table>` 等を素直に吐きます。
既存CSSが「特定クラス配下しかスタイルが当たらない」設計なら、本文を専用クラスで包む:

```php
<div class="article-body">
  <?= $post['body'] ?>
</div>
```

```css
/* assets/css/site.css に追記 */
.article-body p { margin: 0 0 1em; }
.article-body h2 { font-size: 1.4rem; margin: 2em 0 .5em; }
.article-body img { max-width: 100%; height: auto; }
.article-body ul, .article-body ol { padding-left: 1.5em; }
```

### 9.4 `published_at` が NULL の記事が公開ページに出ない

→ 仕様通りです。「公開」ステータスで `published_at` 未設定の場合、
**「無期限の公開」とは扱われず、公開時刻不明として除外**されます。
記事を保存するときに公開日時を入れてください（CMS側で「今すぐ公開」ボタンも可）。

### 9.5 セッションが切れる / ログインが維持されない

→ 既存サイト側で別の `session_start()` を呼んでいる可能性。Takoyaki CMS の
`config.php` を読み込むより**前**に `session_start()` を呼んでいると Cookie名が
衝突することがあります。CMS の `start_session()` ヘルパーに一本化してください。

### 9.6 画像が表示されない

`uploads/` の **パーミッション**と、`SITE_URL` 設定を確認:

```bash
# 書き込めるか
ls -la takoyaki-cms/uploads/
chmod 755 takoyaki-cms/uploads/

# URLが正しいか
curl -I https://your-site.com/takoyaki-cms/uploads/test.png
```

### 9.7 「.htaccess を使いたいけど効かない」

→ Apache の設定で `AllowOverride All`（または最低 `FileInfo`）が必要。
共有サーバーは大抵有効。VPSで自分で建てた Apache だと無効になっていることがあります。

---

## 10. やらないこと・制約

このCMSを組み込み用途で使う時、**やらないほうがいい / できないこと**:

| 項目 | 理由 |
|------|------|
| 既存サイトと**同じDBに混在**させる | 可能だがテーブル名衝突に注意。専用DBを推奨 |
| CMS の `samples/` `router.php` を本番で使う | あくまでデモ。本番は既存サイト側で実装 |
| CMS の **テーマ機構を既存サイトに当てようとする** | テーマは `samples/` `router.php` 専用。既存サイトには影響しない |
| `setup.php` を残す | 第三者が管理者を上書きできる **重大な脆弱性** |
| 編集者の admin ロール乱発 | 設定・プラグイン・バックアップまで触れる。editor を使う |
| `config.php` を Git にコミット | `.gitignore` に入っている。本番DB情報を漏らさない |

このCMSが**カバーしない**こと（必要なら別実装または別CMSを検討）:

- **コメント機能** — 実装なし
- **多言語化** — 実装なし。1サイト1言語想定
- **固定ページ（Pages）** — 記事のみ。`/about` のような静的ページは既存サイトでそのまま運用
- **改訂履歴（Revisions）** — 上書き保存のみ
- **REST API** — 標準では無し。非PHP環境から叩きたい場合は別途実装が必要
- **複数サイト管理（Multisite）** — 1インストール1サイト

---

## 付録: ヘルパー関数チートシート

> **v2.0.0 準拠。** この付録だけは v2.0.0 の内容に更新済みです。
> 本文中のコード例には v1.x 時代のもの（`$post['body']` を直接出力する等）が残っています。

`config.php` を require すると使えるようになる関数の一覧（公開側で使う頻度順）。

### 作品（Works）

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_posts($opts = [])` | `array[]` | 公開中の作品一覧 |
| `get_post($id_or_slug, $include_drafts = false)` | `array?` | 1件取得（ID または slug） |
| `get_post_sections($post_id)` | `array[]` | 本文セクション（表示順） |
| `get_post_categories($post_id)` | `array[]` | 作品に紐付くカテゴリ |
| `get_post_tags($post_id)` | `array[]` | 作品に紐付く使用技術タグ |
| `is_post_live($post)` | `bool` | いま公開中か（予約公開の判定込み） |
| `public_post_url($post)` | `string` | 公開ページのURL |

**`get_posts()` のオプション**:

| キー | 型 | 既定 | 説明 |
|------|----|------|------|
| `limit` | `int?` | `null` | 最大件数 |
| `offset` | `int` | `0` | ページネーション用 |
| `category_id` | `int?` | `null` | カテゴリで絞り込み |
| `tag_id` | `int?` | `null` | タグで絞り込み |
| `order_by` | `string` | `'sort_order'` | `sort_order` / `created_at` / `published_at` / `updated_at` / `id` / `title` のみ可 |
| `order` | `string` | `'ASC'` | `ASC` / `DESC` |
| `include_drafts` | `bool` | `false` | 下書き・予約公開も含めるか（プレビュー用） |

**`get_posts()` / `get_post()` の戻り値の列**:

`id, title, slug, excerpt, thumbnail, status, published_at, period, type, external_url, video_url, sort_order, created_at, updated_at`

> **v1.x からの変更**: `body` / `author_id` / `deleted_at` は無くなりました。
> 本文は `get_post_sections($post['id'])` で別に取得します。

**本文の出し方**:

```php
foreach (get_post_sections((int)$post['id']) as $section) {
    if (!empty($section['title'])) {
        echo '<h2>' . h($section['title']) . '</h2>';
    }
    echo $section['body'];   // CKEditor が出力したHTML。書き手＝管理者を信頼する前提
}
```

### スキル（Skills）

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_skills()` | `array[]` | 全スキル（`SKILL_CATEGORIES` 順 → `sort_order` 順） |
| `get_skills_grouped()` | `array` | `['プログラミング' => [...], ...]` の形 |
| `get_skill($id)` | `array?` | 1件 |

列: `id, category, title, image, period, body, sort_order, created_at, updated_at`

### カテゴリ・タグ

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_categories()` | `array[]` | 全カテゴリ |
| `get_category($id_or_slug)` | `array?` | 1件 |
| `get_tags()` | `array[]` | 全タグ |

### 画像・動画

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `post_thumb_url($filename)` | `string?` | サムネ変種があればそのURL、なければ元画像URL |
| `upload_url($filename)` | `string?` | uploads 内の原寸画像URL |
| `video_embed_url($url)` | `string?` | YouTube / Vimeo のURLを iframe 用に変換（対応外は `null`） |

### 設定

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_setting($key, $default = null)` | `string?` | サイト設定の値 |

主要キー: `site_name`, `site_description`, `footer_text`, `posts_per_page`,
`public_site_url`, `public_article_url_pattern`

### ユーティリティ

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `h($str)` | `string` | XSS対策エスケープ（null / 数値も渡せる） |
| `db()` | `PDO` | DB接続のPDOインスタンス（生クエリを書きたい時） |
| `sluggify($text)` | `string` | 文字列を slug 化（日本語のみなら空文字） |
| `unique_slug($base, $table, $exclude_id, $fallback)` | `string` | 重複しない slug を作る |

### v2.0.0 で削除された関数

以下は**もう存在しません**。v1.x 向けのコードから移行する場合は置き換えが必要です。

| 削除された関数 | 代わりに |
|--------------|---------|
| `get_post_meta()` / `get_all_post_meta()` / `set_post_meta()` | `posts.period` / `type` / `external_url` / `video_url` を直接使う |
| `add_action()` / `do_action()` | （廃止。必要なら自分でフックを書く） |
| `add_filter()` / `apply_filters()` | （廃止） |
| `add_shortcode()` / `do_shortcodes()` | （廃止） |
| `theme_css_tag()` / `active_theme()` / `get_themes()` | （廃止。CSSは公開サイト側で管理する） |
| `log_action()` | （廃止） |
| `send_email()` | （廃止） |
| `require_admin()` / `user_role()` | `require_login()`（管理者は1人構成） |

---

## さらに進んだ用途

このガイドの範囲を超える話題:

- **REST API を足したい**（JS/Next/Astro 等から叩く） → `api/posts.php` を自作して `get_posts()` の結果を `json_encode`。CORS ヘッダと、書き込み許可するなら Bearer Token 認証を追加。
- **検索機能** — 現状 `admin/index.php?q=...` は管理画面用。公開側で全文検索が欲しい場合は MySQL の `LIKE` か、本格的にやるなら Meilisearch 等。
- **アクセス解析** — Takoyaki CMS は持たない。Google Analytics / Plausible 等を既存サイト側で導入。

困ったら [README](README.md) と [CHANGELOG](CHANGELOG.md)、それから
[samples/](samples/) と [router.php](router.php) の実装が良い参考になります。

---
---

# 付記: 開発評価と改善ロードマップ

> このセクションは「組み込みガイド」ではなく、**Takoyaki CMS 本体を完成させるための作業記録**です。
> 以降の修正作業に関するメモは、すべてここに追記していきます。

**評価日**: 2026-07-28
**対象**: v1.13.0（commit `ca563ac`）
**範囲**: PHP 30ファイル / 約5,200行 + schema.sql + ドキュメント一式を全読

## 総評

**完成度: 75%程度。「動くもの」としては十分に完成しているが、「配って安心なもの」まではあと2〜3歩。**

設計の骨格は素直で良い。PDOのプリペアドステートメント、CSRFトークン、権限チェック、
論理削除、監査ログ、フック/フィルター機構と、小規模CMSに必要な部品はほぼ揃っている。
ドキュメント（README / EMBEDDING / TESTING / CHANGELOG）の質は個人プロジェクトとしては
かなり高い水準。

一方で、**「一度も踏まれていない導線」に不具合が固まっている**のが今の状態。
並び替え、設定保存、カテゴリslug、ナビゲーション導線 — どれも「1回手で触れば気づく」
種類の欠陥が残っている。逆に言えば、修正コストは低く、直せば一気に完成度が上がる。

セキュリティは全体的に丁寧だが、**1箇所だけ明確な穴（A-1）**がある。ここは最優先。

| 領域 | 評価 | コメント |
|------|------|---------|
| セキュリティ設計 | ◯ | 方針は一貫。A-1 の1点だけが本物の穴 |
| 機能の網羅性 | ◯ | 小規模CMSとして必要十分。REST APIとコメントは意図的に非対応 |
| 動作の正確さ | △ | 並び替え・設定保存・カテゴリslugが期待通り動かない |
| 権限モデル | △ | admin/editor の境界が粗く、author_id が権限判定に未使用 |
| UI/導線 | △ | 2つの管理ページがメニューから到達不能 |
| コード品質 | ◯ | 読みやすい。ただし画像アップロード処理が3箇所コピペ |
| テスト | × | 自動テストゼロ。TESTING.md の手動手順のみ |
| ドキュメント | ◎ | このサイズのプロジェクトとしては例外的に充実 |

---

## A. セキュリティ（優先度: 最高）

### A-1. 記事編集画面で本文が未エスケープ → 管理画面の保存型XSS 🔴

`admin/post-edit.php:227`

```php
<textarea name="body" class="wysiwyg"><?= $post['body'] ?? '' ?></textarea>
```

本文に `</textarea><script>...</script>` を含む記事を保存すると、**次にその記事の編集画面を
開いた人のブラウザで任意のJSが動く**。CKEditor が初期化される前のHTMLソースの時点で
`</textarea>` がタグを閉じてしまうため、エディタ経由かどうかは関係ない。

editor ロールのユーザーが仕込み、admin が編集画面を開けば、CSRFトークンを盗んで
`admin/users.php` に新しい admin を追加するところまで到達できる。**権限昇格が成立する。**

- 同じ箇所の `admin/post-new.php:165` は `h($_POST['body'] ?? '')` と正しくエスケープ済み。**post-edit だけ抜けている**
- 修正は `h($post['body'] ?? '')` の1文字追加のみ。ブラウザが textarea の中身をデコードしてから
  CKEditor が読むので、表示は一切壊れない
- コード上のコメント（`<!-- WYSIWYGエディタの内容はHTMLのまま保存するため、h()でエスケープせずそのまま出力 -->`）は
  誤解に基づくもの。あわせて削除する

### A-2. 公開側の本文出力にサニタイズが無い（設計判断だが明示が必要）🟡

`samples/single.php:81` / `router.php:160` / `preview.php:127`

WYSIWYG の出力をそのまま echo している。これは「HTMLを書ける人が使う」前提なら妥当だが、
**現状 editor ロールは公開ページに任意のJSを埋め込める**。

- editor を信頼できない相手に配る運用をするなら HTMLPurifier 等が必要
- そうでないなら「**editor = HTML/JSを書ける権限である**」と README とこのガイドに明記する。
  今は書かれていないので、利用者が誤解する

### A-3. DB接続エラーで接続情報が画面に出る 🟡

`config.php:47`

```php
exit('DB接続エラー: ' . $e->getMessage());
```

本番でDB停止時に、ホスト名・DB名・ユーザー名が訪問者に見える。
`error_log()` に出し、画面には汎用メッセージを返すべき。

### A-4. `uploads/` に PHP 実行防止が無い 🟡

拡張子ホワイトリスト + `mime_content_type()` の二重チェックがあるので `.php` の直接アップロードは
防げている。ただし多層防御として `uploads/.htaccess` を同梱したい。

```apache
php_flag engine off
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps
AddType text/plain .php
```

### A-5. ログインフォームにCSRFトークンが無い 🟢

`login.php:96`。ログインCSRF（攻撃者のアカウントに強制ログインさせ、操作を攻撃者側に記録させる）。
実害は限定的だが、他の全フォームに付いているので整合性のためにも追加したい。

---

## B. 機能バグ（優先度: 高）

### B-1. 並び替え（↑↓）が実質まったく機能しない 🔴

3箇所の組み合わせで壊れている。

1. `schema.sql:45` — `sort_order INT NOT NULL DEFAULT 0`
2. `admin/post-new.php:70` — INSERT 文が `sort_order` を設定しない
3. → **全記事の `sort_order` が 0 になる**
4. `admin/index.php:95-96` — 隣接記事を `sort_order < :order` / `> :order` で探す
   → 全部同値なので常に0件 → ↑↓を押しても何も起きない

さらに `config.php:200` の `get_posts()` は既定 `order_by = 'sort_order'` なので、
**公開ページの記事順もMySQL任せ（不定）** になっている。

修正方針:
- INSERT時に `(SELECT COALESCE(MAX(sort_order),0)+1 FROM posts)` で採番
- 既存データ用に `migrations/v1.14.0.sql` で `id` 順の連番を振り直す
- swap ロジックは同値ケースのフォールバックを持たせる
- `get_posts()` の既定順を `published_at DESC` に変えることも検討（ブログ用途ならそちらが自然）

### B-2. 設定を保存しても画面が古い値を表示する 🔴

`config.php:307-322` の `get_setting()` は `static $cache` で全設定をキャッシュするが、
`set_setting()`（`config.php:324`）が**キャッシュを更新しない**。

`admin/settings.php` の流れ:
1. `:28` `$old = get_setting($key, '')` → ここで旧値がキャッシュに載る
2. `:30` `set_setting($key, $new)` → DBは更新される
3. `:49` `$settings[$key] = get_setting($key, '')` → **キャッシュヒットで旧値が返る**

結果、「設定を更新しました（1件）」と出るのに、フォームには旧値が表示される。
DBには正しく入っているので、リロードすれば直る — つまり **「保存できていないように見える」だけ**
だが、利用者は確実に混乱する。

コード内のコメント（`:46` `// 設定を再取得（cache は static なので、上の set_setting 後の値もこの関数経由で読める）`）は
事実と逆。`set_setting()` 内でキャッシュも書き換えるのが正しい修正。

### B-3. カテゴリの slug に数字が入る 🟠

`admin/categories.php:22-26` — 追加時に slug を空文字でINSERTし、直後に**採番されたIDを文字列として**
slug に書き戻している。

```php
$stmt->execute([':name' => $name, ':slug' => '']);
$newId = $pdo->lastInsertId();
$pdo->prepare('UPDATE categories SET slug = :slug WHERE id = :id')
    ->execute([':slug' => (string)$newId, ':id' => $newId]);
```

このため `/category/blog` のようなURLは**永久に作れず**、`/category/3` しか使えない。
`router.php:38` のカテゴリルートも、EMBEDDING.md の URL設計の章も、slug が意味のある文字列である
前提で書かれているので、ドキュメントと実装が矛盾している。

あわせて **カテゴリの編集機能が無い**（追加と削除だけ）のも埋めたい。
記事側は `sluggify()` を使っているので、カテゴリも同じ扱いにして、
日本語名の場合の扱い（B-4と共通）を決める。

### B-4. slug の重複と日本語タイトルが未解決 🟠

`config.php:104` の `sluggify()` は一意性を保証しない。

- 同名タイトルの2件目 → `uk_slug` 制約違反 → 「slug が既存と重複している可能性があります」
  というエラーで保存が拒否される（`post-new.php:84`）
- 日本語のみのタイトル → `preg_replace('/[^A-Za-z0-9]+/', '-', ...)` で全部消えて空 → `NULL`
  → 公開URLが `?id=1` に落ちる。**日本語サイト向けCMSとしてはこちらが既定動作になってしまう**

修正方針: 保存時に重複チェックし `-2` / `-3` を自動付与。日本語タイトルは
`post-{id}` にフォールバックするか、slug入力を必須にするかを決める。

### B-5. タイムゾーン未設定 + 公開判定が二重実装 🟠

`config.php` に `date_default_timezone_set()` が無く、PDO接続時の `SET time_zone` も無い。
PHP側は php.ini（多くの環境でUTC）、MySQL側はサーバのタイムゾーンで動く。

さらに公開判定が2通り実装されている:

| 場所 | 判定方法 |
|------|---------|
| `config.php:217` `get_posts()` / `:258` `get_post()` | SQL の `NOW()` |
| `config.php:674` `is_post_live()` | PHP の `time()` + `strtotime()` |
| `preview.php:41-42` | PHP の `time()` + `strtotime()` |
| `admin/index.php:137` | SQL 式 |

`config.php:254` には「PHP の time() と混在させるとタイムゾーン差で不整合になるため」という
コメントが明記されているのに、**その後に追加された `is_post_live()` がまさにそれをやっている**。

環境によっては管理画面の「公開中」バッジと実際の公開状態が9時間ずれる。
`date_default_timezone_set('Asia/Tokyo')` を config に置き、判定を1関数に統一する。

### B-6. `published_at` の入力を検証していない 🟠

`admin/post-new.php:33` / `admin/post-edit.php:67`

```php
$published_at = str_replace('T', ' ', $published_at_in) . ':00';
```

`datetime-local` の値をそのまま整形するだけで、形式チェックが無い。
curl等で任意文字列を送るとそのままSQLへ渡り、strict mode では例外 →
catch されて **「slug が既存と重複している可能性があります」という無関係なエラー**が出る。

`DateTime::createFromFormat('Y-m-d\TH:i', $in)` で検証し、失敗時は専用のエラーを返す。

### B-7. 記事を完全削除しても画像ファイルが残る 🟢

`admin/index.php:37`（一括purge）/ `:74`（個別purge）は `DELETE FROM posts` のみ。
サムネイル本体・`-thumb` 変種・本文内に挿入した画像がすべて `uploads/` に残り続ける。

メディアライブラリで「未使用」と表示されるので手動掃除はできるが、
purge 時にサムネイルだけでも削除するようにしたい（本文内画像の追跡は難しいので、
「未使用画像の一括削除」ボタンをメディアライブラリに付けるほうが現実的）。

---

## C. UI・導線（優先度: 高 — 「完成度」に直結）

### C-1. カテゴリ管理と監査ログにメニューから到達できない 🔴

`admin/_layout.php:47-61` のナビゲーションに **`categories.php` と `logs.php` へのリンクが無い**。
URL直打ちでしか開けない。

- `admin/categories.php` は記事のカテゴリ付けに必須の機能。TESTING.md 第5章がテスト対象にしている
- `admin/logs.php` も TESTING.md 第14章に手順があり、CHANGELOG では機能として謳っている
- README のディレクトリ構成には `categories.php` が載っているが、`logs.php` は載っていない（記載漏れ）

**最優先で直すべき「完成度の穴」**。ナビに2行足すだけ。

### C-2. カテゴリを1つしか選べない 🟠

DBは多対多（`post_categories` の複合主キー）で、`get_post_categories()` も配列を返す設計。
しかし `post-new.php:190` / `post-edit.php:267` の入力UIは単一選択の `<select>`。

複数選択（チェックボックス）にするか、「1記事1カテゴリ」と割り切ってドキュメントを実装に合わせるか、
方針を決める必要がある。今は**DBとUIで設計思想が食い違っている**状態。

### C-3. 新規作成画面にカスタムフィールドが無い 🟢

`admin/post-edit.php:283` にはカスタムフィールドの入力UIがあるが、`post-new.php` には無い。
新規作成時にメタを付けたければ、一度保存してから編集画面を開き直す必要がある。

### C-4. テーマのプレビューリンクが固定 🟢

`admin/themes.php:94` は常に `samples/index.php` を指す。
`public_site_url` を設定していてもそちらへ飛ばない（`_layout.php:22` は設定を尊重しているので、挙動が不統一）。

### C-5. その他の細かい点 🟢

- ゴミ箱に自動削除（例: 30日後にpurge）が無い。溜まり続ける
- ページネーションが全ページ番号を出力（`admin/index.php:295`）。記事1000件で50個のリンクが並ぶ
- `admin/media.php:44` — 画像1件ごとに使用状況をクエリするN+1。画像が増えると重くなる

---

## D. 権限モデル（優先度: 中）

### D-1. admin と editor の境界が粗い 🟠

| ページ | 現在のガード | 実際にできること |
|--------|------------|-----------------|
| `admin/index.php:7` | `require_login()` | **他人の記事を含む全記事の編集・削除・完全削除** |
| `admin/categories.php:7` | `require_login()` | カテゴリの追加・削除（サイト全体に影響） |
| `admin/tags.php:8` | `require_login()` | タグの削除（サイト全体に影響） |

`posts.author_id` はきちんと記録されているのに、**権限判定に一度も使われていない**。
「editor は自分の記事だけ」という一般的な期待とずれている。

方針を2択で決める:
- **A案**: editor は自分の記事のみ編集可（author_id で絞る）。カテゴリ/タグの削除は admin 限定
- **B案**: 現状維持だが、README に「editor = 記事に関する全権限を持つ信頼された編集者」と明記

### D-2. セッションの寿命管理が無い 🟠

- `config.php:69` — `'lifetime' => 0`（ブラウザを閉じるまで無期限）。アイドルタイムアウト無し
- パスワード変更時に他のセッションを無効化しない（`account.php` / `user-edit.php` / `reset-password.php`）
- 管理者が他人のパスワードをリセットしても、その人のセッションは生き続ける

### D-3. ログイン試行制限がIP単位のみ 🟢

`login.php:28` — IPごとに15分5回。

- 共有NAT/社内LANからだと**関係ない人が巻き添えでロックされる**
- 逆に分散した攻撃元からは無効
- `login_attempts` は成功時に**そのIPの行しか削除されない**ので、古い行が無限に溜まる（掃除処理なし）

アカウント単位のカウントを併用し、古い行を定期削除する。

---

## E. コード品質・保守性（優先度: 中）

### E-1. 画像アップロード処理が3箇所にコピペされている 🟠

| ファイル | 行 |
|---------|-----|
| `admin/post-new.php` | 42-65 |
| `admin/post-edit.php` | 76-104 |
| `admin/upload-image.php` | 26-51 |

拡張子・MIME・サイズの検証ロジックがそれぞれ独立している。
セキュリティルールを1箇所変えると他2箇所が置き去りになる典型的な構造。

`config.php` に `handle_image_upload(array $file, bool $make_thumb): array` を切り出して集約する。
**A-4 のような防御を後から追加するときにも効く。**

### E-2. `h()` の型宣言で PHP 8.1+ の Deprecated が出る 🟢

`config.php:58` — `function h(string $str): string`

null を渡す箇所が多数ある（例: `admin/index.php:243` の `h($post['deleted_at'])`）。
PHP 8.1 以降 `Deprecated: Passing null to parameter` の警告が出る。

`function h(?string $str): string { return htmlspecialchars((string)$str, ...); }` にするだけで解消。
int を渡している箇所（`h($post['id'])` 等）も多いので、`h($str)` を型なしにする案もある。

### E-3. `admin/media.php:76` で未エスケープ出力 🟢

```php
<div class="alert alert-info"><?= $info ?></div>
```

`$info` は `:29` で `h()` 済みなので現状は無害。しかし他の全ページは
`<?= h($info) ?>` の形なので、**規約が破れている箇所**として危うい。
`$info` に生の文字列を入れるように書き換えられた瞬間にXSSになる。

### E-4. 自動テストがゼロ 🟠

TESTING.md（16章・19KB）は手動手順書として非常によく出来ているが、
回帰チェックが完全に人力頼み。**このロードマップの修正を進めるほど、手動テストの負担が増える。**

依存を増やさない範囲で、`config.php` の純粋関数だけでも自動化できる:

- `sluggify()` / `thumb_filename()` / `do_shortcodes()` / `apply_filters()` / `add_filter()`
- `public_post_url()` / `is_post_live()` / `xml_escape()`

PHPUnit を入れずとも `tests/run.php` に簡易アサーションを書けば十分（`php tests/run.php` で走る）。
CMSの思想（依存を増やさない）とも矛盾しない。

### E-5. `config.php` と `config.example.php` の乖離 🟢

ロジックは同一だが、example 側にしか PHPDoc が無い（config.php 720行 / example 849行）。
実質2つのファイルを手で同期している状態。

`lib/functions.php` にロジックを切り出し、`config.php` は `define()` と `require` だけにすると
二重管理が消える。ただし「1ファイルを読めば全部わかる」という**学習用途としての利点は失われる**ので、
このCMSの思想と相談して決める（優先度は低い）。

---

## F. 良い点（変えないほうがいい）

修正作業で壊さないように、明示的に記録しておく。

- **PDOのプリペアドステートメントが全面的に一貫している**。動的SQLを組む箇所（`get_posts()` の
  `$where` 配列、`admin/index.php:28` の `IN (?)` プレースホルダ生成）でも値の直結合が一切ない
- **ホワイトリスト検証の徹底** — `config.php:205`（order_by のカラム名）、
  `admin/themes.php:22`（テーマ名）、`config.php:449`（プラグイン名の正規表現）、
  `admin/plugins.php:16`（保存前のフィルタ）
- **CSRF対策が丁寧** — `hash_equals()` での定数時間比較、GETのバックアップDLにまでトークンを要求
  （`admin/backup.php:16`）
- **パスワードリセットの実装が正しい** — 生トークンはメールのみ、DBには sha256、有効期限1時間、
  使用済みフラグ、同ユーザーの他トークンも一括失効（`reset-password.php:52`）
- **ユーザー列挙対策** — ログイン失敗メッセージの統一（`login.php:65`）、
  リセット申請の結果を常に同一表示（`forgot-password.php:41`）
- **最後の管理者を守るガード** — 削除（`users.php:65`）と降格（`users.php:87`）の両方
- **論理削除 + ゴミ箱 + 復元** が一貫して実装されている（`deleted_at IS NULL` が
  `get_posts()` / `get_post()` / 管理画面すべてで効いている）
- **mysqldump 不要のPHP製バックアップ** — 共有サーバーを想定した現実的な判断
- **ドキュメントの質** — CHANGELOG が Keep a Changelog 準拠で、
  各バージョンで何をなぜ変えたかが追える。個人プロジェクトとしては例外的な水準

---

## G. 完成までのロードマップ

優先度順。各フェーズはそれぞれ独立してリリース可能。

### Phase 1 — 落とし穴を塞ぐ（v1.14.0）

**「触れば気づくのに、まだ誰も触っていない」欠陥。ここを直すと体感の完成度が一番上がる。**

| # | 内容 | 規模 |
|---|------|------|
| A-1 | post-edit.php の本文を `h()` でエスケープ | 1行 |
| C-1 | ナビに カテゴリ / 監査ログ を追加 | 数行 |
| B-2 | `set_setting()` でキャッシュを更新 | 数行 |
| B-1 | `sort_order` の採番 + マイグレーション | 小 |
| A-3 | DB接続エラーの詳細を画面に出さない | 数行 |
| A-4 | `uploads/.htaccess` を同梱 | 新規ファイル |

### Phase 2 — データの正しさ（v1.15.0）

| # | 内容 | 規模 |
|---|------|------|
| B-5 | タイムゾーン設定 + 公開判定の一本化 | 小 |
| B-3 | カテゴリ slug の正常化 + カテゴリ編集機能 | 中 |
| B-4 | slug 重複の自動解決 + 日本語タイトルの方針決定 | 中 |
| B-6 | `published_at` の入力検証 | 小 |
| B-7 | purge 時のサムネイル削除 / 未使用画像の一括削除 | 小 |

### Phase 3 — 権限とUI（v1.16.0）

| # | 内容 | 規模 |
|---|------|------|
| D-1 | editor 権限の境界を決めて実装（A案/B案） | 中 |
| C-2 | カテゴリ複数選択 or 単一に統一 | 中 |
| C-3 | 新規作成画面にカスタムフィールド | 小 |
| D-2 | セッションのアイドルタイムアウト + パスワード変更時の失効 | 中 |
| C-4 / C-5 | テーマプレビューリンク、ゴミ箱の自動削除、ページネーション省略表示 | 小 |

### Phase 4 — 品質の底上げ（v1.17.0）

| # | 内容 | 規模 |
|---|------|------|
| E-1 | 画像アップロード処理の共通化 | 中 |
| E-4 | `tests/run.php` で純粋関数の自動テスト | 中 |
| E-2 / E-3 | `h()` の nullable 化、`media.php` のエスケープ統一 | 小 |
| A-5 | ログインフォームのCSRFトークン | 小 |
| D-3 | ログイン試行制限のアカウント単位併用 + 古い行の掃除 | 小 |
| A-2 | 「editor は HTML を書ける」旨をドキュメントに明記 | 文書 |

### 対象外（意図的にやらないこと）

- REST API / コメント機能 / 多言語化 — README で明示的に非対応と宣言済み。方針を維持
- E-5（config の分割）— 「1ファイルで読める」学習用途の利点とのトレードオフ。急がない

---

## 作業ログ

以降、修正を進めるたびにここへ追記する。

### 2026-07-28 (1) 初回評価

- 初回評価を実施（v1.13.0 / commit `ca563ac`）。上記 A〜G を記録

### 2026-07-28 (2) 方針転換 — ポートフォリオCMSへの特化

上の A〜G は「汎用CMSとして完成させる」前提の評価だった。方針を転換する。

---

# 付記2: v2.0.0 リデザイン計画（ポートフォリオCMS特化）

## 経緯

このCMSは `myportfolio/cms`（ポートフォリオ専用CMS）を汎用化する形で作られた。
しかし汎用化の過程で、**元々できていた「作品紹介」「スキル紹介」が逆にできなくなっていた**。

| | 元 `myportfolio/cms` | 現 `takoyaki-cms` v1.13.0 |
|---|---|---|
| 作品のメタ情報 | `period` / `type` / `external_url` / `video_url` | **全部無い** |
| 作品詳細の本文 | `post_sections`（見出し＋本文の繰り返し） | CKEditor の単一 `body` |
| スキル紹介 | `skill` テーブル + 専用管理画面 | **無い** |
| 汎用機能 | 無い | テーマ / プラグイン / RSS / ゴミ箱 / 監査ログ / ロール… |

WordPress を目指した結果、**必要なものが消えて、使わないものが増えた**状態。
v2.0.0 で元の目的地に戻す。

## 決定事項

| 論点 | 決定 |
|------|------|
| 位置づけ | **ポートフォリオCMSに特化**。Works（作品）+ Skills（スキル）の2本柱 |
| 拡張機構 | 削除（プラグイン / テーマ / ショートコード） |
| 配信系 | 削除（RSS / sitemap / router.php / .htaccess） |
| 運用系 | 削除（監査ログ / DBバックアップ / ゴミ箱） |
| チーム運用 | 削除（複数ユーザー / ロール / パスワードリセット）→ **管理者1人構成** |
| 作品詳細の本文 | **セクション式を復活**（`post_sections`） |

## 削除するもの

### ファイル

```
feed.php                 sitemap.php              router.php
.htaccess.example        forgot-password.php      reset-password.php
admin/logs.php           admin/backup.php         admin/users.php
admin/user-edit.php      admin/plugins.php        admin/themes.php
plugins/                 themes/
samples/index.php        samples/category.php     samples/README.md
```

### `config.php` から削除する関数

```
add_action / do_action / add_filter / apply_filters
add_shortcode / do_shortcodes
get_enabled_plugins / scan_plugins / load_plugins
get_themes / active_theme / get_theme_meta / theme_css_url / theme_css_tag
log_action / send_email / require_admin / user_role
```

> **実測（着手後に追記）**: 行数はほとんど減らなかった。
> 汎用機構を削った一方で、`handle_image_upload()` / `unique_slug()` /
> `parse_datetime_local()` / `video_embed_url()` / スキル系ヘルパーを足し、
> さらに従来 `config.example.php` にしか無かった PHPDoc を本体にも入れたため。
> ドキュメント込みで比較すると **849行（旧 example）→ 838行**。
> 代わりに `config.php` と `config.example.php` が完全に同一ロジックになり、
> **E-5 の二重管理は解消した**（`config.php` は example から生成するだけになった）。

### テーブル

| テーブル | 理由 |
|---------|------|
| `audit_logs` | 監査ログ削除に伴う。1人運用では意味が無い |
| `password_resets` | パスワードリセット削除に伴う |
| `post_meta` | `period` / `type` / `external_url` / `video_url` を**実カラムにする**ので役割が消える |
| `users.role` カラム | 管理者1人構成のため |
| `posts.deleted_at` カラム | ゴミ箱削除に伴う |
| `posts.body` カラム | `post_sections` に移すため |
| `posts.author_id` カラム | 1人構成のため |

> `post_meta` は削除候補に挙げていなかったが、専用カラムを持たせる以上は完全な重複になるため
> 削除する前提で進める。残したい場合は着手前に指摘してほしい。

## 残すもの

判断の根拠を明示しておく（あとで「なぜ残した」と迷わないため）。

| 機能 | 残す理由 |
|------|---------|
| `tags` / `post_tags` テーブル | 「使用技術タグ」はポートフォリオの中核。元CMSはカンマ区切り文字列だったが、正規化済みの現行のほうが良い |
| `categories` / `post_categories` | works のカテゴリフィルターに必要 |
| CKEditor 5 | セクション本文の入力に引き続き使う |
| 画像リサイズ・メディアライブラリ | 作品サムネイル / スキルアイコンで必要 |
| 予約公開・プレビュー | 実務で使う。残す |
| `site_settings` | サイト名 / 説明 / 公開URL に使う |
| `login_attempts` | ログイン試行制限。管理画面が1つしか無いぶん重要度は上がる |

## 新しいDB設計

```sql
users           id, username, password, email, created_at
                -- role カラムを削除（管理者1人）

posts           id, title, slug, thumbnail, excerpt, status, published_at,
                period, type, external_url, video_url,
                sort_order, created_at, updated_at
                -- body / author_id / deleted_at を削除
                -- period / type / external_url / video_url を追加（元CMSから復活）

post_sections   id, post_id, sort_order, title, body        ★復活
                -- 「見出し＋本文」の繰り返しで作品詳細を構成

skills          id, category, title, image, period, body, sort_order   ★新規
                -- category: プログラミング / デザイン / その他

categories      id, name, slug
post_categories post_id, category_id
tags            id, name, slug
post_tags       post_id, tag_id
site_settings   key, value, updated_at
login_attempts  id, ip_address, attempted_at
```

元CMSからの差分:

- `skill` → `skills`（複数形に統一）、`image_url`（自由URL）→ `image`（uploads内のファイル名）に変更。
  `posts.thumbnail` と扱いを揃え、メディアライブラリから一元管理できるようにするため
- `skills.sort_order` を追加（元は `ORDER BY id` 固定で並べ替え不可だった）
- `posts.slug` は takoyaki 側の資産なので維持（元CMSは `?id=` のみだった）

## 新しいディレクトリ構成

```
takoyaki-cms/
├── config.php              # DB接続 + ヘルパー（約350行に減量）
├── schema.sql              # v2.0.0 で作り直し
├── migrations/v2.0.0.sql   # v1.13.0 → v2.0.0
├── setup.php  login.php  logout.php  preview.php
├── admin/
│   ├── index.php        # 作品一覧
│   ├── post-new.php     # 作品 新規（セクション入力対応）
│   ├── post-edit.php    # 作品 編集（セクション入力対応）
│   ├── skill.php        ★ スキル一覧
│   ├── skill-edit.php   ★ スキル 新規/編集
│   ├── categories.php  tags.php  media.php
│   ├── settings.php  account.php  upload-image.php
│   └── _layout.php  admin.css
├── samples/
│   ├── works.php        # 作品一覧（カテゴリフィルター）
│   ├── single.php       # 作品詳細（動画 + セクション）
│   └── skill.php        ★ スキル一覧（カテゴリ別グリッド）
└── uploads/
```

## 作業手順

| Step | 内容 | 状態 |
|------|------|------|
| 1 | 不要ファイル・ディレクトリの削除 | ✅ |
| 2 | `schema.sql` 作り直し + `migrations/v2.0.0.sql` | ✅ |
| 3 | `config.php` / `config.example.php` の減量とヘルパー追加 | ✅ |
| 4 | `admin/_layout.php` のナビ再構成（ロール分岐の除去、スキル追加） | ✅ |
| 5 | 作品の新規/編集にセクションUI + 作品メタ欄を実装 | ✅ |
| 6 | `admin/skill.php` / `admin/skill-edit.php` の新規実装 | ✅ |
| 7 | 公開ページ `samples/works.php` / `single.php` / `skill.php` | ✅ |
| 8 | 初回評価の A〜C の不具合を同時に修正 | ✅ |
| 9 | README / TESTING / CHANGELOG の更新 | ✅ |

### Step 8 で同時に潰す既知の不具合

v2.0.0 でファイルを触るついでに、初回評価で挙げた不具合のうち該当するものを直す。

| # | 内容 | 対応 |
|---|------|------|
| A-1 | post-edit の本文が未エスケープ（保存型XSS） | セクション本文を `h()` で出力 |
| A-3 | DB接続エラーで接続情報が漏れる | `error_log()` + 汎用メッセージ |
| A-4 | uploads に PHP 実行防止が無い | `uploads/.htaccess` を同梱 |
| A-5 | ログインフォームにCSRFが無い | `csrf_field()` を追加 |
| B-1 | sort_order が全部0で並び替えが効かない | INSERT時に採番 |
| B-2 | 設定保存後に旧値が表示される | `set_setting()` でキャッシュ更新 |
| B-3 | カテゴリ slug が数字になる | `sluggify()` + 重複解決を使う |
| B-4 | slug 重複で保存できない | `unique_slug()` を新設して自動採番 |
| B-5 | タイムゾーン未設定 / 公開判定の二重実装 | `date_default_timezone_set()` + 判定を1関数に |
| B-6 | published_at が未検証 | `DateTime::createFromFormat()` で検証 |
| B-7 | 完全削除で画像が残る | 削除時にサムネイルも消す |
| C-1 | カテゴリ管理がナビから到達不能 | ナビ再構成で解決 |
| C-2 | カテゴリが1つしか選べない | チェックボックスで複数選択に |
| C-3 | 新規作成にカスタムフィールドが無い | セクションUIを新規/編集の両方に実装 |
| E-1 | 画像アップロード処理が3箇所コピペ | `handle_image_upload()` に集約 |
| E-2 | `h()` が null で Deprecated | `?string` に変更 |

D-1（editor権限）/ D-2（セッション寿命）/ D-3（試行制限）は、
**ロール自体を削除したことで D-1 は消滅**。D-2 / D-3 は v2.1.0 以降に持ち越す。

---

## 実装結果（2026-07-28）

Step 1〜9 を実施し、**v2.0.0 として完了**。

### 検証したこと

ローカルの MAMP（PHP 8.5 / MySQL 8.0）で以下を確認した。

| 検証 | 結果 |
|------|------|
| 全PHPファイルの構文チェック | 30ファイル エラーなし |
| 削除した機能への参照が残っていないか（grep） | 残存なし |
| `config.php` の純粋関数（slug / 日時 / 動画URL / 公開判定） | 22件 全て通過 |
| `schema.sql` で新規DB作成 → CRUD一式 | 21件 全て通過 |
| `migrations/v2.0.0.sql`（v1.13.0 の実データに対して） | 通過。本文5件が `post_sections` に移行、`sort_order` が 1〜5 に採番、カテゴリ slug が `cat-2` / `cat-3` に正常化、`users.role` 削除を確認 |
| 全画面のレンダリング（開発サーバ + curl） | 管理13画面 + 公開3画面すべて 200、**PHPの警告・Notice ゼロ** |
| 作品の保存 → 公開ページ表示の一連の流れ | 通過。動画埋め込み・制作期間・種別・セクション見出し・外部リンクが公開ページに出ることを確認 |

### A-1（保存型XSS）の修正確認

本文セクションに `<p>ふつうの本文</p></textarea><script>alert(1)</script>` を保存し、
編集画面を再度開いて出力を確認した。

```html
<textarea name="section_body[]" class="wysiwyg">&lt;p&gt;ふつうの本文&lt;/p&gt;&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;</textarea>
```

エスケープされており、textarea を抜けられないことを確認。**修正済み**。

### 実行したDB操作

- ローカルの `takoyaki_test` に `migrations/v2.0.0.sql` を適用済み
- 適用前のダンプをスクラッチ領域に退避（セッション終了で消えるため、
  長期保存したい場合は改めて `mysqldump` を取ること）
- 検証用に作った `takoyaki_v2_test` / `takoyaki_mig_test` と、
  スモークテスト用のユーザー・作品は削除済み

### 積み残し

| 項目 | 状態 |
|------|------|
| **EMBEDDING.md 本文の v2.0.0 対応** | **未着手**。付録のチートシートと冒頭の注意書きのみ更新した。RSS / router.php / テーマ / post_meta の章がまだ残っている |
| **TESTING.md の v2.0.0 対応** | **未着手**。16章のうち、テーマ・プラグイン・ゴミ箱・ユーザー管理・監査ログ・バックアップ・パスワードリセットの章が対象外の機能を指している |
| D-2 セッションのアイドルタイムアウト | v2.1.0 へ |
| D-3 ログイン試行制限のアカウント単位併用 | v2.1.0 へ（古い行の掃除だけ先に入れた） |
| E-4 自動テスト（`tests/run.php`） | v2.1.0 へ。今回の検証は使い捨てスクリプトで行ったため、リポジトリには残っていない |
| C-5 ゴミ箱の自動削除 | ゴミ箱ごと廃止したため**不要になった** |

### 次にやると効果が大きいこと

1. **TESTING.md の作り直し** — 手動テストが今の実装と合っていないと、
   これ以降の変更で何を確認すればいいか分からなくなる
2. **E-4 の自動テスト化** — 今回 43件のアサーションで検証したが使い捨てにした。
   `tests/run.php` として残せば、次の変更から再利用できる
3. **EMBEDDING.md 本文の書き直し** — 既存サイトへの組み込みを実際にやる段階で
