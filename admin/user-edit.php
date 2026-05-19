<?php
// ===================================================
//  ユーザー編集（パスワードリセット・メール変更） / admin限定
// ===================================================
require_once '../config.php';
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

    // メールは常に更新
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
            $info = 'パスワードとメールを更新しました。';
        }
    } else {
        $info = 'メールを更新しました（パスワードは変更されていません）。';
    }

    // 最新の情報を再取得
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー編集 | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 8px; }
        .meta { font-size: .85rem; color: #888; margin-bottom: 24px; }
        label { display: block; margin-top: 16px; font-size: .9rem; font-weight: bold; }
        input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc; font-size: 1rem; }
        button { margin-top: 20px; padding: 10px 24px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        .error { margin-top: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .info  { margin-top: 16px; padding: 10px; background: #eafaf1; border-left: 4px solid #27ae60; font-size: .9rem; }
        .back { margin-top: 32px; display: block; font-size: .85rem; color: #666; }
        .hint { font-size: .8rem; color: #666; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>ユーザー編集： <?= h($user['username']) ?></h1>
    <p class="meta">ID: <?= h($user['id']) ?> ／ ロール: <?= h($user['role']) ?></p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($info !== ''): ?>
        <div class="info"><?= h($info) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>

        <label>メールアドレス
            <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>">
        </label>

        <label>新しいパスワード（変更する場合のみ入力）
            <input type="password" name="new_password" autocomplete="new-password" minlength="8">
            <p class="hint">8文字以上、英字と数字を両方含む。空のままなら変更されません。</p>
        </label>

        <button type="submit">更新する</button>
    </form>

    <a class="back" href="<?= SITE_URL ?>/admin/users.php">← ユーザー一覧へ戻る</a>
</body>
</html>
