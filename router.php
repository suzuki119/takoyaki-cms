<?php
// ===================================================
//  フロントコントローラ（きれいなURL対応）
//  .htaccess で実ファイル以外のリクエストをここに集約し、
//  /post/slug, /category/slug, /tag/slug, / を振り分ける。
//
//  .htaccess を使わない環境でも router.php?p=/post/slug でテスト可能。
//  このファイルは「公開サイトの実装例」です。デザインは自由に編集してください。
// ===================================================
require_once __DIR__ . '/config.php';

// ---- パスの決定 ----
// 1) ?p=/post/slug が来ていればそれを優先（mod_rewrite無し環境のフォールバック）
// 2) なければ REQUEST_URI から SITE_URL のパス部分を差し引いた残り
if (isset($_GET['p'])) {
    $path = '/' . trim((string)$_GET['p'], '/');
} else {
    $base_path = rtrim(parse_url(SITE_URL, PHP_URL_PATH) ?? '', '/');
    $req_path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $rel       = substr($req_path, strlen($base_path));
    $path      = '/' . trim((string)$rel, '/');
}
// router.php 自身への直アクセスはトップ扱い
if ($path === '/router.php' || $path === '') {
    $path = '/';
}

$site_name = get_setting('site_name', 'Takoyaki CMS Site');

// ---- ルーティング ----
$route = '404';
$param = null;
if ($path === '/') {
    $route = 'home';
} elseif (preg_match('#^/post/([^/]+)$#', $path, $m)) {
    $route = 'single';
    $param = urldecode($m[1]);
} elseif (preg_match('#^/category/([^/]+)$#', $path, $m)) {
    $route = 'category';
    $param = urldecode($m[1]);
} elseif (preg_match('#^/tag/([^/]+)$#', $path, $m)) {
    $route = 'tag';
    $param = urldecode($m[1]);
}

// 個別記事URLを組み立てるヘルパー（テンプレートから使用）
function url_for_post(array $post): string
{
    $key = !empty($post['slug']) ? $post['slug'] : $post['id'];
    return SITE_URL . '/post/' . rawurlencode((string)$key);
}
function url_for_category(array $cat): string
{
    $key = !empty($cat['slug']) ? $cat['slug'] : $cat['id'];
    return SITE_URL . '/category/' . rawurlencode((string)$key);
}
function url_for_tag(array $tag): string
{
    $key = !empty($tag['slug']) ? $tag['slug'] : $tag['id'];
    return SITE_URL . '/tag/' . rawurlencode((string)$key);
}

// ---- データ取得 ----
$page_title = $site_name;
$post = $category = $tag = null;
$posts = [];

if ($route === 'home') {
    $posts = get_posts([
        'order_by' => 'published_at',
        'order'    => 'DESC',
        'limit'    => (int)get_setting('posts_per_page', '10'),
    ]);
} elseif ($route === 'single') {
    $post = get_post($param);
    if (!$post) { $route = '404'; }
    else { $page_title = $post['title'] . ' | ' . $site_name; }
} elseif ($route === 'category') {
    $category = get_category($param);
    if (!$category) { $route = '404'; }
    else {
        $page_title = $category['name'] . ' | ' . $site_name;
        $posts = get_posts(['category_id' => (int)$category['id'], 'order_by' => 'published_at', 'order' => 'DESC']);
    }
} elseif ($route === 'tag') {
    // タグはslug/idでtagを引いてから記事を絞る
    $all = get_tags();
    foreach ($all as $t) {
        if ($t['slug'] === $param || (string)$t['id'] === (string)$param) { $tag = $t; break; }
    }
    if (!$tag) { $route = '404'; }
    else {
        $page_title = '#' . $tag['name'] . ' | ' . $site_name;
        $stmt = db()->prepare(
            "SELECT p.* FROM posts p
               INNER JOIN post_tags pt ON pt.post_id = p.id
              WHERE pt.tag_id = :tid
                AND p.deleted_at IS NULL
                AND p.status = 'published'
                AND (p.published_at IS NULL OR p.published_at <= NOW())
              ORDER BY p.published_at DESC"
        );
        $stmt->execute([':tid' => $tag['id']]);
        $posts = $stmt->fetchAll();
    }
}

if ($route === '404') {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?></title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 40px 20px 60px; line-height: 1.7; color: #333; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1 { font-size: 1.8rem; }
        .home-link { font-size: .85rem; }
        article.list-item { padding: 20px 0; border-bottom: 1px solid #eee; }
        article.list-item h2 { margin: 0 0 4px; font-size: 1.2rem; }
        .meta { color: #888; font-size: .85rem; }
        .tags a, .cats a { display: inline-block; background: #eef2ff; color: #3730a3; padding: 2px 8px; border-radius: 3px; font-size: .8rem; margin-right: 4px; }
        .tags a { background: #e0f2fe; color: #0369a1; }
        .body img { max-width: 100%; height: auto; }
        .excerpt { color: #555; }
        .empty { color: #999; padding: 40px 0; }
    </style>
    <?= theme_css_tag() ?>
</head>
<body>

<p class="home-link"><a href="<?= h(SITE_URL) ?>/"><?= h($site_name) ?></a></p>

<?php if ($route === '404'): ?>

    <h1>404 Not Found</h1>
    <p>ページが見つかりませんでした。</p>

<?php elseif ($route === 'single'): ?>

    <article>
        <h1><?= h(apply_filters('the_title', $post['title'], $post)) ?></h1>
        <p class="meta">
            <?= h($post['published_at'] ?? $post['created_at']) ?>
            <?php $cats = get_post_categories((int)$post['id']); $ptags = get_post_tags((int)$post['id']); ?>
            <?php if ($cats): ?><span class="cats"> <?php foreach ($cats as $c): ?><a href="<?= h(url_for_category($c)) ?>"><?= h($c['name']) ?></a><?php endforeach; ?></span><?php endif; ?>
            <?php if ($ptags): ?><span class="tags"> <?php foreach ($ptags as $t): ?><a href="<?= h(url_for_tag($t)) ?>">#<?= h($t['name']) ?></a><?php endforeach; ?></span><?php endif; ?>
        </p>
        <?php if (!empty($post['thumbnail'])): ?>
            <img src="<?= h(UPLOAD_URL . $post['thumbnail']) ?>" alt="" style="max-width:100%;height:auto;">
        <?php endif; ?>
        <div class="body">
            <?php
                $body = apply_filters('the_content', $post['body'] ?? '', $post);
                echo do_shortcodes($body);
            ?>
        </div>
    </article>

<?php else: // home / category / tag （一覧系） ?>

    <h1>
        <?php if ($route === 'category'): ?>カテゴリ: <?= h($category['name']) ?>
        <?php elseif ($route === 'tag'): ?>タグ: #<?= h($tag['name']) ?>
        <?php else: ?><?= h($site_name) ?><?php endif; ?>
    </h1>

    <?php if (empty($posts)): ?>
        <p class="empty">記事がありません。</p>
    <?php else: ?>
        <?php foreach ($posts as $p): ?>
            <article class="list-item">
                <h2><a href="<?= h(url_for_post($p)) ?>"><?= h($p['title']) ?></a></h2>
                <p class="meta"><?= h($p['published_at'] ?? $p['created_at']) ?></p>
                <?php if (!empty($p['excerpt'])): ?>
                    <p class="excerpt"><?= h($p['excerpt']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>
