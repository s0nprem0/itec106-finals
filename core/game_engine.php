<?php
require_once __DIR__ . '/database.php';

const WIN_TARGET = 30000; // lowred to 30000 for presentation purposes, can be set back to 50000 or higher for more challenge
const MAX_ROUNDS = 20;

// Improved DIFFICULTIES with better balance
const DIFFICULTIES = [
    'easy'   => ['volatility' => 12, 'start_money' => 18000, 'payout' => 1.85, 'label' => 'Easy',    'desc' => '±12%'],
    'medium' => ['volatility' => 22, 'start_money' => 12000, 'payout' => 2.00, 'label' => 'Medium',  'desc' => '±22%'],
    'hard'   => ['volatility' => 32, 'start_money' => 7000,  'payout' => 2.50, 'label' => 'Hard',    'desc' => '±32%'],
];

/**
 * Fetches a random asset (kept for sequence generation).
 */
function getRandomAsset($pdo, $excludeIds = [], $volatilityRange = 20, $currentBasePrice = null) {
    if (!is_array($excludeIds)) $excludeIds = $excludeIds ? [$excludeIds] : [];

    $item = false;

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

/** Pre-generate full sequence for fairness. */
function pregenerateAssetSequence($pdo, $difficulty = 'medium', $numRounds = MAX_ROUNDS) {
    $config = DIFFICULTIES[$difficulty] ?? DIFFICULTIES['medium'];
    $sequence = [];
    $seen = [];
    $current = getRandomAsset($pdo, [], $config['volatility']);
    $sequence[] = $current;
    $seen[] = $current['id'];

    for ($i = 1; $i < $numRounds; $i++) {
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
    $_SESSION['next_asset'] = $_SESSION['asset_sequence'][1] ?? $_SESSION['current_asset'];
    
    $_SESSION['round'] = 1;
    $_SESSION['game_over'] = false;
    $_SESSION['game_won'] = false;
    $_SESSION['game_id'] = uniqid('game_');
    unset($_SESSION['last_guess'], $_SESSION['error_reason'], $_SESSION['game_end_reason']);
}

function processGuess($pdo, $guess, $acctId, $betAmount) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game over. Please start a new game."];
    }
    if (empty($_SESSION['asset_sequence']) || !isset($_SESSION['current_index'])) {
        return ['error' => "Invalid game state. Please restart the game."];
    }

    $betAmount = round(filter_var($betAmount, FILTER_VALIDATE_FLOAT) ?? 0, 2);
    $maxAllowedBet = round($_SESSION['balance'] * 0.25, 2);

    if ($betAmount <= 0 || $betAmount > $_SESSION['balance']) {
        return ['error' => "Bet must be between $0.01 and your current balance."];
    }
    if ($betAmount > $maxAllowedBet) {
        return ['error' => "Maximum investment capped at 25% of balance ($" . number_format($maxAllowedBet, 2) . ")."];
    }

    $config = DIFFICULTIES[$_SESSION['difficulty'] ?? 'medium'];
    $currentPrice = $_SESSION['current_asset']['current_price'];
    $nextPrice = $_SESSION['next_asset']['current_price'];

    // FIX 4: Explicit Tie Logic implementation
    if ($currentPrice === $nextPrice) {
        $resultState = 'tie';
    } else {
        $isHigher = $nextPrice > $currentPrice;
        $isLower = $nextPrice < $currentPrice;
        $result = ($guess === "higher" && $isHigher) || ($guess === "lower" && $isLower);
        $resultState = $result ? 'correct' : 'incorrect';
    }

    $_SESSION['last_guess'] = [
        'prev_item' => $_SESSION['current_asset']['item_name'],
        'prev_price' => $currentPrice,
        'next_item' => $_SESSION['next_asset']['item_name'],
        'next_price' => $nextPrice,
        'guess' => $guess,
        'bet' => $betAmount,
        'multiplier' => $config['payout'],
        'result' => $resultState,
    ];

    if ($resultState === 'correct') {
        $winnings = round($betAmount * $config['payout'], 2);
        $_SESSION['balance'] += ($winnings - $betAmount);
        $_SESSION['total_won'] += $winnings;
        $_SESSION['last_guess']['winnings'] = $winnings;
        $_SESSION['last_guess']['net'] = $winnings - $betAmount;
    } elseif ($resultState === 'tie') {
        // Player's balance is safely untouched, but we record the returned wager
        $_SESSION['last_guess']['winnings'] = $betAmount;
        $_SESSION['last_guess']['net'] = 0;
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

// FIX 6: Adding the missing cashOut handler
function cashOut($pdo, $acctId) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game is already over."];
    }
    if (!isset($_SESSION['balance'])) {
        return ['error' => "No active game to cash out from."];
    }

    $profit = round($_SESSION['balance'] - $_SESSION['start_money'], 2);
    
    // Sets the custom reason to trigger your "Mission Aborted" UI view
    $_SESSION['game_end_reason'] = 'cashout'; 
    
    endGame($pdo, $acctId, false, $profit);
    
    return ['success' => true];
}

function endGame($pdo, $acctId, $won, $profit) {
    $_SESSION['game_over'] = true;
    $_SESSION['game_won'] = $won;
    $_SESSION['final_profit'] = $profit;

    $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, profit, difficulty) VALUES (?, 0, ?, ?)");
    $stmt->execute([$acctId, $profit, $_SESSION['difficulty'] ?? 'medium']);

    if ($won) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(profit), 0) FROM scores WHERE acct_id = ?");
        $stmt->execute([$acctId]);
        $_SESSION['is_new_record'] = $profit > (float)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(MIN(profit), 0) FROM scores WHERE acct_id = ?");
        $stmt->execute([$acctId]);
        $_SESSION['is_new_record'] = $profit < (float)$stmt->fetchColumn();
    }
}