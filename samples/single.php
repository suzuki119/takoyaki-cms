<?php
// ===================================================
//  サンプル: 記事詳細ページ
//  URL例: single.php?id=1   または   single.php?slug=my-post
// ===================================================
require_once __DIR__ . '/../config.php';

$key  = $_GET['slug'] ?? $_GET['id'] ?? null;
$post = $key ? get_post($key) : null;

if (!$post) {
    http_response_code(404);
    echo '<h1>記事が見つかりません</h1>';
    exit;
}

$categories = get_post_categories((int)$post['id']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= h($post['title']) ?></title>
    <?php if (!empty($post['excerpt'])): ?>
        <meta name="description" content="<?= h($post['excerpt']) ?>">
    <?php endif; ?>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 40px 20px; line-height: 1.8; color: #333; }
        h1 { font-size: 2rem; line-height: 1.3; }
        .meta { color: #888; font-size: .9rem; margin-bottom: 32px; }
        .categories span { display: inline-block; background: #eaeaea; padding: 2px 10px; border-radius: 3px; margin-right: 4px; font-size: .8rem; }
        .thumbnail { max-width: 100%; height: auto; margin-bottom: 32px; }
        .excerpt { font-size: 1.05rem; color: #555; border-left: 4px solid #ddd; padding: 8px 16px; margin-bottom: 32px; }
        .body img { max-width: 100%; height: auto; }
        .body table { border-collapse: collapse; }
        .body table th, .body table td { border: 1px solid #ccc; padding: 8px 12px; }
        .nav { margin-top: 48px; padding-top: 24px; border-top: 1px solid #eee; font-size: .85rem; }
        .nav a { color: #2980b9; }
    </style>
</head>
<body>

<article>
    <h1><?= h(apply_filters('the_title', $post['title'], $post)) ?></h1>

    <p class="meta">
        <?= h($post['published_at'] ?? $post['created_at']) ?>
        <?php if (!empty($categories)): ?>
            <span class="categories">
                <?php foreach ($categories as $cat): ?>
                    <span><?= h($cat['name']) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
    </p>

    <?php if (!empty($post['thumbnail'])): ?>
        <img class="thumbnail" src="<?= UPLOAD_URL . h($post['thumbnail']) ?>" alt="">
    <?php endif; ?>

    <?php if (!empty($post['excerpt'])): ?>
        <p class="excerpt"><?= h($post['excerpt']) ?></p>
    <?php endif; ?>

    <div class="body">
        <?php
            // プラグインによる本文加工チェーン + ショートコード展開
            $body = $post['body'] ?? '';
            $body = apply_filters('the_content', $body, $post);
            $body = do_shortcodes($body);
            echo $body;
        ?>
    </div>
</article>

<p class="nav"><a href="index.php">← 記事一覧へ</a></p>

</body>
</html>
