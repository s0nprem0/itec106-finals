<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireLogin();

$acct_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_SESSION['acct_id'];
$is_own = $acct_id === (int)$_SESSION['acct_id'];

if (!$is_own && !hasPermission($pdo, 'players.view')) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'You do not have permission to view other profiles.'];
    session_write_close();
    header("Location: " . BASE_URL . "/profile.php");
    exit;
}

$stmt = $pdo->prepare("SELECT acct_id, username, first_name, surname, email_addr, birthdate, role FROM accounts WHERE acct_id = ?");
$stmt->execute([$acct_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'User not found.'];
    session_write_close();
    header("Location: " . BASE_URL . "/profile.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE acct_id = ?");
    $stmt->execute([$acct_id]);
    $total_games = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_games = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(profit), 0) FROM scores WHERE acct_id = ? AND profit IS NOT NULL");
    $stmt->execute([$acct_id]);
    $best_profit = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    $best_profit = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(profit), 0) FROM scores WHERE acct_id = ? AND profit IS NOT NULL");
    $stmt->execute([$acct_id]);
    $total_profit = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_profit = 0;
}

try {
    $stmt = $pdo->prepare("SELECT profit, difficulty, played_at FROM scores WHERE acct_id = ? ORDER BY played_at DESC LIMIT 10");
    $stmt->execute([$acct_id]);
    $recent_scores = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_scores = [];
}

$initials = strtoupper(
    mb_substr($user['first_name'] ?? '?', 0, 1) .
    mb_substr($user['surname'] ?? '?', 0, 1)
);

$role_label = ucfirst($user['role']);

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="profile-page">

    <div class="card profile-card">
        <div class="profile-header">
            <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="profile-header-info">
                <h1 class="profile-name"><?= htmlspecialchars($user['username']) ?></h1>
                <span class="profile-role profile-role-<?= $user['role'] ?>"><?= $role_label ?></span>
            </div>
            <div class="profile-header-actions">
                <?php if ($is_own): ?>
                    <a href="<?= BASE_URL ?>/settings.php" class="btn btn-blue">Manage Account</a>
                <?php endif; ?>
                <?php if (!$is_own): ?>
                    <a href="<?= BASE_URL ?>/admin.php?tab=players" class="btn btn-blue admin-btn">Back to Admin</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-details">
            <div class="profile-detail">
                <span class="profile-detail-label">Full Name</span>
                <span class="profile-detail-value"><?= htmlspecialchars($user['first_name'] . ' ' . $user['surname']) ?></span>
            </div>
            <div class="profile-detail">
                <span class="profile-detail-label">Username</span>
                <span class="profile-detail-value"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="profile-detail">
                <span class="profile-detail-label">Email</span>
                <span class="profile-detail-value"><?= htmlspecialchars($user['email_addr']) ?></span>
            </div>
            <div class="profile-detail">
                <span class="profile-detail-label">Birthdate</span>
                <span class="profile-detail-value"><?= htmlspecialchars($user['birthdate']) ?></span>
            </div>
            <div class="profile-detail">
                <span class="profile-detail-label">Role</span>
                <span class="profile-detail-value profile-role-<?= $user['role'] ?>"><?= $role_label ?></span>
            </div>
        </div>
    </div>

    <div class="card profile-stats-card">
        <h2 class="profile-stats-title">Game Statistics</h2>

        <div class="profile-stats">
            <div class="card profile-stat profile-stat-blue">
                <p class="admin-stat-label">Total Games</p>
                <div class="admin-stat-value"><?= $total_games ?></div>
            </div>
            <div class="card profile-stat profile-stat-green">
                <p class="admin-stat-label">Total Profit</p>
                <div class="admin-stat-value <?= $total_profit >= 0 ? 'game-profit-positive' : 'game-profit-negative' ?>"><?= $total_profit >= 0 ? '+' : '' ?>$<?= number_format(abs($total_profit), 0) ?></div>
            </div>
            <div class="card profile-stat profile-stat-purple">
                <p class="admin-stat-label">Best Game</p>
                <div class="admin-stat-value <?= $best_profit >= 0 ? 'game-profit-positive' : 'game-profit-negative' ?>"><?= $best_profit >= 0 ? '+' : '' ?>$<?= number_format(abs($best_profit), 0) ?></div>
            </div>
        </div>

        <?php if (!empty($recent_scores)): ?>
            <h3 class="profile-recent-title">Recent Sessions</h3>
            <div class="profile-table-wrapper">
            <table class="profile-table">
                <thead>
                    <tr>
                        <th class="admin-th">Profit</th>
                        <th class="admin-th">Difficulty</th>
                        <th class="admin-th profile-th-right">Played At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_scores as $s): ?>
                        <?php
                        $dc = match($s['difficulty'] ?? 'medium') {
                            'easy' => 'lb-diff-easy',
                            'hard' => 'lb-diff-hard',
                            default => 'lb-diff-medium',
                        };
                        $is_pos = ($s['profit'] ?? 0) >= 0;
                        ?>
                        <tr>
                            <td class="admin-td <?= $is_pos ? 'game-profit-positive' : 'game-profit-negative' ?>"><?= $is_pos ? '+' : '' ?>$<?= number_format(abs($s['profit'] ?? 0), 0) ?></td>
                            <td class="admin-td"><span class="lb-diff-badge <?= $dc ?>"><?= htmlspecialchars(ucfirst($s['difficulty'] ?? 'Medium')) ?></span></td>
                            <td class="admin-td profile-td-right"><?= date('M j, Y g:i A', strtotime($s['played_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <p class="profile-no-data">No game sessions recorded yet.</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
