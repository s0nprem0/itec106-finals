<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requirePermission($pdo, 'admin.access');

$action = $_GET['action'] ?? 'add';
$asset_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$validation_errors = [];
$form_data = [
    'item_name' => '',
    'category' => '',
    'price' => '',
    'image_url' => '',
];

if ($action === 'delete' && !hasPermission($pdo, 'assets.delete')) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'You do not have permission to delete assets.'];
    session_write_close();
    header("Location: " . BASE_URL . "/admin.php?tab=assets");
    exit;
}

$asset = null;
if ($asset_id && in_array($action, ['edit', 'delete'])) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
    if (!$asset) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => "Asset #$asset_id not found."];
        session_write_close();
        header("Location: " . BASE_URL . "/admin.php?tab=assets");
        exit;
    }
    if ($action === 'edit') {
        $form_data = [
            'item_name' => $asset['item_name'],
            'category' => $asset['category'],
            'price' => $asset['price'],
            'image_url' => $asset['image_url'] ?? '',
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'save_asset') {
        $name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $image_url = trim($_POST['image_url'] ?? '');
        $edit_id = (int)($_POST['asset_id'] ?? 0);

        if (!$name) {
            $validation_errors[] = 'Item name is required.';
        }
        if (!$category) {
            $validation_errors[] = 'Category is required.';
        }
        if ($price === false || $price <= 0) {
            $validation_errors[] = 'A valid price greater than zero is required.';
        }

        $form_data = [
            'item_name' => $name,
            'category' => $category,
            'price' => $price,
            'image_url' => $image_url,
        ];

        if (empty($validation_errors)) {
            try {
                if ($edit_id) {
                    $stmt = $pdo->prepare("UPDATE assets SET item_name = ?, category = ?, price = ?, image_url = ? WHERE id = ?");
                    $stmt->execute([$name, $category, $price, $image_url ?: null, $edit_id]);
                    $_SESSION['flash'] = ['type' => 'success', 'text' => "Asset \"$name\" updated."];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO assets (item_name, category, price, image_url) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $category, $price, $image_url ?: null]);
                    $_SESSION['flash'] = ['type' => 'success', 'text' => "New asset \"$name\" added."];
                }
                session_write_close();
                header("Location: " . BASE_URL . "/admin.php?tab=assets");
                exit;
            } catch (PDOException $e) {
                $validation_errors[] = 'Database error: Unable to save asset.';
            }
        }
    }

    if ($post_action === 'delete_asset' && hasPermission($pdo, 'assets.delete')) {
        $delete_id = (int)($_POST['asset_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => "Asset #$delete_id deleted."];
            session_write_close();
            header("Location: " . BASE_URL . "/admin.php?tab=assets");
            exit;
        } catch (PDOException $e) {
            $validation_errors[] = 'Database error: Unable to delete asset.';
        }
    }
}

try {
    $categories = $pdo->query("SELECT DISTINCT category FROM assets ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="admin-page">

    <div class="admin-header">
        <h1 class="admin-title">
            <?= $action === 'delete' ? 'Delete Asset' : ($action === 'edit' ? 'Edit Asset' : 'Add Asset') ?>
        </h1>
        <p class="admin-subtitle">
            <a href="<?= BASE_URL ?>/admin.php?tab=assets" class="nav-link" style="display:inline;">&larr; Back to Asset Database</a>
        </p>
    </div>

    <?php if (!empty($validation_errors)): ?>
        <div class="admin-msg admin-msg-error">
            &#33; <?= implode('<br>&#33; ', array_map('htmlspecialchars', $validation_errors)) ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'delete'): ?>

        <div class="card admin-delete-card" style="max-width:520px;margin:0 auto;">
            <h2 class="admin-form-title">Confirm Deletion</h2>
            <p class="admin-delete-text">
                Are you sure you want to permanently remove <strong class="admin-delete-name"><?= htmlspecialchars($asset['item_name']) ?></strong>
                (ID #<?= $asset['id'] ?>) from the database? This action cannot be undone.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/admin_asset.php?action=delete&id=<?= $asset['id'] ?>">
                <input type="hidden" name="action" value="delete_asset">
                <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-red">Confirm Delete</button>
                    <a href="<?= BASE_URL ?>/admin.php?tab=assets" class="btn btn-blue">Cancel</a>
                </div>
            </form>
        </div>

    <?php else: ?>

        <div class="card admin-form-card" style="max-width:620px;margin:0 auto;">
            <h2 class="admin-form-title"><?= $action === 'edit' ? 'Edit Asset Details' : 'New Hardware Asset' ?></h2>

            <form class="admin-form" method="POST"
                  action="<?= BASE_URL ?>/admin_asset.php?action=<?= $action ?><?= $asset_id ? '&id=' . $asset_id : '' ?>">
                <input type="hidden" name="action" value="save_asset">
                <?php if ($asset_id): ?>
                    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
                <?php endif; ?>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="item_name">Item Name</label>
                        <input class="admin-form-input" type="text" id="item_name" name="item_name"
                               required value="<?= htmlspecialchars($form_data['item_name']) ?>"
                               placeholder="e.g. NVIDIA RTX 5090">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="category">Category</label>
                        <input class="admin-form-input" list="category-list" id="category" name="category"
                               required value="<?= htmlspecialchars($form_data['category']) ?>"
                               placeholder="e.g. GPU, CPU, RAM">
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
                        <input class="admin-form-input" type="number" step="0.01" min="0.01" id="price" name="price"
                               required value="<?= htmlspecialchars($form_data['price']) ?>"
                               placeholder="0.00">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="image_url">Image URL</label>
                        <input class="admin-form-input" type="url" id="image_url" name="image_url"
                               value="<?= htmlspecialchars($form_data['image_url']) ?>"
                               placeholder="https://example.com/image.jpg">
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-blue">
                        <?= $action === 'edit' ? 'Update Asset' : 'Add Asset' ?>
                    </button>
                    <a href="<?= BASE_URL ?>/admin.php?tab=assets" class="btn btn-red">Cancel</a>
                </div>
            </form>
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
