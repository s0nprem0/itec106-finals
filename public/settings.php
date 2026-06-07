<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireLogin();

$acct_id = (int)$_SESSION['acct_id'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$validation_errors = [];
$password_errors = [];
$delete_errors = [];

$stmt = $pdo->prepare("SELECT username, first_name, surname, email_addr, birthdate FROM accounts WHERE acct_id = ?");
$stmt->execute([$acct_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "/logout.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_account'])) {
        $del_password = $_POST['del_password'] ?? '';
        $del_confirm = $_POST['del_confirm'] ?? '';

        $stmt = $pdo->prepare("SELECT password, role FROM accounts WHERE acct_id = ?");
        $stmt->execute([$acct_id]);
        $acct = $stmt->fetch();

        if (!$acct) {
            $delete_errors[] = 'Account not found.';
        } elseif ($acct['role'] === 'admin') {
            $delete_errors[] = 'Admin accounts cannot be deleted through this interface.';
        } elseif (!$del_password) {
            $delete_errors[] = 'Current password is required to delete your account.';
        } elseif (!password_verify($del_password, $acct['password'])) {
            $delete_errors[] = 'Current password is incorrect.';
        } elseif (strtoupper($del_confirm) !== 'DELETE') {
            $delete_errors[] = 'Please type DELETE to confirm account deletion.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM accounts WHERE acct_id = ?");
                $stmt->execute([$acct_id]);
                session_destroy();
                session_start();
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your account has been deleted.'];
                session_write_close();
                header("Location: " . BASE_URL . "/login.php");
                exit;
            } catch (PDOException $e) {
                $delete_errors[] = 'Database error: Unable to delete account.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
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
                try {
                    $stmt = $pdo->prepare("UPDATE accounts SET password = ? WHERE acct_id = ?");
                    $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $acct_id]);
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Password changed successfully.'];
                    session_write_close();
                    header("Location: " . BASE_URL . "/settings.php");
                    exit;
                } catch (PDOException $e) {
                    $password_errors[] = 'Database error: Unable to update password.';
                }
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
                $user = compact('username', 'first_name', 'surname', 'email_addr', 'birthdate');
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Account settings saved successfully.'];
                session_write_close();
                header("Location: " . BASE_URL . "/settings.php");
                exit;
            } catch (PDOException $e) {
                $validation_errors[] = 'Database error: Unable to update account.';
            }
        }
    }
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="settings-page">

    <div class="settings-header">
        <h1 class="settings-title">Account Settings</h1>
        <p class="settings-subtitle">Update your personal information and password.</p>
    </div>

    <?php if ($flash): ?>
        <div class="admin-msg admin-msg-<?= $flash['type'] ?>">[<?= $flash['type'] === 'success' ? '&#10003;' : '&#33;' ?>] <?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>

    <?php if (!empty($validation_errors)): ?>
        <div class="admin-msg admin-msg-error">
            &#33; <?= implode('<br>&#33; ', array_map('htmlspecialchars', $validation_errors)) ?>
        </div>
    <?php endif; ?>

    <div class="card settings-card">
        <h2 class="settings-card-title">Personal Information</h2>

        <form class="settings-form" method="POST" action="<?= BASE_URL ?>/settings.php">
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
            </div>

            <div class="settings-actions">
                <button type="submit" class="btn btn-blue">Save Changes</button>
            </div>
        </form>
    </div>

    <div class="card settings-card">
        <h2 class="settings-card-title">Change Password</h2>

        <?php if (!empty($password_errors)): ?>
            <div class="admin-msg admin-msg-error">
                &#33; <?= implode('<br>&#33; ', array_map('htmlspecialchars', $password_errors)) ?>
            </div>
        <?php endif; ?>

        <form class="settings-form" method="POST" action="<?= BASE_URL ?>/settings.php">
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="current_password">Current Password</label>
                    <input class="admin-form-input" type="password" id="current_password" name="current_password" required>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label" for="new_password">New Password</label>
                    <input class="admin-form-input" type="password" id="new_password" name="new_password" minlength="6" required title="At least 6 characters">
                    <div id="password-strength" class="password-strength">
                        <div class="password-strength-bar">
                            <div class="password-strength-fill" id="strength-fill"></div>
                        </div>
                        <span class="password-strength-text" id="strength-text"></span>
                    </div>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="confirm_password">Confirm New Password</label>
                    <input class="admin-form-input" type="password" id="confirm_password" name="confirm_password" minlength="6" required title="At least 6 characters">
                </div>
            </div>

            <div class="settings-actions">
                <input type="hidden" name="change_password" value="1">
                <button type="submit" class="btn btn-blue">Change Password</button>
            </div>
        </form>
    </div>

    <div class="card settings-card settings-card-danger">
        <h2 class="settings-card-title settings-card-title-danger">Delete Account</h2>

        <?php if (!empty($delete_errors)): ?>
            <div class="admin-msg admin-msg-error">
                &#33; <?= implode('<br>&#33; ', array_map('htmlspecialchars', $delete_errors)) ?>
            </div>
        <?php endif; ?>

        <form class="settings-form" method="POST" action="<?= BASE_URL ?>/settings.php" onsubmit="return confirm('This will permanently delete your account. Are you sure?');">
            <p class="settings-delete-warning">
                This will permanently delete your account and all associated data
                (game history, session tokens, etc.). This action cannot be undone.
            </p>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="del_password">Enter Current Password to Confirm</label>
                    <input class="admin-form-input" type="password" id="del_password" name="del_password" required
                           placeholder="Current password">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="del_confirm">Type <strong>DELETE</strong> to confirm</label>
                    <input class="admin-form-input" type="text" id="del_confirm" name="del_confirm" required
                           placeholder="Type DELETE here" pattern="DELETE" title="Type DELETE exactly">
                </div>
            </div>

            <div class="settings-actions">
                <input type="hidden" name="delete_account" value="1">
                <button type="submit" class="btn btn-red">Delete My Account</button>
            </div>
        </form>
    </div>

</div><?php // close settings-page ?>

<script>
document.getElementById('new_password').addEventListener('input', function() {
    var pwd = this.value;
    var fill = document.getElementById('strength-fill');
    var text = document.getElementById('strength-text');
    var score = 0;

    if (pwd.length >= 6) score += 1;
    if (pwd.length >= 8) score += 1;
    if (pwd.length >= 10) score += 1;
    if (/[a-z]/.test(pwd)) score += 1;
    if (/[A-Z]/.test(pwd)) score += 1;
    if (/[0-9]/.test(pwd)) score += 1;
    if (/[^a-zA-Z0-9]/.test(pwd)) score += 1;

    if (pwd.length === 0) {
        fill.style.width = '0';
        text.textContent = '';
    } else if (score < 3) {
        fill.style.width = '33%';
        fill.style.backgroundColor = '#e74c3c';
        text.textContent = 'Weak';
        text.style.color = '#e74c3c';
    } else if (score < 5) {
        fill.style.width = '66%';
        fill.style.backgroundColor = '#f39c12';
        text.textContent = 'Medium';
        text.style.color = '#f39c12';
    } else {
        fill.style.width = '100%';
        fill.style.backgroundColor = '#27ae60';
        text.textContent = 'Strong';
        text.style.color = '#27ae60';
    }
});
</script>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
