<?php
// ===================================================
//  管理画面 トップ（記事一覧）
// ===================================================
require_once '../config.php';
require_login();

$pdo = db();

// ===================================================
//  削除処理（POSTで id が送られてきたとき）
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    verify_csrf();
    $delete_id = $_POST['delete_id'];

    // posts を削除すると post_categories は ON DELETE CASCADE で自動削除される
    $pdo->prepare('DELETE FROM posts WHERE id = :id')
        ->execute([':id' => $delete_id]);

    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

// ===================================================
//  並び替え処理（↑↓ボタンが押されたとき）
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['move_id'])) {
    verify_csrf();
    $move_id   = (int)$_POST['move_id'];
    $direction = $_POST['direction']; // 'up' or 'down'

    // 現在の記事の sort_order を取得
    $stmt_cur = $pdo->prepare('SELECT id, sort_order FROM posts WHERE id = :id');
    $stmt_cur->execute([':id' => $move_id]);
    $current = $stmt_cur->fetch();

    // 隣の記事を取得（up なら1つ小さい、down なら1つ大きい）
    if ($direction === 'up') {
        $stmt_nb = $pdo->prepare('SELECT id, sort_order FROM posts WHERE sort_order < :order ORDER BY sort_order DESC LIMIT 1');
    } else {
        $stmt_nb = $pdo->prepare('SELECT id, sort_order FROM posts WHERE sort_order > :order ORDER BY sort_order ASC LIMIT 1');
    }
    $stmt_nb->execute([':order' => $current['sort_order']]);
    $neighbor = $stmt_nb->fetch();

    // 隣が存在すれば sort_order を入れ替える
    if ($neighbor) {
        $pdo->prepare('UPDATE posts SET sort_order = :order WHERE id = :id')
            ->execute([':order' => $neighbor['sort_order'], ':id' => $current['id']]);
        $pdo->prepare('UPDATE posts SET sort_order = :order WHERE id = :id')
            ->execute([':order' => $current['sort_order'], ':id' => $neighbor['id']]);
    }

    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

// ===================================================
//  記事一覧を取得
// ===================================================
$stmt = $pdo->prepare(
    "SELECT *,
            (status = 'published' AND (published_at IS NULL OR published_at <= NOW())) AS is_live
     FROM posts
     ORDER BY sort_order ASC"
);
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>記事一覧 | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 12px; }
        .header-actions { display: flex; gap: 8px; }
        a.button { padding: 8px 16px; background: #222; color: #fff; text-decoration: none; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .status-published { color: #27ae60; font-weight: bold; }
        .status-draft { color: #999; }
        .status-scheduled { color: #e67e22; font-weight: bold; }
        .status-detail { display: block; font-size: .75rem; color: #888; margin-top: 2px; font-weight: normal; }
        .preview-link { font-size: .8rem; }
        .actions form { display: inline; }
        .actions a { margin-right: 8px; color: #333; font-size: .85rem; }
        .actions button { background: none; border: none; color: #c0392b; cursor: pointer; font-size: .85rem; }
        .actions button.sort-btn { color: #555; margin-right: 2px; }
        .empty { padding: 40px; text-align: center; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>記事一覧</h1>
        <div class="header-actions">
            <a class="button" href="<?= SITE_URL ?>/admin/post-new.php">+ 新規作成</a>
            <a class="button" href="<?= SITE_URL ?>/admin/categories.php">+ カテゴリー</a>
            <?php if (user_role() === 'admin'): ?>
                <a class="button" href="<?= SITE_URL ?>/admin/users.php">ユーザー管理</a>
            <?php endif; ?>
            <a class="button" href="<?= SITE_URL ?>/admin/account.php">アカウント設定</a>
        </div>
    </div>

    <?php if (empty($posts)): ?>
        <p class="empty">記事がまだありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>順番</th>
                    <th>タイトル</th>
                    <th>公開状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $i => $post): ?>
                <tr>
                    <td>
                        <?php if ($i > 0): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="move_id" value="<?= h($post['id']) ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="sort-btn">↑</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($i < count($posts) - 1): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="move_id" value="<?= h($post['id']) ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="sort-btn">↓</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= h($post['title']) ?></td>
                    <td>
                        <?php if ($post['status'] === 'published' && $post['is_live']): ?>
                            <span class="status-published">公開中</span>
                        <?php elseif ($post['status'] === 'published' && !$post['is_live']): ?>
                            <span class="status-scheduled">予約公開</span>
                            <span class="status-detail"><?= h($post['published_at']) ?></span>
                        <?php else: ?>
                            <span class="status-draft">下書き</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="<?= SITE_URL ?>/admin/post-edit.php?id=<?= h($post['id']) ?>">編集</a>
                        <a class="preview-link" href="<?= SITE_URL ?>/preview.php?id=<?= h($post['id']) ?>" target="_blank">プレビュー</a>
                        <form method="post" onsubmit="return confirm('削除しますか？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_id" value="<?= h($post['id']) ?>">
                            <button type="submit">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top: 40px; font-size: .85rem;">
        <a href="<?= SITE_URL ?>/logout.php">ログアウト</a>
    </p>
</body>
</html>
