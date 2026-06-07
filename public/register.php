<?php
// public/register.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

if (isset($_SESSION['acct_id'])) {
    header("Location: /itec106/game.php");
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

    if ($firstName && $surname && $email && $username && $birthdate && $password) {
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

<div class="auth-container" style="max-width: 500px;">
    <div class="card auth-card">
        <h2 class="auth-title">Create Account</h2>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-success"><?= htmlspecialchars($success) ?></div>
            <a href="/itec106/index.php" class="btn btn-blue" style="display: block; text-align: center; margin-top: 1rem;">Go to Login</a>
        <?php else: ?>

            <form class="auth-form" method="POST" action="/itec106/register.php">
                <div class="auth-row">
                    <input class="auth-input" type="text" name="first_name" placeholder="First Name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    <input class="auth-input" type="text" name="surname" placeholder="Surname" required value="<?= htmlspecialchars($_POST['surname'] ?? '') ?>">
                </div>
                <input class="auth-input" type="email" name="email_addr" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email_addr'] ?? '') ?>">
                <div class="auth-row">
                    <input class="auth-input" type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <input class="auth-input" type="date" name="birthdate" required value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>" style="color-scheme: dark;">
                </div>
                <input class="auth-input" type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn btn-blue">Register</button>
            </form>

        <?php endif; ?>

        <p class="auth-footer">
            Already have an account? <a href="/itec106/index.php">Sign In</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
