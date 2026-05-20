<?php
// ===================================================
//  パスワードリセット実行
//  メールのリンクに含まれるトークンを検証し、新しいパスワードを設定する。
// ===================================================
require_once 'config.php';
start_session();

$pdo   = db();
$error = '';
$done  = false;

// トークンは GET（リンク経由）または POST（フォーム送信）から
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($token) ? trim($token) : '';

/**
 * 有効なリセットレコードを返す（無ければ null）
 */
function find_valid_reset(PDO $pdo, string $token): ?array
{
    if ($token === '') return null;
    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT * FROM password_resets
          WHERE token_hash = :th AND used = 0 AND expires_at >= NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':th' => $token_hash]);
    return $stmt->fetch() ?: null;
}

$reset = find_valid_reset($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';

    if (!$reset) {
        $error = 'リンクが無効か期限切れです。お手数ですが、もう一度リセットを申請してください。';
    } elseif (strlen($new_password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = 'パスワードには英字と数字を両方含めてください。';
    } else {
        // パスワード更新 + トークンを使用済みに
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
            ->execute([':p' => $hashed, ':id' => $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id')
            ->execute([':id' => $reset['id']]);
        // 同ユーザーの他の未使用トークンも無効化
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid AND used = 0')
            ->execute([':uid' => $reset['user_id']]);

        log_action('user.reset_password_self', 'user', (int)$reset['user_id'], 'メールリセット経由');
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>新しいパスワードの設定</title>
    <style>
        body { font-family: sans-serif; max-width: 360px; margin: 80px auto; padding: 0 20px; }
        h1 { font-size: 1.3rem; margin-bottom: 24px; }
        label { display: block; margin-top: 16px; font-size: .9rem; }
        input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        button:hover { background: #444; }
        .error { margin-top: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .info { margin-top: 16px; padding: 10px; background: #eafaf1; border-left: 4px solid #27ae60; font-size: .9rem; }
        .hint { font-size: .8rem; color: #666; margin-top: 8px; }
        .back { display: block; margin-top: 24px; font-size: .85rem; color: #666; }
    </style>
</head>
<body>
    <h1>新しいパスワードの設定</h1>

    <?php if ($done): ?>
        <div class="info">パスワードを変更しました。新しいパスワードでログインしてください。</div>
        <a class="back" href="<?= h(SITE_URL) ?>/login.php">ログインへ →</a>
    <?php elseif (!$reset): ?>
        <div class="error">リンクが無効か、期限切れです。</div>
        <a class="back" href="<?= h(SITE_URL) ?>/forgot-password.php">← もう一度リセットを申請する</a>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <label>新しいパスワード
                <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit">パスワードを変更</button>
            <p class="hint">8文字以上、英字と数字を両方含めてください。</p>
        </form>
    <?php endif; ?>
</body>
</html>
