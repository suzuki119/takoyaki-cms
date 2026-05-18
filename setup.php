<?php
// ===================================================
//  管理者ユーザー初回登録用スクリプト
//  使用後は必ず削除すること！
// ===================================================
require_once 'config.php'; // [組み込み] 別ファイルを1回だけ読み込む

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // $_SERVER=サーバー情報の組み込み変数 / REQUEST_METHODでGET/POSTを判定
    $username = trim($_POST['username'] ?? ''); // [組み込み] trim()=前後の空白を除去 / ??=nullなら右の値を使う
    $password = $_POST['password'] ?? '';
    $email    = trim($_POST['email'] ?? '');

    // 簡易バリデーション
    if ($username === '' || $password === '') {
        $message = 'エラー：ユーザー名とパスワードは必須です。';
    } elseif (strlen($password) < 8) { // [組み込み] strlen()=文字列の長さを返す
        $message = 'エラー：パスワードは8文字以上にしてください。';
    } else {
        $pdo = db(); // [自作] config.phpのDB接続関数

        // すでにユーザーが存在する場合は登録させない
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users'); // [PDO組み込み] SQLを準備する
        $stmt->execute();                                    // [PDO組み込み] SQLを実行する
        if ($stmt->fetchColumn() > 0) {                     // [PDO組み込み] 結果の1列目を取得
            $message = 'エラー：管理者はすでに登録されています。このファイルを削除してください。';
        } else {
            // password_hash() でハッシュ化してDBに保存
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // [組み込み] password_hash()=パスワードをハッシュ化 / [組み込み定数] PASSWORD_DEFAULT=推奨アルゴリズムを自動選択

            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password, email) VALUES (:username, :password, :email)'
            );
            $stmt->execute([
                ':username' => $username,
                ':password' => $hashed,
                ':email'    => $email,
            ]);

            $message = '✅ 管理者を登録しました！このファイル（setup.php）を今すぐ削除してください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者登録（初回のみ）</title>
    <style>
        body { font-family: sans-serif; max-width: 400px; margin: 60px auto; padding: 0 20px; }
        h1 { font-size: 1.2rem; }
        label { display: block; margin-top: 16px; font-size: .9rem; }
        input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; }
        button { margin-top: 20px; padding: 10px 24px; background: #333; color: #fff; border: none; cursor: pointer; }
        .message { margin-top: 20px; padding: 12px; background: #f0f0f0; border-left: 4px solid #333; }
        .warning { background: #fff3cd; border-left-color: #e0a800; margin-bottom: 20px; padding: 10px; }
    </style>
</head>
<body>
    <h1>管理者ユーザー登録（初回のみ）</h1>

    <div class="warning">
        ⚠️ このページは初回セットアップ専用です。登録後は <code>setup.php</code> を削除してください。
    </div>

    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div><?php // [自作] h()=XSS対策エスケープ関数 ?>
    <?php endif; ?>

    <form method="post">
        <label>ユーザー名（ログイン名）<br>
            <input type="text" name="username" required autocomplete="off">
        </label>
        <label>パスワード（8文字以上）<br>
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>メールアドレス（任意）<br>
            <input type="email" name="email">
        </label>
        <button type="submit">登録する</button>
    </form>
</body>
</html>
