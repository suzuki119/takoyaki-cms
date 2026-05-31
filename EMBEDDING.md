# Takoyaki CMS 組み込みガイド

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

`config.php` を require すると使えるようになる関数の一覧（公開側で使う頻度順）。

### 記事

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_posts($opts = [])` | `array[]` | 公開記事一覧 |
| `get_post($id_or_slug)` | `array?` | 1件取得（ID または slug） |
| `get_post_categories($post_id)` | `array[]` | 記事に紐付くカテゴリ |
| `get_post_tags($post_id)` | `array[]` | 記事に紐付くタグ |
| `get_all_post_meta($post_id)` | `array` | カスタムフィールド全件 |
| `get_post_meta($post_id, $key, $single = true)` | `mixed` | 特定キーの値 |

**`get_posts()` のオプション**:

| キー | 型 | 既定 | 説明 |
|------|----|------|------|
| `limit` | `int?` | `null` | 最大件数 |
| `offset` | `int` | `0` | ページネーション用 |
| `category_id` | `int?` | `null` | カテゴリで絞り込み |
| `order_by` | `string` | `'sort_order'` | `sort_order` / `created_at` / `published_at` / `updated_at` / `id` / `title` のみ可（他はデフォルトにフォールバック） |
| `order` | `string` | `'ASC'` | `ASC` / `DESC` |
| `include_drafts` | `bool` | `false` | 下書き・予約公開も含めるか（プレビュー用） |

**`get_posts()` / `get_post()` の戻り値の列**:

`id, title, slug, body, excerpt, thumbnail, status, published_at, author_id, sort_order, deleted_at, created_at, updated_at`

### カテゴリ・タグ

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_categories()` | `array[]` | 全カテゴリ |
| `get_category($id_or_slug)` | `array?` | 1件 |
| `get_tags()` | `array[]` | 全タグ |

### 画像

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `post_thumb_url($filename)` | `string?` | サムネ変種があればそのURL、なければ元画像URL |

### 設定

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `get_setting($key, $default = null)` | `string?` | サイト設定の値 |

主要キー: `site_name`, `site_description`, `footer_text`, `posts_per_page`, `active_theme`

### ユーティリティ

| 関数 | 戻り値 | 用途 |
|------|--------|------|
| `h($str)` | `string` | XSS対策エスケープ（`htmlspecialchars`の短縮） |
| `db()` | `PDO` | DB接続のPDOインスタンス（生クエリを書きたい時） |

### 拡張（プラグインや高度な用途）

| 関数 | 用途 |
|------|------|
| `add_action($action, $callback, $priority = 10)` | 記事保存等のタイミングでコールバック登録 |
| `add_filter($filter, $callback, $priority = 10)` | コンテンツ加工 |
| `add_shortcode($tag, $callback)` | `[shortcode]` を本文中で展開 |

発火点: `post.save`, `post.delete`, `login.success`
フィルター: `the_content`, `the_title`（`samples/single.php` で適用）

---

## さらに進んだ用途

このガイドの範囲を超える話題:

- **REST API を足したい**（JS/Next/Astro 等から叩く） → `api/posts.php` を自作して `get_posts()` の結果を `json_encode`。CORS ヘッダと、書き込み許可するなら Bearer Token 認証を追加。
- **検索機能** — 現状 `admin/index.php?q=...` は管理画面用。公開側で全文検索が欲しい場合は MySQL の `LIKE` か、本格的にやるなら Meilisearch 等。
- **アクセス解析** — Takoyaki CMS は持たない。Google Analytics / Plausible 等を既存サイト側で導入。

困ったら [README](README.md) と [CHANGELOG](CHANGELOG.md)、それから
[samples/](samples/) と [router.php](router.php) の実装が良い参考になります。
