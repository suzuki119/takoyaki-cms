<?php
// ===================================================
//  作品 編集
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_post-form.php';
require_login();

$pdo   = db();
$error = '';

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$categories = get_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $input        = collect_post_input();
    $f            = $input['fields'];
    $error        = $input['error'];
    $sections     = $input['sections'];
    $selected_ids = $input['categories'];
    $tags_str     = $input['tags'];

    $thumbnail     = $post['thumbnail'];
    $old_thumbnail = null;   // 差し替え・削除が成功したあとに消す

    if ($error === '' && !empty($_FILES['thumbnail']['name'])) {
        $up = handle_image_upload($_FILES['thumbnail']);
        if ($up['error'] !== null) {
            $error = $up['error'];
        } else {
            $old_thumbnail = $post['thumbnail'];
            $thumbnail     = $up['filename'];
        }
    } elseif ($error === '' && !empty($_POST['delete_thumbnail']) && $post['thumbnail']) {
        $old_thumbnail = $post['thumbnail'];
        $thumbnail     = null;
    }

    if ($error === '') {
        // 公開なのに日時未指定なら、元の値かこの瞬間の時刻を使う
        $published_at = $f['published_at'];
        if ($f['status'] === 'published' && $published_at === null) {
            $published_at = $post['published_at'] ?: date('Y-m-d H:i:s');
        }

        $slug = unique_slug(
            sluggify($f['slug_input'] !== '' ? $f['slug_input'] : $f['title']),
            'posts',
            $id,
            'work-' . $id
        );

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare(
                'UPDATE posts SET
                    title = :title, slug = :slug, excerpt = :excerpt, thumbnail = :thumbnail,
                    status = :status, published_at = :published_at,
                    period = :period, type = :type,
                    external_url = :external_url, video_url = :video_url
                 WHERE id = :id'
            );
            $upd->execute([
                ':title'        => $f['title'],
                ':slug'         => $slug,
                ':excerpt'      => $f['excerpt'],
                ':thumbnail'    => $thumbnail,
                ':status'       => $f['status'],
                ':published_at' => $published_at,
                ':period'       => $f['period'],
                ':type'         => $f['type'],
                ':external_url' => $f['external_url'],
                ':video_url'    => $f['video_url'],
                ':id'           => $id,
            ]);

            set_post_sections($id, $sections);
            set_post_categories($id, $selected_ids);
            set_post_tags($id, $tags_str);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[Takoyaki CMS] post update failed: ' . $e->getMessage());
            $error = '作品の保存に失敗しました。';
            // 新しく上げた画像は使われないので消す
            if ($thumbnail !== null && $thumbnail !== $post['thumbnail']) {
                delete_upload($thumbnail);
            }
        }

        if ($error === '') {
            // 保存が確定してから古い画像を消す
            if ($old_thumbnail !== null) {
                delete_upload($old_thumbnail);
            }
            header('Location: ' . post_save_redirect($id));
            exit;
        }
    }

    // 保存に失敗したときは入力内容をそのまま画面に戻す
    $post = array_merge($post, $f, [
        'slug'         => $f['slug_input'],
        'published_at' => $f['published_at_raw'],
        'thumbnail'    => $thumbnail,
    ]);
} else {
    // 通常表示：DBから現在の値を読む
    $sections     = array_map(
        fn($s) => ['title' => $s['title'], 'body' => $s['body']],
        get_post_sections($id)
    );
    $selected_ids = array_column(get_post_categories($id), 'id');
    $tags_str     = implode(', ', array_column(get_post_tags($id), 'name'));
}

admin_header('作品 編集', post_form_head());
?>

<div class="page-header">
    <h1 class="page-title">作品 編集</h1>
    <div class="page-actions">
        <?php if (is_post_live($post)): ?>
            <a class="btn btn-secondary" href="<?= h(public_post_url($post)) ?>" target="_blank" rel="noopener">公開ページ ↗</a>
        <?php endif; ?>
    </div>
</div>
<p class="page-meta">
    ID: <?= h($post['id']) ?> ／ 作成: <?= h($post['created_at']) ?> ／ 最終更新: <?= h($post['updated_at']) ?>
</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php render_post_form($post, $sections, $categories, $selected_ids, $tags_str, false); ?>

<?php admin_footer(post_form_script()); ?>
