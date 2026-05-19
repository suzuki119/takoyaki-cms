<?php
// ===================================================
//  自分のアカウント設定（パスワード・メール変更）
// ===================================================
require_once '../config.php';
require_login();

$pdo   = db();
$error = '';
$info  = '';

$id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    // セッションのuser_idがDBに無い → 強制ログアウト
    header('Location: ' . SITE_URL . '/logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $email            = trim($_POST['email'] ?? '');

    // 現在のパスワード確認は新パスワード変更時のみ必須
    $changing_password = ($new_password !== '');

    if ($changing_password) {
        if (!password_verify($current_password, $user['password'])) {
            $error = '現在のパスワードが正しくありません。';
        } elseif (strlen($new_password) < 8) {
            $error = '新しいパスワードは8文字以上にしてください。';
        } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = '新しいパスワードには英字と数字を両方含めてください。';
        }
    }

    if ($error === '') {
        // メール更新
        $pdo->prepare('UPDATE users SET email = :e WHERE id = :id')
            ->execute([':e' => $email, ':id' => $id]);

        if ($changing_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute([':p' => $hashed, ':id' => $id]);
            $info = 'パスワードとメールを更新しました。';
        } else {
            $info = 'メールを更新しました。';
        }

        // 最新を再取得
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>アカウント設定 | 管理画面</title>
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
        fieldset { border: 1px solid #ddd; padding: 16px; margin-top: 24px; }
        legend { font-size: .9rem; font-weight: bold; padding: 0 8px; }
    </style>
</head>
<body>
    <h1>アカウント設定</h1>
    <p class="meta">ユーザー名: <?= h($user['username']) ?> ／ ロール: <?= h($user['role']) ?></p>

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

        <fieldset>
            <legend>パスワードを変更する場合のみ入力</legend>

            <label>現在のパスワード
                <input type="password" name="current_password" autocomplete="current-password">
            </label>

            <label>新しいパスワード
                <input type="password" name="new_password" autocomplete="new-password" minlength="8">
                <p class="hint">8文字以上、英字と数字を両方含む。空のままなら変更されません。</p>
            </label>
        </fieldset>

        <button type="submit">更新する</button>
    </form>

    <a class="back" href="<?= SITE_URL ?>/admin/index.php">← 記事一覧へ戻る</a>
</body>
</html>
