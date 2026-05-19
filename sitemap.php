<?php
// ===================================================
//  sitemap.xml
//  公開中の記事を XML サイトマップ形式で出力する。
//  検索エンジンに送信する場合は、このURLを Search Console 等に登録。
//
//  個別記事URLは下のコールバック $post_url で生成。
//  自分のサイトのURL構造に合わせて変更してください。
// ===================================================
require_once __DIR__ . '/config.php';

// ---- サイトトップURL（適宜変更してください） ----
$site_top_url = SITE_URL . '/samples/index.php';

// ---- 個別記事URLの組み立て方（自分のサイトに合わせて変更してください） ----
$post_url = function (array $post): string {
    return SITE_URL . '/samples/single.php?id=' . (int)$post['id'];
};

$posts = get_posts([
    'order_by' => 'updated_at',
    'order'    => 'DESC',
]);

function xml_escape(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * 日時を W3C Datetime (YYYY-MM-DD) に変換（sitemap.xml仕様）
 */
function w3c_date(?string $date): string
{
    return date('Y-m-d', $date ? strtotime($date) : time());
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= xml_escape($site_top_url) ?></loc>
        <lastmod><?= w3c_date(null) ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <?php foreach ($posts as $post): ?>
        <url>
            <loc><?= xml_escape($post_url($post)) ?></loc>
            <lastmod><?= w3c_date($post['updated_at'] ?? $post['created_at']) ?></lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>
</urlset>
