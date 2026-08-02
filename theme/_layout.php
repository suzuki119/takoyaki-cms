<?php
// ===================================================
//  公開ページ（テーマ）の共通レイアウト
//
//  このファイルは「そのまま動くお手本」です。
//  自分のサイトに組み込むときは、ここを既存サイトの
//  header.php / footer.php に置き換えてください。
// ===================================================

/**
 * <head> と共通ヘッダーを出力する。
 *
 * @param string $title       ページタイトル（サイト名は自動で付く）
 * @param string $description <meta name="description">
 */
function site_head(string $title = '', string $description = ''): void
{
    $site_name = (string)get_setting('site_name', 'My Portfolio');
    $desc      = $description !== '' ? $description : (string)get_setting('site_description', '');
    $full      = $title !== '' ? $title . ' | ' . $site_name : $site_name;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($full) ?></title>
    <?php if ($desc !== ''): ?>
        <meta name="description" content="<?= h($desc) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= h(THEME_URL) ?>/style.css">
</head>
<body>

<header class="site-header">
    <div class="container site-header-inner">
        <a class="site-brand" href="<?= h(SITE_URL) ?>/"><?= h($site_name) ?></a>
        <nav class="site-nav">
            <a href="<?= h(SITE_URL) ?>/">Works</a>
            <a href="<?= h(SITE_URL) ?>/skill.php">Skills</a>
        </nav>
    </div>
</header>

<main class="container">
<?php
}

/**
 * 共通フッターを出力する。
 */
function site_foot(): void
{
    $footer_text = (string)get_setting('footer_text', '');
    $site_name   = (string)get_setting('site_name', 'My Portfolio');
?>
</main>

<footer class="site-footer">
    <div class="container">
        <?php if ($footer_text !== ''): ?>
            <p><?= h($footer_text) ?></p>
        <?php endif; ?>
        <p class="copyright">&copy; <?= date('Y') ?> <?= h($site_name) ?></p>
    </div>
</footer>

</body>
</html>
<?php
}
