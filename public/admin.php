<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireAdmin();

$success_msg = '';
$error_msg = '';

$tab = $_GET['tab'] ?? 'assets';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$delete_id = isset($_GET['delete']) ? (int)$_GET['delete'] : null;
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'category';
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$allowed_sorts = ['category', 'item_name', 'price', 'id'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'category';
}

$edit_asset = null;
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_asset = $stmt->fetch();
}

$delete_asset = null;
if ($delete_id) {
    $stmt = $pdo->prepare("SELECT id, item_name FROM assets WHERE id = ?");
    $stmt->execute([$delete_id]);
    $delete_asset = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_price') {
        $asset_id = (int)$_POST['asset_id'];
        $new_price = filter_var($_POST['new_price'], FILTER_VALIDATE_FLOAT);

        if ($new_price !== false && $new_price > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE assets SET price = :price WHERE id = :id");
                $stmt->execute([':price' => $new_price, ':id' => $asset_id]);
                $success_msg = "Asset pricing updated successfully.";
            } catch (PDOException $e) {
                $error_msg = "Database Error: Unable to update asset.";
            }
        } else {
            $error_msg = "Invalid price format detected.";
        }
    }

    if ($action === 'save_asset') {
        $name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $image_url = trim($_POST['image_url'] ?? '');
        $asset_id = (int)($_POST['asset_id'] ?? 0);

        if (!$name || !$category || $price === false || $price <= 0) {
            $error_msg = "Name, category, and a valid price are required.";
        } else {
            try {
                if ($asset_id) {
                    $stmt = $pdo->prepare("UPDATE assets SET item_name = ?, category = ?, price = ?, image_url = ? WHERE id = ?");
                    $stmt->execute([$name, $category, $price, $image_url ?: null, $asset_id]);
                    $success_msg = "Asset updated successfully.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO assets (item_name, category, price, image_url) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $category, $price, $image_url ?: null]);
                    $success_msg = "New asset added to the database.";
                }
            } catch (PDOException $e) {
                $error_msg = "Database Error: Unable to save asset.";
            }
        }
    }

    if ($action === 'delete_asset') {
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            $stmt->execute([$asset_id]);
            $success_msg = "Asset removed from the database.";
        } catch (PDOException $e) {
            $error_msg = "Database Error: Unable to delete asset.";
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
        $where = '';
        $params = [];
        if ($search) {
            $where = "WHERE item_name LIKE ? OR category LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        $dir_sql = $dir === 'desc' ? 'DESC' : 'ASC';
        $sort_map = [
            'category' => 'category',
            'item_name' => 'item_name',
            'price' => 'price',
            'id' => 'id',
        ];
        $order = $sort_map[$sort] ?? 'category';
        $stmt = $pdo->prepare("SELECT * FROM assets $where ORDER BY $order $dir_sql, id ASC");
        $stmt->execute($params);
        $assets = $stmt->fetchAll();
    } elseif ($tab === 'players') {
        $stmt = $pdo->query("SELECT acct_id, username, first_name, surname, email_addr, role, birthdate FROM accounts ORDER BY acct_id ASC");
        $players = $stmt->fetchAll();
    } elseif ($tab === 'scores') {
        $stmt = $pdo->query("SELECT s.id, s.streak, s.difficulty, s.played_at, a.username FROM scores s JOIN accounts a ON s.acct_id = a.acct_id ORDER BY s.played_at DESC LIMIT 50");
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

    <?php if ($success_msg): ?>
        <div class="admin-msg admin-msg-success">[&#10003;] <?= htmlspecialchars($success_msg) ?></div>
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
        <a href="/itec106/admin.php?tab=assets" class="admin-tab <?= $tab === 'assets' ? 'admin-tab-active' : '' ?>">Assets</a>
        <a href="/itec106/admin.php?tab=players" class="admin-tab <?= $tab === 'players' ? 'admin-tab-active' : '' ?>">Players</a>
        <a href="/itec106/admin.php?tab=scores" class="admin-tab <?= $tab === 'scores' ? 'admin-tab-active' : '' ?>">Scores</a>
    </div>

    <div class="admin-tab-content">

<?php if ($tab === 'assets'): ?>

        <div class="card admin-form-card">
            <h2 class="admin-form-title"><?= $edit_asset ? 'Edit Asset' : 'Add New Asset' ?></h2>
            <form class="admin-form" method="POST" action="/itec106/admin.php?tab=assets">
                <input type="hidden" name="action" value="save_asset">
                <?php if ($edit_asset): ?>
                    <input type="hidden" name="asset_id" value="<?= $edit_asset['id'] ?>">
                <?php endif; ?>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="item_name">Item Name</label>
                        <input class="admin-form-input" type="text" id="item_name" name="item_name" required value="<?= htmlspecialchars($edit_asset['item_name'] ?? '') ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="category">Category</label>
                        <input class="admin-form-input" list="category-list" id="category" name="category" required value="<?= htmlspecialchars($edit_asset['category'] ?? '') ?>">
                        <datalist id="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="price">Price ($)</label>
                        <input class="admin-form-input" type="number" step="0.01" id="price" name="price" required value="<?= htmlspecialchars($edit_asset['price'] ?? '') ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="image_url">Image URL (optional)</label>
                        <input class="admin-form-input" type="url" id="image_url" name="image_url" placeholder="https://..." value="<?= htmlspecialchars($edit_asset['image_url'] ?? '') ?>">
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-blue"><?= $edit_asset ? 'Update Asset' : 'Add Asset' ?></button>
                    <?php if ($edit_asset): ?>
                        <a href="/itec106/admin.php?tab=assets" class="btn btn-red admin-btn">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($delete_asset): ?>
            <div class="card admin-delete-card">
                <h2 class="admin-form-title">Delete Asset</h2>
                <p class="admin-delete-text">
                    Are you sure you want to permanently remove <strong class="admin-delete-name"><?= htmlspecialchars($delete_asset['item_name']) ?></strong> from the database?
                </p>
                <form method="POST" action="/itec106/admin.php?tab=assets">
                    <input type="hidden" name="action" value="delete_asset">
                    <input type="hidden" name="asset_id" value="<?= $delete_asset['id'] ?>">
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-red">Confirm Delete</button>
                        <a href="/itec106/admin.php?tab=assets" class="btn btn-blue admin-btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="admin-assets-header">
                <h2 class="admin-assets-title">Hardware Asset Database</h2>
                <form class="admin-search-form" method="GET" action="/itec106/admin.php">
                    <input type="hidden" name="tab" value="assets">
                    <input class="admin-search-input" type="text" name="search" placeholder="Search assets..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-blue admin-btn">Search</button>
                    <?php if ($search): ?>
                        <a href="/itec106/admin.php?tab=assets" class="btn btn-red admin-btn">Clear</a>
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
                            <a href="/itec106/admin.php?tab=assets&sort=id&dir=<?= $sort === 'id' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">ID <?= $sort === 'id' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $sort === 'category' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="/itec106/admin.php?tab=assets&sort=category&dir=<?= $sort === 'category' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Category <?= $sort === 'category' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $sort === 'item_name' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="/itec106/admin.php?tab=assets&sort=item_name&dir=<?= $sort === 'item_name' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Item Name <?= $sort === 'item_name' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
                        </th>
                        <th class="admin-th <?= $sort === 'price' ? 'admin-th-sort-active' : '' ?> admin-th-sortable">
                            <a href="/itec106/admin.php?tab=assets&sort=price&dir=<?= $sort === 'price' && $dir === 'asc' ? 'desc' : 'asc' ?>&search=<?= urlencode($search) ?>">Price <?= $sort === 'price' ? ($dir === 'asc' ? '▲' : '▼') : '' ?></a>
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
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6e738d" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="admin-td admin-td-id">#<?= $asset['id'] ?></td>
                            <td class="admin-td">
                                <span class="admin-category-badge"><?= htmlspecialchars($asset['category']) ?></span>
                            </td>
                            <td class="admin-td admin-item-name"><?= htmlspecialchars($asset['item_name']) ?></td>
                            <td class="admin-td">
                                <form class="admin-price-form" method="POST" action="/itec106/admin.php?tab=assets">
                                    <input type="hidden" name="action" value="update_price">
                                    <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                    <span class="admin-price-dollar">$</span>
                                    <input class="admin-price-input" type="number" step="0.01" name="new_price" value="<?= $asset['price'] ?>" required>
                                    <button type="submit" class="btn btn-blue admin-btn">Save</button>
                                </form>
                            </td>
                            <td class="admin-td">
                                <div class="admin-actions">
                                    <a href="/itec106/admin.php?tab=assets&edit=<?= $asset['id'] ?>" class="btn btn-green admin-btn">Edit</a>
                                    <a href="/itec106/admin.php?tab=assets&delete=<?= $asset['id'] ?>" class="btn btn-red admin-btn">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assets)): ?>
                        <tr><td class="admin-td" colspan="6" style="text-align:center;color:var(--macchiato-subtext);">
                            <?= $search ? 'No assets match your search.' : 'No assets in the database.' ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<?php elseif ($tab === 'players'): ?>

        <div class="card">
            <h2 class="admin-assets-title">Registered Operatives</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="admin-th">ID</th>
                        <th class="admin-th">Username</th>
                        <th class="admin-th">Full Name</th>
                        <th class="admin-th">Email</th>
                        <th class="admin-th">Role</th>
                        <th class="admin-th">Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $p): ?>
                        <tr>
                            <td class="admin-td admin-td-id">#<?= $p['acct_id'] ?></td>
                            <td class="admin-td admin-item-name"><?= htmlspecialchars($p['username']) ?></td>
                            <td class="admin-td"><?= htmlspecialchars($p['first_name'] . ' ' . $p['surname']) ?></td>
                            <td class="admin-td"><?= htmlspecialchars($p['email_addr']) ?></td>
                            <td class="admin-td">
                                <span class="admin-category-badge"><?= htmlspecialchars($p['role']) ?></span>
                            </td>
                            <td class="admin-td"><?= htmlspecialchars($p['birthdate']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<?php elseif ($tab === 'scores'): ?>

        <div class="card">
            <h2 class="admin-assets-title">Recent Game Sessions</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="admin-th">ID</th>
                        <th class="admin-th">Player</th>
                        <th class="admin-th">Streak</th>
                        <th class="admin-th">Difficulty</th>
                        <th class="admin-th">Played At</th>
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
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($scores)): ?>
                        <tr><td class="admin-td" colspan="5" style="text-align:center;color:var(--macchiato-subtext);">No game data recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
