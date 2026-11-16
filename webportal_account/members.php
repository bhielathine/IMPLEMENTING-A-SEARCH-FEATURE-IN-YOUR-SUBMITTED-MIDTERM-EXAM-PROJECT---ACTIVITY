<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user_id();

/* ---------- SEARCH (parent entity) ---------- */
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT u.user_id, u.first_name, u.last_name, u.username, u.age, u.added_by, u.date_added, u.last_updated,
               ab.username AS added_by_name, ub.username AS updated_by_name
        FROM users u
        LEFT JOIN users ab ON ab.user_id = u.added_by
        LEFT JOIN users ub ON ub.user_id = u.updated_by';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY u.user_id';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Members';
include __DIR__ . '/includes/header.php';
?>
<div class="row between">
    <div>
        <h1>Members</h1>
        <p class="muted">Search by name or username, then update or close any record.</p>
    </div>
    <a class="btn" href="<?= BASE_URL ?>/member_form.php">Add member</a>
</div>

<!-- SEARCH: parent entity -->
<form method="get" class="glass card filters">
    <label class="full">Search by first name, last name, or username
        <input type="text" name="q" placeholder="e.g. juan or jdelacruz" value="<?= e($q) ?>">
    </label>
    <button class="btn">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= BASE_URL ?>/members.php">Clear</a><?php endif; ?>
</form>

<?php if ($q !== ''): ?>
    <p class="muted">Found <?= count($rows) ?> result(s) for "<?= e($q) ?>".</p>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="glass card center"><p>No members found.</p></div>
<?php else: ?>
<div class="glass card table-wrap">
<table>
    <thead>
        <tr><th>ID</th><th>Name</th><th>Username</th><th>Age</th><th>Added by</th>
            <th>Date added</th><th>Last updated by</th><th>Last updated</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $self = (int)$r['user_id'] === $me; ?>
        <tr>
            <td><?= (int)$r['user_id'] ?></td>
            <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?><?= $self ? ' <span class="muted small">(you)</span>' : '' ?></td>
            <td><?= e($r['username']) ?></td>
            <td><?= (int)$r['age'] ?></td>
            <td><?= (int)$r['added_by'] === (int)$r['user_id'] ? 'Self-registered' : e(who($r['added_by_name'])) ?></td>
            <td><?= e($r['date_added']) ?></td>
            <td><?= e(who($r['updated_by_name'])) ?></td>
            <td><?= e($r['last_updated']) ?></td>
            <td class="actions">
                <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/member_edit.php?id=<?= (int)$r['user_id'] ?>">Edit</a>
                <?php if ($self): ?>
                    <a class="btn btn-sm btn-danger" href="<?= BASE_URL ?>/profile.php#close">Close account</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
