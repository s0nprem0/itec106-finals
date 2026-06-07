<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/game_engine.php';

requireLogin();

// ====================== RESTART LOGIC ======================
if (isset($_GET['restart'])) {
    $diff = $_GET['difficulty'] ?? 'medium';
    
    // Full cleanup of all game-related session data
    unset(
        $_SESSION['current_asset'], $_SESSION['next_asset'], $_SESSION['balance'],
        $_SESSION['round'], $_SESSION['game_over'], $_SESSION['game_won'],
        $_SESSION['last_guess'], $_SESSION['total_bet'], $_SESSION['total_won'],
        $_SESSION['start_money'], $_SESSION['final_profit'], $_SESSION['asset_sequence'],
        $_SESSION['current_index'], $_SESSION['game_id'], $_SESSION['seen_assets'],
        $_SESSION['guess_error'], $_SESSION['is_new_record']
    );
    
    if (isset(DIFFICULTIES[$diff])) {
        initGame($pdo, $diff);
    } else {
        initGame($pdo, 'medium');
    }
    
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

// ====================== START NEW GAME ======================
if (empty($_SESSION['current_asset']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['difficulty'])) {
    initGame($pdo, $_POST['difficulty']);
}

// ====================== PROCESS GUESS ======================
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

// ====================== CONTINUE AFTER RESULT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['continue'])) {
    unset($_SESSION['last_guess'], $_SESSION['guess_error']);
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

// ====================== CASH OUT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cashout'])) {
    $result = cashOut($pdo, $_SESSION['acct_id']);
    if (!empty($result['error'])) {
        $_SESSION['guess_error'] = $result['error'];
    }
    session_write_close();
    header("Location: " . BASE_URL . "/game.php");
    exit;
}

// ====================== CLEANUP FLASH MESSAGES ======================
$is_new_record = !empty($_SESSION['is_new_record']);
unset($_SESSION['is_new_record']);

$guess_error = $_SESSION['guess_error'] ?? null;
unset($_SESSION['guess_error']);

require_once __DIR__ . '/../views/partials/header.php';
?>

<div class="game-page">

    <?php if (!empty($_SESSION['game_over'])): ?>
        <!-- GAME OVER SCREEN -->
        <?php 
        $endReason = $_SESSION['game_end_reason'] ?? 'completed';
        $isCashOut = $endReason === 'cashout';
        ?>
        <?php if ($isCashOut): ?>
        <div class="card game-over-card game-cashout-card">
            <h1 class="game-over-title game-cashout-title">Mission Aborted</h1>
            <p class="game-cashout-subtitle">You cashed out with $<?= number_format($_SESSION['balance'] ?? 0, 2) ?>.</p>
        <?php elseif (!empty($_SESSION['game_won'])): ?>
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
                    <span class="game-over-stat-value">$<?= number_format($_SESSION['balance'] ?? 0, 2) ?></span>
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

            <div class="game-over-actions">
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=easy"   class="btn btn-green" id="reboot-easy">New Game (Easy)</a>
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=medium" class="btn btn-blue"  id="reboot-medium">New Game (Medium)</a>
                <a href="<?= BASE_URL ?>/game.php?restart=true&difficulty=hard"   class="btn btn-red"   id="reboot-hard">New Game (Hard)</a>
            </div>
        </div>

        <?php elseif (!empty($_SESSION['last_guess'])):
            
            $lg = $_SESSION['last_guess'];
            $was_correct = $lg['result'] === 'correct';
            $was_tie = $lg['result'] === 'tie';
            if ($was_tie): ?>
            <div class="card game-result-card game-result-tie">
                <div class="game-result-badge">&#8646;</div>
                <div class="game-result-verdict">Tie</div>
                <div class="game-result-compare">
                    <div class="game-result-item">
                        <div class="game-result-item-name"><?= htmlspecialchars($lg['prev_item']) ?></div>
                        <div class="game-result-item-price">$<?= number_format($lg['prev_price'], 2) ?></div>
                    </div>
                    <div class="game-result-arrow">&harr;</div>
                    <div class="game-result-item">
                        <div class="game-result-item-name"><?= htmlspecialchars($lg['next_item']) ?></div>
                        <div class="game-result-item-price">$<?= number_format($lg['next_price'], 2) ?></div>
                    </div>
                </div>
                <div class="game-result-detail">
                    Prices are identical. Your bet is returned.
                </div>
                <div class="game-result-bet-info">
                    Bet: <strong>$<?= number_format($lg['bet'], 2) ?></strong>
                    &rarr;
                    <span class="game-profit-neutral">$<?= number_format($lg['bet'], 2) ?> returned</span>
                </div>
                <p class="game-result-keys">Press <kbd>Enter</kbd> or <kbd>Space</kbd> to continue</p>
                <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-continue">
                    <button type="submit" name="continue" value="1" class="btn btn-blue game-continue-btn" autofocus>Continue</button>
                </form>
            </div>
            <?php return; endif;
            $price_diff = abs($lg['prev_price'] - $lg['next_price']);
            $price_diff_fmt = '$' . number_format($price_diff, 2); ?>
            <?php 
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
                    <div class="game-result-arrow">
                        <?= $lg['next_price'] > $lg['prev_price'] ? '&rarr;' : ($lg['next_price'] < $lg['prev_price'] ? '&larr;' : '&harr;') ?>
                    </div>
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
                        <?= $was_correct ? '+' : '' ?>$<?= number_format(abs($lg['net'] ?? 0), 2) ?>
                    </span>
                </div>
                <p class="game-result-keys">Press <kbd>Enter</kbd> or <kbd>Space</kbd> to continue</p>
                <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-continue">
                    <button type="submit" name="continue" value="1" class="btn btn-blue game-continue-btn" autofocus>Continue</button>
                </form>
            </div>

<?php elseif (empty($_SESSION['current_asset'])): ?>
    <!-- START SCREEN - Improved with Clear Instructions -->
    <div class="card game-start-card">
        <div class="card-header">
            <h1 class="game-start-title">Mission Briefing</h1>
        </div>
        
        <div class="card-body">
            <p class="game-start-desc">
                Grow your starting capital to <strong>$<?= number_format(WIN_TARGET) ?></strong> within 20 rounds by correctly predicting hardware price movements.
            </p>

            <!-- Improved Instructions for New Users -->
            <div class="game-instructions">
                <h3>How to Play</h3>
                <p><strong>Objective:</strong> Reach the target balance before going broke or running out of rounds.</p>
                
                <p><strong>Volatility Explained:</strong></p>
                <ul class="volatility-list">
                    <li><strong>Easy</strong> (±18%) — Smaller price swings. Easier to predict.</li>
                    <li><strong>Medium</strong> (±28%) — Moderate swings. Balanced challenge.</li>
                    <li><strong>Hard</strong> (±42%) — Large price movements. High risk, high reward.</li>
                </ul>
                
                <p>Each round you can bet up to <strong>25% of your current balance</strong> and choose whether the next item's price will go <strong>Higher</strong> or <strong>Lower</strong>.</p>
            </div>

            <p class="game-start-disclaimer">All in-game currency is virtual and has no real-world value.</p>

            <!-- Difficulty Selection -->
            <form class="game-diff-form" method="POST" action="<?= BASE_URL ?>/game.php">
                <label class="game-diff-label">Choose Your Difficulty:</label>
                <div class="game-diff-options">
                    <button type="submit" name="difficulty" value="easy"   class="btn btn-green game-diff-btn">
                        Easy — $18,000 — 1.45x
                    </button>
                    <button type="submit" name="difficulty" value="medium" class="btn btn-blue game-diff-btn">
                        Medium — $12,000 — 1.75x
                    </button>
                    <button type="submit" name="difficulty" value="hard"   class="btn btn-red game-diff-btn">
                        Hard — $6,500 — 2.4x
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>
        <!-- ACTIVE GAME BOARD -->
        <?php require_once __DIR__ . '/../views/game/board.php'; ?>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../views/partials/footer.php'; ?>

<!-- Keyboard Shortcuts -->
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