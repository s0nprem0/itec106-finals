<?php
require_once __DIR__ . '/database.php';

const WIN_TARGET = 50000;
const MAX_ROUNDS = 20;

// Improved DIFFICULTIES
const DIFFICULTIES = [
    'easy'   => [
        'volatility' => 18, 
        'start_money' => 15000, 
        'payout' => 1.45, 
        'label' => 'Easy', 
        'desc' => 'Low volatility, more predictable'
    ],
    'medium' => [
        'volatility' => 28, 
        'start_money' => 10000, 
        'payout' => 1.75, 
        'label' => 'Medium', 
        'desc' => 'Moderate volatility, balanced gameplay'
    ],
    'hard'   => [
        'volatility' => 42, 
        'start_money' => 6500,  
        'payout' => 2.4, 
        'label' => 'Hard', 
        'desc' => 'High volatility, risky but rewarding'
    ],
];

/**
 * Fetches a random asset with volatility applied.
 */
function getRandomAsset($pdo, $excludeIds = [], $volatilityRange = 20, $currentBasePrice = null) {
    if (!is_array($excludeIds)) $excludeIds = $excludeIds ? [$excludeIds] : [];

    $item = false;

    // Proximity-based selection for realism
    if (!empty($excludeIds) && $currentBasePrice !== null) {
        $minPrice = $currentBasePrice * 0.5;
        $maxPrice = $currentBasePrice * 1.5;
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $params = array_merge([$minPrice, $maxPrice], $excludeIds);
        
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE price BETWEEN ? AND ? AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1");
        $stmt->execute($params);
        $item = $stmt->fetch();
    }

    if (!$item && !empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1");
        $stmt->execute($excludeIds);
        $item = $stmt->fetch();
    }

    if (!$item) {
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY RAND() LIMIT 1");
        $item = $stmt->fetch();
    }

    if ($item) {
        $volatility = rand(-$volatilityRange, $volatilityRange) / 100;
        $multiplier = 1 + $volatility;
        $item['current_price'] = round($item['price'] * $multiplier, 2);
        $item['volatility'] = ($volatility >= 0 ? "+" : "") . round($volatility * 100) . "%";
    }
    return $item;
}

/** Pre-generate full sequence for fairness and reproducibility.
 *  Generates MAX_ROUNDS+1 items so the final round has a real "next" asset to compare. */
function pregenerateAssetSequence($pdo, $difficulty = 'medium', $numRounds = MAX_ROUNDS) {
    $config = DIFFICULTIES[$difficulty] ?? DIFFICULTIES['medium'];
    $sequence = [];
    $seen = [];
    $current = getRandomAsset($pdo, [], $config['volatility']);
    $sequence[] = $current;
    $seen[] = $current['id'];

    for ($i = 1; $i <= $numRounds; $i++) {
        $next = getRandomAsset($pdo, $seen, $config['volatility'], $current['price']);
        $sequence[] = $next;
        $seen[] = $next['id'];
        if (count($seen) > 25) array_shift($seen);
        $current = $next;
    }
    return $sequence;
}

function initGame($pdo, $difficulty = 'medium') {
    $config = DIFFICULTIES[$difficulty] ?? DIFFICULTIES['medium'];

    $_SESSION['difficulty'] = $difficulty;
    $_SESSION['start_money'] = $config['start_money'];
    $_SESSION['balance'] = $config['start_money'];
    $_SESSION['total_bet'] = 0;
    $_SESSION['total_won'] = 0;

    $_SESSION['asset_sequence'] = pregenerateAssetSequence($pdo, $difficulty);
    $_SESSION['current_index'] = 0;

    $_SESSION['current_asset'] = $_SESSION['asset_sequence'][0];
    $_SESSION['next_asset'] = $_SESSION['asset_sequence'][1];

    $_SESSION['round'] = 1;
    $_SESSION['game_over'] = false;
    $_SESSION['game_won'] = false;

    unset($_SESSION['last_guess'], $_SESSION['error_reason']);
}

/**
 * Process player guess with dynamic bet limits
 */
function processGuess($pdo, $guess, $acctId, $betAmount) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game is already over. Start a new game."];
    }
    if (empty($_SESSION['asset_sequence']) || !isset($_SESSION['current_index'])) {
        return ['error' => "Invalid game state. Please restart the game."];
    }

    $betAmount = round(filter_var($betAmount, FILTER_VALIDATE_FLOAT) ?? 0, 2);
    $balance = $_SESSION['balance'];

    // === DYNAMIC BET LIMIT ===
    $difficulty = $_SESSION['difficulty'] ?? 'medium';
    $maxBetPercent = 0.25; // Default 25%

    if ($difficulty === 'easy') {
        $maxBetPercent = 0.20;
    } elseif ($difficulty === 'hard') {
        $maxBetPercent = 0.35;     // More aggressive on Hard
    }

    // Reduce max bet in late game to avoid huge swings
    if ($_SESSION['round'] >= 16) {
        $maxBetPercent = min($maxBetPercent, 0.20);
    }

    $maxAllowedBet = round($balance * $maxBetPercent, 2);

    if ($betAmount <= 0 || $betAmount > $balance) {
        return ['error' => "Bet must be between $0.01 and your current balance."];
    }
    if ($betAmount > $maxAllowedBet) {
        return ['error' => "Maximum bet for this difficulty/round is " . 
                          round($maxBetPercent * 100) . 
                          "% of balance ($" . number_format($maxAllowedBet, 2) . ")."];
    }

    $config = DIFFICULTIES[$difficulty];
    $currentPrice = $_SESSION['current_asset']['current_price'];
    $nextPrice = $_SESSION['next_asset']['current_price'];

    $isHigher = $nextPrice > $currentPrice;
    $isLower = $nextPrice < $currentPrice;
    $isTie = $nextPrice == $currentPrice;

    // Tie = push (no bet loss/win)
    if ($isTie) {
        $_SESSION['last_guess'] = [
            'prev_item' => $_SESSION['current_asset']['item_name'],
            'prev_price' => $currentPrice,
            'next_item' => $_SESSION['next_asset']['item_name'],
            'next_price' => $nextPrice,
            'guess' => $guess,
            'bet' => $betAmount,
            'multiplier' => $config['payout'],
            'result' => 'tie',
            'winnings' => $betAmount,
            'net' => 0,
        ];
        $_SESSION['round'] = $currentRound + 1;
        $_SESSION['current_index']++;
        $_SESSION['current_asset'] = $_SESSION['next_asset'];
        $_SESSION['next_asset'] = $_SESSION['asset_sequence'][$_SESSION['current_index'] + 1] ?? $_SESSION['current_asset'];
        return ['success' => true, 'tie' => true];
    }

    $result = ($guess === "higher" && $isHigher) || ($guess === "lower" && $isLower);

    // Record guess details
    $_SESSION['last_guess'] = [
        'prev_item' => $_SESSION['current_asset']['item_name'],
        'prev_price' => $currentPrice,
        'next_item' => $_SESSION['next_asset']['item_name'],
        'next_price' => $nextPrice,
        'guess' => $guess,
        'bet' => $betAmount,
        'multiplier' => $config['payout'],
        'result' => $result ? 'correct' : 'incorrect',
    ];

    if ($result) {
        $winnings = round($betAmount * $config['payout'], 2);
        $_SESSION['balance'] += ($winnings - $betAmount);
        $_SESSION['total_won'] += $winnings;
        $_SESSION['last_guess']['winnings'] = $winnings;
        $_SESSION['last_guess']['net'] = $winnings - $betAmount;
    } else {
        $_SESSION['balance'] -= $betAmount;
        $_SESSION['last_guess']['winnings'] = 0;
        $_SESSION['last_guess']['net'] = -$betAmount;
    }

    $_SESSION['total_bet'] += $betAmount;
    $profit = round($_SESSION['balance'] - $_SESSION['start_money'], 2);

    $currentRound = $_SESSION['round'];

    if ($currentRound >= MAX_ROUNDS && $_SESSION['balance'] < WIN_TARGET) {
        endGame($pdo, $acctId, false, $profit);
    } elseif ($_SESSION['balance'] <= 0) {
        endGame($pdo, $acctId, false, $profit);
    } elseif ($_SESSION['balance'] >= WIN_TARGET) {
        endGame($pdo, $acctId, true, $profit);
    } else {
        $_SESSION['current_index']++;
        $_SESSION['current_asset'] = $_SESSION['next_asset'];
        $_SESSION['next_asset'] = $_SESSION['asset_sequence'][$_SESSION['current_index'] + 1] ?? $_SESSION['current_asset'];
        $_SESSION['round'] = $currentRound + 1;
    }

    return ['success' => true];
}

function cashOut($pdo, $acctId) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game is already over."];
    }
    $profit = round($_SESSION['balance'] - $_SESSION['start_money'], 2);
    endGame($pdo, $acctId, false, $profit, 'cashout');
    return ['success' => true, 'profit' => $profit];
}

function endGame($pdo, $acctId, $won, $profit, $reason = 'completed') {
    $_SESSION['game_over'] = true;
    $_SESSION['game_won'] = $won;
    $_SESSION['final_profit'] = $profit;
    $_SESSION['game_end_reason'] = $reason;

    $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, profit, difficulty) VALUES (?, 0, ?, ?)");
    $stmt->execute([$acctId, $profit, $_SESSION['difficulty'] ?? 'medium']);

    if ($won) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(profit), 0) FROM scores WHERE acct_id = ?");
        $stmt->execute([$acctId]);
        $_SESSION['is_new_record'] = $profit > (float)$stmt->fetchColumn();
    } else {
        // Only consider negative profits for worst-loss record
        $stmt = $pdo->prepare("SELECT COALESCE(MIN(profit), 0) FROM scores WHERE acct_id = ? AND profit < 0");
        $stmt->execute([$acctId]);
        $worstLoss = (float)$stmt->fetchColumn();
        $_SESSION['is_new_record'] = ($profit < 0 && $profit < $worstLoss);
    }
}