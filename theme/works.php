<?php
// ===================================================
//  テーマ: 作品一覧（Works）
//  入り口は ルートの index.php （URL例: / , /?category=web , /?page=2）
// ===================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_layout.php';

$per_page = max(1, (int)get_setting('posts_per_page', '12'));
$page     = max(1, (int)($_GET['page'] ?? 1));

// カテゴリ絞り込み（slug または id）
$category = null;
if (!empty($_GET['category'])) {
    $category = get_category($_GET['category']);
}

$opts = [
    'limit'       => $per_page,
    'offset'      => ($page - 1) * $per_page,
    'order_by'    => 'sort_order',
    'order'       => 'ASC',
    'category_id' => $category['id'] ?? null,
];

$posts = get_posts($opts);

// 総件数（ページ送りの判定用に、limit 無しでもう一度数える）
$all   = get_posts(['category_id' => $category['id'] ?? null]);
$total = count($all);
$total_pages = max(1, (int)ceil($total / $per_page));

$categories = get_categories();

$page_title = $category ? $category['name'] : 'Works';

site_head($page_title);
?>

<div class="description">
    <p><?php echo h(get_setting('site_description', '作品の一覧を表示します。')); ?></p>
</div>

<div class="page-hero">
    <h1>Works</h1>
    <?php if ($category): ?>
        <p class="page-hero-sub">カテゴリ: <?= h($category['name']) ?></p>
    <?php endif; ?>
</div>

<?php if (!empty($categories)): ?>
    <nav class="filter">
        <a class="filter-btn <?= $category ? '' : 'is-active' ?>" href="<?= h(SITE_URL) ?>/">All</a>
        <?php foreach ($categories as $c): ?>
            <a class="filter-btn <?= ($category && (int)$category['id'] === (int)$c['id']) ? 'is-active' : '' ?>"
                href="<?= h(SITE_URL) ?>/?category=<?= h(rawurlencode($c['slug'])) ?>">
                <?= h($c['name']) ?>
            </a>
        <?php endforeach; ?>
        <span class="filter-count"><?= $total ?> 件</span>
    </nav>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <p class="empty">まだ公開されている作品はありません。</p>
<?php else: ?>
    <div class="work-grid">
        <?php foreach ($posts as $post): ?>
            <?php $tags = get_post_tags((int)$post['id']); ?>
            <a class="work-card" href="<?= h(public_post_url($post)) ?>">
                <div class="work-card-img">
                    <?php if (!empty($post['thumbnail'])): ?>
                        <img src="<?= h(post_thumb_url($post['thumbnail'])) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="work-card-img-empty"></div>
                    <?php endif; ?>
                </div>
                <div class="work-card-body">
                    <?php if (!empty($tags)): ?>
                        <div class="chips">
                            <?php foreach ($tags as $t): ?>
                                <span class="tag"><?= h($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="work-card-title"><?= h($post['title']) ?></h2>
                    <?php if (!empty($post['period'])): ?>
                        <p class="work-card-period"><?= h($post['period']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($post['excerpt'])): ?>
                        <p class="work-card-excerpt"><?= h($post['excerpt']) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <?php $qs = $category ? 'category=' . rawurlencode($category['slug']) . '&' : ''; ?>
        <nav class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= h(SITE_URL) ?>/?<?= $qs ?>page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php site_foot(); ?>