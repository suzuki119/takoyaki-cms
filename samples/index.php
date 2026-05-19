<?php
// ===================================================
//  サンプル: 記事一覧ページ
//  自分のサイトのトップページ等にコピーして使ってください。
// ===================================================
require_once __DIR__ . '/../config.php';

// 公開中の記事を取得（最新10件）
$posts = get_posts([
    'order_by' => 'published_at',
    'order'    => 'DESC',
    'limit'    => 10,
]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>記事一覧 | サンプルサイト</title>
    <meta name="description" content="Takoyaki CMS のサンプルテンプレートです。">
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 40px 20px; line-height: 1.7; color: #333; }
        h1 { font-size: 1.8rem; border-bottom: 2px solid #eee; padding-bottom: 12px; }
        article { padding: 24px 0; border-bottom: 1px solid #eee; display: flex; gap: 20px; }
        article .thumb { flex: 0 0 200px; }
        article .thumb img { width: 100%; height: auto; border-radius: 4px; }
        article .body { flex: 1; }
        article h2 { margin: 0 0 8px; font-size: 1.2rem; }
        article h2 a { color: #333; text-decoration: none; }
        article h2 a:hover { color: #2980b9; }
        article .meta { font-size: .85rem; color: #888; margin-bottom: 8px; }
        article .excerpt { font-size: .95rem; color: #555; margin: 0; }
        .empty { padding: 40px; text-align: center; color: #999; }
        nav { font-size: .85rem; margin-top: 32px; }
        nav a { color: #2980b9; margin-right: 16px; }
    </style>
</head>
<body>
    <h1>記事一覧</h1>

    <?php if (empty($posts)): ?>
        <p class="empty">公開中の記事はまだありません。</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article>
                <?php $thumb = post_thumb_url($post['thumbnail']); ?>
                <?php if ($thumb): ?>
                    <div class="thumb">
                        <a href="single.php?id=<?= h($post['id']) ?>">
                            <img src="<?= h($thumb) ?>" alt="">
                        </a>
                    </div>
                <?php endif; ?>
                <div class="body">
                    <h2>
                        <a href="single.php?id=<?= h($post['id']) ?>"><?= h($post['title']) ?></a>
                    </h2>
                    <p class="meta">
                        <?= h($post['published_at'] ?? $post['created_at']) ?>
                    </p>
                    <?php if (!empty($post['excerpt'])): ?>
                        <p class="excerpt"><?= h($post['excerpt']) ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <nav>
        <a href="<?= SITE_URL ?>/feed.php">RSS フィード</a>
        <a href="<?= SITE_URL ?>/sitemap.php">サイトマップ</a>
    </nav>
</body>
</html>
