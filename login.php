<?php
// ===================================================
//  管理者ログイン画面
// ===================================================
require_once 'config.php'; // [組み込み] 別ファイルを1回だけ読み込む

// セッション開始
if (session_status() === PHP_SESSION_NONE) { // [組み込み関数] セッションの状態を返す / [組み込み定数] セッション未開始
    session_start(); // [組み込み] セッションを開始する
}

// すでにログイン済みなら管理画面へ
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // $_SERVER はサーバー情報が入るPHP組み込みの変数。REQUEST_METHODでGET/POSTを判定
    $username = trim($_POST['username'] ?? ''); // [組み込み] trim()=前後の空白を除去 / $_POST はフォームの送信値
    $password = $_POST['password'] ?? '';       // ?? は「左がnullなら右を使う」（null合体演算子）

    if ($username === '' || $password === '') {
        $error = 'ユーザー名とパスワードを入力してください。';
    } else {
        $pdo = db(); // [自作] config.phpのDB接続関数

        // ① ユーザー名でDBを検索
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1'); // [PDO組み込み] SQLを準備する
        $stmt->execute([':username' => $username]); // [PDO組み込み] SQLを実行する

        $user = $stmt->fetch(); // [PDO組み込み] 結果を1行取得。見つからなければ false

        // ② password_verify() でハッシュと照合
        if ($user && password_verify($password, $user['password'])) { // [組み込み] パスワードとハッシュを照合する
            // ログイン成功

            // セッションIDを再生成してセッションハイジャック対策
            session_regenerate_id(true); // [組み込み] セッションIDを新しく作り直す

            $_SESSION['user_id']  = $user['id'];       // セッションにログイン情報を保存
            $_SESSION['username'] = $user['username'];

            header('Location: ' . SITE_URL . '/admin/index.php');
            exit;
        } else {
            // ユーザー名が間違っている場合も同じメッセージにする（情報漏洩防止）
            $error = 'ユーザー名またはパスワードが正しくありません。';
        }
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
