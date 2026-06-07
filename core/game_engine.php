<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

requireLogin();

const DIFFICULTIES = [
    'easy'   => ['volatility' => 10, 'label' => 'Easy',    'desc' => '±10%'],
    'medium' => ['volatility' => 20, 'label' => 'Medium',  'desc' => '±20%'],
    'hard'   => ['volatility' => 35, 'label' => 'Hard',    'desc' => '±35%'],
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
    $_SESSION['score'] = 0;
    $_SESSION['lives'] = 3;
    $_SESSION['current_asset'] = getRandomAsset($pdo, null, $config['volatility']);
    $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['current_asset']['id'], $config['volatility']);
    $_SESSION['round'] = 1;
    $_SESSION['game_over'] = false;
}

function processGuess($pdo, $guess) {
    if (!empty($_SESSION['game_over'])) {
        return ['error' => "Game over. Please start a new game."];
    }

    $config = DIFFICULTIES[$_SESSION['difficulty'] ?? 'medium'];

    $currentPrice = $_SESSION['current_asset']['current_price'];
    $nextPrice = $_SESSION['next_asset']['current_price'];
    $result = null;

    $isHigher = $nextPrice >= $currentPrice;
    $isLower = $nextPrice <= $currentPrice;

    if (($guess === "higher" && $isHigher) || ($guess === "lower" && $isLower)) {
        $_SESSION['score'] += 1;
        $result = "correct";
    } else {
        $_SESSION['lives'] -= 1;
        $result = "incorrect";
    }

    if ($_SESSION['lives'] <= 0) {
        $_SESSION['game_over'] = true;
        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, difficulty) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['acct_id'], $_SESSION['score'], $_SESSION['difficulty'] ?? 'medium']);
    } else {
        $_SESSION['current_asset'] = $_SESSION['next_asset'];
        $_SESSION['next_asset'] = getRandomAsset($pdo, $_SESSION['current_asset']['id'], $config['volatility']);
        $_SESSION['round'] += 1;
    }
}

function saveScore($pdo) {
    if (!empty($_SESSION['game_over']) && isset($_SESSION['acct_id']) && isset($_SESSION['score'])) {
        $stmt = $pdo->prepare("INSERT INTO scores (acct_id, streak, difficulty) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['acct_id'], $_SESSION['score'], $_SESSION['difficulty'] ?? 'medium']);
    }
}
