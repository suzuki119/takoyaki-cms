<?php
// ===================================================
//  管理者ログイン画面
// ===================================================
require_once 'config.php';
start_session();

// すでにログイン済みなら管理画面へ
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

// レート制限の設定：15分間に5回失敗したらブロック
const LOGIN_MAX_ATTEMPTS    = 5;
const LOGIN_WINDOW_MINUTES  = 15;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

    $pdo = db();

    // 同一IPからの直近の失敗回数をカウント
    $cnt_stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = :ip
           AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)'
    );
    $cnt_stmt->bindValue(':ip', $ip);
    $cnt_stmt->bindValue(':minutes', LOGIN_WINDOW_MINUTES, PDO::PARAM_INT);
    $cnt_stmt->execute();
    $recent_failures = (int)$cnt_stmt->fetchColumn();

    if ($recent_failures >= LOGIN_MAX_ATTEMPTS) {
        $error = 'ログイン試行回数が多すぎます。' . LOGIN_WINDOW_MINUTES . '分後にもう一度お試しください。';
    } elseif ($username === '' || $password === '') {
        $error = 'ユーザー名とパスワードを入力してください。';
    } else {
        // ユーザー名でDBを検索
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // ログイン成功
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'] ?? 'editor';

            // このIPの失敗履歴をクリア
            $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip')
                ->execute([':ip' => $ip]);

            header('Location: ' . SITE_URL . '/admin/index.php');
            exit;
        }

        // 失敗を記録（ユーザー名が間違っている場合も同じメッセージ：情報漏洩防止）
        $pdo->prepare('INSERT INTO login_attempts (ip_address) VALUES (:ip)')
            ->execute([':ip' => $ip]);

        $error = 'ユーザー名またはパスワードが正しくありません。';
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <style>
        body { font-family: sans-serif; max-width: 360px; margin: 80px auto; padding: 0 20px; }
        h1 { font-size: 1.3rem; margin-bottom: 24px; }
        label { display: block; margin-top: 16px; font-size: .9rem; }
        input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        button:hover { background: #444; }
        .error { margin-top: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
    </style>
</head>
<body>
    <h1>管理者ログイン</h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div><?php // [自作] h()=XSS対策エスケープ関数 ?>
    <?php endif; ?>

    <form method="post">
        <label>ユーザー名<br>
            <input type="text" name="username" autocomplete="username" required>
        </label>
        <label>パスワード<br>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">ログイン</button>
    </form>
</body>
</html>
