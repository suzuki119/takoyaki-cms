<?php
// ===================================================
//  ユーザー編集（パスワードリセット・メール変更） / admin限定
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo   = db();
$error = '';
$info  = '';

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: ' . SITE_URL . '/admin/users.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . SITE_URL . '/admin/users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $new_password      = $_POST['new_password'] ?? '';
    $email             = trim($_POST['email'] ?? '');
    $new_username      = trim($_POST['username'] ?? '');
    $changing_username = ($new_username !== $user['username']);

    if ($changing_username) {
        if ($new_username === '') {
            $error = 'ユーザー名を入力してください。';
        } elseif (mb_strlen($new_username) > 50) {
            $error = 'ユーザー名は50文字以内にしてください。';
        } else {
            $dup = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1');
            $dup->execute([':u' => $new_username, ':id' => $id]);
            if ($dup->fetch()) {
                $error = 'そのユーザー名はすでに使われています。';
            }
        }
    }

    if ($error === '' && $new_password !== '') {
        if (strlen($new_password) < 8) {
            $error = 'パスワードは8文字以上にしてください。';
        } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = 'パスワードには英字と数字を両方含めてください。';
        }
    }

    if ($error === '') {
        $pdo->prepare('UPDATE users SET email = :e WHERE id = :id')
            ->execute([':e' => $email, ':id' => $id]);

        $messages = [];

        if ($changing_username) {
            $old_username = $user['username'];
            $pdo->prepare('UPDATE users SET username = :u WHERE id = :id')
                ->execute([':u' => $new_username, ':id' => $id]);
            // 編集対象が自分自身ならセッションのユーザー名も同期
            if ($id === (int)$_SESSION['user_id']) {
                $_SESSION['username'] = $new_username;
            }
            log_action('user.rename', 'user', $id, "{$old_username} → {$new_username}");
            $messages[] = 'ユーザー名';
        }

        if ($new_password !== '') {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute([':p' => $hashed, ':id' => $id]);
            log_action('user.reset_password', 'user', $id, "対象: {$user['username']}");
            $messages[] = 'パスワード';
        }

        $messages[] = 'メール';
        $info = implode('・', $messages) . 'を更新しました。';

        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
    }
}

admin_header('ユーザー編集: ' . $user['username']);
?>

<h1 class="page-title">ユーザー編集： <?= h($user['username']) ?></h1>
<p class="page-meta">ID: <?= h($user['id']) ?> ／ ロール:
    <span class="badge badge-<?= h($user['role']) ?>"><?= h($user['role']) ?></span>
</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($info !== ''): ?>
    <div class="alert alert-info"><?= h($info) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>

        <label class="field">ユーザー名（ログイン名）
            <input type="text" name="username" value="<?= h($user['username']) ?>" required maxlength="50" autocomplete="off">
            <p class="field-hint">変更すると対象ユーザーは次回ログインから新しい名前を使います。</p>
        </label>

        <label class="field">メールアドレス
            <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>">
        </label>

        <label class="field">新しいパスワード（変更する場合のみ入力）
            <input type="password" name="new_password" autocomplete="new-password" minlength="8">
            <p class="field-hint">8文字以上、英字と数字を両方含む。空のままなら変更されません。</p>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">更新する</button>
            <a class="btn-link" href="<?= SITE_URL ?>/admin/users.php">← ユーザー一覧へ戻る</a>
        </div>
    </form>
</div>

<?php admin_footer(); ?>
