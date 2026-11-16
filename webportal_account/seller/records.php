<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['account_id'] ?? 0);

    $st = $pdo->prepare('SELECT status FROM accounts WHERE account_id = ? AND seller_id = ?');
    $st->execute([$id, $uid]);
    $rec = $st->fetch();

    if (!$rec) {
        flash('error', 'Record not found.');
    } elseif ($action === 'publish' && $rec['status'] === 'draft') {
        $pdo->prepare("UPDATE accounts SET status='available', trade_posted=NOW(), updated_by=?, last_updated=NOW() WHERE account_id=? AND seller_id=?")
            ->execute([$uid, $id, $uid]);
        log_activity('UPDATE', 'accounts', $id, 'Published record to the market');
        flash('success', 'Published. The account is now on the market.');
    } elseif ($action === 'unpublish' && $rec['status'] === 'available') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE accounts SET status='draft', trade_posted=NULL, updated_by=?, last_updated=NOW() WHERE account_id=? AND seller_id=?")
            ->execute([$uid, $id, $uid]);
        $pdo->prepare("UPDATE offers SET status='declined' WHERE account_id=? AND status='pending'")->execute([$id]);
        $pdo->commit();
        log_activity('UPDATE', 'accounts', $id, 'Removed record from the market');
        flash('success', 'Removed from the market. Pending offers were declined.');
    } elseif ($action === 'mark_sold' && $rec['status'] === 'available') {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $st = $pdo->prepare("SELECT 1 FROM offers WHERE offer_id=? AND account_id=? AND status='pending'");
        $st->execute([$offerId, $id]);
        if (!$st->fetchColumn()) {
            flash('error', 'You can only mark an account as sold after a buyer sends an offer.');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE offers SET status='accepted' WHERE offer_id=?")->execute([$offerId]);
            $pdo->prepare("UPDATE offers SET status='declined' WHERE account_id=? AND status='pending'")->execute([$id]);
            $pdo->prepare("UPDATE accounts SET status='sold', updated_by=?, last_updated=NOW() WHERE account_id=? AND seller_id=?")
                ->execute([$uid, $id, $uid]);
            $pdo->commit();
            log_activity('UPDATE', 'accounts', $id, 'Marked record as sold');
            flash('success', 'Marked as sold.');
        }
    } elseif ($action === 'reopen' && $rec['status'] === 'sold') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE offers SET status='declined' WHERE account_id=? AND status='accepted'")->execute([$id]);
        $pdo->prepare("UPDATE accounts SET status='available', updated_by=?, last_updated=NOW() WHERE account_id=? AND seller_id=?")
            ->execute([$uid, $id, $uid]);
        $pdo->commit();
        log_activity('UPDATE', 'accounts', $id, 'Marked record as available again');
        flash('success', 'Marked as available again.');
    } elseif ($action === 'delete') {
        log_activity('DELETE', 'accounts', $id, 'Deleted record');
        $pdo->prepare('DELETE FROM accounts WHERE account_id=? AND seller_id=?')->execute([$id, $uid]);
        flash('success', 'Record deleted.');
    } else {
        flash('error', 'That action is not available for this record.');
    }
    redirect('/seller/records.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}

/* ---------- SEARCH (children entity) ---------- */
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT a.account_id, g.game_name, a.pseudonym, a.selling_price, a.account_level, a.rank_tier,
               a.status, a.date_added, a.last_updated,
               ab.username AS added_by_name, ub.username AS updated_by_name
        FROM accounts a
        JOIN games g ON g.game_id = a.game_id
        LEFT JOIN users ab ON ab.user_id = a.added_by
        LEFT JOIN users ub ON ub.user_id = a.updated_by
        WHERE a.seller_id = ?';
$params = [$uid];
if ($q !== '') {
    $sql .= ' AND (a.pseudonym LIKE ? OR g.game_name LIKE ? OR a.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY a.last_updated DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$records = $st->fetchAll();

$st = $pdo->prepare(
    "SELECT o.offer_id, o.account_id, o.status, u.username
     FROM offers o
     JOIN accounts a ON a.account_id = o.account_id
     JOIN users u ON u.user_id = o.buyer_id
     WHERE a.seller_id = ? AND o.status IN ('pending','accepted') ORDER BY o.created_at"
);
$st->execute([$uid]);
$offers = [];
foreach ($st->fetchAll() as $o) $offers[$o['account_id']][] = $o;

$pageTitle = 'My Records';
include __DIR__ . '/../includes/header.php';
?>
<div class="row between">
    <div>
        <h1>My records</h1>
        <p class="muted">Search, then update, publish, or delete any of your records.</p>
    </div>
    <a class="btn" href="<?= BASE_URL ?>/seller/record_form.php">Add record</a>
</div>

<!-- SEARCH: children entity -->
<form method="get" class="glass card filters">
    <label class="full">Search by pseudonym, game, or description
        <input type="text" name="q" placeholder="e.g. mythic or genshin" value="<?= e($q) ?>">
    </label>
    <button class="btn">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= BASE_URL ?>/seller/records.php">Clear</a><?php endif; ?>
</form>

<?php if ($q !== ''): ?>
    <p class="muted">Found <?= count($records) ?> result(s) for "<?= e($q) ?>".</p>
<?php endif; ?>

<?php if (!$records): ?>
    <div class="glass card center"><p><?= $q !== '' ? 'No records match your search.' : 'No records yet. Add your first account to start keeping track.' ?></p></div>
<?php else: ?>
<div class="glass card table-wrap">
<table>
    <thead>
        <tr><th>Pseudonym</th><th>Game</th><th>Price</th><th>Level</th><th>Rank</th><th>Status</th><th>Offers</th>
            <th>Added by</th><th>Date added</th><th>Last updated by</th><th>Last updated</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($records as $r):
        $meta = game_meta($r['game_name']);
        $list = $offers[$r['account_id']] ?? [];
        $pending  = array_values(array_filter($list, fn($o) => $o['status'] === 'pending'));
        $accepted = array_values(array_filter($list, fn($o) => $o['status'] === 'accepted'));
    ?>
        <tr>
            <td><?= e($r['pseudonym']) ?></td>
            <td><?= e($r['game_name']) ?></td>
            <td><?= peso($r['selling_price']) ?></td>
            <td><?= e($meta['level_label'] ?? 'Level') ?> <?= (int)$r['account_level'] ?></td>
            <td><?= e($r['rank_tier'] ?? '-') ?></td>
            <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td>
                <?php if ($r['status'] === 'sold' && $accepted): ?>
                    Sold to <?= e($accepted[0]['username']) ?>
                <?php elseif ($pending): ?>
                    <?= count($pending) ?> pending
                <?php else: ?>-<?php endif; ?>
            </td>
            <td><?= e(who($r['added_by_name'])) ?></td>
            <td><?= e($r['date_added']) ?></td>
            <td><?= e(who($r['updated_by_name'])) ?></td>
            <td><?= e($r['last_updated']) ?></td>
            <td class="actions">
                <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/seller/record_form.php?id=<?= (int)$r['account_id'] ?>">Edit</a>
                <?php if ($r['status'] === 'draft'): ?>
                    <?= post_button('publish', (int)$r['account_id'], 'Publish') ?>
                <?php elseif ($r['status'] === 'available'): ?>
                    <?php if ($pending): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="mark_sold">
                            <input type="hidden" name="account_id" value="<?= (int)$r['account_id'] ?>">
                            <select name="offer_id" aria-label="Buyer">
                                <?php foreach ($pending as $o): ?>
                                    <option value="<?= (int)$o['offer_id'] ?>"><?= e($o['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm">Mark sold</button>
                        </form>
                    <?php endif; ?>
                    <?= post_button('unpublish', (int)$r['account_id'], 'Unpublish', 'btn btn-sm btn-ghost', 'Remove this listing from the market? Pending offers will be declined.') ?>
                <?php elseif ($r['status'] === 'sold'): ?>
                    <?= post_button('reopen', (int)$r['account_id'], 'Mark available', 'btn btn-sm btn-ghost') ?>
                <?php endif; ?>
                <?= post_button('delete', (int)$r['account_id'], 'Delete', 'btn btn-sm btn-danger', 'Delete this record permanently?') ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
