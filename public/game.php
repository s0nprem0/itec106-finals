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
        unset($_SESSION['current_asset'], $_SESSION['next_asset'], $_SESSION['score'], $_SESSION['lives'], $_SESSION['round'], $_SESSION['game_over'], $_SESSION['last_guess']);
    }
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

if (empty($_SESSION['current_asset']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['difficulty'])) {
    initGame($pdo, $_POST['difficulty']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    processGuess($pdo, $_POST['guess'], $_SESSION['acct_id']);
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['continue'])) {
    unset($_SESSION['last_guess']);
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

$best_streak = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(streak), 0) FROM scores WHERE acct_id = ?");
    $stmt->execute([$_SESSION['acct_id']]);
    $best_streak = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $best_streak = 0;
}

$is_new_record = !empty($_SESSION['is_new_record']);
unset($_SESSION['is_new_record']);

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="game-page">

    <?php if (!empty($_SESSION['game_over'])): ?>

        <div class="card game-over-card">
            <h1 class="game-over-title">System Failure</h1>

            <h2 class="game-over-score">
                Final Intelligence Streak: <span class="game-over-streak"><?= $_SESSION['score'] ?></span>
            </h2>

            <?php if ($is_new_record): ?>
                <p class="game-over-record">&#9733; New Personal Record! &#9733;</p>
            <?php endif; ?>

            <p class="game-over-desc">
                Connection terminated. Your performance data has been securely logged to the mainframe.
            </p>

            <p class="game-over-keys">Keyboard: <kbd>E</kbd> Easy &middot; <kbd>M</kbd> Medium &middot; <kbd>H</kbd> Hard</p>

            <div class="game-over-actions">
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=easy" class="btn btn-green game-over-btn" id="reboot-easy">Reboot (Easy)</a>
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=medium" class="btn btn-blue game-over-btn" id="reboot-medium">Reboot (Medium)</a>
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=hard" class="btn btn-red game-over-btn" id="reboot-hard">Reboot (Hard)</a>
            </div>
        </div>

    <?php elseif (!empty($_SESSION['last_guess'])):

        $lg = $_SESSION['last_guess'];
        $was_correct = $lg['result'] === 'correct';
        $price_diff = abs($lg['prev_price'] - $lg['next_price']);
        $price_diff_fmt = '$' . number_format($price_diff, 2);
    ?>

        <div class="card game-result-card <?= $was_correct ? 'game-result-correct' : 'game-result-incorrect' ?>">
            <div class="game-result-badge"><?= $was_correct ? '&#10003;' : '&#10007;' ?></div>
            <div class="game-result-verdict"><?= $was_correct ? 'Correct' : 'Incorrect' ?></div>

            <div class="game-result-compare">
                <div class="game-result-item">
                    <div class="game-result-item-name"><?= htmlspecialchars($lg['prev_item']) ?></div>
                    <div class="game-result-item-price">$<?= number_format($lg['prev_price'], 2) ?></div>
                </div>
                <div class="game-result-arrow"><?= $lg['guess'] === 'higher' ? '&rarr;' : '&larr;' ?></div>
                <div class="game-result-item">
                    <div class="game-result-item-name"><?= htmlspecialchars($lg['next_item']) ?></div>
                    <div class="game-result-item-price">$<?= number_format($lg['next_price'], 2) ?></div>
                </div>
            </div>

            <div class="game-result-detail">
                You said <strong><?= $lg['guess'] === 'higher' ? 'Higher &#8593;' : 'Lower &#8595;' ?></strong>
                &mdash; <?= $was_correct ? 'price went ' . ($lg['guess'] === 'higher' ? 'up' : 'down') : 'price went ' . ($lg['guess'] === 'higher' ? 'down' : 'up') ?>
                by <?= $price_diff_fmt ?>
            </div>

            <p class="game-result-keys">Press <kbd>Enter</kbd> or <kbd>Space</kbd> to continue</p>

            <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-continue">
                <button type="submit" name="continue" value="1" class="btn btn-blue game-continue-btn">Continue</button>
            </form>
        </div>

    <?php elseif (empty($_SESSION['current_asset'])): ?>

        <div class="card game-start-card">
            <h1 class="game-start-title">Mission Briefing</h1>
            <p class="game-start-desc">
                Guess whether the next hardware component's price will be higher or lower. Three errors and your connection is terminated.
            </p>

            <form class="game-diff-form" method="POST" action="<?= BASE_URL ?>/game.php">
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

<script>
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    var key = e.key.toLowerCase();

    var cont = document.getElementById('form-continue');
    if (cont && (key === 'enter' || key === ' ')) {
        cont.querySelector('button').click();
        return;
    }

    var btn;
    if (key === 'e') btn = document.getElementById('reboot-easy');
    if (key === 'm') btn = document.getElementById('reboot-medium');
    if (key === 'h') btn = document.getElementById('reboot-hard');
    if (btn) btn.click();
});
</script>
