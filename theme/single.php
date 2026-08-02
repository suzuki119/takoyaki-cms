<?php
// ===================================================
//  テーマ: 作品詳細
//  入り口は ルートの single.php （URL例: /single.php?slug=my-work , /single.php?id=1）
// ===================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_layout.php';

$key  = $_GET['slug'] ?? $_GET['id'] ?? null;
$post = $key !== null ? get_post($key) : null;

if (!$post) {
    http_response_code(404);
    site_head('作品が見つかりません');
    echo '<div class="page-hero"><h1>404</h1><p class="page-hero-sub">お探しの作品は見つかりませんでした。</p></div>';
    site_foot();
    exit;
}

$sections   = get_post_sections((int)$post['id']);
$categories = get_post_categories((int)$post['id']);
$tags       = get_post_tags((int)$post['id']);
$embed      = video_embed_url($post['video_url'] ?? null);

site_head($post['title'], (string)($post['excerpt'] ?? ''));
?>

<article class="single">

    <div class="page-hero">
        <h1><?= h($post['title']) ?></h1>
        <?php if (!empty($post['excerpt'])): ?>
            <p class="page-hero-sub"><?= h($post['excerpt']) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($embed): ?>
        <div class="video">
            <iframe src="<?= h($embed) ?>" title="<?= h($post['title']) ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen loading="lazy"></iframe>
        </div>
    <?php elseif (!empty($post['thumbnail'])): ?>
        <img class="single-thumb" src="<?= h(upload_url($post['thumbnail'])) ?>" alt="">
    <?php endif; ?>

    <dl class="single-meta">
        <?php if (!empty($post['period'])): ?>
            <dt>制作期間</dt>
            <dd><?= h($post['period']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($post['type'])): ?>
            <dt>種別</dt>
            <dd><?= h($post['type']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($categories)): ?>
            <dt>カテゴリ</dt>
            <dd class="chips">
                <?php foreach ($categories as $c): ?>
                    <a href="<?= h(SITE_URL) ?>/?category=<?= h(rawurlencode($c['slug'])) ?>"><?= h($c['name']) ?></a>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
        <?php if (!empty($tags)): ?>
            <dt>使用技術</dt>
            <dd class="chips">
                <?php foreach ($tags as $t): ?><span class="tag"><?= h($t['name']) ?></span><?php endforeach; ?>
            </dd>
        <?php endif; ?>
        <?php if (!empty($post['external_url'])): ?>
            <dt>リンク</dt>
            <dd>
                <a href="<?= h($post['external_url']) ?>" target="_blank" rel="noopener noreferrer">
                    サイトを見る ↗
                </a>
            </dd>
        <?php endif; ?>
    </dl>

    <?php foreach ($sections as $section): ?>
        <section class="single-section">
            <?php if (!empty($section['title'])): ?>
                <h2><?= h($section['title']) ?></h2>
            <?php endif; ?>
            <?php
            // 本文は CKEditor が出力した HTML をそのまま表示する。
            // 書き手＝管理者自身なので信頼する前提。
            echo $section['body'] ?? '';
            ?>
        </section>
    <?php endforeach; ?>

    <p class="single-back">
        <a href="<?= h(SITE_URL) ?>/">← 作品一覧へ</a>
    </p>

</article>

<?php site_foot(); ?>