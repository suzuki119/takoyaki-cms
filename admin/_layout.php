<?php
// ===================================================
//  管理画面共通レイアウト
//  使い方:
//    require_once __DIR__ . '/_layout.php';
//    admin_header('記事一覧');
//      // ページ固有のHTML...
//    admin_footer();
// ===================================================

/**
 * 管理画面ページのヘッダーを出力する。
 *
 * @param string $title       ページタイトル（<title>と<h1>に使う）
 * @param string $extra_head  追加で <head> に挿入する文字列（CKEditor の link/script 等）
 */
function admin_header(string $title, string $extra_head = ''): void
{
    $site_url    = defined('SITE_URL') ? SITE_URL : '';
    $role        = function_exists('user_role') ? user_role() : '';
    // 「サイトを表示」のリンク先（設定で上書き可能、未設定なら samples/index.php）
    $public_url  = function_exists('get_setting') ? (string)get_setting('public_site_url', '') : '';
    if ($public_url === '') {
        $public_url = $site_url . '/samples/index.php';
    }
?>
    <!DOCTYPE html>
    <html lang="ja">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> | Takoyaki CMS 管理画面</title>
        <link rel="stylesheet" href="<?= h($site_url) ?>/admin/admin.css">
        <?= $extra_head ?>
    </head>

    <body>
        <nav class="topnav">
            <div class="topnav-inner">
                <a class="topnav-brand" href="<?= h($site_url) ?>/admin/index.php">Takoyaki CMS</a>
                <!-- モバイル用ハンバーガー（CSSのみで開閉） -->
                <input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
                <label for="nav-toggle" class="nav-burger" aria-label="メニュー">
                    <span></span><span></span><span></span>
                </label>
                <ul class="topnav-links">
                    <li><a class="topnav-viewsite" href="<?= h($public_url) ?>" target="_blank" rel="noopener">サイトを表示 ↗</a></li>
                    <li><a href="<?= h($site_url) ?>/admin/index.php">記事</a></li>
                    <li><a href="<?= h($site_url) ?>/admin/tags.php">タグ</a></li>
                    <?php if ($role === 'admin'): ?>
                        <li><a href="<?= h($site_url) ?>/admin/media.php">メディア</a></li>
                        <li><a href="<?= h($site_url) ?>/admin/users.php">ユーザー</a></li>
                        <li><a href="<?= h($site_url) ?>/admin/settings.php">設定</a></li>
                        <li><a href="<?= h($site_url) ?>/admin/themes.php">テーマ</a></li>
                        <li><a href="<?= h($site_url) ?>/admin/plugins.php">プラグイン</a></li>
                        <li><a href="<?= h($site_url) ?>/admin/backup.php">バックアップ</a></li>
                    <?php endif; ?>
                    <li><a href="<?= h($site_url) ?>/admin/account.php">アカウント</a></li>
                    <li><a class="topnav-logout" href="<?= h($site_url) ?>/logout.php">ログアウト</a></li>
                </ul>
            </div>
        </nav>
        <main class="main">
        <?php
    }

    /**
     * 管理画面ページのフッターを出力する。
     *
     * @param string $extra_body  </body> の直前に追加する文字列（ページ固有のscript等）
     */
    function admin_footer(string $extra_body = ''): void
    {
        ?>
        </main>
        <?= $extra_body ?>
        <script>
            // 横長テーブルをスマホで横スクロールできるよう自動でラップする
            (function () {
                document.querySelectorAll('table.table').forEach(function (t) {
                    if (t.parentElement && t.parentElement.classList.contains('table-wrap')) return;
                    var w = document.createElement('div');
                    w.className = 'table-wrap';
                    t.parentNode.insertBefore(w, t);
                    w.appendChild(t);
                });
                // ハンバーガーで開いたメニューはリンクタップ時に自動で閉じる
                var t = document.getElementById('nav-toggle');
                if (t) document.querySelectorAll('.topnav-links a').forEach(function (a) {
                    a.addEventListener('click', function () { t.checked = false; });
                });
            })();
        </script>
    </body>

    </html>
<?php
    }
