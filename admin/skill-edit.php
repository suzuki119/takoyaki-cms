<?php
// ===================================================
//  スキル 新規追加 / 編集
//    skill-edit.php        → 新規追加
//    skill-edit.php?id=1   → 編集
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';

$id     = (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

if ($is_new) {
    $skill = ['category' => SKILL_CATEGORIES[0] ?? 'その他'];
} else {
    $skill = get_skill($id);
    if (!$skill) {
        header('Location: ' . SITE_URL . '/admin/skill.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title    = trim((string)($_POST['title'] ?? ''));
    $category = (string)($_POST['category'] ?? '');
    $period   = trim((string)($_POST['period'] ?? ''));
    $body     = trim((string)($_POST['body'] ?? ''));

    if ($title === '') {
        $error = 'スキル名は必須です。';
    } elseif (mb_strlen($title) > 100) {
        $error = 'スキル名は100文字以内にしてください。';
    } elseif (!in_array($category, SKILL_CATEGORIES, true)) {
        // 選択肢を書き換えて送られても弾く
        $error = 'カテゴリが不正です。';
    }

    $image     = $skill['image'] ?? null;
    $old_image = null;

    if ($error === '' && !empty($_FILES['image']['name'])) {
        $up = handle_image_upload($_FILES['image']);
        if ($up['error'] !== null) {
            $error = $up['error'];
        } else {
            $old_image = $skill['image'] ?? null;
            $image     = $up['filename'];
        }
    } elseif ($error === '' && !empty($_POST['delete_image']) && !empty($skill['image'])) {
        $old_image = $skill['image'];
        $image     = null;
    }

    if ($error === '') {
        try {
            if ($is_new) {
                // 同じカテゴリの末尾に追加する
                $next = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM skills WHERE category = :cat'
                );
                $next->execute([':cat' => $category]);
                $sort_order = (int)$next->fetchColumn();

                $stmt = $pdo->prepare(
                    'INSERT INTO skills (category, title, image, period, body, sort_order)
                     VALUES (:cat, :title, :image, :period, :body, :order)'
                );
                $stmt->execute([
                    ':cat'    => $category,
                    ':title'  => $title,
                    ':image'  => $image,
                    ':period' => $period !== '' ? $period : null,
                    ':body'   => $body !== '' ? $body : null,
                    ':order'  => $sort_order,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE skills SET category = :cat, title = :title, image = :image,
                            period = :period, body = :body
                      WHERE id = :id'
                );
                $stmt->execute([
                    ':cat'    => $category,
                    ':title'  => $title,
                    ':image'  => $image,
                    ':period' => $period !== '' ? $period : null,
                    ':body'   => $body !== '' ? $body : null,
                    ':id'     => $id,
                ]);
            }

            if ($old_image !== null) {
                delete_upload($old_image);
            }

            header('Location: ' . SITE_URL . '/admin/skill.php');
            exit;
        } catch (PDOException $e) {
            error_log('[Takoyaki CMS] skill save failed: ' . $e->getMessage());
            $error = 'スキルの保存に失敗しました。';
            // 使われなかった画像は消す
            if ($image !== null && $image !== ($skill['image'] ?? null)) {
                delete_upload($image);
                $image = $skill['image'] ?? null;
            }
        }
    }

    // 失敗時は入力内容を画面に戻す
    $skill = array_merge($skill, [
        'title'    => $title,
        'category' => $category,
        'period'   => $period,
        'body'     => $body,
        'image'    => $image,
    ]);
}

admin_header($is_new ? 'スキル 新規追加' : 'スキル 編集');
?>

<h1 class="page-title"><?= $is_new ? 'スキル 新規追加' : 'スキル 編集： ' . h($skill['title']) ?></h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label class="field">スキル名
            <input type="text" name="title" value="<?= h($skill['title'] ?? '') ?>" required maxlength="100" placeholder="例: PHP">
        </label>

        <label class="field">カテゴリ
            <select name="category">
                <?php foreach (SKILL_CATEGORIES as $cat): ?>
                    <option value="<?= h($cat) ?>" <?= ($skill['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= h($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="field-hint">公開ページではこのカテゴリごとにグループ分けして表示されます。</p>
        </label>

        <label class="field">期間・習熟度（任意）
            <input type="text" name="period" value="<?= h($skill['period'] ?? '') ?>" maxlength="100" placeholder="例: 使用歴2年">
            <p class="field-hint">スキル名の下に小さく表示されます。</p>
        </label>

        <label class="field">説明（任意）
            <textarea name="body" style="height:120px;" placeholder="どんな場面で使ってきたか、得意なことなど"><?= h($skill['body'] ?? '') ?></textarea>
            <p class="field-hint">プレーンテキストとして表示されます（HTMLは使えません）。</p>
        </label>

        <label class="field">アイコン画像（任意）
            <?php if (!empty($skill['image'])): ?>
                <div class="thumbnail-preview">
                    <img src="<?= h(post_thumb_url($skill['image'])) ?>" alt="現在のアイコン" style="max-width:120px;">
                    <label>
                        <input type="checkbox" name="delete_image" value="1">
                        この画像を削除する
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
            <p class="field-hint">正方形に近い画像が綺麗に表示されます（jpg / png / gif / webp）。</p>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $is_new ? '追加する' : '更新する' ?></button>
            <a class="btn-link" href="<?= SITE_URL ?>/admin/skill.php">← スキル一覧へ戻る</a>
        </div>
    </form>
</div>

<?php admin_footer(); ?>
