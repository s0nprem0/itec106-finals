<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requirePermission($pdo, 'admin.access');

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$error_msg = '';

$tab = $_GET['tab'] ?? 'assets';
$search = trim($_GET['search'] ?? '');
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$dir_sql = $dir === 'desc' ? 'DESC' : 'ASC';

$diff_filter = trim($_GET['difficulty'] ?? '');

$delete_score = null;
if (isset($_GET['delete_score']) && hasPermission($pdo, 'scores.delete')) {
    $sid = (int)$_GET['delete_score'];
    if ($sid) {
        $stmt = $pdo->prepare("SELECT s.id, s.streak, a.username FROM scores s JOIN accounts a ON s.acct_id = a.acct_id WHERE s.id = ?");
        $stmt->execute([$sid]);
        $delete_score = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_price') {
        $asset_id = (int)$_POST['asset_id'];
        $new_price = filter_var($_POST['new_price'], FILTER_VALIDATE_FLOAT);

        if ($new_price !== false && $new_price > 0) {
            try {
                $stmt = $pdo->prepare("SELECT item_name FROM assets WHERE id = ?");
                $stmt->execute([$asset_id]);
                $name = $stmt->fetchColumn();
                $stmt = $pdo->prepare("UPDATE assets SET price = :price WHERE id = :id");
                $stmt->execute([':price' => $new_price, ':id' => $asset_id]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => "Price updated for " . ($name ?: "asset #$asset_id") . "."];
                session_write_close();
                header("Location: " . BASE_URL . "/admin.php?tab=" . urlencode($tab));
                exit;
            } catch (PDOException $e) {
                $error_msg = "Database Error: Unable to update asset.";
            }
        } else {
            $error_msg = "Invalid price format detected.";
        }
    }

    if ($action === 'update_role' && hasPermission($pdo, 'players.edit')) {
        $target_id = (int)($_POST['target_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        if ($target_id && in_array($new_role, ['player', 'moderator', 'admin'])) {
            try {
                $stmt = $pdo->prepare("UPDATE accounts SET role = ? WHERE acct_id = ?");
                $stmt->execute([$new_role, $target_id]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => "Player #$target_id role changed to $new_role."];
                session_write_close();
                header("Location: " . BASE_URL . "/admin.php?tab=" . urlencode($tab));
                exit;
            } catch (PDOException $e) {
                $error_msg = "Database Error: Unable to update role.";
            }
        } else {
            $error_msg = "Invalid role specified.";
        }
    }

    if ($action === 'delete_score' && hasPermission($pdo, 'scores.delete')) {
        $score_id = (int)($_POST['score_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM scores WHERE id = ?");
            $stmt->execute([$score_id]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => "Score record #$score_id removed."];
            session_write_close();
            header("Location: " . BASE_URL . "/admin.php?tab=" . urlencode($tab));
            exit;
        } catch (PDOException $e) {
            $error_msg = "Database Error: Unable to delete score.";
        }
    }
}

try {
    $total_players = $pdo->query("SELECT COUNT(*) FROM accounts WHERE role = 'player'")->fetchColumn();
    $total_games = $pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();
    $highest_streak = $pdo->query("SELECT MAX(streak) FROM scores")->fetchColumn() ?? 0;
    $total_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();

    $categories = $pdo->query("SELECT DISTINCT category FROM assets ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

    $assets = [];
    $players = [];
    $scores = [];

    if ($tab === 'assets') {
        $has_create_perm = hasPermission($pdo, 'assets.create');
        $sort = $_GET['sort'] ?? 'category';
        $allowed_sorts = ['category', 'item_name', 'price', 'id'];
        if (!in_array($sort, $allowed_sorts)) {
            $sort = 'category';
        }
        $sort_map = ['category' => 'category', 'item_name' => 'item_name', 'price' => 'price', 'id' => 'id'];
        $order = $sort_map[$sort] ?? 'category';

        $where = '';
        $params = [];
        if ($search) {
            $where = "WHERE item_name LIKE ? OR category LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        $stmt = $pdo->prepare("SELECT * FROM assets $where ORDER BY $order $dir_sql, id ASC");
        $stmt->execute($params);
        $assets = $stmt->fetchAll();
    } elseif ($tab === 'players') {
        $sort = $_GET['sort'] ?? 'acct_id';
        $allowed_sorts = ['acct_id', 'username', 'first_name', 'surname', 'email_addr', 'role', 'birthdate'];
        if (!in_array($sort, $allowed_sorts)) {
            $sort = 'acct_id';
        }
        $sort_map = ['acct_id' => 'acct_id', 'username' => 'username', 'first_name' => 'first_name', 'surname' => 'surname', 'email_addr' => 'email_addr', 'role' => 'role', 'birthdate' => 'birthdate'];
        $order = $sort_map[$sort] ?? 'acct_id';

        $where = '';
        $params = [];
        if ($search) {
            $where = "WHERE username LIKE ? OR first_name LIKE ? OR surname LIKE ? OR email_addr LIKE ?";
            $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
        }
        $stmt = $pdo->prepare("SELECT acct_id, username, first_name, surname, email_addr, role, birthdate FROM accounts $where ORDER BY $order $dir_sql");
        $stmt->execute($params);
        $players = $stmt->fetchAll();
    } elseif ($tab === 'scores') {
        $sort = $_GET['sort'] ?? 'played_at';
        $allowed_sorts = ['id', 'streak', 'difficulty', 'played_at', 'username'];
        if (!in_array($sort, $allowed_sorts)) {
            $sort = 'played_at';
        }
        $sort_map = ['id' => 's.id', 'streak' => 's.streak', 'difficulty' => 's.difficulty', 'played_at' => 's.played_at', 'username' => 'a.username'];
        $order = $sort_map[$sort] ?? 's.played_at';

        $where = '';
        $params = [];
        $conditions = [];
        if ($search) {
            $conditions[] = "(a.username LIKE ?)";
            $params[] = "%$search%";
        }
        if ($diff_filter && in_array($diff_filter, ['easy', 'medium', 'hard'])) {
            $conditions[] = "s.difficulty = ?";
            $params[] = $diff_filter;
        }
        if ($conditions) {
            $where = "WHERE " . implode(' AND ', $conditions);
        }
        $stmt = $pdo->prepare("SELECT s.id, s.streak, s.difficulty, s.played_at, a.username FROM scores s JOIN accounts a ON s.acct_id = a.acct_id $where ORDER BY $order $dir_sql LIMIT 50");
        $stmt->execute($params);
        $scores = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "System Error: Unable to retrieve mainframe analytics.";
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="admin-page">

    <div class="admin-header">
        <h1 class="admin-title">System Override</h1>
        <p class="admin-subtitle">Administrator Control Panel & Intelligence Dashboard</p>
    </div>

    <?php if ($flash): ?>
        <div class="admin-msg admin-msg-<?= $flash['type'] ?>">[<?= $flash['type'] === 'success' ? '&#10003;' : '&#33;' ?>] <?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="admin-msg admin-msg-error">[&#33;] <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="admin-stats">
        <div class="card admin-stat admin-stat-blue">
            <p class="admin-stat-label">Total Operatives</p>
            <div class="admin-stat-value"><?= $total_players ?></div>
        </div>
        <div class="card admin-stat admin-stat-green">
            <p class="admin-stat-label">Games Executed</p>
            <div class="admin-stat-value"><?= $total_games ?></div>
        </div>
        <div class="card admin-stat admin-stat-red">
            <p class="admin-stat-label">Max Streak Logged</p>
            <div class="admin-stat-value"><?= $highest_streak ?></div>
        </div>
        <div class="card admin-stat admin-stat-purple">
            <p class="admin-stat-label">Database Assets</p>
            <div class="admin-stat-value"><?= $total_assets ?></div>
        </div>
    </div>

    <div class="admin-tabs">
        <a href="<?= BASE_URL ?>/admin.php?tab=assets" class="admin-tab <?= $tab === 'assets' ? 'admin-tab-active' : '' ?>">Assets</a>
        <a href="<?= BASE_URL ?>/admin.php?tab=players" class="admin-tab <?= $tab === 'players' ? 'admin-tab-active' : '' ?>">Players</a>
        <a href="<?= BASE_URL ?>/admin.php?tab=scores" class="admin-tab <?= $tab === 'scores' ? 'admin-tab-active' : '' ?>">Scores</a>
    </div>

    <div class="admin-tab-content">

<?php if ($tab === 'assets'): ?>

        <div class="card">
            <div class="admin-assets-header">
                <h2 class="admin-assets-title">Hardware Asset Database</h2>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                <?php if ($has_create_perm): ?>
                    <a href="<?= BASE_URL ?>/admin_asset.php?action=add" class="btn btn-green admin-btn">+ Add Asset</a>
                <?php endif; ?>
                <form class="admin-search-form" method="GET" action="<?= BASE_URL ?>/admin.php">
                    <input type="hidden" name="tab" value="assets">
                    <input class="admin-search-input" type="text" name="search" placeholder="Search assets..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-blue admin-btn">Search</button>
                    <?php if ($search): ?>
                        <a href="<?= BASE_URL ?>/admin.php?tab=assets" class="btn btn-red admin-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($search && !empty($assets)): ?>
                <p class="admin-search-count"><?= count($assets) ?> result(s) for "<?= htmlspecialchars($search) ?>"</p>
            <?php endif; ?>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="admin-th admin-th-sm"></th>
                        <th class="admin-th <?= $sort === 'id' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=assets&sort=id&dir=<?= $sort === 'id' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">ID <?= $sort === 'id' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $sort === 'category' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=assets&sort=category&dir=<?= $sort === 'category' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Category <?= $sort === 'category' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $sort === 'item_name' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=assets&sort=item_name&dir=<?= $sort === 'item_name' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Item Name <?= $sort === 'item_name' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $sort === 'price' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=assets&sort=price&dir=<?= $sort === 'price' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Price <?= $sort === 'price' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td class="admin-td">
                                <?php if ($asset['image_url']): ?>
                                    <img class="admin-img-thumb" src="<?= htmlspecialchars($asset['image_url']) ?>" alt="">
                                <?php else: ?>
                                    <div class="admin-img-thumb admin-img-placeholder-sm">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="admin-td admin-td-id">#<?= $asset['id'] ?></td>
                            <td class="admin-td">
                                <span class="admin-category-badge"><?= htmlspecialchars($asset['category']) ?></span>
                            </td>
                            <td class="admin-td admin-item-name"><?= htmlspecialchars($asset['item_name']) ?></td>
                            <td class="admin-td">
                                <form class="admin-price-form" method="POST" action="<?= BASE_URL ?>/admin.php?tab=assets">
                                    <input type="hidden" name="action" value="update_price">
                                    <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                    <span class="admin-price-dollar">$</span>
                                    <input class="admin-price-input" type="number" step="0.01" name="new_price" value="<?= $asset['price'] ?>" required>
                                    <button type="submit" class="btn btn-blue admin-btn">Save</button>
                                </form>
                            </td>
                            <td class="admin-td">
                                <div class="admin-actions">
                                    <a href="<?= BASE_URL ?>/admin_asset.php?action=edit&id=<?= $asset['id'] ?>" class="btn btn-green admin-btn">Edit</a>
                                    <?php if (hasPermission($pdo, 'assets.delete')): ?>
                                    <a href="<?= BASE_URL ?>/admin_asset.php?action=delete&id=<?= $asset['id'] ?>" class="btn btn-red admin-btn">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assets)): ?>
                        <tr><td class="admin-td" colspan="6" style="text-align:center;color:var(--flat-subtext);">
                            <?= $search ? 'No assets match your search.' : 'No assets in the database.' ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<?php elseif ($tab === 'players'): ?>

        <div class="card">
            <div class="admin-assets-header">
                <h2 class="admin-assets-title">Registered Operatives</h2>
                <form class="admin-search-form" method="GET" action="<?= BASE_URL ?>/admin.php">
                    <input type="hidden" name="tab" value="players">
                    <input class="admin-search-input" type="text" name="search" placeholder="Search players..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-blue admin-btn">Search</button>
                    <?php if ($search): ?>
                        <a href="<?= BASE_URL ?>/admin.php?tab=players" class="btn btn-red admin-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($search && !empty($players)): ?>
                <p class="admin-search-count"><?= count($players) ?> result(s) for "<?= htmlspecialchars($search) ?>"</p>
            <?php endif; ?>

            <table class="admin-table">
                <thead>
                    <tr>
                        <?php
                        $psort = $_GET['sort'] ?? 'acct_id';
                        $pdir = $_GET['dir'] ?? 'asc';
                        ?>
                        <th class="admin-th <?= $psort === 'acct_id' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=acct_id&dir=<?= $psort === 'acct_id' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">ID <?= $psort === 'acct_id' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $psort === 'username' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=username&dir=<?= $psort === 'username' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Username <?= $psort === 'username' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $psort === 'first_name' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=first_name&dir=<?= $psort === 'first_name' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">First Name <?= $psort === 'first_name' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $psort === 'surname' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=surname&dir=<?= $psort === 'surname' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Surname <?= $psort === 'surname' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $psort === 'email_addr' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=email_addr&dir=<?= $psort === 'email_addr' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Email <?= $psort === 'email_addr' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $psort === 'role' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=role&dir=<?= $psort === 'role' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Role <?= $psort === 'role' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $psort === 'birthdate' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=players&sort=birthdate&dir=<?= $psort === 'birthdate' && $pdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Birthdate <?= $psort === 'birthdate' ? ($pdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $p): ?>
                        <tr>
                            <td class="admin-td admin-td-id">#<?= $p['acct_id'] ?></td>
                            <td class="admin-td admin-item-name"><a href="<?= BASE_URL ?>/profile.php?id=<?= $p['acct_id'] ?>" class="profile-view-link"><?= htmlspecialchars($p['username']) ?></a></td>
                            <td class="admin-td"><?= htmlspecialchars($p['first_name']) ?></td>
                            <td class="admin-td"><?= htmlspecialchars($p['surname']) ?></td>
                            <td class="admin-td"><?= htmlspecialchars($p['email_addr']) ?></td>
                            <td class="admin-td">
                                <?php if (hasPermission($pdo, 'players.edit') && $p['acct_id'] != $_SESSION['acct_id']): ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin.php?tab=players" style="display:flex;gap:0.25rem;align-items:center;">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="target_id" value="<?= $p['acct_id'] ?>">
                                    <select name="new_role" class="admin-price-input" style="width:auto;padding:0.25rem 0.4rem;font-size:0.75rem;" onchange="this.form.submit()">
                                        <option value="player" <?= $p['role'] === 'player' ? 'selected' : '' ?>>player</option>
                                        <option value="moderator" <?= $p['role'] === 'moderator' ? 'selected' : '' ?>>moderator</option>
                                        <option value="admin" <?= $p['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                    </select>
                                </form>
                                <?php else: ?>
                                <span class="admin-category-badge"><?= htmlspecialchars($p['role']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-td"><?= htmlspecialchars($p['birthdate']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($players)): ?>
                        <tr><td class="admin-td" colspan="7" style="text-align:center;color:var(--flat-subtext);">
                            <?= $search ? 'No players match your search.' : 'No players registered yet.' ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<?php elseif ($tab === 'scores'): ?>

        <?php if ($delete_score && hasPermission($pdo, 'scores.delete')): ?>
            <div class="card admin-delete-card">
                <h2 class="admin-form-title">Delete Score Record</h2>
                <p class="admin-delete-text">
                    Are you sure you want to permanently remove <strong class="admin-delete-name"><?= htmlspecialchars($delete_score['username']) ?></strong>'s streak of <strong><?= $delete_score['streak'] ?></strong> from the database?
                </p>
                <form method="POST" action="<?= BASE_URL ?>/admin.php?tab=scores">
                    <input type="hidden" name="action" value="delete_score">
                    <input type="hidden" name="score_id" value="<?= $delete_score['id'] ?>">
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-red">Confirm Delete</button>
                        <a href="<?= BASE_URL ?>/admin.php?tab=scores" class="btn btn-blue admin-btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="admin-assets-header">
                <h2 class="admin-assets-title">Recent Game Sessions</h2>
                <form class="admin-search-form" method="GET" action="<?= BASE_URL ?>/admin.php">
                    <input type="hidden" name="tab" value="scores">
                    <input class="admin-search-input" type="text" name="search" placeholder="Search by player..." value="<?= htmlspecialchars($search) ?>">
                    <select class="admin-search-input" name="difficulty" style="width:auto;">
                        <option value="">All Difficulties</option>
                        <option value="easy" <?= $diff_filter === 'easy' ? 'selected' : '' ?>>Easy</option>
                        <option value="medium" <?= $diff_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="hard" <?= $diff_filter === 'hard' ? 'selected' : '' ?>>Hard</option>
                    </select>
                    <button type="submit" class="btn btn-blue admin-btn">Filter</button>
                    <?php if ($search || $diff_filter): ?>
                        <a href="<?= BASE_URL ?>/admin.php?tab=scores" class="btn btn-red admin-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (($search || $diff_filter) && !empty($scores)): ?>
                <p class="admin-search-count"><?= count($scores) ?> result(s) found</p>
            <?php endif; ?>

            <table class="admin-table">
                <thead>
                    <tr>
                        <?php
                        $ssort = $_GET['sort'] ?? 'played_at';
                        $sdir = $_GET['dir'] ?? 'asc';
                        ?>
                        <th class="admin-th <?= $ssort === 'id' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=scores&sort=id&dir=<?= $ssort === 'id' && $sdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&difficulty=<?= urlencode($diff_filter) ?>">ID <?= $ssort === 'id' ? ($sdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $ssort === 'username' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=scores&sort=username&dir=<?= $ssort === 'username' && $sdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&difficulty=<?= urlencode($diff_filter) ?>">Player <?= $ssort === 'username' ? ($sdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $ssort === 'streak' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=scores&sort=streak&dir=<?= $ssort === 'streak' && $sdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&difficulty=<?= urlencode($diff_filter) ?>">Streak <?= $ssort === 'streak' ? ($sdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $ssort === 'difficulty' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=scores&sort=difficulty&dir=<?= $ssort === 'difficulty' && $sdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&difficulty=<?= urlencode($diff_filter) ?>">Difficulty <?= $ssort === 'difficulty' ? ($sdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $ssort === 'played_at' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="<?= BASE_URL ?>/admin.php?tab=scores&sort=played_at&dir=<?= $ssort === 'played_at' && $sdir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>&difficulty=<?= urlencode($diff_filter) ?>">Played At <?= $ssort === 'played_at' ? ($sdir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $s): ?>
                        <tr>
                            <td class="admin-td admin-td-id">#<?= $s['id'] ?></td>
                            <td class="admin-td admin-item-name"><?= htmlspecialchars($s['username']) ?></td>
                            <td class="admin-td lb-streak" style="text-align:left;font-size:1rem;"><?= $s['streak'] ?></td>
                            <td class="admin-td">
                                <?php
                                $diffClass = match($s['difficulty'] ?? 'medium') {
                                    'easy'   => 'lb-diff-easy',
                                    'hard'   => 'lb-diff-hard',
                                    default  => 'lb-diff-medium',
                                };
                                ?>
                                <span class="lb-diff-badge <?= $diffClass ?>"><?= htmlspecialchars(ucfirst($s['difficulty'] ?? 'Medium')) ?></span>
                            </td>
                            <td class="admin-td"><?= date('M j, Y g:i A', strtotime($s['played_at'])) ?></td>
                            <td class="admin-td">
                                <?php if (hasPermission($pdo, 'scores.delete')): ?>
                                <a href="<?= BASE_URL ?>/admin.php?tab=scores&delete_score=<?= $s['id'] ?>" class="btn btn-red admin-btn">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($scores)): ?>
                        <tr><td class="admin-td" colspan="6" style="text-align:center;color:var(--flat-subtext);">No game data recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
