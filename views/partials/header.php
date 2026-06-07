<?php

// Force start the session globally across all files that include this header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tech Spec Showdown</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
        <h1 class="site-logo">Tech Spec Showdown</h1>
        <nav class="site-nav">
            <?php if (isset($_SESSION['acct_id'])): ?>
                <a href="<?= BASE_URL ?>/game.php" class="nav-link">Play</a>
                <a href="<?= BASE_URL ?>/leaderboard.php" class="nav-link">Leaderboard</a>
                <a href="<?= BASE_URL ?>/profile.php" class="nav-link">Profile</a>
                <a href="<?= BASE_URL ?>/settings.php" class="nav-link">Settings</a>
                <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'moderator'])): ?>
                    <a href="<?= BASE_URL ?>/admin.php" class="nav-link nav-link-admin">Admin</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-link-logout">Logout</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/index.php" class="nav-link nav-link-login">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">