<?php

// 0. Start session before any session access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Pull in the core logic
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

// 2. If the user is already logged in, bypass the login screen
if (isset($_SESSION['acct_id'])) {
    header("Location: /itec106/game.php");
    exit;
}

$error = '';

// 3. Form Processing Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Pass the procedural $pdo connection into your Auth class
        $result = Auth::loginUser($pdo, $username, $password);
        
        if ($result === true) {
            header("Location: /itec106/game.php");
            exit;
        } else {
            $error = $result; // "Invalid username or password."
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

        <form class="auth-form" method="POST" action="/itec106/index.php">
            <input class="auth-input" type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <input class="auth-input" type="password" name="password" placeholder="Password" required>
            <button type="submit" class="btn btn-blue">Sign In</button>
        </form>

        <p class="auth-footer">
            No account? <a href="/itec106/register.php">Register</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>