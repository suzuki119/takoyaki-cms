<?php
// ===================================================
//  ユーザー管理（admin限定）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo   = db();
$error = '';

function count_admins(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

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
                $error = 'ユーザーの追加に失敗しました（ユーザー名が重複している可能性があります）。';
            }
        }
    }

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

$users = $pdo->query('SELECT id, username, email, role, created_at FROM users ORDER BY id ASC')->fetchAll();

admin_header('ユーザー管理');
?>

<h1 class="page-title">ユーザー管理</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>ユーザー名</th>
            <th>メール</th>
            <th style="width:140px;">ロール</th>
            <th style="width:150px;">作成日</th>
            <th style="width:200px;">操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= h($u['id']) ?></td>
            <td>
                <?= h($u['username']) ?>
                <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                    <small style="color:#9ca3af;">(自分)</small>
                <?php endif; ?>
            </td>
            <td><?= h($u['email'] ?? '') ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                    <select name="role" onchange="if(confirm('ロールを変更しますか？')) this.form.submit(); else this.value='<?= h($u['role']) ?>';" style="padding:4px 8px; border:1px solid #d1d5db; border-radius:3px;">
                        <option value="admin"  <?= $u['role'] === 'admin'  ? 'selected' : '' ?>>admin</option>
                        <option value="editor" <?= $u['role'] === 'editor' ? 'selected' : '' ?>>editor</option>
                    </select>
                </form>
            </td>
            <td><?= h(substr($u['created_at'], 0, 10)) ?></td>
            <td class="actions">
                <a href="<?= SITE_URL ?>/admin/user-edit.php?id=<?= h($u['id']) ?>">編集</a>
                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <form method="post" onsubmit="return confirm('このユーザーを削除しますか？');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                        <button type="submit" class="btn-link" style="color:#dc2626;border:none;background:none;cursor:pointer;font-size:.85rem;padding:0;">削除</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="card" style="margin-top:24px;">
    <h2 class="section-title" style="margin-top:0;">新規ユーザー追加</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">

        <label class="field">ユーザー名
            <input type="text" name="username" required autocomplete="off">
        </label>

        <label class="field">パスワード
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
            <p class="field-hint">8文字以上、英字と数字を両方含む。</p>
        </label>

        <label class="field">メール（任意）
            <input type="email" name="email">
        </label>

        <label class="field">ロール
            <select name="role">
                <option value="editor">editor</option>
                <option value="admin">admin</option>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">追加する</button>
        </div>
    </form>
</div>

<?php admin_footer(); ?>
