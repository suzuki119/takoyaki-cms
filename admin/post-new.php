<?php
// ===================================================
//  記事新規作成
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_login();

$pdo   = db();
$error = '';

$c_stmt = $pdo->prepare('SELECT * FROM categories ORDER BY id ASC');
$c_stmt->execute();
$categories = $c_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title           = trim($_POST['title']        ?? '');
    $slug_input      = trim($_POST['slug']         ?? '');
    $body            = $_POST['body']              ?? '';
    $excerpt         = trim($_POST['excerpt']      ?? '');
    $status          = $_POST['status']            ?? 'draft';
    $published_at_in = trim($_POST['published_at'] ?? '');
    $category_id     = $_POST['category_id']       ?? '';

    $slug = sluggify($slug_input !== '' ? $slug_input : $title);
    $slug = $slug === '' ? null : $slug;

    $published_at = null;
    if ($status === 'published') {
        $published_at = $published_at_in !== ''
            ? str_replace('T', ' ', $published_at_in) . ':00'
            : date('Y-m-d H:i:s');
    }

    if ($title === '') {
        $error = 'タイトルは必須です。';
    } else {
        $thumbnail = null;

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
                    resize_image($savePath, IMAGE_MAX_WIDTH, $savePath);
                    resize_image($savePath, IMAGE_THUMB_WIDTH, UPLOAD_DIR . thumb_filename($filename));
                    $thumbnail = $filename;
                } else {
                    $error = '画像の保存に失敗しました。';
                }
            }
        }

        if ($error === '') {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO posts (title, slug, body, excerpt, thumbnail, status, published_at, author_id)
                     VALUES (:title, :slug, :body, :excerpt, :thumbnail, :status, :published_at, :author_id)'
                );
                $stmt->execute([
                    ':title'        => $title,
                    ':slug'         => $slug,
                    ':body'         => $body,
                    ':excerpt'      => $excerpt !== '' ? $excerpt : null,
                    ':thumbnail'    => $thumbnail,
                    ':status'       => $status,
                    ':published_at' => $published_at,
                    ':author_id'    => $_SESSION['user_id'],
                ]);
            } catch (PDOException $e) {
                $error = '記事の保存に失敗しました（slug が既存と重複している可能性があります）。';
            }
        }

        if ($error === '') {
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

$ckeditor_head = <<<'HTML'
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
    .ck-editor__editable { min-height: 300px; }
</style>
HTML;

admin_header('記事新規作成', $ckeditor_head);
?>

<h1 class="page-title">記事新規作成</h1>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label class="field">タイトル
            <input type="text" name="title" value="<?= h($_POST['title'] ?? '') ?>" required>
        </label>

        <label class="field">slug（URL用識別子、任意）
            <input type="text" name="slug" value="<?= h($_POST['slug'] ?? '') ?>" placeholder="例: my-first-post">
            <p class="field-hint">空欄の場合はタイトルから自動生成（日本語のみの場合は NULL）。英数字とハイフンのみ。</p>
        </label>

        <label class="field">本文
            <textarea name="body" class="wysiwyg"><?= h($_POST['body'] ?? '') ?></textarea>
        </label>

        <label class="field">抜粋（任意、最大500文字）
            <textarea name="excerpt" maxlength="500" style="height:80px;"><?= h($_POST['excerpt'] ?? '') ?></textarea>
            <p class="field-hint">一覧ページで表示する短い説明文。</p>
        </label>

        <label class="field">サムネイル画像（任意）
            <input type="file" name="thumbnail" accept="image/*">
        </label>

        <label class="field">ステータス
            <select name="status">
                <option value="draft"     <?= ($_POST['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>下書き</option>
                <option value="published" <?= ($_POST['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>公開</option>
            </select>
        </label>

        <label class="field">公開日時（任意）
            <input type="datetime-local" name="published_at" value="<?= h($_POST['published_at'] ?? '') ?>">
            <p class="field-hint">「公開」ステータスのときに有効。未来日付なら予約公開、空欄なら保存時刻を使用。</p>
        </label>

        <label class="field">カテゴリー
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

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存する</button>
            <a class="btn-link" href="<?= SITE_URL ?>/admin/index.php">← 一覧へ戻る</a>
        </div>
    </form>
</div>

<?php
$ckeditor_body = <<<HTML
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
        Essentials, Bold, Italic, Underline, Strikethrough, Heading, Paragraph,
        List, Link, BlockQuote, Indent, IndentBlock,
        SimpleUploadAdapter, Table, TableToolbar,
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
        uploadUrl: '\${SITE_URL_JS}/admin/upload-image.php',
        withCredentials: true,
    },
    image: {
        toolbar: [
            'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
            'toggleImageCaption', 'imageTextAlternative', '|',
            'resizeImage',
        ]
    },
    table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
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
HTML;
$ckeditor_body = str_replace('${SITE_URL_JS}', SITE_URL, $ckeditor_body);
admin_footer($ckeditor_body);
?>
