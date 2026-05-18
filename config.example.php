<?php
// ===================================================
//  Takoyaki CMS 設定ファイル（テンプレート）
//  このファイルを config.php としてコピーし、
//  自分の環境に合わせて編集してください。
// ===================================================

// --- DB接続情報 ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// --- サイト設定 ---
// このCMSを設置したURL（末尾のスラッシュなし）
define('SITE_URL', 'http://localhost:8888/takoyaki-cms');

// アップロード画像の保存先ディレクトリと公開URL
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// ===================================================
//  PDO でDB接続する関数
// ===================================================

/**
 * DB接続を返す
 * 使い方： $pdo = db();
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            exit('DB接続エラー: ' . $e->getMessage());
        }
    }

    return $pdo;
}

// ===================================================
//  共通ヘルパー関数
// ===================================================

/**
 * XSS対策：HTML特殊文字をエスケープして出力
 * 使い方： echo h($変数);
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * ログイン済みかチェック。未ログインならログイン画面へ飛ばす
 * 使い方： require_login();
 */
function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}
