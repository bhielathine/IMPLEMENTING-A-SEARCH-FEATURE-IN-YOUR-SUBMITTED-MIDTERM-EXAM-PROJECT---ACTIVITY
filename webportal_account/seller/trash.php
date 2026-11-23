<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['account_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'restore') {
        $pdo->prepare(
            'UPDATE accounts SET deleted_at=NULL, deleted_by=NULL, updated_by=?, last_updated=NOW()
             WHERE account_id=? AND seller_id=? AND deleted_at IS NOT NULL'
        )->execute([$uid, $id, $uid]);
        log_activity('UPDATE', 'accounts', $id, 'Restored record from recycle bin');
        flash('success', 'Record restored.');
    }
    redirect('/seller/trash.php');
}

$st = $pdo->prepare(
    'SELECT a.account_id, g.game_name, a.pseudonym, a.deleted_at, db.username AS deleted_by_name
     FROM accounts a
     JOIN games g ON g.game_id = a.game_id
     LEFT JOIN users db ON db.user_id = a.deleted_by
     WHERE a.seller_id = ? AND a.deleted_at IS NOT NULL
     ORDER BY a.deleted_at DESC'
);
$st->execute([$uid]);
$rows = $st->fetchAll();

$pageTitle = 'Recycle bin';
include __DIR__ . '/../includes/header.php';
?>
<a class="muted" href="<?= BASE_URL ?>/seller/records.php">&larr; Back to my records</a>
<h1>Recycle bin</h1>
<p class="muted">Who deleted each record, and when. Restore to bring it back to My Records.</p>

<?php if (!$rows): ?>
    <div class="glass card center"><p>Nothing in the recycle bin.</p></div>
<?php else: ?>
<div class="glass card table-wrap">
<table>
    <thead><tr><th>Pseudonym</th><th>Game</th><th>Deleted by</th><th>Deleted at</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['pseudonym']) ?></td>
            <td><?= e($r['game_name']) ?></td>
            <td><?= e(who($r['deleted_by_name'])) ?></td>
            <td><?= e($r['deleted_at']) ?></td>
            <td><?= post_button('restore', (int)$r['account_id'], 'Restore') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
