<?php
// ===================================================
//  Takoyaki CMS v2.0.0 共通ヘルパー
//
//  DB接続・共通関数・各データ操作をまとめたファイル。
//  設定値は config.php 側にあるので、このファイルは
//  環境ごとに編集する必要はありません（更新時は上書きでOK）。
//
//  通常は config.php から自動で読み込まれます。
//  単体で読み込んだ場合も、下で config.php を読みにいきます。
// ===================================================

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

// ===================================================
//  DB接続
// ===================================================

/**
 * PDO接続を返す（1リクエスト内では使い回す）
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
            // 接続情報が漏れないよう、詳細はログにだけ出す
            error_log('[Takoyaki CMS] DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('データベースに接続できませんでした。設定を確認してください。');
        }

        // MySQL のセッションタイムゾーンを PHP 側に合わせる。
        // NOW() と PHP の time() の判定結果を一致させるために必要。
        $offset = (new DateTime('now', new DateTimeZone(CMS_TIMEZONE)))->format('P');
        $pdo->exec("SET time_zone = '{$offset}'");
    }

    return $pdo;
}


// ===================================================
//  共通ヘルパー
// ===================================================

/**
 * XSS対策：HTML特殊文字をエスケープして出力する
 * 使い方： echo h($変数);
 *
 * null や数値も受け付ける（PHP 8.1 の "Passing null" 警告を避けるため）
 */
function h($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * セッションを安全な設定で開始する。
 * Cookieは httponly / samesite=Lax、HTTPS時は secure 付き。
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
 * ログイン済みかチェック。未ログインならログイン画面へ飛ばす。
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
 * 文字列をURL用 slug に変換する（英数字とハイフンのみ）。
 * 日本語のみの文字列は空文字になるので、呼び出し側は unique_slug() に渡すこと。
 */
function sluggify(string $text, int $maxLen = 60): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    $slug = trim((string)$slug, '-');
    $slug = strtolower($slug);
    return substr($slug, 0, $maxLen);
}

/**
 * 重複しない slug を作る。
 *  - 空文字（日本語タイトル等）なら $fallback を使う
 *  - 既に使われていたら -2, -3 … を付けて回避する
 *
 * @param string   $base       sluggify() 済みの文字列
 * @param string   $table      'posts' / 'categories' / 'tags'
 * @param int|null $exclude_id 更新時に自分自身を除外するためのID
 * @param string   $fallback   $base が空のときに使う代替名
 */
function unique_slug(string $base, string $table, ?int $exclude_id = null, string $fallback = 'item'): string
{
    $allowed = ['posts', 'categories', 'tags'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('unknown table: ' . $table);
    }

    // 空、または数字だけの slug は使わない。
    // get_post() / get_category() は is_numeric() で「IDか slug か」を判定するため、
    // 数字だけの slug を許すと ID 検索と衝突して別のレコードが引かれてしまう。
    if ($base === '' || ctype_digit($base)) {
        $base = $fallback;
    }

    $sql    = "SELECT COUNT(*) FROM `$table` WHERE slug = :slug";
    $params = [':slug' => ''];
    if ($exclude_id !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $exclude_id;
    }
    $stmt = db()->prepare($sql);

    $candidate = $base;
    for ($i = 2; $i < 100; $i++) {
        $params[':slug'] = $candidate;
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $i;
    }

    // 100件も同名が並ぶことは実質ないが、念のための最終手段
    return $base . '-' . bin2hex(random_bytes(3));
}

/**
 * datetime-local の値（例 2026-07-28T14:30）を検証して 'Y-m-d H:i:s' に変換する。
 * 形式が不正なら null を返す。
 */
function parse_datetime_local(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    // 秒あり・秒なしの両方を許容する
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
        $dt = DateTime::createFromFormat($format, $input);
        if ($dt && $dt->format($format) === $input) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    return null;
}


// ===================================================
//  画像アップロード
// ===================================================

/**
 * アップロードされた画像を検証して uploads/ に保存する。
 * 作品サムネイル・スキルアイコン・本文内画像で共通して使う。
 *
 * @param array $file       $_FILES の1要素
 * @param bool  $make_thumb -thumb 変種も生成するか
 * @return array ['filename' => string|null, 'error' => string|null]
 */
function handle_image_upload(array $file, bool $make_thumb = true): array
{
    $fail = fn(string $msg): array => ['filename' => null, 'error' => $msg];

    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return $fail('画像サイズが大きすぎます。');
        }
        return $fail('アップロードに失敗しました。');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('不正なアップロードです。');
    }

    $allowed_ext   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $ext         = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $actual_mime = @mime_content_type($file['tmp_name']);

    if (!in_array($ext, $allowed_ext, true) || !in_array($actual_mime, $allowed_mimes, true)) {
        return $fail('画像は jpg / png / gif / webp のみ使用できます。');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return $fail('画像サイズは ' . (int)(MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB 以下にしてください。');
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $savePath = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $savePath)) {
        return $fail('画像の保存に失敗しました。');
    }

    resize_image($savePath, IMAGE_MAX_WIDTH, $savePath);
    if ($make_thumb) {
        resize_image($savePath, IMAGE_THUMB_WIDTH, UPLOAD_DIR . thumb_filename($filename));
    }

    return ['filename' => $filename, 'error' => null];
}

/**
 * GD で画像をリサイズして保存する。
 * 元画像が $maxWidth 以下なら、$sourcePath != $destPath の場合のみコピーする。
 *
 * @param string $sourcePath 元画像のパス
 * @param int    $maxWidth   最大幅（高さは比率維持）
 * @param string $destPath   保存先パス（拡張子で出力フォーマットが決まる）
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

/**
 * uploads 内の画像とそのサムネイル変種を削除する。
 * uploads の外を指すファイル名は無視する。
 */
function delete_upload(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $filename = basename($filename);
    $real_dir = realpath(UPLOAD_DIR);
    if (!$real_dir) {
        return;
    }
    foreach ([$filename, thumb_filename($filename)] as $f) {
        $path = realpath(UPLOAD_DIR . $f);
        if ($path && strpos($path, $real_dir) === 0) {
            @unlink($path);
        }
    }
}

/**
 * サムネイルURL（-thumb 変種があればそれ、なければ元画像）
 */
function post_thumb_url(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }
    $thumb = thumb_filename($filename);
    return file_exists(UPLOAD_DIR . $thumb) ? UPLOAD_URL . $thumb : UPLOAD_URL . $filename;
}

/**
 * uploads 内の画像URL（原寸）
 */
function upload_url(?string $filename): ?string
{
    return $filename ? UPLOAD_URL . $filename : null;
}


// ===================================================
//  作品（Works）
// ===================================================

/**
 * 公開中の作品を取得する。
 *
 * @param array $opts {
 *     limit          (int)    最大件数（省略時は全件）
 *     offset         (int)    オフセット
 *     category_id    (int)    カテゴリで絞り込み
 *     tag_id         (int)    タグで絞り込み
 *     order_by       (string) 並び順カラム（既定 'sort_order'）
 *     order          (string) 'ASC' or 'DESC'（既定 'ASC'）
 *     include_drafts (bool)   下書き・予約公開も含めるか（既定 false）
 * }
 */
function get_posts(array $opts = []): array
{
    $opts += [
        'limit'          => null,
        'offset'         => 0,
        'category_id'    => null,
        'tag_id'         => null,
        'order_by'       => 'sort_order',
        'order'          => 'ASC',
        'include_drafts' => false,
    ];

    $allowed_columns = ['sort_order', 'created_at', 'published_at', 'updated_at', 'id', 'title'];
    $order_by        = in_array($opts['order_by'], $allowed_columns, true) ? $opts['order_by'] : 'sort_order';
    $order           = strtoupper((string)$opts['order']) === 'DESC' ? 'DESC' : 'ASC';

    $where  = [];
    $params = [];
    $join   = '';

    if (!$opts['include_drafts']) {
        $where[] = "p.status = 'published'";
        $where[] = '(p.published_at IS NULL OR p.published_at <= NOW())';
    }

    if (!empty($opts['category_id'])) {
        $join                  .= ' INNER JOIN post_categories pc ON pc.post_id = p.id';
        $where[]                = 'pc.category_id = :category_id';
        $params[':category_id'] = (int)$opts['category_id'];
    }

    if (!empty($opts['tag_id'])) {
        $join             .= ' INNER JOIN post_tags pt ON pt.post_id = p.id';
        $where[]           = 'pt.tag_id = :tag_id';
        $params[':tag_id'] = (int)$opts['tag_id'];
    }

    $sql = "SELECT DISTINCT p.* FROM posts p$join";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY p.$order_by $order, p.id ASC";

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
 * 1件の作品を ID または slug で取得する。
 */
function get_post($id_or_slug, bool $include_drafts = false): ?array
{
    $col = is_numeric($id_or_slug) ? 'id' : 'slug';
    $val = is_numeric($id_or_slug) ? (int)$id_or_slug : (string)$id_or_slug;

    $sql = "SELECT * FROM posts WHERE $col = :v";
    if (!$include_drafts) {
        $sql .= " AND status = 'published' AND (published_at IS NULL OR published_at <= NOW())";
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([':v' => $val]);

    return $stmt->fetch() ?: null;
}

/**
 * 作品の本文セクションを表示順に取得する。
 */
function get_post_sections(int $post_id): array
{
    $stmt = db()->prepare(
        'SELECT * FROM post_sections WHERE post_id = :id ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':id' => $post_id]);
    return $stmt->fetchAll();
}

/**
 * 作品の本文セクションを丸ごと入れ替える。
 * 見出しも本文も空の行は保存しない。
 *
 * @param array $sections [['title' => '...', 'body' => '...'], ...]
 */
function set_post_sections(int $post_id, array $sections): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM post_sections WHERE post_id = :id')->execute([':id' => $post_id]);

    $ins   = $pdo->prepare(
        'INSERT INTO post_sections (post_id, sort_order, title, body) VALUES (:p, :o, :t, :b)'
    );
    $order = 0;
    foreach ($sections as $s) {
        $title = trim((string)($s['title'] ?? ''));
        $body  = (string)($s['body'] ?? '');
        if ($title === '' && trim(strip_tags($body)) === '') {
            continue;
        }
        $ins->execute([
            ':p' => $post_id,
            ':o' => $order++,
            ':t' => $title !== '' ? $title : null,
            ':b' => $body !== '' ? $body : null,
        ]);
    }
}

/**
 * 作品が今この瞬間「公開中」かを判定する。
 * config.php で PHP と MySQL のタイムゾーンをそろえてあるため、
 * この判定は SQL 側（get_posts / get_post）の結果と必ず一致する。
 */
function is_post_live(array $post): bool
{
    if (($post['status'] ?? '') !== 'published') {
        return false;
    }
    $pub = $post['published_at'] ?? null;
    if ($pub === null || $pub === '') {
        return true;
    }
    return strtotime((string)$pub) <= time();
}

/**
 * 作品の「公開ページURL」を組み立てる。
 *  - site_settings の public_article_url_pattern があれば {slug} / {id} を置換
 *  - 未設定なら single.php?slug=... もしくは ?id=...
 */
function public_post_url(array $post): string
{
    $pattern = (string)get_setting('public_article_url_pattern', '');
    if ($pattern === '') {
        if (!empty($post['slug'])) {
            return SITE_URL . '/single.php?slug=' . urlencode((string)$post['slug']);
        }
        return SITE_URL . '/single.php?id=' . (int)$post['id'];
    }
    return strtr($pattern, [
        '{slug}' => urlencode((string)($post['slug'] ?? '')),
        '{id}'   => (string)(int)$post['id'],
    ]);
}

/**
 * YouTube / Vimeo のURLを iframe 埋め込み用URLに変換する。
 * 対応外・空のときは null を返す。
 *
 * 対応: youtube.com/watch?v= / youtu.be/ / youtube.com/shorts/ /
 *       youtube.com/embed/ / vimeo.com/{id} / player.vimeo.com/video/{id}
 */
function video_embed_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') {
        return null;
    }

    if (preg_match('~(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }

    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }

    return null;
}


// ===================================================
//  カテゴリ
// ===================================================

function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll();
}

function get_category($id_or_slug): ?array
{
    $col = is_numeric($id_or_slug) ? 'id' : 'slug';
    $val = is_numeric($id_or_slug) ? (int)$id_or_slug : (string)$id_or_slug;

    $stmt = db()->prepare("SELECT * FROM categories WHERE $col = :v LIMIT 1");
    $stmt->execute([':v' => $val]);
    return $stmt->fetch() ?: null;
}

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
 * 作品に紐付くカテゴリを丸ごと入れ替える（複数選択に対応）。
 *
 * @param array $category_ids カテゴリIDの配列
 */
function set_post_categories(int $post_id, array $category_ids): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM post_categories WHERE post_id = :id')->execute([':id' => $post_id]);

    $ins  = $pdo->prepare('INSERT IGNORE INTO post_categories (post_id, category_id) VALUES (:p, :c)');
    $seen = [];
    foreach ($category_ids as $cid) {
        $cid = (int)$cid;
        if ($cid <= 0 || isset($seen[$cid])) {
            continue;
        }
        $seen[$cid] = true;
        $ins->execute([':p' => $post_id, ':c' => $cid]);
    }
}


// ===================================================
//  タグ（使用技術）
// ===================================================

function get_tags(): array
{
    return db()->query('SELECT * FROM tags ORDER BY id ASC')->fetchAll();
}

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
 * 作品のタグを丸ごと入れ替える。未登録のタグ名は自動作成する。
 *
 * @param array|string $tags カンマ区切り文字列 or 配列
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
    $pdo->prepare('DELETE FROM post_tags WHERE post_id = :id')->execute([':id' => $post_id]);

    $find = $pdo->prepare('SELECT id FROM tags WHERE name = :name LIMIT 1');
    $ins  = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (:n, :s)');
    $link = $pdo->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:p, :t)');

    foreach ($tags as $name) {
        $name = trim((string)$name);
        if ($name === '') {
            continue;
        }

        $find->execute([':name' => $name]);
        $row = $find->fetch();

        if ($row) {
            $tag_id = (int)$row['id'];
        } else {
            $slug = unique_slug(sluggify($name), 'tags', null, 'tag');
            $ins->execute([':n' => $name, ':s' => $slug]);
            $tag_id = (int)$pdo->lastInsertId();
        }

        $link->execute([':p' => $post_id, ':t' => $tag_id]);
    }
}


// ===================================================
//  スキル（Skills）
// ===================================================

/**
 * スキルを表示順に取得する。
 * 並びは SKILL_CATEGORIES の順 → sort_order → id。
 */
function get_skills(): array
{
    $skills = db()->query('SELECT * FROM skills ORDER BY sort_order ASC, id ASC')->fetchAll();

    $rank = array_flip(SKILL_CATEGORIES);
    usort($skills, function ($a, $b) use ($rank) {
        $ra = $rank[$a['category']] ?? PHP_INT_MAX;
        $rb = $rank[$b['category']] ?? PHP_INT_MAX;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        if ((int)$a['sort_order'] !== (int)$b['sort_order']) {
            return (int)$a['sort_order'] <=> (int)$b['sort_order'];
        }
        return (int)$a['id'] <=> (int)$b['id'];
    });

    return $skills;
}

/**
 * スキルをカテゴリごとにまとめて返す。
 * 戻り値は ['プログラミング' => [...], 'デザイン' => [...]] の形。
 * 1件も無いカテゴリはキー自体が現れない。
 */
function get_skills_grouped(): array
{
    $grouped = [];
    foreach (get_skills() as $skill) {
        $grouped[$skill['category']][] = $skill;
    }
    return $grouped;
}

function get_skill(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM skills WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}


// ===================================================
//  サイト設定
// ===================================================

/**
 * 設定値のキャッシュ本体。get_setting / set_setting の両方から参照する。
 * （static をこの関数に閉じ込め、参照で受け渡す）
 */
function &_settings_cache(): ?array
{
    static $cache = null;
    return $cache;
}

function get_setting(string $key, ?string $default = null): ?string
{
    $cache = &_settings_cache();

    if ($cache === null) {
        $loaded = [];
        try {
            $stmt = db()->query('SELECT `key`, `value` FROM site_settings');
            foreach ($stmt as $row) {
                $loaded[$row['key']] = $row['value'];
            }
        } catch (PDOException $e) {
            return $default;
        }
        $cache = $loaded;
    }

    return $cache[$key] ?? $default;
}

function set_setting(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO site_settings (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = :v2'
    );
    $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);

    // キャッシュも同時に更新する（保存直後に古い値が読まれるのを防ぐ）
    $cache = &_settings_cache();
    if ($cache !== null) {
        $cache[$key] = $value;
    }
}


// ===================================================
//  CSRF対策
// ===================================================

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $expected = csrf_token();
    $given    = $_POST['_csrf'] ?? '';
    if (!is_string($given) || !hash_equals($expected, $given)) {
        http_response_code(403);
        exit('CSRF検証に失敗しました。フォームを再読み込みしてください。');
    }
}
