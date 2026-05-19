<?php
// ===================================================
//  タグ管理（editor 以上アクセス可）
//  新規タグは記事編集時に自動作成されるため、ここでは一覧と削除のみ
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    if ($_POST['action'] === 'delete' && !empty($_POST['tag_id'])) {
        $tag_id = (int)$_POST['tag_id'];
        $pdo->prepare('DELETE FROM tags WHERE id = :id')->execute([':id' => $tag_id]);
        log_action('tag.delete', 'tag', $tag_id);
        header('Location: ' . SITE_URL . '/admin/tags.php');
        exit;
    }
}

// タグ一覧 + 使用記事数
$stmt = $pdo->query(
    'SELECT t.*, COUNT(pt.post_id) AS post_count
       FROM tags t
       LEFT JOIN post_tags pt ON pt.tag_id = t.id
       GROUP BY t.id
       ORDER BY t.id ASC'
);
$tags = $stmt->fetchAll();

admin_header('タグ管理');
?>

<h1 class="page-title">タグ管理</h1>
<p class="page-meta">
    新規タグは記事編集画面でカンマ区切り入力すると自動作成されます。
</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (empty($tags)): ?>
    <p class="empty-state">タグがまだありません。記事編集画面で入力すると自動作成されます。</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>タグ名</th>
                <th>slug</th>
                <th style="width:100px;">使用記事数</th>
                <th style="width:100px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tags as $tag): ?>
            <tr>
                <td><?= h($tag['id']) ?></td>
                <td><?= h($tag['name']) ?></td>
                <td><code><?= h($tag['slug']) ?></code></td>
                <td><?= h($tag['post_count']) ?></td>
                <td class="actions">
                    <form method="post" onsubmit="return confirm('このタグを削除しますか？\n紐付いている記事のタグは外れますが、記事自体は残ります。');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="tag_id" value="<?= h($tag['id']) ?>">
                        <button type="submit" class="btn-link" style="color:#dc2626;border:none;background:none;cursor:pointer;font-size:.85rem;padding:0;">削除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php admin_footer(); ?>
