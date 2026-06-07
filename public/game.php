<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/game_engine.php';

requireLogin();

if (isset($_GET['restart'])) {
    initGame($pdo);
    header("Location: /itec106/game.php");
    exit;
}

if (empty($_SESSION['current_asset'])) {
    initGame($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    processGuess($pdo, $_POST['guess']);
    header("Location: /itec106/game.php");
    exit;
}

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="game-page">

    <?php if (!empty($_SESSION['game_over'])): ?>

        <div class="card game-over-card">
            <h1 class="game-over-title">System Failure</h1>

            <h2 class="game-over-score">
                Final Intelligence Streak: <span class="game-over-streak"><?= $_SESSION['score'] ?></span>
            </h2>

            <p class="game-over-desc">
                Connection terminated. Your performance data has been securely logged to the mainframe.
            </p>

            <a href="/itec106/game.php?restart=true" class="btn btn-blue game-over-btn">Reboot System (Play Again)</a>
        </div>

    <?php else: ?>

        <?php require_once __DIR__ . '/../views/game/board.php'; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
