<?php
// ===================================================
//  テーマ管理（admin限定）
//   themes/<name>/style.css を持つディレクトリをテーマとして一覧表示し、
//   site_settings.active_theme を切り替える。
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

$info  = '';
$error = '';

$available = get_themes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $picked = trim($_POST['theme'] ?? '');

    // ホワイトリスト検証（任意のパス文字列をDBに書き込めないように）
    if (!in_array($picked, $available, true)) {
        $error = '選択されたテーマが見つかりません。';
    } else {
        $old = get_setting('active_theme', 'default');
        if ($picked !== $old) {
            set_setting('active_theme', $picked);
            log_action('theme.activate', 'theme', null, "{$old} → {$picked}");
            $info = "テーマを「{$picked}」に切り替えました。";
        } else {
            $info = 'すでに有効なテーマです。';
        }
    }
}

$current = active_theme();

admin_header('テーマ');
?>

<h1 class="page-title">テーマ</h1>
<p class="page-meta">
    公開ページ（<code>samples/</code> および <code>router.php</code>）の見た目を切り替えます。
    現在のアクティブテーマ:
    <span class="badge badge-admin"><?= h($current ?: '(なし)') ?></span>
</p>

<?php if ($info !== ''): ?>
    <div class="alert alert-info"><?= h($info) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (empty($available)): ?>
    <p class="empty-state">
        <code>themes/</code> ディレクトリにテーマが見つかりません。<br>
        <code>themes/&lt;name&gt;/style.css</code> を作成すると、ここに表示されます。
        詳しくは <code>themes/README.md</code> を参照。
    </p>
<?php else: ?>
    <div class="theme-grid">
        <?php foreach ($available as $name): ?>
            <?php $meta = get_theme_meta($name); $is_active = ($name === $current); ?>
            <div class="card theme-card<?= $is_active ? ' theme-card--active' : '' ?>">
                <div class="theme-card-header">
                    <h2 class="theme-card-title"><?= h($meta['name']) ?></h2>
                    <?php if ($is_active): ?>
                        <span class="badge badge-admin">使用中</span>
                    <?php endif; ?>
                </div>

                <p class="theme-card-meta">
                    <code><?= h($meta['slug']) ?></code>
                    <?php if ($meta['version'] !== ''): ?>
                        ／ v<?= h($meta['version']) ?>
                    <?php endif; ?>
                </p>

                <?php if ($meta['description'] !== ''): ?>
                    <p class="theme-card-desc"><?= h($meta['description']) ?></p>
                <?php endif; ?>

                <div class="theme-card-actions">
                    <?php if ($is_active): ?>
                        <button type="button" class="btn btn-secondary" disabled>使用中</button>
                    <?php else: ?>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="theme" value="<?= h($name) ?>">
                            <button type="submit" class="btn btn-primary">このテーマを使う</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn-link" href="<?= h(SITE_URL) ?>/samples/index.php" target="_blank" rel="noopener">プレビュー ↗</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p style="font-size:.85rem; color:#6b7280; margin-top:24px;">
    ※ 新しいテーマを作るには <code>themes/&lt;your-theme&gt;/style.css</code> を置いてください。
    任意で <code>theme.json</code>（<code>name</code> / <code>description</code> / <code>version</code>）も置けます。
    詳しくは <a href="<?= h(SITE_URL) ?>/themes/README.md" target="_blank" rel="noopener"><code>themes/README.md</code></a>。
</p>

<style>
/* themes.php 専用の最小スタイル（共通 admin.css に持ち上げてもOK） */
.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.theme-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.theme-card--active {
    border: 2px solid #2563eb;
}
.theme-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.theme-card-title {
    margin: 0;
    font-size: 1.1rem;
}
.theme-card-meta {
    margin: 0;
    font-size: .82rem;
    color: #6b7280;
}
.theme-card-desc {
    margin: 4px 0 8px;
    font-size: .9rem;
    color: #374151;
}
.theme-card-actions {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 12px;
}
</style>

<?php admin_footer(); ?>
