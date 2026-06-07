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

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="card">
            <div class="auth-header">
                <div class="auth-logo">TS</div>
                <div class="auth-title">Welcome Back</div>
                <div class="auth-subtitle">Sign in to continue to Tech Spec Showdown</div>
            </div>
            
            <?php if ($error): ?>
                <div class="auth-error">
                    <span>!</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="/itec106/index.php" novalidate>
                <div class="auth-field">
                    <label class="auth-label" for="username">Username</label>
                    <input class="auth-input" type="text" id="username" name="username" placeholder="Your username" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                
                <div class="auth-field">
                    <label class="auth-label" for="password">Password</label>
                    <div class="auth-input-wrapper">
                        <input class="auth-input" type="password" id="password" name="password" placeholder="Your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility" tabindex="-1">&#128065;</button>
                    </div>
                </div>
                
                <button type="submit" class="auth-btn" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="spinner"></span>
                </button>
            </form>

            <div class="auth-footer">
                Need an account? <a href="/itec106/register.php">Register</a>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('loginForm');
    const btn = document.getElementById('loginBtn');
    const toggle = document.getElementById('togglePassword');
    const pw = document.getElementById('password');
    let loading = false;

    toggle.addEventListener('click', function() {
        const type = pw.getAttribute('type') === 'password' ? 'text' : 'password';
        pw.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '&#128065;' : '&#128064;';
    });

    form.addEventListener('submit', function() {
        if (loading) return;
        loading = true;
        btn.classList.add('loading');
        btn.disabled = true;
    });
})();
</script>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>