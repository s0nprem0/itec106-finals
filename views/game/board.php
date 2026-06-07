<?php
$asset = $_SESSION['current_asset'];
$next = $_SESSION['next_asset'];
$balance = $_SESSION['balance'];
$start_money = $_SESSION['start_money'];
$round = $_SESSION['round'];
$diff = $_SESSION['difficulty'] ?? 'medium';
$diffConfig = DIFFICULTIES[$diff];
$diffLabel = $diffConfig['label'];
$diffDesc = $diffConfig['desc'];
$payout = $diffConfig['payout'];
$progress_pct = min(100, round(($balance / WIN_TARGET) * 100));
$profit = round($balance - $start_money, 2);

$diffClass = match($diff) {
    'easy'   => 'diff-easy',
    'hard'   => 'diff-hard',
    default => 'diff-medium',
};
?>

<div class="game-hud">
    <div class="game-hud-item">
        <span class="game-hud-label">Balance</span>
        <span class="game-hud-value game-hud-balance">$<?= number_format($balance, 0) ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">Round</span>
        <span class="game-hud-value"><?= $round ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">Difficulty</span>
        <span class="game-hud-diff <?= $diffClass ?>"><?= $diffLabel ?> &times;<?= $payout ?></span>
    </div>
    <div class="game-hud-item">
        <span class="game-hud-label">P/L</span>
        <span class="game-hud-value <?= $profit >= 0 ? 'game-profit-positive' : 'game-profit-negative' ?>">
            <?= $profit >= 0 ? '+' : '' ?>$<?= number_format(abs($profit), 0) ?>
        </span>
    </div>
</div>

<div class="game-progress">
    <div class="game-progress-bar">
        <div class="game-progress-fill <?= $progress_pct >= 100 ? 'game-progress-won' : '' ?>" style="width:<?= $progress_pct ?>%"></div>
    </div>
    <span class="game-progress-label">Goal: $<?= number_format(WIN_TARGET) ?></span>
</div>

<div class="game-instructions">
    <div class="game-instructions-content">
        <strong>How to Play:</strong> You'll see a hardware item with a price. Guess whether the <em>next</em> item will cost <strong>higher</strong> or <strong>lower</strong>. Place your bet each round. Reach $<?= number_format(WIN_TARGET) ?> to win.
        Current volatility: <strong><?= $diffDesc ?></strong>.
        <span class="game-instructions-keys">Keyboard: <kbd>H</kbd> Higher &middot; <kbd>L</kbd> Lower</span>
    </div>
</div>

<div class="game-board">
    <div class="card game-asset-card">
        <?php if ($asset['image_url']): ?>
            <img class="game-img" src="<?= htmlspecialchars($asset['image_url']) ?>" alt="<?= htmlspecialchars($asset['item_name']) ?>" onerror="this.classList.add('game-img-error');this.nextElementSibling.classList.add('game-img-error-shown')">
            <div class="game-img-placeholder game-img-error-fallback">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
            </div>
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

    <?php if ($guess_error): ?>
        <div class="game-error"><?= htmlspecialchars($guess_error) ?></div>
    <?php endif; ?>

    <div class="game-bet-area">
        <label class="game-bet-label">Your Bet</label>
        <div class="game-bet-row">
            <span class="game-bet-dollar">$</span>
            <input class="game-bet-input" type="number" id="bet-amount" name="bet" step="0.01" min="1" max="<?= $balance ?>" value="<?= min(500, $balance) ?>" required>
        </div>
        <div class="game-bet-presets">
            <button type="button" class="btn btn-sm game-bet-preset" data-amount="100">$100</button>
            <button type="button" class="btn btn-sm game-bet-preset" data-amount="500">$500</button>
            <button type="button" class="btn btn-sm game-bet-preset" data-amount="1000">$1k</button>
            <button type="button" class="btn btn-sm game-bet-preset" data-amount="5000">$5k</button>
            <button type="button" class="btn btn-sm game-bet-preset game-bet-allin" data-amount="<?= $balance ?>">All In</button>
        </div>
    </div>

    <div class="game-actions">
        <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-higher" class="game-action-form">
            <input type="hidden" name="guess" value="higher">
            <input type="hidden" name="bet" id="bet-higher" value="<?= min(500, $balance) ?>">
            <button type="submit" class="btn btn-green game-btn">↑ Higher</button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/game.php" id="form-lower" class="game-action-form">
            <input type="hidden" name="guess" value="lower">
            <input type="hidden" name="bet" id="bet-lower" value="<?= min(500, $balance) ?>">
            <button type="submit" class="btn btn-red game-btn">↓ Lower</button>
        </form>
    </div>

    <div class="game-bet-summary">
        Bet <strong>$<span id="display-bet"><?= number_format(min(500, $balance), 0) ?></span></strong>
        &times; <?= $payout ?>x payout
        &rarr; win <strong class="game-profit-positive">$<span id="display-win"><?= number_format(min(500, $balance) * $payout, 0) ?></span></strong>
    </div>
</div>

<script>
(function() {
    var input = document.getElementById('bet-amount');
    var btnHigher = document.querySelector('#bet-higher');
    var btnLower = document.querySelector('#bet-lower');
    var displayBet = document.getElementById('display-bet');
    var displayWin = document.getElementById('display-win');
    var payout = <?= $payout ?>;

    function updateBet(val) {
        if (val <= 0) val = 1;
        if (val > <?= $balance ?>) val = <?= $balance ?>;
        input.value = val;
        btnHigher.value = val;
        btnLower.value = val;
        displayBet.textContent = Number(val).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
        displayWin.textContent = Number((val * payout).toFixed(2)).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }

    input.addEventListener('input', function() { updateBet(parseFloat(this.value) || 0); });

    document.querySelectorAll('.game-bet-preset').forEach(function(p) {
        p.addEventListener('click', function() { updateBet(parseFloat(this.dataset.amount) || 0); });
    });

    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        var key = e.key.toLowerCase();
        if (key === 'h') document.getElementById('form-higher').querySelector('button').click();
        if (key === 'l') document.getElementById('form-lower').querySelector('button').click();
    });
})();
</script>
