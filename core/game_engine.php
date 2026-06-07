<?php

require_once __DIR__ . '/database.php';

const WIN_TARGET = 50000;

const DIFFICULTIES = [
    'easy'   => ['volatility' => 10, 'start_money' => 15000, 'payout' => 1.5, 'label' => 'Easy',    'desc' => '±10%'],
    'medium' => ['volatility' => 20, 'start_money' => 10000, 'payout' => 2.0, 'label' => 'Medium',  'desc' => '±20%'],
    'hard'   => ['volatility' => 35, 'start_money' => 5000,  'payout' => 3.0, 'label' => 'Hard',    'desc' => '±35%'],
];

function getRandomAsset($pdo, $excludeId = null, $volatilityRange = 20) {
    if ($excludeId) {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id != ? ORDER BY RAND() LIMIT 1");
        $stmt->execute([$excludeId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM assets ORDER BY RAND() LIMIT 1");
    }

    $item = $stmt->fetch();

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
    $_SESSION['start_money'] = $config['start_money'];
    $_SESSION['balance'] = $config['start_money'];
    $_SESSION['total_bet'] = 0;
    $_SESSION['total_won'] = 0;
    $_SESSION['current_asset'] = getRandomAsset($pdo, null, $config['volatility']);
    $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['current_asset']['id'], $config['volatility']);
    $_SESSION['round'] = 1;
    $_SESSION['game_over'] = false;
    $_SESSION['game_won'] = false;
    unset($_SESSION['last_guess']);
}

function processGuess($pdo, $guess, $acctId, $betAmount) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game over. Please start a new game."];
    }

    $betAmount = round(abs($betAmount), 2);
    if ($betAmount <= 0) {
        return ['error' => "Bet must be greater than $0."];
    }
    if ($betAmount > $_SESSION['balance']) {
        return ['error' => "Bet exceeds your current balance."];
    }

    $config = DIFFICULTIES[$_SESSION['difficulty'] ?? 'medium'];

    $currentPrice = $_SESSION['current_asset']['current_price'];
    $nextPrice = $_SESSION['next_asset']['current_price'];

    $isHigher = $nextPrice > $currentPrice;
    $isLower = $nextPrice < $currentPrice;

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

    if ($_SESSION['balance'] <= 0) {
        $_SESSION['game_over'] = true;
        $_SESSION['game_won'] = false;
        $_SESSION['final_profit'] = $profit;

        $stmt = $pdo->prepare("SELECT COALESCE(MIN(profit), 0) FROM scores WHERE acct_id = ? AND profit IS NOT NULL");
        $stmt->execute([$acctId]);
        $worst_profit = (float)$stmt->fetchColumn();
        $_SESSION['is_new_record'] = $profit < $worst_profit;

        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, profit, difficulty) VALUES (?, ?, ?, ?)");
        $stmt->execute([$acctId, 0, $profit, $_SESSION['difficulty'] ?? 'medium']);
    } elseif ($_SESSION['balance'] >= WIN_TARGET) {
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
        $_SESSION['current_asset'] = $_SESSION['next_asset'];
        $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['current_asset']['id'], $config['volatility']);
        $_SESSION['round'] += 1;
    }

    return ['success' => true];
}
