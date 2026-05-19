<?php
// ===================================================
//  監査ログ閲覧（admin限定）
// ===================================================
require_once '../config.php';
require_once __DIR__ . '/_layout.php';
require_admin();

const LOGS_PER_PAGE = 50;

$pdo = db();

$page = max(1, (int)($_GET['page'] ?? 1));

$total       = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$total_pages = max(1, (int)ceil($total / LOGS_PER_PAGE));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * LOGS_PER_PAGE;

$stmt = $pdo->prepare(
    'SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT :lim OFFSET :off'
);
$stmt->bindValue(':lim', LOGS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

admin_header('監査ログ');
?>

<h1 class="page-title">監査ログ</h1>
<p class="page-meta">計 <?= $total ?> 件の操作履歴</p>

<?php if (empty($logs)): ?>
    <p class="empty-state">監査ログはまだありません。</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:140px;">日時</th>
                <th style="width:120px;">ユーザー</th>
                <th style="width:170px;">アクション</th>
                <th style="width:90px;">対象</th>
                <th>詳細</th>
                <th style="width:130px;">IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space:nowrap; font-size:.8rem; color:#6b7280;"><?= h($log['created_at']) ?></td>
                <td><?= h($log['username'] ?? '(削除済み)') ?></td>
                <td><code style="font-size:.82rem;"><?= h($log['action']) ?></code></td>
                <td>
                    <?php if ($log['target_type']): ?>
                        <?= h($log['target_type']) ?>
                        <?php if ($log['target_id']): ?>
                            #<?= h($log['target_id']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="font-size:.85rem;"><?= h($log['details'] ?? '') ?></td>
                <td style="font-size:.8rem; color:#6b7280;"><?= h($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php $base = SITE_URL . '/admin/logs.php'; ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base ?>?page=<?= $page - 1 ?>">← 前</a>
            <?php else: ?>
                <span class="disabled">← 前</span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= $base ?>?page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base ?>?page=<?= $page + 1 ?>">次 →</a>
            <?php else: ?>
                <span class="disabled">次 →</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php admin_footer(); ?>
