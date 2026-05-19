<?php
// ===================================================
//  サイト設定（admin限定）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

$info = '';

// 管理可能な設定キーのホワイトリスト
$editable_keys = [
    'site_name'        => ['label' => 'サイト名',       'type' => 'text',     'hint' => 'samples/ テンプレートや feed.php で利用'],
    'site_description' => ['label' => 'サイト説明',     'type' => 'text',     'hint' => 'RSS/sitemap や <meta description> で利用'],
    'footer_text'      => ['label' => 'フッターテキスト', 'type' => 'text',     'hint' => 'samples/index.php のフッター等で利用（任意）'],
    'posts_per_page'   => ['label' => '一覧表示件数',   'type' => 'number',   'hint' => 'samples/index.php の記事一覧で表示する件数'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $changed = [];
    foreach ($editable_keys as $key => $_) {
        $new = trim($_POST[$key] ?? '');
        $old = get_setting($key, '');
        if ($new !== $old) {
            set_setting($key, $new);
            $changed[] = $key;
        }
    }

    if (!empty($changed)) {
        log_action('setting.update', 'setting', null, '更新: ' . implode(', ', $changed));
        $info = '設定を更新しました（' . count($changed) . ' 件）';
    } else {
        $info = '変更はありませんでした。';
    }

    // 静的キャッシュをリセットするため再読込
    // 簡単のためページ全体をリロード扱いで続行
}

// 設定を再取得（cache は static なので、上の set_setting 後の値もこの関数経由で読める）
$settings = [];
foreach ($editable_keys as $key => $_) {
    $settings[$key] = get_setting($key, '');
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

<p style="font-size:.85rem; color:#6b7280; margin-top:24px;">
    ※ これらの設定は DB の <code>site_settings</code> テーブルに保存され、フロントエンドの <code>samples/</code> テンプレート等から
    <code>get_setting('キー名')</code> で参照できます。
</p>

<?php admin_footer(); ?>
