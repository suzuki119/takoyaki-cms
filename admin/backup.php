<?php
// ===================================================
//  DBバックアップ（admin限定）
//  PHP製のSQLダンプを生成してブラウザにダウンロードさせる。
//  mysqldump 不要、共有サーバーでも動作する。
//
//  GET ?action=download で実ダウンロード、それ以外なら案内ページ。
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

if (($_GET['action'] ?? '') === 'download') {
    // ダウンロード前にCSRFトークンを確認（GETでもURL推測攻撃を防ぐ）
    $token = $_GET['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('CSRF検証に失敗しました。');
    }

    $pdo = db();

    $filename = 'takoyaki-cms-backup-' . date('Y-m-d-His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    echo "-- Takoyaki CMS DB バックアップ\n";
    echo "-- 生成日時: " . date('Y-m-d H:i:s') . "\n";
    echo "-- データベース: " . DB_NAME . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "-- ---------- $table ----------\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";

        // CREATE TABLE
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $create['Create Table'] . ";\n\n";

        // データ
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $columns = '`' . implode('`,`', array_keys($rows[0])) . '`';
            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote((string)$v);
                }, array_values($row));
                echo "INSERT INTO `$table` ($columns) VALUES (" . implode(',', $values) . ");\n";
            }
            echo "\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";

    log_action('backup.download', 'database', null, 'ファイル: ' . $filename);
    exit;
}

// バックアップ案内ページ
$pdo = db();

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

admin_header('DBバックアップ');
?>

<h1 class="page-title">DBバックアップ</h1>

<p class="page-meta">
    データベース全体のSQLダンプをダウンロードします。
</p>

<div class="card">
    <h2 class="section-title" style="margin-top:0;">テーブル一覧</h2>
    <table class="table">
        <thead>
            <tr>
                <th>テーブル名</th>
                <th style="width:120px;">レコード数</th>
            </tr>
        </thead>
        <tbody>
            <?php $total = 0; foreach ($counts as $t => $c): $total += $c; ?>
                <tr>
                    <td><code><?= h($t) ?></code></td>
                    <td><?= $c ?> 件</td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight:600;">
                <td>合計</td>
                <td><?= $total ?> 件</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card" style="text-align:center;">
    <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/backup.php?action=download&_csrf=<?= h(csrf_token()) ?>">
        SQLバックアップをダウンロード
    </a>
    <p style="font-size:.85rem; color:#6b7280; margin-top:12px;">
        生成されたSQLファイルは <code>mysql -u user -p dbname &lt; backup.sql</code> でリストアできます。
    </p>
</div>

<div class="alert alert-warning">
    <strong>注意：</strong>このバックアップは DB のみです。<code>uploads/</code> ディレクトリの画像ファイルは含まれません。
    完全なバックアップが必要な場合は、サーバーから <code>uploads/</code> を別途取得してください。
</div>

<?php admin_footer(); ?>
