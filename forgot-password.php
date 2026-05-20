<?php
// ===================================================
//  パスワードリセット申請
//  ユーザー名 or メールアドレスを受け取り、リセットリンクをメール送信する。
//  （ユーザーの存在有無は明かさない）
// ===================================================
require_once 'config.php';
start_session();

$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if ($identifier !== '') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE username = :v OR email = :v2 LIMIT 1');
        $stmt->execute([':v' => $identifier, ':v2' => $identifier]);
        $user = $stmt->fetch();

        if ($user && !empty($user['email'])) {
            // 生トークンを発行し、ハッシュを保存
            $token      = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires    = date('Y-m-d H:i:s', time() + 3600); // 1時間有効

            $pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :th, :exp)'
            )->execute([':uid' => $user['id'], ':th' => $token_hash, ':exp' => $expires]);

            $reset_url = SITE_URL . '/reset-password.php?token=' . $token;
            $body = "パスワードリセットのご依頼を受け付けました。\n\n"
                  . "以下のリンクから新しいパスワードを設定してください（1時間有効）：\n"
                  . $reset_url . "\n\n"
                  . "心当たりがない場合は、このメールは無視してください。\n";

            send_email($user['email'], 'パスワードリセットのご案内', $body);
        }
    }

    // 存在の有無に関わらず同じ完了表示（情報漏洩防止）
    $done = true;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>パスワードをお忘れですか</title>
    <style>
        body { font-family: sans-serif; max-width: 360px; margin: 80px auto; padding: 0 20px; }
        h1 { font-size: 1.3rem; margin-bottom: 24px; }
        label { display: block; margin-top: 16px; font-size: .9rem; }
        input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        button:hover { background: #444; }
        .info { margin-top: 16px; padding: 10px; background: #eafaf1; border-left: 4px solid #27ae60; font-size: .9rem; }
        .hint { font-size: .8rem; color: #666; margin-top: 8px; }
        .back { display: block; margin-top: 24px; font-size: .85rem; color: #666; }
    </style>
</head>
<body>
    <h1>パスワードのリセット</h1>

    <?php if ($done): ?>
        <div class="info">
            入力されたアカウントが存在する場合、登録メールアドレスにリセット用のリンクを送信しました。
            メールをご確認ください（1時間有効）。
        </div>
    <?php else: ?>
        <form method="post">
            <label>ユーザー名 または メールアドレス
                <input type="text" name="identifier" required autocomplete="username">
            </label>
            <button type="submit">リセットリンクを送信</button>
            <p class="hint">登録済みのメールアドレスにリセット用リンクをお送りします。</p>
        </form>
    <?php endif; ?>

    <a class="back" href="<?= h(SITE_URL) ?>/login.php">← ログインへ戻る</a>
</body>
</html>
