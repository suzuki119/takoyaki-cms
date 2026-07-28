<?php
// ===================================================
//  作品プレビュー（ログイン必須）
//  下書き・予約公開の作品を実際の見た目で確認するための画面。
// ===================================================
require_once 'config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

// 下書き・予約公開も含めて取得する
$post = get_post($id, true);

if (!$post) {
    http_response_code(404);
    exit('作品が見つかりません。');
}

$sections   = get_post_sections($id);
$categories = get_post_categories($id);
$tags       = get_post_tags($id);
$embed      = video_embed_url($post['video_url'] ?? null);

// 公開状態のラベル
if ($post['status'] === 'draft') {
    $status_label = '下書き';
    $status_class = 'draft';
} elseif (!is_post_live($post)) {
    $status_label = '予約公開（' . $post['published_at'] . '）';
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>プレビュー: <?= h($post['title']) ?></title>
    <style>
        body { font-family: sans-serif; max-width: 820px; margin: 0 auto; padding: 0 20px 60px; line-height: 1.8; color: #333; }
        .preview-bar {
            position: sticky; top: 0; z-index: 10;
            background: #fffbe6; border-bottom: 2px solid #f39c12;
            padding: 12px 20px; margin: 0 -20px 40px;
            font-size: .85rem; display: flex; justify-content: space-between; align-items: center; gap: 12px;
        }
        .preview-bar .label { display: inline-block; padding: 2px 8px; border-radius: 3px; font-weight: bold; margin-right: 8px; }
        .preview-bar .draft     { background: #999;    color: #fff; }
        .preview-bar .scheduled { background: #e67e22; color: #fff; }
        .preview-bar .live      { background: #27ae60; color: #fff; }
        .preview-bar a { color: #333; }
        h1 { font-size: 2rem; line-height: 1.3; margin-top: 0; }
        .meta { color: #888; font-size: .9rem; margin-bottom: 24px; }
        .meta dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; margin: 0 0 16px; }
        .meta dt { font-weight: 600; color: #555; }
        .meta dd { margin: 0; }
        .chips span, .chips a { display: inline-block; background: #eaeaea; color: #333; padding: 2px 8px; border-radius: 3px; margin-right: 4px; font-size: .8rem; }
        .chips .tag { background: #e0f2fe; color: #0369a1; }
        .video { position: relative; width: 100%; padding-top: 56.25%; margin-bottom: 32px; }
        .video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .thumbnail { max-width: 100%; height: auto; margin-bottom: 32px; }
        .excerpt { font-size: 1.05rem; color: #555; border-left: 4px solid #ddd; padding: 8px 16px; margin-bottom: 32px; }
        .section { margin-bottom: 40px; }
        .section h2 { font-size: 1.3rem; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .section img { max-width: 100%; height: auto; }
        .section table { border-collapse: collapse; }
        .section table th, .section table td { border: 1px solid #ccc; padding: 8px 12px; }
        .empty { color: #999; }
    </style>
</head>
<body>

<div class="preview-bar">
    <div>
        <span class="label <?= h($status_class) ?>"><?= h($status_label) ?></span>
        <span>プレビュー（ログイン中のみ閲覧可能）</span>
    </div>
    <a href="<?= SITE_URL ?>/admin/post-edit.php?id=<?= h($post['id']) ?>">編集に戻る</a>
</div>

<article>
    <h1><?= h($post['title']) ?></h1>

    <div class="meta">
        <dl>
            <?php if (!empty($post['period'])): ?>
                <dt>制作期間</dt><dd><?= h($post['period']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($post['type'])): ?>
                <dt>種別</dt><dd><?= h($post['type']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($categories)): ?>
                <dt>カテゴリ</dt>
                <dd class="chips"><?php foreach ($categories as $c): ?><span><?= h($c['name']) ?></span><?php endforeach; ?></dd>
            <?php endif; ?>
            <?php if (!empty($tags)): ?>
                <dt>使用技術</dt>
                <dd class="chips"><?php foreach ($tags as $t): ?><span class="tag"><?= h($t['name']) ?></span><?php endforeach; ?></dd>
            <?php endif; ?>
            <?php if (!empty($post['external_url'])): ?>
                <dt>リンク</dt>
                <dd><a href="<?= h($post['external_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($post['external_url']) ?></a></dd>
            <?php endif; ?>
        </dl>
    </div>

    <?php if ($embed): ?>
        <div class="video">
            <iframe src="<?= h($embed) ?>" title="<?= h($post['title']) ?>"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen loading="lazy"></iframe>
        </div>
    <?php elseif (!empty($post['thumbnail'])): ?>
        <img class="thumbnail" src="<?= h(upload_url($post['thumbnail'])) ?>" alt="">
    <?php endif; ?>

    <?php if (!empty($post['excerpt'])): ?>
        <p class="excerpt"><?= h($post['excerpt']) ?></p>
    <?php endif; ?>

    <?php if (empty($sections)): ?>
        <p class="empty">本文セクションがまだありません。</p>
    <?php else: ?>
        <?php foreach ($sections as $section): ?>
            <section class="section">
                <?php if (!empty($section['title'])): ?>
                    <h2><?= h($section['title']) ?></h2>
                <?php endif; ?>
                <?php
                    // 本文は CKEditor が出力した HTML をそのまま表示する。
                    // 書き手＝管理者自身なので信頼する前提（README の注意書きを参照）。
                    echo $section['body'] ?? '';
                ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</article>

</body>
</html>
