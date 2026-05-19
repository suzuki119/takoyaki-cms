<?php
// ===================================================
//  記事プレビュー（ログイン必須）
//  下書き・予約投稿の記事を実際の見た目で確認するための画面。
//  公開ページ用テンプレートのサンプルも兼ねている。
// ===================================================
require_once 'config.php';
require_login();

$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    exit('記事が見つかりません。');
}

// この記事のカテゴリ
$cat_stmt = $pdo->prepare(
    'SELECT c.id, c.name
       FROM categories c
       INNER JOIN post_categories pc ON pc.category_id = c.id
      WHERE pc.post_id = :id'
);
$cat_stmt->execute([':id' => $id]);
$post_categories = $cat_stmt->fetchAll();

$post_tags = get_post_tags($id);
$post_meta = get_all_post_meta($id);

// 公開状態のラベル
$is_live = ($post['status'] === 'published')
    && (empty($post['published_at']) || strtotime($post['published_at']) <= time());

if ($post['status'] === 'draft') {
    $status_label = '下書き';
    $status_class = 'draft';
} elseif (!$is_live) {
    $status_label = '予約公開（' . h($post['published_at']) . '）';
    $status_class = 'scheduled';
} else {
    $status_label = '公開中';
    $status_class = 'live';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>プレビュー: <?= h($post['title']) ?></title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 0 20px 60px; line-height: 1.7; }
        .preview-bar {
            position: sticky; top: 0; z-index: 10;
            background: #fffbe6; border-bottom: 2px solid #f39c12;
            padding: 12px 20px; margin: 0 -20px 40px;
            font-size: .85rem; display: flex; justify-content: space-between; align-items: center;
        }
        .preview-bar .label {
            display: inline-block; padding: 2px 8px; border-radius: 3px;
            font-weight: bold; margin-right: 8px;
        }
        .preview-bar .draft     { background: #999;    color: #fff; }
        .preview-bar .scheduled { background: #e67e22; color: #fff; }
        .preview-bar .live      { background: #27ae60; color: #fff; }
        .preview-bar a { color: #333; text-decoration: underline; }
        h1 { font-size: 2rem; line-height: 1.3; margin-top: 0; }
        .meta { color: #888; font-size: .9rem; margin-bottom: 32px; }
        .categories { margin-top: 8px; }
        .categories span { display: inline-block; background: #eaeaea; padding: 2px 8px; border-radius: 3px; margin-right: 4px; font-size: .8rem; }
        .thumbnail { max-width: 100%; height: auto; margin-bottom: 32px; }
        .excerpt { font-size: 1.05rem; color: #555; border-left: 4px solid #ddd; padding: 8px 16px; margin-bottom: 32px; }
        .body img { max-width: 100%; height: auto; }
        .body table { border-collapse: collapse; }
        .body table th, .body table td { border: 1px solid #ccc; padding: 8px 12px; }
    </style>
</head>
<body>

<div class="preview-bar">
    <div>
        <span class="label <?= h($status_class) ?>"><?= h($status_label) ?></span>
        <span>プレビュー画面（ログイン中のみ閲覧可能）</span>
    </div>
    <a href="<?= SITE_URL ?>/admin/post-edit.php?id=<?= h($post['id']) ?>">編集に戻る</a>
</div>

<article>
    <h1><?= h($post['title']) ?></h1>

    <p class="meta">
        <?= !empty($post['published_at']) ? h($post['published_at']) : h($post['created_at']) ?>
        <?php if (!empty($post_categories)): ?>
            <span class="categories">
                <?php foreach ($post_categories as $cat): ?>
                    <span><?= h($cat['name']) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($post_tags)): ?>
            <span class="categories" style="margin-left:8px;">
                <?php foreach ($post_tags as $tag): ?>
                    <span style="background:#e0f2fe; color:#0369a1;">#<?= h($tag['name']) ?></span>
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
        <?= $post['body'] ?? '' ?>
    </div>

    <?php if (!empty($post_meta)): ?>
        <dl style="margin-top:32px; padding:16px; background:#f9fafb; border-radius:6px; font-size:.9rem;">
            <?php foreach ($post_meta as $k => $v): ?>
                <dt style="font-weight:600; color:#374151; margin-top:8px;"><?= h($k) ?></dt>
                <dd style="margin:0 0 0 16px; color:#555;">
                    <?php if (is_array($v)): ?>
                        <?= h(implode(', ', $v)) ?>
                    <?php else: ?>
                        <?= h((string)$v) ?>
                    <?php endif; ?>
                </dd>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</article>

</body>
</html>
