<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

Auth::tryRememberLogin($pdo);

if (isset($_SESSION['acct_id'])) {
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($username && $password) {
        $result = Auth::loginUser($pdo, $username, $password, $remember);

        if ($result === true) {
            session_write_close();
            header("Location: " . BASE_URL . "/game.php");
            exit;
        } else {
            $error = $result;
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// 4. Load the UI Header
require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="auth-container">
    <div class="card auth-card">
        <h2 class="auth-title">Sign In</h2>
        
        <?php if ($error): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="<?= BASE_URL ?>/index.php">
            <input class="auth-input" type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <input class="auth-input" type="password" name="password" placeholder="Password" required>
            <label class="auth-checkbox">
                <input type="checkbox" name="remember" value="1">
                <span>Remember me for 30 days</span>
            </label>
            <button type="submit" class="btn btn-blue">Sign In</button>
            <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-link" style="text-align:center;font-size:0.8rem;display:block;">Forgot password?</a>
        </form>

        <p class="auth-footer">
            No account? <a href="<?= BASE_URL ?>/register.php">Register</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>