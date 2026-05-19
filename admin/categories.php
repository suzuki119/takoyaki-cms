<?php
// ===================================================
//  カテゴリ管理
// ===================================================
require_once '../config.php'; // [組み込み] 1つ上の階層のconfig.phpを読み込む
require_login();              // [自作] 未ログインならログイン画面へ飛ばす

$pdo   = db(); // [自作] DB接続を取得
$error = '';

// ===================================================
//  追加処理
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    // カテゴリ追加
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? ''); // [組み込み] trim()=前後の空白を除去

        if ($name === '') {
            $error = 'カテゴリ名を入力してください。';
        } else {
            // ① カテゴリ名をDBに挿入（slugは仮で空文字）
            $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
            $stmt->execute([':name' => $name, ':slug' => '']);

            // ② 挿入されたレコードのIDを取得
            $newId = $pdo->lastInsertId(); // [PDO組み込み] 直前のINSERTで発行されたIDを取得

            // ③ slugをIDに更新
            $stmt = $pdo->prepare('UPDATE categories SET slug = :slug WHERE id = :id');
            $stmt->execute([':slug' => (string)$newId, ':id' => $newId]);

            header('Location: ' . SITE_URL . '/admin/categories.php');
            exit;
        }
    }

    // カテゴリ削除（post_categories は ON DELETE CASCADE で自動削除される）
    if ($_POST['action'] === 'delete' && !empty($_POST['delete_id'])) {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $_POST['delete_id']]);

        header('Location: ' . SITE_URL . '/admin/categories.php');
        exit;
    }
}

// ===================================================
//  カテゴリ一覧を取得
// ===================================================
$stmt = $pdo->prepare('SELECT * FROM categories ORDER BY id ASC'); // ASC=古い順（登録順）
$stmt->execute();
$categories = $stmt->fetchAll(); // [PDO組み込み] 全行を配列で取得
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>カテゴリ管理 | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        .add-form { display: flex; gap: 8px; margin-bottom: 32px; }
        .add-form input { flex: 1; padding: 8px; border: 1px solid #ccc; font-size: 1rem; }
        .add-form button { padding: 8px 20px; background: #222; color: #fff; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .delete-btn { background: none; border: none; color: #c0392b; cursor: pointer; font-size: .85rem; }
        .error { margin-bottom: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .empty { padding: 24px; text-align: center; color: #999; }
        .back { margin-top: 32px; display: block; font-size: .85rem; color: #666; }
    </style>
</head>
<body>
    <h1>カテゴリ管理</h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form class="add-form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="カテゴリ名（例：JavaScript）" required>
        <button type="submit">追加</button>
    </form>

    <?php if (empty($categories)): ?>
        <p class="empty">カテゴリがまだありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>カテゴリ名</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= h($category['name']) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('削除しますか？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_id" value="<?= h($category['id']) ?>">
                            <button class="delete-btn" type="submit">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a class="back" href="<?= SITE_URL ?>/admin/index.php">← 記事一覧へ戻る</a>
</body>
</html>
