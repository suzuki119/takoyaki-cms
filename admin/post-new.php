<?php
// ===================================================
//  作品 新規作成
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_post-form.php';
require_login();

$pdo   = db();
$error = '';

$categories = get_categories();

// 画面に表示する値（エラー時はPOSTした内容を戻す）
$post         = ['status' => 'draft'];
$sections     = [];
$selected_ids = [];
$tags_str     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $input        = collect_post_input();
    $f            = $input['fields'];
    $error        = $input['error'];
    $sections     = $input['sections'];
    $selected_ids = $input['categories'];
    $tags_str     = $input['tags'];

    // 再表示用（保存に失敗してもフォームの内容を保つ）
    $post = $f + ['thumbnail' => null, 'slug' => $f['slug_input'], 'published_at' => $f['published_at_raw']];

    $thumbnail = null;
    if ($error === '' && !empty($_FILES['thumbnail']['name'])) {
        $up = handle_image_upload($_FILES['thumbnail']);
        if ($up['error'] !== null) {
            $error = $up['error'];
        } else {
            $thumbnail = $up['filename'];
        }
    }

    if ($error === '') {
        // 公開なのに日時未指定なら保存時刻を使う
        $published_at = $f['published_at'];
        if ($f['status'] === 'published' && $published_at === null) {
            $published_at = date('Y-m-d H:i:s');
        }

        try {
            $pdo->beginTransaction();

            // slug は一旦仮で入れて、採番されたIDを使って確定させる
            $stmt = $pdo->prepare(
                'INSERT INTO posts
                    (title, slug, excerpt, thumbnail, status, published_at,
                     period, type, external_url, video_url, sort_order)
                 VALUES
                    (:title, NULL, :excerpt, :thumbnail, :status, :published_at,
                     :period, :type, :external_url, :video_url,
                     (SELECT COALESCE(MAX(s.sort_order), 0) + 1 FROM (SELECT sort_order FROM posts) AS s))'
            );
            $stmt->execute([
                ':title'        => $f['title'],
                ':excerpt'      => $f['excerpt'],
                ':thumbnail'    => $thumbnail,
                ':status'       => $f['status'],
                ':published_at' => $published_at,
                ':period'       => $f['period'],
                ':type'         => $f['type'],
                ':external_url' => $f['external_url'],
                ':video_url'    => $f['video_url'],
            ]);

            $post_id = (int)$pdo->lastInsertId();

            $slug = unique_slug(
                sluggify($f['slug_input'] !== '' ? $f['slug_input'] : $f['title']),
                'posts',
                $post_id,
                'work-' . $post_id
            );
            $pdo->prepare('UPDATE posts SET slug = :s WHERE id = :id')
                ->execute([':s' => $slug, ':id' => $post_id]);

            set_post_sections($post_id, $sections);
            set_post_categories($post_id, $selected_ids);
            set_post_tags($post_id, $tags_str);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[Takoyaki CMS] post insert failed: ' . $e->getMessage());
            $error = '作品の保存に失敗しました。';
            if ($thumbnail !== null) {
                delete_upload($thumbnail);
            }
        }

        if ($error === '') {
            header('Location: ' . post_save_redirect($post_id));
            exit;
        }
    }
}

admin_header('作品 新規作成', post_form_head());
?>

<h1 class="page-title">作品 新規作成</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php render_post_form($post, $sections, $categories, $selected_ids, $tags_str, true); ?>

<?php admin_footer(post_form_script()); ?>
