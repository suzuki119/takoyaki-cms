<?php
// ===================================================
//  サンプル: カテゴリ別記事一覧
//  URL例: category.php?id=1   または   category.php?slug=blog
// ===================================================
require_once __DIR__ . '/../config.php';

$key      = $_GET['slug'] ?? $_GET['id'] ?? null;
$category = $key ? get_category($key) : null;

if (!$category) {
    http_response_code(404);
    echo '<h1>カテゴリが見つかりません</h1>';
    exit;
}

$posts = get_posts([
    'category_id' => (int)$category['id'],
    'order_by'    => 'published_at',
    'order'       => 'DESC',
    'limit'       => 20,
]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= h($category['name']) ?> | カテゴリ</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 40px 20px; line-height: 1.7; color: #333; }
        h1 { font-size: 1.5rem; border-bottom: 2px solid #eee; padding-bottom: 12px; }
        h1 small { color: #888; font-weight: normal; font-size: .9rem; margin-left: 8px; }
        article { padding: 20px 0; border-bottom: 1px solid #eee; }
        article h2 { margin: 0 0 4px; font-size: 1.1rem; }
        article h2 a { color: #333; text-decoration: none; }
        article h2 a:hover { color: #2980b9; }
        article .meta { font-size: .85rem; color: #888; }
        article .excerpt { font-size: .95rem; color: #555; margin: 8px 0 0; }
        .empty { padding: 40px; text-align: center; color: #999; }
        .nav { margin-top: 32px; font-size: .85rem; }
        .nav a { color: #2980b9; }
    </style>
    <?= theme_css_tag() ?>
</head>
<body>
    <h1>
        <?= h($category['name']) ?>
        <small><?= count($posts) ?> 件の記事</small>
    </h1>

    <?php if (empty($posts)): ?>
        <p class="empty">このカテゴリの公開中の記事はまだありません。</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article>
                <h2><a href="single.php?id=<?= h($post['id']) ?>"><?= h($post['title']) ?></a></h2>
                <p class="meta"><?= h($post['published_at'] ?? $post['created_at']) ?></p>
                <?php if (!empty($post['excerpt'])): ?>
                    <p class="excerpt"><?= h($post['excerpt']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="nav"><a href="index.php">← 記事一覧へ</a></p>
</body>
</html>
