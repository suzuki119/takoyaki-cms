<?php
// ===================================================
//  作品フォームの共通部品（post-new.php / post-edit.php から使う）
//
//  このファイルは関数を定義するだけで、読み込んだ時点では何も出力しない。
//    require_once __DIR__ . '/_post-form.php';
//    admin_header('作品 新規作成', post_form_head());
//    render_post_form($post, $sections, $categories, $selected_ids, $tags_str, true);
//    admin_footer(post_form_script());
// ===================================================

/**
 * CKEditor 5 を <head> に読み込むためのタグ。
 * admin_header() の第2引数に渡す。
 */
function post_form_head(): string
{
    return <<<'HTML'
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
    .ck-editor__editable { min-height: 200px; }
</style>
HTML;
}

/**
 * POST された作品フォームの内容を正規化して返す。
 *
 * @return array {
 *     fields     array  posts テーブルに入れる値
 *     sections   array  [['title' => ..., 'body' => ...], ...]
 *     categories array  カテゴリIDの配列
 *     tags       string カンマ区切りのタグ
 *     error      string 入力エラー（無ければ空文字）
 * }
 */
function collect_post_input(): array
{
    $error = '';

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        $error = 'タイトルは必須です。';
    } elseif (mb_strlen($title) > 255) {
        $error = 'タイトルは255文字以内にしてください。';
    }

    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    // 公開日時は形式を検証してから使う（不正な値をそのままSQLへ渡さない）
    $published_at    = null;
    $published_at_in = trim((string)($_POST['published_at'] ?? ''));
    if ($status === 'published' && $published_at_in !== '') {
        $published_at = parse_datetime_local($published_at_in);
        if ($published_at === null && $error === '') {
            $error = '公開日時の形式が正しくありません。';
        }
    }

    $external_url = trim((string)($_POST['external_url'] ?? ''));
    if ($external_url !== '' && !filter_var($external_url, FILTER_VALIDATE_URL)) {
        $error = $error ?: '外部リンクのURLが正しくありません。';
    }

    $video_url = trim((string)($_POST['video_url'] ?? ''));
    if ($video_url !== '' && video_embed_url($video_url) === null) {
        $error = $error ?: '動画URLは YouTube または Vimeo のURLを入力してください。';
    }

    $excerpt = trim((string)($_POST['excerpt'] ?? ''));

    // セクション（section_title[] と section_body[] の並び順で対応させる）
    $titles   = is_array($_POST['section_title'] ?? null) ? $_POST['section_title'] : [];
    $bodies   = is_array($_POST['section_body']  ?? null) ? $_POST['section_body']  : [];
    $sections = [];
    foreach ($titles as $i => $t) {
        $sections[] = [
            'title' => (string)$t,
            'body'  => (string)($bodies[$i] ?? ''),
        ];
    }

    return [
        'fields' => [
            'title'            => $title,
            'slug_input'       => trim((string)($_POST['slug'] ?? '')),
            'excerpt'          => $excerpt !== '' ? $excerpt : null,
            'status'           => $status,
            'published_at'     => $published_at,
            'published_at_raw' => $published_at_in,
            'period'           => trim((string)($_POST['period'] ?? '')) ?: null,
            'type'             => trim((string)($_POST['type'] ?? '')) ?: null,
            'external_url'     => $external_url !== '' ? $external_url : null,
            'video_url'        => $video_url !== '' ? $video_url : null,
        ],
        'sections'   => $sections,
        'categories' => is_array($_POST['category_ids'] ?? null) ? $_POST['category_ids'] : [],
        'tags'       => trim((string)($_POST['tags'] ?? '')),
        'error'      => $error,
    ];
}

/**
 * 保存後の遷移先を決める。
 * 「保存して公開ページを表示」なら公開URL（未公開ならプレビュー）へ。
 */
function post_save_redirect(int $post_id): string
{
    if (($_POST['post_action'] ?? '') !== 'save_view') {
        return SITE_URL . '/admin/index.php';
    }

    $stmt = db()->prepare('SELECT id, slug, status, published_at FROM posts WHERE id = :id');
    $stmt->execute([':id' => $post_id]);
    $row = $stmt->fetch();

    return ($row && is_post_live($row))
        ? public_post_url($row)
        : SITE_URL . '/preview.php?id=' . $post_id;
}

/**
 * 作品フォームを出力する。
 *
 * @param array  $post                  表示する値（title / slug / excerpt / thumbnail / status /
 *                                      published_at / period / type / external_url / video_url / id）
 * @param array  $sections              [['title' => ..., 'body' => ...], ...]
 * @param array  $categories            全カテゴリ
 * @param array  $selected_category_ids 選択済みカテゴリIDの配列
 * @param string $tags_str              カンマ区切りのタグ
 * @param bool   $is_new                新規作成画面かどうか
 */
function render_post_form(
    array $post,
    array $sections,
    array $categories,
    array $selected_category_ids,
    string $tags_str,
    bool $is_new
): void {
    // datetime-local 用に 'Y-m-d H:i:s' → 'Y-m-dTH:i' へ
    $pub_val = '';
    if (!empty($post['published_at'])) {
        $pub_val = str_replace(' ', 'T', substr((string)$post['published_at'], 0, 16));
    }

    // セクションが1つも無いときは空行を1つ出しておく
    if (empty($sections)) {
        $sections = [['title' => '', 'body' => '']];
    }

    $selected_ids = array_map('intval', $selected_category_ids);
?>

<div class="card">
    <form method="post" enctype="multipart/form-data" id="post-form">
        <?= csrf_field() ?>

        <h2 class="section-title" style="margin-top:0;">基本情報</h2>

        <label class="field">タイトル
            <input type="text" name="title" value="<?= h($post['title'] ?? '') ?>" required maxlength="255">
        </label>

        <label class="field">slug（URL用識別子、任意）
            <input type="text" name="slug" value="<?= h($post['slug'] ?? '') ?>" placeholder="例: my-portfolio-site">
            <p class="field-hint">
                空欄ならタイトルから自動生成。日本語のみのタイトルは <code>work-{ID}</code> になります。
                既に使われている場合は <code>-2</code> / <code>-3</code> が自動で付きます。
            </p>
        </label>

        <label class="field">概要（任意、最大500文字）
            <textarea name="excerpt" maxlength="500" style="height:80px;"><?= h($post['excerpt'] ?? '') ?></textarea>
            <p class="field-hint">一覧カードや &lt;meta name="description"&gt; に使う短い説明文。</p>
        </label>

        <div class="field-row">
            <label class="field">制作期間
                <input type="text" name="period" value="<?= h($post['period'] ?? '') ?>" placeholder="例: 2025.06 – 08" maxlength="100">
            </label>

            <label class="field">種別
                <input type="text" name="type" value="<?= h($post['type'] ?? '') ?>" placeholder="例: 個人制作 / チーム制作" maxlength="100">
            </label>
        </div>

        <label class="field">外部リンク（任意）
            <input type="url" name="external_url" value="<?= h($post['external_url'] ?? '') ?>" placeholder="https://example.com">
            <p class="field-hint">公開中のサイトや GitHub リポジトリへのリンク。</p>
        </label>

        <label class="field">動画URL（任意）
            <input type="url" name="video_url" value="<?= h($post['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
            <p class="field-hint">
                YouTube / Vimeo に対応。指定するとサムネイル画像より優先して作品ページの先頭に表示されます。
            </p>
        </label>

        <label class="field">サムネイル画像
            <?php if (!empty($post['thumbnail'])): ?>
                <div class="thumbnail-preview">
                    <img src="<?= h(post_thumb_url($post['thumbnail'])) ?>" alt="現在のサムネイル">
                    <label>
                        <input type="checkbox" name="delete_thumbnail" value="1">
                        この画像を削除する
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="thumbnail" accept="image/*">
        </label>

        <h2 class="section-title">分類</h2>

        <fieldset class="field">
            <legend>カテゴリー（複数選択可）</legend>
            <?php if (empty($categories)): ?>
                <p class="field-hint" style="margin-top:0;">
                    カテゴリがまだありません。<a href="<?= SITE_URL ?>/admin/categories.php">カテゴリ管理</a>から追加できます。
                </p>
            <?php else: ?>
                <div class="checkbox-grid">
                    <?php foreach ($categories as $category): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="category_ids[]" value="<?= h($category['id']) ?>"
                                <?= in_array((int)$category['id'], $selected_ids, true) ? 'checked' : '' ?>>
                            <?= h($category['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <label class="field">使用技術タグ（カンマ区切り）
            <input type="text" name="tags" value="<?= h($tags_str) ?>" placeholder="例: PHP, MySQL, SCSS">
            <p class="field-hint">未登録のタグ名は自動作成されます。空にすると全タグが外れます。</p>
        </label>

        <h2 class="section-title">本文セクション</h2>
        <p class="field-hint" style="margin-top:0;">
            「見出し＋本文」の組を並べて作品ページを構成します。見出しだけ・本文だけでも保存できます。
            両方とも空の行は保存されません。
        </p>

        <div id="sections">
            <?php foreach ($sections as $section): ?>
                <div class="section-row">
                    <div class="section-row-head">
                        <span class="section-row-num"></span>
                        <input type="text" name="section_title[]" class="section-title-input"
                               value="<?= h($section['title'] ?? '') ?>" placeholder="見出し（例: 制作の背景）">
                        <div class="section-row-actions">
                            <button type="button" class="sort-btn" data-move="up"   title="上へ">↑</button>
                            <button type="button" class="sort-btn" data-move="down" title="下へ">↓</button>
                            <button type="button" class="btn-link btn-danger" data-remove="1">削除</button>
                        </div>
                    </div>
                    <?php // 本文は必ず h() でエスケープする（未エスケープだと </textarea> で抜けられる） ?>
                    <textarea name="section_body[]" class="wysiwyg"><?= h($section['body'] ?? '') ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-secondary btn-sm" id="add-section" style="margin-top:12px;">
            + セクションを追加
        </button>

        <h2 class="section-title">公開設定</h2>

        <div class="field-row">
            <label class="field">ステータス
                <select name="status">
                    <option value="draft"     <?= ($post['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>下書き</option>
                    <option value="published" <?= ($post['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>公開</option>
                </select>
            </label>

            <label class="field">公開日時
                <input type="datetime-local" name="published_at" value="<?= h($pub_val) ?>">
                <p class="field-hint">「公開」のときのみ有効。未来日時なら予約公開、空欄なら保存時刻。</p>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $is_new ? '保存する' : '更新する' ?></button>
            <button type="submit" name="post_action" value="save_view" class="btn btn-secondary">保存して公開ページを表示 ↗</button>
            <?php if (!$is_new): ?>
                <a class="btn-link" href="<?= SITE_URL ?>/preview.php?id=<?= h($post['id']) ?>" target="_blank">プレビュー</a>
            <?php endif; ?>
            <a class="btn-link" href="<?= SITE_URL ?>/admin/index.php">← 一覧へ戻る</a>
        </div>
    </form>
</div>

<?php
}

/**
 * フォームのJS（セクションの追加・削除・並び替え + CKEditor）。
 * admin_footer() の第1引数に渡す。
 */
function post_form_script(): string
{
    $script = <<<'HTML'
<script type="module">
import {
    ClassicEditor, Essentials,
    Bold, Italic, Underline, Strikethrough,
    Heading, Paragraph, List, Link, BlockQuote,
    Indent, IndentBlock, SimpleUploadAdapter,
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
        uploadUrl: '__SITE_URL__/admin/upload-image.php',
        withCredentials: true,
    },
    image: {
        toolbar: [
            'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
            'toggleImageCaption', 'imageTextAlternative', '|',
            'resizeImage',
        ],
    },
    table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
    language: 'ja',
};

// textarea 要素 → CKEditor インスタンスの対応表
const editors     = new Map();
const sectionsEl  = document.getElementById('sections');
const formEl      = document.getElementById('post-form');

function attachEditor(textarea) {
    return ClassicEditor.create(textarea, editorConfig)
        .then(editor => { editors.set(textarea, editor); return editor; })
        .catch(err => console.error(err));
}

function detachEditor(textarea) {
    const editor = editors.get(textarea);
    if (!editor) return Promise.resolve();
    editors.delete(textarea);
    return editor.destroy().catch(err => console.error(err));
}

function renumber() {
    const rows = sectionsEl.querySelectorAll('.section-row');
    rows.forEach((row, i) => {
        const num = row.querySelector('.section-row-num');
        if (num) num.textContent = String(i + 1);
        // 1行しか無いときは削除ボタンを隠す（0行になるのを防ぐ）
        const del = row.querySelector('[data-remove]');
        if (del) del.style.visibility = rows.length > 1 ? 'visible' : 'hidden';
    });
}

// --- 初期表示ぶんのエディタを作る ---
sectionsEl.querySelectorAll('textarea.wysiwyg').forEach(attachEditor);
renumber();

// --- セクション追加 ---
document.getElementById('add-section').addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'section-row';
    row.innerHTML =
        '<div class="section-row-head">' +
            '<span class="section-row-num"></span>' +
            '<input type="text" name="section_title[]" class="section-title-input" placeholder="見出し（例: 制作の背景）">' +
            '<div class="section-row-actions">' +
                '<button type="button" class="sort-btn" data-move="up" title="上へ">↑</button>' +
                '<button type="button" class="sort-btn" data-move="down" title="下へ">↓</button>' +
                '<button type="button" class="btn-link btn-danger" data-remove="1">削除</button>' +
            '</div>' +
        '</div>' +
        '<textarea name="section_body[]" class="wysiwyg"></textarea>';
    sectionsEl.appendChild(row);
    attachEditor(row.querySelector('textarea.wysiwyg'));
    renumber();
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

// --- 削除・並び替え（イベント委譲）---
sectionsEl.addEventListener('click', (e) => {
    const row = e.target.closest('.section-row');
    if (!row) return;

    if (e.target.matches('[data-remove]')) {
        if (!confirm('このセクションを削除しますか？')) return;
        const textarea = row.querySelector('textarea.wysiwyg');
        detachEditor(textarea).then(() => { row.remove(); renumber(); });
        return;
    }

    const dir = e.target.dataset.move;
    if (!dir) return;
    if (dir === 'up'   && !row.previousElementSibling) return;
    if (dir === 'down' && !row.nextElementSibling)     return;

    // CKEditor は DOM ごと動かすと壊れるので、外して移動してから作り直す
    const textarea = row.querySelector('textarea.wysiwyg');
    const editor   = editors.get(textarea);
    const data     = editor ? editor.getData() : textarea.value;

    detachEditor(textarea).then(() => {
        textarea.value = data;
        if (dir === 'up') {
            sectionsEl.insertBefore(row, row.previousElementSibling);
        } else {
            sectionsEl.insertBefore(row.nextElementSibling, row);
        }
        attachEditor(textarea);
        renumber();
    });
});

// --- 送信前に全エディタの内容を textarea へ書き戻す ---
formEl.addEventListener('submit', () => {
    editors.forEach((editor, textarea) => { textarea.value = editor.getData(); });
});
</script>
HTML;

    return str_replace('__SITE_URL__', SITE_URL, $script);
}
