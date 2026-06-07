<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireAdmin();

$success_msg = '';
$error_msg = '';

$edit_asset = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_asset = $stmt->fetch();
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

    $assets = $pdo->query("SELECT * FROM assets ORDER BY category ASC, price DESC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "System Error: Unable to retrieve mainframe analytics.";
    $assets = [];
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
    </div>

    <div class="card admin-form-card">
        <h2 class="admin-form-title"><?= $edit_asset ? 'Edit Asset' : 'Add New Asset' ?></h2>
        <form class="admin-form" method="POST" action="/itec106/admin.php">
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
                    <input class="admin-form-input" type="text" id="category" name="category" required value="<?= htmlspecialchars($edit_asset['category'] ?? '') ?>">
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
                    <a href="/itec106/admin.php" class="btn btn-red admin-btn">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="admin-assets-title">Hardware Asset Database</h2>

        <table class="admin-table">
            <thead>
                <tr>
                    <th class="admin-th">ID</th>
                    <th class="admin-th">Category</th>
                    <th class="admin-th">Item Name</th>
                    <th class="admin-th">Price</th>
                    <th class="admin-th">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td class="admin-td admin-td-id">#<?= $asset['id'] ?></td>
                        <td class="admin-td">
                            <span class="admin-category-badge"><?= htmlspecialchars($asset['category']) ?></span>
                        </td>
                        <td class="admin-td admin-item-name"><?= htmlspecialchars($asset['item_name']) ?></td>
                        <td class="admin-td">
                            <form class="admin-price-form" method="POST" action="/itec106/admin.php">
                                <input type="hidden" name="action" value="update_price">
                                <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                <span class="admin-price-dollar">$</span>
                                <input class="admin-price-input" type="number" step="0.01" name="new_price" value="<?= $asset['price'] ?>" required>
                                <button type="submit" class="btn btn-blue admin-btn">Save</button>
                            </form>
                        </td>
                        <td class="admin-td">
                            <div class="admin-actions">
                                <a href="/itec106/admin.php?edit=<?= $asset['id'] ?>" class="btn btn-green admin-btn">Edit</a>
                                <form method="POST" action="/itec106/admin.php" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($asset['item_name'])) ?>?')">
                                    <input type="hidden" name="action" value="delete_asset">
                                    <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                    <button type="submit" class="btn btn-red admin-btn">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
