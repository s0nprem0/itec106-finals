<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="auth-container">
    <div class="card auth-card">
        <h2 class="auth-title">Reset Password</h2>

        <p style="color: var(--flat-subtext); font-size: 0.85rem; text-align: center; line-height: 1.4; margin-bottom: 1rem;">
            Password reset is not available in this environment.
            Please contact your system administrator to reset your password.
        </p>

        <div class="auth-form-actions" style="justify-content: center;">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-blue">Return to Login</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
