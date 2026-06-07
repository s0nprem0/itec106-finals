<?php
$asset = $_SESSION['current_asset'];
$next = $_SESSION['next_asset'];
$score = $_SESSION['score'];
$lives = $_SESSION['lives'];
$round = $_SESSION['round'];
$diff = $_SESSION['difficulty'] ?? 'medium';
$diffConfig = DIFFICULTIES[$diff];
$diffLabel = $diffConfig['label'];
$diffDesc = $diffConfig['desc'];

$diffClass = match($diff) {
    'easy'   => 'diff-easy',
    'hard'   => 'diff-hard',
    default => 'diff-medium',
};
?>

<div class="game-hud">
    <div class="game-hud-item">
        <span class="game-hud-label">Score</span>
        <span class="game-hud-value"><?= $score ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">Best</span>
        <span class="game-hud-value game-hud-best"><?= $best_streak ?: '&mdash;' ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">Round</span>
        <span class="game-hud-value"><?= $round ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">Difficulty</span>
        <span class="game-hud-diff <?= $diffClass ?>"><?= $diffLabel ?> <?= $diffDesc ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">Lives</span>
        <span class="game-hearts">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <span class="game-heart <?= $i < $lives ? 'filled' : 'empty' ?>"><?= $i < $lives ? '♥' : '♡' ?></span>
            <?php endfor; ?>
        </span>
    </div>
</div>

<?php if ($round === 1): ?>
<div class="game-instructions">
    <div class="game-instructions-content">
        <strong>How to Play:</strong> You'll see a hardware item with a price. Guess whether the <em>next</em> item will cost <strong>higher</strong> or <strong>lower</strong>. Correct guesses extend your streak. Three wrong guesses and it's game over.
        Current volatility: <strong><?= $diffDesc ?></strong>.
        <span class="game-instructions-keys">Keyboard: <kbd>H</kbd> Higher &middot; <kbd>L</kbd> Lower</span>
    </div>
</div>
<?php endif; ?>

<div class="game-board">
    <div class="card game-asset-card">
        <?php if ($asset['image_url']): ?>
            <img class="game-img" src="<?= htmlspecialchars($asset['image_url']) ?>" alt="<?= htmlspecialchars($asset['item_name']) ?>">
        <?php else: ?>
            <div class="game-img-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
            </div>
        <?php endif; ?>

        <div class="game-asset-name"><?= htmlspecialchars($asset['item_name']) ?></div>
        <div class="game-price">$<?= number_format($asset['current_price'], 2) ?></div>

        <div class="game-asset-meta">
            <span class="game-volatility <?= $asset['current_price'] >= $asset['price'] ? 'up' : 'down' ?>">
                <?= $asset['current_price'] >= $asset['price'] ? '▲' : '▼' ?> <?= $asset['volatility'] ?>
            </span>
            <span class="game-category"><?= htmlspecialchars($asset['category']) ?></span>
        </div>
    </div>

    <p class="game-prompt">Will the next item cost more or less?</p>

    <div class="game-actions">
        <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-higher">
            <input type="hidden" name="guess" value="higher">
            <button type="submit" class="btn btn-green game-btn">↑ Higher</button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-lower">
            <input type="hidden" name="guess" value="lower">
            <button type="submit" class="btn btn-red game-btn">↓ Lower</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    var key = e.key.toLowerCase();
    if (key === 'h') document.getElementById('form-higher').querySelector('button').click();
    if (key === 'l') document.getElementById('form-lower').querySelector('button').click();
});
</script>
