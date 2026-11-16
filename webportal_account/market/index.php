<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$games  = $pdo->query('SELECT game_id, game_name FROM games ORDER BY game_id')->fetchAll();
$gameId = (int)($_GET['game'] ?? 0);
$sort   = ($_GET['sort'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$q      = trim($_GET['q'] ?? '');

/* SEARCH: public columns only (pseudonym, game name, trader username) — never
   the seller-only fields (password, notes), same rule as the rest of the market. */
$sql = "SELECT a.account_id, a.seller_id, g.game_name, a.pseudonym, a.selling_price, a.account_level,
               a.rank_tier, a.status, a.trade_posted, u.username AS trader
        FROM accounts a
        JOIN games g ON g.game_id = a.game_id
        JOIN users u ON u.user_id = a.seller_id
        WHERE a.status IN ('available','sold')";
$params = [];
if ($gameId) { $sql .= ' AND a.game_id = ?'; $params[] = $gameId; }
if ($q !== '') {
    $sql .= ' AND (a.pseudonym LIKE ? OR g.game_name LIKE ? OR u.username LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY (a.status = 'sold') ASC, a.selling_price $sort";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Market';
include __DIR__ . '/../includes/header.php';

function market_card(array $r): void {
    $meta = game_meta($r['game_name']);
    $sold = $r['status'] === 'sold';
    $mine = (int)$r['seller_id'] === current_user_id();
    ?>
    <article class="glass card listing <?= $sold ? 'is-sold' : '' ?>">
        <div class="row between">
            <span class="badge badge-game"><?= e($r['game_name']) ?></span>
            <?php if ($sold): ?><span class="badge badge-sold">sold</span><?php endif; ?>
        </div>
        <h3><?= e($r['pseudonym']) ?></h3>
        <p class="price"><?= peso($r['selling_price']) ?></p>
        <ul class="facts">
            <li><?= e($meta['level_label'] ?? 'Level') ?>: <strong><?= (int)$r['account_level'] ?></strong></li>
            <?php if ($r['rank_tier']): ?><li>Rank: <strong><?= e($r['rank_tier']) ?></strong></li><?php endif; ?>
            <li>Trader: <strong><?= e($r['trader']) ?></strong></li>
            <li>Posted: <?= e($r['trade_posted']) ?></li>
        </ul>
        <?php if (!$sold && !$mine): ?>
            <a class="btn btn-block" href="<?= BASE_URL ?>/market/view.php?id=<?= (int)$r['account_id'] ?>">Buy</a>
        <?php elseif ($mine): ?>
            <span class="muted small">Your listing</span>
        <?php endif; ?>
    </article>
    <?php
}
$available = array_filter($rows, fn($r) => $r['status'] === 'available');
$sold      = array_filter($rows, fn($r) => $r['status'] === 'sold');
?>
<h1>Market</h1>

<!-- SEARCH: market listings -->
<form method="get" class="glass card filters">
    <label class="full">Search by pseudonym, game, or trader
        <input type="text" name="q" placeholder="e.g. mythic or genshin" value="<?= e($q) ?>">
    </label>
    <label>Game
        <select name="game">
            <option value="0">All games</option>
            <?php foreach ($games as $g): ?>
                <option value="<?= (int)$g['game_id'] ?>" <?= $gameId === (int)$g['game_id'] ? 'selected' : '' ?>><?= e($g['game_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Price
        <select name="sort">
            <option value="asc"  <?= $sort === 'ASC'  ? 'selected' : '' ?>>Lowest first</option>
            <option value="desc" <?= $sort === 'DESC' ? 'selected' : '' ?>>Highest first</option>
        </select>
    </label>
    <button class="btn">Apply</button>
    <?php if ($q !== '' || $gameId): ?><a class="btn btn-ghost" href="<?= BASE_URL ?>/market/index.php">Clear</a><?php endif; ?>
</form>

<?php if ($q !== ''): ?>
    <p class="muted">Found <?= count($rows) ?> result(s) for "<?= e($q) ?>".</p>
<?php endif; ?>

<h2>Available</h2>
<?php if (!$available): ?>
    <div class="glass card center"><p><?= $q !== '' || $gameId ? 'No accounts match your search.' : 'No accounts are available right now.' ?></p></div>
<?php else: ?>
    <div class="grid cards"><?php foreach ($available as $r) market_card($r); ?></div>
<?php endif; ?>

<?php if ($sold): ?>
    <h2>Recently sold</h2>
    <div class="grid cards"><?php foreach ($sold as $r) market_card($r); ?></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
