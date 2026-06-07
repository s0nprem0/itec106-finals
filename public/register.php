<?php
// public/register.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

if (isset($_SESSION['acct_id'])) {
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $surname   = trim($_POST['surname'] ?? '');
    $email     = trim($_POST['email_addr'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif ($firstName && $surname && $email && $username && $birthdate && $password) {
        $result = Auth::registerUser($pdo, $firstName, $surname, $email, $username, $birthdate, $password);

        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="auth-container auth-container-narrow">
    <div class="card auth-card">
        <h2 class="auth-title">Create Account</h2>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-success"><?= htmlspecialchars($success) ?></div>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-blue btn-block">Go to Login</a>
        <?php else: ?>

            <form class="auth-form" method="POST" action="<?= BASE_URL ?>/register.php">
                <div class="auth-row">
                    <input class="auth-input" type="text" name="first_name" placeholder="First Name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    <input class="auth-input" type="text" name="surname" placeholder="Surname" required value="<?= htmlspecialchars($_POST['surname'] ?? '') ?>">
                </div>
                <input class="auth-input" type="email" name="email_addr" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email_addr'] ?? '') ?>">
                <div class="auth-row">
                    <input class="auth-input" type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <input class="auth-input" type="date" name="birthdate" required value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
                </div>
                <input class="auth-input" type="password" name="password" id="reg_password" placeholder="Password" minlength="6" required title="At least 6 characters">
                <div id="reg-password-strength" class="password-strength" style="margin-bottom:0.5rem">
                    <div class="password-strength-bar" style="max-width:100%">
                        <div class="password-strength-fill" id="reg-strength-fill"></div>
                    </div>
                    <span class="password-strength-text" id="reg-strength-text"></span>
                </div>
                <input class="auth-input" type="password" name="confirm_password" placeholder="Confirm Password" minlength="6" required title="Passwords must match">
                <button type="submit" class="btn btn-blue">Register</button>
            </form>

        <?php endif; ?>

        <p class="auth-footer">
            Already have an account? <a href="<?= BASE_URL ?>/index.php">Sign In</a>
        </p>
    </div>
</div>

<script>
document.getElementById('reg_password').addEventListener('input', function() {
    var pwd = this.value;
    var fill = document.getElementById('reg-strength-fill');
    var text = document.getElementById('reg-strength-text');
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
