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
        unset($_SESSION['current_asset'], $_SESSION['next_asset'], $_SESSION['balance'], $_SESSION['round'], $_SESSION['game_over'], $_SESSION['game_won'], $_SESSION['last_guess'], $_SESSION['total_bet'], $_SESSION['total_won'], $_SESSION['start_money'], $_SESSION['final_profit']);
    }
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

if (empty($_SESSION['current_asset']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['difficulty'])) {
    initGame($pdo, $_POST['difficulty']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    $bet = filter_var($_POST['bet'] ?? 0, FILTER_VALIDATE_FLOAT);
    $result = processGuess($pdo, $_POST['guess'], $_SESSION['acct_id'], $bet);
    if (!empty($result['error'])) {
        $_SESSION['guess_error'] = $result['error'];
    }
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['continue'])) {
    unset($_SESSION['last_guess'], $_SESSION['guess_error']);
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

$is_new_record = !empty($_SESSION['is_new_record']);
unset($_SESSION['is_new_record']);

$guess_error = $_SESSION['guess_error'] ?? null;
unset($_SESSION['guess_error']);

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="game-page">

    <?php if (!empty($_SESSION['game_over'])): ?>

        <?php if (!empty($_SESSION['game_won'])): ?>
        <div class="card game-over-card game-won-card">
            <h1 class="game-over-title game-won-title">Target Acquired</h1>
            <p class="game-won-subtitle">You reached the $<?= number_format(WIN_TARGET) ?> target!</p>
        <?php else: ?>
        <div class="card game-over-card">
            <h1 class="game-over-title">System Failure</h1>
            <p class="game-over-desc">Your funds have been depleted. Connection terminated.</p>
        <?php endif; ?>

            <div class="game-over-stats">
                <div class="game-over-stat">
                    <span class="game-over-stat-label">Final Balance</span>
                    <span class="game-over-stat-value <?= ($_SESSION['final_profit'] ?? 0) >= 0 ? 'game-profit-positive' : 'game-profit-negative' ?>">
                        $<?= number_format($_SESSION['balance'] ?? 0, 2) ?>
                    </span>
                </div>
                <div class="game-over-stat">
                    <span class="game-over-stat-label">Profit / Loss</span>
                    <span class="game-over-stat-value <?= ($_SESSION['final_profit'] ?? 0) >= 0 ? 'game-profit-positive' : 'game-profit-negative' ?>">
                        <?= ($_SESSION['final_profit'] ?? 0) >= 0 ? '+' : '' ?>$<?= number_format(abs($_SESSION['final_profit'] ?? 0), 2) ?>
                    </span>
                </div>
                <div class="game-over-stat">
                    <span class="game-over-stat-label">Rounds Played</span>
                    <span class="game-over-stat-value"><?= $_SESSION['round'] ?? 0 ?></span>
                </div>
                <div class="game-over-stat">
                    <span class="game-over-stat-label">Total Wagered</span>
                    <span class="game-over-stat-value">$<?= number_format($_SESSION['total_bet'] ?? 0, 2) ?></span>
                </div>
            </div>

            <?php if ($is_new_record): ?>
                <p class="game-over-record">&#9733; <?= $_SESSION['game_won'] ? 'New Best Profit!' : 'New Worst Loss!' ?> &#9733;</p>
            <?php endif; ?>

            <p class="game-over-keys">Keyboard: <kbd>E</kbd> Easy &middot; <kbd>M</kbd> Medium &middot; <kbd>H</kbd> Hard</p>

            <div class="game-over-actions">
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=easy" class="btn btn-green game-over-btn" id="reboot-easy">New Game (Easy)</a>
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=medium" class="btn btn-blue game-over-btn" id="reboot-medium">New Game (Medium)</a>
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=hard" class="btn btn-red game-over-btn" id="reboot-hard">New Game (Hard)</a>
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
                <div class="game-result-arrow"><?= $lg['next_price'] > $lg['prev_price'] ? '&rarr;' : ($lg['next_price'] < $lg['prev_price'] ? '&larr;' : '&harr;') ?></div>
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

            <div class="game-result-bet-info">
                Bet: <strong>$<?= number_format($lg['bet'], 2) ?></strong>
                &times; <?= $lg['multiplier'] ?>x
                &rarr;
                <span class="<?= $was_correct ? 'game-profit-positive' : 'game-profit-negative' ?>">
                    <?= $was_correct ? '+' : '' ?>$<?= number_format(abs($lg['net']), 2) ?>
                </span>
            </div>

            <p class="game-result-keys">Press <kbd>Enter</kbd> or <kbd>Space</kbd> to continue</p>

            <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-continue">
                <button type="submit" name="continue" value="1" class="btn btn-blue game-continue-btn" autofocus>Continue</button>
            </form>
        </div>

    <?php elseif (empty($_SESSION['current_asset'])): ?>

        <div class="card game-start-card">
            <h1 class="game-start-title">Mission Briefing</h1>
            <p class="game-start-desc">
                Start with a capital fund and predict hardware price movements to grow your wealth.
                Reach $<?= number_format(WIN_TARGET) ?> to win. Lose it all and your run is over.
            </p>
            <p class="game-start-disclaimer">All in-game currency is virtual and has no real-world value.</p>

            <form class="game-diff-form" method="POST" action="<?= BASE_URL ?>/game.php">
                <label class="game-diff-label">Select Difficulty &amp; Starting Capital:</label>
                <div class="game-diff-options">
                    <button type="submit" name="difficulty" value="easy"   class="btn btn-green game-diff-btn">Easy &middot; $15,000 &middot; 1.5x</button>
                    <button type="submit" name="difficulty" value="medium" class="btn btn-blue  game-diff-btn">Medium &middot; $10,000 &middot; 2.0x</button>
                    <button type="submit" name="difficulty" value="hard"   class="btn btn-red   game-diff-btn">Hard &middot; $5,000 &middot; 3.0x</button>
                </div>
            </form>
        </div>

    <?php else: ?>

        <?php require_once __DIR__ . '/../views/game/board.php'; ?>

    <?php endif; ?>

    <p class="game-legal-note">All in-game currency is virtual and has no real-world value.</p>

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
