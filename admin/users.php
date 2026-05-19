<?php
// ===================================================
//  ユーザー管理（admin限定）
// ===================================================
require_once '../config.php';
require_admin();

$pdo   = db();
$error = '';

/**
 * 現在の admin ユーザー数を返す
 */
function count_admins(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    // --- ユーザー追加 ---
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'editor';

        if ($username === '' || $password === '') {
            $error = 'ユーザー名とパスワードは必須です。';
        } elseif (strlen($password) < 8) {
            $error = 'パスワードは8文字以上にしてください。';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'パスワードには英字と数字を両方含めてください。';
        } elseif (!in_array($role, ['admin', 'editor'], true)) {
            $error = 'ロールが不正です。';
        } else {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, password, email, role) VALUES (:u, :p, :e, :r)'
                );
                $stmt->execute([
                    ':u' => $username,
                    ':p' => $hashed,
                    ':e' => $email,
                    ':r' => $role,
                ]);
                header('Location: ' . SITE_URL . '/admin/users.php');
                exit;
            } catch (PDOException $e) {
                // ユニーク制約違反など
                $error = 'ユーザーの追加に失敗しました（ユーザー名が重複している可能性があります）。';
            }
        }
    }

    // --- ユーザー削除 ---
    if ($_POST['action'] === 'delete' && !empty($_POST['user_id'])) {
        $target_id = (int)$_POST['user_id'];

        if ($target_id === (int)$_SESSION['user_id']) {
            $error = '自分自身は削除できません。';
        } else {
            $target = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $target->execute([':id' => $target_id]);
            $target_user = $target->fetch();

            if ($target_user && $target_user['role'] === 'admin' && count_admins($pdo) <= 1) {
                $error = '最後の管理者は削除できません。';
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $target_id]);
                header('Location: ' . SITE_URL . '/admin/users.php');
                exit;
            }
        }
    }

    // --- ロール変更 ---
    if ($_POST['action'] === 'change_role' && !empty($_POST['user_id'])) {
        $target_id = (int)$_POST['user_id'];
        $new_role  = $_POST['role'] ?? '';

        if (!in_array($new_role, ['admin', 'editor'], true)) {
            $error = 'ロールが不正です。';
        } else {
            $target = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $target->execute([':id' => $target_id]);
            $target_user = $target->fetch();

            if ($target_user && $target_user['role'] === 'admin' && $new_role === 'editor' && count_admins($pdo) <= 1) {
                $error = '最後の管理者を editor に降格することはできません。';
            } else {
                $pdo->prepare('UPDATE users SET role = :r WHERE id = :id')
                    ->execute([':r' => $new_role, ':id' => $target_id]);
                header('Location: ' . SITE_URL . '/admin/users.php');
                exit;
            }
        }
    }
}

// ユーザー一覧
$users = $pdo->query('SELECT id, username, email, role, created_at FROM users ORDER BY id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー管理 | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        h2 { font-size: 1.1rem; margin-top: 32px; }
        label { display: block; margin-top: 12px; font-size: .9rem; font-weight: bold; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc; font-size: 1rem; }
        button { padding: 8px 16px; background: #222; color: #fff; border: none; cursor: pointer; font-size: .9rem; }
        button.danger { background: none; color: #c0392b; padding: 4px 8px; }
        button.inline { padding: 4px 8px; font-size: .85rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; margin-top: 16px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: middle; }
        th { background: #f5f5f5; }
        .error { margin-bottom: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .role-admin  { color: #2980b9; font-weight: bold; }
        .role-editor { color: #555; }
        .add-form { margin-top: 16px; padding: 16px; background: #f9f9f9; border: 1px solid #e0e0e0; }
        .actions form { display: inline; margin-right: 4px; }
        .back { margin-top: 32px; display: block; font-size: .85rem; color: #666; }
        .self { font-size: .75rem; color: #888; margin-left: 6px; }
    </style>
</head>
<body>
    <h1>ユーザー管理</h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ユーザー名</th>
                <th>メール</th>
                <th>ロール</th>
                <th>作成日</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= h($u['id']) ?></td>
                <td>
                    <?= h($u['username']) ?>
                    <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                        <span class="self">(自分)</span>
                    <?php endif; ?>
                </td>
                <td><?= h($u['email'] ?? '') ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                        <select name="role" onchange="if(confirm('ロールを変更しますか？')) this.form.submit(); else this.value='<?= h($u['role']) ?>';" style="width:auto; padding: 4px;">
                            <option value="admin"  <?= $u['role'] === 'admin'  ? 'selected' : '' ?>>admin</option>
                            <option value="editor" <?= $u['role'] === 'editor' ? 'selected' : '' ?>>editor</option>
                        </select>
                    </form>
                </td>
                <td><?= h($u['created_at']) ?></td>
                <td class="actions">
                    <a href="<?= SITE_URL ?>/admin/user-edit.php?id=<?= h($u['id']) ?>">パスワード変更</a>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="post" onsubmit="return confirm('このユーザーを削除しますか？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                            <button type="submit" class="danger inline">削除</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>新規ユーザー追加</h2>
    <form class="add-form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label>ユーザー名
            <input type="text" name="username" required autocomplete="off">
        </label>
        <label>パスワード（8文字以上、英字と数字を両方含む）
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>
        <label>メール（任意）
            <input type="email" name="email">
        </label>
        <label>ロール
            <select name="role">
                <option value="editor">editor</option>
                <option value="admin">admin</option>
            </select>
        </label>
        <p style="margin-top: 16px;"><button type="submit">追加する</button></p>
    </form>

    <a class="back" href="<?= SITE_URL ?>/admin/index.php">← 記事一覧へ戻る</a>
</body>
</html>
