<?php
// ===================================================
//  タグ管理（使用技術）
//  新規タグは作品編集時に自動作成されるため、ここでは一覧・改名・削除のみ
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';

$editing_id = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    if ($_POST['action'] === 'delete' && !empty($_POST['tag_id'])) {
        $pdo->prepare('DELETE FROM tags WHERE id = :id')->execute([':id' => (int)$_POST['tag_id']]);
        header('Location: ' . SITE_URL . '/admin/tags.php');
        exit;
    }

    if ($_POST['action'] === 'update' && !empty($_POST['tag_id'])) {
        $tid  = (int)$_POST['tag_id'];
        $name = trim((string)($_POST['name'] ?? ''));

        if ($name === '') {
            $error      = 'タグ名を入力してください。';
            $editing_id = $tid;
        } else {
            $dup = $pdo->prepare('SELECT id FROM tags WHERE name = :n AND id <> :id LIMIT 1');
            $dup->execute([':n' => $name, ':id' => $tid]);

            if ($dup->fetch()) {
                $error      = 'そのタグ名はすでに使われています。';
                $editing_id = $tid;
            } else {
                $slug = unique_slug(sluggify($name), 'tags', $tid, 'tag-' . $tid);
                $pdo->prepare('UPDATE tags SET name = :n, slug = :s WHERE id = :id')
                    ->execute([':n' => $name, ':s' => $slug, ':id' => $tid]);
                header('Location: ' . SITE_URL . '/admin/tags.php');
                exit;
            }
        }
    }
}

$tags = $pdo->query(
    'SELECT t.*, COUNT(pt.post_id) AS post_count
       FROM tags t
       LEFT JOIN post_tags pt ON pt.tag_id = t.id
      GROUP BY t.id
      ORDER BY t.id ASC'
)->fetchAll();

admin_header('タグ管理');
?>

<h1 class="page-title">タグ（使用技術）</h1>
<p class="page-meta">
    新規タグは作品の編集画面でカンマ区切り入力すると自動作成されます。
</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (empty($tags)): ?>
    <p class="empty-state">タグがまだありません。作品編集画面で入力すると自動作成されます。</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>タグ名</th>
                <th style="width:200px;">slug</th>
                <th style="width:90px;">作品数</th>
                <th style="width:130px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tags as $tag): ?>
            <tr>
                <td><?= h($tag['id']) ?></td>

                <?php if ((int)$tag['id'] === $editing_id): ?>
                    <td colspan="2">
                        <form method="post" class="inline-form" id="edit-tag-<?= h($tag['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="tag_id" value="<?= h($tag['id']) ?>">
                            <input type="text" name="name" value="<?= h($tag['name']) ?>" required maxlength="100" style="flex:1;">
                        </form>
                        <p class="field-hint">slug はタグ名から自動で作り直されます。</p>
                    </td>
                    <td><?= (int)$tag['post_count'] ?></td>
                    <td class="actions">
                        <button type="submit" form="edit-tag-<?= h($tag['id']) ?>" class="btn btn-primary btn-sm">保存</button>
                        <a href="<?= SITE_URL ?>/admin/tags.php">中止</a>
                    </td>
                <?php else: ?>
                    <td><?= h($tag['name']) ?></td>
                    <td><code><?= h($tag['slug']) ?></code></td>
                    <td><?= (int)$tag['post_count'] ?></td>
                    <td class="actions">
                        <a href="<?= SITE_URL ?>/admin/tags.php?edit=<?= h($tag['id']) ?>">編集</a>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('「<?= h($tag['name']) ?>」を削除しますか？\n作品との紐付けは外れますが、作品自体は残ります。');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tag_id" value="<?= h($tag['id']) ?>">
                            <button type="submit" class="btn-link btn-danger">削除</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php admin_footer(); ?>
