# Themes

公開ページ（`samples/index.php`, `samples/single.php`, `samples/category.php`, `router.php`）の
**CSS 部分だけ**を差し替えるテーマ機構です。HTMLテンプレートは samples/ + router.php をそのまま使います。

> もっと自由なテンプレートカスタマイズが必要になったら、テンプレートそのものを
> 差し替える「フルテーマ」方式へ拡張するのが自然な次の一歩です。

---

## ディレクトリ構成

```
themes/
├── default/
│   ├── style.css       # 既定（空＝既存のインラインCSSをそのまま使う）
│   └── theme.json
├── dark/
│   ├── style.css       # ダーク基調
│   └── theme.json
└── newspaper/
    ├── style.css       # セリフ書体・新聞風
    └── theme.json
```

`themes/<name>/style.css` が存在するディレクトリだけがテーマとして認識されます（`scandir` + `file_exists`）。

`theme.json` はメタデータ（name / description / version）。フォーマットは任意。今後の表示用に
書いておくと管理画面拡張時に活用しやすいです。

---

## 仕組み

公開ページの `<head>` 末尾に、アクティブテーマの CSS が読み込まれます:

```html
<style>/* 既存のインラインCSS（ベース） */</style>
<link rel="stylesheet" href="{SITE_URL}/themes/{active}/style.css?v={mtime}">
```

カスケード順序で**後勝ち**になるため、`themes/<name>/style.css` の指定が既定スタイルを上書きします。
強めに上書きしたい場合は `!important` を使ってください（`themes/newspaper/style.css` 参照）。

`?v={mtime}` はキャッシュ対策（CSSファイル更新後、即反映される）。

---

## アクティブテーマの切替

管理画面の「テーマ」ページ（`admin/themes.php`）でカードを選んで切替できます。
内部的には `site_settings.active_theme` に保存されます。

DBから直接設定したい場合:

```sql
UPDATE site_settings SET value = 'dark' WHERE `key` = 'active_theme';
```

---

## 新しいテーマを作る

1. `themes/<your-theme>/` ディレクトリを作成
2. `style.css` を置く（空でも認識される）
3. `theme.json` を置く（任意）
4. 管理画面の設定で選択するか、`site_settings` を直接更新

`themes/dark/style.css` を参考にすると簡単です。

---

## ヘルパー関数

`config.php` を読み込めば、テンプレートから以下が使えます:

| 関数 | 用途 |
|------|------|
| `get_themes()` | インストール済みテーマ名の配列 |
| `active_theme()` | 現在のテーマ名（実体が無ければ `default` にフォールバック） |
| `theme_css_url()` | アクティブテーマの CSS の絶対URL（mtimeクエリ付き） |
| `theme_css_tag()` | `<link rel="stylesheet" href="...">` を直接返す |
