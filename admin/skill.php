<?php
// ===================================================
//  スキル管理（一覧・並び替え・削除）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo      = db();
$redirect = SITE_URL . '/admin/skill.php';

// ---------------------------------------------------
//  削除（アイコン画像も一緒に消す）
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    verify_csrf();
    $del_id = (int)$_POST['delete_id'];

    $skill = get_skill($del_id);
    $pdo->prepare('DELETE FROM skills WHERE id = :id')->execute([':id' => $del_id]);

    if ($skill && !empty($skill['image'])) {
        delete_upload($skill['image']);
    }

    header('Location: ' . $redirect);
    exit;
}

// ---------------------------------------------------
//  並び替え（同じカテゴリ内で入れ替える）
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['move_id'])) {
    verify_csrf();
    $move_id = (int)$_POST['move_id'];
    $up      = ($_POST['direction'] ?? '') === 'up';

    $current = get_skill($move_id);

    if ($current) {
        $sql = $up
            ? 'SELECT id, sort_order FROM skills
                WHERE category = :cat AND (sort_order < :o OR (sort_order = :o2 AND id < :id))
                ORDER BY sort_order DESC, id DESC LIMIT 1'
            : 'SELECT id, sort_order FROM skills
                WHERE category = :cat AND (sort_order > :o OR (sort_order = :o2 AND id > :id))
                ORDER BY sort_order ASC, id ASC LIMIT 1';

        $nb = $pdo->prepare($sql);
        $nb->execute([
            ':cat' => $current['category'],
            ':o'   => $current['sort_order'],
            ':o2'  => $current['sort_order'],
            ':id'  => $current['id'],
        ]);
        $neighbor = $nb->fetch();

        if ($neighbor) {
            if ((int)$neighbor['sort_order'] === (int)$current['sort_order']) {
                // 同値だと入れ替えられないので、カテゴリ内で振り直してからやり直す
                $ids = $pdo->prepare(
                    'SELECT id FROM skills WHERE category = :cat ORDER BY sort_order ASC, id ASC'
                );
                $ids->execute([':cat' => $current['category']]);
                $upd = $pdo->prepare('UPDATE skills SET sort_order = :o WHERE id = :id');
                foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $i => $sid) {
                    $upd->execute([':o' => $i + 1, ':id' => $sid]);
                }
            } else {
                $upd = $pdo->prepare('UPDATE skills SET sort_order = :o WHERE id = :id');
                $upd->execute([':o' => $neighbor['sort_order'], ':id' => $current['id']]);
                $upd->execute([':o' => $current['sort_order'],  ':id' => $neighbor['id']]);
            }
        }
    }

    header('Location: ' . $redirect);
    exit;
}

$grouped = get_skills_grouped();
$total   = array_sum(array_map('count', $grouped));

// SKILL_CATEGORIES に無いカテゴリ（過去データ等）も末尾に出す
$display_categories = array_merge(
    SKILL_CATEGORIES,
    array_diff(array_keys($grouped), SKILL_CATEGORIES)
);

admin_header('スキル管理');
?>

<div class="page-header">
    <h1 class="page-title">スキル</h1>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/skill-edit.php">+ 新規追加</a>
    </div>
</div>

<p class="page-meta">
    計 <?= $total ?> 件。カテゴリごとにまとめて公開ページに表示されます（表示順は ↑↓ で調整）。
</p>

<?php if ($total === 0): ?>
    <p class="empty-state">
        スキルがまだ登録されていません。「+ 新規追加」から追加してください。
    </p>
<?php else: ?>
    <?php foreach ($display_categories as $cat): ?>
        <?php if (empty($grouped[$cat])) continue; ?>
        <?php $rows = $grouped[$cat]; ?>

        <h2 class="section-title"><?= h($cat) ?> <span class="row-sub">(<?= count($rows) ?>)</span></h2>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:64px;">順番</th>
                    <th style="width:72px;">アイコン</th>
                    <th style="width:180px;">スキル名</th>
                    <th style="width:130px;">期間</th>
                    <th>説明</th>
                    <th style="width:110px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $skill): ?>
                <tr>
                    <td>
                        <?php if ($i > 0): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="move_id" value="<?= h($skill['id']) ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="sort-btn">↑</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($i < count($rows) - 1): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="move_id" value="<?= h($skill['id']) ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="sort-btn">↓</button>
                            </form>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (!empty($skill['image'])): ?>
                            <img class="skill-icon" src="<?= h(post_thumb_url($skill['image'])) ?>" alt="">
                        <?php else: ?>
                            <span class="row-sub">—</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <a href="<?= SITE_URL ?>/admin/skill-edit.php?id=<?= h($skill['id']) ?>"><?= h($skill['title']) ?></a>
                    </td>

                    <td class="row-sub"><?= h($skill['period'] ?? '') ?></td>

                    <td class="row-sub"><?= h(mb_strimwidth((string)($skill['body'] ?? ''), 0, 60, '…')) ?></td>

                    <td class="actions">
                        <a href="<?= SITE_URL ?>/admin/skill-edit.php?id=<?= h($skill['id']) ?>">編集</a>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('「<?= h($skill['title']) ?>」を削除しますか？\nアイコン画像も削除され、元に戻せません。');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_id" value="<?= h($skill['id']) ?>">
                            <button type="submit" class="btn-link btn-danger">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

<p class="hint-note">
    ※ カテゴリの種類と表示順は <code>config.php</code> の <code>SKILL_CATEGORIES</code> で変更できます。
</p>

<?php admin_footer(); ?>
