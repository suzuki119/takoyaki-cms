<?php
// ===================================================
//  メディアライブラリ
//  uploads/ の画像を一覧し、どこで使われているかを表示する。
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    // ---- 1件削除 ----
    if ($_POST['action'] === 'delete' && !empty($_POST['filename'])) {
        $filename = basename((string)$_POST['filename']);

        if (!file_exists(UPLOAD_DIR . $filename)) {
            $error = 'ファイルが見つかりません。';
        } else {
            delete_upload($filename);
            $info = $filename . ' を削除しました。';
        }
    }

    // ---- 未使用をまとめて削除 ----
    if ($_POST['action'] === 'purge_unused') {
        $deleted = 0;
        foreach (($_POST['unused'] ?? []) as $f) {
            $filename = basename((string)$f);
            if (file_exists(UPLOAD_DIR . $filename)) {
                delete_upload($filename);
                $deleted++;
            }
        }
        $info = "未使用の画像を {$deleted} 件削除しました。";
    }
}

// ---------------------------------------------------
//  使用状況をまとめて引く（画像ごとにクエリを投げない）
// ---------------------------------------------------
$usage = [];   // filename => [['type' => '作品', 'id' => 1, 'title' => '...'], ...]

foreach ($pdo->query('SELECT id, title, thumbnail FROM posts WHERE thumbnail IS NOT NULL') as $row) {
    $usage[$row['thumbnail']][] = ['type' => '作品', 'id' => (int)$row['id'], 'title' => $row['title']];
}
foreach ($pdo->query('SELECT id, title, image FROM skills WHERE image IS NOT NULL') as $row) {
    $usage[$row['image']][] = ['type' => 'スキル', 'id' => (int)$row['id'], 'title' => $row['title']];
}

// 本文セクション内で参照されている画像も「使用中」として扱う
$section_bodies = $pdo->query('SELECT body FROM post_sections WHERE body IS NOT NULL')
                      ->fetchAll(PDO::FETCH_COLUMN);
$body_blob = implode("\n", $section_bodies);

// ---------------------------------------------------
//  ファイル一覧
// ---------------------------------------------------
$files = [];
foreach (scandir(UPLOAD_DIR) as $f) {
    if ($f === '.' || $f === '..' || $f === '.gitkeep' || $f === '.htaccess') continue;
    if (preg_match('/-thumb\.[^.]+$/', $f)) continue;
    if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) continue;

    $path = UPLOAD_DIR . $f;
    $dim  = @getimagesize($path);

    $used = $usage[$f] ?? [];
    if (empty($used) && $body_blob !== '' && strpos($body_blob, $f) !== false) {
        $used[] = ['type' => '本文', 'id' => 0, 'title' => '作品の本文セクション'];
    }

    $files[] = [
        'name'  => $f,
        'size'  => (int)@filesize($path),
        'w'     => $dim[0] ?? null,
        'h'     => $dim[1] ?? null,
        'mtime' => (int)@filemtime($path),
        'usage' => $used,
    ];
}

usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

$unused = array_values(array_filter($files, fn($f) => empty($f['usage'])));

function format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) return sprintf('%.1f MB', $bytes / 1024 / 1024);
    if ($bytes >= 1024)        return sprintf('%.1f KB', $bytes / 1024);
    return $bytes . ' B';
}

admin_header('メディアライブラリ');
?>

<h1 class="page-title">メディア</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($info !== ''): ?>
    <div class="alert alert-info"><?= h($info) ?></div>
<?php endif; ?>

<?php if (empty($files)): ?>
    <p class="empty-state">アップロードされた画像がまだありません。</p>
<?php else: ?>
    <div class="page-header">
        <p class="page-meta" style="margin:0;">
            計 <?= count($files) ?> 件（うち未使用 <?= count($unused) ?> 件）。
            使用中の画像を削除すると、その参照が壊れます。
        </p>
        <?php if (!empty($unused)): ?>
            <form method="post"
                  onsubmit="return confirm('未使用の <?= count($unused) ?> 件を削除しますか？\n元に戻せません。');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="purge_unused">
                <?php foreach ($unused as $f): ?>
                    <input type="hidden" name="unused[]" value="<?= h($f['name']) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-secondary btn-sm">
                    未使用 <?= count($unused) ?> 件をまとめて削除
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="media-grid">
        <?php foreach ($files as $f): ?>
        <div class="media-card">
            <div class="media-thumb">
                <a href="<?= h(upload_url($f['name'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= h(post_thumb_url($f['name'])) ?>" alt="">
                </a>
            </div>
            <div class="media-meta">
                <div class="filename"><?= h($f['name']) ?></div>
                <div>
                    <?php if ($f['w'] && $f['h']): ?><?= h($f['w']) ?>×<?= h($f['h']) ?>px ／ <?php endif; ?>
                    <?= h(format_bytes($f['size'])) ?>
                </div>
                <div class="usage <?= !empty($f['usage']) ? 'used' : '' ?>">
                    <?php if (!empty($f['usage'])): ?>
                        <?php foreach ($f['usage'] as $u): ?>
                            <div><?= h($u['type']) ?>: <?= h($u['title']) ?></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        未使用
                    <?php endif; ?>
                </div>
            </div>
            <div class="media-card-actions">
                <a href="<?= h(upload_url($f['name'])) ?>" target="_blank" rel="noopener">原寸を開く</a>
                <form method="post"
                      onsubmit="return confirm('「<?= h($f['name']) ?>」を削除しますか？\nサムネイル変種も一緒に削除されます。');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="filename" value="<?= h($f['name']) ?>">
                    <button type="submit" class="btn-link btn-danger">削除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php admin_footer(); ?>
