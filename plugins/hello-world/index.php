<?php
// ===================================================
//  Hello World プラグイン
//  Takoyaki CMS の拡張ポイントの使用例。
//  - ショートコード [hello] / [hello name="名前"]
//  - フィルター 'the_content' で本文末尾にフッターを追加
//  - アクション 'post.save' で監査ログにメッセージを残す
// ===================================================

// ショートコード： [hello name="..."] を「Hello, <name>!」に変換
add_shortcode('hello', function (array $attrs): string {
    $name = $attrs['name'] ?? 'World';
    return 'Hello, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!';
});

// フィルター： 本文末尾に「✨ Hello World plugin」を付ける
add_filter('the_content', function (string $content): string {
    return $content . "\n<p style=\"color:#888;font-size:.85em;margin-top:32px;\">✨ Hello World plugin enabled.</p>";
}, 20);

// アクション： 記事保存時に監査ログにメッセージを残す
add_action('post.save', function (array $post): void {
    if (function_exists('log_action')) {
        log_action('plugin.hello_world.post_save', 'post', (int)($post['id'] ?? 0), 'Hello World プラグインが発火');
    }
});
