<?php
// ===================================================
//  RSS 2.0 フィード
//  公開中の記事を最新50件まで XML で出力する。
//
//  各記事のURLは下のコールバック $post_url で生成しています。
//  自分のサイトのURL構造に合わせて変更してください。
// ===================================================
require_once __DIR__ . '/config.php';

// ---- サイト情報（適宜変更してください） ----
$feed_title       = 'Takoyaki CMS Site';
$feed_description = 'Powered by Takoyaki CMS';
$feed_language    = 'ja';

// ---- 個別記事URLの組み立て方（自分のサイトに合わせて変更してください） ----
$post_url = function (array $post): string {
    // 既定: SITE_URL/samples/single.php?id=N
    return SITE_URL . '/samples/single.php?id=' . (int)$post['id'];
};

$posts = get_posts([
    'limit'    => 50,
    'order_by' => 'published_at',
    'order'    => 'DESC',
]);

/**
 * XML 用エスケープ
 */
function xml_escape(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * 日時を RFC 2822 形式に変換（RSS仕様）
 */
function rfc2822_date(?string $date): string
{
    return date(DATE_RSS, $date ? strtotime($date) : time());
}

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
    <channel>
        <title><?= xml_escape($feed_title) ?></title>
        <link><?= xml_escape(SITE_URL) ?></link>
        <description><?= xml_escape($feed_description) ?></description>
        <language><?= xml_escape($feed_language) ?></language>
        <lastBuildDate><?= rfc2822_date(null) ?></lastBuildDate>
        <?php foreach ($posts as $post): ?>
            <?php $url = $post_url($post); ?>
            <item>
                <title><?= xml_escape($post['title']) ?></title>
                <link><?= xml_escape($url) ?></link>
                <guid isPermaLink="true"><?= xml_escape($url) ?></guid>
                <pubDate><?= rfc2822_date($post['published_at'] ?? $post['created_at']) ?></pubDate>
                <?php if (!empty($post['excerpt'])): ?>
                    <description><?= xml_escape($post['excerpt']) ?></description>
                <?php else: ?>
                    <description><?= xml_escape(mb_substr(strip_tags($post['body'] ?? ''), 0, 200)) ?></description>
                <?php endif; ?>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
