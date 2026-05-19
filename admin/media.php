<?php
// ===================================================
//  メディアライブラリ（admin限定）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo   = db();
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    if ($_POST['action'] === 'delete' && !empty($_POST['filename'])) {
        $filename = basename((string)$_POST['filename']);
        $path     = UPLOAD_DIR . $filename;

        $real_uploads = realpath(UPLOAD_DIR);
        $real_target  = realpath($path);

        if (!$real_target || strpos($real_target, $real_uploads) !== 0) {
            $error = 'ファイルが見つかりません。';
        } else {
            @unlink($path);
            @unlink(UPLOAD_DIR . thumb_filename($filename));
            log_action('media.delete', 'media', null, "ファイル: {$filename}");
            $info = h($filename) . ' を削除しました。';
        }
    }
}

$files = [];
foreach (scandir(UPLOAD_DIR) as $f) {
    if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
    if (preg_match('/-thumb\.[^.]+$/', $f)) continue;
    if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) continue;

    $path = UPLOAD_DIR . $f;
    $size = @filesize($path);
    $dim  = @getimagesize($path);

    $usage_stmt = $pdo->prepare('SELECT id, title FROM posts WHERE thumbnail = :f');
    $usage_stmt->execute([':f' => $f]);
    $usage = $usage_stmt->fetchAll();

    $files[] = [
        'name'  => $f,
        'size'  => $size,
        'w'     => $dim[0] ?? null,
        'h'     => $dim[1] ?? null,
        'mtime' => @filemtime($path),
        'usage' => $usage,
    ];
}

usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

function format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) return sprintf('%.1f MB', $bytes / 1024 / 1024);
    if ($bytes >= 1024)        return sprintf('%.1f KB', $bytes / 1024);
    return $bytes . ' B';
}

admin_header('メディアライブラリ');
?>

<h1 class="page-title">メディアライブラリ</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($info !== ''): ?>
    <div class="alert alert-info"><?= $info ?></div>
<?php endif; ?>

<?php if (empty($files)): ?>
    <p class="empty-state">アップロードされた画像がまだありません。</p>
<?php else: ?>
    <p style="font-size:.9rem; color:#6b7280; margin-bottom:20px;">
        計 <?= count($files) ?> 件。記事に使われている画像も削除できますが、その記事のサムネイル参照が壊れます。
    </p>
    <div class="media-grid">
        <?php foreach ($files as $f):
            $thumb = thumb_filename($f['name']);
            $thumb_url = file_exists(UPLOAD_DIR . $thumb)
                ? UPLOAD_URL . $thumb
                : UPLOAD_URL . $f['name'];
        ?>
        <div class="media-card">
            <div class="media-thumb">
                <a href="<?= UPLOAD_URL . h($f['name']) ?>" target="_blank">
                    <img src="<?= h($thumb_url) ?>" alt="">
                </a>
            </div>
            <div class="media-meta">
                <div class="filename"><?= h($f['name']) ?></div>
                <div>
                    <?php if ($f['w'] && $f['h']): ?>
                        <?= h($f['w']) ?>×<?= h($f['h']) ?>px
                    <?php endif; ?>
                    ／ <?= h(format_bytes((int)$f['size'])) ?>
                </div>
                <div class="usage <?= !empty($f['usage']) ? 'used' : '' ?>">
                    <?php if (!empty($f['usage'])): ?>
                        使用中: <?= count($f['usage']) ?> 件の記事
                    <?php else: ?>
                        未使用
                    <?php endif; ?>
                </div>
            </div>
            <div class="media-card-actions">
                <a href="<?= UPLOAD_URL . h($f['name']) ?>" target="_blank">原寸を開く</a>
                <form method="post" onsubmit="return confirm('「<?= h($f['name']) ?>」を削除しますか？\nサムネイル変種も一緒に削除されます。');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="filename" value="<?= h($f['name']) ?>">
                    <button type="submit" class="btn-link" style="color:#dc2626;border:none;background:none;cursor:pointer;font-size:.82rem;padding:0;">削除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php admin_footer(); ?>
