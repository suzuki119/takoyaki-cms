# Plugins

このディレクトリ配下に Takoyaki CMS のプラグインを配置します。

## プラグインの構成

各プラグインはサブディレクトリで管理します：

```
plugins/
└── your-plugin/
    ├── index.php      # エントリーポイント（必須）
    └── plugin.json    # メタデータ（任意）
```

`plugin.json` の例：

```json
{
    "name": "Your Plugin",
    "description": "プラグインの説明",
    "version": "1.0.0"
}
```

## 有効化

プラグインは管理画面の **「プラグイン」** メニュー（admin限定）で有効化します。
有効化されたプラグインは `config.php` 読み込み時に自動でロードされます。

## 利用できる拡張ポイント

### アクションフック

```php
add_action('post.save', function (array $post) {
    // 記事が作成 or 更新されたとき
});

add_action('post.delete', function (int $post_id) {
    // 記事が削除されたとき
});

add_action('login.success', function (array $user) {
    // ログイン成功時
});
```

### フィルター

```php
add_filter('the_content', function (string $content): string {
    return $content . '<p>フッター追加</p>';
}, 20);

add_filter('the_title', function (string $title): string {
    return mb_strtoupper($title);
});
```

### ショートコード

```php
add_shortcode('today', fn($attrs) => date('Y-m-d'));
add_shortcode('greet', fn($attrs) => 'こんにちは、' . ($attrs['name'] ?? 'みなさん'));

// 本文中の [today] や [greet name="太郎"] が展開される
```

ショートコードを展開するには公開ページ側で `do_shortcodes($post['body'])` を呼びます。
`samples/single.php` が実例です。

## サンプルプラグイン

[`hello-world/`](hello-world/) — ショートコード・フィルター・アクションを全種類使う最小サンプル。
