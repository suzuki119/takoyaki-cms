<?php
// ===================================================
//  管理画面トップ（作品一覧）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

const POSTS_PER_PAGE = 20;

$pdo      = db();
$redirect = SITE_URL . '/admin/index.php';

/**
 * sort_order を 1..N に振り直す。
 * 同値が混ざって並び替えが効かなくなるのを防ぐための保険。
 */
function resequence_posts(PDO $pdo): void
{
    $ids = $pdo->query('SELECT id FROM posts ORDER BY sort_order ASC, id ASC')
               ->fetchAll(PDO::FETCH_COLUMN);
    $upd = $pdo->prepare('UPDATE posts SET sort_order = :o WHERE id = :id');
    foreach ($ids as $i => $id) {
        $upd->execute([':o' => $i + 1, ':id' => $id]);
    }
}

/**
 * 作品を1件削除する（サムネイル画像も一緒に消す）。
 * post_sections / post_categories / post_tags は外部キーの CASCADE で消える。
 */
function delete_post(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT thumbnail FROM posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    $pdo->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $id]);

    if ($row && !empty($row['thumbnail'])) {
        delete_upload($row['thumbnail']);
    }
}

// ===================================================
//  削除（単体 / 一括）
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    verify_csrf();
    delete_post($pdo, (int)$_POST['delete_id']);
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['bulk_action'] ?? '') === 'delete') {
    verify_csrf();
    $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
    foreach (array_filter(array_map('intval', $ids)) as $id) {
        delete_post($pdo, $id);
    }
    header('Location: ' . $redirect);
    exit;
}

// ===================================================
//  並び替え（↑↓）
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['move_id'])) {
    verify_csrf();
    $move_id = (int)$_POST['move_id'];
    $up      = ($_POST['direction'] ?? '') === 'up';

    $cur_stmt = $pdo->prepare('SELECT id, sort_order FROM posts WHERE id = :id LIMIT 1');
    $cur_stmt->execute([':id' => $move_id]);
    $current = $cur_stmt->fetch();

    if ($current) {
        // (sort_order, id) の組で「ひとつ前 / ひとつ後ろ」を探す
        $sql = $up
            ? 'SELECT id, sort_order FROM posts
                WHERE sort_order < :o OR (sort_order = :o2 AND id < :id)
                ORDER BY sort_order DESC, id DESC LIMIT 1'
            : 'SELECT id, sort_order FROM posts
                WHERE sort_order > :o OR (sort_order = :o2 AND id > :id)
                ORDER BY sort_order ASC, id ASC LIMIT 1';

        $nb_stmt = $pdo->prepare($sql);
        $nb_stmt->execute([
            ':o'  => $current['sort_order'],
            ':o2' => $current['sort_order'],
            ':id' => $current['id'],
        ]);
        $neighbor = $nb_stmt->fetch();

        if ($neighbor) {
            if ((int)$neighbor['sort_order'] === (int)$current['sort_order']) {
                // 同値で並んでいると入れ替えられないので、一度振り直してからやり直す
                resequence_posts($pdo);
            } else {
                $upd = $pdo->prepare('UPDATE posts SET sort_order = :o WHERE id = :id');
                $upd->execute([':o' => $neighbor['sort_order'], ':id' => $current['id']]);
                $upd->execute([':o' => $current['sort_order'],  ':id' => $neighbor['id']]);
            }
        }
    }

    header('Location: ' . $redirect . (!empty($_POST['page']) ? '?page=' . (int)$_POST['page'] : ''));
    exit;
}

// ===================================================
//  検索 + ページネーション
// ===================================================
$q    = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($q !== '') {
    $where[]      = '(p.title LIKE :q OR p.period LIKE :q OR p.type LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM posts p $where_sql");
$count_stmt->execute($params);
$total       = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / POSTS_PER_PAGE));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * POSTS_PER_PAGE;

$sql = "SELECT p.*,
               (p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())) AS is_live,
               (SELECT COUNT(*) FROM post_sections s WHERE s.post_id = p.id) AS section_count
          FROM posts p
          $where_sql
          ORDER BY p.sort_order ASC, p.id ASC
          LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', POSTS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

// 並び替えは「全件を素の順序で見ている」ときだけ許可する
$sortable = ($q === '');

admin_header('作品一覧');
?>

<div class="page-header">
    <h1 class="page-title">作品</h1>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/post-new.php">+ 新規作成</a>
    </div>
</div>

<form class="search-form" method="get" action="<?= SITE_URL ?>/admin/index.php">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="タイトル・制作期間・種別で検索">
    <button class="btn btn-secondary" type="submit">検索</button>
    <?php if ($q !== ''): ?>
        <a class="btn btn-link" href="<?= SITE_URL ?>/admin/index.php">クリア</a>
    <?php endif; ?>
    <span class="search-meta">
        <?= $q !== '' ? '「' . h($q) . '」で ' . $total . ' 件' : '計 ' . $total . ' 件' ?>
    </span>
</form>

<?php if (empty($posts)): ?>
    <p class="empty-state">
        <?= $q !== '' ? '該当する作品はありません。' : '作品がまだありません。「+ 新規作成」から追加してください。' ?>
    </p>
<?php else: ?>
    <form method="post" onsubmit="return confirmBulk(this);">
        <?= csrf_field() ?>
        <div class="bulk-bar">
            <select name="bulk_action">
                <option value="">一括操作</option>
                <option value="delete">削除する</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">適用</button>
            <span class="count"><span id="selected-count">0</span> 件選択中</span>
        </div>

        <table class="table with-bulk">
            <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="check-all"></th>
                    <?php if ($sortable): ?><th style="width:64px;">順番</th><?php endif; ?>
                    <th>タイトル</th>
                    <th style="width:150px;">制作期間 / 種別</th>
                    <th style="width:130px;">公開状態</th>
                    <th style="width:230px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $i => $post): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= h($post['id']) ?>" class="row-check"></td>

                    <?php if ($sortable): ?>
                    <td>
                        <?php if (!($page === 1 && $i === 0)): ?>
                            <button type="submit" class="sort-btn" form="move-up-<?= h($post['id']) ?>">↑</button>
                        <?php endif; ?>
                        <?php if (!($page === $total_pages && $i === count($posts) - 1)): ?>
                            <button type="submit" class="sort-btn" form="move-down-<?= h($post['id']) ?>">↓</button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <td>
                        <a href="<?= SITE_URL ?>/admin/post-edit.php?id=<?= h($post['id']) ?>"><?= h($post['title']) ?></a>
                        <div class="row-sub">
                            <?= (int)$post['section_count'] ?> セクション
                            <?php if (!empty($post['video_url'])): ?> ／ 動画あり<?php endif; ?>
                            <?php if (!empty($post['external_url'])): ?> ／ 外部リンクあり<?php endif; ?>
                        </div>
                    </td>

                    <td class="row-sub">
                        <?= h($post['period'] ?? '') ?>
                        <?php if (!empty($post['type'])): ?>
                            <div><?= h($post['type']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($post['status'] === 'published' && $post['is_live']): ?>
                            <span class="badge badge-published">公開中</span>
                        <?php elseif ($post['status'] === 'published'): ?>
                            <span class="badge badge-scheduled">予約</span>
                            <div class="row-sub"><?= h($post['published_at']) ?></div>
                        <?php else: ?>
                            <span class="badge badge-draft">下書き</span>
                        <?php endif; ?>
                    </td>

                    <td class="actions">
                        <a href="<?= SITE_URL ?>/admin/post-edit.php?id=<?= h($post['id']) ?>">編集</a>
                        <a href="<?= SITE_URL ?>/preview.php?id=<?= h($post['id']) ?>" target="_blank">プレビュー</a>
                        <?php if ($post['is_live']): ?>
                            <a href="<?= h(public_post_url($post)) ?>" target="_blank" rel="noopener">公開ページ ↗</a>
                        <?php endif; ?>
                        <button type="submit" class="btn-link btn-danger"
                                form="delete-<?= h($post['id']) ?>"
                                onclick="return confirm('「<?= h($post['title']) ?>」を削除しますか？\n本文セクションとサムネイル画像も削除され、元に戻せません。');">
                            削除
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <?php // 一括操作フォームの中にフォームは入れられないので、行ごとのフォームは外に出しておく ?>
    <?php foreach ($posts as $post): ?>
        <form id="delete-<?= h($post['id']) ?>" method="post" hidden>
            <?= csrf_field() ?>
            <input type="hidden" name="delete_id" value="<?= h($post['id']) ?>">
        </form>
        <?php if ($sortable): ?>
            <form id="move-up-<?= h($post['id']) ?>" method="post" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="move_id" value="<?= h($post['id']) ?>">
                <input type="hidden" name="direction" value="up">
                <input type="hidden" name="page" value="<?= h($page) ?>">
            </form>
            <form id="move-down-<?= h($post['id']) ?>" method="post" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="move_id" value="<?= h($post['id']) ?>">
                <input type="hidden" name="direction" value="down">
                <input type="hidden" name="page" value="<?= h($page) ?>">
            </form>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($total_pages > 1): ?>
        <?php
            $qs   = $q !== '' ? 'q=' . urlencode($q) . '&' : '';
            $base = SITE_URL . '/admin/index.php';
            // ページ数が多いとき用に、現在ページの前後2つだけ出す
            $from = max(1, $page - 2);
            $to   = min($total_pages, $page + 2);
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= $base ?>?<?= $qs ?>page=<?= $page - 1 ?>">← 前</a>
            <?php else: ?>
                <span class="disabled">← 前</span>
            <?php endif; ?>

            <?php if ($from > 1): ?>
                <a href="<?= $base ?>?<?= $qs ?>page=1">1</a>
                <?php if ($from > 2): ?><span class="disabled">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $from; $p <= $to; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= $base ?>?<?= $qs ?>page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($to < $total_pages): ?>
                <?php if ($to < $total_pages - 1): ?><span class="disabled">…</span><?php endif; ?>
                <a href="<?= $base ?>?<?= $qs ?>page=<?= $total_pages ?>"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $base ?>?<?= $qs ?>page=<?= $page + 1 ?>">次 →</a>
            <?php else: ?>
                <span class="disabled">次 →</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$sortable): ?>
        <p class="hint-note">※ 検索中は並び替えできません。「クリア」を押すと ↑↓ が使えます。</p>
    <?php endif; ?>
<?php endif; ?>

<?php
admin_footer(<<<'HTML'
<script>
(function () {
    const checkAll = document.getElementById('check-all');
    const checks   = document.querySelectorAll('.row-check');
    const counter  = document.getElementById('selected-count');

    function updateCount() {
        if (counter) counter.textContent = [...checks].filter(c => c.checked).length;
    }

    if (checkAll) {
        checkAll.addEventListener('change', () => {
            checks.forEach(c => c.checked = checkAll.checked);
            updateCount();
        });
    }
    checks.forEach(c => c.addEventListener('change', updateCount));
    updateCount();
})();

function confirmBulk(form) {
    if (!form.bulk_action.value) {
        alert('操作を選択してください。');
        return false;
    }
    const checked = form.querySelectorAll('.row-check:checked').length;
    if (checked === 0) {
        alert('対象の作品を選択してください。');
        return false;
    }
    return confirm('選択した ' + checked + ' 件を削除しますか？\n本文セクションとサムネイル画像も削除され、元に戻せません。');
}
</script>
HTML);
?>
