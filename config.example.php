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
 * セッションを安全な設定で開始する。
 * Cookieはhttponly, samesite=Lax, HTTPS時はsecure付き。
 * 既にセッション開始済みの場合は何もしない。
 */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * ログイン済みかチェック。未ログインならログイン画面へ飛ばす
 * 使い方： require_login();
 */
function require_login(): void
{
    start_session();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

/**
 * 現在ログイン中のユーザーのロール（admin / editor）を返す。
 * 未ログインや不明なときは空文字を返す。
 */
function user_role(): string
{
    start_session();
    return $_SESSION['role'] ?? '';
}

/**
 * 管理者権限を要求する。editor 以下なら 403 で終了。
 * 使い方： require_admin();
 */
function require_admin(): void
{
    require_login();
    if (user_role() !== 'admin') {
        http_response_code(403);
        exit('この操作には管理者権限が必要です。');
    }
}

/**
 * 文字列をURL用 slug に変換する。
 * 英数字・ハイフンのみに正規化。日本語のみの文字列の場合は空文字を返すので、
 * 呼び出し側は空文字なら NULL として扱うこと。
 */
function sluggify(string $text, int $maxLen = 60): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    return substr($slug, 0, $maxLen);
}

/**
 * CSRFトークンを取得（セッションごとに一意、初回呼び出し時に生成）
 */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * フォームに埋め込むCSRFトークンのhidden inputを返す
 * 使い方： <?= csrf_field() ?>
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/**
 * POSTリクエストのCSRFトークンを検証。一致しなければHTTP 403で終了
 * 使い方： POST処理の冒頭で verify_csrf();
 */
function verify_csrf(): void
{
    $expected = csrf_token();
    $given    = $_POST['_csrf'] ?? '';
    if (!is_string($given) || !hash_equals($expected, $given)) {
        http_response_code(403);
        exit('CSRF検証に失敗しました。フォームを再読み込みしてください。');
    }
}
