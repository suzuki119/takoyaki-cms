<?php
// ===================================================
//  メディアライブラリ（admin限定）
//  uploads/ ディレクトリ内の画像を一覧・削除する。
// ===================================================
require_once '../config.php';
require_admin();

$pdo   = db();
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    verify_csrf();

    if ($_POST['action'] === 'delete' && !empty($_POST['filename'])) {
        $filename = basename((string)$_POST['filename']); // パス区切り除去（ディレクトリトラバーサル防止）
        $path     = UPLOAD_DIR . $filename;

        // uploads/ 配下のファイルだけ削除可能（実体パス検証）
        $real_uploads = realpath(UPLOAD_DIR);
        $real_target  = realpath($path);

        if (!$real_target || strpos($real_target, $real_uploads) !== 0) {
            $error = 'ファイルが見つかりません。';
        } else {
            @unlink($path);
            @unlink(UPLOAD_DIR . thumb_filename($filename));
            $info = h($filename) . ' を削除しました。';
        }
    }
}

// アップロード済みファイルを取得（-thumb 変種を除外）
$files = [];
foreach (scandir(UPLOAD_DIR) as $f) {
    if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
    if (preg_match('/-thumb\.[^.]+$/', $f)) continue;       // サムネイル変種を除外
    if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) continue;

    $path = UPLOAD_DIR . $f;
    $size = @filesize($path);
    $dim  = @getimagesize($path);

    // 使用状況（posts.thumbnail と一致するもの）
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

// 新しい順
usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

function format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) return sprintf('%.1f MB', $bytes / 1024 / 1024);
    if ($bytes >= 1024)        return sprintf('%.1f KB', $bytes / 1024);
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>メディアライブラリ | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        .info  { margin-bottom: 16px; padding: 10px; background: #eafaf1; border-left: 4px solid #27ae60; font-size: .9rem; }
        .error { margin-bottom: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .card { border: 1px solid #ddd; border-radius: 6px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
        .card .thumb { background: #f5f5f5; aspect-ratio: 4 / 3; display: flex; align-items: center; justify-content: center; }
        .card .thumb img { max-width: 100%; max-height: 100%; }
        .card .meta { padding: 10px 12px; font-size: .8rem; color: #555; flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .card .filename { font-weight: bold; color: #333; word-break: break-all; }
        .card .usage { color: #888; }
        .card .usage.used { color: #2980b9; }
        .card .actions { padding: 8px 12px; border-top: 1px solid #eee; background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
        .card .actions a { font-size: .8rem; color: #555; text-decoration: none; }
        .card .actions button { background: none; border: none; color: #c0392b; cursor: pointer; font-size: .8rem; padding: 0; }
        .empty { padding: 40px; text-align: center; color: #999; border: 1px dashed #ddd; border-radius: 6px; }
        .back { margin-top: 32px; display: block; font-size: .85rem; color: #666; }
    </style>
</head>
<body>
    <h1>メディアライブラリ</h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($info !== ''): ?>
        <div class="info"><?= $info ?></div>
    <?php endif; ?>

    <?php if (empty($files)): ?>
        <p class="empty">アップロードされた画像がまだありません。</p>
    <?php else: ?>
        <p style="font-size:.85rem; color:#666; margin-bottom:16px;">
            計 <?= count($files) ?> 件。記事に使われている画像は削除できますが、その記事のサムネイル参照が壊れます。
        </p>
        <div class="grid">
            <?php foreach ($files as $f):
                $thumb = thumb_filename($f['name']);
                $thumb_url = file_exists(UPLOAD_DIR . $thumb)
                    ? UPLOAD_URL . $thumb
                    : UPLOAD_URL . $f['name'];
            ?>
            <div class="card">
                <div class="thumb">
                    <a href="<?= UPLOAD_URL . h($f['name']) ?>" target="_blank">
                        <img src="<?= h($thumb_url) ?>" alt="">
                    </a>
                </div>
                <div class="meta">
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
                <div class="actions">
                    <a href="<?= UPLOAD_URL . h($f['name']) ?>" target="_blank">原寸を開く</a>
                    <form method="post" onsubmit="return confirm('「<?= h($f['name']) ?>」を削除しますか？\nサムネイル変種も一緒に削除されます。');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="filename" value="<?= h($f['name']) ?>">
                        <button type="submit">削除</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a class="back" href="<?= SITE_URL ?>/admin/index.php">← 記事一覧へ戻る</a>
</body>
</html>
