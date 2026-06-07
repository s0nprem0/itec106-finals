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
    <link rel="stylesheet" href="/itec106/public/css/style.css">
</head>
<body>

<header style="background-color: var(--macchiato-surface); padding: 1rem; border-bottom: 2px solid var(--macchiato-base);">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
        <h1 style="color: var(--macchiato-blue); font-size: 1.5rem;">Tech Spec Showdown</h1>
        <nav>
            <?php if (isset($_SESSION['acct_id'])): ?>
                <a href="/itec106/game.php" style="color: var(--macchiato-text); margin-right: 15px; text-decoration: none;">Play</a>
                <a href="/itec106/leaderboard.php" style="color: var(--macchiato-text); margin-right: 15px; text-decoration: none;">Leaderboard</a>
                <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="/itec106/admin.php" style="color: var(--macchiato-green); margin-right: 15px; text-decoration: none;">Admin</a>
                <?php endif; ?>
                <a href="/itec106/logout.php" style="color: var(--macchiato-red); text-decoration: none;">Logout</a>
            <?php else: ?>
                <a href="/itec106/index.php" style="color: var(--macchiato-blue); text-decoration: none;">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">