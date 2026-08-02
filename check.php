<?php
// ===================================================
//  Takoyaki CMS 設置診断スクリプト（一時ファイル）
//
//  CMSを設置したディレクトリに置いてブラウザで開く。
//    例: https://your-site.com/takoyaki-cms/check.php
//
//  ★確認が終わったら必ず削除すること★
//  （PHPバージョンやDB名などの環境情報が見えるため）
// ===================================================

// このスクリプト内ではエラーを画面に出す（サーバ設定に関係なく）
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=UTF-8');

$ok   = [];
$ng   = [];
$warn = [];

function row(string $label, string $value, string $state = ''): void
{
    $color = ['ok' => '#0a7', 'ng' => '#c33', 'warn' => '#c80'][$state] ?? '#666';
    $mark  = ['ok' => 'OK', 'ng' => 'NG', 'warn' => '注意'][$state] ?? '-';
    echo '<tr>'
       . '<td style="padding:6px 12px;border-bottom:1px solid #eee;white-space:nowrap">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
       . '<td style="padding:6px 12px;border-bottom:1px solid #eee;color:' . $color . ';font-weight:bold;white-space:nowrap">' . $mark . '</td>'
       . '<td style="padding:6px 12px;border-bottom:1px solid #eee;font-family:monospace;font-size:13px">' . nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . '</td>'
       . '</tr>';
}

echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<body style="font-family:sans-serif;max-width:900px;margin:2em auto;padding:0 1em;line-height:1.6">'
   . '<h1>Takoyaki CMS 設置診断</h1>'
   . '<p style="background:#fee;border-left:4px solid #c33;padding:8px 12px">'
   . '確認が終わったら <code>check.php</code> は<strong>必ず削除</strong>してください。</p>'
   . '<table style="border-collapse:collapse;width:100%">';


// --- 1. PHP環境 ---
echo '<tr><th colspan="3" style="text-align:left;padding:16px 12px 4px;border-bottom:2px solid #333">1. PHP環境</th></tr>';

$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
row('PHPバージョン', PHP_VERSION . '（要件: 8.0以上）', $phpOk ? 'ok' : 'ng');
row('SAPI', PHP_SAPI);

foreach (['pdo_mysql' => 'DB接続に必須', 'gd' => '画像リサイズに必須', 'mbstring' => '日本語処理に必須', 'json' => '画像アップロードに必須'] as $ext => $why) {
    row('拡張: ' . $ext, extension_loaded($ext) ? '有効' : '無効（' . $why . '）', extension_loaded($ext) ? 'ok' : 'ng');
}

row('display_errors', ini_get('display_errors') ? 'On' : 'Off（画面が真っ白になる原因）', ini_get('display_errors') ? 'ok' : 'warn');
row('error_log', (string)(ini_get('error_log') ?: '(未設定 / サーバ既定の場所)'));


// --- 2. ファイルの配置 ---
echo '<tr><th colspan="3" style="text-align:left;padding:16px 12px 4px;border-bottom:2px solid #333">2. ファイルの配置</th></tr>';

row('このファイルの場所', __DIR__);

$required = ['config.php', 'functions.php', 'login.php', 'admin/index.php', 'schema.sql'];
foreach ($required as $f) {
    $path = __DIR__ . '/' . $f;
    row('存在: ' . $f, is_file($path) ? '有り' : '★見つからない★', is_file($path) ? 'ok' : 'ng');
}

$setupExists = is_file(__DIR__ . '/setup.php');
row('setup.php', $setupExists ? '有り（管理者登録がまだならこのまま。登録後は必ず削除）' : '無し（登録済みなら正常）', $setupExists ? 'warn' : 'ok');

$uploadsDir = __DIR__ . '/uploads';
if (is_dir($uploadsDir)) {
    row('uploads/ 書き込み', is_writable($uploadsDir) ? '可（' . substr(sprintf('%o', fileperms($uploadsDir)), -4) . '）' : '不可 — chmod 755 が必要', is_writable($uploadsDir) ? 'ok' : 'ng');
    row('uploads/.htaccess', is_file($uploadsDir . '/.htaccess') ? '有り' : '無し — FTPでドット始まりのファイルが転送されていない可能性', is_file($uploadsDir . '/.htaccess') ? 'ok' : 'warn');
} else {
    row('uploads/', '★ディレクトリが無い★', 'ng');
}


// --- 3. config.php の読み込み ---
echo '<tr><th colspan="3" style="text-align:left;padding:16px 12px 4px;border-bottom:2px solid #333">3. config.php の読み込み</th></tr>';

if (!is_file(__DIR__ . '/config.php')) {
    row('読み込み', '★config.php が無い。config.example.php をコピーして作る★', 'ng');
} else {
    require_once __DIR__ . '/config.php';
    row('読み込み', '成功', 'ok');

    row('DB_HOST', defined('DB_HOST') ? DB_HOST : '(未定義)', defined('DB_HOST') ? 'ok' : 'ng');
    row('DB_NAME', defined('DB_NAME') ? DB_NAME : '(未定義)', defined('DB_NAME') ? 'ok' : 'ng');
    row('DB_USER', defined('DB_USER') ? DB_USER : '(未定義)', defined('DB_USER') ? 'ok' : 'ng');
    row('DB_PASS', defined('DB_PASS') ? (DB_PASS === '' ? '★空★' : '(設定済み・非表示)') : '(未定義)', (defined('DB_PASS') && DB_PASS !== '') ? 'ok' : 'warn');

    // 関数がちゃんと読み込まれているか（config.php と functions.php の分割が効いているか）
    $fnOk = function_exists('db') && function_exists('h') && function_exists('get_posts');
    row('ヘルパー関数', $fnOk ? 'functions.php を読み込めている' : '★functions.php が読み込めていない★', $fnOk ? 'ok' : 'ng');
}


// --- 4. SITE_URL と実際のURLの一致 ---
echo '<tr><th colspan="3" style="text-align:left;padding:16px 12px 4px;border-bottom:2px solid #333">4. SITE_URL の設定</th></tr>';

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? '';
$dir      = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$detected = $scheme . '://' . $host . $dir;

row('実際のURL（自動検出）', $detected);

if (defined('SITE_URL')) {
    $match = (rtrim(SITE_URL, '/') === $detected);
    row('config.php の SITE_URL', SITE_URL . ($match ? '' : "\n★不一致★ 上の「実際のURL」に合わせてください"), $match ? 'ok' : 'ng');
    row('UPLOAD_URL', defined('UPLOAD_URL') ? UPLOAD_URL : '(未定義)');
} else {
    row('SITE_URL', '(未定義)', 'ng');
}


// --- 5. DB接続 ---
echo '<tr><th colspan="3" style="text-align:left;padding:16px 12px 4px;border-bottom:2px solid #333">5. データベース接続</th></tr>';

if (defined('DB_HOST')) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        row('接続', '成功', 'ok');
        row('MySQLバージョン', (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

        // テーブルが揃っているか
        $need  = ['posts', 'post_sections', 'skills', 'categories', 'post_categories', 'tags', 'post_tags', 'users', 'site_settings', 'login_attempts'];
        $have  = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $miss  = array_diff($need, $have);
        row('テーブル', $miss ? '★不足: ' . implode(', ', $miss) . '★（schema.sql を投入する）' : count($have) . '個すべて有り', $miss ? 'ng' : 'ok');

        if (!$miss) {
            $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            row('管理者ユーザー', $userCount > 0 ? $userCount . '人 登録済み' : '0人 — setup.php で登録が必要', $userCount > 0 ? 'ok' : 'warn');
            row('作品(posts)', (string)(int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn() . '件');
        }
    } catch (PDOException $e) {
        row('接続', "★失敗★\n" . $e->getMessage(), 'ng');
        echo '<tr><td colspan="3" style="padding:12px;background:#fff8f8">'
           . '<strong>よくある原因</strong><br>'
           . '・[2002] No such file or directory / Connection refused → <code>DB_HOST</code> が違う。'
           . 'ロリポップはDBが別サーバなので <code>localhost</code> ではなく、'
           . 'ユーザー専用ページに表示される <code>mysqlXXX.phy.lolipop.lan</code> 形式のホスト名を指定する<br>'
           . '・[1045] Access denied → <code>DB_USER</code> / <code>DB_PASS</code> が違う<br>'
           . '・[1049] Unknown database → <code>DB_NAME</code> が違う'
           . '</td></tr>';
    }
} else {
    row('接続', 'config.php が読めていないため未実施', 'ng');
}

echo '</table>'
   . '<p style="margin-top:2em;background:#fee;border-left:4px solid #c33;padding:8px 12px">'
   . '確認が終わったら <code>check.php</code> を<strong>削除</strong>してください。</p>'
   . '</body>';
