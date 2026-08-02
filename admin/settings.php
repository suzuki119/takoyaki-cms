<?php
// ===================================================
//  サイト設定
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$info = '';

// 管理できる設定キーのホワイトリスト
$editable_keys = [
    'site_name' => [
        'label' => 'サイト名',
        'type'  => 'text',
        'hint'  => 'samples/ のテンプレートで <title> などに使います',
    ],
    'site_description' => [
        'label' => 'サイト説明',
        'type'  => 'text',
        'hint'  => '<meta name="description"> とサイトの説明に使います',
    ],
    'footer_text' => [
        'label' => 'フッターテキスト',
        'type'  => 'text',
        'hint'  => '公開ページのフッターに表示します（任意）',
    ],
    'posts_per_page' => [
        'label' => '作品一覧の表示件数',
        'type'  => 'number',
        'hint'  => 'samples/works.php で1ページに表示する作品数',
    ],
    'public_site_url' => [
        'label' => '公開サイトのURL',
        'type'  => 'text',
        'hint'  => '管理画面の「サイトを表示」の飛び先。空なら samples/works.php へ。既存サイトに組み込んでいる場合はそのURLを入れてください',
    ],
    'public_article_url_pattern' => [
        'label' => '公開作品URLのパターン',
        'type'  => 'text',
        'hint'  => '一覧・編集画面の「公開ページ ↗」の形式。例: https://your-site.com/works/{slug} ／ {slug} と {id} が使えます。空なら samples/single.php へ',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $changed = [];
    foreach ($editable_keys as $key => $meta) {
        $new = trim((string)($_POST[$key] ?? ''));

        if ($meta['type'] === 'number') {
            $new = (string)max(1, (int)$new);
        }

        if ($new !== (string)get_setting($key, '')) {
            set_setting($key, $new);
            $changed[] = $meta['label'];
        }
    }

    $info = !empty($changed)
        ? '設定を更新しました（' . implode('・', $changed) . '）'
        : '変更はありませんでした。';
}

// set_setting() がキャッシュも更新するので、ここでは常に最新の値が読める
$settings = [];
foreach ($editable_keys as $key => $meta) {
    $settings[$key] = (string)get_setting($key, '');
}

admin_header('サイト設定');
?>

<h1 class="page-title">サイト設定</h1>

<?php if ($info !== ''): ?>
    <div class="alert alert-info"><?= h($info) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>

        <?php foreach ($editable_keys as $key => $meta): ?>
            <label class="field">
                <?= h($meta['label']) ?>
                <?php if ($meta['type'] === 'number'): ?>
                    <input type="number" name="<?= h($key) ?>" value="<?= h($settings[$key]) ?>" min="1">
                <?php else: ?>
                    <input type="text" name="<?= h($key) ?>" value="<?= h($settings[$key]) ?>">
                <?php endif; ?>
                <p class="field-hint"><?= h($meta['hint']) ?></p>
            </label>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存する</button>
        </div>
    </form>
</div>

<p class="hint-note">
    ※ これらの設定は DB の <code>site_settings</code> テーブルに保存され、
    公開ページから <code>get_setting('キー名')</code> で参照できます。
</p>

<?php admin_footer(); ?>