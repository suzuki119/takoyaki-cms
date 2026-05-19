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

// --- 画像アップロード設定 ---
define('MAX_UPLOAD_SIZE',   5 * 1024 * 1024); // 5MB
define('IMAGE_MAX_WIDTH',   1600);            // 元画像の最大幅（超えるとリサイズ）
define('IMAGE_THUMB_WIDTH', 300);             // サムネイル変種の幅

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
 * GD で画像をリサイズして保存する。
 * 元画像が $maxWidth 以下なら、$sourcePath != $destPath の場合のみコピーする。
 * JPEG / PNG / GIF / WebP に対応。
 *
 * @param string $sourcePath 元画像のパス
 * @param int    $maxWidth   最大幅（高さは比率維持）
 * @param string $destPath   保存先パス（拡張子で出力フォーマット決定）
 * @return bool 成功時 true
 */
function resize_image(string $sourcePath, int $maxWidth, string $destPath): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        return false;
    }

    [$origW, $origH] = $info;
    $mime = $info['mime'];

    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($sourcePath);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($sourcePath);
    } elseif ($mime === 'image/gif') {
        $src = @imagecreatefromgif($sourcePath);
    } elseif ($mime === 'image/webp') {
        $src = @imagecreatefromwebp($sourcePath);
    } else {
        return false;
    }

    if (!$src) {
        return false;
    }

    // 縮小不要なら、必要に応じてコピーするだけで終わり
    if ($origW <= $maxWidth) {
        imagedestroy($src);
        if ($sourcePath === $destPath) {
            return true;
        }
        return copy($sourcePath, $destPath);
    }

    $newW = $maxWidth;
    $newH = (int) round($origH * ($maxWidth / $origW));

    $dst = imagecreatetruecolor($newW, $newH);

    // PNG / GIF の透過を保持
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $result = imagejpeg($dst, $destPath, 85);
    } elseif ($ext === 'png') {
        $result = imagepng($dst, $destPath);
    } elseif ($ext === 'gif') {
        $result = imagegif($dst, $destPath);
    } elseif ($ext === 'webp') {
        $result = imagewebp($dst, $destPath, 85);
    } else {
        $result = false;
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $result;
}

/**
 * 画像ファイル名から -thumb 付きのサムネイル変種名を返す。
 *   "abc123.jpg" -> "abc123-thumb.jpg"
 */
function thumb_filename(string $filename): string
{
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    return $base . '-thumb.' . $ext;
}

// ===================================================
//  フロントエンド用 記事取得ヘルパー
//  公開ページ（テーマ）から呼び出す想定のヘルパー関数群。
//  既定では公開中の記事のみを返す（status='published' かつ
//  published_at <= NOW()）。下書きを含めたい場合は include_drafts オプション。
// ===================================================

/**
 * 公開中の記事を取得する。
 *
 * @param array $opts {
 *     limit         (int)    最大件数（省略時は全件）
 *     offset        (int)    オフセット
 *     category_id   (int)    特定カテゴリで絞り込み
 *     order_by      (string) 並び順カラム（既定 'sort_order'）
 *     order         (string) 'ASC' or 'DESC'（既定 'ASC'）
 *     include_drafts (bool)  下書き・予約も含めるか（既定 false）
 * }
 * @return array
 */
function get_posts(array $opts = []): array
{
    $opts += [
        'limit'          => null,
        'offset'         => 0,
        'category_id'    => null,
        'order_by'       => 'sort_order',
        'order'          => 'ASC',
        'include_drafts' => false,
    ];

    // ORDER BY 句の値は識別子なのでホワイトリスト検証
    $allowed_columns = ['sort_order', 'created_at', 'published_at', 'updated_at', 'id', 'title'];
    $order_by        = in_array($opts['order_by'], $allowed_columns, true) ? $opts['order_by'] : 'sort_order';
    $order           = strtoupper($opts['order']) === 'DESC' ? 'DESC' : 'ASC';

    $where  = [];
    $params = [];

    if (!$opts['include_drafts']) {
        $where[] = "p.status = 'published'";
        $where[] = "(p.published_at IS NULL OR p.published_at <= NOW())";
    }

    $join = '';
    if (!empty($opts['category_id'])) {
        $join             = 'INNER JOIN post_categories pc ON pc.post_id = p.id';
        $where[]          = 'pc.category_id = :category_id';
        $params[':category_id'] = (int)$opts['category_id'];
    }

    $sql  = "SELECT p.* FROM posts p $join";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY p.$order_by $order";

    if ($opts['limit'] !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    if ($opts['limit'] !== null) {
        $stmt->bindValue(':limit', (int)$opts['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$opts['offset'], PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * 1件の記事を取得。
 *
 * @param int|string $id_or_slug   数値ならID、文字列ならslug
 * @param bool       $include_drafts 下書き・予約も含めるか
 * @return array|null 見つからなければ null
 */
function get_post($id_or_slug, bool $include_drafts = false): ?array
{
    if (is_numeric($id_or_slug)) {
        $sql    = 'SELECT * FROM posts WHERE id = :v LIMIT 1';
        $params = [':v' => (int)$id_or_slug];
    } else {
        $sql    = 'SELECT * FROM posts WHERE slug = :v LIMIT 1';
        $params = [':v' => (string)$id_or_slug];
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $post = $stmt->fetch();

    if (!$post) {
        return null;
    }

    // 公開状態のチェック
    if (!$include_drafts) {
        $is_live = $post['status'] === 'published'
            && (empty($post['published_at']) || strtotime($post['published_at']) <= time());
        if (!$is_live) {
            return null;
        }
    }

    return $post;
}

/**
 * 全カテゴリを取得（登録順）
 */
function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll();
}

/**
 * 1件のカテゴリを取得（ID または slug）
 */
function get_category($id_or_slug): ?array
{
    if (is_numeric($id_or_slug)) {
        $stmt = db()->prepare('SELECT * FROM categories WHERE id = :v LIMIT 1');
        $stmt->execute([':v' => (int)$id_or_slug]);
    } else {
        $stmt = db()->prepare('SELECT * FROM categories WHERE slug = :v LIMIT 1');
        $stmt->execute([':v' => (string)$id_or_slug]);
    }
    return $stmt->fetch() ?: null;
}

/**
 * 特定の記事に紐付いているカテゴリを取得
 */
function get_post_categories(int $post_id): array
{
    $stmt = db()->prepare(
        'SELECT c.* FROM categories c
           INNER JOIN post_categories pc ON pc.category_id = c.id
          WHERE pc.post_id = :id
          ORDER BY c.id ASC'
    );
    $stmt->execute([':id' => $post_id]);
    return $stmt->fetchAll();
}

/**
 * 記事のサムネイル変種URLを返す（thumb 変種が存在しない場合は元画像URL）
 */
function post_thumb_url(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }
    $thumb = thumb_filename($filename);
    return file_exists(UPLOAD_DIR . $thumb) ? UPLOAD_URL . $thumb : UPLOAD_URL . $filename;
}

// ===================================================
//  サイト設定 (site_settings テーブル)
//  管理画面 admin/settings.php から編集可能
// ===================================================

/**
 * サイト設定の値を取得。
 * 静的キャッシュで同一リクエスト内のクエリは1回のみ。
 */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = db()->query('SELECT `key`, `value` FROM site_settings');
            foreach ($stmt as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (PDOException $e) {
            // site_settings テーブルがまだ無いケース（マイグレーション前）
            return $default;
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * サイト設定の値を保存（無ければ追加、あれば更新）。
 */
function set_setting(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO site_settings (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = :v2'
    );
    $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

// ===================================================
//  監査ログ (audit_logs テーブル)
// ===================================================

/**
 * 監査ログを記録する。
 *
 * @param string  $action      'post.create', 'user.delete' など
 * @param string  $target_type 'post', 'user', 'category', 'setting' など
 * @param int|null $target_id  対象レコードのID
 * @param string|null $details 任意のメモ（例: 変更前後の差分）
 */
function log_action(string $action, ?string $target_type = null, ?int $target_id = null, ?string $details = null): void
{
    start_session();
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, username, action, target_type, target_id, details, ip_address)
             VALUES (:uid, :uname, :action, :ttype, :tid, :details, :ip)'
        );
        $stmt->execute([
            ':uid'     => $_SESSION['user_id'] ?? null,
            ':uname'   => $_SESSION['username'] ?? null,
            ':action'  => $action,
            ':ttype'   => $target_type,
            ':tid'     => $target_id,
            ':details' => $details,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $e) {
        // ログ取得失敗時は本処理を止めない（テーブル未作成や接続瞬断）
    }
}

// ===================================================
//  フック / フィルター / ショートコード
//  WordPress 風の拡張ポイント。プラグインで使う。
// ===================================================

/**
 * アクションフックにコールバックを登録する。
 * @param string   $action   フック名（例: 'post.save'）
 * @param callable $callback フック発火時に呼ばれる関数
 * @param int      $priority 小さいほど先に呼ばれる
 */
function add_action(string $action, callable $callback, int $priority = 10): void
{
    $GLOBALS['_takoyaki_actions'][$action][] = ['cb' => $callback, 'p' => $priority];
}

/**
 * アクションフックを発火する。登録された全コールバックを priority 順に呼び出す。
 * @param string $action フック名
 * @param mixed  ...$args コールバックに渡す追加引数
 */
function do_action(string $action, ...$args): void
{
    $list = $GLOBALS['_takoyaki_actions'][$action] ?? [];
    if (empty($list)) return;

    usort($list, fn($a, $b) => $a['p'] <=> $b['p']);
    foreach ($list as $entry) {
        call_user_func_array($entry['cb'], $args);
    }
}

/**
 * フィルターフックにコールバックを登録する。
 * コールバックは値を受け取り、加工した値を返す（チェーン可能）。
 */
function add_filter(string $filter, callable $callback, int $priority = 10): void
{
    $GLOBALS['_takoyaki_filters'][$filter][] = ['cb' => $callback, 'p' => $priority];
}

/**
 * フィルターチェーンを適用する。
 * @param string $filter フィルター名
 * @param mixed  $value  初期値
 * @return mixed 全コールバック適用後の値
 */
function apply_filters(string $filter, $value, ...$args)
{
    $list = $GLOBALS['_takoyaki_filters'][$filter] ?? [];
    if (empty($list)) return $value;

    usort($list, fn($a, $b) => $a['p'] <=> $b['p']);
    foreach ($list as $entry) {
        $value = call_user_func_array($entry['cb'], [$value, ...$args]);
    }
    return $value;
}

/**
 * ショートコードを登録する。
 * コールバックは $attrs（属性連想配列）を受け取り、置き換える文字列を返す。
 *
 *   add_shortcode('today', fn($attrs) => date('Y-m-d'));
 *   // 本文中の [today] が「2026-05-20」に展開される
 */
function add_shortcode(string $tag, callable $callback): void
{
    $GLOBALS['_takoyaki_shortcodes'][$tag] = $callback;
}

/**
 * 文字列中のショートコード [tag attr="val"] をすべて解析・置き換えて返す。
 */
function do_shortcodes(string $content): string
{
    $shortcodes = $GLOBALS['_takoyaki_shortcodes'] ?? [];
    if (empty($shortcodes)) return $content;

    $tag_alt = implode('|', array_map(fn($t) => preg_quote($t, '/'), array_keys($shortcodes)));
    $pattern = '/\[(' . $tag_alt . ')((?:\s+\w+="[^"]*")*)\s*\/?\]/';

    return preg_replace_callback($pattern, function ($m) use ($shortcodes) {
        $tag   = $m[1];
        $attrs = [];
        if (!empty($m[2])) {
            preg_match_all('/(\w+)="([^"]*)"/', $m[2], $attr_matches, PREG_SET_ORDER);
            foreach ($attr_matches as $am) {
                $attrs[$am[1]] = $am[2];
            }
        }
        return (string) call_user_func($shortcodes[$tag], $attrs);
    }, $content);
}

// ===================================================
//  プラグインローダー
//  plugins/ ディレクトリ配下の有効化済みプラグインを読み込む。
// ===================================================

/**
 * 有効化済みプラグインの名前一覧を返す。
 * site_settings.enabled_plugins に JSON 配列で保存されている。
 */
function get_enabled_plugins(): array
{
    $raw = get_setting('enabled_plugins', '[]');
    $list = json_decode($raw ?? '[]', true);
    return is_array($list) ? $list : [];
}

/**
 * インストール済みのプラグイン一覧を返す（plugins/ ディレクトリのスキャン）。
 * 各プラグインは plugins/<name>/index.php と plugins/<name>/plugin.json を持つ。
 */
function scan_plugins(): array
{
    $dir = __DIR__ . '/plugins';
    if (!is_dir($dir)) return [];

    $plugins = [];
    foreach (scandir($dir) as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . '/' . $name;
        if (!is_dir($path)) continue;
        if (!file_exists($path . '/index.php')) continue;

        $meta = ['name' => $name, 'description' => '', 'version' => ''];
        if (file_exists($path . '/plugin.json')) {
            $json = json_decode((string)file_get_contents($path . '/plugin.json'), true);
            if (is_array($json)) {
                $meta = array_merge($meta, array_intersect_key($json, $meta));
            }
        }
        $plugins[] = $meta;
    }
    return $plugins;
}

/**
 * 有効化済みのプラグインを読み込む。
 * config.php の最後で呼ぶ。
 */
function load_plugins(): void
{
    $enabled = get_enabled_plugins();
    foreach ($enabled as $name) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) continue;  // 安全な名前のみ
        $entry = __DIR__ . '/plugins/' . $name . '/index.php';
        if (file_exists($entry)) {
            require_once $entry;
        }
    }
}

// ===================================================
//  タグシステム
//  カテゴリと別軸の自由ラベル。多対多 (post_tags)
// ===================================================

/**
 * 全タグを取得（登録順）
 */
function get_tags(): array
{
    return db()->query('SELECT * FROM tags ORDER BY id ASC')->fetchAll();
}

/**
 * 特定の記事に紐付くタグを取得
 */
function get_post_tags(int $post_id): array
{
    $stmt = db()->prepare(
        'SELECT t.* FROM tags t
           INNER JOIN post_tags pt ON pt.tag_id = t.id
          WHERE pt.post_id = :id
          ORDER BY t.id ASC'
    );
    $stmt->execute([':id' => $post_id]);
    return $stmt->fetchAll();
}

/**
 * 記事のタグを設定する。既存の紐付けは全て削除して入れ直す。
 *
 * @param int          $post_id
 * @param string|array $tags  カンマ区切り文字列 or タグ名の配列
 */
function set_post_tags(int $post_id, $tags): void
{
    if (is_string($tags)) {
        $tags = array_filter(array_map('trim', explode(',', $tags)));
    }
    if (!is_array($tags)) {
        $tags = [];
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM post_tags WHERE post_id = :id')
        ->execute([':id' => $post_id]);

    foreach ($tags as $name) {
        $name = trim((string)$name);
        if ($name === '') continue;

        // 既存タグを探す（名前で）
        $find = $pdo->prepare('SELECT id FROM tags WHERE name = :name LIMIT 1');
        $find->execute([':name' => $name]);
        $row = $find->fetch();

        if ($row) {
            $tag_id = (int)$row['id'];
        } else {
            // 新規作成（slug は sluggify。空なら id を後で採用）
            $slug = sluggify($name);
            try {
                $ins = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (:n, :s)');
                $ins->execute([':n' => $name, ':s' => $slug]);
                $tag_id = (int)$pdo->lastInsertId();
                if ($slug === '') {
                    $pdo->prepare('UPDATE tags SET slug = :s WHERE id = :id')
                        ->execute([':s' => (string)$tag_id, ':id' => $tag_id]);
                }
            } catch (PDOException $e) {
                // slug衝突などは別 slug にリトライ
                $slug2 = $slug !== '' ? $slug . '-' . substr(md5($name), 0, 6) : substr(md5($name), 0, 8);
                $ins->execute([':n' => $name, ':s' => $slug2]);
                $tag_id = (int)$pdo->lastInsertId();
            }
        }

        $pdo->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:p, :t)')
            ->execute([':p' => $post_id, ':t' => $tag_id]);
    }
}

// ===================================================
//  カスタムフィールド (post_meta)
//  記事ごとの任意 key-value メタデータ
// ===================================================

/**
 * 記事のカスタムフィールド値を取得。
 *
 * @param int    $post_id
 * @param string $key
 * @param bool   $single  true なら最初の1件（または null）、false なら配列
 * @return string|array|null
 */
function get_post_meta(int $post_id, string $key, bool $single = true)
{
    $stmt = db()->prepare(
        'SELECT `value` FROM post_meta WHERE post_id = :p AND `key` = :k ORDER BY id ASC'
    );
    $stmt->execute([':p' => $post_id, ':k' => $key]);
    $vals = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($single) {
        return $vals[0] ?? null;
    }
    return $vals;
}

/**
 * カスタムフィールドを保存。
 *
 * @param bool $unique  true: 同じ key は1件に置き換え。false: 追加で挿入。
 */
function set_post_meta(int $post_id, string $key, ?string $value, bool $unique = true): void
{
    $pdo = db();
    if ($unique) {
        $pdo->prepare('DELETE FROM post_meta WHERE post_id = :p AND `key` = :k')
            ->execute([':p' => $post_id, ':k' => $key]);
    }
    $pdo->prepare('INSERT INTO post_meta (post_id, `key`, `value`) VALUES (:p, :k, :v)')
        ->execute([':p' => $post_id, ':k' => $key, ':v' => $value]);
}

/**
 * 特定のキー（任意で値も）のカスタムフィールドを削除。
 */
function delete_post_meta(int $post_id, string $key, ?string $value = null): void
{
    if ($value === null) {
        db()->prepare('DELETE FROM post_meta WHERE post_id = :p AND `key` = :k')
            ->execute([':p' => $post_id, ':k' => $key]);
    } else {
        db()->prepare('DELETE FROM post_meta WHERE post_id = :p AND `key` = :k AND `value` = :v')
            ->execute([':p' => $post_id, ':k' => $key, ':v' => $value]);
    }
}

/**
 * 記事のカスタムフィールドを全て取得。
 * 同じ key が複数あれば配列、1件なら文字列が値となる連想配列を返す。
 */
function get_all_post_meta(int $post_id): array
{
    $stmt = db()->prepare(
        'SELECT `key`, `value` FROM post_meta WHERE post_id = :p ORDER BY id ASC'
    );
    $stmt->execute([':p' => $post_id]);

    $result = [];
    foreach ($stmt as $row) {
        $k = $row['key'];
        if (!isset($result[$k])) {
            $result[$k] = $row['value'];
        } elseif (is_array($result[$k])) {
            $result[$k][] = $row['value'];
        } else {
            $result[$k] = [$result[$k], $row['value']];
        }
    }
    return $result;
}

// プラグインを読み込む（テーブルが無い場合 get_setting が null を返すので安全）
load_plugins();

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
