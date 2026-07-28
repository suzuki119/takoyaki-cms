<?php
// ===================================================
//  アカウント設定（ユーザー名・メール・パスワードの変更）
//  v2.0.0 から管理者は1人構成なので、このページが唯一のユーザー管理画面。
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';
$info  = '';

$id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id, username, email, password, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . SITE_URL . '/logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password     = (string)($_POST['new_password'] ?? '');
    $email            = trim((string)($_POST['email'] ?? ''));
    $new_username     = trim((string)($_POST['username'] ?? ''));

    $changing_password = ($new_password !== '');
    $changing_username = ($new_username !== $user['username']);

    if ($changing_password) {
        if (!password_verify($current_password, $user['password'])) {
            $error = '現在のパスワードが正しくありません。';
        } elseif (strlen($new_password) < 8) {
            $error = '新しいパスワードは8文字以上にしてください。';
        } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = '新しいパスワードには英字と数字を両方含めてください。';
        }
    }

    if ($error === '' && $changing_username) {
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

    if ($error === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'メールアドレスの形式が正しくありません。';
    }

    if ($error === '') {
        $messages = [];

        $pdo->prepare('UPDATE users SET email = :e WHERE id = :id')
            ->execute([':e' => $email !== '' ? $email : null, ':id' => $id]);
        $messages[] = 'メール';

        if ($changing_username) {
            $pdo->prepare('UPDATE users SET username = :u WHERE id = :id')
                ->execute([':u' => $new_username, ':id' => $id]);
            $_SESSION['username'] = $new_username;
            $messages[]           = 'ユーザー名';
        }

        if ($changing_password) {
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute([':p' => password_hash($new_password, PASSWORD_DEFAULT), ':id' => $id]);
            // パスワードを変えたらセッションIDも作り直す
            session_regenerate_id(true);
            $messages[] = 'パスワード';
        }

        $info = implode('・', $messages) . 'を更新しました。';

        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
    }
}

admin_header('アカウント設定');
?>

<h1 class="page-title">アカウント</h1>
<p class="page-meta">登録日: <?= h(substr((string)$user['created_at'], 0, 10)) ?></p>

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
            <input type="text" name="username" value="<?= h($user['username']) ?>" required maxlength="50" autocomplete="username">
        </label>

        <label class="field">メールアドレス
            <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>">
        </label>

        <fieldset>
            <legend>パスワードを変更する場合のみ入力</legend>

            <label class="field">現在のパスワード
                <input type="password" name="current_password" autocomplete="current-password">
            </label>

            <label class="field">新しいパスワード
                <input type="password" name="new_password" autocomplete="new-password" minlength="8">
                <p class="field-hint">8文字以上、英字と数字を両方含む。空のままなら変更されません。</p>
            </label>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">更新する</button>
        </div>
    </form>
</div>

<div class="alert alert-warning">
    <strong>パスワードを忘れた場合：</strong>
    v2.0.0 ではメールによるリセット機能を持ちません。
    phpMyAdmin 等で <code>users</code> テーブルの <code>password</code> を
    <code>password_hash()</code> で作ったハッシュに書き換えてください。
</div>

<?php admin_footer(); ?>
