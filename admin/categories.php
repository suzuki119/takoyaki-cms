<?php
// ===================================================
//  カテゴリ管理（追加・名前変更・削除）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';
$info  = '';

$editing_id = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();
    $action = $_POST['action'];

    // ---- 追加 ----
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));

        if ($name === '') {
            $error = 'カテゴリ名を入力してください。';
        } elseif (mb_strlen($name) > 100) {
            $error = 'カテゴリ名は100文字以内にしてください。';
        } else {
            // slug 未入力ならカテゴリ名から生成。日本語のみなら cat-{ID} を後で振る
            $slug = unique_slug(sluggify($slug !== '' ? $slug : $name), 'categories', null, 'category');

            $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:n, :s)');
            $stmt->execute([':n' => $name, ':s' => $slug]);

            header('Location: ' . SITE_URL . '/admin/categories.php');
            exit;
        }
    }

    // ---- 名前・slug の変更 ----
    if ($action === 'update' && !empty($_POST['id'])) {
        $cid  = (int)$_POST['id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));

        if ($name === '') {
            $error      = 'カテゴリ名を入力してください。';
            $editing_id = $cid;
        } else {
            $slug = unique_slug(sluggify($slug !== '' ? $slug : $name), 'categories', $cid, 'category-' . $cid);

            $pdo->prepare('UPDATE categories SET name = :n, slug = :s WHERE id = :id')
                ->execute([':n' => $name, ':s' => $slug, ':id' => $cid]);

            header('Location: ' . SITE_URL . '/admin/categories.php');
            exit;
        }
    }

    // ---- 削除 ----
    if ($action === 'delete' && !empty($_POST['delete_id'])) {
        // post_categories は外部キーの CASCADE で一緒に消える（作品自体は残る）
        $pdo->prepare('DELETE FROM categories WHERE id = :id')
            ->execute([':id' => (int)$_POST['delete_id']]);

        header('Location: ' . SITE_URL . '/admin/categories.php');
        exit;
    }
}

// 使用中の作品数も一緒に取る
$categories = $pdo->query(
    'SELECT c.*, COUNT(pc.post_id) AS post_count
       FROM categories c
       LEFT JOIN post_categories pc ON pc.category_id = c.id
      GROUP BY c.id
      ORDER BY c.id ASC'
)->fetchAll();

admin_header('カテゴリ管理');
?>

<h1 class="page-title">カテゴリ</h1>
<p class="page-meta">
    作品を分類します。公開ページの一覧でフィルターとして使えます。
</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="section-title" style="margin-top:0;">新規カテゴリを追加</h2>
    <form method="post" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label class="field" style="flex:2; margin:0;">
            カテゴリ名
            <input type="text" name="name" placeholder="例: Webサイト制作" required maxlength="100">
        </label>
        <label class="field" style="flex:1; margin:0;">
            slug（任意）
            <input type="text" name="slug" placeholder="例: website">
        </label>
        <button type="submit" class="btn btn-primary">追加する</button>
    </form>
    <p class="field-hint">
        slug は公開ページのURLに使う識別子です。空欄ならカテゴリ名から自動生成します
        （日本語のみの名前は <code>category-{ID}</code> になるので、必要なら手で入れてください）。
    </p>
</div>

<?php if (empty($categories)): ?>
    <p class="empty-state">カテゴリがまだありません。</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>カテゴリ名</th>
                <th style="width:200px;">slug</th>
                <th style="width:90px;">作品数</th>
                <th style="width:130px;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
            <tr>
                <?php if ((int)$category['id'] === $editing_id): ?>
                    <?php // ---- 編集中の行 ---- ?>
                    <td><?= h($category['id']) ?></td>
                    <td colspan="2">
                        <form method="post" class="inline-form" id="edit-cat-<?= h($category['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= h($category['id']) ?>">
                            <input type="text" name="name" value="<?= h($category['name']) ?>" required maxlength="100" style="flex:2;">
                            <input type="text" name="slug" value="<?= h($category['slug']) ?>" placeholder="slug" style="flex:1;">
                        </form>
                    </td>
                    <td><?= (int)$category['post_count'] ?></td>
                    <td class="actions">
                        <button type="submit" form="edit-cat-<?= h($category['id']) ?>" class="btn btn-primary btn-sm">保存</button>
                        <a href="<?= SITE_URL ?>/admin/categories.php">中止</a>
                    </td>
                <?php else: ?>
                    <?php // ---- 通常の行 ---- ?>
                    <td><?= h($category['id']) ?></td>
                    <td><?= h($category['name']) ?></td>
                    <td><code><?= h($category['slug']) ?></code></td>
                    <td><?= (int)$category['post_count'] ?></td>
                    <td class="actions">
                        <a href="<?= SITE_URL ?>/admin/categories.php?edit=<?= h($category['id']) ?>">編集</a>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('「<?= h($category['name']) ?>」を削除しますか？\n作品との紐付けは外れますが、作品自体は残ります。');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_id" value="<?= h($category['id']) ?>">
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
