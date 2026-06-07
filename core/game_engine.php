<?php

require_once __DIR__ . '/database.php';

const WIN_TARGET = 50000;
const MAX_ROUNDS = 20;

// DIFFICULTY TWEAKS: Reduced payouts to require higher win rates
const DIFFICULTIES = [
    'easy'   => ['volatility' => 10, 'start_money' => 15000, 'payout' => 1.2, 'label' => 'Easy',    'desc' => '±10%'],
    'medium' => ['volatility' => 20, 'start_money' => 10000, 'payout' => 1.5, 'label' => 'Medium',  'desc' => '±20%'],
    'hard'   => ['volatility' => 35, 'start_money' => 5000,  'payout' => 2.0, 'label' => 'Hard',    'desc' => '±35%'],
];

/**
 * Fetches a random asset applying market volatility.
 * INCLUDES: Proximity Matchmaking and Anti-Loop Exclusion
 */
function getRandomAsset($pdo, $excludeIds = [], $volatilityRange = 20, $currentBasePrice = null) {
    if (!is_array($excludeIds)) {
        $excludeIds = $excludeIds ? [$excludeIds] : [];
    }

    $item = false;

    // 1. PRIMARY ATTEMPT: Proximity Matchmaking (Find item within 50% - 150% of current price)
    if (!empty($excludeIds) && $currentBasePrice !== null) {
        $minPrice = $currentBasePrice * 0.50;
        $maxPrice = $currentBasePrice * 1.50;

        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $params = array_merge([$minPrice, $maxPrice], $excludeIds);

        $stmt = $pdo->prepare("SELECT * FROM assets WHERE price BETWEEN ? AND ? AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1");
        $stmt->execute($params);
        $item = $stmt->fetch();
    }

    // 2. FALLBACK ATTEMPT: Ignore proximity but exclude previously seen items
    if (!$item && !empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1");
        $stmt->execute($excludeIds);
        $item = $stmt->fetch();
    }

    // 3. FINAL FALLBACK: First item of the game or absolute fallback
    if (!$item) {
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY RAND() LIMIT 1");
        $item = $stmt->fetch();
    }

    // Apply the Volatility Modifier
    if ($item) {
        $volatility = rand(-$volatilityRange, $volatilityRange) / 100;
        $multiplier = 1 + $volatility;
        $item['current_price'] = round($item['price'] * $multiplier, 2);
        $item['volatility'] = ($volatility >= 0 ? "+" : "") . round($volatility * 100) . "%";
    }
    return $item;
}

function initGame($pdo, $difficulty = 'medium') {
    $config = DIFFICULTIES[$difficulty] ?? DIFFICULTIES['medium'];
    $_SESSION['difficulty'] = $difficulty;
    
    // Financial Initializations
    $_SESSION['start_money'] = $config['start_money'];
    $_SESSION['balance'] = $config['start_money'];
    $_SESSION['total_bet'] = 0;
    $_SESSION['total_won'] = 0;
    
    // Anti-Loop Memory Array
    $_SESSION['seen_assets'] = [];

    // Fetch initial assets
    $_SESSION['current_asset'] = getRandomAsset($pdo, [], $config['volatility']);
    $_SESSION['seen_assets'][] = $_SESSION['current_asset']['id'];

    $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['seen_assets'], $config['volatility'], $_SESSION['current_asset']['price']);
    $_SESSION['seen_assets'][] = $_SESSION['next_asset']['id'];

    $_SESSION['round'] = 1;
    $_SESSION['game_over'] = false;
    $_SESSION['game_won'] = false;
    unset($_SESSION['last_guess']);
    unset($_SESSION['error_reason']); // Clear any previous game over reasons
}

function processGuess($pdo, $guess, $acctId, $betAmount) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game over. Please start a new game."];
    }

    // Input Sanitization & Cap
    $betAmount = round(abs($betAmount), 2);
    
    // EXPLOIT PREVENTION: Maximum investment capped at 25% of current balance
    $maxAllowedBet = round($_SESSION['balance'] * 0.25, 2);
    
    if ($betAmount <= 0) {
        return ['error' => "Bet must be greater than $0."];
    }
    if ($betAmount > $maxAllowedBet) {
        return ['error' => "System Restricted: Maximum investment is capped at 25% of total capital ($" . number_format($maxAllowedBet, 2) . ")."];
    }

    $config = DIFFICULTIES[$_SESSION['difficulty'] ?? 'medium'];

    $currentPrice = $_SESSION['current_asset']['current_price'];
    $nextPrice = $_SESSION['next_asset']['current_price'];

    // Inclusive Tie Logic prevents unfair losses if prices exactly match
    $isHigher = $nextPrice >= $currentPrice;
    $isLower = $nextPrice <= $currentPrice;

    $result = ($guess === "higher" && $isHigher) || ($guess === "lower" && $isLower);

    $_SESSION['last_guess'] = [
        'prev_item' => $_SESSION['current_asset']['item_name'],
        'prev_price' => $currentPrice,
        'next_item' => $_SESSION['next_asset']['item_name'],
        'next_price' => $nextPrice,
        'guess' => $guess,
        'bet' => $betAmount,
        'multiplier' => $config['payout'],
    ];

    // Calculate Financial Outcome
    if ($result) {
        $winnings = round($betAmount * $config['payout'], 2);
        $_SESSION['balance'] += $winnings;
        $_SESSION['total_won'] += $winnings;
        $_SESSION['last_guess']['result'] = 'correct';
        $_SESSION['last_guess']['winnings'] = $winnings;
        $_SESSION['last_guess']['net'] = round($winnings, 2);
        $_SESSION['total_bet'] += $betAmount;
    } else {
        $_SESSION['balance'] -= $betAmount;
        $_SESSION['last_guess']['result'] = 'incorrect';
        $_SESSION['last_guess']['winnings'] = round(-$betAmount, 2);
        $_SESSION['last_guess']['net'] = round(-$betAmount, 2);
        $_SESSION['total_bet'] += $betAmount;
    }

    $start_money = $_SESSION['start_money'];
    $profit = round($_SESSION['balance'] - $start_money, 2);

    // WIN/LOSS & SYSTEM AUDIT TRIGGER EXECUTION
    if ($_SESSION['round'] >= MAX_ROUNDS && $_SESSION['balance'] < WIN_TARGET) {
        // AUDIT TRIGGERED: Ran out of turns
        $_SESSION['game_over'] = true;
        $_SESSION['game_won'] = false;
        $_SESSION['final_profit'] = $profit;
        $_SESSION['error_reason'] = "System Audit Triggered: Failed to reach $" . number_format(WIN_TARGET) . " in " . MAX_ROUNDS . " trades.";

        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, profit, difficulty) VALUES (?, ?, ?, ?)");
        $stmt->execute([$acctId, 0, $profit, $_SESSION['difficulty'] ?? 'medium']);
        
    } elseif ($_SESSION['balance'] <= 0) {
        // BANKRUPTCY TRIGGERED: Ran out of money
        $_SESSION['game_over'] = true;
        $_SESSION['game_won'] = false;
        $_SESSION['final_profit'] = $profit;
        $_SESSION['error_reason'] = "Account Liquidated: Complete Bankruptcy.";

        $stmt = $pdo->prepare("SELECT COALESCE(MIN(profit), 0) FROM scores WHERE acct_id = ? AND profit IS NOT NULL");
        $stmt->execute([$acctId]);
        $worst_profit = (float)$stmt->fetchColumn();
        $_SESSION['is_new_record'] = $profit < $worst_profit;

        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, profit, difficulty) VALUES (?, ?, ?, ?)");
        $stmt->execute([$acctId, 0, $profit, $_SESSION['difficulty'] ?? 'medium']);
        
    } elseif ($_SESSION['balance'] >= WIN_TARGET) {
        // VICTORY TRIGGERED: Hit the $50k goal
        $_SESSION['game_over'] = true;
        $_SESSION['game_won'] = true;
        $_SESSION['final_profit'] = $profit;

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(profit), 0) FROM scores WHERE acct_id = ? AND profit IS NOT NULL");
        $stmt->execute([$acctId]);
        $best_profit = (float)$stmt->fetchColumn();
        $_SESSION['is_new_record'] = $profit > $best_profit;

        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, profit, difficulty) VALUES (?, ?, ?, ?)");
        $stmt->execute([$acctId, 0, $profit, $_SESSION['difficulty'] ?? 'medium']);
        
    } else {
        // NEXT ROUND: Shift assets
        $_SESSION['current_asset'] = $_SESSION['next_asset'];
        $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['seen_assets'], $config['volatility'], $_SESSION['current_asset']['price']);
        
        $_SESSION['seen_assets'][] = $_SESSION['next_asset']['id'];
        if (count($_SESSION['seen_assets']) > 20) {
            array_shift($_SESSION['seen_assets']);
        }

        $_SESSION['round'] += 1;
    }

    return ['success' => true];
}