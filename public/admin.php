<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireAdmin();

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_price') {
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
            $error_msg = "Invalid price format detected. Threat neutralized.";
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

    <div class="card">
        <h2 class="admin-assets-title">Hardware Asset Database</h2>

        <table class="admin-table">
            <thead>
                <tr>
                    <th class="admin-th">ID</th>
                    <th class="admin-th">Category</th>
                    <th class="admin-th">Item Name</th>
                    <th class="admin-th" colspan="2">Current Price</th>
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
                        <td class="admin-td" colspan="2">
                            <form class="admin-price-form" method="POST" action="/itec106/admin.php">
                                <input type="hidden" name="action" value="update_price">
                                <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                                <span class="admin-price-dollar">$</span>
                                <input class="admin-price-input" type="number" step="0.01" name="new_price" value="<?= $asset['price'] ?>" required>
                                <button type="submit" class="btn btn-blue admin-btn">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
