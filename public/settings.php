<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

requireLogin();

$acct_id = (int)$_SESSION['acct_id'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$validation_errors = [];
$password_errors = [];

$stmt = $pdo->prepare("SELECT username, first_name, surname, email_addr, birthdate FROM accounts WHERE acct_id = ?");
$stmt->execute([$acct_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "/logout.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                <div class="admin-form-group"></div>
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

            <div class="settings-actions">
                <input type="hidden" name="change_password" value="1">
                <button type="submit" class="btn btn-blue">Change Password</button>
            </div>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
