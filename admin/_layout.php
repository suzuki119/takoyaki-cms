<?php
// ===================================================
//  管理画面 共通レイアウト
//  使い方:
//    require_once __DIR__ . '/_layout.php';
//    admin_header('作品一覧');
//      // ページ固有のHTML...
//    admin_footer();
// ===================================================

/**
 * 管理画面ページのヘッダーを出力する。
 *
 * @param string $title      ページタイトル（<title> に使う）
 * @param string $extra_head 追加で <head> に挿入する文字列（CKEditor の link/script 等）
 */
function admin_header(string $title, string $extra_head = ''): void
{
    $site_url = defined('SITE_URL') ? SITE_URL : '';

    // 「サイトを表示」のリンク先（設定で上書き可能、未設定なら samples/works.php）
    $public_url = function_exists('get_setting') ? (string)get_setting('public_site_url', '') : '';
    if ($public_url === '') {
        $public_url = $site_url . '/samples/works.php';
    }

    // 現在のページをナビでハイライトするための判定材料
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');

    $nav = [
        ['label' => '作品',       'file' => 'index.php',      'match' => ['index.php', 'post-new.php', 'post-edit.php']],
        ['label' => 'カテゴリ',   'file' => 'categories.php', 'match' => ['categories.php']],
        ['label' => 'タグ',       'file' => 'tags.php',       'match' => ['tags.php']],
        ['label' => 'スキル',     'file' => 'skill.php',      'match' => ['skill.php', 'skill-edit.php']],
        ['label' => 'メディア',   'file' => 'media.php',      'match' => ['media.php']],
        ['label' => '設定',       'file' => 'settings.php',   'match' => ['settings.php']],
        ['label' => 'アカウント', 'file' => 'account.php',    'match' => ['account.php']],
    ];
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
                <li>
                    <a class="topnav-viewsite" href="<?= h($public_url) ?>" target="_blank" rel="noopener">
                        サイトを表示 ↗
                    </a>
                </li>
                <?php foreach ($nav as $item): ?>
                    <li>
                        <a href="<?= h($site_url) ?>/admin/<?= h($item['file']) ?>"
                           <?= in_array($current, $item['match'], true) ? 'class="is-current"' : '' ?>>
                            <?= h($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
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
 * @param string $extra_body </body> の直前に追加する文字列（ページ固有のscript等）
 */
function admin_footer(string $extra_body = ''): void
{
?>
    </main>
    <?= $extra_body ?>
    <script>
        (function () {
            // 横長テーブルをスマホで横スクロールできるよう自動でラップする
            document.querySelectorAll('table.table').forEach(function (t) {
                if (t.parentElement && t.parentElement.classList.contains('table-wrap')) return;
                var w = document.createElement('div');
                w.className = 'table-wrap';
                t.parentNode.insertBefore(w, t);
                w.appendChild(t);
            });
            // ハンバーガーで開いたメニューはリンクタップ時に自動で閉じる
            var toggle = document.getElementById('nav-toggle');
            if (toggle) {
                document.querySelectorAll('.topnav-links a').forEach(function (a) {
                    a.addEventListener('click', function () { toggle.checked = false; });
                });
            }
        })();
    </script>
</body>

</html>
<?php
}
