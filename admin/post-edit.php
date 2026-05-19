<?php
// ===================================================
//  記事編集
// ===================================================
require_once '../config.php';
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

// カテゴリ一覧を取得
$c_stmt = $pdo->prepare('SELECT * FROM categories ORDER BY id ASC');
$c_stmt->execute();
$categories = $c_stmt->fetchAll();

// この記事に現在付与されているカテゴリIDを取得
$pc_stmt = $pdo->prepare('SELECT category_id FROM post_categories WHERE post_id = :post_id');
$pc_stmt->execute([':post_id' => $id]);
$post_category_id  = $pc_stmt->fetch();
$currentCategoryId = $post_category_id ? $post_category_id['category_id'] : null;

// ===================================================
//  更新処理
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title           = trim($_POST['title']        ?? '');
    $slug_input      = trim($_POST['slug']         ?? '');
    $body            = $_POST['body']              ?? '';
    $excerpt         = trim($_POST['excerpt']      ?? '');
    $status          = $_POST['status']            ?? 'draft';
    $published_at_in = trim($_POST['published_at'] ?? '');
    $thumbnail       = $post['thumbnail'];
    $category_id     = $_POST['category_id']       ?? '';

    // slug: 入力があれば sluggify、なければタイトルから自動生成
    $slug = sluggify($slug_input !== '' ? $slug_input : $title);
    $slug = $slug === '' ? null : $slug;

    // published_at:
    // - status='draft' → NULL
    // - status='published' & 入力あり → 入力値を採用
    // - status='published' & 入力なし → 既存値があれば維持、なければ現在時刻
    $published_at = null;
    if ($status === 'published') {
        if ($published_at_in !== '') {
            $published_at = str_replace('T', ' ', $published_at_in) . ':00';
        } else {
            $published_at = $post['published_at'] ?? date('Y-m-d H:i:s');
        }
    }

    if ($title === '') {
        $error = 'タイトルは必須です。';
    } else {
        // ===================================================
        //  画像アップロード処理
        // ===================================================
        if (!empty($_FILES['thumbnail']['name'])) {
            $file          = $_FILES['thumbnail'];
            $ext           = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $actual_mime   = mime_content_type($file['tmp_name']);

            if (!in_array($ext, $allowed_ext) || !in_array($actual_mime, $allowed_mimes)) {
                $error = '画像はjpg・png・gif・webpのみ使用できます。';
            } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
                $error = '画像サイズは ' . (int)(MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB 以下にしてください。';
            } else {
                $filename = uniqid() . '.' . $ext;
                $savePath = UPLOAD_DIR . $filename;

                if (move_uploaded_file($file['tmp_name'], $savePath)) {
                    // 新しい画像をリサイズ
                    resize_image($savePath, IMAGE_MAX_WIDTH, $savePath);
                    resize_image($savePath, IMAGE_THUMB_WIDTH, UPLOAD_DIR . thumb_filename($filename));

                    // 古い画像とその thumb 変種を削除
                    if ($post['thumbnail']) {
                        @unlink(UPLOAD_DIR . $post['thumbnail']);
                        @unlink(UPLOAD_DIR . thumb_filename($post['thumbnail']));
                    }
                    $thumbnail = $filename;
                } else {
                    $error = '画像の保存に失敗しました。';
                }
            }
        }

        if (!empty($_POST['delete_thumbnail']) && $post['thumbnail']) {
            @unlink(UPLOAD_DIR . $post['thumbnail']);
            @unlink(UPLOAD_DIR . thumb_filename($post['thumbnail']));
            $thumbnail = null;
        }

        if ($error === '') {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE posts SET
                        title = :title, slug = :slug, body = :body, excerpt = :excerpt,
                        thumbnail = :thumbnail, status = :status, published_at = :published_at
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':title'        => $title,
                    ':slug'         => $slug,
                    ':body'         => $body,
                    ':excerpt'      => $excerpt !== '' ? $excerpt : null,
                    ':thumbnail'    => $thumbnail,
                    ':status'       => $status,
                    ':published_at' => $published_at,
                    ':id'           => $id,
                ]);
            } catch (PDOException $e) {
                $error = '記事の保存に失敗しました（slug が既存と重複している可能性があります）。';
            }
        }

        if ($error === '') {

            // カテゴリの紐付けを更新（全削除 → 入れ直し）
            $pc_stmt = $pdo->prepare('DELETE FROM post_categories WHERE post_id = :post_id');
            $pc_stmt->execute([':post_id' => $id]);

            if (!empty($category_id)) {
                $pc_stmt = $pdo->prepare('INSERT INTO post_categories (post_id, category_id) VALUES (:post_id, :category_id)');
                $pc_stmt->execute([':post_id' => $id, ':category_id' => $category_id]);
            }

            header('Location: ' . SITE_URL . '/admin/index.php');
            exit;
        }
    }

    // エラー時：フォームの入力値を保持する
    $post['title']        = $title;
    $post['slug']         = $slug_input;
    $post['body']         = $body;
    $post['excerpt']      = $excerpt;
    $post['status']       = $status;
    $post['published_at'] = $published_at_in !== '' ? $published_at_in : ($post['published_at'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>記事編集 | 管理画面</title>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
    <script type="importmap">
    {
        "imports": {
            "ckeditor5": "https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.js",
            "ckeditor5/": "https://cdn.ckeditor.com/ckeditor5/43.3.1/"
        }
    }
    </script>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        label { display: block; margin-top: 20px; font-size: .9rem; font-weight: bold; }
        input[type="text"], input[type="url"], textarea, select { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 6px; border: 1px solid #ccc; font-size: 1rem; }
        textarea { height: 200px; resize: vertical; }
        .actions { margin-top: 24px; display: flex; gap: 12px; align-items: center; }
        button[type="submit"] { padding: 10px 24px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        a.back { font-size: .9rem; color: #666; }
        .error { margin-top: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .hint { font-size: .8rem; color: #666; margin-top: 4px; font-weight: normal; }
        .meta { margin-top: 8px; font-size: .8rem; color: #999; }
        .thumbnail-preview img { max-width: 200px; margin-top: 8px; display: block; }
        .thumbnail-preview label { font-weight: normal; font-size: .85rem; color: #c0392b; margin-top: 6px; }
        .ck-editor__editable { min-height: 300px; }
    </style>
</head>
<body>
    <h1>記事編集</h1>
    <p class="meta">ID: <?= h($post['id']) ?> ／ 作成日: <?= h($post['created_at']) ?></p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label>タイトル
            <input type="text" name="title" value="<?= h($post['title']) ?>" required>
        </label>

        <label>slug（URL用識別子、任意）
            <input type="text" name="slug" value="<?= h($post['slug'] ?? '') ?>" placeholder="例: my-first-post">
            <p class="hint">空欄の場合はタイトルから自動生成。英数字とハイフンのみ。</p>
        </label>

        <label>本文
            <!-- [重要] WYSIWYGエディタの内容はHTMLのまま保存するため、h()でエスケープせずそのまま出力する -->
            <textarea name="body" class="wysiwyg"><?= $post['body'] ?? '' ?></textarea>
        </label>

        <label>抜粋（任意、最大500文字）
            <textarea name="excerpt" maxlength="500" style="height: 80px;"><?= h($post['excerpt'] ?? '') ?></textarea>
            <p class="hint">一覧ページで表示する短い説明文。</p>
        </label>

        <label>サムネイル画像
            <?php if ($post['thumbnail']): ?>
                <div class="thumbnail-preview">
                    <img src="<?= UPLOAD_URL . h($post['thumbnail']) ?>" alt="現在のサムネイル">
                    <label>
                        <input type="checkbox" name="delete_thumbnail" value="1">
                        この画像を削除する
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="thumbnail" accept="image/*" style="margin-top:8px;">
        </label>

        <label>ステータス
            <select name="status">
                <option value="draft"     <?= $post['status'] === 'draft'     ? 'selected' : '' ?>>下書き</option>
                <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>公開</option>
            </select>
        </label>

        <?php
            // datetime-local の value は "YYYY-MM-DDTHH:MM" 形式
            $pub_val = '';
            if (!empty($post['published_at'])) {
                // 既にHTML形式（POSTエラー時）か MySQL形式（DB由来）かを正規化
                $pub_val = str_replace(' ', 'T', substr($post['published_at'], 0, 16));
            }
        ?>
        <label>公開日時
            <input type="datetime-local" name="published_at" value="<?= h($pub_val) ?>">
            <p class="hint">「公開」ステータスのときに有効。未来日付なら予約公開、空欄なら保存時刻を使用。</p>
        </label>

        <label>カテゴリー
            <select name="category_id">
                <option value="">選択してください</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h($category['id']) ?>"
                        <?= $category['id'] == $currentCategoryId ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="actions">
            <button type="submit">更新する</button>
            <a class="back" href="<?= SITE_URL ?>/admin/index.php">← 一覧へ戻る</a>
        </div>
    </form>

    <script type="module">
    import {
        ClassicEditor,
        Essentials,
        Bold, Italic, Underline, Strikethrough,
        Heading,
        Paragraph,
        List,
        Link,
        BlockQuote,
        Indent, IndentBlock,
        SimpleUploadAdapter,
        Image, ImageCaption, ImageStyle, ImageToolbar, ImageResize, ImageUpload,
        Table, TableToolbar,
    } from 'ckeditor5';
    import 'ckeditor5/translations/ja.js';

    const editorConfig = {
        plugins: [
            Essentials,
            Bold, Italic, Underline, Strikethrough,
            Heading,
            Paragraph,
            List,
            Link,
            BlockQuote,
            Indent, IndentBlock,
            SimpleUploadAdapter,
            Table, TableToolbar,
            Image, ImageCaption, ImageStyle, ImageToolbar, ImageResize, ImageUpload,
        ],
        toolbar: {
            items: [
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'bulletedList', 'numberedList', 'indent', 'outdent', '|',
                'link', 'blockQuote', 'uploadImage', 'insertTable', '|',
                'undo', 'redo',
            ],
            shouldNotGroupWhenFull: true,
        },
        simpleUpload: {
            uploadUrl: '<?= SITE_URL ?>/admin/upload-image.php',
            withCredentials: true,
        },
        image: {
            toolbar: [
                'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
                'toggleImageCaption', 'imageTextAlternative', '|',
                'resizeImage',
            ]
        },
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
        },
        language: 'ja'
    };

    let bodyEditor = null;

    ClassicEditor.create(document.querySelector('.wysiwyg'), editorConfig)
        .then(editor => { bodyEditor = editor; })
        .catch(err => console.error(err));

    document.querySelector('form').addEventListener('submit', function() {
        if (bodyEditor) {
            document.querySelector('.wysiwyg').value = bodyEditor.getData();
        }
    });
    </script>
</body>
</html>
