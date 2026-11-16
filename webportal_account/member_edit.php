<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$me = current_user_id();
$id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$st->execute([$id]);
$user = $st->fetch();
if (!$user) { flash('error', 'Member not found.'); redirect('/members.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    /* ---------- UPDATE this record ---------- */
    if ($action === 'update') {
        [$data, $errors] = validate_person($_POST);

        if (!$errors) {
            /* "last_updated" timestamp and "updated_by" (last_updated_by) both
               change here, to the CURRENT logged-in user ($me), not the record
               owner — this is the proof that the change was tracked. */
            $pdo->prepare(
                'UPDATE users SET first_name=?, last_name=?, age=?, address=?, contact_number=?, email=?,
                        updated_by=?, last_updated=NOW() WHERE user_id=?'
            )->execute([
                $data['first_name'], $data['last_name'], (int)$data['age'],
                $data['address'] !== '' ? $data['address'] : null,
                $data['contact_number'] !== '' ? $data['contact_number'] : null,
                $data['email'] !== '' ? $data['email'] : null,
                $me, $id,
            ]);
            log_activity('UPDATE', 'users', $id, "Updated member '{$user['username']}'");
            flash('success', 'Member updated.');
            redirect('/member_edit.php?id=' . $id);
        }
        foreach ($errors as $err) flash('error', $err);
        $user = array_merge($user, $_POST);

    /* ---------- DELETE this record (admin-style, any member) ---------- */
    } elseif ($action === 'delete') {
        if ($id === $me) {
            flash('error', 'Use "Close account" on your own profile to delete your own record.');
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM accounts WHERE seller_id = ?');
            $st->execute([$id]);
            $n = (int)$st->fetchColumn();
            log_activity('DELETE', 'users', $id, "Deleted member '{$user['username']}' ($n account record(s) removed)");
            $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$id]);   // cascades to their accounts + offers
            flash('success', "Member '{$user['username']}' deleted.");
            redirect('/members.php');
        }
    }
}

$pageTitle = 'Edit member';
include __DIR__ . '/includes/header.php';
$v = $user;
?>
<a class="muted" href="<?= BASE_URL ?>/members.php">&larr; Back to members</a>
<h1>Edit member: <?= e($user['first_name'] . ' ' . $user['last_name']) ?></h1>

<div class="glass card meta-box">
    <span>Username: <strong><?= e($user['username']) ?></strong></span>
    <span>Date added: <strong><?= e($user['date_added']) ?></strong></span>
    <span>Last updated: <strong><?= e($user['last_updated']) ?></strong></span>
</div>

<form method="post" autocomplete="off" class="glass card form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="user_id" value="<?= $id ?>">
    <?php include __DIR__ . '/includes/person_fields.php'; ?>
    <div class="full row">
        <button class="btn">Save changes</button>
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/members.php">Cancel</a>
    </div>
</form>

<?php if ($id !== $me): ?>
<section class="glass card danger-zone">
    <h2>Delete this member</h2>
    <p class="muted">This also deletes all of their account records and offers.</p>
    <form method="post" data-confirm="Delete this member and all their records permanently?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" value="<?= $id ?>">
        <button class="btn btn-danger">Delete member</button>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
