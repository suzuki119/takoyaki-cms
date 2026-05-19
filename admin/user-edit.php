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

    $new_password = $_POST['new_password'] ?? '';
    $email        = trim($_POST['email'] ?? '');

    $pdo->prepare('UPDATE users SET email = :e WHERE id = :id')
        ->execute([':e' => $email, ':id' => $id]);

    if ($new_password !== '') {
        if (strlen($new_password) < 8) {
            $error = 'パスワードは8文字以上にしてください。';
        } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = 'パスワードには英字と数字を両方含めてください。';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute([':p' => $hashed, ':id' => $id]);
            log_action('user.reset_password', 'user', $id, "対象: {$user['username']}");
            $info = 'パスワードとメールを更新しました。';
        }
    } else {
        $info = 'メールを更新しました（パスワードは変更されていません）。';
    }

    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
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
