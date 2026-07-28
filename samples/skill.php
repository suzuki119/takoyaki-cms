<?php
// ===================================================
//  サンプル: スキル一覧（Skills）
//  管理画面の「スキル」で登録した内容をカテゴリごとに表示する。
// ===================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_layout.php';

$grouped = get_skills_grouped();

// SKILL_CATEGORIES の順に並べ、そこに無いカテゴリは末尾へ
$display_categories = array_merge(
    SKILL_CATEGORIES,
    array_diff(array_keys($grouped), SKILL_CATEGORIES)
);

site_head('Skills', 'これまでに使ってきた技術とツールの一覧です。');
?>

<div class="page-hero">
    <h1>Skills</h1>
    <p class="page-hero-sub">これまでに使ってきた技術とツールです。</p>
</div>

<?php if (empty($grouped)): ?>
    <p class="empty">スキルはまだ登録されていません。</p>
<?php else: ?>
    <?php foreach ($display_categories as $cat): ?>
        <?php if (empty($grouped[$cat])) continue; ?>

        <section class="skill-group">
            <h2 class="skill-category"><?= h($cat) ?></h2>

            <div class="skill-grid">
                <?php foreach ($grouped[$cat] as $skill): ?>
                    <div class="skill-card">
                        <div class="skill-card-head">
                            <?php if (!empty($skill['image'])): ?>
                                <img class="skill-icon" src="<?= h(post_thumb_url($skill['image'])) ?>"
                                     alt="<?= h($skill['title']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="skill-icon skill-icon-empty"><?= h(mb_substr($skill['title'], 0, 1)) ?></span>
                            <?php endif; ?>

                            <div class="skill-card-meta">
                                <div class="skill-name"><?= h($skill['title']) ?></div>
                                <?php if (!empty($skill['period'])): ?>
                                    <div class="skill-period"><?= h($skill['period']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($skill['body'])): ?>
                            <p class="skill-detail"><?= nl2br(h($skill['body'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php site_foot(); ?>
