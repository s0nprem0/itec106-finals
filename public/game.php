<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/game_engine.php';

requireLogin();

if (isset($_GET['restart'])) {
    $diff = $_GET['difficulty'] ?? null;
    if ($diff && isset(DIFFICULTIES[$diff])) {
        initGame($pdo, $diff);
    } else {
        unset($_SESSION['current_asset'], $_SESSION['next_asset'], $_SESSION['score'], $_SESSION['lives'], $_SESSION['round'], $_SESSION['game_over']);
    }
    session_write_close();
    header("Location: /itec106/game.php");
    exit;
}

if (empty($_SESSION['current_asset']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['difficulty'])) {
    initGame($pdo, $_POST['difficulty']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    processGuess($pdo, $_POST['guess']);
    session_write_close();
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

            <div class="game-over-actions">
                <a href="/itec106/game.php?restart=true&difficulty=easy" class="btn btn-green game-over-btn">Reboot (Easy)</a>
                <a href="/itec106/game.php?restart=true&difficulty=medium" class="btn btn-blue game-over-btn">Reboot (Medium)</a>
                <a href="/itec106/game.php?restart=true&difficulty=hard" class="btn btn-red game-over-btn">Reboot (Hard)</a>
            </div>
        </div>

    <?php elseif (empty($_SESSION['current_asset'])): ?>

        <div class="card game-start-card">
            <h1 class="game-start-title">Mission Briefing</h1>
            <p class="game-start-desc">
                Guess whether the next hardware component's price will be higher or lower. Three errors and your connection is terminated.
            </p>

            <form class="game-diff-form" method="POST" action="/itec106/game.php">
                <label class="game-diff-label">Select Difficulty:</label>
                <div class="game-diff-options">
                    <button type="submit" name="difficulty" value="easy"   class="btn btn-green game-diff-btn">Easy ±10%</button>
                    <button type="submit" name="difficulty" value="medium" class="btn btn-blue  game-diff-btn">Medium ±20%</button>
                    <button type="submit" name="difficulty" value="hard"   class="btn btn-red   game-diff-btn">Hard ±35%</button>
                </div>
            </form>
        </div>

    <?php else: ?>

        <?php require_once __DIR__ . '/../views/game/board.php'; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>
