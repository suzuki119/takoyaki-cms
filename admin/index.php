<?php
// ===================================================
//  管理画面 トップ（記事一覧 + ゴミ箱）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

const POSTS_PER_PAGE = 20;

$pdo  = db();
$view = ($_GET['view'] ?? '') === 'trash' ? 'trash' : 'normal';

// アクション後のリダイレクト先（ビューを維持）
$redirect = SITE_URL . '/admin/index.php' . ($view === 'trash' ? '?view=trash' : '');

// ===================================================
//  一括操作（削除 / 復元 / 完全削除）
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_action'])) {
    verify_csrf();
    $action = $_POST['bulk_action'];
    $ids    = $_POST['ids'] ?? [];

    if (is_array($ids) && !empty($ids)) {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            if ($action === 'delete') {
                $pdo->prepare("UPDATE posts SET deleted_at = NOW() WHERE id IN ($placeholders)")->execute($ids);
                log_action('post.bulk_trash', 'post', null, 'ゴミ箱へ: ' . implode(',', $ids));
            } elseif ($action === 'restore') {
                $pdo->prepare("UPDATE posts SET deleted_at = NULL WHERE id IN ($placeholders)")->execute($ids);
                log_action('post.bulk_restore', 'post', null, '復元: ' . implode(',', $ids));
            } elseif ($action === 'purge') {
                $pdo->prepare("DELETE FROM posts WHERE id IN ($placeholders)")->execute($ids);
                log_action('post.bulk_delete', 'post', null, '完全削除: ' . implode(',', $ids));
                foreach ($ids as $pid) {
                    do_action('post.delete', $pid);
                }
            }
        }
    }
    header('Location: ' . $redirect);
    exit;
}

// ===================================================
//  単一操作
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    verify_csrf();
    $delete_id = (int)$_POST['delete_id'];
    // 通常ビュー → ゴミ箱へ（ソフトデリート）
    $pdo->prepare('UPDATE posts SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $delete_id]);
    log_action('post.trash', 'post', $delete_id);
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['restore_id'])) {
    verify_csrf();
    $restore_id = (int)$_POST['restore_id'];
    $pdo->prepare('UPDATE posts SET deleted_at = NULL WHERE id = :id')->execute([':id' => $restore_id]);
    log_action('post.restore', 'post', $restore_id);
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['purge_id'])) {
    verify_csrf();
    $purge_id = (int)$_POST['purge_id'];
    $pdo->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $purge_id]);
    log_action('post.delete', 'post', $purge_id);
    do_action('post.delete', $purge_id);
    header('Location: ' . $redirect);
    exit;
}

// ===================================================
//  並び替え（↑↓）— 通常ビューのみ
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['move_id'])) {
    verify_csrf();
    $move_id   = (int)$_POST['move_id'];
    $direction = $_POST['direction'] ?? '';

    $stmt_cur = $pdo->prepare('SELECT id, sort_order FROM posts WHERE id = :id');
    $stmt_cur->execute([':id' => $move_id]);
    $current = $stmt_cur->fetch();

    if ($current) {
        $stmt_nb = $direction === 'up'
            ? $pdo->prepare('SELECT id, sort_order FROM posts WHERE sort_order < :order AND deleted_at IS NULL ORDER BY sort_order DESC LIMIT 1')
            : $pdo->prepare('SELECT id, sort_order FROM posts WHERE sort_order > :order AND deleted_at IS NULL ORDER BY sort_order ASC LIMIT 1');
        $stmt_nb->execute([':order' => $current['sort_order']]);
        $neighbor = $stmt_nb->fetch();

        if ($neighbor) {
            $pdo->prepare('UPDATE posts SET sort_order = :order WHERE id = :id')
                ->execute([':order' => $neighbor['sort_order'], ':id' => $current['id']]);
            $pdo->prepare('UPDATE posts SET sort_order = :order WHERE id = :id')
                ->execute([':order' => $current['sort_order'], ':id' => $neighbor['id']]);
        }
    }

    header('Location: ' . $redirect);
    exit;
}

// ===================================================
//  検索 + ページネーション
// ===================================================
$q    = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where  = [$view === 'trash' ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
$params = [];
if ($q !== '') {
    $where[]      = '(title LIKE :q OR body LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

// ゴミ箱の件数（タブ表示用）
$trash_count = (int)$pdo->query('SELECT COUNT(*) FROM posts WHERE deleted_at IS NOT NULL')->fetchColumn();

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM posts $where_sql");
$count_stmt->execute($params);
$total       = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / POSTS_PER_PAGE));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * POSTS_PER_PAGE;

$sql = "SELECT *,
               (status = 'published' AND (published_at IS NULL OR published_at <= NOW())) AS is_live
          FROM posts
          $where_sql
          ORDER BY sort_order ASC
          LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', POSTS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

admin_header($view === 'trash' ? 'ゴミ箱' : '記事一覧');
?>

<div class="page-header">
    <h1 class="page-title"><?= $view === 'trash' ? 'ゴミ箱' : '記事一覧' ?></h1>
    <div class="page-actions">
        <?php if ($view === 'trash'): ?>
            <a class="btn btn-secondary" href="<?= SITE_URL ?>/admin/index.php">← 記事一覧へ</a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/post-new.php">+ 新規作成</a>
            <a class="btn btn-secondary" href="<?= SITE_URL ?>/admin/index.php?view=trash">ゴミ箱 (<?= $trash_count ?>)</a>
        <?php endif; ?>
    </div>
</div>

<form class="search-form" method="get" action="<?= SITE_URL ?>/admin/index.php">
    <?php if ($view === 'trash'): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="タイトル・本文で検索">
    <button class="btn btn-secondary" type="submit">検索</button>
    <?php if ($q !== ''): ?>
        <a class="btn btn-link" href="<?= SITE_URL ?>/admin/index.php<?= $view === 'trash' ? '?view=trash' : '' ?>">クリア</a>
    <?php endif; ?>
    <span class="search-meta">
        <?php if ($q !== ''): ?>
            「<?= h($q) ?>」で <?= $total ?> 件
        <?php else: ?>
            計 <?= $total ?> 件
        <?php endif; ?>
    </span>
</form>

<?php if (empty($posts)): ?>
    <p class="empty-state">
        <?php if ($view === 'trash'): ?>
            ゴミ箱は空です。
        <?php else: ?>
            <?= $q !== '' ? '該当する記事はありません。' : '記事がまだありません。' ?>
        <?php endif; ?>
    </p>
<?php else: ?>
    <form method="post" onsubmit="return confirmBulk(this);">
        <?= csrf_field() ?>
        <div class="bulk-bar">
            <select name="bulk_action">
                <option value="">一括操作</option>
                <?php if ($view === 'trash'): ?>
                    <option value="restore">復元</option>
                    <option value="purge">完全に削除</option>
                <?php else: ?>
                    <option value="delete">ゴミ箱へ移動</option>
                <?php endif; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">適用</button>
            <span class="count"><span id="selected-count">0</span> 件選択中</span>
        </div>
        <table class="table with-bulk">
            <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="check-all"></th>
                    <?php if ($view === 'normal'): ?><th style="width:64px;">順番</th><?php endif; ?>
                    <th>タイトル</th>
                    <th style="width:140px;"><?= $view === 'trash' ? '削除日時' : '公開状態' ?></th>
                    <th style="width:220px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $i => $post): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= h($post['id']) ?>" class="row-check"></td>
                    <?php if ($view === 'normal'): ?>
                    <td>
                        <?php if ($i > 0 && $q === ''): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="move_id" value="<?= h($post['id']) ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="sort-btn">↑</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($i < count($posts) - 1 && $q === ''): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="move_id" value="<?= h($post['id']) ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="sort-btn">↓</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><?= h($post['title']) ?></td>
                    <td>
                        <?php if ($view === 'trash'): ?>
                            <span style="font-size:.8rem;color:#888;"><?= h($post['deleted_at']) ?></span>
                        <?php elseif ($post['status'] === 'published' && $post['is_live']): ?>
                            <span class="badge badge-published">公開中</span>
                        <?php elseif ($post['status'] === 'published'): ?>
                            <span class="badge badge-scheduled">予約</span>
                            <div style="font-size:.75rem;color:#888;margin-top:2px;"><?= h($post['published_at']) ?></div>
                        <?php else: ?>
                            <span class="badge badge-draft">下書き</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($view === 'trash'): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="restore_id" value="<?= h($post['id']) ?>">
                                <button type="submit" class="btn-link" style="border:none;background:none;cursor:pointer;font-size:.85rem;padding:0;">復元</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('完全に削除しますか？この操作は元に戻せません。');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="purge_id" value="<?= h($post['id']) ?>">
                                <button type="submit" class="btn-link" style="color:#dc2626;border:none;background:none;cursor:pointer;font-size:.85rem;padding:0;">完全に削除</button>
                            </form>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/admin/post-edit.php?id=<?= h($post['id']) ?>">編集</a>
                            <a href="<?= SITE_URL ?>/preview.php?id=<?= h($post['id']) ?>" target="_blank">プレビュー</a>
                            <?php if ($post['is_live']): ?>
                                <a href="<?= h(public_post_url($post)) ?>" target="_blank" rel="noopener">公開ページ ↗</a>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('この記事をゴミ箱に移動しますか？');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= h($post['id']) ?>">
                                <button type="submit" class="btn-link" style="color:#dc2626;border:none;background:none;cursor:pointer;font-size:.85rem;padding:0;">ゴミ箱へ</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <?php if ($total_pages > 1): ?>
        <?php
            $qs   = ($view === 'trash' ? 'view=trash&' : '') . ($q !== '' ? 'q=' . urlencode($q) . '&' : '');
            $base = SITE_URL . '/admin/index.php';
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= $base ?>?<?= $qs ?>page=<?= $page - 1 ?>">← 前</a>
            <?php else: ?>
                <span class="disabled">← 前</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= $base ?>?<?= $qs ?>page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base ?>?<?= $qs ?>page=<?= $page + 1 ?>">次 →</a>
            <?php else: ?>
                <span class="disabled">次 →</span>
            <?php endif; ?>
        </div>
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
        const n = [...checks].filter(c => c.checked).length;
        if (counter) counter.textContent = n;
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
    const action = form.bulk_action.value;
    if (!action) {
        alert('操作を選択してください。');
        return false;
    }
    const checked = form.querySelectorAll('.row-check:checked').length;
    if (checked === 0) {
        alert('対象の記事を選択してください。');
        return false;
    }
    if (action === 'purge') {
        return confirm('選択した ' + checked + ' 件を完全に削除しますか？この操作は元に戻せません。');
    }
    if (action === 'delete') {
        return confirm('選択した ' + checked + ' 件をゴミ箱に移動しますか？');
    }
    return true;
}
</script>
HTML);
?>
