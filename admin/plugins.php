<?php
// ===================================================
//  プラグイン管理（admin限定）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $enabled_now = is_array($_POST['enabled'] ?? null) ? $_POST['enabled'] : [];
    // 安全な名前のみ保存
    $enabled_now = array_values(array_filter($enabled_now, fn($n) => is_string($n) && preg_match('/^[A-Za-z0-9_-]+$/', $n)));

    set_setting('enabled_plugins', json_encode($enabled_now, JSON_UNESCAPED_UNICODE));
    log_action('plugin.update', 'plugin', null, '有効: ' . implode(',', $enabled_now));
    $info = 'プラグインの有効化設定を更新しました（' . count($enabled_now) . ' 件有効）';
}

$installed = scan_plugins();
$enabled   = get_enabled_plugins();

admin_header('プラグイン');
?>

<h1 class="page-title">プラグイン</h1>

<?php if ($info !== ''): ?>
    <div class="alert alert-info"><?= h($info) ?></div>
<?php endif; ?>

<?php if (empty($installed)): ?>
    <p class="empty-state">
        <code>plugins/</code> ディレクトリにインストール済みのプラグインがありません。<br>
        サンプル <code>plugins/hello-world/</code> を参考にプラグインを作成できます。
    </p>
<?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:60px;">有効</th>
                    <th>プラグイン名</th>
                    <th>説明</th>
                    <th style="width:80px;">バージョン</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installed as $p): ?>
                    <?php $is_enabled = in_array($p['name'], $enabled, true); ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="enabled[]" value="<?= h($p['name']) ?>" <?= $is_enabled ? 'checked' : '' ?>>
                        </td>
                        <td><strong><?= h($p['name']) ?></strong></td>
                        <td style="font-size:.9rem; color:#555;"><?= h($p['description']) ?></td>
                        <td style="font-size:.85rem; color:#888;"><?= h($p['version']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">設定を保存</button>
        </div>
    </form>
<?php endif; ?>

<div class="alert alert-warning" style="margin-top:24px;">
    <strong>注意：</strong>プラグインは PHP コードを動的に読み込みます。
    信頼できないプラグインを有効化しないでください。
    詳しい開発方法は <code>plugins/README.md</code> を参照。
</div>

<?php admin_footer(); ?>
