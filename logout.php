<?php
// ===================================================
//  ログアウト処理
// ===================================================
require_once 'config.php';
start_session();

// セッション変数を全削除
$_SESSION = []; // $_SESSION に空配列を代入して中身をすべて消す

// セッションクッキーを削除
if (ini_get('session.use_cookies')) { // [組み込み] ini_get()=PHPの設定値を取得する
    $params = session_get_cookie_params(); // [組み込み] セッションクッキーの設定情報を取得する
    setcookie(                             // [組み込み] ブラウザのクッキーを操作する
        session_name(),                    // [組み込み] セッション名を取得（デフォルトは "PHPSESSID"）
        '',                                // 値を空にする
        time() - 42000,                    // [組み込み] time()=現在時刻（過去の時刻を指定してクッキーを失効させる）
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// セッション破棄
session_destroy(); // [組み込み] サーバー側のセッションデータを完全に削除する

// ログイン画面へリダイレクト
header('Location: ' . SITE_URL . '/login.php');
exit; // [組み込み] 処理を止める
