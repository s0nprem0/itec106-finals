<?php

// Force start the session globally across all files that include this header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tech Spec Showdown</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var toggle = document.querySelector('.nav-dropdown-toggle');
        var dropdown = document.querySelector('.nav-dropdown');
        if (toggle && dropdown) {
            toggle.addEventListener('click', function(e) { e.stopPropagation(); dropdown.classList.toggle('nav-dropdown--open'); });
            document.addEventListener('click', function(e) { if (!dropdown.contains(e.target)) dropdown.classList.remove('nav-dropdown--open'); });
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { dropdown.classList.remove('nav-dropdown--open'); toggle.focus(); } });
        }
        document.querySelectorAll('form').forEach(function(f) {
            f.addEventListener('submit', function() {
                var btns = f.querySelectorAll('button[type="submit"], input[type="submit"]');
                setTimeout(function() { btns.forEach(function(b) { b.disabled = true; }); }, 0);
            });
        });
    });
    </script>
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
        <a href="<?= BASE_URL ?>/<?= isset($_SESSION['acct_id']) ? 'game.php' : 'index.php' ?>" class="site-logo">Tech Spec Showdown</a>
        <nav class="site-nav">
            <?php if (isset($_SESSION['acct_id'])): ?>
                <a href="<?= BASE_URL ?>/game.php" class="nav-link <?= $current_page === 'game' ? 'nav-link-active' : '' ?>">Play</a>
                <a href="<?= BASE_URL ?>/leaderboard.php" class="nav-link <?= $current_page === 'leaderboard' ? 'nav-link-active' : '' ?>">Leaderboard</a>

                <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'moderator'])): ?>
                    <a href="<?= BASE_URL ?>/admin.php" class="nav-link nav-link-admin <?= $current_page === 'admin' ? 'nav-link-active' : '' ?>">Admin</a>
                <?php endif; ?>

                <span class="nav-divider"></span>

                <div class="nav-dropdown">
                    <span class="nav-link nav-dropdown-toggle <?= in_array($current_page, ['profile', 'settings']) ? 'nav-link-active' : '' ?>" tabindex="0" role="button" aria-haspopup="true">
                        Account <span class="nav-dropdown-arrow">&#9662;</span>
                    </span>
                    <div class="nav-dropdown-menu">
                        <a href="<?= BASE_URL ?>/profile.php" class="nav-dropdown-item <?= $current_page === 'profile' ? 'nav-dropdown-item-active' : '' ?>">Profile</a>
                        <a href="<?= BASE_URL ?>/settings.php" class="nav-dropdown-item <?= $current_page === 'settings' ? 'nav-dropdown-item-active' : '' ?>">Settings</a>
                        <div class="nav-dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/logout.php" class="nav-dropdown-item nav-dropdown-item-logout">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/index.php" class="nav-link nav-link-login <?= $current_page === 'index' ? 'nav-link-active' : '' ?>">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">