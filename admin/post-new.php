<?php
// ===================================================
//  記事新規作成
// ===================================================
require_once '../config.php';
require_login();

$pdo   = db();
$error = '';

// カテゴリ一覧を取得
$c_stmt = $pdo->prepare('SELECT * FROM categories ORDER BY id ASC');
$c_stmt->execute();
$categories = $c_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title       = trim($_POST['title']  ?? '');
    $body        = $_POST['body']        ?? '';
    $status      = $_POST['status']      ?? 'draft';
    $category_id = $_POST['category_id'] ?? '';

    if ($title === '') {
        $error = 'タイトルは必須です。';
    } else {
        // ===================================================
        //  画像アップロード処理
        // ===================================================
        $thumbnail = null;

        if (!empty($_FILES['thumbnail']['name'])) {
            $file          = $_FILES['thumbnail'];
            $ext           = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $actual_mime   = mime_content_type($file['tmp_name']);

            if (!in_array($ext, $allowed_ext) || !in_array($actual_mime, $allowed_mimes)) {
                $error = '画像はjpg・png・gif・webpのみ使用できます。';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = '画像サイズは2MB以下にしてください。';
            } else {
                $filename = uniqid() . '.' . $ext;
                $savePath = UPLOAD_DIR . $filename;

                if (move_uploaded_file($file['tmp_name'], $savePath)) {
                    $thumbnail = $filename;
                } else {
                    $error = '画像の保存に失敗しました。';
                }
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare(
                'INSERT INTO posts (title, body, thumbnail, status, author_id)
                 VALUES (:title, :body, :thumbnail, :status, :author_id)'
            );
            $stmt->execute([
                ':title'     => $title,
                ':body'      => $body,
                ':thumbnail' => $thumbnail,
                ':status'    => $status,
                ':author_id' => $_SESSION['user_id'],
            ]);

            $newPostId = $pdo->lastInsertId();

            if (!empty($category_id)) {
                $pc_stmt = $pdo->prepare(
                    'INSERT INTO post_categories (post_id, category_id) VALUES (:post_id, :category_id)'
                );
                $pc_stmt->execute([
                    ':post_id'     => $newPostId,
                    ':category_id' => $category_id,
                ]);
            }

            header('Location: ' . SITE_URL . '/admin/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>記事新規作成 | 管理画面</title>
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
        .ck-editor__editable { min-height: 300px; }
    </style>
</head>
<body>
    <h1>記事新規作成</h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label>タイトル
            <input type="text" name="title" value="<?= h($_POST['title'] ?? '') ?>" required>
        </label>

        <label>本文
            <textarea name="body" class="wysiwyg"><?= h($_POST['body'] ?? '') ?></textarea>
        </label>

        <label>サムネイル画像（任意）
            <input type="file" name="thumbnail" accept="image/*">
        </label>

        <label>ステータス
            <select name="status">
                <option value="draft"     <?= ($_POST['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>下書き</option>
                <option value="published" <?= ($_POST['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>公開</option>
            </select>
        </label>

        <label>カテゴリー
            <select name="category_id">
                <option value="">選択してください</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h($category['id']) ?>"
                        <?= ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="actions">
            <button type="submit">保存する</button>
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
