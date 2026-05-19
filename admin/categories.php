<?php
// ===================================================
//  カテゴリ管理
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'カテゴリ名を入力してください。';
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
            $stmt->execute([':name' => $name, ':slug' => '']);

            $newId = $pdo->lastInsertId();
            $pdo->prepare('UPDATE categories SET slug = :slug WHERE id = :id')
                ->execute([':slug' => (string)$newId, ':id' => $newId]);

            log_action('category.create', 'category', (int)$newId, '名前: ' . $name);
            header('Location: ' . SITE_URL . '/admin/categories.php');
            exit;
        }
    }

    if ($_POST['action'] === 'delete' && !empty($_POST['delete_id'])) {
        $del_id = (int)$_POST['delete_id'];
        $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $del_id]);
        log_action('category.delete', 'category', $del_id);
        header('Location: ' . SITE_URL . '/admin/categories.php');
        exit;
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll();

admin_header('カテゴリ管理');
?>

<h1 class="page-title">カテゴリ管理</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="section-title" style="margin-top:0;">新規カテゴリを追加</h2>
    <form method="post" style="display:flex; gap:8px; align-items:flex-end;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label class="field" style="flex:1; margin:0;">
            カテゴリ名
            <input type="text" name="name" placeholder="例: ブログ" required>
        </label>
        <button type="submit" class="btn">追加する</button>
    </form>
</div>

<?php if (empty($categories)): ?>
    <p class="empty-state">カテゴリがまだありません。</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>カテゴリ名</th>
                <th>slug</th>
                <th style="width:120px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= h($category['id']) ?></td>
                <td><?= h($category['name']) ?></td>
                <td><code><?= h($category['slug']) ?></code></td>
                <td class="actions">
                    <form method="post" onsubmit="return confirm('削除しますか？\n所属している記事のカテゴリ紐付けも削除されます。');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="delete_id" value="<?= h($category['id']) ?>">
                        <button type="submit" class="btn-link" style="color:#dc2626;border:none;background:none;cursor:pointer;font-size:.85rem;padding:0;">削除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php admin_footer(); ?>
