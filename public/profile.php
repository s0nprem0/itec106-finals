<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireLogin();

$acct_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_SESSION['acct_id'];
$is_own = $acct_id === (int)$_SESSION['acct_id'];
$is_edit = $is_own && isset($_GET['edit']);

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

$validation_errors = [];
$password_errors = [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_own) {
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $password_errors[] = 'All password fields are required.';
        } elseif ($new !== $confirm) {
            $password_errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($new) < 6) {
            $password_errors[] = 'New password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM accounts WHERE acct_id = ?");
            $stmt->execute([$acct_id]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $password_errors[] = 'Current password is incorrect.';
            } else {
                $stmt = $pdo->prepare("UPDATE accounts SET password = ? WHERE acct_id = ?");
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $acct_id]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Password changed successfully.'];
                session_write_close();
                header("Location: " . BASE_URL . "/profile.php");
                exit;
            }
        }
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $email = trim($_POST['email_addr'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');

        if (!$first_name) $validation_errors[] = 'First name is required.';
        if (!$surname) $validation_errors[] = 'Surname is required.';
        if (!$email) $validation_errors[] = 'Email address is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $validation_errors[] = 'Invalid email format.';
        if (!$username) $validation_errors[] = 'Username is required.';
        if (!$birthdate) $validation_errors[] = 'Birthdate is required.';

        if (empty($validation_errors)) {
            $stmt = $pdo->prepare("SELECT acct_id FROM accounts WHERE username = ? AND acct_id != ?");
            $stmt->execute([$username, $acct_id]);
            if ($stmt->fetch()) {
                $validation_errors[] = 'Username is already taken.';
            }

            $stmt = $pdo->prepare("SELECT acct_id FROM accounts WHERE email_addr = ? AND acct_id != ?");
            $stmt->execute([$email, $acct_id]);
            if ($stmt->fetch()) {
                $validation_errors[] = 'Email address is already in use.';
            }
        }

        if (empty($validation_errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE accounts SET first_name = ?, surname = ?, email_addr = ?, username = ?, birthdate = ? WHERE acct_id = ?");
                $stmt->execute([$first_name, $surname, $email, $username, $birthdate, $acct_id]);
                $user['first_name'] = $first_name;
                $user['surname'] = $surname;
                $user['email_addr'] = $email;
                $user['username'] = $username;
                $user['birthdate'] = $birthdate;
                $success_flash = ['type' => 'success', 'text' => 'Profile updated successfully.'];
            } catch (PDOException $e) {
                $validation_errors[] = 'Database error: Unable to update profile.';
            }
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE acct_id = ?");
    $stmt->execute([$acct_id]);
    $total_games = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_games = 0;
}

try {
    $stmt = $pdo->prepare("SELECT MAX(streak) FROM scores WHERE acct_id = ?");
    $stmt->execute([$acct_id]);
    $best_streak = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $best_streak = 0;
}

try {
    $stmt = $pdo->prepare("SELECT streak, difficulty, played_at FROM scores WHERE acct_id = ? ORDER BY played_at DESC LIMIT 10");
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
$role_color = match($user['role']) {
    'admin' => 'var(--flat-red)',
    'moderator' => 'var(--flat-green)',
    default => 'var(--flat-blue)',
};

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="profile-page">

    <?php if ($flash): ?>
        <div class="admin-msg admin-msg-<?= $flash['type'] ?>">[<?= $flash['type'] === 'success' ? '&#10003;' : '&#33;' ?>] <?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>

    <?php if (isset($success_flash)): ?>
        <div class="admin-msg admin-msg-success">[&#10003;] <?= htmlspecialchars($success_flash['text']) ?></div>
    <?php endif; ?>

    <div class="card profile-card">
        <div class="profile-header">
            <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="profile-header-info">
                <h1 class="profile-name"><?= htmlspecialchars($user['username']) ?></h1>
                <span class="profile-role" style="color:<?= $role_color ?>;"><?= $role_label ?></span>
            </div>
            <div class="profile-header-actions">
                <?php if ($is_own && !$is_edit): ?>
                    <a href="<?= BASE_URL ?>/profile.php?edit" class="btn btn-blue">Edit Profile</a>
                <?php elseif ($is_own && $is_edit): ?>
                    <a href="<?= BASE_URL ?>/profile.php" class="btn btn-red admin-btn">Cancel</a>
                <?php endif; ?>
                <?php if (!$is_own): ?>
                    <a href="<?= BASE_URL ?>/admin.php?tab=players" class="btn btn-blue admin-btn">Back to Admin</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($validation_errors)): ?>
            <div class="admin-msg admin-msg-error">
                &#33; <?= implode('<br>&#33; ', array_map('htmlspecialchars', $validation_errors)) ?>
            </div>
        <?php endif; ?>

        <?php if ($is_edit): ?>

            <form class="profile-form" method="POST" action="<?= BASE_URL ?>/profile.php?edit">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="first_name">First Name</label>
                        <input class="admin-form-input" type="text" id="first_name" name="first_name" required
                               value="<?= htmlspecialchars($_POST['first_name'] ?? $user['first_name']) ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="surname">Surname</label>
                        <input class="admin-form-input" type="text" id="surname" name="surname" required
                               value="<?= htmlspecialchars($_POST['surname'] ?? $user['surname']) ?>">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="email_addr">Email</label>
                        <input class="admin-form-input" type="email" id="email_addr" name="email_addr" required
                               value="<?= htmlspecialchars($_POST['email_addr'] ?? $user['email_addr']) ?>">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="username">Username</label>
                        <input class="admin-form-input" type="text" id="username" name="username" required
                               value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="birthdate">Birthdate</label>
                        <input class="admin-form-input" type="date" id="birthdate" name="birthdate" required
                               value="<?= htmlspecialchars($_POST['birthdate'] ?? $user['birthdate']) ?>">
                    </div>
                    <div class="admin-form-group"></div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-blue">Save Changes</button>
                </div>
            </form>

            <form class="profile-form profile-password-form" method="POST" action="<?= BASE_URL ?>/profile.php?edit">
                <h3 class="profile-password-title">Change Password</h3>

                <?php if (!empty($password_errors)): ?>
                    <div class="admin-msg admin-msg-error">
                        &#33; <?= implode('<br>&#33; ', array_map('htmlspecialchars', $password_errors)) ?>
                    </div>
                <?php endif; ?>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="current_password">Current Password</label>
                        <input class="admin-form-input" type="password" id="current_password" name="current_password">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="new_password">New Password</label>
                        <input class="admin-form-input" type="password" id="new_password" name="new_password" minlength="6">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="confirm_password">Confirm New Password</label>
                        <input class="admin-form-input" type="password" id="confirm_password" name="confirm_password" minlength="6">
                    </div>
                    <div class="admin-form-group"></div>
                </div>

                <div class="admin-form-actions">
                    <input type="hidden" name="change_password" value="1">
                    <button type="submit" class="btn btn-blue">Change Password</button>
                </div>
            </form>

        <?php else: ?>

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
                    <span class="profile-detail-value" style="color:<?= $role_color ?>;font-weight:600;"><?= $role_label ?></span>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <div class="card profile-stats-card">
        <h2 class="profile-stats-title">Game Statistics</h2>

        <div class="profile-stats">
            <div class="card profile-stat profile-stat-blue">
                <p class="admin-stat-label">Total Games</p>
                <div class="admin-stat-value"><?= $total_games ?></div>
            </div>
            <div class="card profile-stat profile-stat-green">
                <p class="admin-stat-label">Best Streak</p>
                <div class="admin-stat-value"><?= $best_streak ?></div>
            </div>
        </div>

        <?php if (!empty($recent_scores)): ?>
            <h3 class="profile-recent-title">Recent Sessions</h3>
            <table class="admin-table profile-recent-table">
                <thead>
                    <tr>
                        <th class="admin-th">Streak</th>
                        <th class="admin-th">Difficulty</th>
                        <th class="admin-th" style="text-align:right;">Played At</th>
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
                        ?>
                        <tr>
                            <td class="admin-td lb-streak" style="text-align:left;"><?= (int)$s['streak'] ?></td>
                            <td class="admin-td"><span class="lb-diff-badge <?= $dc ?>"><?= htmlspecialchars(ucfirst($s['difficulty'] ?? 'Medium')) ?></span></td>
                            <td class="admin-td" style="text-align:right;color:var(--flat-subtext);font-size:0.85rem;"><?= date('M j, Y g:i A', strtotime($s['played_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="profile-no-data">No game sessions recorded yet.</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
